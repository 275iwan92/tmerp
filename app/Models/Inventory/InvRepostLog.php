<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

// Satu baris per jurnal koreksi bertumpuk (D18): jurnal asal -> jurnal koreksi, nilai sebelum / sesudah. Jurnal lama tidak pernah diubah.
class InvRepostLog extends Model
{
    protected $connection = 'tm2inv';

    protected $table = 'inv_repost_log';

    protected $primaryKey = 'id_repost';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['posted_on' => 'date', 'amount_before' => 'float', 'amount_after' => 'float', 'amount_delta' => 'float'];
}
