<?php

use App\Models\Hrr\VacVacancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::portal')] #[Title('Detail Lowongan')] class extends Component
{
    public string $vacancy_code;

    public function mount(string $vacancy_code): void
    {
        $this->vacancy_code = $vacancy_code;
    }

    public function getVacancyProperty()
    {
        return VacVacancy::where('vacancy_code', $this->vacancy_code)->where('is_published', true)->firstOrFail();
    }
};
?>

<div class="max-w-[760px] mx-auto px-5 py-10">
    <a href="{{ route('careers.landing') }}" class="text-[12px] font-bold text-muted hover:text-navy">&larr; Kembali ke daftar lowongan</a>

    <div class="bg-white border border-[#e6e9ef] rounded-xl p-6 mt-4">
        <span class="text-[10px] font-bold text-accent uppercase tracking-wide">{{ $this->vacancy->vacancy_code }}</span>
        <h1 class="text-[22px] font-extrabold text-navy mt-1 mb-4">{{ $this->vacancy->title }}</h1>
        <div class="text-[13px] text-[#333a4d] leading-relaxed whitespace-pre-line">{{ $this->vacancy->description }}</div>

        <div class="mt-6 pt-5 border-t border-[#eef1f5]">
            @if (session('portal_candidate_uid'))
                <a href="{{ route('careers.apply', $this->vacancy->vacancy_code) }}" class="inline-block bg-[#1f8a4c] text-white text-sm font-bold px-5 py-2.5 rounded-md hover:opacity-90">Lamar Posisi Ini</a>
            @else
                <a href="{{ route('careers.register') }}" class="inline-block bg-navy text-white text-sm font-bold px-5 py-2.5 rounded-md hover:opacity-90">Daftar untuk Melamar</a>
                <span class="text-[12px] text-muted ml-2">Sudah punya akun? <a href="{{ route('careers.login') }}" class="text-accent font-bold">Masuk</a></span>
            @endif
        </div>
    </div>
</div>
