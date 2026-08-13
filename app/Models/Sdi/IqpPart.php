<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IqpPart extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'iqp_part';

    protected $primaryKey = 'id_part';

    protected $fillable = [
        'inquiry_id',
        'part_seq',
        'part_code',
        'part_name',
        'part_type',
        'qty_per_sku',
        'cfg_color',
        'cfg_ink',
        'dieline_spec',
        'flat_w',
        'flat_h',
        'flat_area',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'dieline_spec' => 'array',
        'qty_per_sku' => 'decimal:4',
        'flat_w' => 'decimal:4',
        'flat_h' => 'decimal:4',
        'flat_area' => 'decimal:4',
    ];

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(IqpInquiry::class, 'inquiry_id', 'id_inquiry');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(IqpPartMaterial::class, 'part_id', 'id_part');
    }

    public function processes(): HasMany
    {
        return $this->hasMany(IqpPartProcess::class, 'part_id', 'id_part');
    }
}
