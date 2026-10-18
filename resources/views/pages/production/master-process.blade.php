<?php

/*
 * REMARK
 * Tujuan       : Komponen v1 untuk layar "Process Master" (dibuka dari grid v2 lewat Open / New). Teks tampilan (label, kolom, tab, tombol, pesan) English sesuai keputusan Jo 2026-10-18; nilai tersimpan dan kunci data tidak diubah.
 * Sumber hulu  : Tabel dan service yang dibaca komponen ini (lihat query di file); tidak ada logika hulu baru.
 * Input        : Isian form dan filter layar.
 * Output       : Simpan ke tabel modul terkait lewat service yang sama seperti sebelumnya.
 * Dipakai oleh : Grid v2 modul terkait (v1Route).
 * Status       : Aktif; teks tampilan English (Jo 2026-10-18).
 */


use App\Livewire\Concerns\HasGridStatFilter;
use App\Livewire\Concerns\ManagesProcessMachineCapability;
use App\Models\Master\OrgDepartment;
use App\Models\Master\ProdMachine;
use App\Models\Master\ProdProcess;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use App\Livewire\Concerns\OpensFromQuery;
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
new #[Title('Process Master — TELASIH MAKMUR ERP')] class extends Component
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
            $this->addError('max_lot_qty', 'Maximum lot must be >= minimum lot.');

            return;
        }

        // Tarif tanpa basis = angka tak bermakna (per jam? per 1000?), dan
        // CostingEngine akan salah 3 digit. Blokir di sini, bukan di engine.
        if ($data['standard_rate'] !== '' && $data['cost_basis'] === '') {
            $this->activeTab = 'cost';
            $this->addError('cost_basis', 'Select a cost basis first — a rate without a basis cannot be used to calculate COGS (HPP).');

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
            'total' => ['label' => 'Total Processes', 'value' => $this->statTotal, 'tone' => 'total'],
            'active' => ['label' => 'Active', 'value' => $this->statActive, 'tone' => 'active',
                'filter' => fn ($q) => $q->where('activate', true)],
            'bottleneck' => ['label' => 'Bottleneck', 'value' => $this->statBottleneck, 'tone' => 'warn',
                'subtitle' => 'Limiting capacity',
                'filter' => fn ($q) => $q->where('activate', true)->where('is_bottleneck', true)],
            'subcon' => ['label' => 'Subcontract', 'value' => $this->statSubcon, 'tone' => 'extra',
                'subtitle' => 'Done by a third party',
                'filter' => fn ($q) => $q->where('activate', true)->where('inhouse', false)],
            'nomachine' => ['label' => 'No Machine', 'value' => $this->statNoMachine, 'tone' => 'critical',
                'subtitle' => 'Cannot be scheduled yet',
                'filter' => fn ($q) => $q->where('activate', true)->where('is_manual', false)
                    ->whereDoesntHave('capabilities', fn ($qq) => $qq->where('activate', true))],
            'inactive' => ['label' => 'Inactive', 'value' => $this->statInactive, 'tone' => 'critical',
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

        <x-ui.card variant="panel" size="none">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <x-form.input wire:model.live.debounce.300ms="search" placeholder="Search code or process name…" :case-toggle="false" class="w-56 shrink-0" />
                </div>
                <x-slot:tools>
                    <x-form.select wire:model.live="stageFilter" liveSync :selected="$stageFilter" placeholder="All Stages"
                        :options="\App\Models\Master\ProdProcess::STAGES" class="shrink-0" />
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
                                'Code' => 'w-[72px]', 'Process & Department' => '', 'Stage' => 'w-[110px]',
                                'Execution' => 'w-[96px]', 'Capacity / Hour' => 'w-[132px]',
                                'Setup' => 'w-[70px] num', 'Waste Std' => 'w-[92px] num',
                                'Mesin' => 'w-[92px]', 'Std Cost' => 'w-[124px] num',
                                'Readiness' => 'w-[104px]', 'Status' => 'w-[80px]',
                            ] as $label => $w)
                                <th class="{{ $w }}">{{ $label }}</th>
                            @endforeach
                            <th class="text-right w-[100px]">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->processes as $p)
                            @php($ready = $p->readinessScore())
                            <tr wire:key="process-{{ $p->id_process }}" wire:dblclick="openModal('edit', {{ $p->id_process }})" class="cursor-pointer" title="Double-click to open details">
                                <td>
                                    @if ($p->process_code)
                                        <x-ui.badge tone="code" size="md" mono>{{ $p->process_code }}</x-ui.badge>
                                    @else
                                        <span class="text-[10px] text-warn-ink" title="Process code not filled in">belum</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <span class="w-2.5 h-2.5 rounded-full shrink-0 border border-line" style="background:{{ $p->gantt_color ?: '#cbd5e1' }}"></span>
                                        <div class="min-w-0">
                                            <div class="font-semibold text-[12.5px] truncate">{{ $p->process_name }}</div>
                                            <div class="text-[10.5px] text-muted truncate">
                                                {{ $p->department?->department_name ?? 'Department not filled in' }}
                                                @if ($p->is_bottleneck) <span class="text-warn-ink font-bold">· BOTTLENECK</span> @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if ($p->process_stage)
                                        <x-ui.badge tone="info">{{ $p->stageLabel() }}</x-ui.badge>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($p->is_manual)
                                        <x-ui.badge tone="extra">Manual</x-ui.badge>
                                    @elseif ($p->inhouse)
                                        <x-ui.badge tone="info">In-house</x-ui.badge>
                                    @else
                                        <x-ui.badge tone="warn">Subkon</x-ui.badge>
                                    @endif
                                </td>
                                <td>
                                    @if ($p->default_speed_per_hour > 0)
                                        <div class="text-[11.5px] leading-tight">
                                            <span class="font-mono font-semibold">{{ number_format((float) $p->default_speed_per_hour, 0, ',', '.') }}</span>
                                            <span class="text-[10px] text-muted">{{ $p->output_uom === 'LEMBAR' ? 'Sheet' : ucfirst(strtolower($p->output_uom)) }} / Hour</span>
                                        </div>
                                        {{-- Bar pembanding kapasitas antar-baris (permintaan user 2026-09-22) --}}
                                        <x-ui.progress class="mt-1" tone="info" :value="max(3, round((float) $p->default_speed_per_hour / $this->speedScaleMax * 100))" />
                                    @elseif ($p->is_manual)
                                        <span class="text-[10.5px] text-muted">Manual</span>
                                    @else
                                        <span class="text-[10.5px] text-warn-ink">not filled in</span>
                                    @endif
                                </td>
                                <td class="num">
                                    @if ($p->default_setup_minutes !== null)
                                        <span class="font-mono text-[12px]">{{ $p->default_setup_minutes }}</span><span class="text-[10px] text-muted"> mnt</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="num text-[11.5px]">
                                    @if ($p->default_waste_pct !== null || $p->default_waste_fixed_qty !== null)
                                        <span class="font-mono font-semibold {{ (float) $p->default_waste_pct >= 2.5 ? 'text-danger-ink' : 'text-success-ink' }}">{{ $this->num($p->default_waste_pct) ?: '0' }}%</span>
                                        @if ($p->default_waste_fixed_qty)
                                            <div class="text-[10px] text-muted">+{{ number_format($p->default_waste_fixed_qty, 0, ',', '.') }} tetap</div>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                {{-- Kolom inti permintaan user: berapa mesin bisa mengerjakan proses ini --}}
                                <td>
                                    @if ($p->capabilities_count > 0)
                                        <x-ui.badge-button tone="success" size="md" icon="fa-gears" wire:click="openModal('edit', {{ $p->id_process }})"
                                            title="See executing machines">{{ $p->capabilities_count }} unit</x-ui.badge-button>
                                    @elseif ($p->is_manual)
                                        <span class="text-[10.5px] text-muted">no machine</span>
                                    @else
                                        <x-ui.badge tone="danger" title="This process cannot be scheduled yet">none yet</x-ui.badge>
                                    @endif
                                </td>
                                <td class="num">
                                    @if ($p->standard_rate > 0)
                                        <div class="font-mono text-[11.5px]">{{ number_format((float) $p->standard_rate, 0, ',', '.') }}</div>
                                        <div class="text-[10px] text-muted">{{ \App\Models\Master\ProdProcess::COST_BASIS[$p->cost_basis] ?? '—' }}</div>
                                    @else
                                        <span class="text-[10.5px] text-warn-ink">not filled in</span>
                                    @endif
                                </td>
                                <td>
                                    <x-ui.badge :tone="\App\View\Components\Ui\Tone::forScore($ready)" size="md" mono
                                        title="Data completeness required for engine use">{{ $ready }}%</x-ui.badge>
                                </td>
                                <td>
                                    <x-ui.badge :tone="$p->activate ? 'success' : 'muted'" pill>{{ $p->activate ? 'ACTIVE' : 'INACTIVE' }}</x-ui.badge>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-pen" title="Edit" action="openModal('edit', {{ $p->id_process }})" />
                                    <x-grid.action-icon :active="$p->activate" :title="$p->activate ? 'Deactivate' : 'Activate'" action="toggleActive({{ $p->id_process }})"
                                        :confirm="($p->activate ? 'Deactivate' : 'Activate').' process '.$p->process_name.'?'"
                                        :variant="$p->activate ? 'danger' : 'default'" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="12" message="None." />
                        @endforelse
                    </tbody>
                </x-ui.table>
            </x-grid.table-shell>
        </x-ui.card>
        @endif

        {{-- ============ FORM (NEW/EDIT) ============ --}}
        @if ($panel === 'form')
            <x-ui.panel-header :title="$mode === 'edit' ? 'Edit Process'.($process_code ? ' : '.$process_code : '') : 'New Process'">
                @if ($mode === 'edit')
                    <x-slot:aside>{{ $this->capabilityRows->where('activate', true)->count() }} executing machines</x-slot:aside>
                @endif
            </x-ui.panel-header>

            <div class="bg-surface border border-t-0 border-line rounded-b-md overflow-hidden">
                <x-ui.tabs model="activeTab" :active="$activeTab" :tabs="[
                    'main' => '1. Main Info',
                    'capacity' => '2. Parameters & Capacity',
                    'cost' => '3. Cost & Tooling',
                    'machines' => '4. Executing Machines',
                ]" :badges="$mode === 'edit' ? ['machines' => [
                    'text' => $this->capabilityRows->where('activate', true)->count(),
                    'tone' => $this->capabilityRows->where('activate', true)->count() > 0 ? 'success' : 'danger',
                ]] : []" />

                <form wire:submit="save" class="p-5">
                    {{-- TAB 1 --}}
                    <div @if ($activeTab !== 'main') style="display:none" @endif>
                        <div class="[display:grid] grid-cols-1 md:grid-cols-2 gap-3">
                            <x-form.input label="Process Code" name="process_code" wire:model="process_code" placeholder="e.g. CE, PLG, LAM" hint="Letters/digits, max 10 characters. Used in the SPK (work order) & routing." />
                            <x-form.select label="Active Status" wire:model="process_activate" :placeholder="false" :options="['1' => 'Active', '0' => 'Inactive']" />
                        </div>

                        <div class="mt-3">
                            <x-form.input label="Process Name *" name="process_name" wire:model="process_name" placeholder="e.g. Offset Print 4 Colours" />
                        </div>

                        <div class="[display:grid] grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                            <x-form.select label="Production Stage" name="process_stage" wire:model="process_stage" :options="\App\Models\Master\ProdProcess::STAGES" placeholder="— select stage —" />
                            <x-form.select label="Related Department" name="department_id" wire:model="department_id" :options="$this->departmentOptions" placeholder="— select department —" />
                        </div>

                        <div class="[display:grid] grid-cols-1 md:grid-cols-3 gap-3 mt-3">
                            <x-form.input label="Routing Sequence" type="number" name="routing_seq" wire:model="routing_seq" placeholder="10, 20, 30…" hint="Suggested order in the BOP" />
                            <x-form.input label="Gantt Colour" type="color" wire:model="gantt_color" hint="Tells processes apart in the MPS" />
                            <x-form.input label="Crew Certification" name="skill_code" wire:model="skill_code" placeholder="e.g. OP-OFFSET" />
                        </div>

                        <x-ui.card class="mt-3  [display:grid] grid-cols-1 md:grid-cols-3 gap-2">
                            <x-form.checkbox label="Done in-house" wire:model="inhouse" />
                            <x-form.checkbox label="Manual process — no machine" wire:model="is_manual" />
                            <x-form.checkbox label="Must pass the QC gate" wire:model="qc_required" />
                        </x-ui.card>
                        @if (! $inhouse)
                            <div class="mt-3 max-w-xs">
                                <x-form.input label="Subcontract Lead Time (days)" type="number" name="subcon_lead_days" wire:model="subcon_lead_days" />
                            </div>
                        @endif

                        <div class="mt-3">
                            <x-form.textarea label="Description" name="description" wire:model="description" autogrow placeholder="Notes on how to work, material requirements, common mistakes…" />
                        </div>
                    </div>

                    {{-- TAB 2 --}}
                    <div @if ($activeTab !== 'capacity') style="display:none" @endif>
                        <p class="text-[11px] text-muted mb-3">The numbers here are the <strong>default values</strong> of the process. If one machine has different numbers, enter the override in the tab <strong>Executing Machines</strong> — do not change these default numbers.</p>

                        <div class="[display:grid] grid-cols-1 md:grid-cols-2 gap-3">
                            <x-form.select label="Output Unit (UoM)" name="output_uom" wire:model="output_uom" :placeholder="false" :options="array_map('t', \App\Models\Master\ProdProcess::OUTPUT_UOM)" />
                            <x-form.input label="Setup Time / Make-ready (minutes)" type="number" name="default_setup_minutes" wire:model="default_setup_minutes" placeholder="e.g. 45" />
                        </div>

                        <div class="[display:grid] grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                            <x-form.input label="Standard Capacity (per hour)" name="default_speed_per_hour" wire:model="default_speed_per_hour" placeholder="e.g. 10000" hint="In the output unit above" />
                            <x-form.input label="Standard Crew Count / Shift" type="number" name="default_manpower" wire:model="default_manpower" placeholder="e.g. 3" />
                        </div>

                        <x-ui.card class="mt-4" title="Waste / Spoilage">
                            <x-slot:intro>Offset has two waste components: the make-ready sheets that are <em>tetap</em> (used up whatever the order size) plus a percentage that grows with volume. Fill in both.</x-slot:intro>
                            <div class="[display:grid] grid-cols-1 md:grid-cols-3 gap-3">
                                <x-form.input label="Running Waste (%)" name="default_waste_pct" wire:model="default_waste_pct" placeholder="e.g. 2.5" />
                                <x-form.input label="Fixed Insheet (qty)" type="number" name="default_waste_fixed_qty" wire:model="default_waste_fixed_qty" placeholder="e.g. 150" />
                                <x-form.input label="Delivery Tolerance (%)" name="default_tolerance_pct" wire:model="default_tolerance_pct" placeholder="e.g. 5" />
                            </div>
                        </x-ui.card>

                        <div class="[display:grid] grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                            <x-form.input label="Minimum Lot" type="number" name="min_lot_qty" wire:model="min_lot_qty" />
                            <x-form.input label="Maximum Lot" type="number" name="max_lot_qty" wire:model="max_lot_qty" />
                        </div>

                        <x-ui.card class="mt-3  [display:grid] grid-cols-1 md:grid-cols-2 gap-2">
                            <x-form.checkbox wire:model="is_bottleneck">
                                <span class="font-semibold">Bottleneck process</span>
                                <span class="block text-[10.5px] text-muted">Limiting capacity — scheduled first</span>
                            </x-form.checkbox>
                            <x-form.checkbox wire:model="allow_parallel">
                                <span class="font-semibold">Parallel allowed across machines</span>
                                <span class="block text-[10.5px] text-muted">1 WO may be split across several machines at once</span>
                            </x-form.checkbox>
                        </x-ui.card>
                    </div>

                    {{-- TAB 3 --}}
                    <div @if ($activeTab !== 'cost') style="display:none" @endif>
                        <div class="[display:grid] grid-cols-1 md:grid-cols-2 gap-3">
                            <x-form.select label="Cost Basis" name="cost_basis" wire:model="cost_basis" :options="\App\Models\Master\ProdProcess::COST_BASIS" placeholder="— select basis —" />
                            <x-form.input label="Standard Rate (IDR)" name="standard_rate" wire:model="standard_rate" placeholder="e.g. 250000" hint="In the basis unit next to it" />
                        </div>
                        <p class="text-[11px] text-muted mt-2">A rate without a basis will not be saved — "250,000" could mean per hour or per 1,000 sheets, and the CostingEngine must not guess.</p>

                        <x-ui.card class="mt-4" title="Tooling Requirements">
                            <x-slot:intro>Used by ProDev to show tooling cost & warn when the tooling does not exist yet.</x-slot:intro>
                            <div class="[display:grid] grid-cols-1 md:grid-cols-3 gap-2">
                                <x-form.checkbox label="Needs Plate (CTP)" wire:model="requires_plate" />
                                <x-form.checkbox label="Needs Knife / Die-cut" wire:model="requires_die" />
                                <x-form.checkbox label="Needs other tooling (mould/cliche/film)" wire:model="requires_tooling" />
                            </div>
                        </x-ui.card>

                        @if ($mode === 'edit' && $this->whereUsed)
                            <x-ui.card class="mt-4" title="Used Where (Where-used)">
                                <x-slot:intro>Relations to other modules are a <em>logis</em> cross-database — MySQL does not enforce it, so check this list before deactivating the process.</x-slot:intro>
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

                    {{-- TAB 4: MATRIKS KAPABILITAS --}}
                    <div @if ($activeTab !== 'machines') style="display:none" @endif>
                        @if ($mode !== 'edit')
                            <x-alert.banner type="info" message="Save this process first, then its executing machines can be attached here." />
                        @else
                            <p class="text-[11px] text-muted mb-3">The list of machines that <strong>can</strong> perform this process. These appear as machine choices in routing/BOP and Scheduling. A machine marked <strong>Primary</strong> is suggested first, but the officer is still free to choose another.</p>

                            @if ($is_manual)
                                <x-alert.banner type="warn" message="This process is marked as a manual process (no machine). If it does have a machine, untick 'Manual process' in tab 1." />
                            @endif

                            <x-ui.card variant="muted" class="flex flex-wrap items-end gap-2 mb-3">
                                <div class="flex-1 min-w-[280px]">
                                    <x-form.select label="Add executing machine" name="capPickId" wire:model="capPickId" :options="$this->machineCandidates" placeholder="— select machine —" />
                                </div>
                                <x-ui.button wire:click="capAdd" :disabled="! $capPickId">+ Add Machine</x-ui.button>
                            </x-ui.card>

                            <x-ui.table size="sm" framed>
                                    <thead>
                                        <tr>
                                            @foreach (['Mesin', 'Type / Dept', 'Effective Capacity', 'Setup', 'Waste', 'Rate / Hour', 'Kru', 'Est. 10.000 unit', 'Role', ''] as $h)
                                                <th>{{ $h }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($this->capabilityRows as $cap)
                                            @php($rp = $this->resolvedParams[$cap->capability_id] ?? null)
                                            <tr wire:key="cap-{{ $cap->capability_id }}" class="{{ $cap->activate ? '' : 'opacity-45' }}">
                                                <td class="whitespace-nowrap">
                                                    <x-ui.badge tone="code" size="md" mono>{{ $cap->machine->machine_code }}</x-ui.badge>
                                                    <div class="text-[11px] mt-0.5">{{ $cap->machine->machine_name }}</div>
                                                </td>
                                                <td>
                                                    <div class="text-[11px]">{{ $cap->machine->machine_type }}</div>
                                                    <div class="text-[10px] text-muted">{{ $cap->machine->department?->department_name ?? '—' }}</div>
                                                    @if (in_array($cap->machine->machine_status, \App\Models\Master\ProdMachine::STATUSES_BLOCKING, true))
                                                        <div class="text-[10px] font-bold text-danger-ink mt-0.5">{{ $cap->machine->statusLabel() }}</div>
                                                    @endif
                                                </td>
                                                @if ($rp)
                                                    @foreach (['speed_per_hour', 'setup_minutes'] as $f)
                                                        <td class="whitespace-nowrap">
                                                            @if ($rp['params'][$f]['value'] !== null)
                                                                <span class="font-mono text-[11.5px]">{{ number_format((float) $rp['params'][$f]['value'], 0, ',', '.') }}</span>
                                                                <span class="block text-[9.5px] {{ $rp['params'][$f]['source'] === 'CAPABILITY' ? 'text-info-ink font-bold' : 'text-muted' }}">{{ $rp['params'][$f]['source'] === 'CAPABILITY' ? 'machine-specific' : ($rp['params'][$f]['source'] === 'PROCESS' ? 'default proses' : 'from machine') }}</span>
                                                            @else
                                                                <span class="text-[10px] text-warn-ink">none yet</span>
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
                                                            <span class="text-[10px] text-warn-ink">none yet</span>
                                                        @endif
                                                    </td>
                                                    <td class="font-mono text-[11.5px]">{{ $rp['params']['manpower']['value'] ?? '—' }}</td>
                                                    <td class="whitespace-nowrap">
                                                        @if ($rp['hours_10k'] !== null)
                                                            <span class="font-mono font-semibold text-[11.5px]">{{ number_format($rp['hours_10k'], 2, ',', '.') }}</span><span class="text-[10px] text-muted"> hours</span>
                                                        @else
                                                            <span class="text-[10px] text-muted" title="Capacity per hour is not filled in at any level">cannot be calculated</span>
                                                        @endif
                                                    </td>
                                                @else
                                                    <td colspan="6" class="text-muted">—</td>
                                                @endif
                                                <td class="whitespace-nowrap">
                                                    <x-ui.badge-button :tone="$cap->preference_rank === 1 ? 'highlight' : 'muted'" wire:click="capTogglePrimary({{ $cap->capability_id }})"
                                                        title="Click to switch primary / alternative machine">{{ $cap->preference_rank === 1 ? '★ Primary' : 'Alternative' }}</x-ui.badge-button>
                                                    <x-ui.badge-button size="xs" class="flex mt-1" :tone="$cap->is_certified ? 'success' : 'warn'" wire:click="capToggleCertified({{ $cap->capability_id }})"
                                                        title="Already proven in production?">{{ $cap->is_certified ? 'Validated' : 'Not validated yet' }}</x-ui.badge-button>
                                                </td>
                                                <td class="text-right whitespace-nowrap">
                                                    <x-grid.action-icon icon="fa-sliders" title="Set parameters specific to this machine" action="capEdit({{ $cap->capability_id }})" />
                                                    @if ($cap->activate)
                                                        <x-grid.action-icon icon="fa-ban" title="Detach machine from this process" variant="danger"
                                                            action="capRemove({{ $cap->capability_id }})"
                                                            :confirm="'Detach '.$cap->machine->machine_code.' from this process? Parameters already set are kept.'" />
                                                    @else
                                                        <x-grid.action-icon icon="fa-rotate" title="Re-attach" action="capRestore({{ $cap->capability_id }})" />
                                                    @endif
                                                </td>
                                            </tr>

                                            {{-- Baris editor parameter override --}}
                                            @if ($capEditingId === $cap->capability_id)
                                                <tr wire:key="capedit-{{ $cap->capability_id }}" class="bg-surface-hover">
                                                    <td colspan="10" class="px-3 py-3">
                                                        <p class="text-[10px] font-bold text-accent uppercase mb-1">Specific parameters {{ $cap->machine->machine_code }}</p>
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
                                                No machine attached yet. Without this the process will not appear as a choice in routing & cannot be scheduled.
                                            </td></tr>
                                        @endforelse
                                    </tbody>
                            </x-ui.table>
                        @endif
                    </div>

                    <x-ui.action-bar>
                        <x-ui.button preset="save" />
                        <x-ui.button preset="cancel" wire:click="showGrid" />
                        <x-back-button fallback="production.master-process" class="ml-auto" />
                    </x-ui.action-bar>
                </form>
            </div>
        @endif
    </div>
</div>
