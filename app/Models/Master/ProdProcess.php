<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProdProcess extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_process';

    protected $primaryKey = 'id_process';

    // Tahap routing; urutan array = urutan alur produksi, dipakai untuk
    // mengurutkan usulan routing dan mengelompokkan Gantt.
    public const STAGES = [
        'PREPRESS' => 'Pra-Cetak',
        'PRESS' => 'Cetak',
        'POSTPRESS' => 'Pasca-Cetak',
        'FINISHING' => 'Finishing',
        'PACKING' => 'Packing',
        'QC' => 'Quality Control',
        'LOGISTIC' => 'Logistik',
    ];

    public const COST_BASIS = [
        'PER_HOUR' => 'Per Jam',
        'PER_1000' => 'Per 1.000 Unit',
        'PER_PCS' => 'Per Pcs',
        'PER_METER' => 'Per Meter',
        'LUMPSUM' => 'Borongan (Lumpsum)',
    ];

    public const OUTPUT_UOM = [
        'LEMBAR' => 'Sheet (Plano / Print)',
        'PCS' => 'Pcs (Finished Unit)',
        'METER' => 'Running Meter',
        'KG' => 'Kilogram',
        'JAM' => 'Working Hour',
    ];

    protected $fillable = [
        'process_code',
        'process_name',
        'department_id',
        'work_center_id',
        'process_stage',
        'routing_seq',
        'output_uom',
        'gantt_color',
        'inhouse',
        'default_setup_minutes',
        'washup_minutes',
        'default_speed_per_hour',
        'default_waste_pct',
        'default_waste_fixed_qty',
        'default_tolerance_pct',
        'default_manpower',
        'cost_basis',
        'standard_rate',
        'requires_plate',
        'requires_die',
        'requires_tooling',
        'qc_required',
        'qc_blocking',
        'is_bottleneck',
        'allow_parallel',
        'is_manual',
        'default_supplier_id',
        'subcon_lead_days',
        'min_lot_qty',
        'max_lot_qty',
        'skill_code',
        'description',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
        'inhouse' => 'boolean',
        'requires_plate' => 'boolean',
        'requires_die' => 'boolean',
        'requires_tooling' => 'boolean',
        'qc_required' => 'boolean',
        'qc_blocking' => 'boolean',
        'is_bottleneck' => 'boolean',
        'allow_parallel' => 'boolean',
        'is_manual' => 'boolean',
        'routing_seq' => 'integer',
        'default_setup_minutes' => 'integer',
        'washup_minutes' => 'integer',
        'default_speed_per_hour' => 'decimal:2',
        'default_waste_pct' => 'decimal:2',
        'default_waste_fixed_qty' => 'integer',
        'default_tolerance_pct' => 'decimal:2',
        'default_manpower' => 'integer',
        'standard_rate' => 'decimal:4',
        'subcon_lead_days' => 'integer',
        'min_lot_qty' => 'integer',
        'max_lot_qty' => 'integer',
    ];

    // Tidak ada relasi balik ke iqp_part_process (tm2sdi, process_id) — sengaja,
    // master tetap netral. Lihat IqpPartProcess::process().

    public function department(): BelongsTo
    {
        return $this->belongsTo(OrgDepartment::class, 'department_id', 'department_id');
    }

    public function capabilities(): HasMany
    {
        return $this->hasMany(ProdProcessMachine::class, 'process_id', 'id_process');
    }

    // Mesin yang bisa mengerjakan proses ini, mesin utama lebih dulu.
    public function machines(): BelongsToMany
    {
        return $this->belongsToMany(
            ProdMachine::class,
            'prod_process_machine',
            'process_id',
            'machine_id',
            'id_process',
            'machine_id'
        )
            ->withPivot([
                'capability_id', 'preference_rank', 'is_certified', 'setup_minutes',
                'speed_per_hour', 'waste_pct', 'waste_fixed_qty', 'hourly_rate',
                'manpower', 'gsm_min', 'gsm_max', 'note', 'activate',
            ])
            ->orderBy('prod_process_machine.preference_rank')
            ->orderBy('prod_machine.machine_code');
    }

    public function stageLabel(): string
    {
        return self::STAGES[$this->process_stage] ?? '—';
    }

    // Kelengkapan data untuk dipakai engine: tanpa angka-angka ini, scheduling
    // & HPP masih harus menebak. Dipakai kolom "Kesiapan" di grid.
    // Butuh withCount('capabilities') bila cek mesin ikut dinilai.
    public function readinessScore(): int
    {
        $capCount = $this->capabilities_count ?? $this->capabilities()->count();

        $checks = [
            (string) $this->process_code !== '',
            $this->process_stage !== null,
            $this->is_manual || $this->default_speed_per_hour > 0,
            $this->is_manual || $this->default_setup_minutes !== null,
            $this->cost_basis !== null && $this->standard_rate > 0,
            $this->is_manual || $capCount > 0,
        ];

        return (int) round(count(array_filter($checks)) / count($checks) * 100);
    }

    public function precedenceRules(): HasMany
    {
        return $this->hasMany(ProdProcessPrecedence::class, 'process_id', 'id_process')
            ->where('activate', true);
    }

    // Total waktu mesin terpakai per job = setup + jalan + washup. Washup
    // sering terlupakan padahal di offset bisa 20-30 menit per job.
    public function overheadMinutes(): int
    {
        return (int) $this->default_setup_minutes + (int) $this->washup_minutes;
    }
}
