<?php

use App\Livewire\Concerns\HasGridStatFilter;
use App\Livewire\Concerns\ManagesProcessMachineCapability;
use App\Models\Master\OrgDepartment;
use App\Models\Master\ProdMachine;
use App\Models\Master\ProdProcess;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Master Proses (PROD_MST_PROCESS) -- data riil dimigrasi dari tm2old.m_process,
// lalu diperluas jadi master routing pada 2026-09-22 (permintaan user).
//
// Bagian paling penting di layar ini adalah tab "Mesin Pelaksana": matriks
// kapabilitas prod_process_machine. Itu jawaban untuk "pilih proses -> mesin
// mana saja yang bisa mengerjakannya", dan sekaligus sumber parameter khusus
// pasangan proses x mesin (plong di die cutter otomatis != di yang manual).
//
// Angka yang belum diisi DIBIARKAN KOSONG, tidak diganti 0: CostingEngine &
// Scheduling membaca master ini, dan 0 akan diperlakukan sebagai fakta.
// Kolom "Kesiapan" di grid yang menunjukkan mana yang masih perlu dilengkapi.
new #[Title('Master Proses — TELASIH MAKMUR ERP')] class extends Component
{
    use HasGridStatFilter;
    use ManagesProcessMachineCapability;

    public string $search = '';

    public string $stageFilter = '';

    public string $panel = 'grid'; // grid | form

    public string $mode = 'new'; // new | edit

    public string $activeTab = 'main';

    public ?int $editingId = null;

    // --- tab 1: info utama ---
    public string $process_code = '';

    public string $process_name = '';

    public ?int $department_id = null;

    public string $process_stage = '';

    public string $routing_seq = '';

    public string $gantt_color = '#2c3e69';

    public bool $process_activate = true;

    public bool $inhouse = true;

    public bool $is_manual = false;

    public string $description = '';

    // --- tab 2: parameter & kapasitas ---
    public string $output_uom = 'LEMBAR';

    public string $default_setup_minutes = '';

    public string $default_speed_per_hour = '';

    public string $default_waste_pct = '';

    public string $default_waste_fixed_qty = '';

    public string $default_tolerance_pct = '';

    public string $default_manpower = '';

    public string $min_lot_qty = '';

    public string $max_lot_qty = '';

    public bool $is_bottleneck = false;

    public bool $allow_parallel = false;

    // --- tab 3: ongkos & tooling ---
    public string $cost_basis = '';

    public string $standard_rate = '';

    public bool $requires_plate = false;

    public bool $requires_die = false;

    public bool $requires_tooling = false;

    public bool $qc_required = false;

    public string $skill_code = '';

    public string $subcon_lead_days = '';

    #[Computed]
    public function processes()
    {
        return $this->applyStatFilter(
            ProdProcess::query()
                ->with('department')
                ->withCount(['capabilities' => fn ($q) => $q->where('activate', true)])
                ->when($this->search !== '', fn ($q) => $q->where(fn ($qq) => $qq
                    ->where('process_name', 'like', "%{$this->search}%")
                    ->orWhere('process_code', 'like', "%{$this->search}%")))
                ->when($this->stageFilter !== '', fn ($q) => $q->where('process_stage', $this->stageFilter))
        )
            // Urut sesuai alur produksi, bukan alfabet: officer membaca master
            // ini sebagai urutan routing.
            ->orderByRaw('FIELD(process_stage, "PREPRESS","PRESS","POSTPRESS","FINISHING","PACKING","QC","LOGISTIC")')
            ->orderByRaw('routing_seq IS NULL, routing_seq')
            ->orderBy('process_name')
            ->get();
    }

    // Pembagi untuk bar kapasitas di grid. Diskalakan ke baris TERCEPAT yang
    // sedang tampil, bukan angka tetap: kecepatan proses di sini beda 2 orde
    // (potong 50.000 lembar/jam vs emboss 3.000), jadi skala tetap bikin
    // hampir semua bar kelihatan kosong. Ikut berubah saat difilter --
    // tujuannya perbandingan antar-baris yang terlihat, bukan nilai absolut.
    #[Computed]
    public function speedScaleMax(): float
    {
        return max(1, (float) $this->processes->max('default_speed_per_hour'));
    }

    #[Computed]
    public function departmentOptions()
    {
        return OrgDepartment::query()->where('activate', true)
            ->orderBy('department_name')->pluck('department_name', 'department_id')->all();
    }

    // Mesin yang sudah dipasangkan ke proses yang sedang dibuka.
    #[Computed]
    public function capabilityRows()
    {
        if (! $this->editingId) {
            return collect();
        }

        return \App\Models\Master\ProdProcessMachine::query()
            ->with('machine.department')
            ->where('process_id', $this->editingId)
            ->orderByDesc('activate')
            ->orderBy('preference_rank')
            ->get()
            ->filter(fn ($c) => $c->machine !== null)
            ->sortBy([
                fn ($a, $b) => ($b->activate <=> $a->activate),
                fn ($a, $b) => ($a->preference_rank <=> $b->preference_rank),
                fn ($a, $b) => strcmp($a->machine->machine_code, $b->machine->machine_code),
            ])
            ->values();
    }

    // Kandidat mesin yang BELUM aktif dipasangkan. Alat penunjang
    // (kompresor/forklift) tidak ikut -- tidak pernah jadi mesin proses.
    #[Computed]
    public function machineCandidates()
    {
        $taken = $this->capabilityRows->where('activate', true)->pluck('machine_id')->all();

        return ProdMachine::query()
            ->where('activate', true)
            ->where('is_process_machine', true)
            ->whereNotIn('machine_id', $taken)
            ->orderBy('machine_type')->orderBy('machine_code')
            ->get()
            ->mapWithKeys(fn ($m) => [$m->machine_id => $m->machine_code.' — '.$m->machine_name.' ('.$m->machine_type.')'])
            ->all();
    }

    // Parameter hasil resolusi 3 lapis untuk tiap pasangan; dipakai panel
    // supaya officer lihat angka EFEKTIF, bukan cuma kolom override yg kosong.
    #[Computed]
    public function resolvedParams()
    {
        if (! $this->editingId) {
            return [];
        }

        $process = ProdProcess::find($this->editingId);
        $svc = $this->capabilityService();

        return $this->capabilityRows
            ->mapWithKeys(fn ($cap) => [$cap->capability_id => [
                'params' => $svc->resolveParams($cap, $process),
                'hours_10k' => $svc->estimateHours($cap, 10000, $process),
            ]])->all();
    }

    #[Computed]
    public function whereUsed()
    {
        return $this->editingId ? $this->capabilityService()->whereUsedProcess($this->editingId) : [];
    }

    protected function capabilityOwner(): array
    {
        return ['process_id' => (int) $this->editingId];
    }

    protected function refreshCapabilityViews(): void
    {
        unset($this->capabilityRows, $this->machineCandidates, $this->resolvedParams, $this->processes);
    }

    public function openModal(string $mode, ?int $id = null): void
    {
        $this->resetErrorBag();
        $this->mode = in_array($mode, ['new', 'edit'], true) ? $mode : 'new';
        $this->editingId = $id;
        $this->activeTab = 'main';
        $this->capEditingId = null;
        $this->capPickId = null;

        if ($this->mode === 'edit' && $id) {
            $p = ProdProcess::findOrFail($id);
            $this->process_code = (string) $p->process_code;
            $this->process_name = $p->process_name;
            $this->department_id = $p->department_id;
            $this->process_stage = (string) $p->process_stage;
            $this->routing_seq = (string) ($p->routing_seq ?? '');
            $this->gantt_color = $p->gantt_color ?: '#2c3e69';
            $this->process_activate = (bool) $p->activate;
            $this->inhouse = (bool) $p->inhouse;
            $this->is_manual = (bool) $p->is_manual;
            $this->description = (string) $p->description;
            $this->output_uom = (string) ($p->output_uom ?: 'LEMBAR');
            $this->default_setup_minutes = (string) ($p->default_setup_minutes ?? '');
            $this->default_speed_per_hour = $this->num($p->default_speed_per_hour);
            $this->default_waste_pct = $this->num($p->default_waste_pct);
            $this->default_waste_fixed_qty = (string) ($p->default_waste_fixed_qty ?? '');
            $this->default_tolerance_pct = $this->num($p->default_tolerance_pct);
            $this->default_manpower = (string) ($p->default_manpower ?? '');
            $this->min_lot_qty = (string) ($p->min_lot_qty ?? '');
            $this->max_lot_qty = (string) ($p->max_lot_qty ?? '');
            $this->is_bottleneck = (bool) $p->is_bottleneck;
            $this->allow_parallel = (bool) $p->allow_parallel;
            $this->cost_basis = (string) $p->cost_basis;
            $this->standard_rate = $this->num($p->standard_rate);
            $this->requires_plate = (bool) $p->requires_plate;
            $this->requires_die = (bool) $p->requires_die;
            $this->requires_tooling = (bool) $p->requires_tooling;
            $this->qc_required = (bool) $p->qc_required;
            $this->skill_code = (string) $p->skill_code;
            $this->subcon_lead_days = (string) ($p->subcon_lead_days ?? '');
        } else {
            $this->reset([
                'process_code', 'process_name', 'department_id', 'process_stage', 'routing_seq',
                'description', 'default_setup_minutes', 'default_speed_per_hour',
                'default_waste_pct', 'default_waste_fixed_qty', 'default_tolerance_pct',
                'default_manpower', 'min_lot_qty', 'max_lot_qty', 'cost_basis', 'standard_rate',
                'skill_code', 'subcon_lead_days',
            ]);
            $this->gantt_color = '#2c3e69';
            $this->output_uom = 'LEMBAR';
            $this->process_activate = true;
            $this->inhouse = true;
            $this->is_manual = false;
            $this->is_bottleneck = false;
            $this->allow_parallel = false;
            $this->requires_plate = $this->requires_die = $this->requires_tooling = $this->qc_required = false;
        }

        $this->panel = 'form';
    }

    // decimal:N cast mengembalikan "45.00"; buang nol berlebih supaya kotak
    // input tidak menampilkan "2.50" untuk angka yang diketik "2.5".
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
        $unique = 'unique:tm2mst.prod_process,process_name';
        $uniqueCode = 'unique:tm2mst.prod_process,process_code';
        if ($this->mode === 'edit' && $this->editingId) {
            $unique .= ','.$this->editingId.',id_process';
            $uniqueCode .= ','.$this->editingId.',id_process';
        }

        $data = $this->validate([
            'process_code' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9_-]+$/', $uniqueCode],
            'process_name' => ['required', 'string', 'max:200', $unique],
            'department_id' => ['nullable', 'integer'],
            'process_stage' => ['nullable', 'string', 'in:'.implode(',', array_keys(ProdProcess::STAGES))],
            'routing_seq' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'gantt_color' => ['nullable', 'string', 'max:10'],
            'output_uom' => ['nullable', 'string', 'in:'.implode(',', array_keys(ProdProcess::OUTPUT_UOM))],
            'default_setup_minutes' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'default_speed_per_hour' => ['nullable', 'numeric', 'min:0'],
            'default_waste_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_waste_fixed_qty' => ['nullable', 'integer', 'min:0'],
            'default_tolerance_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_manpower' => ['nullable', 'integer', 'min:0', 'max:255'],
            'min_lot_qty' => ['nullable', 'integer', 'min:0'],
            'max_lot_qty' => ['nullable', 'integer', 'min:0'],
            'cost_basis' => ['nullable', 'string', 'in:'.implode(',', array_keys(ProdProcess::COST_BASIS))],
            'standard_rate' => ['nullable', 'numeric', 'min:0'],
            'skill_code' => ['nullable', 'string', 'max:30'],
            'subcon_lead_days' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'description' => ['nullable', 'string'],
        ]);

        if ($data['min_lot_qty'] !== '' && $data['max_lot_qty'] !== ''
            && (int) $data['min_lot_qty'] > (int) $data['max_lot_qty']) {
            $this->activeTab = 'capacity';
            $this->addError('max_lot_qty', 'Lot maksimum harus >= lot minimum.');

            return;
        }

        // Tarif tanpa basis = angka tak bermakna (per jam? per 1000?), dan
        // CostingEngine akan salah 3 digit. Blokir di sini, bukan di engine.
        if ($data['standard_rate'] !== '' && $data['cost_basis'] === '') {
            $this->activeTab = 'cost';
            $this->addError('cost_basis', 'Pilih basis ongkos dulu — tarif tanpa basis tidak bisa dipakai menghitung HPP.');

            return;
        }

        $payload = collect($data)->map(fn ($v) => $v === '' ? null : $v)->all();
        $payload['gantt_color'] = $data['gantt_color'] ?: null;
        $payload['process_code'] = $data['process_code'] ? strtoupper($data['process_code']) : null;
        $payload['activate'] = $this->process_activate;
        $payload['inhouse'] = $this->inhouse;
        $payload['is_manual'] = $this->is_manual;
        $payload['is_bottleneck'] = $this->is_bottleneck;
        $payload['allow_parallel'] = $this->allow_parallel;
        $payload['requires_plate'] = $this->requires_plate;
        $payload['requires_die'] = $this->requires_die;
        $payload['requires_tooling'] = $this->requires_tooling;
        $payload['qc_required'] = $this->qc_required;
        $payload['updated_by'] = Auth::user()->login;

        if ($this->mode === 'edit' && $this->editingId) {
            ProdProcess::whereKey($this->editingId)->update($payload);
        } else {
            $payload['created_by'] = Auth::user()->login;
            $new = ProdProcess::create($payload);
            // Tetap di form: proses baru belum punya mesin, dan tanpa mesin
            // dia belum bisa dipakai routing. Langsung antar ke tab-nya.
            $this->editingId = $new->id_process;
            $this->mode = 'edit';
            $this->activeTab = 'machines';
            $this->refreshCapabilityViews();
            $this->resetStatCache();

            return;
        }

        $this->refreshCapabilityViews();
        $this->resetStatCache();
        $this->panel = 'grid';
    }

    public function toggleActive(int $id): void
    {
        $p = ProdProcess::findOrFail($id);
        $p->activate = ! $p->activate;
        $p->updated_by = Auth::user()->login;
        $p->save();

        unset($this->processes);
        $this->resetStatCache();
    }

    private function resetStatCache(): void
    {
        unset($this->statTotal, $this->statActive, $this->statInactive,
            $this->statBottleneck, $this->statSubcon, $this->statNoMachine);
    }

    #[Computed]
    public function statTotal(): int
    {
        return ProdProcess::count();
    }

    #[Computed]
    public function statActive(): int
    {
        return ProdProcess::where('activate', true)->count();
    }

    #[Computed]
    public function statInactive(): int
    {
        return ProdProcess::where('activate', false)->count();
    }

    #[Computed]
    public function statBottleneck(): int
    {
        return ProdProcess::where('activate', true)->where('is_bottleneck', true)->count();
    }

    #[Computed]
    public function statSubcon(): int
    {
        return ProdProcess::where('activate', true)->where('inhouse', false)->count();
    }

    // Proses yang butuh mesin tapi belum punya satu pun -- tidak bisa
    // dijadwalkan. is_manual dikecualikan (memang tanpa mesin).
    #[Computed]
    public function statNoMachine(): int
    {
        return ProdProcess::where('activate', true)->where('is_manual', false)
            ->whereDoesntHave('capabilities', fn ($q) => $q->where('activate', true))
            ->count();
    }

    protected function statFilterDefs(): array
    {
        return [
            'total' => ['label' => 'Total Proses', 'value' => $this->statTotal, 'tone' => 'total'],
            'active' => ['label' => 'Aktif', 'value' => $this->statActive, 'tone' => 'active',
                'filter' => fn ($q) => $q->where('activate', true)],
            'bottleneck' => ['label' => 'Bottleneck', 'value' => $this->statBottleneck, 'tone' => 'warn',
                'subtitle' => 'Kapasitas penentu',
                'filter' => fn ($q) => $q->where('activate', true)->where('is_bottleneck', true)],
            'subcon' => ['label' => 'Subkon', 'value' => $this->statSubcon, 'tone' => 'extra',
                'subtitle' => 'Dikerjakan pihak ke-3',
                'filter' => fn ($q) => $q->where('activate', true)->where('inhouse', false)],
            'nomachine' => ['label' => 'Tanpa Mesin', 'value' => $this->statNoMachine, 'tone' => 'critical',
                'subtitle' => 'Belum bisa dijadwalkan',
                'filter' => fn ($q) => $q->where('activate', true)->where('is_manual', false)
                    ->whereDoesntHave('capabilities', fn ($qq) => $qq->where('activate', true))],
            'inactive' => ['label' => 'Nonaktif', 'value' => $this->statInactive, 'tone' => 'critical',
                'filter' => fn ($q) => $q->where('activate', false)],
        ];
    }
};
?>

