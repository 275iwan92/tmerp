<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

// Hasil rekonsiliasi harian nilai persediaan: buku besar (akun persediaan) dibanding kartu stok, per kelas RM / SFG / FG (php artisan inv:reconcile).
class InvReconcileLog extends Model
{
    protected $connection = 'tm2inv';

    protected $table = 'inv_reconcile_log';

    protected $primaryKey = 'id_reconcile';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['recon_date' => 'date', 'gl_value' => 'float', 'ledger_value' => 'float', 'landed_pending' => 'float', 'difference' => 'float'];
}
