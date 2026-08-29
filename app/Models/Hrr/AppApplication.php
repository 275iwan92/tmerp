<?php

namespace App\Models\Hrr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppApplication extends Model
{
    public $timestamps = false;

    protected $connection = 'tm2hrr';

    protected $table = 'app_application';

    protected $primaryKey = 'id_application';

    protected $fillable = [
        'id_candidate', 'id_vacancy', 'submission_uuid', 'current_stage_seq',
        'status', 'reject_reason', 'provisioned_employee_id', 'provisioned_at', 'applied_at',
    ];

    protected $casts = ['provisioned_at' => 'datetime', 'applied_at' => 'datetime'];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(CndCandidate::class, 'id_candidate', 'id_candidate');
    }

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(VacVacancy::class, 'id_vacancy', 'id_vacancy');
    }

    public function interviewSchedules(): HasMany
    {
        return $this->hasMany(AppInterviewSchedule::class, 'id_application', 'id_application');
    }

    public function assessmentResults(): HasMany
    {
        return $this->hasMany(AppAssessmentResult::class, 'id_application', 'id_application');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(AppOffer::class, 'id_application', 'id_application')->orderByDesc('id_offer');
    }
}
