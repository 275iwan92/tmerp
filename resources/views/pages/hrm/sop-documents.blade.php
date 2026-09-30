<?php

use App\Models\Master\OrgDepartment;
use App\Models\Master\SopDocument;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use App\Livewire\Concerns\OpensFromQuery;
use Livewire\Component;

// Register dokumen SOP (keputusan user 2026-09-13: "Begitu pula dengan SOP"
// -- pola sama dengan Deskripsi Kerja: input HANYA HR Officer, dibaca
// read-only oleh siapa pun via Self-Service). Layar ini SATU untuk 2 peran:
// HR Officer dapat New/Edit/Nonaktifkan; selain itu grid yang sama jadi
// read-only (klik 2x buka detail, tidak ada tombol simpan/tambah). Field
// masih flat (kode+judul+tujuan+isi teks), BELUM memecah isi jadi langkah
// kerja terstruktur seperti sop_workflow_steps di
// docs/sop_module_blueprint_reference.md -- itu menunggu arahan lanjutan.
new #[Title('SOP — TELASIH MAKMUR ERP')] class extends Component
{
    use OpensFromQuery;

    protected function openFromQuery(int $id): void
    {
        $this->open($id);
    }

    public string $search = '';

    public string $panel = 'grid'; // grid | form | view

    public ?int $editingId = null;

    public string $sop_code = '';

    public string $title = '';

    public string $revision = '0';

    public string $effective_date = '';

    public ?int $department_id = null;

    public string $purpose = '';

    public string $content = '';

    public bool $sop_activate = true;

    public function isHrOfficer(): bool
    {
        return Auth::user()->isHrOfficer();
    }

    #[Computed]
    public function departmentOptions()
    {
        return OrgDepartment::where('activate', true)->orderBy('department_name')->pluck('department_name', 'department_id');
    }

    #[Computed]
    public function documents()
    {
        return SopDocument::with('department')
            ->when($this->search !== '', fn ($q) => $q->where(fn ($qq) => $qq
                ->where('title', 'like', "%{$this->search}%")
                ->orWhere('sop_code', 'like', "%{$this->search}%")))
            ->orderBy('title')
            ->get();
    }

    #[Computed]
    public function statTotal(): int
    {
        return SopDocument::count();
    }

    #[Computed]
    public function statActive(): int
    {
        return SopDocument::where('activate', true)->count();
    }

    #[Computed]
    public function statAllDept(): int
    {
        return SopDocument::whereNull('department_id')->where('activate', true)->count();
    }

    public function openNew(): void
    {
        if (! $this->isHrOfficer()) {
            return;
        }

        $this->resetErrorBag();
        $this->reset(['editingId', 'sop_code', 'title', 'effective_date', 'department_id', 'purpose', 'content']);
        $this->revision = '0';
        $this->sop_activate = true;
        $this->panel = 'form';
    }

    // Klik dari grid: HR Officer -> langsung ke form edit. Selain itu -> ke
    // panel view read-only (tidak ada method save yang bisa dipanggil dari sana).
    public function open(int $id): void
    {
        $doc = SopDocument::findOrFail($id);
        $this->editingId = $doc->sop_id;
        $this->sop_code = $doc->sop_code;
        $this->title = $doc->title;
        $this->revision = $doc->revision;
        $this->effective_date = (string) ($doc->effective_date?->format('Y-m-d') ?? '');
        $this->department_id = $doc->department_id;
        $this->purpose = (string) $doc->purpose;
        $this->content = (string) $doc->content;
        $this->sop_activate = (bool) $doc->activate;

        $this->panel = $this->isHrOfficer() ? 'form' : 'view';
    }

    public function showGrid(): void
    {
        $this->panel = 'grid';
    }

    public function save(): void
    {
        // Guard server-side, bukan cuma sembunyikan tombol di Blade.
        if (! $this->isHrOfficer()) {
            return;
        }

        $data = $this->validate([
            'sop_code' => ['required', 'string', 'max:50', 'unique:tm2mst.sop_document,sop_code,'.($this->editingId ?: 'NULL').',sop_id'],
            'title' => ['required', 'string', 'max:200'],
            'revision' => ['required', 'string', 'max:10'],
            'effective_date' => ['nullable', 'date'],
            'department_id' => ['nullable', 'integer', 'exists:tm2mst.org_department,department_id'],
            'purpose' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'sop_activate' => ['boolean'],
        ]);

        $payload = [
            'sop_code' => $data['sop_code'],
            'title' => $data['title'],
            'revision' => $data['revision'],
            'effective_date' => $data['effective_date'] ?: null,
            'department_id' => $data['department_id'] ?: null,
            'purpose' => $data['purpose'] ?: null,
            'content' => $data['content'] ?: null,
            'activate' => $data['sop_activate'],
            'updated_by' => Auth::user()->login,
        ];

        if ($this->editingId) {
            SopDocument::whereKey($this->editingId)->update($payload);
        } else {
            $payload['created_by'] = Auth::user()->login;
            SopDocument::create($payload);
        }

        unset($this->documents, $this->statTotal, $this->statActive, $this->statAllDept);
        $this->panel = 'grid';
    }

    public function toggleActive(int $id): void
    {
        if (! $this->isHrOfficer()) {
            return;
        }

        $doc = SopDocument::findOrFail($id);
        $doc->update(['activate' => ! $doc->activate, 'updated_by' => Auth::user()->login]);

        unset($this->documents, $this->statTotal, $this->statActive, $this->statAllDept);
    }
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">

        {{-- ============ GRID ============ --}}
        @if ($panel === 'grid')
        <x-grid.stat-row :count="3">
            <x-grid.stat-box tone="total" label="Total SOP" :value="$this->statTotal" />
            <x-grid.stat-box tone="active" label="Aktif" :value="$this->statActive" />
            <x-grid.stat-box tone="extra" label="Berlaku Semua Departemen" :value="$this->statAllDept" />
        </x-grid.stat-row>

        @unless ($this->isHrOfficer())
            <div class="text-xs px-3 py-2.5 rounded-md mb-3.5 leading-relaxed bg-[#eef2ff] border border-[#c7d0f5] text-[#3f4a8a]">
                Mode lihat saja — hanya HR Officer yang bisa menambah/mengubah SOP. Klik 2x baris untuk membaca isi lengkap.
            </div>
        @endunless

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari kode / judul SOP…"
                        class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                @if ($this->isHrOfficer())
                    <x-slot:newRecord>
                        <button type="button" wire:click="openNew"
                            class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
                    </x-slot:newRecord>
                @endif
            </x-grid.toolbar>

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Dokumen SOP</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Cakupan Departemen</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">Rev / Berlaku</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[80px]">Status</th>
                            @if ($this->isHrOfficer())
                                <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[80px]">Aksi</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->documents as $doc)
                            <tr wire:key="sop-{{ $doc->sop_id }}" wire:dblclick="open({{ $doc->sop_id }})" class="hover:bg-[#f7faff] cursor-pointer" title="Klik 2x untuk buka">
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <div class="font-semibold text-[13px]">{{ $doc->title }}</div>
                                    <div class="text-[11px] text-muted font-mono">{{ $doc->sop_code }}</div>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $doc->department?->department_name ?: 'Semua Departemen' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-[#667]">Rev {{ $doc->revision }}{{ $doc->effective_date ? ' · '.$doc->effective_date->format('d/m/Y') : '' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block {{ $doc->activate ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#eef0f3] text-[#8a8f98]' }}">{{ $doc->activate ? 'AKTIF' : 'NONAKTIF' }}</span>
                                </td>
                                @if ($this->isHrOfficer())
                                    <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                        <x-grid.action-icon icon="fa-pen" title="Edit" action="open({{ $doc->sop_id }})" />
                                        <x-grid.action-icon :active="$doc->activate" :title="$doc->activate ? 'Nonaktifkan' : 'Aktifkan'" action="toggleActive({{ $doc->sop_id }})"
                                            :confirm="($doc->activate ? 'Nonaktifkan' : 'Aktifkan').' SOP '.$doc->title.'?'"
                                            :variant="$doc->activate ? 'danger' : 'default'" />
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="$this->isHrOfficer() ? 5 : 4" message="Belum ada dokumen SOP." />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
        @endif

        {{-- ============ FORM (HR OFFICER) ============ --}}
        @if ($panel === 'form')
            <div class="flex justify-between items-center bg-navy text-white px-4 py-3 rounded-t-md">
                <div class="text-sm font-bold">{{ $editingId ? 'Edit SOP' : 'SOP Baru' }}</div>
            </div>
            <form wire:submit="save" class="bg-white border border-t-0 border-[#d7dbe2] p-5 flex flex-col gap-3">
                <div class="grid two gap-2.5">
                    <x-form.input label="Nomor Dokumen *" name="sop_code" wire:model="sop_code" placeholder="mis. 001/SOP/TM/V/2026" />
                    <x-form.input label="Revisi" name="revision" wire:model="revision" />
                </div>
                <x-form.input label="Judul SOP *" name="title" wire:model="title" />
                <div class="grid two gap-2.5">
                    <x-form.date-picker label="Berlaku Sejak" name="effective_date" wire:model="effective_date" />
                    <x-form.select label="Cakupan Departemen" name="department_id" wire:model="department_id"
                        :options="$this->departmentOptions" :selected="$department_id" placeholder="— Semua Departemen —" />
                </div>
                <x-form.textarea label="Tujuan" name="purpose" wire:model="purpose" rows="2" autogrow />
                <x-form.textarea label="Isi SOP" name="content" wire:model="content" rows="8" autogrow
                    hint="Ditampilkan apa adanya (baris baru dipertahankan) ke pegawai lewat Self-Service." />

                <x-form.checkbox label="Aktif" wire:model="sop_activate" />

                <div class="flex gap-2 pt-3.5 -mx-5 -mb-5 px-4 py-3.5 bg-[#fafbfc] border-t border-[#d7dbe2] rounded-b-md">
                    <button type="submit" class="px-4 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer">Simpan</button>
                    <button type="button" wire:click="showGrid" data-dirty-guard class="px-4 py-2 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">Batal</button>
                </div>
            </form>
        @endif

        {{-- ============ VIEW (READ-ONLY, non-HR Officer) ============ --}}
        @if ($panel === 'view')
            <div class="flex justify-between items-center bg-navy text-white px-4 py-3 rounded-t-md">
                <div class="text-sm font-bold">{{ $title }}</div>
                <span class="text-[10.5px] font-mono opacity-75">{{ $sop_code }} · Rev {{ $revision }}</span>
            </div>
            <div class="bg-white border border-t-0 border-[#d7dbe2] p-5 rounded-b-md flex flex-col gap-3">
                <div class="text-[11px] text-muted flex gap-4 flex-wrap">
                    <span>Cakupan: <b class="text-[#233]">{{ $this->departmentOptions[$department_id] ?? 'Semua Departemen' }}</b></span>
                    @if ($effective_date)
                        <span>Berlaku sejak: <b class="text-[#233]">{{ \Illuminate\Support\Carbon::parse($effective_date)->format('d F Y') }}</b></span>
                    @endif
                </div>
                @if ($purpose)
                    <div>
                        <div class="text-xs font-bold text-navy uppercase tracking-wide mb-1">Tujuan</div>
                        <div class="text-[13px] text-[#334] leading-relaxed whitespace-pre-line">{{ $purpose }}</div>
                    </div>
                @endif
                <div>
                    <div class="text-xs font-bold text-navy uppercase tracking-wide mb-1">Isi SOP</div>
                    <div class="text-[13px] text-[#334] leading-relaxed whitespace-pre-line">{{ $content ?: '—' }}</div>
                </div>
                <div class="pt-2 -mx-5 -mb-5 px-4 py-3.5 bg-[#fafbfc] border-t border-[#d7dbe2] rounded-b-md">
                    <button type="button" wire:click="showGrid" data-dirty-guard class="px-4 py-2 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">← Kembali</button>
                </div>
            </div>
        @endif
    </div>
</div>
