<?php

/*
 * REMARK
 * Tujuan       : Komponen v1 untuk layar "Manpower Requisition" (dibuka dari grid v2 lewat Open / New). Teks tampilan (label, kolom, tab, tombol, pesan) English sesuai keputusan Jo 2026-10-18; nilai tersimpan dan kunci data tidak diubah.
 * Sumber hulu  : Tabel dan service yang dibaca komponen ini (lihat query di file); tidak ada logika hulu baru.
 * Input        : Isian form dan filter layar.
 * Output       : Simpan ke tabel modul terkait lewat service yang sama seperti sebelumnya.
 * Dipakai oleh : Grid v2 modul terkait (v1Route).
 * Status       : Aktif; teks tampilan English (Jo 2026-10-18).
 */


use App\Models\Hrr\RcrRequisition;
use App\Models\Hrr\RcrTrack;
use App\Models\Master\OrgDepartment;
use App\Services\Recruitment\RecruitmentApprovalService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// L1 (entry + approval merge-in) -- docs/recruitment_tier4.md SECTION 6, pola
// persis pur/pr/entry.blade.php (griduistandard.md §8: approver memutuskan di
// dokumen asli, bukan layar ringkasan terpisah). Approval bespoke
// RecruitmentApprovalService (bukan FlowEngineService, lihat class itu).
new #[Title('Manpower Requisition — TELASIH MAKMUR ERP')] class extends Component
{
    public int $requisitionId;

    public string $approvalNote = '';

    public function mount(int $requisition): void
    {
        $this->requisitionId = $requisition;
    }

    #[Computed]
    public function requisition(): RcrRequisition
    {
        return RcrRequisition::with(['track', 'approvals'])->findOrFail($this->requisitionId);
    }

    #[Computed]
    public function tracks()
    {
        return RcrTrack::where('is_active', true)->orderBy('id_track')->get();
    }

    #[Computed]
    public function departments()
    {
        return OrgDepartment::where('activate', true)->orderBy('department_name')->get();
    }

    // Seq PENDING terkecil, hanya kalau approver_id = user login ini.
    #[Computed]
    public function pendingApproval()
    {
        $approval = $this->requisition->approvals->firstWhere('decision', 'PENDING');

        return ($approval && (int) $approval->approver_id === Auth::id()) ? $approval : null;
    }

    public function updateHeader(string $field, $value): void
    {
        if (! in_array($field, ['id_track', 'department_id', 'position_title', 'headcount', 'qualification', 'reason'], true)) {
            return;
        }

        $this->requisition->update([$field => $value]);
        unset($this->requisition);
    }

    public function submit(RecruitmentApprovalService $approval): void
    {
        $r = $this->requisition;

        if (trim($r->position_title) === '' || trim($r->qualification) === '' || trim($r->reason) === '') {
            $this->addError('submit', 'Position, qualification and reason are required before submitting.');

            return;
        }

        $approval->submitRequisition($r);
        unset($this->requisition);
    }

    public function approveDoc(RecruitmentApprovalService $approval): void
    {
        $pending = $this->pendingApproval;
        if (! $pending) {
            return;
        }

        $approval->decideRequisition($pending, 'APPROVED', $this->approvalNote ?: null);
        $this->approvalNote = '';
        unset($this->requisition, $this->pendingApproval);
    }

    public function rejectDoc(RecruitmentApprovalService $approval): void
    {
        $pending = $this->pendingApproval;
        if (! $pending) {
            return;
        }

        if (trim($this->approvalNote) === '') {
            $this->addError('reject', 'Rejection reason is required.');

            return;
        }

        $approval->decideRequisition($pending, 'REJECTED', $this->approvalNote);
        $this->approvalNote = '';
        unset($this->requisition, $this->pendingApproval);
    }

    public function delete()
    {
        if ($this->requisition->status !== 'DRAFT') {
            return;
        }

        $this->requisition->delete();

        return $this->redirect(route('hrm.recruitment.requisition.list'));
    }
};
?>

