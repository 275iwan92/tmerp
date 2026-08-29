<?php

namespace App\Models\Hrr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RcrRequisitionApproval extends Model
{
    public $timestamps = false;

    protected $connection = 'tm2hrr';

    protected $table = 'rcr_requisition_approval';

    protected $primaryKey = 'id_approval';

    protected $fillable = ['id_requisition', 'approval_seq', 'approver_id', 'decision', 'decided_at', 'note'];

    protected $casts = ['decided_at' => 'datetime'];

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(RcrRequisition::class, 'id_requisition', 'id_requisition');
    }
}
