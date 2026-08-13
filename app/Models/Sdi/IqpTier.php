<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IqpTier extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'iqp_tier';

    protected $primaryKey = 'id_tier';

    protected $fillable = [
        'inquiry_id',
        'tier_seq',
        'qty',
        'is_primary',
        'discount_item_type',
        'discount_item_value',
        'price_excl',
        'price_incl',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'qty' => 'decimal:4',
        'discount_item_value' => 'decimal:4',
        'price_excl' => 'decimal:4',
        'price_incl' => 'decimal:4',
    ];

    // primary_key_guard = generated column (STORED) di DDL, sengaja tidak masuk
    // $fillable — nilainya dihitung MySQL dari is_primary/inquiry_id, bukan diisi app.

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(IqpInquiry::class, 'inquiry_id', 'id_inquiry');
    }

    public function costingPriceTiers(): HasMany
    {
        return $this->hasMany(IqpCostingPriceTier::class, 'tier_id', 'id_tier');
    }
}
