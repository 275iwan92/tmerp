{{-- Wrapper grid utk kotak "pihak" (Kepada Yth./Dari/PIC, dst). `columns`
     = nilai grid-template-columns mentah (mis. "1.25fr 1fr .85fr") supaya
     proporsi lebar per dokumen bisa beda -- default sama rata. --}}
@props(['columns' => null])
<div class="print-parties" @if ($columns) style="grid-template-columns:{{ $columns }}" @endif>
    {{ $slot }}
</div>
