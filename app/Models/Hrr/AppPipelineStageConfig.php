<?php

namespace App\Models\Hrr;

use Illuminate\Database\Eloquent\Model;

class AppPipelineStageConfig extends Model
{
    public $timestamps = false;

    protected $connection = 'tm2hrr';

    protected $table = 'app_pipeline_stage_config';

    protected $primaryKey = 'id_stage_config';

    protected $fillable = ['id_track', 'stage_seq', 'stage_code', 'stage_name', 'is_mandatory'];

    protected $casts = ['is_mandatory' => 'boolean'];
}
