<?php

namespace App\Models\Prd;

use App\Models\Master\ProdMachine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Laporan masalah mesin (30 detik, siapa saja). Kepala maintenance Accept -> MWO; P1 (mesin berhenti) langsung jadi MWO.
class MntRequest extends Model
{
    protected $connection = 'tm2prd';

    protected $table = 'mnt_request';

    protected $primaryKey = 'id_request';

    protected $guarded = ['id_request'];

    protected $casts = ['machine_stopped' => 'boolean', 'request_date' => 'datetime', 'stopped_at' => 'datetime'];

    public const KINDS = ['BREAKDOWN' => 'Breakdown', 'CORRECTIVE' => 'Corrective', 'SAFETY' => 'Safety', 'IMPROVEMENT' => 'Improvement'];

    public function machine(): BelongsTo
    {
        return $this->belongsTo(ProdMachine::class, 'machine_id', 'machine_id');
    }

    public function wo(): BelongsTo
    {
        return $this->belongsTo(MntWo::class, 'wo_id', 'id_wo');
    }
}
