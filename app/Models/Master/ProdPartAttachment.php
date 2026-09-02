<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdPartAttachment extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_part_attachment';

    protected $primaryKey = 'id_attachment';

    public $timestamps = false;

    protected $fillable = [
        'part_id',
        'id_layout_group',
        'file_type',
        'file_path',
        'original_filename',
        'uploaded_at',
        'uploaded_by',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function part(): BelongsTo
    {
        return $this->belongsTo(ProdPart::class, 'part_id', 'id_part');
    }

    public function layoutGroup(): BelongsTo
    {
        return $this->belongsTo(ProdLayoutGroup::class, 'id_layout_group', 'id_layout_group');
    }
}
