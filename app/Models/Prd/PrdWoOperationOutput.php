<?php

namespace App\Models\Prd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Production Output Recording (F15) -- 1 baris = 1 entri catatan hasil aktual,
// boleh berkali-kali per operation (per shift/giliran). qty_good header di
// sini; reject/waste PER TIPE ada di defects() (migration 2026_08_28_000012 --
// direstrukturisasi dari qty_reject/qty_waste polos ke baris rinci ber-tipe).
class PrdWoOperationOutput extends Model
{
    protected $connection = 'tm2prd';

    protected $table = 'prd_wo_operation_output';

    protected $primaryKey = 'output_id';

    public $timestamps = false;

    protected $fillable = [
        'operation_id', 'qty_good', 'shift_note', 'notes', 'recorded_by', 'recorded_at',
    ];

    protected $casts = [
        'qty_good' => 'decimal:4',
        'recorded_at' => 'datetime',
    ];

    public function operation(): BelongsTo
    {
        return $this->belongsTo(PrdWoOperation::class, 'operation_id', 'operation_id');
    }

    public function defects(): HasMany
    {
        return $this->hasMany(PrdWoOperationOutputDefect::class, 'output_id', 'output_id');
    }
}
