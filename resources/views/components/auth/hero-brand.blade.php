{{-- Brand besar di panel hero halaman auth (login/register) --
    docs/configurationstandard.md. Sumber: App\Support\Branding\Brand
    (tm2sys.sys_constant), diedit via Configuration -- bukan hardcode lagi. --}}
@php
    $brandName = \App\Support\Branding\Brand::name();
    $brandTagline = \App\Support\Branding\Brand::tagline();
    $brandLogoUrl = \App\Support\Branding\Brand::logoUrl();
@endphp
<div class="hero-brand">
    <span class="logo-badge">
        @if ($brandLogoUrl)
            <img src="{{ $brandLogoUrl }}" alt="{{ $brandName }}" class="w-full h-full object-cover rounded-xl">
        @endif
    </span>
    <div><b>{{ $brandName }}<x-dl::env-badge part="title" variant="title" /></b>@if ($brandTagline)<span>{{ $brandTagline }}</span>@endif</div>
</div>
