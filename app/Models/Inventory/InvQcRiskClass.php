<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

// Kelas risiko A / B / C per kategori material atau per item (item lebih spesifik daripada kategori).
class InvQcRiskClass extends Model
{
    protected $connection = 'tm2inv';

    protected $table = 'inv_qc_risk_class';

    protected $primaryKey = 'id_risk';

    protected $guarded = ['id_risk'];

    protected $casts = ['activate' => 'boolean'];
}
