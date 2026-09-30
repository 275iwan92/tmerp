<?php

use App\Livewire\Concerns\HasGridStatFilter;
use App\Models\Master\ProdProcess;
use App\Models\Master\ProdRoutingStep;
use App\Models\Master\ProdRoutingTemplate;
use App\Services\Production\MachineCapabilityService;
use App\Services\Production\RoutingTemplateService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use App\Livewire\Concerns\OpensFromQuery;
use Livewire\Component;

// Master Routing Template -> tm2mst.prod_routing_template + _step.
// Layar BARU 2026-09-22: urutan operasi standar per tipe/kategori produk.
//
// Kenapa perlu: sebelum ini tiap prod_part_process disusun manual dari nol per
// part, padahal "Box Duplex Full Color" selalu lewat urutan yang sama
// (POTONG -> CETAK -> LAMINASI -> PLONG -> KUPAS -> LEM -> QC PACK). Template
// jadi titik awal BOP ProDev; officer tetap boleh mengubah hasilnya.
//
// Nilai utama layar ini BUKAN penyimpanan urutannya, tapi VALIDATOR-nya:
// tiap perubahan langsung diperiksa terhadap prod_process_precedence dan
// terhadap matriks kapabilitas (proses tanpa mesin = tidak bisa dijadwalkan).
// BLOCK menghentikan, WARN hanya memperingatkan.
new #[Title('Master Routing Template — TELASIH MAKMUR ERP')] class extends Component
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

    public string $search = '';

    public string $panel = 'grid'; // grid | form

    public string $mode = 'new';

    public ?int $editingId = null;

    public string $template_code = '';

    public string $template_name = '';

    public string $prod_type_code = '';

    public string $substrate_hint = '';

    public string $description = '';

    public bool $is_default = false;

    public bool $template_activate = true;

    // Form tambah langkah.
    public ?int $stepProcessId = null;

    public ?int $stepMachineId = null;

    public string $stepBranch = 'MAIN';

    public bool $stepOptional = false;

    // Langkah yang sedang diedit inline.
    public ?int $stepEditingId = null;

    public string $stepEditSeq = '';

    public string $stepEditNote = '';

    public string $stepEditQtyFactor = '';

    #[Computed]
    public function templates()
    {
        return $this->applyStatFilter(
            ProdRoutingTemplate::query()
                ->withCount('steps')
                ->when($this->search !== '', fn ($q) => $q->where(fn ($qq) => $qq
                    ->where('template_code', 'like', "%{$this->search}%")
                    ->orWhere('template_name', 'like', "%{$this->search}%")))
        )
            ->orderByDesc('is_default')->orderBy('template_name')
            ->get();
    }

    #[Computed]
    public function steps()
    {
        return $this->editingId
            ? ProdRoutingStep::with(['process.department', 'preferredMachine'])
                ->where('template_id', $this->editingId)
                ->orderBy('branch_code')->orderBy('step_seq')->get()
            : collect();
    }

    #[Computed]
    public function processOptions()
    {
        return ProdProcess::query()->where('activate', true)
            ->orderByRaw('FIELD(process_stage, "PREPRESS","PRESS","POSTPRESS","FINISHING","PACKING","QC","LOGISTIC")')
            ->orderBy('process_name')->get()
            ->mapWithKeys(fn ($p) => [$p->id_process =>
                ($p->process_code ? $p->process_code.' — ' : '').$p->process_name
                .' ['.($p->process_stage ? $p->stageLabel() : 'tanpa tahap').']'])
            ->all();
    }

    // Mesin yang BISA mengerjakan proses yang sedang dipilih. Ini pemakai
    // pertama matriks kapabilitas di luar 2 layar master -- picker mesin di
    // sini tidak pernah menampilkan mesin yang tidak mampu.
    #[Computed]
    public function machineOptionsForStep()
    {
        if (! $this->stepProcessId) {
            return [];
        }

        return app(MachineCapabilityService::class)
            ->machinesForProcess((int) $this->stepProcessId)
            ->filter(fn ($o) => $o['eligible'])
            ->mapWithKeys(fn ($o) => [$o['machine']->machine_id =>
                $o['machine']->machine_code.' — '.$o['machine']->machine_name
                .($o['is_primary'] ? '  ★ utama' : '')])
            ->all();
    }

    #[Computed]
    public function validation()
    {
        return $this->editingId
            ? app(RoutingTemplateService::class)->validate($this->steps)
            : ['ok' => true, 'blocks' => [], 'warnings' => [], 'notes' => []];
    }

    // Pratinjau hasil penerapan template: mesin yang akan diusulkan ke BOP.
    #[Computed]
    public function expansion()
    {
        return $this->editingId
            ? app(RoutingTemplateService::class)->expand($this->editingId)
            : [];
    }

    public function openModal(string $mode, ?int $id = null): void
    {
        $this->resetErrorBag();
        $this->mode = in_array($mode, ['new', 'edit'], true) ? $mode : 'new';
        $this->editingId = $id;
        $this->stepEditingId = null;
        $this->reset(['stepProcessId', 'stepMachineId', 'stepOptional']);
        $this->stepBranch = 'MAIN';

        if ($this->mode === 'edit' && $id) {
            $t = ProdRoutingTemplate::findOrFail($id);
            $this->template_code = $t->template_code;
            $this->template_name = $t->template_name;
            $this->prod_type_code = (string) $t->prod_type_code;
            $this->substrate_hint = (string) $t->substrate_hint;
            $this->description = (string) $t->description;
            $this->is_default = (bool) $t->is_default;
            $this->template_activate = (bool) $t->activate;
        } else {
            $this->reset(['template_code', 'template_name', 'prod_type_code', 'substrate_hint', 'description']);
            $this->is_default = false;
            $this->template_activate = true;
        }

        $this->panel = 'form';
    }

    public function showGrid(): void
    {
        $this->panel = 'grid';
        $this->stepEditingId = null;
    }

    public function save(): void
    {
        $unique = 'unique:tm2mst.prod_routing_template,template_code';
        if ($this->mode === 'edit' && $this->editingId) {
            $unique .= ','.$this->editingId.',template_id';
        }

        $data = $this->validate([
            'template_code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/', $unique],
            'template_name' => ['required', 'string', 'max:150'],
            'prod_type_code' => ['nullable', 'string', 'max:20'],
            'substrate_hint' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $payload = [
            'template_code' => strtoupper($data['template_code']),
            'template_name' => $data['template_name'],
            'prod_type_code' => $data['prod_type_code'] ?: null,
            'substrate_hint' => $data['substrate_hint'] ?: null,
            'description' => $data['description'] ?: null,
            'is_default' => $this->is_default,
            'activate' => $this->template_activate,
            'updated_by' => Auth::user()->login,
        ];

        if ($this->mode === 'edit' && $this->editingId) {
            ProdRoutingTemplate::whereKey($this->editingId)->update($payload);
            unset($this->templates);
            $this->panel = 'grid';
        } else {
            $payload['created_by'] = Auth::user()->login;
            $new = ProdRoutingTemplate::create($payload);
            // Tetap di form: template tanpa langkah belum ada gunanya.
            $this->editingId = $new->template_id;
            $this->mode = 'edit';
            $this->refreshViews();
        }
    }

    public function addStep(): void
    {
        if (! $this->editingId || ! $this->stepProcessId) {
            return;
        }

        $this->validate([
            'stepBranch' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_]+$/'],
        ], [], ['stepBranch' => 'Kode cabang']);

        // Nomor urut berkelipatan 10 supaya langkah baru bisa DISISIPKAN di
        // tengah (15, 25) tanpa menomori ulang seluruh routing.
        $lastSeq = (int) DB::connection('tm2mst')->table('prod_routing_step')
            ->where('template_id', $this->editingId)
            ->where('branch_code', $this->stepBranch)
            ->max('step_seq');

        ProdRoutingStep::create([
            'template_id' => $this->editingId,
            'step_seq' => $lastSeq + 10,
            'process_id' => $this->stepProcessId,
            'preferred_machine_id' => $this->stepMachineId ?: null,
            'is_optional' => $this->stepOptional,
            'branch_code' => $this->stepBranch,
            'created_by' => Auth::user()->login,
            'updated_by' => Auth::user()->login,
        ]);

        $this->reset(['stepProcessId', 'stepMachineId', 'stepOptional']);
        $this->refreshViews();
    }

    // Proses berganti -> mesin pilihan harus direset: mesin lama belum tentu
    // mampu mengerjakan proses yang baru dipilih.
    public function updatedStepProcessId(): void
    {
        $this->stepMachineId = null;
        unset($this->machineOptionsForStep);
    }

    public function removeStep(int $stepId): void
    {
        // Langkah template belum jadi transaksi apa pun -> aman dihapus
        // (beda dari matriks kapabilitas yang menyimpan parameter).
        ProdRoutingStep::whereKey($stepId)->where('template_id', $this->editingId)->delete();

        if ($this->stepEditingId === $stepId) {
            $this->stepEditingId = null;
        }

        $this->refreshViews();
    }

    public function moveStep(int $stepId, string $direction): void
    {
        $step = ProdRoutingStep::findOrFail($stepId);
        $siblings = ProdRoutingStep::where('template_id', $step->template_id)
            ->where('branch_code', $step->branch_code)
            ->orderBy('step_seq')->get();

        $index = $siblings->search(fn ($s) => $s->step_id === $stepId);
        $swapWith = $direction === 'up' ? $siblings->get($index - 1) : $siblings->get($index + 1);

        if ($index === false || ! $swapWith) {
            return;
        }

        // Tukar nomor urut lewat nilai sementara: UNIQUE(template, branch, seq)
        // akan menolak kalau dua baris sempat bernomor sama.
        $mySeq = $step->step_seq;
        $theirSeq = $swapWith->step_seq;

        $step->update(['step_seq' => 0, 'updated_by' => Auth::user()->login]);
        $swapWith->update(['step_seq' => $mySeq, 'updated_by' => Auth::user()->login]);
        $step->update(['step_seq' => $theirSeq, 'updated_by' => Auth::user()->login]);

        $this->refreshViews();
    }

    public function editStep(int $stepId): void
    {
        $s = ProdRoutingStep::findOrFail($stepId);
        $this->stepEditingId = $stepId;
        $this->stepEditSeq = (string) $s->step_seq;
        $this->stepEditNote = (string) $s->note;
        $this->stepEditQtyFactor = $s->qty_factor === null
            ? ''
            : (rtrim(rtrim((string) $s->qty_factor, '0'), '.') ?: '0');
    }

    public function saveStep(): void
    {
        if (! $this->stepEditingId) {
            return;
        }

        $data = $this->validate([
            'stepEditSeq' => ['required', 'integer', 'min:1', 'max:65535'],
            'stepEditNote' => ['nullable', 'string', 'max:255'],
            'stepEditQtyFactor' => ['nullable', 'numeric', 'min:0.001', 'max:99'],
        ]);

        $step = ProdRoutingStep::findOrFail($this->stepEditingId);

        $clash = ProdRoutingStep::where('template_id', $step->template_id)
            ->where('branch_code', $step->branch_code)
            ->where('step_seq', (int) $data['stepEditSeq'])
            ->where('step_id', '!=', $step->step_id)
            ->exists();

        if ($clash) {
            $this->addError('stepEditSeq', 'Nomor urut itu sudah dipakai langkah lain di cabang yang sama.');

            return;
        }

        $step->update([
            'step_seq' => (int) $data['stepEditSeq'],
            'note' => $data['stepEditNote'] ?: null,
            'qty_factor' => $data['stepEditQtyFactor'] === '' ? null : $data['stepEditQtyFactor'],
            'updated_by' => Auth::user()->login,
        ]);

        $this->stepEditingId = null;
        $this->refreshViews();
    }

    public function cancelStepEdit(): void
    {
        $this->stepEditingId = null;
    }

    public function toggleActive(int $id): void
    {
        $t = ProdRoutingTemplate::findOrFail($id);
        $t->activate = ! $t->activate;
        $t->updated_by = Auth::user()->login;
        $t->save();

        unset($this->templates, $this->statTotal, $this->statActive, $this->statInvalid);
    }

    private function refreshViews(): void
    {
        unset($this->steps, $this->validation, $this->expansion,
            $this->templates, $this->machineOptionsForStep,
            $this->statTotal, $this->statActive, $this->statInvalid);
    }

    #[Computed]
    public function statTotal(): int
    {
        return ProdRoutingTemplate::count();
    }

    #[Computed]
    public function statActive(): int
    {
        return ProdRoutingTemplate::where('activate', true)->count();
    }

    #[Computed]
    public function statEmpty(): int
    {
        return ProdRoutingTemplate::where('activate', true)->doesntHave('steps')->count();
    }

    protected function statFilterDefs(): array
    {
        return [
            'total' => ['label' => 'Total Template', 'value' => $this->statTotal, 'tone' => 'total'],
            'active' => ['label' => 'Aktif', 'value' => $this->statActive, 'tone' => 'active',
                'filter' => fn ($q) => $q->where('activate', true)],
            'empty' => ['label' => 'Tanpa Langkah', 'value' => $this->statEmpty, 'tone' => 'critical',
                'subtitle' => 'Belum bisa dipakai',
                'filter' => fn ($q) => $q->where('activate', true)->doesntHave('steps')],
        ];
    }
};
?>

