<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

// WO yang ditahan karena memakai batch yang kemudian ditolak (telusur maju).
class InvQcHold extends Model
{
    protected $connection = 'tm2inv';

    protected $table = 'inv_qc_hold';

    protected $primaryKey = 'id_hold';

    protected $guarded = ['id_hold'];

    protected $casts = ['held_at' => 'datetime', 'cleared_at' => 'datetime'];

    public $timestamps = false;
}