<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">👤 Manpower Requisition</b>
        <span class="text-xs text-white/75">{{ $this->requisition->requisition_no }}</span>
        <span class="text-[10.5px] font-bold px-2.5 py-0.5 rounded-full
            {{ match ($this->requisition->status) {
                'DRAFT' => 'bg-[#eef1f5] text-[#4b5568]',
                'SUBMITTED' => 'bg-[#fff5e0] text-[#8a5a00]',
                'APPROVED', 'CLOSED' => 'bg-[#eaf7ee] text-[#1f8a4c]',
                'REJECTED' => 'bg-[#fbeaea] text-[#a3241f]',
                default => 'bg-[#eef1f5] text-[#4b5568]',
            } }}">{{ $this->requisition->status }}</span>
        <x-back-button :fallback="route('hrm.recruitment.requisition.list')" title="Back" class="ml-auto" />
    </header>

    <div class="max-w-[900px] mx-auto my-5 px-4">
        <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4">
            <h3 class="text-xs text-navy uppercase font-bold mb-3">Requisition Information</h3>
            <div class="grid">
                <x-form.select :disabled="$this->requisition->status !== 'DRAFT'" wire:change="updateHeader('id_track', $event.target.value)" class="disabled:bg-[#f8fafc]"
                    :selected="$this->requisition->id_track" :options="$this->tracks->pluck('name', 'id_track')->all()" :placeholder="false">
                    <x-slot:label>Track <span class="text-[#a3241f]">*</span></x-slot:label>
                </x-form.select>
                <x-form.select :disabled="$this->requisition->status !== 'DRAFT'" wire:change="updateHeader('department_id', $event.target.value)" class="disabled:bg-[#f8fafc]"
                    :selected="$this->requisition->department_id" :options="$this->departments->pluck('department_name', 'department_id')->all()" :placeholder="false">
                    <x-slot:label>Department <span class="text-[#a3241f]">*</span></x-slot:label>
                </x-form.select>
                <x-form.input label="Position Needed" value="{{ $this->requisition->position_title }}" :disabled="$this->requisition->status !== 'DRAFT'"
                    wire:change="updateHeader('position_title', $event.target.value)" class="disabled:bg-[#f8fafc]" />
                <x-form.input label="Headcount" type="number" min="1" value="{{ $this->requisition->headcount }}" :disabled="$this->requisition->status !== 'DRAFT'"
                    wire:change="updateHeader('headcount', $event.target.value)" class="disabled:bg-[#f8fafc]" />
            </div>
            <div class="grid two mt-3">
                <x-form.textarea rows="3" :disabled="$this->requisition->status !== 'DRAFT'" wire:change="updateHeader('qualification', $event.target.value)" class="disabled:bg-[#f8fafc]">
                    <x-slot:label>Qualification <span class="text-[#a3241f]">*</span></x-slot:label>
                    {{ $this->requisition->qualification }}
                </x-form.textarea>
                <x-form.textarea rows="3" :disabled="$this->requisition->status !== 'DRAFT'" wire:change="updateHeader('reason', $event.target.value)" class="disabled:bg-[#f8fafc]">
                    <x-slot:label>Requisition Reason <span class="text-[#a3241f]">*</span></x-slot:label>
                    {{ $this->requisition->reason }}
                </x-form.textarea>
            </div>
        </div>

        @if ($errors->has('submit'))
            <div class="bg-[#fdecec] border border-[#f5c6c6] text-[#a3453f] text-xs rounded-md px-4 py-2.5 mb-4">⚠ {{ $errors->first('submit') }}</div>
        @endif

        @if ($this->requisition->approvals->isNotEmpty())
            <div class="border border-[#eef1f5] rounded-xl p-4 bg-white mb-4">
                <p class="text-[10.5px] font-bold text-muted uppercase mb-3">Approval Progress</p>
                <div class="flex items-center gap-2">
                    @foreach ($this->requisition->approvals as $a)
                        <div class="flex-1 text-center">
                            <div class="w-8 h-8 mx-auto rounded-full flex items-center justify-center text-xs font-bold text-white {{ match ($a->decision) {
                                'APPROVED' => 'bg-[#1f8a4c]',
                                'REJECTED' => 'bg-[#a3241f]',
                                default => 'bg-[#c8ccd6]',
                            } }}">{{ $a->decision === 'APPROVED' ? '✓' : ($a->decision === 'REJECTED' ? '✕' : $a->approval_seq) }}</div>
                            <p class="text-[10px] text-muted mt-1">Seq {{ $a->approval_seq }}</p>
                            @if ($a->decided_at)
                                <p class="text-[9.5px] text-muted">{{ $a->decided_at->translatedFormat('d M Y') }}</p>
                            @endif
                            @if ($a->note)
                                <p class="text-[9.5px] text-[#a3241f]">"{{ $a->note }}"</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($this->requisition->status === 'DRAFT')
            <div class="flex justify-end gap-2 mb-8">
                <button type="button" wire:click="delete" wire:confirm="Delete this draft requisition?" class="border border-[#d7dbe2] text-[#a3241f] text-xs font-bold px-4 py-2 rounded-md">Delete</button>
                <button type="button" wire:click="submit" wire:confirm="Submit this requisition for approval?" class="bg-navy text-white text-xs font-bold px-4 py-2 rounded-md">Submit for Approval</button>
            </div>
        @elseif ($this->pendingApproval)
            <div class="border-2 border-dashed border-[#d7dbe2] rounded-xl p-4 bg-white mb-8">
                <p class="text-xs font-bold text-navy mb-2">Approval Decision — Seq {{ $this->pendingApproval->approval_seq }}</p>
                @if ($errors->has('reject'))
                    <p class="text-[11px] text-[#a3241f] mb-2">⚠ {{ $errors->first('reject') }}</p>
                @endif
                <div class="mb-3">
                    <x-form.textarea wire:model="approvalNote" rows="2" placeholder="Rejection reason (required to Reject)...">
                        <x-slot:label>Notes</x-slot:label>
                    </x-form.textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="rejectDoc" wire:confirm="Reject this requisition?" class="bg-[#a3241f] text-white px-4 py-2 rounded-lg text-sm font-semibold">✕ Reject</button>
                    <button type="button" wire:click="approveDoc" wire:confirm="Approve this requisition?" class="bg-[#1f8a4c] text-white px-4 py-2 rounded-lg text-sm font-semibold">✓ Approve</button>
                </div>
            </div>
        @endif
    </div>
</div>
