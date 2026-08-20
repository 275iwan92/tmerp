<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

// Lookup deskripsi kategori jenis supplier (ATK/BAHAN BAKU/JASA/dst).
// sup_supplier.category TIDAK di-FK ke sini secara ketat — lihat catatan
// migration 2026_08_18_000006.
class SupCategory extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'sup_category';

    protected $primaryKey = 'category_code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'category_code',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
