<?php

namespace App\Models\Hrr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppOffer extends Model
{
    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    protected $connection = 'tm2hrr';

    protected $table = 'app_offer';

    protected $primaryKey = 'id_offer';

    protected $fillable = [
        'id_application', 'previous_offer_id', 'proposed_salary', 'employment_type',
        'start_date', 'status', 'created_by',
    ];

    protected $casts = ['start_date' => 'date', 'proposed_salary' => 'decimal:2'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(AppApplication::class, 'id_application', 'id_application');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(AppOfferApproval::class, 'id_offer', 'id_offer')->orderBy('approval_seq');
    }
}
