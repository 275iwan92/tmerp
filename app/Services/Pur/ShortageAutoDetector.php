<?php

declare(strict_types=1);

namespace App\Services\Pur;

use App\Models\Inventory\InvShortageRequest;
use App\Models\Pur\PvLine;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

// Deteksi kurang kirim OTOMATIS (Q-13): mengisi sendiri "output baik" dan "waste standar" dari data produksi, lalu memakai ShortageDetector::evaluate.
//   Kekurangan = bahan keluar bersih - (output baik + waste standar), dalam satuan bahan.
// Konversi output produk -> satuan bahan (satu-satunya yang bisa ditentukan dari data):
//   output baik (satuan bahan) = prd_wo_material.qty_required x (output baik operasi TERAKHIR WO / prd_wo.qty).
//   qty_required dihitung ProDev untuk qty WO penuh dan SUDAH memuat waste & toleransi seluruh langkah, jadi hasil ini memuat waste standar langkah;
//   waste standar tambahan = cutting_yield_waste_qty x rasio yang sama. Akibatnya deteksi condong longgar (tidak menuduh), bukan ketat.
// Dilewati dengan alasan jelas (tetap input manual) bila: WO belum selesai, operasi terakhir belum punya output, kebutuhan bahan WO 0, satuan bahan WO
// berbeda dengan satuan gudang, atau keluar bahan tidak bernomor batch (tanpa batch tidak diketahui penerimaan mana yang kurang).
final class ShortageAutoDetector
{
    public const SKIPS = [
        'NO_MATERIAL_ROW' => 'The work order has no material row for this item.', 'NO_MATERIAL_NEED' => 'The material need on the work order is 0, so there is nothing to compare with.',
        'NOT_FINISHED' => 'The work order is not finished yet.', 'NO_OUTPUT' => 'The last operation has no output recorded.', 'UOM_DIFFERS' => 'The work order unit differs from the warehouse unit, so output cannot be converted.',
        'NO_BATCH' => 'The material issue has no batch, so the receipt line is unknown.', 'NO_RECEIPT_LINK' => 'No confirmed receipt line belongs to the issued batches.', 'EXISTS' => 'A request for this work order and material exists.',
    ];

    private const RECEIPT_TYPES = ['PO', 'SUBCON', 'CUSTOMER'];

    /**
     * Memeriksa semua pasangan WO + bahan yang sudah mengeluarkan bahan (atau satu WO). Dry run tidak menulis apa pun.
     *
     * @return list<array{wo_no: string, material: string, state: string, candidate: float, detail: string, request_no: ?string}>
     */
    public static function scan(?int $woId = null, bool $dryRun = false, string $login = 'system'): array
    {
        $pairs = DB::connection('tm2inv')->table('v_inv_wo_reconcile')->where('qty_net_used', '>', 0)->when($woId, fn ($q) => $q->where('wo_id', $woId))->orderBy('wo_id')->get(['wo_id', 'material_id']);
        $rows = [];
        foreach ($pairs as $p) {
            $rows[] = self::check((int) $p->wo_id, (int) $p->material_id, $dryRun, $login);
        }

        return $rows;
    }

    /** @return array{wo_no: string, material: string, state: string, candidate: float, detail: string, request_no: ?string} */
    public static function check(int $woId, int $materialId, bool $dryRun, string $login): array
    {
        $recon = ShortageDetector::reconcile($woId, $materialId);
        $base = ['wo_no' => (string) ($recon['wo_no'] ?? $woId), 'material' => (string) ($recon['material_code'] ?? $materialId), 'candidate' => 0.0, 'request_no' => null];
        if (! $recon) {
            return $base + ['state' => 'SKIPPED', 'detail' => self::SKIPS['NO_MATERIAL_ROW']];
        }
        if (InvShortageRequest::query()->where('wo_id', $woId)->where('material_id', $materialId)->exists()) {
            return $base + ['state' => 'SKIPPED', 'detail' => self::SKIPS['EXISTS']];
        }
        $facts = self::productionFacts($woId, $materialId, $recon['uom']);
        if (isset($facts['skip'])) {
            return $base + ['state' => 'SKIPPED', 'detail' => self::SKIPS[$facts['skip']].(isset($facts['extra']) ? ' '.$facts['extra'] : '')];
        }
        $line = self::receiptLine($woId, $materialId);
        if (! $line) {
            return $base + ['state' => 'SKIPPED', 'detail' => self::SKIPS[self::issuedBatches($woId, $materialId) !== [] ? 'NO_RECEIPT_LINK' : 'NO_BATCH']];
        }
        $check = ShortageDetector::evaluate($recon['issued_net'], $facts['output_good'], $facts['waste_std'], ShortageDetector::tolerancePct($line->po_line_id ? (int) $line->po_line_id : null));
        $base['candidate'] = $check['candidate'];
        if (! $check['over_tolerance']) {
            return $base + ['state' => $check['candidate'] > 0 ? 'WITHIN_TOLERANCE' : 'COVERED', 'detail' => ''];
        }
        if ($dryRun) {
            return $base + ['state' => 'WOULD_CREATE', 'detail' => ''];
        }
        try {
            $req = ShortageRequestService::create(['wo_material' => $woId.':'.$materialId, 'pv_line_id' => $line->pv_line_id, 'qty_output_good' => $facts['output_good'], 'qty_waste_std' => $facts['waste_std'],
                'notes' => 'Detected automatically from production output.'], $login);
        } catch (InvalidArgumentException $e) {
            return $base + ['state' => 'SKIPPED', 'detail' => $e->getMessage()];
        }
        $req->forceFill(['detected_source' => 'AUTO'])->save();

        return ['request_no' => $req->request_no] + $base + ['state' => 'CREATED', 'detail' => ''];
    }

