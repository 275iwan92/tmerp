<?php

use App\Livewire\Concerns\HasGridStatFilter;
use App\Livewire\Concerns\ManagesProcessMachineCapability;
use App\Models\Master\OrgDepartment;
use App\Models\Master\OrgDivision;
use App\Models\Master\ProdMachine;
use App\Models\Master\ProdMachineDowntime;
use App\Models\Master\ProdProcess;
use App\Models\Master\ProdProcessMachine;
use App\Services\Production\MachineCapabilityService;
use App\Services\Production\MachineOeeService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Master Mesin -> tm2mst.prod_machine. Naik kelas dari daftar aset jadi
// ASSET + CAPABILITY REGISTER pada 2026-09-22 (permintaan user).
//
// Tiga hal yang membuat layar ini beda dari daftar mesin biasa:
//   1. Tab "Proses" = matriks kapabilitas (prod_process_machine). Arah balik
//      dari Master Proses; baris yang sama, sisi yang ditampilkan beda.
//   2. Tab "Perawatan" mencatat downtime NYATA, lalu dipakai menghitung OEE
//      aktual dan MTTR/MTBF -- bukan cuma menyimpan angka target.
//   3. Ukuran lembar disimpan sebagai ANGKA mm. Kolom lama max_paper_size
//      (teks "103x72") dipertahankan untuk tampilan ringkas, tapi engine
//      imposisi & filter mesin membaca kolom mm.
//
// Toggle aktif/nonaktif dipakai alih-alih hard delete: mesin jadi target FK
// logis prod_part_process.machine_id & prd_wo_operation.machine_id -- lihat
// panel Where-used di tab Biaya sebelum menonaktifkan.
new #[Title('Master Mesin — TELASIH MAKMUR ERP')] class extends Component
{
    use HasGridStatFilter;
    use ManagesProcessMachineCapability;

    // Jenis mesin = prefix kode mesin (lihat migration update_prod_machine_asset_list,
    // 2026-09-21). Paper Cutting (guillotine) sengaja DIPISAH dari Die Cutting
    // (plong) -- sebelumnya keduanya bertipe 'Potong' sehingga picker mesin plong
    // di ProDev ikut menarik mesin potong kertas.
    public array $machineTypes = [
        'Offset Printing', 'Digital Printing', 'CTP Plate Setter',
        'Paper Cutting', 'Die Cutting', 'Cutting Plotter',
        'Folder Gluer', 'Laminating', 'UV Coating', 'Water-Based Coating',
        'Waxing', 'Gluing', 'Pressing', 'Shrink Wrapping',
        'Stripping', 'Stitching', 'Seaming', 'Support Equipment',
    ];

    public array $dryingSystems = ['IR' => 'IR Dryer', 'UV' => 'UV Curing', 'HOT_AIR' => 'Hot Air', 'NONE' => 'Tanpa Pengering'];

    public string $search = '';

    public string $typeFilter = '';

    public string $panel = 'grid'; // grid | form

    public string $mode = 'new'; // new | edit

    public string $activeTab = 'identity';

    public ?int $editingId = null;

    // --- tab 1: identitas ---
    public string $machine_code = '';

    public string $machine_name = '';

    public string $machine_type = '';

    public ?int $division_id = null;

    public ?int $department_id = null;

    public string $brand_model = '';

    public string $year_acquired = '';

    public string $acquired_date = '';

    public string $serial_no = '';

    public string $machine_status = 'READY';

    public bool $is_process_machine = true;

    public string $criticality = '';

    public string $plant_code = '';

    public string $location_note = '';

    public string $notes = '';

    public bool $machine_activate = true;

    // --- tab 2: spesifikasi teknis ---
    public string $max_paper_size = '';

    public string $max_sheet_length_mm = '';

    public string $max_sheet_width_mm = '';

    public string $min_sheet_length_mm = '';

    public string $min_sheet_width_mm = '';

    public string $max_print_length_mm = '';

    public string $max_print_width_mm = '';

    public string $gripper_margin_mm = '';

    public string $plate_size = '';

    public string $drying_system = '';

    public string $color_count = '';

    public string $max_colors_per_pass = '';

    public bool $perfecting = false;

    public string $substrate_types = '';

    public string $gsm_min = '';

    public string $gsm_max = '';

    // --- tab 3: kapasitas & performa ---
    public string $max_speed_per_hour = '';

    public string $speed_target_per_hour = '';

    public string $oee_target_pct = '';

    public string $mtbf_hours = '';

    public string $capacity_per_shift = '';

    public string $shifts_per_day = '';

    public string $hours_per_shift = '';

    public string $operator_min = '';

    public string $helper_min = '';

    // --- tab 4: biaya & aset ---
    public string $cost_center_code = '';

    public string $hourly_rate = '';

    public string $power_kw = '';

    public string $acquisition_cost = '';

    public string $useful_life_years = '';

    public string $warranty_until = '';

    // --- tab 5: perawatan ---
    public string $maintenance_interval_hours = '';

    public string $maintenance_interval_days = '';

    public string $last_maintenance_date = '';

    public string $next_maintenance_date = '';

    public string $total_run_hours = '';

    public string $total_impressions = '';

    public string $service_vendor = '';

    // Form catat downtime baru.
    public string $dtKind = 'BREAKDOWN';

    public string $dtStart = '';

    public string $dtEnd = '';

    public string $dtCause = '';

    public string $dtAction = '';

    public int $oeeDays = 30;

    #[Computed]
    public function machines()
    {
        return $this->applyStatFilter(
            ProdMachine::query()
                ->with(['division', 'department'])
                ->withCount(['capabilities' => fn ($q) => $q->where('activate', true)])
                ->when($this->search !== '', fn ($q) => $q->where(fn ($qq) => $qq
                    ->where('machine_code', 'like', "%{$this->search}%")
                    ->orWhere('machine_name', 'like', "%{$this->search}%")
                    ->orWhere('brand_model', 'like', "%{$this->search}%")))
                ->when($this->typeFilter !== '', fn ($q) => $q->where('machine_type', $this->typeFilter))
        )
            ->orderBy('machine_type')->orderBy('machine_code')
            ->get();
    }

    // Skala bar kecepatan: relatif ke mesin TERCEPAT yang sedang tampil.
    // Kecepatan antar-jenis mesin beda orde besaran (guillotine puluhan ribu
    // lembar/jam vs pressing ribuan), jadi skala tetap bikin bar tak terbaca.
    #[Computed]
    public function speedScaleMax(): float
    {
        return max(1, (float) $this->machines->max(fn ($m) => (int) $m->max_speed_per_hour));
    }

    #[Computed]
    public function divisionOptions()
    {
        return OrgDivision::query()->where('activate', true)
            ->orderBy('division_name')->pluck('division_name', 'division_id')->all();
    }

    #[Computed]
    public function departmentOptions()
    {
        return OrgDepartment::query()->where('activate', true)
            ->orderBy('department_name')->pluck('department_name', 'department_id')->all();
    }

    // --- matriks kapabilitas (arah mesin -> proses) ---
    #[Computed]
    public function capabilityRows()
    {
        if (! $this->editingId) {
            return collect();
        }

        return ProdProcessMachine::query()
            ->with('process.department')
            ->where('machine_id', $this->editingId)
            ->get()
            ->filter(fn ($c) => $c->process !== null)
            ->sortBy([
                fn ($a, $b) => ($b->activate <=> $a->activate),
                fn ($a, $b) => ($a->preference_rank <=> $b->preference_rank),
                fn ($a, $b) => strcmp((string) $a->process->process_name, (string) $b->process->process_name),
            ])
            ->values();
    }

    #[Computed]
    public function processCandidates()
    {
        $taken = $this->capabilityRows->where('activate', true)->pluck('process_id')->all();

        return ProdProcess::query()
            ->where('activate', true)
            ->where('is_manual', false)
            ->whereNotIn('id_process', $taken)
            ->orderBy('process_name')
            ->get()
            ->mapWithKeys(fn ($p) => [$p->id_process => ($p->process_code ? $p->process_code.' — ' : '').$p->process_name])
            ->all();
    }

    #[Computed]
    public function resolvedParams()
    {
        if (! $this->editingId) {
            return [];
        }

        $svc = app(MachineCapabilityService::class);

        return $this->capabilityRows
            ->mapWithKeys(fn ($cap) => [$cap->capability_id => [
                'params' => $svc->resolveParams($cap),
                'hours_10k' => $svc->estimateHours($cap, 10000),
            ]])->all();
    }

    #[Computed]
    public function whereUsed()
    {
        return $this->editingId
            ? app(MachineCapabilityService::class)->whereUsedMachine($this->editingId)
            : [];
    }

    // --- perawatan & OEE ---
    #[Computed]
    public function downtimeRows()
    {
        return $this->editingId
            ? ProdMachineDowntime::where('machine_id', $this->editingId)
                ->orderByDesc('start_at')->limit(25)->get()
            : collect();
    }

    #[Computed]
    public function oee()
    {
        return $this->editingId
            ? app(MachineOeeService::class)->calculate(
                $this->editingId,
                now()->subDays($this->oeeDays)->startOfDay(),
                now()->endOfDay()
            )
            : null;
    }

    #[Computed]
    public function reliability()
    {
        return $this->editingId
            ? app(MachineOeeService::class)->reliability(
                $this->editingId,
                now()->subDays($this->oeeDays)->startOfDay(),
                now()->endOfDay()
            )
            : null;
    }

    protected function capabilityOwner(): array
    {
        return ['machine_id' => (int) $this->editingId];
    }

    protected function refreshCapabilityViews(): void
    {
        unset($this->capabilityRows, $this->processCandidates, $this->resolvedParams, $this->machines);
    }

    public function openModal(string $mode, ?int $id = null): void
    {
        $this->resetErrorBag();
        $this->mode = in_array($mode, ['new', 'edit'], true) ? $mode : 'new';
        $this->editingId = $id;
        $this->activeTab = 'identity';
        $this->capEditingId = null;
        $this->capPickId = null;

        if ($this->mode === 'edit' && $id) {
            $m = ProdMachine::findOrFail($id);
            $this->machine_code = $m->machine_code;
            $this->machine_name = $m->machine_name;
            $this->machine_type = (string) $m->machine_type;
            $this->division_id = $m->division_id;
            $this->department_id = $m->department_id;
            $this->brand_model = (string) $m->brand_model;
            $this->year_acquired = (string) ($m->year_acquired ?? '');
            $this->acquired_date = $m->acquired_date?->format('Y-m-d') ?? '';
            $this->serial_no = (string) $m->serial_no;
            $this->machine_status = (string) ($m->machine_status ?: 'READY');
            $this->is_process_machine = (bool) $m->is_process_machine;
            $this->criticality = (string) $m->criticality;
            $this->plant_code = (string) $m->plant_code;
            $this->location_note = (string) $m->location_note;
            $this->notes = (string) $m->notes;
            $this->machine_activate = (bool) $m->activate;

            $this->max_paper_size = (string) $m->max_paper_size;
            foreach (['max_sheet_length_mm', 'max_sheet_width_mm', 'min_sheet_length_mm',
                'min_sheet_width_mm', 'max_print_length_mm', 'max_print_width_mm',
                'gripper_margin_mm', 'color_count', 'max_colors_per_pass', 'gsm_min', 'gsm_max',
                'max_speed_per_hour', 'speed_target_per_hour', 'mtbf_hours', 'shifts_per_day',
                'operator_min', 'helper_min', 'useful_life_years',
                'maintenance_interval_hours', 'maintenance_interval_days', 'total_impressions'] as $f) {
                $this->{$f} = (string) ($m->{$f} ?? '');
            }
            foreach (['oee_target_pct', 'hours_per_shift', 'hourly_rate', 'power_kw',
                'acquisition_cost', 'total_run_hours'] as $f) {
                $this->{$f} = $this->num($m->{$f});
            }
            $this->plate_size = (string) $m->plate_size;
            $this->drying_system = (string) $m->drying_system;
            $this->perfecting = (bool) $m->perfecting;
            $this->substrate_types = (string) $m->substrate_types;
            $this->capacity_per_shift = (string) $m->capacity_per_shift;
            $this->cost_center_code = (string) $m->cost_center_code;
            $this->service_vendor = (string) $m->service_vendor;
            $this->warranty_until = $m->warranty_until?->format('Y-m-d') ?? '';
            $this->last_maintenance_date = $m->last_maintenance_date?->format('Y-m-d') ?? '';
            $this->next_maintenance_date = $m->next_maintenance_date?->format('Y-m-d') ?? '';
        } else {
            $this->reset([
                'machine_code', 'machine_name', 'machine_type', 'division_id', 'department_id',
                'brand_model', 'year_acquired', 'acquired_date', 'serial_no', 'criticality',
                'plant_code', 'location_note', 'notes', 'max_paper_size',
                'max_sheet_length_mm', 'max_sheet_width_mm', 'min_sheet_length_mm', 'min_sheet_width_mm',
                'max_print_length_mm', 'max_print_width_mm', 'gripper_margin_mm', 'plate_size',
                'drying_system', 'color_count', 'max_colors_per_pass', 'substrate_types',
                'gsm_min', 'gsm_max', 'max_speed_per_hour', 'speed_target_per_hour',
                'oee_target_pct', 'mtbf_hours', 'capacity_per_shift', 'shifts_per_day',
                'hours_per_shift', 'operator_min', 'helper_min', 'cost_center_code', 'hourly_rate',
                'power_kw', 'acquisition_cost', 'useful_life_years', 'warranty_until',
                'maintenance_interval_hours', 'maintenance_interval_days', 'last_maintenance_date',
                'next_maintenance_date', 'total_run_hours', 'total_impressions', 'service_vendor',
            ]);
            $this->machine_status = 'READY';
            $this->is_process_machine = true;
            $this->perfecting = false;
            $this->machine_activate = true;
        }

        $this->panel = 'form';
    }

    private function num(mixed $v): string
    {
        return $v === null ? '' : (rtrim(rtrim((string) $v, '0'), '.') ?: '0');
    }

    public function showGrid(): void
    {
        $this->panel = 'grid';
        $this->capEditingId = null;
    }

    public function save(): void
    {
        $unique = 'unique:tm2mst.prod_machine,machine_code';
        if ($this->mode === 'edit' && $this->editingId) {
            $unique .= ','.$this->editingId.',machine_id';
        }

        $data = $this->validate([
            'machine_code' => ['required', 'string', 'max:20', $unique],
            'machine_name' => ['required', 'string', 'max:100'],
            'machine_type' => ['required', 'string', 'max:30', 'in:'.implode(',', $this->machineTypes)],
            'division_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'brand_model' => ['nullable', 'string', 'max:150'],
            'year_acquired' => ['nullable', 'integer', 'min:1950', 'max:'.(date('Y') + 1)],
            'acquired_date' => ['nullable', 'date'],
            'serial_no' => ['nullable', 'string', 'max:100'],
            'machine_status' => ['required', 'string', 'in:'.implode(',', array_keys(ProdMachine::STATUSES))],
            'criticality' => ['nullable', 'string', 'in:A,B,C'],
            'plant_code' => ['nullable', 'string', 'max:20'],
            'location_note' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string'],
            'max_paper_size' => ['nullable', 'string', 'max:50'],
            'max_sheet_length_mm' => ['nullable', 'integer', 'min:0'],
            'max_sheet_width_mm' => ['nullable', 'integer', 'min:0'],
            'min_sheet_length_mm' => ['nullable', 'integer', 'min:0'],
            'min_sheet_width_mm' => ['nullable', 'integer', 'min:0'],
            'max_print_length_mm' => ['nullable', 'integer', 'min:0'],
            'max_print_width_mm' => ['nullable', 'integer', 'min:0'],
            'gripper_margin_mm' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'plate_size' => ['nullable', 'string', 'max:50'],
            'drying_system' => ['nullable', 'string', 'in:IR,UV,HOT_AIR,NONE'],
            'color_count' => ['nullable', 'integer', 'min:0', 'max:255'],
            'max_colors_per_pass' => ['nullable', 'integer', 'min:0', 'max:255'],
            'substrate_types' => ['nullable', 'string', 'max:255'],
            'gsm_min' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'gsm_max' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'max_speed_per_hour' => ['nullable', 'integer', 'min:0'],
            'speed_target_per_hour' => ['nullable', 'integer', 'min:0'],
            'oee_target_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'mtbf_hours' => ['nullable', 'integer', 'min:0'],
            'capacity_per_shift' => ['nullable', 'string', 'max:50'],
            'shifts_per_day' => ['nullable', 'integer', 'min:0', 'max:4'],
            'hours_per_shift' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'operator_min' => ['nullable', 'integer', 'min:0', 'max:255'],
            'helper_min' => ['nullable', 'integer', 'min:0', 'max:255'],
            'cost_center_code' => ['nullable', 'string', 'max:20'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'power_kw' => ['nullable', 'numeric', 'min:0'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            'useful_life_years' => ['nullable', 'integer', 'min:0', 'max:255'],
            'warranty_until' => ['nullable', 'date'],
            'maintenance_interval_hours' => ['nullable', 'integer', 'min:0'],
            'maintenance_interval_days' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'last_maintenance_date' => ['nullable', 'date'],
            'next_maintenance_date' => ['nullable', 'date'],
            'total_run_hours' => ['nullable', 'numeric', 'min:0'],
            'total_impressions' => ['nullable', 'integer', 'min:0'],
            'service_vendor' => ['nullable', 'string', 'max:150'],
        ]);

        // Cek kewajaran yang tidak bisa diserahkan ke rule bawaan.
        foreach ([
            ['max_sheet_length_mm', 'min_sheet_length_mm', 'Panjang lembar maksimum harus >= minimum.', 'specs'],
            ['max_sheet_width_mm', 'min_sheet_width_mm', 'Lebar lembar maksimum harus >= minimum.', 'specs'],
            ['gsm_max', 'gsm_min', 'Gramatur maksimum harus >= minimum.', 'specs'],
        ] as [$maxField, $minField, $message, $tab]) {
            if ($data[$maxField] !== '' && $data[$minField] !== ''
                && (int) $data[$maxField] < (int) $data[$minField]) {
                $this->activeTab = $tab;
                $this->addError($maxField, $message);

                return;
            }
        }

        // Area cetak tidak mungkin lebih besar dari lembar yang masuk mesin.
        if ($data['max_print_length_mm'] !== '' && $data['max_sheet_length_mm'] !== ''
            && (int) $data['max_print_length_mm'] > (int) $data['max_sheet_length_mm']) {
            $this->activeTab = 'specs';
            $this->addError('max_print_length_mm', 'Area cetak tidak boleh lebih panjang dari lembar maksimum.');

            return;
        }

        // Target kecepatan di atas klaim produsen = salah satu angkanya keliru.
        if ($data['speed_target_per_hour'] !== '' && $data['max_speed_per_hour'] !== ''
            && (int) $data['speed_target_per_hour'] > (int) $data['max_speed_per_hour']) {
            $this->activeTab = 'capacity';
            $this->addError('speed_target_per_hour', 'Target kecepatan tidak boleh melebihi kecepatan maksimum produsen.');

            return;
        }

        $payload = collect($data)->map(fn ($v) => $v === '' ? null : $v)->all();
        $payload['perfecting'] = $this->perfecting;
        $payload['is_process_machine'] = $this->is_process_machine;
        $payload['activate'] = $this->machine_activate;
        $payload['machine_code'] = strtoupper($data['machine_code']);
        $payload['updated_by'] = Auth::user()->login;

        if ($this->mode === 'edit' && $this->editingId) {
            ProdMachine::whereKey($this->editingId)->update($payload);
        } else {
            $payload['created_by'] = Auth::user()->login;
            $new = ProdMachine::create($payload);
            // Mesin baru belum punya proses -> belum bisa dipakai routing.
            $this->editingId = $new->machine_id;
            $this->mode = 'edit';
            $this->activeTab = 'processes';
            $this->refreshCapabilityViews();
            $this->resetStatCache();

            return;
        }

        $this->refreshCapabilityViews();
        $this->resetStatCache();
        $this->panel = 'grid';
    }

    public function addDowntime(): void
    {
        if (! $this->editingId) {
            return;
        }

        $data = $this->validate([
            'dtKind' => ['required', 'string', 'in:'.implode(',', array_keys(ProdMachineDowntime::KINDS))],
            'dtStart' => ['required', 'date'],
            'dtEnd' => ['nullable', 'date', 'after_or_equal:dtStart'],
            'dtCause' => ['nullable', 'string', 'max:255'],
            'dtAction' => ['nullable', 'string', 'max:255'],
        ]);

        ProdMachineDowntime::create([
            'machine_id' => $this->editingId,
            'downtime_kind' => $data['dtKind'],
            // Terencana/tidak ditentukan jenisnya, bukan diisi manual -- kalau
            // operator bisa memilih, Availability jadi bisa "dipoles".
            'is_planned' => ProdMachineDowntime::KINDS[$data['dtKind']]['planned'],
            'start_at' => $data['dtStart'],
            'end_at' => $data['dtEnd'] ?: null,
            'root_cause' => $data['dtCause'] ?: null,
            'action_taken' => $data['dtAction'] ?: null,
            'reported_by' => Auth::user()->login,
            'created_by' => Auth::user()->login,
            'updated_by' => Auth::user()->login,
        ]);

        $this->reset(['dtStart', 'dtEnd', 'dtCause', 'dtAction']);
        unset($this->downtimeRows, $this->oee, $this->reliability);
    }

    public function closeDowntime(int $downtimeId): void
    {
        $d = ProdMachineDowntime::findOrFail($downtimeId);
        $d->end_at = now();
        $d->updated_by = Auth::user()->login;
        $d->save();

        unset($this->downtimeRows, $this->oee, $this->reliability);
    }

    public function setOeeDays(int $days): void
    {
        $this->oeeDays = $days;
        unset($this->oee, $this->reliability);
    }

    public function toggleActive(int $id): void
    {
        $m = ProdMachine::findOrFail($id);
        $m->activate = ! $m->activate;
        // Nonaktif di master = mesin tidak dimiliki/dipakai lagi; status
        // operasionalnya ikut RETIRED supaya kedua flag tidak saling bohong.
        $m->machine_status = $m->activate ? 'READY' : 'RETIRED';
        $m->updated_by = Auth::user()->login;
        $m->save();

        unset($this->machines);
        $this->resetStatCache();
    }

    private function resetStatCache(): void
    {
        unset($this->statTotal, $this->statActive, $this->statInactive,
            $this->statDown, $this->statNoProcess, $this->statMaintDue);
    }

    #[Computed]
    public function statTotal(): int
    {
        return ProdMachine::count();
    }

    #[Computed]
    public function statActive(): int
    {
        return ProdMachine::where('activate', true)->count();
    }

    #[Computed]
    public function statInactive(): int
    {
        return ProdMachine::where('activate', false)->count();
    }

    #[Computed]
    public function statDown(): int
    {
        return ProdMachine::where('activate', true)
            ->whereIn('machine_status', ['MAINTENANCE', 'BREAKDOWN'])->count();
    }

    #[Computed]
    public function statNoProcess(): int
    {
        return ProdMachine::where('activate', true)->where('is_process_machine', true)
            ->whereDoesntHave('capabilities', fn ($q) => $q->where('activate', true))
            ->count();
    }

    #[Computed]
    public function statMaintDue(): int
    {
        return ProdMachine::where('activate', true)
            ->whereNotNull('next_maintenance_date')
            ->whereDate('next_maintenance_date', '<=', now()->addDays(7))
            ->count();
    }

    protected function statFilterDefs(): array
    {
        return [
            'total' => ['label' => 'Total Unit Mesin', 'value' => $this->statTotal, 'tone' => 'total',
                'subtitle' => 'Kapasitas produksi pabrik'],
            'active' => ['label' => 'Aktif', 'value' => $this->statActive, 'tone' => 'active',
                'filter' => fn ($q) => $q->where('activate', true)],
            'down' => ['label' => 'Maintenance / Rusak', 'value' => $this->statDown, 'tone' => 'critical',
                'subtitle' => 'Tidak bisa dijadwalkan',
                'filter' => fn ($q) => $q->where('activate', true)
                    ->whereIn('machine_status', ['MAINTENANCE', 'BREAKDOWN'])],
            'maintdue' => ['label' => 'Perawatan Jatuh Tempo', 'value' => $this->statMaintDue, 'tone' => 'warn',
                'subtitle' => '7 hari ke depan',
                'filter' => fn ($q) => $q->where('activate', true)->whereNotNull('next_maintenance_date')
                    ->whereDate('next_maintenance_date', '<=', now()->addDays(7))],
            'noprocess' => ['label' => 'Tanpa Proses', 'value' => $this->statNoProcess, 'tone' => 'extra',
                'subtitle' => 'Belum masuk routing',
                'filter' => fn ($q) => $q->where('activate', true)->where('is_process_machine', true)
                    ->whereDoesntHave('capabilities', fn ($qq) => $qq->where('activate', true))],
            'inactive' => ['label' => 'Nonaktif', 'value' => $this->statInactive, 'tone' => 'critical',
                'filter' => fn ($q) => $q->where('activate', false)],
        ];
    }
};
?>

<div>
    <div class="max-w-[1560px] mx-auto my-5 px-4">

        {{-- ============ GRID (LIST) ============ --}}
        @if ($panel === 'grid')
        <x-grid.stat-filter-row :boxes="$this->statBoxes()" :active="$statFilter" />

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari kode, nama, merk mesin…"
                        class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <x-slot:tools>
                    <select wire:model.live="typeFilter"
                        class="shrink-0 px-2 py-1.5 border border-[#d7dbe2] rounded text-xs bg-white">
                        <option value="">Semua Jenis Mesin</option>
                        @foreach ($machineTypes as $t)
                            <option value="{{ $t }}">{{ $t }}</option>
                        @endforeach
                    </select>
                </x-slot:tools>
                <x-slot:newRecord>
                    <button type="button" wire:click="openModal('new')"
                        class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
                </x-slot:newRecord>
            </x-grid.toolbar>

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            @foreach ([
                                'Kode' => 'w-[78px]', 'Nama Mesin & Merk' => '', 'Jenis' => 'w-[124px]',
                                'Format Max (mm)' => 'w-[112px]', 'Kecepatan / Jam' => 'w-[150px]',
                                'Tarif / Jam' => 'w-[104px]', 'Kru' => 'w-[62px]',
                                'Proses' => 'w-[88px]', 'Kesiapan' => 'w-[100px]', 'Status' => 'w-[118px]',
                            ] as $label => $w)
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] {{ $w }}">{{ $label }}</th>
                            @endforeach
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->machines as $m)
                            @php($ready = $m->readinessScore())
                            @php($due = $m->maintenanceDueInDays())
                            <tr wire:key="machine-{{ $m->machine_id }}" wire:dblclick="openModal('edit', {{ $m->machine_id }})" class="hover:bg-[#f7faff] cursor-pointer" title="Klik 2x untuk buka spesifikasi lengkap">
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[11px] font-mono font-bold px-1.5 py-0.5 rounded bg-[#eef1f5] text-[#2c3e69]">{{ $m->machine_code }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <div class="font-semibold text-[12.5px] truncate">{{ $m->machine_name }}</div>
                                    <div class="text-[10.5px] text-muted truncate">
                                        Merk: {{ $m->brand_model ?: '—' }}{{ $m->year_acquired ? ' ('.$m->year_acquired.')' : '' }}
                                        @if ($m->criticality) <span class="font-bold text-[#b45309]">· Kritis {{ $m->criticality }}</span> @endif
                                    </div>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-[#eff6ff] text-[#1d4ed8]">{{ $m->machine_type }}</span>
                                    @if (! $m->is_process_machine)
                                        <div class="text-[9.5px] text-muted mt-0.5">alat penunjang</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    @if ($m->max_sheet_length_mm && $m->max_sheet_width_mm)
                                        <span class="font-mono text-[11.5px]">{{ $m->max_sheet_length_mm }} × {{ $m->max_sheet_width_mm }}</span>
                                        @if ($m->gsm_min || $m->gsm_max)
                                            <div class="text-[9.5px] text-muted">{{ $m->gsm_min ?: '?' }}–{{ $m->gsm_max ?: '?' }} gsm</div>
                                        @endif
                                    @elseif ($m->max_paper_size)
                                        <span class="text-[10.5px] text-[#c2410c]" title="Masih teks, belum angka mm — engine imposisi tidak bisa memakainya">{{ $m->max_paper_size }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                {{-- Bar kecepatan: pembanding cepat antar-mesin (permintaan user 2026-09-22) --}}
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    @if ($m->max_speed_per_hour)
                                        <div class="text-[11.5px] leading-tight">
                                            <span class="font-mono font-semibold">{{ number_format($m->max_speed_per_hour, 0, ',', '.') }}</span>
                                            <span class="text-[10px] text-muted">SPH</span>
                                            @if ($m->speed_target_per_hour)
                                                <span class="text-[9.5px] text-muted">· target {{ number_format($m->speed_target_per_hour, 0, ',', '.') }}</span>
                                            @endif
                                        </div>
                                        <div class="mt-1 h-1.5 rounded-full bg-[#eef1f5] overflow-hidden relative">
                                            <div class="h-full rounded-full bg-[#2f80ed]"
                                                style="width:{{ max(3, round($m->max_speed_per_hour / $this->speedScaleMax * 100)) }}%"></div>
                                            @if ($m->speed_target_per_hour)
                                                {{-- Penanda target di dalam bar: selisih klaim produsen vs realistis --}}
                                                <span class="absolute top-0 h-full w-[2px] bg-[#0f172a]"
                                                    style="left:{{ min(99, max(1, round($m->speed_target_per_hour / $this->speedScaleMax * 100))) }}%"
                                                    title="Target kecepatan aktual"></span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-[10.5px] text-muted">N/A (Manual/Potong)</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    @if ($m->hourly_rate > 0)
                                        <span class="font-mono text-[11.5px]">{{ number_format((float) $m->hourly_rate, 0, ',', '.') }}</span>
                                    @elseif ($m->is_process_machine)
                                        <span class="text-[10.5px] text-[#c2410c]" title="Tarif BOP belum ada — HPP mesin ini tidak bisa dihitung">belum diisi</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] font-mono text-[11.5px]">
                                    {{ $m->crewTotal() ?? '—' }}
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    @if ($m->capabilities_count > 0)
                                        <button type="button" wire:click="openModal('edit', {{ $m->machine_id }})"
                                            class="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded bg-[#eaf7ee] text-[#166534] cursor-pointer"
                                            title="Lihat proses yang bisa dijalankan di mesin ini">
                                            <i class="fa-solid fa-diagram-project text-[9px]"></i> {{ $m->capabilities_count }}
                                        </button>
                                    @elseif (! $m->is_process_machine)
                                        <span class="text-muted">—</span>
                                    @else
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-[#fef2f2] text-[#b91c1c]" title="Mesin ini tidak akan muncul di picker routing">belum ada</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[11px] font-mono font-bold px-2 py-0.5 rounded {{ $ready >= 80 ? 'bg-[#eaf7ee] text-[#166534]' : ($ready >= 50 ? 'bg-[#fff7ed] text-[#c2410c]' : 'bg-[#fef2f2] text-[#b91c1c]') }}"
                                        title="Kelengkapan data agar bisa dipakai engine">{{ $ready }}%</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    @php($tone = match ($m->machine_status) {
                                        'READY', 'RUNNING' => 'bg-[#eaf7ee] text-[#1f8a4c]',
                                        'SETUP', 'IDLE' => 'bg-[#eff6ff] text-[#1d4ed8]',
                                        'MAINTENANCE' => 'bg-[#fff7ed] text-[#c2410c]',
                                        'BREAKDOWN' => 'bg-[#fef2f2] text-[#b91c1c]',
                                        default => 'bg-[#eef0f3] text-[#8a8f98]',
                                    })
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block {{ $tone }}">{{ $m->statusLabel() }}</span>
                                    @if (! $m->activate)
                                        <div class="text-[9.5px] text-muted mt-0.5">nonaktif di master</div>
                                    @elseif ($due !== null && $due <= 7)
                                        <div class="text-[9.5px] font-bold {{ $due < 0 ? 'text-[#b91c1c]' : 'text-[#c2410c]' }} mt-0.5">
                                            {{ $due < 0 ? 'Perawatan lewat '.abs($due).' hr' : 'Perawatan '.($due === 0 ? 'hari ini' : $due.' hr lagi') }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-pen" title="Edit" action="openModal('edit', {{ $m->machine_id }})" />
                                    <x-grid.action-icon :active="$m->activate" :title="$m->activate ? 'Nonaktifkan' : 'Aktifkan'" action="toggleActive({{ $m->machine_id }})"
                                        :confirm="($m->activate ? 'Nonaktifkan' : 'Aktifkan').' mesin '.$m->machine_code.'?'"
                                        :variant="$m->activate ? 'danger' : 'default'" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="11" message="Tidak ada." />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
        @endif

        {{-- ============ FORM (NEW/EDIT) ============ --}}
        @if ($panel === 'form')
            <div class="flex justify-between items-center bg-navy text-white px-4 py-3 rounded-t-md">
                <div class="text-sm font-bold">
                    {{ $mode === 'edit' ? 'Edit Mesin : '.$machine_code : 'Mesin Baru' }}
                </div>
                @if ($mode === 'edit')
                    <div class="text-[11px] opacity-80">{{ $this->capabilityRows->where('activate', true)->count() }} proses bisa dijalankan</div>
                @endif
            </div>

            <div class="bg-white border border-t-0 border-[#d7dbe2] rounded-b-md overflow-hidden">
                <div class="flex bg-[#eef1f5] border-b border-[#d7dbe2] flex-wrap">
                    @foreach ([
                        'identity' => '1. Identitas Mesin',
                        'specs' => '2. Spesifikasi Teknis',
                        'capacity' => '3. Kapasitas & Performa',
                        'finance' => '4. Biaya & Aset',
                        'maintenance' => '5. Perawatan & OEE',
                        'processes' => '6. Proses',
                    ] as $key => $label)
                        <button type="button" wire:click="$set('activeTab', '{{ $key }}')"
                            class="px-3.5 py-2 text-xs font-semibold border-r border-[#d7dbe2] {{ $activeTab === $key ? 'bg-white text-accent border-b-2 border-b-accent' : 'text-[#4b5568]' }}">
                            {{ $label }}
                            @if ($key === 'processes' && $mode === 'edit')
                                <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded-full {{ $this->capabilityRows->where('activate', true)->count() > 0 ? 'bg-[#eaf7ee] text-[#166534]' : 'bg-[#fef2f2] text-[#b91c1c]' }}">{{ $this->capabilityRows->where('activate', true)->count() }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>

                <form wire:submit="save" class="p-5">
                    {{-- TAB 1: IDENTITAS --}}
                    <div @if ($activeTab !== 'identity') style="display:none" @endif>
                        <div class="[display:grid] grid-cols-1 md:grid-cols-3 gap-3">
                            <x-form.input label="Kode Asset / Mesin *" name="machine_code" wire:model="machine_code" placeholder="mis. OFF-01" hint="Prefix jenis + nomor urut" />
                            <x-form.select label="Kondisi Status" name="machine_status" wire:model="machine_status" :placeholder="false" :options="\App\Models\Master\ProdMachine::STATUSES" />
                            <x-form.select label="Status Master" wire:model="machine_activate" :placeholder="false" :options="['1' => 'Aktif (dimiliki)', '0' => 'Nonaktif (dilepas)']" />
                        </div>
                        <p class="text-[11px] text-muted mt-1.5">Dua status sengaja dipisah: <strong>Kondisi</strong> berubah harian (rusak/perawatan), <strong>Status Master</strong> hanya saat mesin benar-benar dilepas. Mesin rusak tetap aktif di master supaya histori routing & WO-nya utuh.</p>

                        <div class="mt-3">
                            <x-form.input label="Nama & Model Mesin *" name="machine_name" wire:model="machine_name" placeholder="mis. Heidelberg Speedmaster CD 102" />
                        </div>

                        <div class="[display:grid] grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                            <x-form.select label="Klasifikasi Tipe *" name="machine_type" wire:model="machine_type" :options="array_combine($machineTypes, $machineTypes)" placeholder="— pilih jenis —" />
                            <x-form.input label="Merk / Produsen" name="brand_model" wire:model="brand_model" placeholder="mis. Heidelberg" />
                        </div>

                        <div class="[display:grid] grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                            <x-form.select label="Divisi Produksi" name="division_id" wire:model="division_id" :options="$this->divisionOptions" placeholder="— pilih divisi —" />
                            <x-form.select label="Departemen Pengelola" name="department_id" wire:model="department_id" :options="$this->departmentOptions" placeholder="— pilih departemen —" />
                        </div>

                        <div class="[display:grid] grid-cols-1 md:grid-cols-3 gap-3 mt-3">
                            <x-form.input label="Tahun Pembuatan/Install" type="number" name="year_acquired" wire:model="year_acquired" placeholder="mis. 2018" />
                            <x-form.date-picker label="Tanggal Datang" name="acquired_date" wire:model="acquired_date" hint="Kosongkan bila hanya tahun diketahui" />
                            <x-form.input label="Nomor Seri" name="serial_no" wire:model="serial_no" />
                        </div>

                        <div class="[display:grid] grid-cols-1 md:grid-cols-3 gap-3 mt-3">
                            <x-form.select label="Tingkat Kritis" name="criticality" wire:model="criticality" :options="\App\Models\Master\ProdMachine::CRITICALITY" placeholder="— belum dinilai —" />
                            <x-form.input label="Kode Plant / Pabrik" name="plant_code" wire:model="plant_code" placeholder="mis. P1" />
                            <x-form.input label="Letak di Lantai Produksi" name="location_note" wire:model="location_note" placeholder="mis. Blok B, dekat gudang plate" />
                        </div>

                        <div class="mt-3 border border-[#d7dbe2] rounded-md p-3">
                            <x-form.checkbox wire:model="is_process_machine">
                                <span class="font-semibold">Mesin proses produksi</span>
                                <span class="block text-[10.5px] text-muted">Hapus centang untuk alat penunjang (kompresor, forklift) — tidak akan muncul di picker routing dan tidak dihitung sebagai mesin yang "belum dipetakan".</span>
                            </x-form.checkbox>
                        </div>

                        <div class="mt-3">
                            <x-form.textarea label="Catatan" name="notes" wire:model="notes" autogrow />
                        </div>
                    </div>

                    {{-- TAB 2: SPESIFIKASI TEKNIS --}}
                    <div @if ($activeTab !== 'specs') style="display:none" @endif>
                        <div class="border border-[#d7dbe2] rounded-md p-3">
                            <p class="text-[10px] font-bold text-accent uppercase mb-2">Media Handling — Ukuran Lembar</p>
                            <p class="text-[11px] text-muted mb-2.5">Isi dalam <strong>milimeter</strong>. Angka inilah yang dipakai engine imposisi & filter "mesin mana yang muat plano ini" — kolom teks ringkas di bawah hanya untuk tampilan.</p>
                            <div class="[display:grid] grid-cols-2 md:grid-cols-4 gap-3">
                                <x-form.input label="Panjang Maks (mm)" type="number" name="max_sheet_length_mm" wire:model="max_sheet_length_mm" placeholder="1030" />
                                <x-form.input label="Lebar Maks (mm)" type="number" name="max_sheet_width_mm" wire:model="max_sheet_width_mm" placeholder="720" />
                                <x-form.input label="Panjang Min (mm)" type="number" name="min_sheet_length_mm" wire:model="min_sheet_length_mm" placeholder="340" />
                                <x-form.input label="Lebar Min (mm)" type="number" name="min_sheet_width_mm" wire:model="min_sheet_width_mm" placeholder="480" />
                            </div>
                            <div class="[display:grid] grid-cols-1 md:grid-cols-3 gap-3 mt-3">
                                <x-form.input label="Gramatur Min (gsm)" type="number" name="gsm_min" wire:model="gsm_min" />
                                <x-form.input label="Gramatur Maks (gsm)" type="number" name="gsm_max" wire:model="gsm_max" />
                                <x-form.input label="Ukuran Ringkas (teks)" name="max_paper_size" wire:model="max_paper_size" placeholder="103x72" hint="Tampilan saja" />
                            </div>
                        </div>

                        <div class="border border-[#d7dbe2] rounded-md p-3 mt-3">
                            <p class="text-[10px] font-bold text-accent uppercase mb-2">Area Cetak Efektif</p>
                            <p class="text-[11px] text-muted mb-2.5">Area cetak <strong>lebih kecil</strong> dari ukuran lembar karena sisi jepit (gripper) tidak bisa dicetak. Tanpa angka ini, layout bisa lolos validasi di sistem tapi kena jepitan di mesin.</p>
                            <div class="[display:grid] grid-cols-1 md:grid-cols-3 gap-3">
                                <x-form.input label="Panjang Area Cetak (mm)" type="number" name="max_print_length_mm" wire:model="max_print_length_mm" />
                                <x-form.input label="Lebar Area Cetak (mm)" type="number" name="max_print_width_mm" wire:model="max_print_width_mm" />
                                <x-form.input label="Gripper Margin (mm)" type="number" name="gripper_margin_mm" wire:model="gripper_margin_mm" placeholder="mis. 10" />
                            </div>
                        </div>

                        <div class="border border-[#d7dbe2] rounded-md p-3 mt-3">
                            <p class="text-[10px] font-bold text-accent uppercase mb-2">Konfigurasi Cetak & Coating</p>
                            <div class="[display:grid] grid-cols-1 md:grid-cols-3 gap-3">
                                <x-form.input label="Jumlah Unit Warna" type="number" name="color_count" wire:model="color_count" placeholder="4" hint="0 / kosong untuk mesin finishing" />
                                <x-form.input label="Maks Warna per Lintasan" type="number" name="max_colors_per_pass" wire:model="max_colors_per_pass" />
                                <x-form.select label="Sistem Pengering" name="drying_system" wire:model="drying_system" :options="$dryingSystems" placeholder="— pilih —" />
                            </div>
                            <div class="[display:grid] grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                                <x-form.input label="Ukuran Plate" name="plate_size" wire:model="plate_size" placeholder="mis. 790x1030 mm" />
                                <x-form.input label="Jenis Bahan yang Bisa Dijalankan" name="substrate_types" wire:model="substrate_types" placeholder="ART, DUPLEX, KRAFT" hint="Pisahkan dengan koma" />
                            </div>
                            <div class="mt-3">
                                <x-form.checkbox wire:model="perfecting">
                                    <span class="font-semibold">Perfecting — bisa cetak bolak-balik 1 lintasan</span>
                                    <span class="block text-[10.5px] text-muted">Memengaruhi jumlah lintasan & waktu di scheduling</span>
                                </x-form.checkbox>
                            </div>
                        </div>
                    </div>

                    {{-- TAB 3: KAPASITAS & PERFORMA --}}
                    <div @if ($activeTab !== 'capacity') style="display:none" @endif>
                        <div class="border border-[#d7dbe2] rounded-md p-3">
                            <p class="text-[10px] font-bold text-accent uppercase mb-2">Kecepatan — Tiga Angka Berbeda Arti</p>
                            <p class="text-[11px] text-muted mb-2.5"><strong>Maksimum produsen</strong> adalah klaim brosur. <strong>Target aktual</strong> adalah yang benar-benar dicapai di lantai produksi — inilah yang dipakai scheduling & basis OEE. Jangan isi keduanya sama kalau kenyataannya tidak begitu.</p>
                            <div class="[display:grid] grid-cols-1 md:grid-cols-3 gap-3">
                                <x-form.input label="Kecepatan Maks Produsen (SPH)" type="number" name="max_speed_per_hour" wire:model="max_speed_per_hour" placeholder="15000" />
                                <x-form.input label="Target Kecepatan Aktual (SPH)" type="number" name="speed_target_per_hour" wire:model="speed_target_per_hour" placeholder="12000" />
                                <x-form.input label="Kapasitas per Shift (teks)" name="capacity_per_shift" wire:model="capacity_per_shift" />
                            </div>
                        </div>

                        <div class="border border-[#d7dbe2] rounded-md p-3 mt-3">
                            <p class="text-[10px] font-bold text-accent uppercase mb-2">Kalender Mesin — Dasar Kapasitas & OEE</p>
                            <p class="text-[11px] text-muted mb-2.5">Tanpa shift & jam per shift, jam tersedia tidak diketahui dan <strong>Availability OEE tidak bisa dihitung</strong> (sistem tidak akan mengasumsikan 24/7).</p>
                            <div class="[display:grid] grid-cols-1 md:grid-cols-4 gap-3">
                                <x-form.input label="Shift / Hari" type="number" name="shifts_per_day" wire:model="shifts_per_day" placeholder="2" />
                                <x-form.input label="Jam / Shift" name="hours_per_shift" wire:model="hours_per_shift" placeholder="8" />
                                <x-form.input label="Target OEE (%)" name="oee_target_pct" wire:model="oee_target_pct" placeholder="82" />
                                <x-form.input label="MTBF Standar (jam)" type="number" name="mtbf_hours" wire:model="mtbf_hours" placeholder="500" hint="Dari manual mesin" />
                            </div>
                        </div>

                        <div class="border border-[#d7dbe2] rounded-md p-3 mt-3">
                            <p class="text-[10px] font-bold text-accent uppercase mb-2">Kebutuhan Kru</p>
                            <p class="text-[11px] text-muted mb-2.5">Operator dan pembantu dipisah karena tarif upahnya beda — Payroll butuh pecahannya, bukan cuma total.</p>
                            <div class="[display:grid] grid-cols-1 md:grid-cols-3 gap-3">
                                <x-form.input label="Operator (orang)" type="number" name="operator_min" wire:model="operator_min" placeholder="1" />
                                <x-form.input label="Pembantu (orang)" type="number" name="helper_min" wire:model="helper_min" placeholder="2" />
                                <x-form.display label="Total Kru Minimum">
                                    <span class="font-mono font-bold">{{ ($operator_min === '' && $helper_min === '') ? '—' : ((int) $operator_min + (int) $helper_min).' orang' }}</span>
                                </x-form.display>
                            </div>
                        </div>
                    </div>

                    {{-- TAB 4: BIAYA & ASET --}}
                    <div @if ($activeTab !== 'finance') style="display:none" @endif>
                        <div class="border border-[#d7dbe2] rounded-md p-3">
                            <p class="text-[10px] font-bold text-accent uppercase mb-2">Costing & Alokasi Akuntansi</p>
                            <div class="[display:grid] grid-cols-1 md:grid-cols-3 gap-3">
                                <x-form.input label="Tarif Mesin / Jam (IDR)" name="hourly_rate" wire:model="hourly_rate" placeholder="250000" hint="Dipakai estimator untuk HPP" />
                                <x-form.input label="Konsumsi Daya (kW)" name="power_kw" wire:model="power_kw" placeholder="75" />
                                <x-form.input label="Cost Center / Kode GL" name="cost_center_code" wire:model="cost_center_code" placeholder="CT-001" />
                            </div>
                        </div>

                        <div class="border border-[#d7dbe2] rounded-md p-3 mt-3">
                            <p class="text-[10px] font-bold text-accent uppercase mb-2">Register Aset</p>
                            <div class="[display:grid] grid-cols-1 md:grid-cols-3 gap-3">
                                <x-form.input label="Nilai Perolehan (IDR)" name="acquisition_cost" wire:model="acquisition_cost" />
                                <x-form.input label="Umur Ekonomis (tahun)" type="number" name="useful_life_years" wire:model="useful_life_years" placeholder="10" />
                                <x-form.date-picker label="Garansi Sampai" name="warranty_until" wire:model="warranty_until" />
                            </div>
                        </div>

                        @if ($mode === 'edit' && $this->whereUsed)
                            <div class="border border-[#d7dbe2] rounded-md p-3 mt-3">
                                <p class="text-[10px] font-bold text-accent uppercase mb-2">Dipakai Di Mana (Where-used)</p>
                                <p class="text-[11px] text-muted mb-2.5">Relasi ke modul lain adalah FK <em>logis</em> lintas-database — MySQL tidak menjaganya. Periksa daftar ini sebelum menonaktifkan mesin.</p>
                                <div class="[display:grid] grid-cols-1 md:grid-cols-2 gap-1.5">
                                    @foreach ($this->whereUsed as $label => $count)
                                        <div class="flex justify-between items-center text-[11.5px] px-2 py-1 rounded bg-[#fafbfd]">
                                            <span class="text-[#4b5568]">{{ $label }}</span>
                                            <span class="font-mono font-bold {{ $count > 0 ? 'text-[#b45309]' : 'text-muted' }}">{{ $count }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- TAB 5: PERAWATAN & OEE --}}
                    <div @if ($activeTab !== 'maintenance') style="display:none" @endif>
                        @if ($mode !== 'edit')
                            <x-alert.banner type="info" message="Simpan mesin ini dulu, lalu jadwal perawatan & log downtime bisa diisi di sini." />
                        @else
                            <div class="border border-[#d7dbe2] rounded-md p-3">
                                <p class="text-[10px] font-bold text-accent uppercase mb-2">Jadwal Perawatan Preventif</p>
                                <div class="[display:grid] grid-cols-1 md:grid-cols-4 gap-3">
                                    <x-form.input label="Interval (jam jalan)" type="number" name="maintenance_interval_hours" wire:model="maintenance_interval_hours" placeholder="500" />
                                    <x-form.input label="Interval (hari)" type="number" name="maintenance_interval_days" wire:model="maintenance_interval_days" placeholder="90" />
                                    <x-form.date-picker label="Perawatan Terakhir" name="last_maintenance_date" wire:model="last_maintenance_date" />
                                    <x-form.date-picker label="Perawatan Berikutnya" name="next_maintenance_date" wire:model="next_maintenance_date" />
                                </div>
                                <div class="[display:grid] grid-cols-1 md:grid-cols-3 gap-3 mt-3">
                                    <x-form.input label="Total Jam Jalan" name="total_run_hours" wire:model="total_run_hours" />
                                    <x-form.input label="Total Impresi" type="number" name="total_impressions" wire:model="total_impressions" />
                                    <x-form.input label="Vendor Servis" name="service_vendor" wire:model="service_vendor" />
                                </div>
                            </div>

                            {{-- OEE AKTUAL --}}
                            @php($o = $this->oee)
                            <div class="border border-[#d7dbe2] rounded-md p-3 mt-3">
                                <div class="flex items-center justify-between mb-2 flex-wrap gap-2">
                                    <p class="text-[10px] font-bold text-accent uppercase">OEE Aktual — {{ $oeeDays }} hari terakhir</p>
                                    <div class="flex gap-1">
                                        @foreach ([7, 30, 90] as $d)
                                            <button type="button" wire:click="setOeeDays({{ $d }})"
                                                class="text-[10px] font-semibold px-2 py-1 rounded cursor-pointer {{ $oeeDays === $d ? 'bg-accent text-white' : 'bg-[#eef1f5] text-[#4b5568]' }}">{{ $d }} hr</button>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="[display:grid] grid-cols-2 md:grid-cols-4 gap-2.5">
                                    @foreach ([
                                        'availability' => ['Availability', 'Jam tersedia − stop tak terencana'],
                                        'performance' => ['Performance', 'Output vs kapasitas teoretis'],
                                        'quality' => ['Quality', 'Hasil bagus vs total produksi'],
                                    ] as $fk => [$flabel, $fhint])
                                        @php($f = $o['factors'][$fk])
                                        <div class="border border-[#eef1f5] rounded p-2.5 bg-[#fafbfd]">
                                            <div class="text-[10px] uppercase tracking-wide text-muted">{{ $flabel }}</div>
                                            @if ($f['value'] !== null)
                                                <div class="text-[20px] font-bold leading-tight {{ $f['value'] >= 85 ? 'text-[#15803d]' : ($f['value'] >= 65 ? 'text-[#b45309]' : 'text-[#b91c1c]') }}">{{ number_format($f['value'], 1, ',', '.') }}<span class="text-[12px]">%</span></div>
                                                <div class="mt-1 h-1.5 rounded-full bg-[#eef1f5] overflow-hidden">
                                                    <div class="h-full rounded-full {{ $f['value'] >= 85 ? 'bg-[#22c55e]' : ($f['value'] >= 65 ? 'bg-[#f59e0b]' : 'bg-[#ef4444]') }}" style="width:{{ min(100, $f['value']) }}%"></div>
                                                </div>
                                            @else
                                                <div class="text-[13px] font-bold text-muted leading-tight mt-1">belum bisa dihitung</div>
                                                <div class="text-[9.5px] text-[#c2410c] mt-1 leading-snug">{{ $f['reason'] }}</div>
                                            @endif
                                            <div class="text-[9.5px] text-muted mt-1">{{ $fhint }}</div>
                                        </div>
                                    @endforeach

                                    <div class="border-2 rounded p-2.5 {{ $o['oee'] !== null ? 'border-accent bg-[#eff6ff]' : 'border-[#eef1f5] bg-[#fafbfd]' }}">
                                        <div class="text-[10px] uppercase tracking-wide text-muted">OEE Total</div>
                                        @if ($o['oee'] !== null)
                                            <div class="text-[20px] font-bold leading-tight text-accent">{{ number_format($o['oee'], 1, ',', '.') }}<span class="text-[12px]">%</span></div>
                                            @if ($o['oee_target'] !== null)
                                                <div class="text-[9.5px] mt-1 font-semibold {{ $o['oee'] >= $o['oee_target'] ? 'text-[#15803d]' : 'text-[#b91c1c]' }}">
                                                    Target {{ number_format($o['oee_target'], 0, ',', '.') }}% — {{ $o['oee'] >= $o['oee_target'] ? 'tercapai' : 'kurang '.number_format($o['oee_target'] - $o['oee'], 1, ',', '.').' poin' }}
                                                </div>
                                            @endif
                                        @else
                                            <div class="text-[13px] font-bold text-muted leading-tight mt-1">belum bisa dihitung</div>
                                            <div class="text-[9.5px] text-muted mt-1 leading-snug">OEE hanya sah kalau ketiga faktor terukur. Angka target saja tidak cukup.</div>
                                        @endif
                                    </div>
                                </div>

                                @php($rel = $this->reliability)
                                <div class="[display:grid] grid-cols-2 md:grid-cols-4 gap-2.5 mt-2.5 text-[11.5px]">
                                    <div class="px-2.5 py-2 rounded bg-[#fafbfd] border border-[#eef1f5]">
                                        <span class="text-muted text-[10px] block uppercase">Stop tak terencana</span>
                                        <span class="font-mono font-bold">{{ number_format($o['downtime']['unplanned'] / 60, 1, ',', '.') }} jam</span>
                                    </div>
                                    <div class="px-2.5 py-2 rounded bg-[#fafbfd] border border-[#eef1f5]">
                                        <span class="text-muted text-[10px] block uppercase">Stop terencana</span>
                                        <span class="font-mono font-bold">{{ number_format($o['downtime']['planned'] / 60, 1, ',', '.') }} jam</span>
                                    </div>
                                    <div class="px-2.5 py-2 rounded bg-[#fafbfd] border border-[#eef1f5]">
                                        <span class="text-muted text-[10px] block uppercase">MTTR aktual</span>
                                        <span class="font-mono font-bold">{{ $rel['mttr_hours'] !== null ? number_format($rel['mttr_hours'], 2, ',', '.').' jam' : '—' }}</span>
                                    </div>
                                    <div class="px-2.5 py-2 rounded bg-[#fafbfd] border border-[#eef1f5]">
                                        <span class="text-muted text-[10px] block uppercase">MTBF aktual</span>
                                        <span class="font-mono font-bold">{{ $rel['mtbf_hours'] !== null ? number_format($rel['mtbf_hours'], 1, ',', '.').' jam' : '—' }}</span>
                                        @if ($mtbf_hours !== '' && $rel['mtbf_hours'] !== null)
                                            <span class="block text-[9.5px] text-muted">standar {{ number_format((int) $mtbf_hours, 0, ',', '.') }} jam</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- LOG DOWNTIME --}}
                            <div class="border border-[#d7dbe2] rounded-md p-3 mt-3">
                                <p class="text-[10px] font-bold text-accent uppercase mb-2">Catat Stop Mesin (Downtime)</p>
                                <p class="text-[11px] text-muted mb-2.5">Terencana atau tidak <strong>ditentukan otomatis dari jenisnya</strong>, bukan dipilih operator — kalau bisa dipilih, Availability gampang dipoles.</p>
                                <div class="[display:grid] grid-cols-1 md:grid-cols-5 gap-2.5 items-end">
                                    <x-form.select label="Jenis" name="dtKind" wire:model="dtKind" :placeholder="false"
                                        :options="collect(\App\Models\Master\ProdMachineDowntime::KINDS)->map(fn ($k) => $k['label'])->all()" />
                                    <x-form.date-picker label="Mulai" name="dtStart" wire:model="dtStart" mode="datetime" />
                                    <x-form.date-picker label="Selesai" name="dtEnd" wire:model="dtEnd" mode="datetime" hint="Kosongkan bila masih berlangsung" />
                                    <x-form.input label="Penyebab" name="dtCause" wire:model="dtCause" placeholder="mis. roller tinta macet" />
                                    <button type="button" wire:click="addDowntime"
                                        class="px-3.5 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer">+ Tambah Catatan</button>
                                </div>

                                <div class="mt-3 border border-[#eef1f5] rounded overflow-x-auto">
                                    <table class="w-full border-collapse text-[11.5px]">
                                        <thead>
                                            <tr class="bg-[#fafbfd]">
                                                @foreach (['Jenis', 'Mulai', 'Selesai', 'Lama', 'Penyebab', 'Sifat', ''] as $h)
                                                    <th class="text-left px-2.5 py-1.5 text-[10px] uppercase text-[#667] border-b border-[#eef1f5] whitespace-nowrap">{{ $h }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($this->downtimeRows as $d)
                                                <tr wire:key="dt-{{ $d->downtime_id }}">
                                                    <td class="px-2.5 py-1.5 border-b border-[#eef1f5] whitespace-nowrap">{{ $d->kindLabel() }}</td>
                                                    <td class="px-2.5 py-1.5 border-b border-[#eef1f5] font-mono whitespace-nowrap">{{ $d->start_at->format('d/m/y H:i') }}</td>
                                                    <td class="px-2.5 py-1.5 border-b border-[#eef1f5] font-mono whitespace-nowrap">
                                                        {{ $d->end_at?->format('d/m/y H:i') ?? '—' }}
                                                    </td>
                                                    <td class="px-2.5 py-1.5 border-b border-[#eef1f5] font-mono whitespace-nowrap">
                                                        {{ $d->duration_minutes !== null ? number_format($d->duration_minutes / 60, 1, ',', '.').' jam' : 'berjalan' }}
                                                    </td>
                                                    <td class="px-2.5 py-1.5 border-b border-[#eef1f5]">{{ $d->root_cause ?: '—' }}</td>
                                                    <td class="px-2.5 py-1.5 border-b border-[#eef1f5] whitespace-nowrap">
                                                        <span class="text-[9.5px] font-bold px-1.5 py-0.5 rounded {{ $d->is_planned ? 'bg-[#eff6ff] text-[#1d4ed8]' : 'bg-[#fef2f2] text-[#b91c1c]' }}">{{ $d->is_planned ? 'Terencana' : 'Tak terencana' }}</span>
                                                    </td>
                                                    <td class="px-2.5 py-1.5 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                                        @if ($d->isOpen())
                                                            <x-grid.action-icon icon="fa-check" title="Tutup — mesin sudah jalan lagi" action="closeDowntime({{ $d->downtime_id }})" />
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="7" class="px-3 py-4 text-center text-muted">Belum ada catatan stop mesin. Tanpa ini Availability OEE tidak bisa dihitung.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- TAB 6: MATRIKS KAPABILITAS (mesin -> proses) --}}
                    <div @if ($activeTab !== 'processes') style="display:none" @endif>
                        @if ($mode !== 'edit')
                            <x-alert.banner type="info" message="Simpan mesin ini dulu, lalu proses yang bisa dijalankan di sini bisa dipasang." />
                        @elseif (! $is_process_machine)
                            <x-alert.banner type="warn" message="Mesin ini ditandai alat penunjang, jadi tidak perlu dipetakan ke proses. Centang 'Mesin proses produksi' di tab 1 kalau ternyata dipakai produksi." />
                        @else
                            <p class="text-[11px] text-muted mb-3">Proses yang <strong>bisa</strong> dijalankan di mesin ini — arah balik dari Master Proses, baris data yang sama. Mesin ini akan muncul sebagai pilihan pada setiap proses di daftar ini.</p>

                            <div class="flex flex-wrap items-end gap-2 mb-3 p-3 bg-[#fafbfd] border border-[#d7dbe2] rounded-md">
                                <div class="flex-1 min-w-[280px]">
                                    <x-form.select label="Tambah proses yang bisa dijalankan" name="capPickId" wire:model="capPickId" :options="$this->processCandidates" placeholder="— pilih proses —" />
                                </div>
                                <button type="button" wire:click="capAdd" @disabled(! $capPickId)
                                    class="px-3.5 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">+ Tambah Proses</button>
                            </div>

                            <div class="border border-[#d7dbe2] rounded-md overflow-x-auto">
                                <table class="w-full border-collapse text-[12px]">
                                    <thead>
                                        <tr class="bg-[#fafbfd]">
                                            @foreach (['Proses', 'Tahap / Dept', 'Kapasitas Efektif', 'Setup', 'Waste', 'Tarif / Jam', 'Kru', 'Est. 10.000 unit', 'Peran', ''] as $h)
                                                <th class="text-left px-2.5 py-2 text-[10px] uppercase tracking-wide text-[#667] border-b border-[#eef1f5] whitespace-nowrap">{{ $h }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($this->capabilityRows as $cap)
                                            @php($rp = $this->resolvedParams[$cap->capability_id] ?? null)
                                            <tr wire:key="mcap-{{ $cap->capability_id }}" class="{{ $cap->activate ? '' : 'opacity-45' }}">
                                                <td class="px-2.5 py-2 border-b border-[#eef1f5] whitespace-nowrap">
                                                    @if ($cap->process->process_code)
                                                        <span class="font-mono font-bold text-[11px] px-1.5 py-0.5 rounded bg-[#eef1f5] text-[#2c3e69]">{{ $cap->process->process_code }}</span>
                                                    @endif
                                                    <div class="text-[11.5px] font-semibold mt-0.5">{{ $cap->process->process_name }}</div>
                                                </td>
                                                <td class="px-2.5 py-2 border-b border-[#eef1f5]">
                                                    <div class="text-[11px]">{{ $cap->process->stageLabel() }}</div>
                                                    <div class="text-[10px] text-muted">{{ $cap->process->department?->department_name ?? '—' }}</div>
                                                    @if (! $cap->process->activate)
                                                        <div class="text-[10px] font-bold text-[#b91c1c] mt-0.5">proses nonaktif</div>
                                                    @endif
                                                </td>
                                                @if ($rp)
                                                    @foreach (['speed_per_hour', 'setup_minutes'] as $f)
                                                        <td class="px-2.5 py-2 border-b border-[#eef1f5] whitespace-nowrap">
                                                            @if ($rp['params'][$f]['value'] !== null)
                                                                <span class="font-mono text-[11.5px]">{{ number_format((float) $rp['params'][$f]['value'], 0, ',', '.') }}</span>
                                                                <span class="block text-[9.5px] {{ $rp['params'][$f]['source'] === 'CAPABILITY' ? 'text-[#1d4ed8] font-bold' : 'text-muted' }}">{{ $rp['params'][$f]['source'] === 'CAPABILITY' ? 'khusus pasangan' : ($rp['params'][$f]['source'] === 'PROCESS' ? 'default proses' : 'dari mesin') }}</span>
                                                            @else
                                                                <span class="text-[10px] text-[#c2410c]">belum ada</span>
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                    <td class="px-2.5 py-2 border-b border-[#eef1f5] whitespace-nowrap">
                                                        @if ($rp['params']['waste_pct']['value'] !== null)
                                                            <span class="font-mono text-[11.5px]">{{ rtrim(rtrim((string) $rp['params']['waste_pct']['value'], '0'), '.') ?: '0' }}%</span>
                                                        @else
                                                            <span class="text-[10px] text-muted">—</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-2.5 py-2 border-b border-[#eef1f5] whitespace-nowrap">
                                                        @if ($rp['params']['hourly_rate']['value'] > 0)
                                                            <span class="font-mono text-[11.5px]">{{ number_format((float) $rp['params']['hourly_rate']['value'], 0, ',', '.') }}</span>
                                                        @else
                                                            <span class="text-[10px] text-[#c2410c]">belum ada</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-2.5 py-2 border-b border-[#eef1f5] font-mono text-[11.5px]">{{ $rp['params']['manpower']['value'] ?? '—' }}</td>
                                                    <td class="px-2.5 py-2 border-b border-[#eef1f5] whitespace-nowrap">
                                                        @if ($rp['hours_10k'] !== null)
                                                            <span class="font-mono font-semibold text-[11.5px]">{{ number_format($rp['hours_10k'], 2, ',', '.') }}</span><span class="text-[10px] text-muted"> jam</span>
                                                        @else
                                                            <span class="text-[10px] text-muted">tak bisa dihitung</span>
                                                        @endif
                                                    </td>
                                                @else
                                                    <td colspan="6" class="px-2.5 py-2 border-b border-[#eef1f5] text-muted">—</td>
                                                @endif
                                                <td class="px-2.5 py-2 border-b border-[#eef1f5] whitespace-nowrap">
                                                    <button type="button" wire:click="capTogglePrimary({{ $cap->capability_id }})"
                                                        class="text-[10px] font-bold px-2 py-0.5 rounded cursor-pointer {{ $cap->preference_rank === 1 ? 'bg-[#fef9c3] text-[#92400e]' : 'bg-[#eef1f5] text-[#8a8f98]' }}"
                                                        title="Mesin utama untuk proses ini?">{{ $cap->preference_rank === 1 ? '★ Utama' : 'Alternatif' }}</button>
                                                    <button type="button" wire:click="capToggleCertified({{ $cap->capability_id }})"
                                                        class="block mt-1 text-[9.5px] font-semibold px-1.5 py-0.5 rounded cursor-pointer {{ $cap->is_certified ? 'bg-[#eaf7ee] text-[#166534]' : 'bg-[#fff7ed] text-[#c2410c]' }}">{{ $cap->is_certified ? 'Tervalidasi' : 'Belum divalidasi' }}</button>
                                                </td>
                                                <td class="px-2.5 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                                    <x-grid.action-icon icon="fa-sliders" title="Atur parameter khusus pasangan ini" action="capEdit({{ $cap->capability_id }})" />
                                                    @if ($cap->activate)
                                                        <x-grid.action-icon icon="fa-ban" title="Lepas proses dari mesin ini" variant="danger"
                                                            action="capRemove({{ $cap->capability_id }})"
                                                            :confirm="'Lepas proses '.$cap->process->process_name.' dari mesin ini? Parameter yang sudah disetel tetap disimpan.'" />
                                                    @else
                                                        <x-grid.action-icon icon="fa-rotate" title="Pasang kembali" action="capRestore({{ $cap->capability_id }})" />
                                                    @endif
                                                </td>
                                            </tr>

                                            @if ($capEditingId === $cap->capability_id)
                                                <tr wire:key="mcapedit-{{ $cap->capability_id }}" class="bg-[#f7faff]">
                                                    <td colspan="10" class="px-3 py-3 border-b border-[#eef1f5]">
                                                        <p class="text-[10px] font-bold text-accent uppercase mb-1">Parameter {{ $cap->process->process_name }} di mesin {{ $machine_code }}</p>
                                                        <p class="text-[11px] text-muted mb-2.5">Kosongkan sebuah kotak berarti <strong>ikut nilai default proses</strong>. Isi hanya yang memang beda di mesin ini.</p>
                                                        <div class="[display:grid] grid-cols-2 md:grid-cols-4 gap-2.5">
                                                            <x-form.input label="Kapasitas / jam" name="capForm.speed_per_hour" wire:model="capForm.speed_per_hour" placeholder="default proses" />
                                                            <x-form.input label="Setup (menit)" type="number" name="capForm.setup_minutes" wire:model="capForm.setup_minutes" placeholder="default proses" />
                                                            <x-form.input label="Waste (%)" name="capForm.waste_pct" wire:model="capForm.waste_pct" placeholder="default proses" />
                                                            <x-form.input label="Insheet tetap" type="number" name="capForm.waste_fixed_qty" wire:model="capForm.waste_fixed_qty" placeholder="default proses" />
                                                            <x-form.input label="Tarif / jam (IDR)" name="capForm.hourly_rate" wire:model="capForm.hourly_rate" placeholder="tarif mesin" />
                                                            <x-form.input label="Kru" type="number" name="capForm.manpower" wire:model="capForm.manpower" placeholder="default proses" />
                                                            <x-form.input label="Gramatur min" type="number" name="capForm.gsm_min" wire:model="capForm.gsm_min" placeholder="batas mesin" />
                                                            <x-form.input label="Gramatur maks" type="number" name="capForm.gsm_max" wire:model="capForm.gsm_max" placeholder="batas mesin" />
                                                        </div>
                                                        <div class="mt-2.5">
                                                            <x-form.input label="Catatan" name="capForm.note" wire:model="capForm.note" placeholder="mis. hanya untuk duplex di atas 250 gsm" />
                                                        </div>
                                                        <div class="flex gap-2 mt-3">
                                                            <button type="button" wire:click="capSaveParams" class="px-3.5 py-1.5 rounded text-xs font-semibold bg-accent text-white cursor-pointer">Simpan</button>
                                                            <button type="button" wire:click="capCancelEdit" class="px-3.5 py-1.5 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">Batal</button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endif
                                        @empty
                                            <tr><td colspan="10" class="px-3 py-6 text-center text-muted text-[12px]">
                                                Belum ada proses dipasangkan. Mesin ini tidak akan muncul sebagai pilihan di routing mana pun.
                                            </td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    <div class="flex gap-2 mt-4 -mx-5 -mb-5 px-4 py-3.5 bg-[#fafbfc] border-t border-[#d7dbe2]">
                        <button type="submit" class="px-4 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer">Simpan</button>
                        <button type="button" wire:click="showGrid" class="px-4 py-2 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">Batal</button>
                        <x-back-button fallback="master.machine" class="ml-auto" />
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>
