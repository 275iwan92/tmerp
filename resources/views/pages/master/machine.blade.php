<?php

/*
 * REMARK
 * Tujuan       : Komponen v1 untuk layar "Machine Master" (dibuka dari grid v2 lewat Open / New). Teks tampilan (label, kolom, tab, tombol, pesan) English sesuai keputusan Jo 2026-10-18; nilai tersimpan dan kunci data tidak diubah.
 * Sumber hulu  : Tabel dan service yang dibaca komponen ini (lihat query di file); tidak ada logika hulu baru.
 * Input        : Isian form dan filter layar.
 * Output       : Simpan ke tabel modul terkait lewat service yang sama seperti sebelumnya.
 * Dipakai oleh : Grid v2 modul terkait (v1Route).
 * Status       : Aktif; teks tampilan English (Jo 2026-10-18).
 */


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
use App\Livewire\Concerns\OpensFromQuery;
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
new #[Title('Machine Master — TELASIH MAKMUR ERP')] class extends Component
{
    use OpensFromQuery;

    protected function openFromQuery(int $id): void
    {
        $this->openModal('edit', $id);
    }

    protected function newFromQuery(): void
    {
        $this->openModal('new');
    }

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

    public array $dryingSystems = ['IR' => 'IR Dryer', 'UV' => 'UV Curing', 'HOT_AIR' => 'Hot Air', 'NONE' => 'No Dryer'];

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

    // Nama sehari-hari di pabrik ("Plong 2", "Daxiang") & model di pasar (katalog prod_machine_model) — user 2026-10-01.
    public string $nickname = '';

    public string $machine_model_id = '';

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
            $this->nickname = (string) $m->nickname;
            $this->machine_model_id = (string) ($m->machine_model_id ?? '');
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
                'brand_model', 'nickname', 'machine_model_id', 'year_acquired', 'acquired_date', 'serial_no', 'criticality',
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

    public function modelOptions(): array
    {
        return \App\Models\Master\ProdMachineModel::where('activate', true)->orderBy('machine_type')->orderBy('work_length_mm')->get()
            ->mapWithKeys(fn ($m) => [$m->id_machine_model => $m->model_code.($m->alias_codes ? ' ('.$m->alias_codes.')' : '').' · '
                .($m->work_length_mm ? $m->work_length_mm.'×'.$m->work_width_mm.' mm' : '').' · '.(\App\Models\Master\ProdMachineModel::DRIVES[$m->drive] ?? $m->drive)])->all();
    }

    // Pilih model = spek umum model diisikan ke form (engine suggests, officer confirms: tetap bisa diubah sebelum Save).
    public function updatedMachineModelId($value): void
    {
        $m = $value ? \App\Models\Master\ProdMachineModel::find($value) : null;
        if (! $m) {
            return;
        }
        foreach (['max_sheet_length_mm' => $m->work_length_mm, 'max_sheet_width_mm' => $m->work_width_mm,
            'max_speed_per_hour' => $m->speed_per_hour, 'power_kw' => $m->motor_kw] as $prop => $v) {
            if ($v !== null) {
                $this->{$prop} = (string) (float) $v === (string) (int) $v ? (string) (int) $v : (string) $v;
            }
        }
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
            'nickname' => ['nullable', 'string', 'max:60'],
            'machine_model_id' => ['nullable', 'integer', 'exists:tm2mst.prod_machine_model,id_machine_model'],
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
            ['max_sheet_length_mm', 'min_sheet_length_mm', 'Maximum sheet length must be >= minimum.', 'specs'],
            ['max_sheet_width_mm', 'min_sheet_width_mm', 'Maximum sheet width must be >= minimum.', 'specs'],
            ['gsm_max', 'gsm_min', 'Maximum grammage must be >= minimum.', 'specs'],
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
            $this->addError('max_print_length_mm', 'The print area must not be longer than the maximum sheet.');

            return;
        }

        // Target kecepatan di atas klaim produsen = salah satu angkanya keliru.
        if ($data['speed_target_per_hour'] !== '' && $data['max_speed_per_hour'] !== ''
            && (int) $data['speed_target_per_hour'] > (int) $data['max_speed_per_hour']) {
            $this->activeTab = 'capacity';
            $this->addError('speed_target_per_hour', 'The target speed must not exceed the manufacturer maximum speed.');

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
            'total' => ['label' => 'Total Machine Units', 'value' => $this->statTotal, 'tone' => 'total',
                'subtitle' => 'Factory production capacity'],
            'active' => ['label' => 'Active', 'value' => $this->statActive, 'tone' => 'active',
                'filter' => fn ($q) => $q->where('activate', true)],
            'down' => ['label' => 'Maintenance / Broken', 'value' => $this->statDown, 'tone' => 'critical',
                'subtitle' => 'Cannot be scheduled',
                'filter' => fn ($q) => $q->where('activate', true)
                    ->whereIn('machine_status', ['MAINTENANCE', 'BREAKDOWN'])],
            'maintdue' => ['label' => 'Maintenance Due', 'value' => $this->statMaintDue, 'tone' => 'warn',
                'subtitle' => 'Next 7 days',
                'filter' => fn ($q) => $q->where('activate', true)->whereNotNull('next_maintenance_date')
                    ->whereDate('next_maintenance_date', '<=', now()->addDays(7))],
            'noprocess' => ['label' => 'No Process', 'value' => $this->statNoProcess, 'tone' => 'extra',
                'subtitle' => 'Not in any routing yet',
                'filter' => fn ($q) => $q->where('activate', true)->where('is_process_machine', true)
                    ->whereDoesntHave('capabilities', fn ($qq) => $qq->where('activate', true))],
            'inactive' => ['label' => 'Inactive', 'value' => $this->statInactive, 'tone' => 'critical',
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

        <x-ui.card variant="panel" size="none">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <x-form.input wire:model.live.debounce.300ms="search" placeholder="Search code, name, machine brand…" :case-toggle="false" class="w-64 shrink-0" />
                </div>
                <x-slot:tools>
                    <x-form.select wire:model.live="typeFilter" liveSync :selected="$typeFilter" placeholder="All Machine Types"
                        :options="array_combine($machineTypes, $machineTypes)" class="shrink-0" />
                </x-slot:tools>
                <x-slot:newRecord>
                    <x-ui.button preset="new" size="sm" class="shrink-0" wire:click="openModal('new')" />
                </x-slot:newRecord>
            </x-grid.toolbar>

            <x-grid.table-shell>
                <x-ui.table sticky hover>
                    <thead>
                        <tr>
                            @foreach ([
                                'Code' => 'w-[78px]', 'Machine Name & Brand' => '', 'Type' => 'w-[124px]',
                                'Format Max (mm)' => 'w-[112px]', 'Speed / Hour' => 'w-[150px]',
                                'Rate / Hour' => 'w-[104px] num', 'Crew' => 'w-[62px] num',
                                'Process' => 'w-[88px]', 'Readiness' => 'w-[100px]', 'Status' => 'w-[118px]',
                            ] as $label => $w)
                                <th class="{{ $w }}">{{ $label }}</th>
                            @endforeach
                            <th class="text-right w-[100px]">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->machines as $m)
                            @php($ready = $m->readinessScore())
                            @php($due = $m->maintenanceDueInDays())
                            <tr wire:key="machine-{{ $m->machine_id }}" wire:dblclick="openModal('edit', {{ $m->machine_id }})" class="cursor-pointer" title="Double-click to open the full specification">
                                <td>
                                    <x-ui.badge tone="code" size="md" mono>{{ $m->machine_code }}</x-ui.badge>
                                </td>
                                <td>
                                    <div class="font-semibold text-[12.5px] truncate">{{ $m->machine_name }}</div>
                                    <div class="text-[10.5px] text-muted truncate">
                                        Brand: {{ $m->brand_model ?: '—' }}{{ $m->year_acquired ? ' ('.$m->year_acquired.')' : '' }}
                                        @if ($m->criticality) <span class="font-bold text-warn-ink">· Critical {{ $m->criticality }}</span> @endif
                                    </div>
                                </td>
                                <td>
                                    <x-ui.badge tone="info">{{ $m->machine_type }}</x-ui.badge>
                                    @if (! $m->is_process_machine)
                                        <div class="text-[9.5px] text-muted mt-0.5">support equipment</div>
                                    @endif
                                </td>
                                <td>
                                    @if ($m->max_sheet_length_mm && $m->max_sheet_width_mm)
                                        <span class="font-mono text-[11.5px]">{{ $m->max_sheet_length_mm }} × {{ $m->max_sheet_width_mm }}</span>
                                        @if ($m->gsm_min || $m->gsm_max)
                                            <div class="text-[9.5px] text-muted">{{ $m->gsm_min ?: '?' }}–{{ $m->gsm_max ?: '?' }} gsm</div>
                                        @endif
                                    @elseif ($m->max_paper_size)
                                        <span class="text-[10.5px] text-warn-ink" title="Still text, not a mm number yet — the imposition engine cannot use it">{{ $m->max_paper_size }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                {{-- Bar kecepatan: pembanding cepat antar-mesin (permintaan user 2026-09-22) --}}
                                <td>
                                    @if ($m->max_speed_per_hour)
                                        <div class="text-[11.5px] leading-tight">
                                            <span class="font-mono font-semibold">{{ number_format($m->max_speed_per_hour, 0, ',', '.') }}</span>
                                            <span class="text-[10px] text-muted">SPH</span>
                                            @if ($m->speed_target_per_hour)
                                                <span class="text-[9.5px] text-muted">· target {{ number_format($m->speed_target_per_hour, 0, ',', '.') }}</span>
                                            @endif
                                        </div>
                                        {{-- Penanda target di dalam bar: selisih klaim produsen vs realistis --}}
                                        <x-ui.progress class="mt-1" tone="info"
                                            :value="max(3, round($m->max_speed_per_hour / $this->speedScaleMax * 100))"
                                            :marker="$m->speed_target_per_hour ? round($m->speed_target_per_hour / $this->speedScaleMax * 100) : null"
                                            marker-title="Actual speed target" />
                                    @else
                                        <span class="text-[10.5px] text-muted">N/A (Manual/Cutting)</span>
                                    @endif
                                </td>
                                <td class="num">
                                    @if ($m->hourly_rate > 0)
                                        <span class="font-mono text-[11.5px]">{{ number_format((float) $m->hourly_rate, 0, ',', '.') }}</span>
                                    @elseif ($m->is_process_machine)
                                        <span class="text-[10.5px] text-warn-ink" title="BOP rate not set — COGS (HPP) of this machine cannot be calculated">not filled in</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="num font-mono text-[11.5px]">
                                    {{ $m->crewTotal() ?? '—' }}
                                </td>
                                <td>
                                    @if ($m->capabilities_count > 0)
                                        <x-ui.badge-button tone="success" size="md" icon="fa-diagram-project" wire:click="openModal('edit', {{ $m->machine_id }})"
                                            title="See the processes this machine can run">{{ $m->capabilities_count }}</x-ui.badge-button>
                                    @elseif (! $m->is_process_machine)
                                        <span class="text-muted">—</span>
                                    @else
                                        <x-ui.badge tone="danger" title="This machine will not appear in the routing picker">belum ada</x-ui.badge>
                                    @endif
                                </td>
                                <td>
                                    <x-ui.badge :tone="\App\View\Components\Ui\Tone::forScore($ready)" size="md" mono
                                        title="Data completeness required for engine use">{{ $ready }}%</x-ui.badge>
                                </td>
                                <td>
                                    @php($tone = match ($m->machine_status) {
                                        'READY', 'RUNNING' => 'success',
                                        'SETUP', 'IDLE' => 'info',
                                        'MAINTENANCE' => 'warn',
                                        'BREAKDOWN' => 'danger',
                                        default => 'muted',
                                    })
                                    <x-ui.badge :tone="$tone" pill>{{ $m->statusLabel() }}</x-ui.badge>
                                    @if (! $m->activate)
                                        <div class="text-[9.5px] text-muted mt-0.5">inactive in master</div>
                                    @elseif ($due !== null && $due <= 7)
                                        <div class="text-[9.5px] font-bold {{ $due < 0 ? 'text-danger-ink' : 'text-warn-ink' }} mt-0.5">
                                            {{ $due < 0 ? 'Maintenance overdue '.abs($due).' d' : 'Maintenance '.($due === 0 ? 'today' : $due.' d left') }}
                                        </div>
                                    @endif
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-pen" title="Edit" action="openModal('edit', {{ $m->machine_id }})" />
                                    <x-grid.action-icon :active="$m->activate" :title="$m->activate ? 'Deactivate' : 'Activate'" action="toggleActive({{ $m->machine_id }})"
                                        :confirm="($m->activate ? 'Deactivate' : 'Activate').' machine '.$m->machine_code.'?'"
                                        :variant="$m->activate ? 'danger' : 'default'" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="11" message="None." />
                        @endforelse
                    </tbody>
                </x-ui.table>
            </x-grid.table-shell>
        </x-ui.card>
        @endif

        {{-- ============ FORM (NEW/EDIT) ============ --}}
        @if ($panel === 'form')
            <x-ui.panel-header :title="$mode === 'edit' ? 'Edit Machine : '.$machine_code : 'New Machine'">
                @if ($mode === 'edit')
                    <x-slot:aside>{{ $this->capabilityRows->where('activate', true)->count() }} processes can be run</x-slot:aside>
                @endif
            </x-ui.panel-header>

            <div class="bg-surface border border-t-0 border-line rounded-b-md overflow-hidden">
                <x-ui.tabs model="activeTab" :active="$activeTab" :tabs="[
                    'identity' => '1. Machine Identity',
                    'specs' => '2. Technical Specifications',
                    'capacity' => '3. Capacity & Performance',
                    'finance' => '4. Cost & Assets',
                    'maintenance' => '5. Maintenance & OEE',
                    'processes' => '6. Processes',
                ]" :badges="$mode === 'edit' ? ['processes' => [
                    'text' => $this->capabilityRows->where('activate', true)->count(),
                    'tone' => $this->capabilityRows->where('activate', true)->count() > 0 ? 'success' : 'danger',
                ]] : []" />

                <form wire:submit="save" class="p-5">
                    {{-- TAB 1: IDENTITAS --}}
                    <div @if ($activeTab !== 'identity') style="display:none" @endif>
                        <div class="[display:grid] grid-cols-1 md:grid-cols-3 gap-3">
                            <x-form.input label="Asset / Machine Code *" name="machine_code" wire:model="machine_code" placeholder="e.g. OFF-01" hint="Type prefix + sequence number" />
                            <x-form.select label="Condition Status" name="machine_status" wire:model="machine_status" :placeholder="false" :options="array_map('t', \App\Models\Master\ProdMachine::STATUSES)" />
                            <x-form.select label="Status Master" wire:model="machine_activate" :placeholder="false" :options="['1' => 'Active (owned)', '0' => 'Inactive (released)']" />
                        </div>
                        <p class="text-[11px] text-muted mt-1.5">The two statuses are deliberately separate: <strong>Condition</strong> changes daily (breakdown/maintenance), <strong>Status Master</strong> only when the machine is really released. A broken machine stays active in master so its routing & WO history stays intact.</p>

                        <div class="mt-3">
                            <x-form.input label="Machine Name & Model *" name="machine_name" wire:model="machine_name" placeholder="e.g. Heidelberg Speedmaster CD 102" />
                        </div>

                        <div class="[display:grid] grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                            <x-form.input label="Nickname (everyday name)" name="nickname" wire:model="nickname" placeholder="e.g. Plong 2, Daxiang" />
                            <x-form.select label="Market Model (catalogue)" name="machine_model_id" wire:model.live="machine_model_id" :options="$this->modelOptions()" placeholder="— select model —" hint="Selecting a model fills in its common specs; change them if the machine differs." />
                        </div>

                        <div class="[display:grid] grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                            <x-form.select label="Type Classification *" name="machine_type" wire:model="machine_type" :options="array_combine($machineTypes, $machineTypes)" placeholder="— select type —" />
                            <x-form.input label="Brand / Manufacturer" name="brand_model" wire:model="brand_model" placeholder="e.g. Heidelberg" />
                        </div>

                        <div class="[display:grid] grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                            <x-form.select label="Production Division" name="division_id" wire:model="division_id" :options="$this->divisionOptions" placeholder="— select division —" />
                            <x-form.select label="Managing Department" name="department_id" wire:model="department_id" :options="$this->departmentOptions" placeholder="— select department —" />
                        </div>

                        <div class="[display:grid] grid-cols-1 md:grid-cols-3 gap-3 mt-3">
                            <x-form.input label="Year Made/Installed" type="number" name="year_acquired" wire:model="year_acquired" placeholder="e.g. 2018" />
                            <x-form.date-picker label="Arrival Date" name="acquired_date" wire:model="acquired_date" hint="Leave empty if only the year is known" />
                            <x-form.input label="Serial Number" name="serial_no" wire:model="serial_no" />
                        </div>

                        <div class="[display:grid] grid-cols-1 md:grid-cols-3 gap-3 mt-3">
                            <x-form.select label="Criticality" name="criticality" wire:model="criticality" :options="array_map('t', \App\Models\Master\ProdMachine::CRITICALITY)" placeholder="— not rated yet —" />
                            <x-form.input label="Plant / Factory Code" name="plant_code" wire:model="plant_code" placeholder="e.g. P1" />
                            <x-form.input label="Location on the Shop Floor" name="location_note" wire:model="location_note" placeholder="e.g. Block B, near the plate warehouse" />
                        </div>

                        <x-ui.card class="mt-3">
                            <x-form.checkbox wire:model="is_process_machine">
                                <span class="font-semibold">Production process machine</span>
                                <span class="block text-[10.5px] text-muted">Untick for support equipment (compressor, forklift) — it will not appear in the routing picker and is not counted as a machine that is "not mapped yet".</span>
                            </x-form.checkbox>
                        </x-ui.card>

                        <div class="mt-3">
                            <x-form.textarea label="Notes" name="notes" wire:model="notes" autogrow />
                        </div>
                    </div>

                    {{-- TAB 2: SPESIFIKASI TEKNIS --}}
                    <div @if ($activeTab !== 'specs') style="display:none" @endif>
                        <x-ui.card title="Media Handling — Sheet Size">
                            <x-slot:intro>Enter in <strong>millimetres</strong>. This number is what the imposition engine & the filter "which machine fits this plano" use — the short text field below is for display only.</x-slot:intro>
                            <div class="[display:grid] grid-cols-2 md:grid-cols-4 gap-3">
                                <x-form.input label="Max Length (mm)" type="number" name="max_sheet_length_mm" wire:model="max_sheet_length_mm" placeholder="1030" />
                                <x-form.input label="Max Width (mm)" type="number" name="max_sheet_width_mm" wire:model="max_sheet_width_mm" placeholder="720" />
                                <x-form.input label="Min Length (mm)" type="number" name="min_sheet_length_mm" wire:model="min_sheet_length_mm" placeholder="340" />
                                <x-form.input label="Min Width (mm)" type="number" name="min_sheet_width_mm" wire:model="min_sheet_width_mm" placeholder="480" />
                            </div>
                            <div class="[display:grid] grid-cols-1 md:grid-cols-3 gap-3 mt-3">
                                <x-form.input label="Min Grammage (gsm)" type="number" name="gsm_min" wire:model="gsm_min" />
                                <x-form.input label="Max Grammage (gsm)" type="number" name="gsm_max" wire:model="gsm_max" />
                                <x-form.input label="Short Size (text)" name="max_paper_size" wire:model="max_paper_size" placeholder="103x72" hint="Display only" />
                            </div>
                        </x-ui.card>

                        <x-ui.card class="mt-3" title="Effective Print Area">
                            <x-slot:intro>The print area is <strong>smaller</strong> than the sheet size because the gripper edge cannot be printed. Without this number a layout can pass system validation but hit the gripper on the machine.</x-slot:intro>
                            <div class="[display:grid] grid-cols-1 md:grid-cols-3 gap-3">
                                <x-form.input label="Print Area Length (mm)" type="number" name="max_print_length_mm" wire:model="max_print_length_mm" />
                                <x-form.input label="Print Area Width (mm)" type="number" name="max_print_width_mm" wire:model="max_print_width_mm" />
                                <x-form.input label="Gripper Margin (mm)" type="number" name="gripper_margin_mm" wire:model="gripper_margin_mm" placeholder="e.g. 10" />
                            </div>
                        </x-ui.card>

                        <x-ui.card class="mt-3" title="Print & Coating Configuration">
                            <div class="[display:grid] grid-cols-1 md:grid-cols-3 gap-3">
                                <x-form.input label="Number of Colour Units" type="number" name="color_count" wire:model="color_count" placeholder="4" hint="0 / empty for finishing machines" />
                                <x-form.input label="Max Colours per Pass" type="number" name="max_colors_per_pass" wire:model="max_colors_per_pass" />
                                <x-form.select label="Drying System" name="drying_system" wire:model="drying_system" :options="$dryingSystems" placeholder="— select —" />
                            </div>
                            <div class="[display:grid] grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                                <x-form.input label="Plate Size" name="plate_size" wire:model="plate_size" placeholder="e.g. 790x1030 mm" />
                                <x-form.input label="Runnable Material Types" name="substrate_types" wire:model="substrate_types" placeholder="ART, DUPLEX, KRAFT" hint="Separate with commas" />
                            </div>
                            <div class="mt-3">
                                <x-form.checkbox wire:model="perfecting">
                                    <span class="font-semibold">Perfecting — can print both sides in 1 pass</span>
                                    <span class="block text-[10.5px] text-muted">Affects the number of passes & time in scheduling</span>
                                </x-form.checkbox>
                            </div>
                        </x-ui.card>
                    </div>

                    {{-- TAB 3: KAPASITAS & PERFORMA --}}
                    <div @if ($activeTab !== 'capacity') style="display:none" @endif>
                        <x-ui.card title="Speed — Three Numbers With Different Meanings">
                            <x-slot:intro><strong>Manufacturer maximum</strong> is the brochure claim. <strong>Actual target</strong> is what is really achieved on the shop floor — this is what scheduling & the OEE basis use. Do not enter the same figure in both if reality is different.</x-slot:intro>
                            <div class="[display:grid] grid-cols-1 md:grid-cols-3 gap-3">
                                <x-form.input label="Manufacturer Max Speed (SPH)" type="number" name="max_speed_per_hour" wire:model="max_speed_per_hour" placeholder="15000" />
                                <x-form.input label="Actual Speed Target (SPH)" type="number" name="speed_target_per_hour" wire:model="speed_target_per_hour" placeholder="12000" />
                                <x-form.input label="Capacity per Shift (text)" name="capacity_per_shift" wire:model="capacity_per_shift" />
                            </div>
                        </x-ui.card>

                        <x-ui.card class="mt-3" title="Machine Calendar — Basis of Capacity & OEE">
                            <x-slot:intro>Without shifts & hours per shift, the available hours are unknown and <strong>Availability OEE cannot be calculated</strong> (the system will not assume 24/7).</x-slot:intro>
                            <div class="[display:grid] grid-cols-1 md:grid-cols-4 gap-3">
                                <x-form.input label="Shifts / Day" type="number" name="shifts_per_day" wire:model="shifts_per_day" placeholder="2" />
                                <x-form.input label="Hours / Shift" name="hours_per_shift" wire:model="hours_per_shift" placeholder="8" />
                                <x-form.input label="Target OEE (%)" name="oee_target_pct" wire:model="oee_target_pct" placeholder="82" />
                                <x-form.input label="Standard MTBF (hours)" type="number" name="mtbf_hours" wire:model="mtbf_hours" placeholder="500" hint="From the machine manual" />
                            </div>
                        </x-ui.card>

                        <x-ui.card class="mt-3" title="Crew Requirement">
                            <x-slot:intro>Operators and helpers are separate because their wage rates differ — Payroll needs the breakdown, not just the total.</x-slot:intro>
                            <div class="[display:grid] grid-cols-1 md:grid-cols-3 gap-3">
                                <x-form.input label="Operator (people)" type="number" name="operator_min" wire:model="operator_min" placeholder="1" />
                                <x-form.input label="Helper (people)" type="number" name="helper_min" wire:model="helper_min" placeholder="2" />
                                <x-form.display label="Total Minimum Crew">
                                    <span class="font-mono font-bold">{{ ($operator_min === '' && $helper_min === '') ? '—' : ((int) $operator_min + (int) $helper_min).' people' }}</span>
                                </x-form.display>
                            </div>
                        </x-ui.card>
                    </div>

                    {{-- TAB 4: BIAYA & ASET --}}
                    <div @if ($activeTab !== 'finance') style="display:none" @endif>
                        <x-ui.card title="Costing & Accounting Allocation">
                            <div class="[display:grid] grid-cols-1 md:grid-cols-3 gap-3">
                                <x-form.input label="Machine Rate / Hour (IDR)" name="hourly_rate" wire:model="hourly_rate" placeholder="250000" hint="Used by the estimator for COGS (HPP)" />
                                <x-form.input label="Power Consumption (kW)" name="power_kw" wire:model="power_kw" placeholder="75" />
                                <x-form.input label="Cost Center / GL Code" name="cost_center_code" wire:model="cost_center_code" placeholder="CT-001" />
                            </div>
                        </x-ui.card>

                        <x-ui.card class="mt-3" title="Asset Register">
                            <div class="[display:grid] grid-cols-1 md:grid-cols-3 gap-3">
                                <x-form.input label="Acquisition Value (IDR)" name="acquisition_cost" wire:model="acquisition_cost" />
                                <x-form.input label="Useful Life (years)" type="number" name="useful_life_years" wire:model="useful_life_years" placeholder="10" />
                                <x-form.date-picker label="Warranty Until" name="warranty_until" wire:model="warranty_until" />
                            </div>
                        </x-ui.card>

                        @if ($mode === 'edit' && $this->whereUsed)
                            <x-ui.card class="mt-3" title="Used Where (Where-used)">
                                <x-slot:intro>Relations to other modules are a <em>logical</em> cross-database — MySQL does not enforce it. Check this list before deactivating the machine.</x-slot:intro>
                                <div class="[display:grid] grid-cols-1 md:grid-cols-2 gap-1.5">
                                    @foreach ($this->whereUsed as $label => $count)
                                        <div class="flex justify-between items-center text-[11.5px] px-2 py-1 rounded bg-surface-alt">
                                            <span class="text-ink-soft">{{ $label }}</span>
                                            <span class="font-mono font-bold {{ $count > 0 ? 'text-warn-ink' : 'text-muted' }}">{{ $count }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </x-ui.card>
                        @endif
                    </div>

                    {{-- TAB 5: PERAWATAN & OEE --}}
                    <div @if ($activeTab !== 'maintenance') style="display:none" @endif>
                        @if ($mode !== 'edit')
                            <x-alert.banner type="info" message="Save this machine first, then the maintenance schedule & downtime log can be filled in here." />
                        @else
                            <x-ui.card title="Preventive Maintenance Schedule">
                                <div class="[display:grid] grid-cols-1 md:grid-cols-4 gap-3">
                                    <x-form.input label="Interval (running hours)" type="number" name="maintenance_interval_hours" wire:model="maintenance_interval_hours" placeholder="500" />
                                    <x-form.input label="Interval (days)" type="number" name="maintenance_interval_days" wire:model="maintenance_interval_days" placeholder="90" />
                                    <x-form.date-picker label="Last Maintenance" name="last_maintenance_date" wire:model="last_maintenance_date" />
                                    <x-form.date-picker label="Next Maintenance" name="next_maintenance_date" wire:model="next_maintenance_date" />
                                </div>
                                <div class="[display:grid] grid-cols-1 md:grid-cols-3 gap-3 mt-3">
                                    <x-form.input label="Total Running Hours" name="total_run_hours" wire:model="total_run_hours" />
                                    <x-form.input label="Total Impressions" type="number" name="total_impressions" wire:model="total_impressions" />
                                    <x-form.input label="Service Vendor" name="service_vendor" wire:model="service_vendor" />
                                </div>
                            </x-ui.card>

                            {{-- OEE AKTUAL --}}
                            @php($o = $this->oee)
                            <x-ui.card class="mt-3" :title="'Actual OEE — last '.$oeeDays.' days'">
                                <x-slot:actions>
                                    @foreach ([7, 30, 90] as $d)
                                        <x-ui.button variant="chip" size="xs" :active="$oeeDays === $d" wire:click="setOeeDays({{ $d }})">{{ $d }} hr</x-ui.button>
                                    @endforeach
                                </x-slot:actions>

                                <div class="[display:grid] grid-cols-2 md:grid-cols-4 gap-2.5">
                                    @foreach ([
                                        'availability' => ['Availability', 'Available hours − unplanned stops'],
                                        'performance' => ['Performance', 'Output vs theoretical capacity'],
                                        'quality' => ['Quality', 'Good output vs total production'],
                                    ] as $fk => [$flabel, $fhint])
                                        @php($f = $o['factors'][$fk])
                                        <x-ui.card variant="plain" size="sm">
                                            <div class="text-[10px] uppercase tracking-wide text-muted">{{ $flabel }}</div>
                                            @if ($f['value'] !== null)
                                                <div class="text-[20px] font-bold leading-tight {{ \App\View\Components\Ui\Tone::text(\App\View\Components\Ui\Tone::forScore($f['value'], 85, 65)) }}">{{ number_format($f['value'], 1, ',', '.') }}<span class="text-[12px]">%</span></div>
                                                <x-ui.progress class="mt-1" :value="$f['value']" :tone="\App\View\Components\Ui\Tone::forScore($f['value'], 85, 65)" />
                                            @else
                                                <div class="text-[13px] font-bold text-muted leading-tight mt-1">cannot be calculated yet</div>
                                                <div class="text-[9.5px] text-warn-ink mt-1 leading-snug">{{ $f['reason'] }}</div>
                                            @endif
                                            <div class="text-[9.5px] text-muted mt-1">{{ $fhint }}</div>
                                        </x-ui.card>
                                    @endforeach

                                    <div class="border-2 rounded p-2.5 {{ $o['oee'] !== null ? 'border-accent bg-info-soft' : 'border-line-soft bg-surface-alt' }}">
                                        <div class="text-[10px] uppercase tracking-wide text-muted">OEE Total</div>
                                        @if ($o['oee'] !== null)
                                            <div class="text-[20px] font-bold leading-tight text-accent">{{ number_format($o['oee'], 1, ',', '.') }}<span class="text-[12px]">%</span></div>
                                            @if ($o['oee_target'] !== null)
                                                <div class="text-[9.5px] mt-1 font-semibold {{ $o['oee'] >= $o['oee_target'] ? 'text-success-ink' : 'text-danger-ink' }}">
                                                    Target {{ number_format($o['oee_target'], 0, ',', '.') }}% — {{ $o['oee'] >= $o['oee_target'] ? 'achieved' : 'short by '.number_format($o['oee_target'] - $o['oee'], 1, ',', '.').' points' }}
                                                </div>
                                            @endif
                                        @else
                                            <div class="text-[13px] font-bold text-muted leading-tight mt-1">cannot be calculated yet</div>
                                            <div class="text-[9.5px] text-muted mt-1 leading-snug">OEE is valid only when all three factors are measured. A target figure alone is not enough.</div>
                                        @endif
                                    </div>
                                </div>

                                @php($rel = $this->reliability)
                                <div class="[display:grid] grid-cols-2 md:grid-cols-4 gap-2.5 mt-2.5 text-[11.5px]">
                                    <x-ui.metric label="Unplanned stops">{{ number_format($o['downtime']['unplanned'] / 60, 1, ',', '.') }} jam</x-ui.metric>
                                    <x-ui.metric label="Planned stops">{{ number_format($o['downtime']['planned'] / 60, 1, ',', '.') }} jam</x-ui.metric>
                                    <x-ui.metric label="Actual MTTR">{{ $rel['mttr_hours'] !== null ? number_format($rel['mttr_hours'], 2, ',', '.').' h' : '—' }}</x-ui.metric>
                                    <x-ui.metric label="Actual MTBF" :hint="$mtbf_hours !== '' && $rel['mtbf_hours'] !== null ? 'standard '.number_format((int) $mtbf_hours, 0, ',', '.').' h' : null">{{ $rel['mtbf_hours'] !== null ? number_format($rel['mtbf_hours'], 1, ',', '.').' h' : '—' }}</x-ui.metric>
                                </div>
                            </x-ui.card>

                            {{-- LOG DOWNTIME --}}
                            <x-ui.card class="mt-3" title="Record Machine Stop (Downtime)">
                                <x-slot:intro>Planned or not <strong>is determined automatically from its type</strong>, not chosen by the operator — if it could be chosen, Availability could easily be massaged.</x-slot:intro>
                                <div class="[display:grid] grid-cols-1 md:grid-cols-5 gap-2.5 items-end">
                                    <x-form.select label="Type" name="dtKind" wire:model="dtKind" :placeholder="false"
                                        :options="collect(\App\Models\Master\ProdMachineDowntime::KINDS)->map(fn ($k) => t($k['label']))->all()" />
                                    <x-form.date-picker label="Start" name="dtStart" wire:model="dtStart" mode="datetime" />
                                    <x-form.date-picker label="End" name="dtEnd" wire:model="dtEnd" mode="datetime" hint="Leave empty if still ongoing" />
                                    <x-form.input label="Cause" name="dtCause" wire:model="dtCause" placeholder="e.g. ink roller jammed" />
                                    <x-ui.button wire:click="addDowntime">+ Add Record</x-ui.button>
                                </div>

                                <x-ui.table size="sm" framed class="mt-3">
                                        <thead>
                                            <tr>
                                                @foreach (['Type', 'Start', 'End', 'Duration', 'Cause', 'Nature', ''] as $h)
                                                    <th>{{ $h }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($this->downtimeRows as $d)
                                                <tr wire:key="dt-{{ $d->downtime_id }}">
                                                    <td class="whitespace-nowrap">{{ $d->kindLabel() }}</td>
                                                    <td class="font-mono whitespace-nowrap">{{ $d->start_at->format('d/m/y H:i') }}</td>
                                                    <td class="font-mono whitespace-nowrap">
                                                        {{ $d->end_at?->format('d/m/y H:i') ?? '—' }}
                                                    </td>
                                                    <td class="font-mono whitespace-nowrap">
                                                        {{ $d->duration_minutes !== null ? number_format($d->duration_minutes / 60, 1, ',', '.').' h' : 'ongoing' }}
                                                    </td>
                                                    <td>{{ $d->root_cause ?: '—' }}</td>
                                                    <td class="whitespace-nowrap">
                                                        <x-ui.badge size="xs" :tone="$d->is_planned ? 'info' : 'danger'">{{ $d->is_planned ? 'Planned' : 'Unplanned' }}</x-ui.badge>
                                                    </td>
                                                    <td class="text-right whitespace-nowrap">
                                                        @if ($d->isOpen())
                                                            <x-grid.action-icon icon="fa-check" title="Close — the machine is running again" action="closeDowntime({{ $d->downtime_id }})" />
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="7" class="px-3 py-4 text-center text-muted">No machine stop records yet. Without them Availability OEE cannot be calculated.</td></tr>
                                            @endforelse
                                        </tbody>
                                </x-ui.table>
                            </x-ui.card>
                        @endif
                    </div>

                    {{-- TAB 6: MATRIKS KAPABILITAS (mesin -> proses) --}}
                    <div @if ($activeTab !== 'processes') style="display:none" @endif>
                        @if ($mode !== 'edit')
                            <x-alert.banner type="info" message="Save this machine first, then the processes it can run can be attached here." />
                        @elseif (! $is_process_machine)
                            <x-alert.banner type="warn" message="This machine is marked as support equipment, so it does not need to be mapped to processes. Tick 'Production process machine' in tab 1 if it is actually used in production." />
                        @else
                            <p class="text-[11px] text-muted mb-3">The processes that <strong>can</strong> be run on this machine — the reverse direction of Process Master, the same data rows. This machine will appear as a choice on each process in this list.</p>

                            <x-ui.card variant="muted" class="flex flex-wrap items-end gap-2 mb-3">
                                <div class="flex-1 min-w-[280px]">
                                    <x-form.select label="Add a process it can run" name="capPickId" wire:model="capPickId" :options="$this->processCandidates" placeholder="— select process —" />
                                </div>
                                <x-ui.button wire:click="capAdd" :disabled="! $capPickId">+ Add Process</x-ui.button>
                            </x-ui.card>

                            <x-ui.table size="sm" framed>
                                    <thead>
                                        <tr>
                                            @foreach (['Process', 'Stage / Dept', 'Effective Capacity', 'Setup', 'Waste', 'Rate / Hour', 'Crew', 'Est. 10,000 units', 'Role', ''] as $h)
                                                <th>{{ $h }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($this->capabilityRows as $cap)
                                            @php($rp = $this->resolvedParams[$cap->capability_id] ?? null)
                                            <tr wire:key="mcap-{{ $cap->capability_id }}" class="{{ $cap->activate ? '' : 'opacity-45' }}">
                                                <td class="whitespace-nowrap">
                                                    @if ($cap->process->process_code)
                                                        <x-ui.badge tone="code" size="md" mono>{{ $cap->process->process_code }}</x-ui.badge>
                                                    @endif
                                                    <div class="text-[11.5px] font-semibold mt-0.5">{{ $cap->process->process_name }}</div>
                                                </td>
                                                <td>
                                                    <div class="text-[11px]">{{ $cap->process->stageLabel() }}</div>
                                                    <div class="text-[10px] text-muted">{{ $cap->process->department?->department_name ?? '—' }}</div>
                                                    @if (! $cap->process->activate)
                                                        <div class="text-[10px] font-bold text-danger-ink mt-0.5">process inactive</div>
                                                    @endif
                                                </td>
                                                @if ($rp)
                                                    @foreach (['speed_per_hour', 'setup_minutes'] as $f)
                                                        <td class="whitespace-nowrap">
                                                            @if ($rp['params'][$f]['value'] !== null)
                                                                <span class="font-mono text-[11.5px]">{{ number_format((float) $rp['params'][$f]['value'], 0, ',', '.') }}</span>
                                                                <span class="block text-[9.5px] {{ $rp['params'][$f]['source'] === 'CAPABILITY' ? 'text-info-ink font-bold' : 'text-muted' }}">{{ $rp['params'][$f]['source'] === 'CAPABILITY' ? 'pair-specific' : ($rp['params'][$f]['source'] === 'PROCESS' ? 'default proses' : 'from machine') }}</span>
                                                            @else
                                                                <span class="text-[10px] text-warn-ink">belum ada</span>
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                    <td class="whitespace-nowrap">
                                                        @if ($rp['params']['waste_pct']['value'] !== null)
                                                            <span class="font-mono text-[11.5px]">{{ rtrim(rtrim((string) $rp['params']['waste_pct']['value'], '0'), '.') ?: '0' }}%</span>
                                                        @else
                                                            <span class="text-[10px] text-muted">—</span>
                                                        @endif
                                                    </td>
                                                    <td class="whitespace-nowrap">
                                                        @if ($rp['params']['hourly_rate']['value'] > 0)
                                                            <span class="font-mono text-[11.5px]">{{ number_format((float) $rp['params']['hourly_rate']['value'], 0, ',', '.') }}</span>
                                                        @else
                                                            <span class="text-[10px] text-warn-ink">belum ada</span>
                                                        @endif
                                                    </td>
                                                    <td class="font-mono text-[11.5px]">{{ $rp['params']['manpower']['value'] ?? '—' }}</td>
                                                    <td class="whitespace-nowrap">
                                                        @if ($rp['hours_10k'] !== null)
                                                            <span class="font-mono font-semibold text-[11.5px]">{{ number_format($rp['hours_10k'], 2, ',', '.') }}</span><span class="text-[10px] text-muted"> jam</span>
                                                        @else
                                                            <span class="text-[10px] text-muted">cannot be calculated</span>
                                                        @endif
                                                    </td>
                                                @else
                                                    <td colspan="6" class="text-muted">—</td>
                                                @endif
                                                <td class="whitespace-nowrap">
                                                    <x-ui.badge-button :tone="$cap->preference_rank === 1 ? 'highlight' : 'muted'" wire:click="capTogglePrimary({{ $cap->capability_id }})"
                                                        title="Primary machine for this process?">{{ $cap->preference_rank === 1 ? '★ Primary' : 'Alternative' }}</x-ui.badge-button>
                                                    <x-ui.badge-button size="xs" class="flex mt-1" :tone="$cap->is_certified ? 'success' : 'warn'" wire:click="capToggleCertified({{ $cap->capability_id }})">{{ $cap->is_certified ? 'Validated' : 'Not validated yet' }}</x-ui.badge-button>
                                                </td>
                                                <td class="text-right whitespace-nowrap">
                                                    <x-grid.action-icon icon="fa-sliders" title="Set parameters specific to this pair" action="capEdit({{ $cap->capability_id }})" />
                                                    @if ($cap->activate)
                                                        <x-grid.action-icon icon="fa-ban" title="Detach process from this machine" variant="danger"
                                                            action="capRemove({{ $cap->capability_id }})"
                                                            :confirm="'Detach process '.$cap->process->process_name.' from this machine? Parameters already set are kept.'" />
                                                    @else
                                                        <x-grid.action-icon icon="fa-rotate" title="Re-attach" action="capRestore({{ $cap->capability_id }})" />
                                                    @endif
                                                </td>
                                            </tr>

                                            @if ($capEditingId === $cap->capability_id)
                                                <tr wire:key="mcapedit-{{ $cap->capability_id }}" class="bg-surface-hover">
                                                    <td colspan="10" class="px-3 py-3">
                                                        <p class="text-[10px] font-bold text-accent uppercase mb-1">Parameter {{ $cap->process->process_name }} on machine {{ $machine_code }}</p>
                                                        <p class="text-[11px] text-muted mb-2.5">Leaving a box empty means <strong>follow the process default value</strong>. Fill in only what really differs on this machine.</p>
                                                        <div class="[display:grid] grid-cols-2 md:grid-cols-4 gap-2.5">
                                                            <x-form.input label="Capacity / hour" name="capForm.speed_per_hour" wire:model="capForm.speed_per_hour" placeholder="default proses" />
                                                            <x-form.input label="Setup (minutes)" type="number" name="capForm.setup_minutes" wire:model="capForm.setup_minutes" placeholder="default proses" />
                                                            <x-form.input label="Waste (%)" name="capForm.waste_pct" wire:model="capForm.waste_pct" placeholder="default proses" />
                                                            <x-form.input label="Fixed insheet" type="number" name="capForm.waste_fixed_qty" wire:model="capForm.waste_fixed_qty" placeholder="default proses" />
                                                            <x-form.input label="Rate / hour (IDR)" name="capForm.hourly_rate" wire:model="capForm.hourly_rate" placeholder="machine rate" />
                                                            <x-form.input label="Kru" type="number" name="capForm.manpower" wire:model="capForm.manpower" placeholder="default proses" />
                                                            <x-form.input label="Min grammage" type="number" name="capForm.gsm_min" wire:model="capForm.gsm_min" placeholder="machine limit" />
                                                            <x-form.input label="Max grammage" type="number" name="capForm.gsm_max" wire:model="capForm.gsm_max" placeholder="machine limit" />
                                                        </div>
                                                        <div class="mt-2.5">
                                                            <x-form.input label="Notes" name="capForm.note" wire:model="capForm.note" placeholder="e.g. only for duplex above 250 gsm" />
                                                        </div>
                                                        <x-ui.action-bar variant="inline">
                                                            <x-ui.button preset="save" size="sm" type="button" wire:click="capSaveParams" />
                                                            <x-ui.button preset="cancel" size="sm" wire:click="capCancelEdit" />
                                                        </x-ui.action-bar>
                                                    </td>
                                                </tr>
                                            @endif
                                        @empty
                                            <tr><td colspan="10" class="px-3 py-6 text-center text-muted text-[12px]">
                                                No process attached yet. This machine will not appear as a choice in any routing.
                                            </td></tr>
                                        @endforelse
                                    </tbody>
                            </x-ui.table>
                        @endif
                    </div>

                    <x-ui.action-bar>
                        <x-ui.button preset="save" />
                        <x-ui.button preset="cancel" wire:click="showGrid" />
                        <x-back-button fallback="master.machine" class="ml-auto" />
                    </x-ui.action-bar>
                </form>
            </div>
        @endif
    </div>
</div>
