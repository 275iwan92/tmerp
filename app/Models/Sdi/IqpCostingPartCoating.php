<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IqpCostingPartCoating extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'iqp_costing_part_coating';

    protected $primaryKey = 'id_coating';

    protected $fillable = [
        'costing_part_id',
        'seq',
        'coating_type',
        'index_coating',
        'c_cost_per_lc',
        'c_cost_per_pc',
        'notes_coating',
    ];

    protected $casts = [
        'index_coating' => 'decimal:4',
        'c_cost_per_lc' => 'decimal:6',
        'c_cost_per_pc' => 'decimal:4',
    ];

    public $timestamps = false;

    public function costingPart(): BelongsTo
    {
        return $this->belongsTo(IqpCostingPart::class, 'costing_part_id', 'id_costing_part');
    }
}
