<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\InvBatch;
use App\Models\Master\MatRaw;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdProduct;
use App\Models\Sys\SysConstant;
use RuntimeException;

// Satu pintu untuk membuat dan mengubah batch (master batch v2.01). Layar, penerimaan, backfill, dan QC memanggil kelas ini;
// tidak ada yang menulis inv_batch langsung. Status batch hanya berubah lewat setStatus() (hasil sampling / keputusan QC).
final class BatchService
{
    /** @return array{label: string, tone: string, available: bool, allocatable: bool} bendera ketersediaan satu status */
    public static function flags(string $status): array
    {
        return InvBatch::STATUSES[$status] ?? throw new RuntimeException("Status batch tidak dikenal: {$status}");
    }

    // Stok tanpa batch (data lama) dianggap boleh dipakai; stok berbatch mengikuti bendera status batchnya.
    public static function allocatable(?InvBatch $batch): bool
    {
        return $batch === null || $batch->isAllocatable();
    }

    // Batch yang sudah ada untuk item + lot supplier (huruf besar-kecil dan spasi tepi tidak dibedakan), atau null.
    public static function findByLot(string $class, int $itemId, ?string $supplierLot): ?InvBatch
    {
        $lot = self::normalizeLot($supplierLot);

        return InvBatch::query()->where('stock_class', $class)->where('item_id', $itemId)->whereRaw('UPPER(TRIM(IFNULL(supplier_lot, ?))) = ?', [SysConstant::text('INV_NO_LOT_BATCH_CODE', 'NO-LOT'), $lot])->first();
    }

    /** @return array{id: int, code: string, name: string, shelf_life_days: ?int}|null item master menurut kode (RM = bahan, SFG = part, FG = product) */
    public static function itemByCode(string $class, string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }
        $m = match ($class) {
            'RM' => MatRaw::withoutGlobalScopes()->where('material_code', $code)->first(),
            'SFG' => ProdPart::withoutGlobalScopes()->where('part_code', $code)->first(),
            'FG' => ProdProduct::withoutGlobalScopes()->where('product_code', $code)->first(),
            default => null,
        };

        return $m ? match ($class) {
            'RM' => ['id' => (int) $m->material_id, 'code' => (string) $m->material_code, 'name' => (string) $m->material_name, 'shelf_life_days' => $m->shelf_life_days ? (int) $m->shelf_life_days : null],
            'SFG' => ['id' => (int) $m->id_part, 'code' => (string) $m->part_code, 'name' => (string) $m->part_name, 'shelf_life_days' => null],
            default => ['id' => (int) $m->id_product, 'code' => (string) $m->product_code, 'name' => (string) $m->product_name, 'shelf_life_days' => null],
        } : null;
    }

    // Item bertanggal kedaluwarsa (punya umur simpan di master): tanggal kedaluwarsa batch baru wajib.
    public static function needsExpiry(string $class, int $itemId): bool
    {
        return $class === 'RM' && (int) MatRaw::withoutGlobalScopes()->whereKey($itemId)->value('shelf_life_days') > 0;
    }

    public static function normalizeLot(?string $lot): string
    {
        $lot = strtoupper(trim((string) $lot));

        return $lot === '' ? strtoupper((string) SysConstant::text('INV_NO_LOT_BATCH_CODE', 'NO-LOT')) : $lot;
    }

    /**
     * Pakai batch yang sudah ada untuk item + lot, atau buat baru. Atribut hanya dipakai saat membuat.
     *
     * @param  array<string, mixed>  $attrs  part_id, item_code, item_name, supplier_id, production_date, expiry_date, qc_status, certification, food_grade, grain_direction, notes
     */
    public static function ensure(string $class, int $itemId, ?string $supplierLot, array $attrs, string $login): InvBatch
    {
        return self::findByLot($class, $itemId, $supplierLot) ?? self::create($class, $itemId, $supplierLot, $attrs, $login);
    }

    /** @param  array<string, mixed>  $attrs */
    public static function create(string $class, int $itemId, ?string $supplierLot, array $attrs, string $login): InvBatch
    {
        if (! in_array($class, ['RM', 'SFG', 'FG'], true)) {
            throw new RuntimeException("Kelas stok tidak dikenal: {$class}");
        }
        $status = (string) ($attrs['qc_status'] ?? 'RELEASED');
        self::flags($status);
        $lot = trim((string) $supplierLot);

        return InvBatch::create([
            'batch_no' => InvBatch::nextNo(), 'stock_class' => $class, 'item_id' => $itemId, 'supplier_lot' => $lot === '' ? null : $lot, 'qc_status' => $status,
            'qc_status_by' => $login, 'qc_status_at' => now(), 'certification' => $attrs['certification'] ?? 'NONE', 'food_grade' => (bool) ($attrs['food_grade'] ?? false),
            'created_by' => $login, 'updated_by' => $login,
        ] + array_intersect_key($attrs, array_flip(['part_id', 'item_code', 'item_name', 'supplier_id', 'production_date', 'expiry_date', 'retest_date', 'grain_direction', 'coa_path', 'notes'])));
    }

    // Ubah status mutu batch (hasil sampling di dok, first article di mesin, Usage Decision). Alasan wajib bila batch ditolak atau diblokir.
    public static function setStatus(InvBatch $batch, string $status, string $login, ?string $reason = null): InvBatch
    {
        self::flags($status);
        if (in_array($status, ['REJECTED', 'BLOCKED'], true) && trim((string) $reason) === '') {
            throw new RuntimeException('Alasan wajib diisi untuk status Rejected atau Blocked.');
        }
        $batch->forceFill(['qc_status' => $status, 'qc_status_by' => $login, 'qc_status_at' => now(), 'updated_by' => $login])->save();

        return $batch;
    }
}
