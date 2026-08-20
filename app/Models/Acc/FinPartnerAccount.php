<?php

namespace App\Models\Acc;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinPartnerAccount extends Model
{
    protected $connection = 'tm2acc';

    protected $table = 'fin_partner_account';

    protected $primaryKey = 'map_id';

    protected $fillable = [
        'partner_type',
        'partner_id',
        'ar_account_id',
        'ap_account_id',
        'payment_term',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function arAccount(): BelongsTo
    {
        return $this->belongsTo(FinAccount::class, 'ar_account_id', 'account_id');
    }

    public function apAccount(): BelongsTo
    {
        return $this->belongsTo(FinAccount::class, 'ap_account_id', 'account_id');
    }
}
