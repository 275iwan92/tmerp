<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

// Jam terima barang customer per hari (ISO 1 = Monday .. 7 = Sunday). customer_id NULL = jam default semua customer;
// customer yang punya baris sendiri memakai barisnya (per hari), selain itu default.
class CustReceivingHour extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'cust_receiving_hour';

    protected $primaryKey = 'id_receiving_hour';

    protected $fillable = ['customer_id', 'day_of_week', 'is_open', 'open_time', 'close_time', 'break_start', 'break_end', 'notes', 'activate',
        'created_by', 'updated_by'];

    protected $casts = ['is_open' => 'boolean', 'activate' => 'boolean'];
}
