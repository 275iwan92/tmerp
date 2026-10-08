<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Models\Inventory\InvBatch;
use App\Models\Inventory\InvQcDecision;
use App\Models\Inventory\InvQcHold;
use App\Models\Inventory\InvSupplierClaim;
use App\Models\Master\SupSupplier;
use App\Models\Pur\PvLine;
use App\Models\Pur\PvReceipt;
use App\Services\Inventory\QcDecisionService;
use App\Services\Inventory\QcSamplingService;
use App\Services\Inventory\QcTraceService;
use App\Services\Pur\UrgentReceiptService;
use App\Support\V2\Grid\Inv\SupplierClaimReport;
use Devlib\Locale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Data halaman batch di Inventory > Batch & Quality (Conditional Stock, First Article): layar hanya memanggil kelas ini; aturan di App\Services\Inventory\Qc*.
final class BatchQuality
{
    public static function batch(int $id): ?InvBatch
    {
        return InvBatch::query()->find($id);
    }

    /**
     * Semua yang ditampilkan di halaman satu batch.
     *
     * @return array{supplier: string, received: float, uom: string, plan: array<string, mixed>, age: array<string, mixed>, urgent: ?object, decisions: list<array<string, mixed>>, usage: list<array<string, mixed>>, holds: list<array<string, mixed>>, claims: Collection<int, InvSupplierClaim>}
     */
    public static function facts(InvBatch $batch): array
    {
        $line = DB::connection('tm2pur')->table('pv_line')->where('i_batch_id', $batch->id_batch)->orderBy('pv_line_id')->first();
        $received = (float) DB::connection('tm2pur')->table('pv_line')->where('i_batch_id', $batch->id_batch)->sum('c_qty_accepted');
        $supplier = $batch->supplier_id ? (string) SupSupplier::query()->whereKey($batch->supplier_id)->value('supplier_name') : '';

        return [
            'supplier' => $supplier, 'received' => $received, 'uom' => (string) ($line->i_uom_stock ?? ''),
            'plan' => QcSamplingService::planFor((int) $batch->item_id, $batch->supplier_id ? (int) $batch->supplier_id : null, $received),
            'age' => QcDecisionService::age($batch), 'urgent' => $line && $line->is_urgent ? $line : null,
            'decisions' => $batch->decisions()->orderByDesc('id_decision')->get()->map(fn (InvQcDecision $d) => [
                'at' => Locale::dt($d->decided_at), 'type' => t(QcDecisionService::TYPES[$d->decision_type] ?? $d->decision_type), 'decision' => t(QcDecisionService::DECISIONS[$d->decision] ?? $d->decision),
                'sample' => $d->sample_size !== null ? $d->sample_size.($d->sample_defects !== null ? ' / '.$d->sample_defects : '') : '', 'result' => (string) $d->test_result,
                'reason' => (string) $d->reason, 'by' => $d->decided_by])->all(),
            'usage' => array_map(fn (array $u) => ['doc' => $u['doc_no'], 'date' => $u['doc_date'] ? Locale::d($u['doc_date']) : '', 'wo' => (string) $u['wo_no'], 'qty' => Locale::decimal($u['qty'], 2).' '.$u['uom'],
                'co' => (string) $u['co_no'], 'delivery' => (string) $u['delivery_no']], QcTraceService::forward($batch)),
            'holds' => InvQcHold::query()->where('batch_id', $batch->id_batch)->orderBy('id_hold')->get()->map(fn (InvQcHold $h) => ['wo' => (string) $h->wo_no, 'qty' => Locale::decimal((float) $h->qty_issued, 2),
                'status' => $h->status === 'HELD' ? t('Held') : t('Cleared'), 'since' => Locale::dt($h->held_at)])->all(),
            'claims' => InvSupplierClaim::query()->where('batch_id', $batch->id_batch)->orderBy('id_claim')->get(),
        ];
    }

    /**
     * Baris penerimaan barang untuk bagian URGENT dan Sampling di form Goods Receipt: tanda URGENT, sertifikasi, persetujuan, dan rencana sampling per baris.
     *
     * @return list<array<string, mixed>>
     */
    public static function receiptRows(PvReceipt $pv): array
    {
        $wos = DB::connection('tm2prd')->table('prd_wo')->whereIn('wo_id', $pv->lines->pluck('urgent_wo_id')->filter())->pluck('wo_no', 'wo_id');

        return $pv->lines->sortBy('line_no')->map(function (PvLine $l) use ($pv, $wos) {
            $stock = (bool) ($l->is_stock && $l->item_id);
            $plan = $stock ? QcSamplingService::planFor((int) $l->item_id, $pv->i_supplier_id ? (int) $pv->i_supplier_id : null, (float) $l->i_qty_stock) : null;
            $needs = $l->is_urgent && UrgentReceiptService::needsApproval($l);

            return ['id' => $l->pv_line_id, 'line' => $l->line_no, 'item' => (string) $l->i_description, 'stock' => $stock,
                'cert' => ($l->i_certification !== 'NONE' ? $l->i_certification : '').($l->i_food_grade ? ' '.t('Food grade') : '') ?: '—',
                'urgent' => $l->is_urgent ? 'URGENT · '.$l->urgent_by : '—', 'wo' => (string) ($wos[$l->urgent_wo_id] ?? '—'), 'reason' => (string) ($l->urgent_reason ?: '—'),
                'approval' => $needs ? ($l->urgent_approved_by ? t('Approved by').' '.$l->urgent_approved_by : t('Needs approval')) : '—',
                'sampling' => $plan ? $plan['risk_class'].' · '.t(QcSamplingService::INTENSITIES[$plan['intensity']] ?? $plan['intensity']).' · '.($plan['sample_size'] > 0 ? $plan['sample_size'].' '.t('pcs') : t('certificate only')) : '—'];
        })->values()->all();
    }

    /** @return array<string, string> keputusan yang boleh dipilih di form (label English, diterjemahkan komponen) */
    public static function decisionOptions(): array
    {
        return QcDecisionService::DECISIONS;
    }

    /** @return array<string, string> */
    public static function typeOptions(): array
    {
        return QcDecisionService::TYPES;
    }

    public static function claimStatuses(): array
    {
        return SupplierClaimReport::STATUSES;
    }

    /** @return array<int, string> WO terbaru untuk pilihan (nomor WO saja; datanya dibaca, tidak diubah) */
    public static function workOrders(): array
    {
        return DB::connection('tm2prd')->table('prd_wo')->orderByDesc('wo_id')->limit(300)->pluck('wo_no', 'wo_id')->all();
    }

    /** @return array<int, string> batch yang bisa diuji di mesin: nomor · item · lot · status */
    public static function batchOptions(): array
    {
        return InvBatch::query()->where('activate', true)->whereIn('qc_status', ['CONDITIONAL', 'QUARANTINE', 'RELEASED'])->orderByDesc('id_batch')->limit(300)->get()
            ->mapWithKeys(fn (InvBatch $b) => [$b->id_batch => $b->batch_no.' · '.$b->item_name.' · '.($b->supplier_lot ?: 'NO-LOT').' · '.InvBatch::STATUSES[$b->qc_status]['label']])->all();
    }
}
