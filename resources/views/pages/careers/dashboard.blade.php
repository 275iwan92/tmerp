<?php

use App\Mail\Portal\CandidateEmailVerification;
use App\Services\Portal\CandidateAuthService;
use App\Services\Recruitment\FirebaseSyncAdapter;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

// P4 -- Riwayat Lamaran (recruitment_tier4.md §5), versi web publik. Status
// dibaca dari field 'app_status' yang di-tulis balik App\Observers\AppApplicationObserver
// (pushStatusUpdate real, bukan no-op lagi) -- kalau belum pernah di-push
// (baru submit, belum diproses HR), tampilkan berdasar sync_status saja.
// FirebaseSyncAdapter LANGSUNG (bukan interface switchable) -- lihat komentar
// careers/apply.blade.php kenapa portal profesional ini hardcode Firebase.
new #[Layout('layouts::portal')] #[Title('Lamaran Saya')] class extends Component
{
    #[Computed]
    public function candidate()
    {
        return app(CandidateAuthService::class)->findByUid(session('portal_candidate_uid'));
    }

    #[Computed]
    public function submissions()
    {
        return collect(app(FirebaseSyncAdapter::class)->findAllByCandidateUid(session('portal_candidate_uid')));
    }

    public function resendVerification(): void
    {
        $candidate = $this->candidate;
        $verifyUrl = URL::temporarySignedRoute('careers.verify-email', now()->addMinutes(60), ['uid' => $candidate['_docId']]);
        Mail::to($candidate['email'])->send(new CandidateEmailVerification($candidate['name'], $verifyUrl));
        $this->dispatch('verification-resent');
    }
};
?>

<div class="max-w-[760px] mx-auto my-8 px-5" x-data="{ resent: false }" x-on:verification-resent.window="resent = true">
    <h1 class="text-[18px] font-extrabold text-navy mb-1">Halo, {{ $this->candidate['name'] ?? '' }}</h1>
    <p class="text-[12.5px] text-muted mb-5">{{ $this->candidate['email'] ?? '' }} · {{ $this->candidate['national_id'] ?? '' }}</p>

    @if (empty($this->candidate['email_verified_at'] ?? null))
        <div class="bg-[#fff8e6] border border-[#f4e0a3] text-[#8a5a00] text-[11.5px] rounded-md px-3 py-2.5 mb-5 flex items-center justify-between gap-3">
            <span>⚠ Email belum diverifikasi -- lamaran tidak bisa dikirim sampai diverifikasi.</span>
            <button type="button" wire:click="resendVerification" x-show="!resent" class="font-bold text-navy underline whitespace-nowrap">Kirim ulang</button>
            <span x-show="resent" x-cloak class="font-bold text-[#1f8a4c] whitespace-nowrap">Terkirim ✓</span>
        </div>
    @endif

    <div class="bg-white border border-[#e6e9ef] rounded-xl p-5">
        <h2 class="text-[13px] font-bold text-muted uppercase tracking-wide mb-3">Riwayat Lamaran</h2>

        @if ($this->submissions->isEmpty())
            <p class="text-[12.5px] text-muted">Belum ada lamaran. <a href="{{ route('careers.landing') }}" class="text-accent font-bold">Lihat lowongan terbuka &rarr;</a></p>
        @else
            <div class="divide-y divide-[#eef1f5]">
                @foreach ($this->submissions as $s)
                    @php
                        $status = $s['app_status'] ?? ($s['sync_status'] === 'synced' ? 'DIPROSES' : 'MENUNGGU SINKRONISASI');
                        $badge = match ($status) {
                            'HIRED' => 'bg-[#eaf7ee] text-[#1f8a4c]',
                            'REJECTED', 'WITHDRAWN' => 'bg-[#fbeaea] text-[#a3241f]',
                            'OFFERING' => 'bg-[#f1ecfc] text-[#6d28d9]',
                            'MENUNGGU SINKRONISASI' => 'bg-[#f1f4f9] text-muted',
                            default => 'bg-[#fff5e0] text-[#8a5a00]',
                        };
                        $label = match ($status) {
                            'APPLIED', 'IN_PROGRESS' => 'DIPROSES',
                            default => $status,
                        };
                    @endphp
                    <div class="flex items-center justify-between py-3 text-[13px]">
                        <div>
                            <p class="font-bold text-navy">{{ $s['vacancy_code'] ?? '-' }}</p>
                            <p class="text-[11px] text-muted">Dikirim {{ isset($s['submitted_at']) ? \Illuminate\Support\Carbon::parse($s['submitted_at'])->translatedFormat('d M Y H:i') : '-' }}</p>
                        </div>
                        <span class="text-[10px] font-bold px-2.5 py-1 rounded-full {{ $badge }}">{{ $label }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
