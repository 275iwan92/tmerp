<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

// VIEW hanya-baca tm2inv.v_inv_movement: kartu stok RM / SFG / FG dengan kolom seragam (stock_class, item_id, item_code,
// item_name, gudang, lokasi, saldo awal / masuk / keluar / penyesuaian / saldo akhir). doc_id unik di dalam satu stock_class.
class InvMovement extends Model
{
    protected $connection = 'tm2inv';

    protected $table = 'v_inv_movement';

    protected $primaryKey = 'doc_id';

    public $timestamps = false;

    protected $casts = ['doc_date' => 'datetime'];
}
