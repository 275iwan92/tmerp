<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProdMachine extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_machine';

    protected $primaryKey = 'machine_id';

    // Kondisi mesin HARI INI -- beda dari activate (= masih dimiliki
    // perusahaan). Mesin BREAKDOWN tetap activate=1 supaya histori routing/WO
    // dan matriks kapabilitasnya utuh.
    public const STATUSES = [
        'READY' => 'Ready to run',
        'RUNNING' => 'Running now',
        'SETUP' => 'Make-ready / Setup',
        'MAINTENANCE' => 'Scheduled Maintenance',
        'BREAKDOWN' => 'Broken / Breakdown',
        'IDLE' => 'Idle (No Order)',
        'RETIRED' => 'Retired / Sold',
    ];

    // Status yang membuat mesin tidak bisa menerima jadwal baru.
    public const STATUSES_BLOCKING = ['MAINTENANCE', 'BREAKDOWN', 'RETIRED'];

    public const CRITICALITY = [
        'A' => 'A — Down = production stops',
        'B' => 'B — Down = orders delayed',
        'C' => 'C — A substitute exists',
    ];

    protected $fillable = [
        'division_id',
        'department_id',
        'machine_code',
        'machine_name',
        'nickname',
        'legacy_machine_names',
        'machine_model_id',
        'machine_type',
        'brand_model',
        'year_acquired',
        'acquired_date',
        'serial_no',
        'max_paper_size',
        'max_sheet_length_mm',
        'max_sheet_width_mm',
        'min_sheet_length_mm',
        'min_sheet_width_mm',
        'max_print_length_mm',
        'max_print_width_mm',
        'gripper_margin_mm',
        'plate_size',
        'drying_system',
        'color_count',
        'perfecting',
        'max_colors_per_pass',
        'substrate_types',
        'max_speed_per_hour',
        'speed_target_per_hour',
        'oee_target_pct',
        'mtbf_hours',
        'capacity_per_shift',
        'shifts_per_day',
        'hours_per_shift',
        'operator_min',
        'helper_min',
        'cost_center_code',
        'hourly_rate',
        'power_kw',
        'acquisition_cost',
        'useful_life_years',
        'warranty_until',
        'maintenance_interval_hours',
        'maintenance_interval_days',
        'last_maintenance_date',
        'next_maintenance_date',
        'total_run_hours',
        'total_impressions',
        'service_vendor',
        'criticality',
        'plant_code',
        'location_note',
        'photo_path',
        'gsm_min',
        'gsm_max',
        'notes',
        'machine_status',
        'is_process_machine',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
        'is_process_machine' => 'boolean',
        'perfecting' => 'boolean',
        'year_acquired' => 'integer',
        'acquired_date' => 'date',
        'warranty_until' => 'date',
        'last_maintenance_date' => 'date',
        'next_maintenance_date' => 'date',
        'color_count' => 'integer',
        'max_colors_per_pass' => 'integer',
        'max_speed_per_hour' => 'integer',
        'speed_target_per_hour' => 'integer',
        'oee_target_pct' => 'decimal:2',
        'shifts_per_day' => 'integer',
        'hours_per_shift' => 'decimal:1',
        'operator_min' => 'integer',
        'helper_min' => 'integer',
        'mtbf_hours' => 'integer',
        'hourly_rate' => 'decimal:4',
        'power_kw' => 'decimal:2',
        'acquisition_cost' => 'decimal:2',
        'useful_life_years' => 'integer',
        'maintenance_interval_hours' => 'integer',
        'maintenance_interval_days' => 'integer',
        'total_run_hours' => 'decimal:2',
        'total_impressions' => 'integer',
        'max_sheet_length_mm' => 'integer',
        'max_sheet_width_mm' => 'integer',
        'min_sheet_length_mm' => 'integer',
        'min_sheet_width_mm' => 'integer',
        'max_print_length_mm' => 'integer',
        'max_print_width_mm' => 'integer',
        'gripper_margin_mm' => 'integer',
        'gsm_min' => 'integer',
        'gsm_max' => 'integer',
    ];

    public function division(): BelongsTo
    {
        return $this->belongsTo(OrgDivision::class, 'division_id', 'division_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(OrgDepartment::class, 'department_id', 'department_id');
    }

    public function capabilities(): HasMany
    {
        return $this->hasMany(ProdProcessMachine::class, 'machine_id', 'machine_id');
    }

    // Proses yang bisa dijalankan di mesin ini.
    // Model di pasar yang dipilih user (katalog prod_machine_model).
    public function machineModel(): BelongsTo
    {
        return $this->belongsTo(ProdMachineModel::class, 'machine_model_id', 'id_machine_model');
    }

    public function processes(): BelongsToMany
    {
        return $this->belongsToMany(
            ProdProcess::class,
            'prod_process_machine',
            'machine_id',
            'process_id',
            'machine_id',
            'id_process'
        )
            ->withPivot([
                'capability_id', 'preference_rank', 'is_certified', 'setup_minutes',
                'speed_per_hour', 'waste_pct', 'waste_fixed_qty', 'hourly_rate',
                'manpower', 'gsm_min', 'gsm_max', 'note', 'activate',
            ])
            ->orderBy('prod_process_machine.preference_rank')
            ->orderBy('prod_process.process_name');
    }

    public function statusLabel(): string
    {
        return isset(self::STATUSES[$this->machine_status]) ? t(self::STATUSES[$this->machine_status]) : ($this->machine_status ?? '—');
    }

    public function isSchedulable(): bool
    {
        return $this->activate
            && $this->is_process_machine
            && ! in_array($this->machine_status, self::STATUSES_BLOCKING, true);
    }

    // Jam tersedia/minggu dari kalender mesin -- dasar hitung beban vs kapasitas.
    // NULL bila kalender belum diisi (jangan diasumsikan 24/7).
    public function availableHoursPerWeek(?int $workDays = 6): ?float
    {
        if (! $this->shifts_per_day || ! $this->hours_per_shift) {
            return null;
        }

        return (float) $this->shifts_per_day * (float) $this->hours_per_shift * $workDays;
    }

    // Selisih klaim produsen vs target realistis. >35% biasanya tanda salah
    // satu angka belum diperbarui setelah mesin menua.
    public function speedGapPct(): ?float
    {
        if (! $this->max_speed_per_hour || ! $this->speed_target_per_hour) {
            return null;
        }

        return round(($this->max_speed_per_hour - $this->speed_target_per_hour) / $this->max_speed_per_hour * 100, 1);
    }

    public function maintenanceDueInDays(): ?int
    {
        return $this->next_maintenance_date
            ? (int) now()->startOfDay()->diffInDays($this->next_maintenance_date->startOfDay(), false)
            : null;
    }

    // Kelengkapan data agar mesin bisa dipakai engine imposisi + scheduling
    // + HPP. Ukuran mm dan tarif jam adalah yang paling sering kosong.
    public function readinessScore(): int
    {
        $capCount = $this->capabilities_count ?? $this->capabilities()->count();

        $checks = [
            $this->max_sheet_length_mm > 0 && $this->max_sheet_width_mm > 0,
            $this->speed_target_per_hour > 0 || ! $this->is_process_machine,
            $this->hourly_rate > 0 || ! $this->is_process_machine,
            $this->shifts_per_day > 0 && $this->hours_per_shift > 0,
            $this->department_id !== null,
            ! $this->is_process_machine || $capCount > 0,
        ];

        return (int) round(count(array_filter($checks)) / count($checks) * 100);
    }

    public function changeovers(): HasMany
    {
        return $this->hasMany(ProdMachineChangeover::class, 'machine_id', 'machine_id');
    }

    public function downtimes(): HasMany
    {
        return $this->hasMany(ProdMachineDowntime::class, 'machine_id', 'machine_id');
    }

    // Total kru = operator + pembantu. Dipisah di master karena tarif upah
    // keduanya beda dan Payroll butuh pecahannya.
    public function crewTotal(): ?int
    {
        if ($this->operator_min === null && $this->helper_min === null) {
            return null;
        }

        return (int) $this->operator_min + (int) $this->helper_min;
    }
}
