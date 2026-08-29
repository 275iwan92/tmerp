<?php

namespace App\Models\Hrr;

use Illuminate\Database\Eloquent\Model;

class AppOfferApproval extends Model
{
    public $timestamps = false;

    protected $connection = 'tm2hrr';

    protected $table = 'app_offer_approval';

    protected $primaryKey = 'id_approval';

    protected $fillable = ['id_offer', 'approval_seq', 'approver_id', 'decision', 'decided_at'];

    protected $casts = ['decided_at' => 'datetime'];
}
