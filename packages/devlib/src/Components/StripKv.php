<?php

/*
 * REMARK
 * Tujuan       : Satu kotak "label kecil + nilai" di strip ringkasan Form dokumen (no. dokumen, total, status angka).
 * Sumber hulu  : Slot strip milik x-dl::form; nilai dari layar (no. dokumen, total, tanggal).
 * Input        : label (teks English, diterjemahkan), value (teks jadi; pakai raw() untuk data), kind: '' | doc | grand; valueAttrs = atribut untuk elemen nilai (mis. data-l-val untuk total langsung di form dokumen).
 * Output       : <div class="dl-strip__kv [dl-strip__doc|dl-strip__grand]"><span>label</span><b>value</b></div>, gaya dari devlib.css.
 * Dipakai oleh : layar dokumen v2 (customer-order, purchase-order, maintenance-*, ...) menggantikan div dl-strip__kv tulisan tangan.
 * Status       : Aktif.
 */

namespace Devlib\Components;

class StripKv extends DevlibComponent
{
    public function __construct(
        public string $label = '',
        public ?string $value = null,
        public string $kind = '',
        public array $valueAttrs = [],
    ) {}

    public function tag(): string
    {
        return 'x-dl::strip-kv';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-strip__kv', 'dl-strip__doc' => $kind === 'doc', 'dl-strip__grand' => $kind === 'grand']) }} {!! $dl() !!}><span>{{ $label }}</span><b {{ new \Illuminate\View\ComponentAttributeBag($valueAttrs) }}>{{ $value ?? $slot }}</b></div>
            BLADE;
    }
}
