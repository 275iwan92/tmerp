<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

// Rencana sampling QC: ukuran sampel menurut intensitas dan rentang jumlah lot (diisi user, Inventory > Batch & Quality > Sampling Plan).
class InvQcSamplingPlan extends Model
{
    protected $connection = 'tm2inv';

    protected $table = 'inv_qc_sampling_plan';

    protected $primaryKey = 'id_plan';

    protected $guarded = ['id_plan'];

    protected $casts = ['activate' => 'boolean'];
}
