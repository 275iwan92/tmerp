<?php

namespace App\Models\Hrr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppAssessmentResult extends Model
{
    public $timestamps = false;

    protected $connection = 'tm2hrr';

    protected $table = 'app_assessment_result';

    protected $primaryKey = 'id_result';

    protected $fillable = ['id_application', 'stage_code', 'assessor_id', 'score', 'recommendation', 'notes', 'assessed_at'];

    protected $casts = ['assessed_at' => 'datetime', 'score' => 'decimal:2'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(AppApplication::class, 'id_application', 'id_application');
    }
}