<div>
    <div class="max-w-[1480px] mx-auto my-5 px-4">

        {{-- ============ GRID (LIST) ============ --}}
        @if ($panel === 'grid')
        <x-grid.stat-filter-row :boxes="$this->statBoxes()" :active="$statFilter" />

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari kode atau nama proses…"
                        class="w-56 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <x-slot:tools>
                    <select wire:model.live="stageFilter"
                        class="shrink-0 px-2 py-1.5 border border-[#d7dbe2] rounded text-xs bg-white">
                        <option value="">Semua Tahap</option>
                        @foreach (\App\Models\Master\ProdProcess::STAGES as $k => $v)
                            <option value="{{ $k }}">{{ $v }}</option>
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
                                'Kode' => 'w-[72px]', 'Proses & Departemen' => '', 'Tahap' => 'w-[110px]',
                                'Eksekusi' => 'w-[96px]', 'Kapasitas / Jam' => 'w-[132px]',
                                'Setup' => 'w-[70px]', 'Waste Std' => 'w-[92px]',
                                'Mesin' => 'w-[92px]', 'Ongkos Std' => 'w-[124px]',
                                'Kesiapan' => 'w-[104px]', 'Status' => 'w-[80px]',
                            ] as $label => $w)
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] {{ $w }}">{{ $label }}</th>
                            @endforeach
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->processes as $p)
                            @php($ready = $p->readinessScore())
                            <tr wire:key="process-{{ $p->id_process }}" wire:dblclick="openModal('edit', {{ $p->id_process }})" class="hover:bg-[#f7faff] cursor-pointer" title="Klik 2x untuk buka detail">
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    @if ($p->process_code)
                                        <span class="text-[11px] font-mono font-bold px-1.5 py-0.5 rounded bg-[#eef1f5] text-[#2c3e69]">{{ $p->process_code }}</span>
                                    @else
                                        <span class="text-[10px] text-[#c2410c]" title="Kode proses belum diisi">belum</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2.5 h-2.5 rounded-full shrink-0 border border-[#d7dbe2]" style="background:{{ $p->gantt_color ?: '#cbd5e1' }}"></span>
                                        <div class="min-w-0">
                                            <div class="font-semibold text-[12.5px] truncate">{{ $p->process_name }}</div>
                                            <div class="text-[10.5px] text-muted truncate">
                                                {{ $p->department?->department_name ?? 'Dept belum diisi' }}
                                                @if ($p->is_bottleneck) <span class="text-[#b45309] font-bold">· BOTTLENECK</span> @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    @if ($p->process_stage)
                                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-[#eff6ff] text-[#1d4ed8]">{{ $p->stageLabel() }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    @if ($p->is_manual)
                                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-[#f5f3ff] text-[#6d28d9]">Manual</span>
                                    @elseif ($p->inhouse)
                                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-[#eff6ff] text-[#1d4ed8]">In-house</span>
                                    @else
                                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-[#fff7ed] text-[#c2410c]">Subkon</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    @if ($p->default_speed_per_hour > 0)
                                        <div class="text-[11.5px] leading-tight">
                                            <span class="font-mono font-semibold">{{ number_format((float) $p->default_speed_per_hour, 0, ',', '.') }}</span>
                                            <span class="text-[10px] text-muted">{{ $p->output_uom === 'LEMBAR' ? 'Lembar' : ucfirst(strtolower($p->output_uom)) }} / Jam</span>
                                        </div>
                                        {{-- Bar pembanding kapasitas antar-baris (permintaan user 2026-09-22) --}}
                                        <div class="mt-1 h-1.5 rounded-full bg-[#eef1f5] overflow-hidden">
                                            <div class="h-full rounded-full bg-[#2f80ed]"
                                                style="width:{{ max(3, round((float) $p->default_speed_per_hour / $this->speedScaleMax * 100)) }}%"></div>
                                        </div>
                                    @elseif ($p->is_manual)
                                        <span class="text-[10.5px] text-muted">Manual</span>
                                    @else
                                        <span class="text-[10.5px] text-[#c2410c]">belum diisi</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    @if ($p->default_setup_minutes !== null)
                                        <span class="font-mono text-[12px]">{{ $p->default_setup_minutes }}</span><span class="text-[10px] text-muted"> mnt</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-[11.5px]">
                                    @if ($p->default_waste_pct !== null || $p->default_waste_fixed_qty !== null)
                                        <span class="font-mono font-semibold {{ (float) $p->default_waste_pct >= 2.5 ? 'text-[#b91c1c]' : 'text-[#15803d]' }}">{{ $this->num($p->default_waste_pct) ?: '0' }}%</span>
                                        @if ($p->default_waste_fixed_qty)
                                            <div class="text-[10px] text-muted">+{{ number_format($p->default_waste_fixed_qty, 0, ',', '.') }} tetap</div>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                {{-- Kolom inti permintaan user: berapa mesin bisa mengerjakan proses ini --}}
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    @if ($p->capabilities_count > 0)
                                        <button type="button" wire:click="openModal('edit', {{ $p->id_process }})"
                                            class="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded bg-[#eaf7ee] text-[#166534] cursor-pointer"
                                            title="Lihat mesin pelaksana">
                                            <i class="fa-solid fa-gears text-[9px]"></i> {{ $p->capabilities_count }} unit
                                        </button>
                                    @elseif ($p->is_manual)
                                        <span class="text-[10.5px] text-muted">tanpa mesin</span>
                                    @else
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-[#fef2f2] text-[#b91c1c]" title="Proses ini belum bisa dijadwalkan">belum ada</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    @if ($p->standard_rate > 0)
                                        <div class="font-mono text-[11.5px]">{{ number_format((float) $p->standard_rate, 0, ',', '.') }}</div>
                                        <div class="text-[10px] text-muted">{{ \App\Models\Master\ProdProcess::COST_BASIS[$p->cost_basis] ?? '—' }}</div>
                                    @else
                                        <span class="text-[10.5px] text-[#c2410c]">belum diisi</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[11px] font-mono font-bold px-2 py-0.5 rounded {{ $ready >= 80 ? 'bg-[#eaf7ee] text-[#166534]' : ($ready >= 50 ? 'bg-[#fff7ed] text-[#c2410c]' : 'bg-[#fef2f2] text-[#b91c1c]') }}"
                                        title="Kelengkapan data agar bisa dipakai engine">{{ $ready }}%</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block {{ $p->activate ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#eef0f3] text-[#8a8f98]' }}">{{ $p->activate ? 'AKTIF' : 'NONAKTIF' }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-pen" title="Edit" action="openModal('edit', {{ $p->id_process }})" />
                                    <x-grid.action-icon :active="$p->activate" :title="$p->activate ? 'Nonaktifkan' : 'Aktifkan'" action="toggleActive({{ $p->id_process }})"
                                        :confirm="($p->activate ? 'Nonaktifkan' : 'Aktifkan').' proses '.$p->process_name.'?'"
                                        :variant="$p->activate ? 'danger' : 'default'" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="12" message="Tidak ada." />
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
                    {{ $mode === 'edit' ? 'Edit Proses'.($process_code ? ' : '.$process_code : '') : 'Proses Baru' }}
                </div>
                @if ($mode === 'edit')
                    <div class="text-[11px] opacity-80">{{ $this->capabilityRows->where('activate', true)->count() }} mesin pelaksana</div>
                @endif
            </div>

            <div class="bg-white border border-t-0 border-[#d7dbe2] rounded-b-md overflow-hidden">
                <div class="flex bg-[#eef1f5] border-b border-[#d7dbe2] flex-wrap">
                    @foreach ([
                        'main' => '1. Info Utama',
                        'capacity' => '2. Parameter & Kapasitas',
                        'cost' => '3. Ongkos & Tooling',
                        'machines' => '4. Mesin Pelaksana',
                    ] as $key => $label)
                        <button type="button" wire:click="$set('activeTab', '{{ $key }}')"
                            class="px-4 py-2 text-xs font-semibold border-r border-[#d7dbe2] {{ $activeTab === $key ? 'bg-white text-accent border-b-2 border-b-accent' : 'text-[#4b5568]' }}">
                            {{ $label }}
                            @if ($key === 'machines' && $mode === 'edit')
                                <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded-full {{ $this->capabilityRows->where('activate', true)->count() > 0 ? 'bg-[#eaf7ee] text-[#166534]' : 'bg-[#fef2f2] text-[#b91c1c]' }}">{{ $this->capabilityRows->where('activate', true)->count() }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>

                <form wire:submit="save" class="p-5">
                    {{-- TAB 1 --}}
                    <div @if ($activeTab !== 'main') style="display:none" @endif>
                        <div class="[display:grid] grid-cols-1 md:grid-cols-2 gap-3">
                            <x-form.input label="Kode Proses" name="process_code" wire:model="process_code" placeholder="mis. CE, PLG, LAM" hint="Huruf/angka, maks 10 karakter. Dipakai di SPK & routing." />
                            <x-form.select label="Status Aktif" wire:model="process_activate" :placeholder="false" :options="['1' => 'Aktif', '0' => 'Nonaktif']" />
                        </div>

                        <div class="mt-3">
                            <x-form.input label="Nama Proses *" name="process_name" wire:model="process_name" placeholder="mis. Cetak Offset 4 Warna" />
                        </div>

                        <div class="[display:grid] grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                            <x-form.select label="Tahap Produksi" name="process_stage" wire:model="process_stage" :options="\App\Models\Master\ProdProcess::STAGES" placeholder="— pilih tahap —" />
                            <x-form.select label="Departemen Terkait" name="department_id" wire:model="department_id" :options="$this->departmentOptions" placeholder="— pilih departemen —" />
                        </div>

                        <div class="[display:grid] grid-cols-1 md:grid-cols-3 gap-3 mt-3">
                            <x-form.input label="Urutan Routing" type="number" name="routing_seq" wire:model="routing_seq" placeholder="10, 20, 30…" hint="Usulan urutan di BOP" />
                            <x-form.input label="Warna Gantt" type="color" wire:model="gantt_color" hint="Pembeda proses di MPS" />
                            <x-form.input label="Sertifikasi Kru" name="skill_code" wire:model="skill_code" placeholder="mis. OP-OFFSET" />
                        </div>

                        <div class="mt-3 border border-[#d7dbe2] rounded-md p-3 [display:grid] grid-cols-1 md:grid-cols-3 gap-2">
                            <x-form.checkbox label="Dikerjakan sendiri (in-house)" wire:model="inhouse" />
                            <x-form.checkbox label="Proses tangan — tanpa mesin" wire:model="is_manual" />
                            <x-form.checkbox label="Wajib lewat QC gate" wire:model="qc_required" />
                        </div>
                        @if (! $inhouse)
                            <div class="mt-3 max-w-xs">
                                <x-form.input label="Lead Time Subkon (hari)" type="number" name="subcon_lead_days" wire:model="subcon_lead_days" />
                            </div>
                        @endif

                        <div class="mt-3">
                            <x-form.textarea label="Keterangan" name="description" wire:model="description" autogrow placeholder="Catatan cara kerja, syarat bahan, hal yang sering salah…" />
                        </div>
                    </div>

                    {{-- TAB 2 --}}
                    <div @if ($activeTab !== 'capacity') style="display:none" @endif>
                        <p class="text-[11px] text-muted mb-3">Angka di sini adalah <strong>nilai default</strong> proses. Kalau satu mesin punya angka berbeda, isi override-nya di tab <strong>Mesin Pelaksana</strong> — jangan ubah angka default ini.</p>

                        <div class="[display:grid] grid-cols-1 md:grid-cols-2 gap-3">
                            <x-form.select label="Satuan Hasil (UoM)" name="output_uom" wire:model="output_uom" :placeholder="false" :options="\App\Models\Master\ProdProcess::OUTPUT_UOM" />
                            <x-form.input label="Setup Time / Make-ready (menit)" type="number" name="default_setup_minutes" wire:model="default_setup_minutes" placeholder="mis. 45" />
                        </div>

                        <div class="[display:grid] grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                            <x-form.input label="Kapasitas Standar (per jam)" name="default_speed_per_hour" wire:model="default_speed_per_hour" placeholder="mis. 10000" hint="Dalam satuan hasil di atas" />
                            <x-form.input label="Jumlah Kru Standar / Shift" type="number" name="default_manpower" wire:model="default_manpower" placeholder="mis. 3" />
                        </div>

                        <div class="mt-4 border border-[#d7dbe2] rounded-md p-3">
                            <p class="text-[10px] font-bold text-accent uppercase mb-2">Waste / Spoilage</p>
                            <p class="text-[11px] text-muted mb-2.5">Offset punya dua komponen waste: lembar make-ready yang <em>tetap</em> (habis berapa pun ordernya) plus persentase yang ikut naik sesuai volume. Isi keduanya.</p>
                            <div class="[display:grid] grid-cols-1 md:grid-cols-3 gap-3">
                                <x-form.input label="Waste Berjalan (%)" name="default_waste_pct" wire:model="default_waste_pct" placeholder="mis. 2.5" />
                                <x-form.input label="Insheet Tetap (qty)" type="number" name="default_waste_fixed_qty" wire:model="default_waste_fixed_qty" placeholder="mis. 150" />
                                <x-form.input label="Toleransi Kirim (%)" name="default_tolerance_pct" wire:model="default_tolerance_pct" placeholder="mis. 5" />
                            </div>
                        </div>

                        <div class="[display:grid] grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                            <x-form.input label="Lot Minimum" type="number" name="min_lot_qty" wire:model="min_lot_qty" />
                            <x-form.input label="Lot Maksimum" type="number" name="max_lot_qty" wire:model="max_lot_qty" />
                        </div>

                        <div class="mt-3 border border-[#d7dbe2] rounded-md p-3 [display:grid] grid-cols-1 md:grid-cols-2 gap-2">
                            <x-form.checkbox wire:model="is_bottleneck">
                                <span class="font-semibold">Proses bottleneck</span>
                                <span class="block text-[10.5px] text-muted">Kapasitas penentu — dijadwalkan lebih dulu</span>
                            </x-form.checkbox>
                            <x-form.checkbox wire:model="allow_parallel">
                                <span class="font-semibold">Boleh paralel antar-mesin</span>
                                <span class="block text-[10.5px] text-muted">1 WO boleh dipecah ke beberapa mesin sekaligus</span>
                            </x-form.checkbox>
                        </div>
                    </div>

                    {{-- TAB 3 --}}
                    <div @if ($activeTab !== 'cost') style="display:none" @endif>
                        <div class="[display:grid] grid-cols-1 md:grid-cols-2 gap-3">
                            <x-form.select label="Basis Ongkos" name="cost_basis" wire:model="cost_basis" :options="\App\Models\Master\ProdProcess::COST_BASIS" placeholder="— pilih basis —" />
                            <x-form.input label="Tarif Standar (IDR)" name="standard_rate" wire:model="standard_rate" placeholder="mis. 250000" hint="Dalam satuan basis di sebelah" />
                        </div>
                        <p class="text-[11px] text-muted mt-2">Tarif tanpa basis tidak akan disimpan — "250.000" bisa berarti per jam atau per 1.000 lembar, dan CostingEngine tidak boleh menebak.</p>

                        <div class="mt-4 border border-[#d7dbe2] rounded-md p-3">
                            <p class="text-[10px] font-bold text-accent uppercase mb-2">Kebutuhan Tooling</p>
                            <p class="text-[11px] text-muted mb-2.5">Dipakai ProDev untuk memunculkan biaya tooling & memperingatkan kalau tooling-nya belum ada.</p>
                            <div class="[display:grid] grid-cols-1 md:grid-cols-3 gap-2">
                                <x-form.checkbox label="Butuh Plate (CTP)" wire:model="requires_plate" />
                                <x-form.checkbox label="Butuh Pisau / Plong" wire:model="requires_die" />
                                <x-form.checkbox label="Butuh tooling lain (mould/cliche/film)" wire:model="requires_tooling" />
                            </div>
                        </div>

                        @if ($mode === 'edit' && $this->whereUsed)
                            <div class="mt-4 border border-[#d7dbe2] rounded-md p-3">
                                <p class="text-[10px] font-bold text-accent uppercase mb-2">Dipakai Di Mana (Where-used)</p>
                                <p class="text-[11px] text-muted mb-2.5">Relasi ke modul lain adalah FK <em>logis</em> lintas-database — MySQL tidak menjaganya, jadi periksa daftar ini sebelum menonaktifkan proses.</p>
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

                    {{-- TAB 4: MATRIKS KAPABILITAS --}}
                    <div @if ($activeTab !== 'machines') style="display:none" @endif>
                        @if ($mode !== 'edit')
                            <x-alert.banner type="info" message="Simpan proses ini dulu, lalu mesin pelaksananya bisa dipasang di sini." />
                        @else
                            <p class="text-[11px] text-muted mb-3">Daftar mesin yang <strong>bisa</strong> mengerjakan proses ini. Inilah yang muncul sebagai pilihan mesin di routing/BOP dan Scheduling. Mesin bertanda <strong>Utama</strong> diusulkan lebih dulu, tapi officer tetap bebas memilih yang lain.</p>

                            @if ($is_manual)
                                <x-alert.banner type="warn" message="Proses ini ditandai proses tangan (tanpa mesin). Kalau ternyata ada mesinnya, hapus centang 'Proses tangan' di tab 1." />
                            @endif

                            <div class="flex flex-wrap items-end gap-2 mb-3 p-3 bg-[#fafbfd] border border-[#d7dbe2] rounded-md">
                                <div class="flex-1 min-w-[280px]">
                                    <x-form.select label="Tambah mesin pelaksana" name="capPickId" wire:model="capPickId" :options="$this->machineCandidates" placeholder="— pilih mesin —" />
                                </div>
                                <button type="button" wire:click="capAdd" @disabled(! $capPickId)
                                    class="px-3.5 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">+ Tambah Mesin</button>
                            </div>

                            <div class="border border-[#d7dbe2] rounded-md overflow-x-auto">
                                <table class="w-full border-collapse text-[12px]">
                                    <thead>
                                        <tr class="bg-[#fafbfd]">
                                            @foreach (['Mesin', 'Jenis / Dept', 'Kapasitas Efektif', 'Setup', 'Waste', 'Tarif / Jam', 'Kru', 'Est. 10.000 unit', 'Peran', ''] as $h)
                                                <th class="text-left px-2.5 py-2 text-[10px] uppercase tracking-wide text-[#667] border-b border-[#eef1f5] whitespace-nowrap">{{ $h }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($this->capabilityRows as $cap)
                                            @php($rp = $this->resolvedParams[$cap->capability_id] ?? null)
                                            <tr wire:key="cap-{{ $cap->capability_id }}" class="{{ $cap->activate ? '' : 'opacity-45' }}">
                                                <td class="px-2.5 py-2 border-b border-[#eef1f5] whitespace-nowrap">
                                                    <span class="font-mono font-bold text-[11.5px] px-1.5 py-0.5 rounded bg-[#eef1f5] text-[#2c3e69]">{{ $cap->machine->machine_code }}</span>
                                                    <div class="text-[11px] mt-0.5">{{ $cap->machine->machine_name }}</div>
                                                </td>
                                                <td class="px-2.5 py-2 border-b border-[#eef1f5]">
                                                    <div class="text-[11px]">{{ $cap->machine->machine_type }}</div>
                                                    <div class="text-[10px] text-muted">{{ $cap->machine->department?->department_name ?? '—' }}</div>
                                                    @if (in_array($cap->machine->machine_status, \App\Models\Master\ProdMachine::STATUSES_BLOCKING, true))
                                                        <div class="text-[10px] font-bold text-[#b91c1c] mt-0.5">{{ $cap->machine->statusLabel() }}</div>
                                                    @endif
                                                </td>
                                                @if ($rp)
                                                    @foreach (['speed_per_hour', 'setup_minutes'] as $f)
                                                        <td class="px-2.5 py-2 border-b border-[#eef1f5] whitespace-nowrap">
                                                            @if ($rp['params'][$f]['value'] !== null)
                                                                <span class="font-mono text-[11.5px]">{{ number_format((float) $rp['params'][$f]['value'], 0, ',', '.') }}</span>
                                                                <span class="block text-[9.5px] {{ $rp['params'][$f]['source'] === 'CAPABILITY' ? 'text-[#1d4ed8] font-bold' : 'text-muted' }}">{{ $rp['params'][$f]['source'] === 'CAPABILITY' ? 'khusus mesin' : ($rp['params'][$f]['source'] === 'PROCESS' ? 'default proses' : 'dari mesin') }}</span>
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
                                                            <span class="text-[10px] text-muted" title="Kapasitas per jam belum diisi di lapis mana pun">tak bisa dihitung</span>
                                                        @endif
                                                    </td>
                                                @else
                                                    <td colspan="6" class="px-2.5 py-2 border-b border-[#eef1f5] text-muted">—</td>
                                                @endif
                                                <td class="px-2.5 py-2 border-b border-[#eef1f5] whitespace-nowrap">
                                                    <button type="button" wire:click="capTogglePrimary({{ $cap->capability_id }})"
                                                        class="text-[10px] font-bold px-2 py-0.5 rounded cursor-pointer {{ $cap->preference_rank === 1 ? 'bg-[#fef9c3] text-[#92400e]' : 'bg-[#eef1f5] text-[#8a8f98]' }}"
                                                        title="Klik untuk ubah mesin utama / alternatif">{{ $cap->preference_rank === 1 ? '★ Utama' : 'Alternatif' }}</button>
                                                    <button type="button" wire:click="capToggleCertified({{ $cap->capability_id }})"
                                                        class="block mt-1 text-[9.5px] font-semibold px-1.5 py-0.5 rounded cursor-pointer {{ $cap->is_certified ? 'bg-[#eaf7ee] text-[#166534]' : 'bg-[#fff7ed] text-[#c2410c]' }}"
                                                        title="Sudah terbukti produksi?">{{ $cap->is_certified ? 'Tervalidasi' : 'Belum divalidasi' }}</button>
                                                </td>
                                                <td class="px-2.5 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                                    <x-grid.action-icon icon="fa-sliders" title="Atur parameter khusus mesin ini" action="capEdit({{ $cap->capability_id }})" />
                                                    @if ($cap->activate)
                                                        <x-grid.action-icon icon="fa-ban" title="Lepas mesin dari proses ini" variant="danger"
                                                            action="capRemove({{ $cap->capability_id }})"
                                                            :confirm="'Lepas '.$cap->machine->machine_code.' dari proses ini? Parameter yang sudah disetel tetap disimpan.'" />
                                                    @else
                                                        <x-grid.action-icon icon="fa-rotate" title="Pasang kembali" action="capRestore({{ $cap->capability_id }})" />
                                                    @endif
                                                </td>
                                            </tr>

                                            {{-- Baris editor parameter override --}}
                                            @if ($capEditingId === $cap->capability_id)
                                                <tr wire:key="capedit-{{ $cap->capability_id }}" class="bg-[#f7faff]">
                                                    <td colspan="10" class="px-3 py-3 border-b border-[#eef1f5]">
                                                        <p class="text-[10px] font-bold text-accent uppercase mb-1">Parameter khusus {{ $cap->machine->machine_code }}</p>
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
                                                Belum ada mesin dipasangkan. Tanpa ini proses tidak akan muncul sebagai pilihan di routing & tidak bisa dijadwalkan.
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
                        <x-back-button fallback="production.master-process" class="ml-auto" />
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>
