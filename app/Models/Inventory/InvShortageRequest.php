<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

// Permintaan Kirim Kekurangan ke supplier / subkon / customer (bahan kurang sejak awal, bagian 7.6).
class InvShortageRequest extends Model
{
    protected $connection = 'tm2inv';

    protected $table = 'inv_shortage_request';

    protected $primaryKey = 'id_shortage';

    protected $guarded = ['id_shortage'];

    protected $casts = ['need_date' => 'date', 'window_until' => 'date', 'recount_at' => 'datetime', 'confirmed_at' => 'datetime', 'sent_at' => 'datetime', 'closed_at' => 'datetime', 'debit_note_required' => 'boolean'];
}
