<?php

use App\Models\Hrr\VacVacancy;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

// P2 -- Daftar Lowongan (recruitment_tier4.md §3), versi web publik. Baca
// vac_vacancy langsung (internal) -- portal & internal masih 1 proses Laravel
// utk sekarang (belum host terpisah sungguhan), lihat docs/recruitmentportal_web_tier1.md.
new #[Layout('layouts::portal')] #[Title('Karir')] class extends Component
{
    #[Computed]
    public function vacancies()
    {
        return VacVacancy::where('is_published', true)->whereNull('closed_at')->orderByDesc('id_vacancy')->get();
    }
};
?>

<div>
    <section class="bg-white border-b border-[#e6e9ef]">
        <div class="max-w-[1100px] mx-auto px-5 py-14 text-center">
            <h1 class="text-[28px] md:text-[34px] font-extrabold text-navy leading-tight mb-3">Bangun Karir Anda di Telasih Makmur</h1>
            <p class="text-[14px] text-muted max-w-[560px] mx-auto">Perusahaan percetakan offset &amp; packaging yang terus berkembang. Temukan posisi yang sesuai dengan keahlian Anda.</p>
        </div>
    </section>

    <section class="max-w-[1100px] mx-auto px-5 py-10">
        <h2 class="text-[13px] font-bold text-muted uppercase tracking-wide mb-4">Lowongan Terbuka ({{ $this->vacancies->count() }})</h2>

        @if ($this->vacancies->isEmpty())
            <div class="bg-white border border-[#e6e9ef] rounded-xl p-8 text-center text-[13px] text-muted">
                Belum ada lowongan terbuka saat ini. Silakan cek kembali lain waktu.
            </div>
        @else
            <div class="[display:grid] gap-4 md:grid-cols-2">
                @foreach ($this->vacancies as $vac)
                    <a href="{{ route('careers.vacancy-detail', $vac->vacancy_code) }}" class="block bg-white border border-[#e6e9ef] rounded-xl p-5 hover:border-accent hover:shadow-md transition">
                        <span class="text-[10px] font-bold text-accent uppercase tracking-wide">{{ $vac->vacancy_code }}</span>
                        <h3 class="text-[15px] font-bold text-navy mt-1 mb-2">{{ $vac->title }}</h3>
                        <p class="text-[12.5px] text-muted line-clamp-2">{{ $vac->description }}</p>
                        <span class="inline-block mt-3 text-[12px] font-bold text-accent">Lihat detail &amp; lamar &rarr;</span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</div>
