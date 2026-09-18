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
        'part_type_code',
        'sheet_rate_id',
        'rm_from',
        'rm_to',
        'discount_pct',
        'effective_date',
        'notes',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'rm_from' => 'decimal:2',
        'rm_to' => 'decimal:2',
        'discount_pct' => 'decimal:4',
        'effective_date' => 'date',
        'activate' => 'boolean',
    ];

    /**
     * Port legacy m_sheet_discount_tier: rm_to INKLUSIF (299.99), tier spesifik
     * substansi/tipe part menang atas NULL, lalu effective_date terbaru yg sudah
     * berlaku. null = tidak ada tier (mis. volume di bawah tier minimum).
     */
    public static function lookup(string $supplierCode, float $rm, ?int $sheetRateId = null, ?string $partType = null): ?float
    {
        $rm = floor($rm * 100) / 100;

        return static::query()
            ->where('supplier_code', $supplierCode)
            ->where('activate', true)
            ->where('effective_date', '<=', now()->toDateString())
            ->where(fn ($q) => $q->whereNull('sheet_rate_id')->orWhere('sheet_rate_id', $sheetRateId))
            ->where(fn ($q) => $q->whereNull('part_type_code')->orWhere('part_type_code', $partType))
            ->where('rm_from', '<=', $rm)
            ->where(fn ($q) => $q->whereNull('rm_to')->orWhere('rm_to', '>=', $rm))
            ->orderByRaw('sheet_rate_id IS NULL, part_type_code IS NULL')
            ->orderByDesc('effective_date')
            ->orderByDesc('rm_from')
            ->value('discount_pct');
    }
}