    /** @return array{output_good: float, waste_std: float}|array{skip: string, extra?: string} */
    public static function productionFacts(int $woId, int $materialId, string $warehouseUom): array
    {
        $prd = DB::connection('tm2prd');
        $wo = $prd->table('prd_wo')->where('wo_id', $woId)->first(['qty', 'wo_status']);
        $mats = $prd->table('prd_wo_material')->where('wo_id', $woId)->where(fn ($q) => $q->where('material_id', $materialId)->orWhere('substitute_material_id', $materialId))->get(['qty_required', 'cutting_yield_waste_qty', 'uom']);
        if (! $wo || $mats->isEmpty()) {
            return ['skip' => 'NO_MATERIAL_ROW'];
        }
        $need = (float) $mats->sum('qty_required');
        if ($need <= 0 || (float) $wo->qty <= 0) {
            return ['skip' => 'NO_MATERIAL_NEED'];
        }
        $uom = strtoupper(trim((string) $mats->first()->uom));
        if ($uom !== '' && $uom !== strtoupper(trim($warehouseUom))) {
            return ['skip' => 'UOM_DIFFERS', 'extra' => "({$uom} / {$warehouseUom})"];
        }
        $last = $prd->table('prd_wo_operation')->where('wo_id', $woId)->orderByDesc('seq_no')->first(['operation_id', 'task_status']);
        if (! $last || ($wo->wo_status !== 'COMPLETED' && ! in_array($last->task_status, ['DONE', 'QC_PASSED'], true))) {
            return ['skip' => 'NOT_FINISHED'];
        }
        $outputs = $prd->table('prd_wo_operation_output')->where('operation_id', $last->operation_id);
        if (! $outputs->exists()) {
            return ['skip' => 'NO_OUTPUT'];
        }
        $ratio = (float) $outputs->sum('qty_good') / (float) $wo->qty;

        return ['output_good' => round($need * $ratio, 4), 'waste_std' => round((float) $mats->sum('cutting_yield_waste_qty') * $ratio, 4)];
    }

    // Baris penerimaan (Confirmed) dari batch yang paling banyak dikeluarkan ke WO untuk bahan itu.
    private static function receiptLine(int $woId, int $materialId): ?PvLine
    {
        foreach (self::issuedBatches($woId, $materialId) as $batchId) {
            $line = PvLine::query()->with('receipt')->where('i_batch_id', $batchId)->orderByDesc('pv_line_id')->get()
                ->first(fn (PvLine $l) => $l->receipt && $l->receipt->status === 'POSTED' && in_array($l->receipt->i_receipt_type, self::RECEIPT_TYPES, true));
            if ($line) {
                return $line;
            }
        }

        return null;
    }

    /** @return list<int> id batch bahan yang dikeluarkan ke WO, terbanyak dulu */
    private static function issuedBatches(int $woId, int $materialId): array
    {
        return DB::connection('tm2inv')->table('rm_stock_d as d')->join('rm_stock as h', 'h.doc_id', '=', 'd.doc_id')
            ->where('h.wo_id', $woId)->where('h.doc_cat', 'STOCK')->where('h.doc_type', -1)->where('h.flow_status', 'POSTED')->where('d.material_id', $materialId)->whereNotNull('d.batch_id')
            ->groupBy('d.batch_id')->orderByRaw('SUM(d.qty) DESC')->pluck('d.batch_id')->map(fn ($id) => (int) $id)->all();
    }
}
