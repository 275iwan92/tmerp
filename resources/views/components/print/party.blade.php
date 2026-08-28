{{-- Satu kotak pihak di dalam print.parties. Konten (nama, detail) bebas
     HTML lewat slot default -- variasinya (baris <br>, kondisional) terlalu
     beragam antar dokumen utk dipaksa jadi prop tunggal. --}}
@props(['label'])
<div class="print-party">
    <h4>{{ $label }}</h4>
    {{ $slot }}
</div>
