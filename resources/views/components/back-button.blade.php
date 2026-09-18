@props(['fallback'])
{{-- Kembali ke layar sebelumnya lewat riwayat browser; fallback (grid modul) hanya bila tak ada riwayat, mis. tab baru / URL dibuka langsung. --}}
<a href="{{ $fallback }}"
    {{ $attributes->merge([
        'title' => 'Kembali ke layar sebelumnya',
        'class' => 'inline-flex items-center gap-2 pl-3.5 pr-1 py-1 bg-[#43508a] rounded-full no-underline hover:brightness-110',
    ]) }}
    onclick="if (window.navigation && navigation.canGoBack) { event.preventDefault(); history.back(); }">
    <span class="text-[11px] font-bold uppercase tracking-wide text-white">Back</span>
    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-[#43508a] text-[13px]"><i class="fa-solid fa-arrow-left"></i></span>
</a>