<div>
    <div class="max-w-[1360px] mx-auto my-5 px-4">

        {{-- ============ GRID ============ --}}
        @if ($panel === 'grid')
        <x-grid.stat-filter-row :boxes="$this->statBoxes()" :active="$statFilter" />

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari kode atau nama template…"
                        class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
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
                                'Kode' => 'w-[96px]', 'Nama Template' => '', 'Tipe Produk' => 'w-[120px]',
                                'Acuan Bahan' => 'w-[150px]', 'Langkah' => 'w-[86px]', 'Default' => 'w-[80px]',
                                'Status' => 'w-[80px]',
                            ] as $label => $w)
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] {{ $w }}">{{ $label }}</th>
                            @endforeach
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->templates as $t)
                            <tr wire:key="tpl-{{ $t->template_id }}" wire:dblclick="openModal('edit', {{ $t->template_id }})" class="hover:bg-[#f7faff] cursor-pointer" title="Klik 2x untuk buka urutan langkah">
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[11px] font-mono font-bold px-1.5 py-0.5 rounded bg-[#eef1f5] text-[#2c3e69]">{{ $t->template_code }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <div class="font-semibold truncate">{{ $t->template_name }}</div>
                                    @if ($t->description)
                                        <div class="text-[10.5px] text-muted truncate">{{ $t->description }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-[11.5px]">{{ $t->prod_type_code ?: 'semua tipe' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-[11.5px]">{{ $t->substrate_hint ?: '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    @if ($t->steps_count > 0)
                                        <span class="text-[11px] font-bold px-2 py-0.5 rounded bg-[#eaf7ee] text-[#166534]">{{ $t->steps_count }} langkah</span>
                                    @else
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-[#fef2f2] text-[#b91c1c]">kosong</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    @if ($t->is_default)
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-[#fef9c3] text-[#92400e]">★ Default</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block {{ $t->activate ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#eef0f3] text-[#8a8f98]' }}">{{ $t->activate ? 'AKTIF' : 'NONAKTIF' }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-pen" title="Edit" action="openModal('edit', {{ $t->template_id }})" />
                                    <x-grid.action-icon :active="$t->activate" :title="$t->activate ? 'Nonaktifkan' : 'Aktifkan'" action="toggleActive({{ $t->template_id }})"
                                        :confirm="($t->activate ? 'Nonaktifkan' : 'Aktifkan').' template '.$t->template_name.'?'"
                                        :variant="$t->activate ? 'danger' : 'default'" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="8" message="Belum ada routing template." />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
        @endif

        {{-- ============ FORM ============ --}}
        @if ($panel === 'form')
            <div class="flex justify-between items-center bg-navy text-white px-4 py-3 rounded-t-md">
                <div class="text-sm font-bold">
                    {{ $mode === 'edit' ? 'Edit Routing Template : '.$template_code : 'Routing Template Baru' }}
                </div>
                @if ($mode === 'edit')
                    @php($v = $this->validation)
                    <div class="text-[11px] font-semibold px-2 py-0.5 rounded {{ $v['ok'] ? 'bg-[#166534]' : 'bg-[#b91c1c]' }}">
                        {{ $v['ok'] ? 'Urutan valid' : count($v['blocks']).' masalah menghalangi' }}
                    </div>
                @endif
            </div>

            <div class="bg-white border border-t-0 border-[#d7dbe2] rounded-b-md p-5">
                <form wire:submit="save">
                    <div class="[display:grid] grid-cols-1 md:grid-cols-3 gap-3">
                        <x-form.input label="Kode Template *" name="template_code" wire:model="template_code" placeholder="mis. BOX-DPX-4C" />
                        <x-form.input label="Tipe Produk (kode)" name="prod_type_code" wire:model="prod_type_code" placeholder="kosong = semua tipe" />
                        <x-form.select label="Status Aktif" wire:model="template_activate" :placeholder="false" :options="['1' => 'Aktif', '0' => 'Nonaktif']" />
                    </div>
                    <div class="[display:grid] grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                        <x-form.input label="Nama Template *" name="template_name" wire:model="template_name" placeholder="mis. Box Duplex Full Color + Laminasi" />
                        <x-form.input label="Acuan Bahan" name="substrate_hint" wire:model="substrate_hint" placeholder="mis. DUPLEX 350gsm" />
                    </div>
                    <div class="mt-3">
                        <x-form.textarea label="Keterangan" name="description" wire:model="description" autogrow />
                    </div>
                    <div class="mt-3 border border-[#d7dbe2] rounded-md p-3">
                        <x-form.checkbox wire:model="is_default">
                            <span class="font-semibold">Jadikan template default untuk tipe/kategori ini</span>
                            <span class="block text-[10.5px] text-muted">Dipakai otomatis saat produk cocok dan tidak ada pilihan lain. Kalau tidak ada default, officer yang memilih.</span>
                        </x-form.checkbox>
                    </div>

                    <div class="flex gap-2 mt-4">
                        <button type="submit" class="px-4 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer">Simpan</button>
                        <button type="button" wire:click="showGrid" class="px-4 py-2 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">Batal</button>
                        <x-back-button fallback="production.routing-template" class="ml-auto" />
                    </div>
                </form>

                @if ($mode === 'edit')
                    {{-- ===== PEMBANGUN URUTAN LANGKAH ===== --}}
                    <div class="mt-5 pt-5 border-t border-[#d7dbe2]">
                        <p class="text-[10px] font-bold text-accent uppercase mb-2">Urutan Langkah Produksi</p>
                        <p class="text-[11px] text-muted mb-3">Nomor urut berkelipatan 10 supaya langkah bisa <strong>disisipkan</strong> di tengah tanpa menomori ulang semuanya. Picker mesin hanya menampilkan mesin yang <strong>terdaftar mampu</strong> mengerjakan proses terpilih — datanya dari matriks kapabilitas di Master Proses.</p>

                        <div class="flex flex-wrap items-end gap-2 mb-3 p-3 bg-[#fafbfd] border border-[#d7dbe2] rounded-md">
                            <div class="flex-1 min-w-[260px]">
                                <x-form.select label="Proses" name="stepProcessId" wire:model.live="stepProcessId" :options="$this->processOptions" placeholder="— pilih proses —" />
                            </div>
                            <div class="flex-1 min-w-[220px]">
                                <x-form.select label="Usulan Mesin" name="stepMachineId" wire:model="stepMachineId"
                                    :options="$this->machineOptionsForStep"
                                    :placeholder="$stepProcessId ? '— biarkan engine pilih —' : '— pilih proses dulu —'" />
                            </div>
                            <div class="w-28">
                                <x-form.input label="Cabang" name="stepBranch" wire:model="stepBranch" placeholder="MAIN" hint="MAIN/BODY/LID" />
                            </div>
                            <div class="pb-2">
                                <x-form.checkbox label="Opsional" wire:model="stepOptional" />
                            </div>
                            <button type="button" wire:click="addStep" @disabled(! $stepProcessId)
                                class="px-3.5 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">+ Tambah Langkah</button>
                        </div>

                        @if ($stepProcessId && ! $this->machineOptionsForStep)
                            <x-alert.banner type="warn" message="Proses yang dipilih belum punya mesin pelaksana yang memenuhi syarat. Langkah tetap bisa ditambah, tapi routing ini tidak akan bisa dijadwalkan sampai mesinnya dipasang di Master Proses." />
                        @endif

                        {{-- ===== PANEL VALIDASI ===== --}}
                        @php($v = $this->validation)
                        @if ($v['blocks'] || $v['warnings'] || $v['notes'])
                            <div class="mb-3 [display:grid] grid-cols-1 gap-1.5">
                                @foreach ($v['blocks'] as $b)
                                    <div class="flex gap-2 items-start text-[11.5px] px-2.5 py-2 rounded bg-[#fef2f2] border border-[#fecaca]">
                                        <i class="fa-solid fa-circle-exclamation text-[#b91c1c] mt-0.5"></i>
                                        <div>
                                            <span class="font-bold text-[#b91c1c]">BLOKIR</span>
                                            <span class="text-[#7f1d1d]">{{ $b['message'] }}</span>
                                            @if ($b['reason'])
                                                <div class="text-[10.5px] text-[#9a3412] mt-0.5">{{ $b['reason'] }}</div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                                @foreach ($v['warnings'] as $w)
                                    <div class="flex gap-2 items-start text-[11.5px] px-2.5 py-2 rounded bg-[#fff7ed] border border-[#fed7aa]">
                                        <i class="fa-solid fa-triangle-exclamation text-[#c2410c] mt-0.5"></i>
                                        <div>
                                            <span class="font-bold text-[#c2410c]">PERINGATAN</span>
                                            <span class="text-[#7c2d12]">{{ $w['message'] }}</span>
                                            @if ($w['reason'])
                                                <div class="text-[10.5px] text-[#9a3412] mt-0.5">{{ $w['reason'] }}</div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                                @foreach ($v['notes'] as $n)
                                    <div class="flex gap-2 items-start text-[11.5px] px-2.5 py-2 rounded bg-[#eff6ff] border border-[#bfdbfe]">
                                        <i class="fa-solid fa-circle-info text-[#1d4ed8] mt-0.5"></i>
                                        <span class="text-[#1e3a8a]">{{ $n }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @elseif ($this->steps->isNotEmpty())
                            <div class="mb-3 flex gap-2 items-center text-[11.5px] px-2.5 py-2 rounded bg-[#eaf7ee] border border-[#bbf7d0]">
                                <i class="fa-solid fa-circle-check text-[#166534]"></i>
                                <span class="text-[#14532d]">Urutan lolos semua aturan precedence, dan tiap proses punya mesin pelaksana.</span>
                            </div>
                        @endif

                        {{-- ===== DAFTAR LANGKAH ===== --}}
                        <div class="border border-[#d7dbe2] rounded-md overflow-x-auto">
                            <table class="w-full border-collapse text-[12px]">
                                <thead>
                                    <tr class="bg-[#fafbfd]">
                                        @foreach (['Urut', 'Proses', 'Tahap', 'Usulan Mesin', 'Alternatif', 'Sifat', 'Catatan', ''] as $h)
                                            <th class="text-left px-2.5 py-2 text-[10px] uppercase tracking-wide text-[#667] border-b border-[#eef1f5] whitespace-nowrap">{{ $h }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @php($lastBranch = null)
                                    @php($exp = collect($this->expansion)->keyBy(fn ($e) => $e['branch_code'].'#'.$e['step_seq']))
                                    @forelse ($this->steps as $s)
                                        @if ($s->branch_code !== $lastBranch)
                                            <x-grid.group-header-row label="Cabang" :value="$s->branch_code" :colspan="8" />
                                            @php($lastBranch = $s->branch_code)
                                        @endif
                                        @php($e = $exp->get($s->branch_code.'#'.$s->step_seq))
                                        <tr wire:key="step-{{ $s->step_id }}">
                                            <td class="px-2.5 py-2 border-b border-[#eef1f5] font-mono font-bold">{{ $s->step_seq }}</td>
                                            <td class="px-2.5 py-2 border-b border-[#eef1f5]">
                                                @if ($s->process?->process_code)
                                                    <span class="font-mono font-bold text-[11px] px-1.5 py-0.5 rounded bg-[#eef1f5] text-[#2c3e69]">{{ $s->process->process_code }}</span>
                                                @endif
                                                <div class="font-semibold text-[11.5px] mt-0.5">{{ $s->process?->process_name ?? '—' }}</div>
                                            </td>
                                            <td class="px-2.5 py-2 border-b border-[#eef1f5] text-[11px]">{{ $s->process?->stageLabel() ?? '—' }}</td>
                                            <td class="px-2.5 py-2 border-b border-[#eef1f5]">
                                                @if ($e && $e['suggested_machine'])
                                                    <span class="font-mono text-[11px] font-semibold">{{ $e['suggested_machine']->machine_code }}</span>
                                                    <div class="text-[10px] text-muted">{{ $s->preferred_machine_id ? 'dipilih di template' : 'usulan engine' }}</div>
                                                    @if ($e['fallback_reason'])
                                                        <div class="text-[9.5px] text-[#c2410c] mt-0.5 max-w-[220px] leading-snug">{{ $e['fallback_reason'] }}</div>
                                                    @endif
                                                @elseif ($s->process?->is_manual)
                                                    <span class="text-[10.5px] text-muted">proses tangan</span>
                                                @else
                                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-[#fef2f2] text-[#b91c1c]">tidak ada mesin</span>
                                                @endif
                                            </td>
                                            <td class="px-2.5 py-2 border-b border-[#eef1f5] text-[11px]">
                                                @if ($e)
                                                    <span class="font-mono font-semibold">{{ $e['alternatives'] }}</span> siap
                                                    @if ($e['blocked_machines'] > 0)
                                                        <div class="text-[9.5px] text-muted">{{ $e['blocked_machines'] }} tak memenuhi syarat</div>
                                                    @endif
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="px-2.5 py-2 border-b border-[#eef1f5] whitespace-nowrap">
                                                @if ($s->is_optional)
                                                    <span class="text-[9.5px] font-bold px-1.5 py-0.5 rounded bg-[#eff6ff] text-[#1d4ed8]">Opsional</span>
                                                @else
                                                    <span class="text-[9.5px] font-bold px-1.5 py-0.5 rounded bg-[#eef1f5] text-[#4b5568]">Wajib</span>
                                                @endif
                                                @if ($s->qty_factor !== null)
                                                    <div class="text-[9.5px] text-muted mt-0.5">qty x{{ rtrim(rtrim((string) $s->qty_factor, '0'), '.') }}</div>
                                                @endif
                                            </td>
                                            <td class="px-2.5 py-2 border-b border-[#eef1f5] text-[11px] max-w-[180px] truncate">{{ $s->note ?: '—' }}</td>
                                            <td class="px-2.5 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                                <x-grid.action-icon icon="fa-arrow-up" title="Naikkan" action="moveStep({{ $s->step_id }}, 'up')" />
                                                <x-grid.action-icon icon="fa-arrow-down" title="Turunkan" action="moveStep({{ $s->step_id }}, 'down')" />
                                                <x-grid.action-icon icon="fa-sliders" title="Ubah nomor/catatan" action="editStep({{ $s->step_id }})" />
                                                <x-grid.action-icon icon="fa-xmark" title="Hapus langkah" variant="danger"
                                                    action="removeStep({{ $s->step_id }})"
                                                    :confirm="'Hapus langkah '.($s->process?->process_name ?? '').' dari template ini?'" />
                                            </td>
                                        </tr>

                                        @if ($stepEditingId === $s->step_id)
                                            <tr wire:key="stepedit-{{ $s->step_id }}" class="bg-[#f7faff]">
                                                <td colspan="8" class="px-3 py-3 border-b border-[#eef1f5]">
                                                    <div class="[display:grid] grid-cols-1 md:grid-cols-3 gap-2.5">
                                                        <x-form.input label="Nomor Urut" type="number" name="stepEditSeq" wire:model="stepEditSeq" hint="Kelipatan 10; sisipan pakai 15, 25" />
                                                        <x-form.input label="Faktor Qty" name="stepEditQtyFactor" wire:model="stepEditQtyFactor" placeholder="1" hint="0.5 = belah plano" />
                                                        <x-form.input label="Catatan" name="stepEditNote" wire:model="stepEditNote" />
                                                    </div>
                                                    <div class="flex gap-2 mt-3">
                                                        <button type="button" wire:click="saveStep" class="px-3.5 py-1.5 rounded text-xs font-semibold bg-accent text-white cursor-pointer">Simpan</button>
                                                        <button type="button" wire:click="cancelStepEdit" class="px-3.5 py-1.5 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">Batal</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endif
                                    @empty
                                        <tr><td colspan="8" class="px-3 py-6 text-center text-muted text-[12px]">
                                            Belum ada langkah. Tambahkan proses satu per satu sesuai urutan produksi — validator akan memeriksa urutannya begitu ada 2 langkah atau lebih.
                                        </td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
