<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdPartFinishing extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_part_finishing';

    protected $primaryKey = 'id_part_finishing';

    protected $fillable = [
        'part_id',
        'seq',
        'finishing_type_id',
        'attribute',
        'notes',
        'cost_estimate',
        'created_by',
        'updated_by',
    ];

    public function part(): BelongsTo
    {
        return $this->belongsTo(ProdPart::class, 'part_id', 'id_part');
    }

    public function finishingType(): BelongsTo
    {
        return $this->belongsTo(RefFinishingType::class, 'finishing_type_id', 'id_finishing_type');
    }
}
