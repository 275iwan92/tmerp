<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

// Aturan intensitas sampling per kelas risiko A / B / C (intensitas awal, AQL, pindah ke REDUCED, pulih dari TIGHTENED).
class InvQcIntensityRule extends Model
{
    protected $connection = 'tm2inv';

    protected $table = 'inv_qc_intensity_rule';

    protected $primaryKey = 'id_rule';

    protected $guarded = ['id_rule'];

    protected $casts = ['activate' => 'boolean'];
}
