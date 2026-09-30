<?php

namespace App\Models\Prd;

use Illuminate\Database\Eloquent\Model;

// Checklist WO = salinan (snapshot) tugas plan saat WO lahir + hasil pengerjaan.
class MntWoTask extends Model
{
    protected $connection = 'tm2prd';

    protected $table = 'mnt_wo_task';

    protected $primaryKey = 'id_wo_task';

    protected $guarded = ['id_wo_task'];

    protected $casts = ['is_required' => 'boolean', 'min_value' => 'float', 'max_value' => 'float', 'measured_value' => 'float', 'done_at' => 'datetime'];

    public const RESULTS = ['OK' => 'OK', 'NOT_OK' => 'Not OK', 'NA' => 'N/A'];
}
