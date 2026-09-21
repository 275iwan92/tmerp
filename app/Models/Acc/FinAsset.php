<?php

namespace App\Models\Acc;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinAsset extends Model
{
    protected $connection = 'tm2acc';

    protected $table = 'fin_asset';

    protected $primaryKey = 'asset_id';

    protected $guarded = ['asset_id'];

    protected $casts = ['acquisition_date' => 'date', 'depr_start_date' => 'date', 'next_review_date' => 'date', 'disposal_date' => 'date', 'acquisition_cost' => 'decimal:4', 'residual_value' => 'decimal:4', 'disposal_amount' => 'decimal:4'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinAssetCategory::class, 'category_id', 'category_id');
    }

    public function deprLines(): HasMany
    {
        return $this->hasMany(FinAssetDepr::class, 'asset_id', 'asset_id')->orderBy('fiscal_year')->orderBy('period_no');
    }
}
