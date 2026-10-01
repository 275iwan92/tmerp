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

    // PK = id_category (BIGINT auto number, aturan PK 2026-09-30); category_code = kode unik yang dirujuk sup_supplier.category.
    protected $primaryKey = 'id_category';

    protected $fillable = [
        'category_code',
        'description',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
    ];
}
