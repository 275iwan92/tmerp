{{-- Brand kecil di atas kartu halaman auth (forgot-password/password-change/
    security-setup) -- docs/configurationstandard.md, sumber sama dgn
    x-auth.hero-brand. Logo di sini tetap emoji 📦 kalau belum ada logo upload
    (kartu ini terlalu sempit utk badge gradient penuh spt hero-brand). --}}
@php
    $brandName = \App\Support\Branding\Brand::name();
    $brandLogoUrl = \App\Support\Branding\Brand::logoUrl();
@endphp
<div class="top">
    @if ($brandLogoUrl)
        <img src="{{ $brandLogoUrl }}" alt="{{ $brandName }}" class="inline-block w-5 h-5 rounded object-cover align-middle mr-1">
    @else
        <span class="logo">📦</span>
    @endif
    {{ $brandName }}
</div>
