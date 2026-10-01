<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

// Sumber penutup satu kebutuhan booking: STOCK (lokasi) / FG (stok barang jadi) / PO (po_line) / PR (pr_line).
class RmBookingAlloc extends Model
{
    protected $connection = 'tm2inv';

    protected $table = 'rm_booking_alloc';

    protected $primaryKey = 'id_booking_alloc';

    protected $guarded = ['id_booking_alloc'];

    protected $casts = ['source_date' => 'date', 'is_late' => 'boolean'];
}
