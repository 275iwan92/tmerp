<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Models\Inventory\InvBatch;
use App\Models\Inventory\InvPallet;
use App\Models\Inventory\InvQcDecision;
use App\Models\Inventory\InvQcFirstArticle;
use App\Models\Master\MatRaw;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdProduct;
use App\Models\Master\SupSupplier;
use App\Models\Master\WhLoc;
use App\Models\Master\WhWarehouse;
use App\Models\Sys\SysConstant;
use App\Services\Inventory\BatchService;
use App\Services\Inventory\QcDecisionService;
use Devlib\Locale;
use Illuminate\Support\Facades\DB;

// Data form satu batch (Inventory > Batch & Quality > Batch, docs/mockups/inv_v201_batch_form.jpg): tab "Stock of this batch" (saldo per bin dari kartu
// stok + palet aktifnya), "History" (pergerakan kartu stok + perubahan status) dan "Quality decisions" (keputusan + first article). Hanya baca; batch
// disimpan lewat BatchLookup::save dan status berubah lewat QcDecisionService.
final class Batches
{
    // kelas stok => tabel kartu stok
    public const CLASS_LABELS = ['RM' => 'Raw Material', 'SFG' => 'Semi Finished Goods', 'FG' => 'Finished Goods'];

    public const CARDS = ['RM' => 'rm_stockcard', 'SFG' => 'sfg_stockcard', 'FG' => 'fg_stockcard'];

    public static function find(int $id): ?InvBatch
    {
        return InvBatch::query()->find($id);
    }

    /** @return array<int, string> supplier aktif untuk pilihan form */
    public static function supplierOptions(): array
    {
        return SupSupplier::query()->where('activate', true)->orderBy('supplier_name')->pluck('supplier_name', 'id_supplier')->map(fn ($n) => trim((string) $n))->all();
    }

    /**
     * Cari item master semua kelas (RM bahan, SFG part, FG product) menurut kode / nama untuk lookup Item Code form batch.
     * Baris membawa 'class' sehingga memilih item mengisi pilihan Class sekaligus.
     *
     * @return list<array<string, string>>
     */
    public static function itemSearch(string $q, int $limit = 30): array
    {
        $like = fn ($w, string $code, string $name) => $w->where($code, 'like', "%{$q}%")->orWhere($name, 'like', "%{$q}%");
        $sources = ['RM' => [MatRaw::class, 'material_code', 'material_name'], 'SFG' => [ProdPart::class, 'part_code', 'part_name'], 'FG' => [ProdProduct::class, 'product_code', 'product_name']];
        $rows = [];
        foreach ($sources as $class => [$model, $code, $name]) {
            foreach ($model::withoutGlobalScopes()->where('activate', true)->where(fn ($w) => $like($w, $code, $name))->orderBy($name)->limit($limit)->get([$code, $name]) as $m) {
                $rows[] = ['item_code' => (string) $m->{$code}, 'item_name' => trim((string) $m->{$name}), 'class' => $class, 'class_label' => t(self::CLASS_LABELS[$class]),
                    'label' => $m->{$code}.' · '.trim((string) $m->{$name})];
            }
        }

        return array_slice($rows, 0, $limit);
    }

    /** Baris lookup untuk kode yang sudah tersimpan: kelas ikut kelas batch bila diketahui. */
    public static function itemFind(string $code, ?string $class = null): ?array
    {
        foreach (array_keys(self::CLASS_LABELS) as $c) {
            if (($class === null || $class === $c) && ($i = BatchService::itemByCode($c, $code))) {
                return ['item_code' => $i['code'], 'item_name' => $i['name'], 'class' => $c, 'class_label' => t(self::CLASS_LABELS[$c]), 'label' => $i['code'].' · '.$i['name']];
            }
        }

        return null;
    }

    /** Warna tanggal kedaluwarsa: danger = sudah lewat, warn = dalam WH_EXPIRY_SOON_DAYS, null = biasa. */
    public static function expiryTone(InvBatch $batch): ?string
    {
        return match ($batch->expiryState((int) SysConstant::number('WH_EXPIRY_SOON_DAYS', 30))) {
            'EXPIRED' => 'danger', 'SOON' => 'warn', default => null,
        };
    }

