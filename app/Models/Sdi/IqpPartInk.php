<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IqpPartInk extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'iqp_part_ink';

    protected $primaryKey = 'id_part_ink';

    protected $fillable = [
        'part_id',
        'pass_seq',
        'pass_label',
        'unit_no',
        'ink_name',
        'coverage_pct',
        'coverage_cm2',
        'color_hex',
        'source',
        'created_by',
        'updated_by',
    ];

    public function part(): BelongsTo
    {
        return $this->belongsTo(IqpPart::class, 'part_id', 'id_part');
    }
}
