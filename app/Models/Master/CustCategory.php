<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

// Lookup deskripsi kategori tracking pembayaran customer (LANCAR/KURANG
// LANCAR/dst). cust_customer.category TIDAK di-FK ke sini secara ketat —
// lihat catatan migration 2026_08_18_000006.
class CustCategory extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'cust_category';

    protected $primaryKey = 'category_code';

    public $incrementing = false;

    protected $keyType = 'string';

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
