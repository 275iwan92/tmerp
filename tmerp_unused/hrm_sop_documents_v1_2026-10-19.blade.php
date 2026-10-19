<?php

/*
 * REMARK
 * Tujuan       : Form dan daftar "SOP and Work Instruction" perusahaan (dibuka dari grid v2 hrm-sop lewat Open / New). Satu register dengan
 *                dokumen Maintenance (tm2mnt.mnt_document); halaman ini memegang dokumen perusahaan (doc_set kosong). HR Officer menulis dan
 *                meng-Approve; yang lain hanya membaca dokumen yang sudah Approved.
 * Sumber hulu  : tm2mnt.mnt_document + mnt_document_line, tm2mst.org_department (logis).
 * Input        : Isian form (nomor, jenis, judul, revisi, berlaku sejak, departemen, tujuan, baris langkah) dan pencarian.
 * Output       : Dokumen DRAFT lalu APPROVED (terkunci; Back to Draft menaikkan revisi), langkah per baris, jejak di mnt_log.
 * Dipakai oleh : Grid v2 hrm-sop (v1Route hrm.sop-documents), Self-Service, Opening Board.
 * Status       : Aktif; menggantikan tabel sop_document (dilebur 2026-10-18).
 */


use App\Models\Master\OrgDepartment;
use App\Models\Mnt\MntDocument;
use App\Models\Mnt\MntLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use App\Livewire\Concerns\OpensFromQuery;
use Livewire\Component;

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

    public string $doc_no = '';

    public string $kind = 'SOP'; // SOP | WI (Work Instruction)

    public string $title = '';

    public string $revision = '00';

    public string $effective_date = '';

    public ?int $department_id = null;

    public string $purpose = '';

    public string $status = 'DRAFT';

    /** @var array<int, array{section: string, text: string}> */
    public array $lines = [];

    public bool $doc_active = true;

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
        return MntDocument::company()->with('department')
            ->when(! $this->isHrOfficer(), fn ($q) => $q->inForce())
            ->when($this->search !== '', fn ($q) => $q->where(fn ($qq) => $qq
                ->where('title', 'like', "%{$this->search}%")
                ->orWhere('doc_no', 'like', "%{$this->search}%")))
            ->orderBy('title')
            ->get();
    }

    #[Computed]
    public function statTotal(): int
    {
        return MntDocument::company()->count();
    }

    #[Computed]
    public function statApproved(): int
    {
        return MntDocument::company()->inForce()->count();
    }

    #[Computed]
    public function statAllDept(): int
    {
        return MntDocument::company()->inForce()->whereNull('department_id')->count();
    }

    private function refresh(): void
    {
        unset($this->documents, $this->statTotal, $this->statApproved, $this->statAllDept);
    }

    public function openNew(): void
    {
        if (! $this->isHrOfficer()) {
            return;
        }

        $this->resetErrorBag();
        $this->reset(['editingId', 'doc_no', 'title', 'effective_date', 'department_id', 'purpose']);
        $this->revision = '00';
        $this->kind = 'SOP';
        $this->status = 'DRAFT';
        $this->doc_active = true;
        $this->lines = [['section' => 'Steps', 'text' => '']];
        $this->panel = 'form';
    }

    // Klik dari grid: HR Officer + Draft -> form edit. Selain itu (Approved, atau bukan HR Officer) -> panel view read-only.
    public function open(int $id): void
    {
        $doc = MntDocument::company()->with('lines')->findOrFail($id);
        abort_unless($this->isHrOfficer() || ($doc->isApproved() && $doc->activate), 404);

        $this->resetErrorBag();
        $this->editingId = $doc->id_document;
        $this->doc_no = $doc->doc_no;
        $this->kind = $doc->kind;
        $this->title = $doc->title;
        $this->revision = (string) $doc->revision;
        $this->effective_date = (string) ($doc->effective_date?->format('Y-m-d') ?? '');
        $this->department_id = $doc->department_id;
        $this->purpose = (string) $doc->purpose;
        $this->status = $doc->status;
        $this->doc_active = (bool) $doc->activate;
        $this->lines = $doc->lines->map(fn ($l) => ['section' => $l->section, 'text' => $l->line_text])->all() ?: [['section' => 'Steps', 'text' => '']];

        $this->panel = ($this->isHrOfficer() && ! $doc->isApproved()) ? 'form' : 'view';
    }

    public function addLine(): void
    {
        $this->lines[] = ['section' => $this->lines ? end($this->lines)['section'] : 'Steps', 'text' => ''];
    }

    public function removeLine(int $i): void
    {
        unset($this->lines[$i]);
        $this->lines = array_values($this->lines) ?: [['section' => 'Steps', 'text' => '']];
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
        if ($this->editingId && MntDocument::findOrFail($this->editingId)->isApproved()) {
            $this->addError('doc_no', 'An approved document is locked. Use Back to Draft first.');

            return;
        }

        $data = $this->validate([
            'doc_no' => ['required', 'string', 'max:40', 'unique:tm2mnt.mnt_document,doc_no,'.($this->editingId ?: 'NULL').',id_document'],
            'kind' => ['required', 'in:'.implode(',', array_keys(MntDocument::COMPANY_KINDS))],
            'title' => ['required', 'string', 'max:200'],
            'revision' => ['required', 'string', 'max:10'],
            'effective_date' => ['nullable', 'date'],
            'department_id' => ['nullable', 'integer', 'exists:tm2mst.org_department,department_id'],
            'purpose' => ['nullable', 'string'],
            'lines.*.section' => ['nullable', 'string', 'max:60'],
            'lines.*.text' => ['nullable', 'string'],
            'doc_active' => ['boolean'],
        ]);

        $login = Auth::user()->login;
        $payload = [
            'doc_no' => $data['doc_no'], 'kind' => $data['kind'], 'title' => $data['title'], 'revision' => $data['revision'],
            'effective_date' => $data['effective_date'] ?: null, 'department_id' => $data['department_id'] ?: null,
            'purpose' => $data['purpose'] ?: null, 'activate' => $data['doc_active'], 'updated_by' => $login,
        ];

        DB::connection('tm2mnt')->transaction(function () use ($payload, $login) {
            $doc = $this->editingId
                ? tap(MntDocument::findOrFail($this->editingId))->update($payload)
                : MntDocument::create($payload + ['status' => 'DRAFT', 'created_by' => $login]);
            $doc->lines()->delete();
            $seq = 0;
            foreach ($this->lines as $line) {
                $text = trim((string) ($line['text'] ?? ''));
                if ($text !== '') {
                    $doc->lines()->create(['seq_no' => ++$seq, 'section' => trim((string) ($line['section'] ?? '')) ?: 'Steps', 'line_text' => $text, 'created_by' => $login, 'updated_by' => $login]);
                }
            }
            MntLog::add('DOCUMENT', $doc->id_document, $this->editingId ? 'Saved' : 'Created');
        });

        $this->refresh();
        $this->panel = 'grid';
    }

    public function approve(int $id): void
    {
        if (! $this->isHrOfficer()) {
            return;
        }

        $doc = MntDocument::company()->findOrFail($id);
        abort_if($doc->isApproved(), 422, 'This document is already approved.');
        $doc->update(['status' => 'APPROVED', 'approved_by' => Auth::user()->login, 'approved_at' => now(), 'updated_by' => Auth::user()->login]);
        MntLog::add('DOCUMENT', $doc->id_document, 'Approved', 'Revision '.$doc->revision);

        $this->refresh();
        $this->panel = 'grid';
    }

    public function backToDraft(int $id): void
    {
        if (! $this->isHrOfficer()) {
            return;
        }

        $doc = MntDocument::company()->findOrFail($id);
        abort_unless($doc->isApproved(), 422, 'Only an approved document can go back to draft.');
        $rev = str_pad((string) ((int) $doc->revision + 1), 2, '0', STR_PAD_LEFT);
        $doc->update(['status' => 'DRAFT', 'revision' => $rev, 'approved_by' => null, 'approved_at' => null, 'updated_by' => Auth::user()->login]);
        MntLog::add('DOCUMENT', $doc->id_document, 'Back to draft', 'New revision '.$rev);

        $this->refresh();
        $this->open($id);
    }

    public function toggleActive(int $id): void
    {
        if (! $this->isHrOfficer()) {
            return;
        }

        $doc = MntDocument::company()->findOrFail($id);
        $doc->update(['activate' => ! $doc->activate, 'updated_by' => Auth::user()->login]);

        $this->refresh();
    }
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">

        {{-- ============ GRID ============ --}}
        @if ($panel === 'grid')
        <x-grid.stat-row :count="3">
            <x-grid.stat-box tone="total" label="Total documents" :value="$this->statTotal" />
            <x-grid.stat-box tone="active" label="Approved" :value="$this->statApproved" />
            <x-grid.stat-box tone="extra" label="All Departments" :value="$this->statAllDept" />
        </x-grid.stat-row>

        @unless ($this->isHrOfficer())
            <div class="text-xs px-3 py-2.5 rounded-md mb-3.5 leading-relaxed bg-[#eef2ff] border border-[#c7d0f5] text-[#3f4a8a]">
                View-only mode — only the HR Officer can add or change SOPs and Work Instructions. Double-click a row to read the full text.
            </div>
        @endunless

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search code / title…"
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
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Document</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Department Scope</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">Rev / Effective</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[90px]">Status</th>
                            @if ($this->isHrOfficer())
                                <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[80px]">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->documents as $doc)
                            <tr wire:key="sop-{{ $doc->id_document }}" wire:dblclick="open({{ $doc->id_document }})" class="hover:bg-[#f7faff] cursor-pointer" title="Double-click to open">
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <div class="font-semibold text-[13px]">{{ $doc->title }}</div>
                                    <div class="text-[11px] text-muted font-mono">{{ \App\Models\Mnt\MntDocument::COMPANY_KINDS[$doc->kind] ?? 'SOP' }} · {{ $doc->doc_no }}</div>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $doc->department?->department_name ?: 'All Departments' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-[#667]">Rev {{ $doc->revision }}{{ $doc->effective_date ? ' · '.$doc->effective_date->format('d-M-Y') : '' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block {{ ! $doc->activate ? 'bg-[#eef0f3] text-[#8a8f98]' : ($doc->isApproved() ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#fff4de] text-[#8a5a00]') }}">{{ ! $doc->activate ? 'INACTIVE' : ($doc->isApproved() ? 'APPROVED' : 'DRAFT') }}</span>
                                </td>
                                @if ($this->isHrOfficer())
                                    <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                        <x-grid.action-icon icon="fa-pen" title="Edit" action="open({{ $doc->id_document }})" />
                                        <x-grid.action-icon :active="$doc->activate" :title="$doc->activate ? 'Deactivate' : 'Activate'" action="toggleActive({{ $doc->id_document }})"
                                            :confirm="($doc->activate ? 'Deactivate' : 'Activate').' '.$doc->title.'?'"
                                            :variant="$doc->activate ? 'danger' : 'default'" />
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="$this->isHrOfficer() ? 5 : 4" message="No documents yet." />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
        @endif

        {{-- ============ FORM (HR OFFICER, Draft only) ============ --}}
        @if ($panel === 'form')
            <div class="flex justify-between items-center bg-navy text-white px-4 py-3 rounded-t-md">
                <div class="text-sm font-bold">{{ $editingId ? 'Edit document' : 'New document' }}</div>
                <span class="text-[10.5px] font-mono opacity-75">DRAFT</span>
            </div>
            <form wire:submit="save" class="bg-white border border-t-0 border-[#d7dbe2] p-5 flex flex-col gap-3">
                <div class="grid two gap-2.5">
                    <x-form.input label="Document No. *" name="doc_no" wire:model="doc_no" placeholder="e.g. 001/SOP/TM/V/2026" />
                    <x-form.input label="Revision" name="revision" wire:model="revision" />
                </div>
                <div class="grid two gap-2.5">
                    <x-form.select label="Document Type *" name="kind" wire:model="kind" :options="\App\Models\Mnt\MntDocument::COMPANY_KINDS" :selected="$kind" />
                    <x-form.input label="Title *" name="title" wire:model="title" />
                </div>
                <div class="grid two gap-2.5">
                    <x-form.date-picker label="Effective From" name="effective_date" wire:model="effective_date" />
                    <x-form.select label="Department Scope" name="department_id" wire:model="department_id"
                        :options="$this->departmentOptions" :selected="$department_id" placeholder="— All Departments —" />
                </div>
                <x-form.textarea label="Purpose" name="purpose" wire:model="purpose" rows="2" autogrow />

                <div>
                    <div class="text-xs font-bold text-navy uppercase tracking-wide mb-1">Steps</div>
                    <div class="flex flex-col gap-1.5">
                        @foreach ($lines as $i => $line)
                            <div class="flex items-start gap-2" wire:key="line-{{ $i }}">
                                <span class="w-6 pt-2 text-xs text-[#667] text-right">{{ $i + 1 }}</span>
                                <div class="w-40 shrink-0"><x-form.input :case-toggle="false" label="" name="lines_{{ $i }}_section" wire:model="lines.{{ $i }}.section" placeholder="Section" /></div>
                                <div class="flex-1"><x-form.input :case-toggle="false" label="" name="lines_{{ $i }}_text" wire:model="lines.{{ $i }}.text" placeholder="Step text" /></div>
                                <button type="button" wire:click="removeLine({{ $i }})" title="Delete line" class="mt-1 w-8 h-8 rounded-md hover:bg-[#eef1f5] cursor-pointer">🗑</button>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" wire:click="addLine" class="mt-2 px-3 py-1.5 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">+ Add line</button>
                </div>

                <x-form.checkbox label="Active" wire:model="doc_active" />

                <div class="flex gap-2 pt-3.5 -mx-5 -mb-5 px-4 py-3.5 bg-[#fafbfc] border-t border-[#d7dbe2] rounded-b-md">
                    <button type="submit" class="px-4 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer">Save</button>
                    @if ($editingId)
                        <button type="button" wire:click="approve({{ $editingId }})" wire:confirm="Approve this document? It becomes visible to employees and locked." class="px-4 py-2 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">Approve</button>
                    @endif
                    <button type="button" wire:click="showGrid" data-dirty-guard class="px-4 py-2 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">Cancel</button>
                </div>
            </form>
        @endif

        {{-- ============ VIEW (read-only: Approved document, or not HR Officer) ============ --}}
        @if ($panel === 'view')
            <div class="flex justify-between items-center bg-navy text-white px-4 py-3 rounded-t-md">
                <div class="text-sm font-bold">{{ $title }}</div>
                <span class="text-[10.5px] font-mono opacity-75">{{ \App\Models\Mnt\MntDocument::COMPANY_KINDS[$kind] ?? 'SOP' }} · {{ $doc_no }} · Rev {{ $revision }} · {{ $status === 'APPROVED' ? 'APPROVED' : 'DRAFT' }}</span>
            </div>
            <div class="bg-white border border-t-0 border-[#d7dbe2] p-5 rounded-b-md flex flex-col gap-3">
                <div class="text-[11px] text-muted flex gap-4 flex-wrap">
                    <span>Scope: <b class="text-[#233]">{{ $this->departmentOptions[$department_id] ?? 'All Departments' }}</b></span>
                    @if ($effective_date)
                        <span>Effective from: <b class="text-[#233]">{{ \Illuminate\Support\Carbon::parse($effective_date)->format('d-M-Y') }}</b></span>
                    @endif
                </div>
                @if ($purpose)
                    <div>
                        <div class="text-xs font-bold text-navy uppercase tracking-wide mb-1">Purpose</div>
                        <div class="text-[13px] text-[#334] leading-relaxed whitespace-pre-line">{{ $purpose }}</div>
                    </div>
                @endif
                <div>
                    <div class="text-xs font-bold text-navy uppercase tracking-wide mb-1">Steps</div>
                    <div class="text-[13px] text-[#334] leading-relaxed">
                        @forelse ($lines as $i => $line)
                            @if ($line['text'] !== '')
                                <div><span class="text-[#667] inline-block w-6">{{ $i + 1 }}.</span>{{ $line['text'] }} <span class="text-[11px] text-muted">({{ $line['section'] }})</span></div>
                            @endif
                        @empty
                            —
                        @endforelse
                    </div>
                </div>
                <div class="flex gap-2 pt-2 -mx-5 -mb-5 px-4 py-3.5 bg-[#fafbfc] border-t border-[#d7dbe2] rounded-b-md">
                    @if ($this->isHrOfficer() && $status === 'APPROVED' && $editingId)
                        <button type="button" wire:click="backToDraft({{ $editingId }})" wire:confirm="Back to Draft? The revision goes up and employees stop seeing it until you approve again." class="px-4 py-2 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">Back to Draft</button>
                    @endif
                    <button type="button" wire:click="showGrid" data-dirty-guard class="px-4 py-2 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">← Back</button>
                </div>
            </div>
        @endif
    </div>
</div>
