{{-- Satu halaman A4 fisik di dalam dokumen cetak. Dokumen multi-halaman
     (mis. Nota Inquiry: 2 halaman) pakai print.sheet lebih dari sekali. --}}
@props([])
<div class="print-page">
    {{ $slot }}
</div>
