<?php

namespace App\Models\Hrr;

use Illuminate\Database\Eloquent\Model;

class RcrApprovalMatrix extends Model
{
    public $timestamps = false;

    protected $connection = 'tm2hrr';

    protected $table = 'rcr_approval_matrix';

    protected $primaryKey = 'id_matrix';

    protected $fillable = ['id_track', 'approval_seq', 'approver_role'];
}
