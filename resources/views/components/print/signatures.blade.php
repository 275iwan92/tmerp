{{-- Blok "PENGESAHAN & PERSETUJUAN" -- header + intro opsional + grid sel
     tanda tangan (print.signature). `columns` default menyesuaikan jumlah
     print.signature anak (biasanya 2 atau 3), boleh override eksplisit. --}}
@props(['title' => t('VALIDATION & APPROVAL'), 'columns' => null])
<div class="print-sign">
    <div class="print-sign-h">{{ $title }}</div>
    <div class="print-sign-b">
        @isset($intro)
            <div class="print-sign-intro">{{ $intro }}</div>
        @endisset
        <div class="print-sign-grid" @if ($columns) style="grid-template-columns:{{ $columns }}" @endif>
            {{ $slot }}
        </div>
    </div>
</div>
