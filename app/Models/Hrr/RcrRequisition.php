<?php

namespace App\Models\Hrr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RcrRequisition extends Model
{
    protected $connection = 'tm2hrr';

    protected $table = 'rcr_requisition';

    protected $primaryKey = 'id_requisition';

    protected $fillable = [
        'requisition_no', 'id_track', 'department_id', 'position_title',
        'headcount', 'qualification', 'reason', 'requested_by', 'status',
    ];

    public function track(): BelongsTo
    {
        return $this->belongsTo(RcrTrack::class, 'id_track', 'id_track');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(RcrRequisitionApproval::class, 'id_requisition', 'id_requisition')->orderBy('approval_seq');
    }

    public function vacancies(): HasMany
    {
        return $this->hasMany(VacVacancy::class, 'id_requisition', 'id_requisition');
    }
}
