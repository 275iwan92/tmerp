@props(['title', 'subtitle' => null])
{{-- Kerangka halaman Accounting blueprint: judul + deskripsi + aksi, flash & error. --}}
<div class="lg">
    <div class="lg-wrap">
        <div class="lg-head">
            <div>
                <h1>{{ $title }}</h1>
                @if ($subtitle)<p>{{ $subtitle }}</p>@endif
            </div>
            @isset($actions)<div class="lg-actions">{{ $actions }}</div>@endisset
        </div>
        @if (session('flash'))<div class="lg-alert ok">{{ session('flash') }}</div>@endif
        @if (session('error'))<div class="lg-alert err">{{ session('error') }}</div>@endif
        {{ $slot }}
    </div>
</div>
