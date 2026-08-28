<?php

namespace App\Models\Acc;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinAccount extends Model
{
    protected $connection = 'tm2acc';

    protected $table = 'fin_account';

    protected $primaryKey = 'account_id';

    // account_id manual (TIER_3A_FINANCE.sql §2), bukan AUTO_INCREMENT.
    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'account_id',
        'account_code',
        'account_name',
        'account_type',
        'normal_balance',
        'parent_id',
        'is_recon',
        'is_postable',
        'default_cc_id',
        'tax_scheme_code',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_recon' => 'boolean',
        'is_postable' => 'boolean',
        'activate' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'account_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'account_id');
    }

    public function defaultCostCenter(): BelongsTo
    {
        return $this->belongsTo(FinCostCenter::class, 'default_cc_id', 'cc_id');
    }
}
