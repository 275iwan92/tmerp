<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;

// Log negosiasi satu baris CPDEL: siapa (PPIC / Sales / Ops), dari -> ke, alasan, saluran & kontak customer. Hanya ditambah.
class CoDeliveryNegotiation extends Model
{
    public const UPDATED_AT = null;

    protected $connection = 'tm2sdi';

    protected $table = 'co_delivery_negotiation';

    protected $primaryKey = 'id_negotiation';

    protected $fillable = ['delivery_id', 'event', 'actor_role', 'old_date', 'old_time', 'new_date', 'new_time', 'old_qty', 'new_qty', 'ready_at',
        'reason_code', 'channel', 'customer_contact', 'decision', 'note', 'created_by'];

    protected $casts = ['old_date' => 'date', 'new_date' => 'date', 'ready_at' => 'datetime'];
}
