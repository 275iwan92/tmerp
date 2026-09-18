<?php

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\AppOffer;
use App\Services\Recruitment\RecruitmentApprovalService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// L7 (entry + approval merge-in) -- docs/recruitment_tier4.md SECTION 12.
// Kandidat accept/decline seharusnya lewat portal (P4) -- di iterasi ini
// portal belum punya tombol accept interaktif, jadi HR input manual di sini
// (dicatat sbg keterbatasan di plan sesi 2026-08-29).
new #[Title('Offering — TELASIH MAKMUR ERP')] class extends Component
{
    public int $applicationId;

    public string $approvalNote = '';

    public function mount(int $application): void
    {
        $this->applicationId = $application;

        if (! AppOffer::where('id_application', $application)->exists()) {
            AppOffer::create([
                'id_application' => $application,
                'proposed_salary' => 0,
                'employment_type' => 'TETAP',
                'start_date' => now()->addDays(14)->toDateString(),
                'status' => 'DRAFT',
                'created_by' => Auth::id(),
            ]);
        }
    }

    #[Computed]
    public function application(): AppApplication
    {
        return AppApplication::with('candidate', 'vacancy')->findOrFail($this->applicationId);
    }

    #[Computed]
    public function offer(): AppOffer
    {
        return AppOffer::with('approvals')->where('id_application', $this->applicationId)->orderByDesc('id_offer')->firstOrFail();
    }

    #[Computed]
    public function pendingApproval()
    {
        $approval = $this->offer->approvals->firstWhere('decision', 'PENDING');

        return ($approval && (int) $approval->approver_id === Auth::id()) ? $approval : null;
    }

    public function updateField(string $field, $value): void
    {
        if (! in_array($field, ['proposed_salary', 'employment_type', 'start_date'], true)) {
            return;
        }

        $this->offer->update([$field => $value]);
        unset($this->offer);
    }

    public function submit(RecruitmentApprovalService $approval): void
    {
        $offer = $this->offer;

        if ((float) $offer->proposed_salary <= 0) {
            $this->addError('submit', 'Gaji diusulkan wajib diisi.');

            return;
        }

        $approval->submitOffer($offer);
        unset($this->offer);
    }

    public function approveDoc(RecruitmentApprovalService $approval): void
    {
        $pending = $this->pendingApproval;
        if (! $pending) {
            return;
        }

        $approval->decideOffer($pending, 'APPROVED');
        unset($this->offer, $this->pendingApproval);
    }

    public function rejectDoc(RecruitmentApprovalService $approval): void
    {
        $pending = $this->pendingApproval;
        if (! $pending) {
            return;
        }

        $approval->decideOffer($pending, 'REJECTED');
        unset($this->offer, $this->pendingApproval);
    }

    public function markSent(): void
    {
        $this->offer->update(['status' => 'SENT']);
        unset($this->offer);
    }

    public function markAccepted(): void
    {
        $this->offer->update(['status' => 'ACCEPTED']);
        unset($this->offer);
    }

    public function markDeclined(): void
    {
        $this->offer->update(['status' => 'DECLINED']);
        unset($this->offer);
    }

    public function revise(): void
    {
        $old = $this->offer;

        AppOffer::create([
            'id_application' => $this->applicationId,
            'previous_offer_id' => $old->id_offer,
            'proposed_salary' => $old->proposed_salary,
            'employment_type' => $old->employment_type,
            'start_date' => $old->start_date,
            'status' => 'DRAFT',
            'created_by' => Auth::id(),
        ]);

        unset($this->offer);
    }
};
?>

