<?php

namespace App\Models\Acc;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinAssetDepr extends Model
{
    protected $connection = 'tm2acc';

    protected $table = 'fin_asset_depr';

    protected $primaryKey = 'depr_id';

    protected $guarded = ['depr_id'];

    protected $casts = ['amount' => 'decimal:4', 'accumulated' => 'decimal:4', 'book_value' => 'decimal:4'];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FinAsset::class, 'asset_id', 'asset_id');
    }
}
