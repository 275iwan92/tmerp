<?php

namespace App\Models\Pur;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrLineAlloc extends Model
{
    protected $connection = 'tm2pur';

    protected $table = 'pr_line_alloc';

    protected $primaryKey = 'alloc_id';

    protected $fillable = [
        'pr_line_id', 'alloc_no', 'i_qty', 'i_supplier_id', 'i_notes', 'c_is_converted',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'c_is_converted' => 'boolean',
    ];

    public function line(): BelongsTo
    {
        return $this->belongsTo(PrLine::class, 'pr_line_id', 'pr_line_id');
    }
}
