<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Matriks kapabilitas Proses x Mesin. Satu baris = "proses ini BISA dijalankan
// di mesin ini", plus parameter khusus pasangan tersebut.
//
// Kolom parameter yang NULL bukan berarti nol -- artinya "pakai default proses".
// Jangan pernah membaca langsung untuk hitungan; lewat
// App\Services\Production\MachineCapabilityService::resolveParams() supaya
// urutan 3 lapis (pasangan -> proses -> mesin) selalu sama di semua pemakai.
class ProdProcessMachine extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_process_machine';

    protected $primaryKey = 'capability_id';

    protected $fillable = [
        'process_id',
        'machine_id',
        'preference_rank',
        'is_certified',
        'setup_minutes',
        'speed_per_hour',
        'waste_pct',
        'waste_fixed_qty',
        'hourly_rate',
        'manpower',
        'min_sheet_length_mm',
        'min_sheet_width_mm',
        'max_sheet_length_mm',
        'max_sheet_width_mm',
        'gsm_min',
        'gsm_max',
        'note',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_certified' => 'boolean',
        'activate' => 'boolean',
        'preference_rank' => 'integer',
        'setup_minutes' => 'integer',
        'speed_per_hour' => 'decimal:2',
        'waste_pct' => 'decimal:2',
        'waste_fixed_qty' => 'integer',
        'hourly_rate' => 'decimal:4',
        'manpower' => 'integer',
        'min_sheet_length_mm' => 'integer',
        'min_sheet_width_mm' => 'integer',
        'max_sheet_length_mm' => 'integer',
        'max_sheet_width_mm' => 'integer',
        'gsm_min' => 'integer',
        'gsm_max' => 'integer',
    ];

    public function process(): BelongsTo
    {
        return $this->belongsTo(ProdProcess::class, 'process_id', 'id_process');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(ProdMachine::class, 'machine_id', 'machine_id');
    }
}
