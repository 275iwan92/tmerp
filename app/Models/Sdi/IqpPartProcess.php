<?php

namespace App\Models\Sdi;

use App\Models\Master\ProdProcess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IqpPartProcess extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'iqp_part_process';

    protected $primaryKey = 'id_part_process';

    protected $fillable = [
        'part_id',
        'branch_code',
        'step_seq',
        'flow_type',
        'process_id',
        'process_name',
        'machine_id',
        'qty_factor',
        'note',
        'source',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'qty_factor' => 'decimal:4',
    ];

    public function part(): BelongsTo
    {
        return $this->belongsTo(IqpPart::class, 'part_id', 'id_part');
    }

    // FK lintas-DB (tm2sdi -> tm2mst): process_id = id snapshot routing saat inquiry dibuat.
    public function process(): BelongsTo
    {
        return $this->belongsTo(ProdProcess::class, 'process_id', 'id_process');
    }

    // machine_id -> master mesin: belum ada model (master mesin belum digarap Tier 3A).
}
