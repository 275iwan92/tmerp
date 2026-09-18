@props(['fallback'])
{{-- data-back ditangani resources/js/dirty-guard.js: tanya simpan kalau form berubah, lalu kembali ke layar sebelumnya; href = fallback bila tak ada riwayat. --}}
<a href="{{ $fallback }}" data-back
    {{ $attributes->merge([
        'title' => 'Kembali ke layar sebelumnya',
        'class' => 'inline-flex items-center gap-2 pl-3.5 pr-1 py-1 bg-[#43508a] rounded-full no-underline hover:brightness-110',
    ]) }}>
    <span class="text-[11px] font-bold uppercase tracking-wide text-white">Back</span>
    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-[#43508a] text-[13px]"><i class="fa-solid fa-arrow-left"></i></span>
</a>
