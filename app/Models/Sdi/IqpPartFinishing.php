<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Snapshot Inquiry dari prod_part_finishing (finishing yang diminta customer).
// Sumber input engine BOP/BOM -- bukan routing itu sendiri.
class IqpPartFinishing extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'iqp_part_finishing';

    protected $primaryKey = 'id_part_finishing';

    protected $fillable = [
        'part_id',
        'seq',
        'finishing_type_id',
        'finishing_name',
        'attribute',
        'notes',
        'created_by',
        'updated_by',
    ];

    public function part(): BelongsTo
    {
        return $this->belongsTo(IqpPart::class, 'part_id', 'id_part');
    }
}
