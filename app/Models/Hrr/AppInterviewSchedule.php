<?php

namespace App\Models\Hrr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppInterviewSchedule extends Model
{
    public $timestamps = false;

    protected $connection = 'tm2hrr';

    protected $table = 'app_interview_schedule';

    protected $primaryKey = 'id_schedule';

    protected $fillable = ['id_application', 'stage_code', 'scheduled_at', 'interviewer_id', 'location', 'status'];

    protected $casts = ['scheduled_at' => 'datetime'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(AppApplication::class, 'id_application', 'id_application');
    }
}
