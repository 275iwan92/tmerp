{{-- Foot fisik per halaman (posisi absolute ke bawah print.sheet). `page`
     = teks "Halaman X dari Y" di kanan; slot default = info kiri. --}}
@props(['page' => null])
<div class="print-foot">
    <span>{{ $slot }}</span>
    @if ($page)<span>{{ $page }}</span>@endif
</div>
