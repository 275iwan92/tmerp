<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IqpProve extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'iqp_prove';

    protected $primaryKey = 'id_prove';

    protected $fillable = [
        'inquiry_id',
        'design_number',
        'final_version',
        'pdf_file',
        'physical_approved_date',
        'is_approved',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'physical_approved_date' => 'date',
        'approved_at' => 'datetime',
        'is_approved' => 'boolean',
    ];

    // 1:1 dengan iqp_inquiry (UNIQUE index uq_prove_inquiry) — belongsTo di sisi ini,
    // hasOne di IqpInquiry::prove().
    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(IqpInquiry::class, 'inquiry_id', 'id_inquiry');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(IqpProveLog::class, 'prove_id', 'id_prove');
    }
}
