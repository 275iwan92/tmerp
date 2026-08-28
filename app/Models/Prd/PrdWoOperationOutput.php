<?php

namespace App\Models\Prd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Production Output Recording (F15) -- 1 baris = 1 entri catatan hasil aktual
// (qty baik/reject/waste), boleh berkali-kali per operation (per shift/giliran).
// Lihat catatan desain di migration 2026_08_28_000010_create_prd_wo_operation_output.
class PrdWoOperationOutput extends Model
{
    protected $connection = 'tm2prd';

    protected $table = 'prd_wo_operation_output';

    protected $primaryKey = 'output_id';

    public $timestamps = false;

    protected $fillable = [
        'operation_id', 'qty_good', 'qty_reject', 'qty_waste',
        'shift_note', 'notes', 'recorded_by', 'recorded_at',
    ];

    protected $casts = [
        'qty_good' => 'decimal:4',
        'qty_reject' => 'decimal:4',
        'qty_waste' => 'decimal:4',
        'recorded_at' => 'datetime',
    ];

    public function operation(): BelongsTo
    {
        return $this->belongsTo(PrdWoOperation::class, 'operation_id', 'operation_id');
    }
}
