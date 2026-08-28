<?php

namespace App\Models\Prd;

use Illuminate\Database\Eloquent\Model;

// Log keputusan PPIC saat interupsi/hot-job -- Tier1 §5A.5/§9.6. Belum ditulis
// runtime di iterasi ini (Interruption Dialog belum dibangun).
class PrdDecisionFeedbackLog extends Model
{
    protected $connection = 'tm2prd';

    protected $table = 'prd_decision_feedback_log';

    protected $primaryKey = 'log_id';

    public $timestamps = false;

    protected $fillable = [
        'event_type', 'wo_id', 'context_customer_id', 'context_machine_id',
        'options_presented', 'option_chosen', 'chosen_by', 'chosen_at',
    ];

    protected $casts = [
        'options_presented' => 'array',
        'chosen_at' => 'datetime',
    ];
}
