<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

// Riwayat satu palet: CREATED (label kedatangan), RELABEL (label baru setelah isinya dipakai), MOVED (ikut Stock
// Transfer), CLOSED (habis).
class InvPalletLog extends Model
{
    public const UPDATED_AT = null;

    protected $connection = 'tm2inv';

    protected $table = 'inv_pallet_log';

    protected $primaryKey = 'id_pallet_log';

    protected $guarded = ['id_pallet_log'];

    protected $casts = ['qty_before' => 'float', 'qty_after' => 'float'];
}
