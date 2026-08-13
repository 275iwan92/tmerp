<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class MatSheetDiscountTier extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'mat_sheet_discount_tier';

    protected $primaryKey = 'id_discount_tier';

    protected $fillable = [
        'supplier_code',
        'rm_from',
        'rm_to',
        'discount_pct',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'rm_from' => 'decimal:2',
        'rm_to' => 'decimal:2',
        'discount_pct' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    // rm < tier terkecil (rm_from paling rendah) = "KURANG" (belum layak produksi
    // sheet, biasanya dialihkan ke part_type lain) — lihat CostingEngine::sheetDiscountRm().
    public static function lookup(string $supplierCode, float $rm): ?float
    {
        return static::query()
            ->where('supplier_code', $supplierCode)
            ->where('is_active', true)
            ->where('rm_from', '<=', $rm)
            ->where(function ($q) use ($rm) {
                $q->whereNull('rm_to')->orWhere('rm_to', '>', $rm);
            })
            ->orderByDesc('rm_from')
            ->value('discount_pct');
    }
}
