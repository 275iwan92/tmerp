<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Usulan engine target kecepatan (App\Services\Production\SpeedTarget). Nilai berlaku tetap di prod_machine /
// prod_process_machine; baris ini = riwayat usulan + keputusan PPIC (terima/ubah/tolak).
class ProdSpeedSuggestion extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_speed_suggestion';

    protected $primaryKey = 'id_speed_suggestion';

    public const STATUSES = ['PENDING' => 'Pending', 'ACCEPTED' => 'Accepted', 'REJECTED' => 'Rejected', 'SUPERSEDED' => 'Superseded by a new suggestion'];

    protected $fillable = [
        'machine_id', 'capability_id', 'process_name', 'suggested_speed', 'uom', 'source_code', 'confidence', 'sample_size',
        'basis', 'current_value', 'status', 'decided_value', 'decided_by', 'decided_at', 'decision_note', 'computed_at',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'suggested_speed' => 'float', 'current_value' => 'float', 'decided_value' => 'float',
        'decided_at' => 'datetime', 'computed_at' => 'datetime',
    ];

    public function machine(): BelongsTo
    {
        return $this->belongsTo(ProdMachine::class, 'machine_id', 'machine_id');
    }

    public function capability(): BelongsTo
    {
        return $this->belongsTo(ProdProcessMachine::class, 'capability_id', 'capability_id');
    }
}