    /** @return list<array<string, mixed>> */
    public static function stock(InvBatch $batch): array
    {
        $table = self::CARDS[$batch->stock_class] ?? null;
        if (! $table) {
            return [];
        }
        $cards = DB::connection('tm2inv')->table($table)->where('batch_id', $batch->id_batch)->groupBy('warehouse_id', 'location_id', 'owner_type', 'uom_code')
            ->selectRaw('warehouse_id, location_id, owner_type, uom_code, SUM(IFNULL(qty_in, 0) - IFNULL(qty_out, 0) + IFNULL(qty_adj, 0)) AS net, MIN(CASE WHEN IFNULL(qty_in, 0) > 0 THEN doc_date END) AS arrived')->get();
        $warehouses = WhWarehouse::query()->whereKey($cards->pluck('warehouse_id')->filter()->unique()->all())->pluck('warehouse_name', 'id_warehouse');
        $bins = WhLoc::query()->whereKey($cards->pluck('location_id')->filter()->unique()->all())->pluck('location_code', 'location_id');
        $pallets = InvPallet::query()->where('batch_id', $batch->id_batch)->where('status', 'ACTIVE')->orderBy('pallet_no')->get()->groupBy('location_id');

        return $cards->sortBy(fn ($c) => [(string) ($warehouses[$c->warehouse_id] ?? ''), (string) ($bins[$c->location_id] ?? '')])->map(fn ($c) => [
            'warehouse' => $warehouses[$c->warehouse_id] ?? '—', 'bin' => $bins[$c->location_id] ?? '—',
            'pallet' => ($p = $pallets->get($c->location_id)) ? $p->pluck('pallet_no')->implode(', ') : '—',
            'owner' => ($c->owner_type ?: 'COMPANY') === 'COMPANY' ? 'Company' : 'Customer',
            'arrived' => $c->arrived ? substr((string) $c->arrived, 0, 10) : null, 'qty' => round((float) $c->net, 4), 'unit' => (string) $c->uom_code,
        ])->values()->all();
    }

    // Saldo batch = jumlah kolom qty tab stok (sama dengan kolom On Hand di daftar).
    public static function onHand(InvBatch $batch): float
    {
        return round(array_sum(array_column(self::stock($batch), 'qty')), 4);
    }

    /** @return list<array{at: string, by: string, action: string, note: string}> baris log form (tab History): pergerakan kartu stok + perubahan status, terbaru di atas */
    public static function history(InvBatch $batch): array
    {
        $rows = [];
        if ($table = self::CARDS[$batch->stock_class] ?? null) {
            $cards = DB::connection('tm2inv')->table($table)->where('batch_id', $batch->id_batch)->orderByDesc('doc_date')->orderByDesc('doc_id')->limit(200)->get();
            $bins = WhLoc::query()->whereKey($cards->pluck('location_id')->filter()->unique()->all())->pluck('location_code', 'location_id');
            foreach ($cards as $c) {
                $qty = round((float) $c->qty_in - (float) $c->qty_out + (float) $c->qty_adj, 4);
                $rows[] = ['at' => (string) $c->doc_date, 'by' => (string) $c->create_by, 'action' => 'Stock',
                    'note' => trim(($c->reference_name ?: $c->doc_type).' · '.($bins[$c->location_id] ?? '—').' · '.($qty > 0 ? '+' : '').Locale::decimal($qty, 2).' '.$c->uom_code)];
            }
        }
        foreach (InvQcDecision::query()->where('batch_id', $batch->id_batch)->get() as $d) {
            $rows[] = ['at' => (string) $d->decided_at, 'by' => (string) $d->decided_by, 'action' => 'Status',
                'note' => (InvBatch::STATUSES[$d->status_before]['label'] ?? $d->status_before).' → '.(InvBatch::STATUSES[$d->status_after]['label'] ?? $d->status_after)];
        }
        usort($rows, fn (array $x, array $y) => strcmp($y['at'], $x['at']));

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public static function decisions(InvBatch $batch): array
    {
        return InvQcDecision::query()->where('batch_id', $batch->id_batch)->orderByDesc('id_decision')->get()->map(fn (InvQcDecision $d) => [
            'at' => Locale::dt($d->decided_at), 'type' => QcDecisionService::TYPES[$d->decision_type] ?? $d->decision_type, 'decision' => QcDecisionService::DECISIONS[$d->decision] ?? $d->decision,
            'sample' => $d->sample_size !== null ? $d->sample_size.($d->sample_defects !== null ? ' / '.$d->sample_defects : '') : '—', 'result' => (string) $d->test_result, 'reason' => (string) $d->reason,
            'by' => $d->decided_by])->all();
    }

    /** @return list<array<string, mixed>> */
    public static function firstArticles(InvBatch $batch): array
    {
        return InvQcFirstArticle::query()->where('batch_id', $batch->id_batch)->orderByDesc('id_first_article')->get()->map(fn (InvQcFirstArticle $f) => [
            'at' => Locale::dt($f->checked_at), 'wo' => (string) $f->wo_no, 'result' => $f->result === 'PASS' ? 'Pass' : 'Fail', 'notes' => (string) $f->notes, 'by' => $f->checked_by])->all();
    }
}
