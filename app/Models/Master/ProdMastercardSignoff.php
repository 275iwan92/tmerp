<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Tanda tangan Master Card (5 role: DESAINER|MARKETING|PPIC|QA|PLANT_MANAGER),
// tabel sudah ada sejak DDL awal TIER_3A. Dipakai prodev-print.blade.php.
class ProdMastercardSignoff extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_mastercard_signoff';

    protected $primaryKey = 'id_signoff';

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'role_name',
        'signed_by',
        'signed_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProdProduct::class, 'product_id', 'id_product');
    }
}
