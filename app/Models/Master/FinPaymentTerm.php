<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class FinPaymentTerm extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'fin_payment_term';

    // PK = nomor urut (aturan PK BIGINT 2026-09-30); payment_term_code tetap kunci bisnis UNIQUE yang disimpan dokumen.
    protected $primaryKey = 'id_payment_term';

    protected $fillable = [
        'payment_term_code',
        'term_label',
        'days',
        'sort_order',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'days' => 'integer',
        'activate' => 'boolean',
    ];
}