<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">💼 Offering</b>
        <span class="text-xs text-white/75">{{ $this->application->candidate->name ?? '' }}</span>
        <span class="text-[10.5px] font-bold px-2.5 py-0.5 rounded-full
            {{ match ($this->offer->status) {
                'DRAFT' => 'bg-[#eef1f5] text-[#4b5568]',
                'PENDING_APPROVAL' => 'bg-[#fff5e0] text-[#8a5a00]',
                'APPROVED', 'SENT' => 'bg-[#f1ecfc] text-[#6d28d9]',
                'ACCEPTED' => 'bg-[#eaf7ee] text-[#1f8a4c]',
                'DECLINED', 'EXPIRED' => 'bg-[#fbeaea] text-[#a3241f]',
                default => 'bg-[#eef1f5] text-[#4b5568]',
            } }}">{{ $this->offer->status }}</span>
        <x-back-button :fallback="route('hrm.recruitment.offer.list')" title="Kembali" class="ml-auto" />
    </header>

    <div class="max-w-[700px] mx-auto my-5 px-4">
        <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4">
            <h3 class="text-xs text-navy uppercase font-bold mb-3">Detail Penawaran</h3>
            <div class="grid">
                <x-form.input label="Gaji Diusulkan" type="number" step="0.01" value="{{ $this->offer->proposed_salary }}" :disabled="$this->offer->status !== 'DRAFT'"
                    wire:change="updateField('proposed_salary', $event.target.value)" class="disabled:bg-[#f8fafc]" />
                <x-form.select :disabled="$this->offer->status !== 'DRAFT'" wire:change="updateField('employment_type', $event.target.value)" class="disabled:bg-[#f8fafc]"
                    :selected="$this->offer->employment_type" :options="['TETAP' => 'Tetap', 'KONTRAK' => 'Kontrak', 'HARIAN' => 'Harian', 'BORONGAN' => 'Borongan']" :placeholder="false">
                    <x-slot:label>Jenis Kontrak</x-slot:label>
                </x-form.select>
                <x-form.date-picker label="Tanggal Mulai" value="{{ $this->offer->start_date?->format('Y-m-d') }}" :disabled="$this->offer->status !== 'DRAFT'"
                    wire:change="updateField('start_date', $event.target.value)" class="disabled:bg-[#f8fafc]" />
            </div>
        </div>

        @if ($errors->has('submit'))
            <div class="bg-[#fdecec] border border-[#f5c6c6] text-[#a3453f] text-xs rounded-md px-4 py-2.5 mb-4">⚠ {{ $errors->first('submit') }}</div>
        @endif

        @if ($this->offer->approvals->isNotEmpty())
            <div class="border border-[#eef1f5] rounded-xl p-4 bg-white mb-4">
                <p class="text-[10.5px] font-bold text-muted uppercase mb-3">Progres Approval</p>
                <div class="flex items-center gap-2">
                    @foreach ($this->offer->approvals as $a)
                        <div class="flex-1 text-center">
                            <div class="w-8 h-8 mx-auto rounded-full flex items-center justify-center text-xs font-bold text-white {{ match ($a->decision) {
                                'APPROVED' => 'bg-[#1f8a4c]',
                                'REJECTED' => 'bg-[#a3241f]',
                                default => 'bg-[#c8ccd6]',
                            } }}">{{ $a->decision === 'APPROVED' ? '✓' : ($a->decision === 'REJECTED' ? '✕' : $a->approval_seq) }}</div>
                            <p class="text-[10px] text-muted mt-1">Seq {{ $a->approval_seq }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($this->offer->status === 'DRAFT')
            <div class="flex justify-end gap-2 mb-8">
                <button type="button" wire:click="submit" wire:confirm="Ajukan offer ini untuk approval?" class="bg-navy text-white text-xs font-bold px-4 py-2 rounded-md">Ajukan Approval</button>
            </div>
        @elseif ($this->pendingApproval)
            <div class="border-2 border-dashed border-[#d7dbe2] rounded-xl p-4 bg-white mb-8">
                <p class="text-xs font-bold text-navy mb-2">Keputusan Approval — Seq {{ $this->pendingApproval->approval_seq }}</p>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="rejectDoc" wire:confirm="Tolak offer ini?" class="bg-[#a3241f] text-white px-4 py-2 rounded-lg text-sm font-semibold">✕ Tolak</button>
                    <button type="button" wire:click="approveDoc" wire:confirm="Setujui offer ini?" class="bg-[#1f8a4c] text-white px-4 py-2 rounded-lg text-sm font-semibold">✓ Setujui</button>
                </div>
            </div>
        @elseif ($this->offer->status === 'APPROVED')
            <div class="flex justify-end gap-2 mb-8">
                <button type="button" wire:click="markSent" class="bg-navy text-white text-xs font-bold px-4 py-2 rounded-md">Kirim ke Kandidat</button>
            </div>
        @elseif ($this->offer->status === 'SENT')
            <div class="border-2 border-dashed border-[#d7dbe2] rounded-xl p-4 bg-white mb-8">
                <p class="text-xs font-bold text-navy mb-2">Respons Kandidat <span class="font-normal text-muted">(input manual — portal belum ada tombol accept interaktif)</span></p>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="markDeclined" wire:confirm="Tandai kandidat menolak offer ini?" class="bg-[#a3241f] text-white px-4 py-2 rounded-lg text-sm font-semibold">Kandidat Menolak</button>
                    <button type="button" wire:click="markAccepted" wire:confirm="Tandai kandidat menerima offer ini?" class="bg-[#1f8a4c] text-white px-4 py-2 rounded-lg text-sm font-semibold">Kandidat Menerima</button>
                </div>
            </div>
        @elseif ($this->offer->status === 'DECLINED')
            <div class="flex justify-end gap-2 mb-8">
                <button type="button" wire:click="revise" class="bg-navy text-white text-xs font-bold px-4 py-2 rounded-md">Revisi Offer</button>
            </div>
        @elseif ($this->offer->status === 'ACCEPTED')
            <div class="bg-[#eaf7ee] border border-[#c8ecd6] text-[#1f8a4c] text-xs rounded-md px-4 py-2.5 mb-8">
                ✓ Offer diterima. Lanjut ke <a href="{{ route('hrm.recruitment.hire-provision') }}" class="font-bold underline">Provisioning ke ERP</a>.
            </div>
        @endif
    </div>
</div>
