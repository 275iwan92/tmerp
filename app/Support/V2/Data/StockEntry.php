<?php

declare(strict_types=1);

/*
 * REMARK
 * Tujuan       : Bahan lookup dan baris form untuk dokumen stok manual v2 (Stock In, Stock Out dan Return, lalu Adjustment): cari item per kelas stok (RM bahan, FG product, SFG part), cari bin, cari batch, dan ubah baris dokumen tersimpan menjadi baris x-dl::lines.
 * Sumber hulu  : tm2mst.mat_raw / prod_product / prod_part (activate = 1, sama dengan pencarian v1), tm2mst.wh_loc + wh_warehouse (bin aktif dan usable), tm2inv.inv_batch, baris dokumen rm_stock_d / fg_stock_d / sfg_stock_d.
 * Input        : Kelas stok (RM / FG / SFG), kata cari, id.
 * Output       : Baris lookup (Devlib\Lookup search / find) dan array baris form; hanya baca.
 * Dipakai oleh : App\Support\V2\Lookups (stock_item_rm / _fg / _sfg, stock_bin, stock_batch), layar inv-stock-doc-form, App\Services\Inventory\StockDocEntryService.
 * Status       : Aktif.
 */

namespace App\Support\V2\Data;

use App\Models\Inventory\InvBatch;
use App\Models\Master\MatRaw;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdProduct;
use App\Models\Master\WhLoc;
use Devlib\Locale;

final class StockEntry
{
    /** kelas => [model, kolom id, kolom kode, kolom nama] */
    public const ITEMS = [
        'RM' => [MatRaw::class, 'material_id', 'material_code', 'material_name'],
        'FG' => [ProdProduct::class, 'id_product', 'product_code', 'product_name'],
        'SFG' => [ProdPart::class, 'id_part', 'part_code', 'part_name'],
    ];

    /** kolom id item di baris dokumen */
    public const LINE_ITEM = ['RM' => 'material_id', 'FG' => 'product_id', 'SFG' => 'parts_id'];

    /** @return list<array<string, mixed>> */
    public static function itemSearch(string $class, string $q, int $limit = 30): array
    {
        [$model, $id, $code, $name] = self::ITEMS[$class];

        return $model::withoutGlobalScopes()->where('activate', true)->where(fn ($w) => $w->where($code, 'like', "%{$q}%")->orWhere($name, 'like', "%{$q}%"))
            ->orderBy($name)->limit($limit)->get()->map(fn ($m) => self::itemRow($m, $id, $code, $name))->all();
    }

    /** @return array<string, mixed>|null */
    public static function itemFind(string $class, mixed $value): ?array
    {
        [$model, $id, $code, $name] = self::ITEMS[$class];
        $m = $model::withoutGlobalScopes()->whereKey((int) $value)->first();

        return $m ? self::itemRow($m, $id, $code, $name) : null;
    }

    private static function itemRow($m, string $id, string $code, string $name): array
    {
        return ['id' => (int) $m->{$id}, 'code' => (string) $m->{$code}, 'name' => trim((string) $m->{$name}), 'uom' => (string) $m->uom_stock, 'label' => $m->{$code}.' · '.trim((string) $m->{$name})];
    }

    /** @return list<array<string, mixed>> bin aktif dan usable (Return: semua bin aktif, usable dan scrap) */
    public static function binSearch(string $q, int $limit = 40, bool $usableOnly = true): array
    {
        return self::bins($usableOnly)->where(fn ($w) => $w->where('l.location_code', 'like', "%{$q}%")->orWhere('l.location_name', 'like', "%{$q}%"))
            ->orderBy('l.location_code')->limit($limit)->get()->map(fn ($r) => self::binRow($r))->all();
    }

    /** @return array<string, mixed>|null */
    public static function binFind(mixed $value): ?array
    {
        $r = self::bins(false)->where('l.location_id', (int) $value)->first();

        return $r ? self::binRow($r) : null;
    }

    private static function bins(bool $usableOnly = true)
    {
        $q = WhLoc::query()->from('wh_loc as l')->join('wh_warehouse as w', 'w.id_warehouse', '=', 'l.warehouse_id')
            ->select('l.location_id', 'l.location_code', 'l.location_name', 'l.warehouse_id', 'l.is_usable', 'w.warehouse_code');

        return $usableOnly ? $q->where('l.activate', true)->where('l.is_usable', true) : $q;
    }

    private static function binRow($r): array
    {
        return ['id' => (int) $r->location_id, 'code' => (string) $r->location_code, 'warehouse' => (string) $r->warehouse_code, 'type' => $r->is_usable ? 'Usable' : 'Scrap', 'label' => (string) $r->location_code];
    }

    /** @return list<array<string, mixed>> */
    public static function batchSearch(string $q, int $limit = 40): array
    {
        return InvBatch::query()->where('activate', true)->where(fn ($w) => $w->where('batch_no', 'like', "%{$q}%")->orWhere('supplier_lot', 'like', "%{$q}%")->orWhere('item_name', 'like', "%{$q}%"))
            ->orderByDesc('batch_no')->limit($limit)->get()->map(fn (InvBatch $b) => self::batchRow($b))->all();
    }

    /** @return array<string, mixed>|null */
    public static function batchFind(mixed $value): ?array
    {
        $b = InvBatch::query()->find((int) $value);

        return $b ? self::batchRow($b) : null;
    }

    private static function batchRow(InvBatch $b): array
    {
        return ['id' => (int) $b->id_batch, 'batch_no' => (string) $b->batch_no, 'lot' => (string) ($b->supplier_lot ?: '—'), 'item' => (string) $b->item_name, 'class' => (string) $b->stock_class,
            'expiry' => $b->expiry_date ? Locale::date($b->expiry_date) : '—', 'label' => (string) $b->batch_no];
    }

    /**
     * Baris yang baru dikirim form (old input) + label lookup-nya, supaya tampil utuh sesudah validasi menolak.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function withLabels(string $class, array $rows): array
    {
        return array_map(fn ($r) => $r + [
            'item_id_label' => ! empty($r['item_id']) ? (self::itemFind($class, $r['item_id'])['label'] ?? '') : '',
            'batch_id_label' => ! empty($r['batch_id']) ? (self::batchFind($r['batch_id'])['label'] ?? '') : '',
            'location_id_label' => ! empty($r['location_id']) ? (self::binFind($r['location_id'])['label'] ?? '') : '',
        ], $rows);
    }

    /**
     * Baris dokumen tersimpan -> baris x-dl::lines (id item, label lookup, batch, bin, qty, satuan, harga).
     *
     * @return list<array<string, mixed>>
     */
    public static function rows(string $class, $doc): array
    {
        $col = self::LINE_ITEM[$class];

        return $doc->lines->map(function ($l) use ($class, $col) {
            $item = self::itemFind($class, $l->{$col});
            $bin = $l->location_id ? self::binFind($l->location_id) : null;
            $batch = $l->batch_id ? self::batchFind($l->batch_id) : null;

            return ['item_id' => $l->{$col}, 'item_id_label' => $item['label'] ?? '#'.$l->{$col}, 'batch_id' => $l->batch_id ?: '', 'batch_id_label' => $batch['label'] ?? '',
                'location_id' => $l->location_id ?: '', 'location_id_label' => $bin['label'] ?? '', 'qty' => (float) $l->qty, 'uom_code' => (string) $l->uom_code, 'unit_price' => (float) $l->unit_price,
                'lot_no' => $l->batch_id ? '' : (string) $l->lot_no, 'new_lot' => '', 'new_production' => '', 'new_expiry' => ''];
        })->all();
    }
}
