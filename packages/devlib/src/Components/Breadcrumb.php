<?php

namespace Devlib\Components;

// Jejak lokasi halaman: Modul › Grup › Layar [› current].
// items: [label => url|null], atau [[label, url|null, openUrl|null], ...] bila dua label boleh sama (grup dan layar bernama sama).
// menu=true: item tanpa url (Modul, Grup) bisa diklik = membuka Layer Menu pada cabang itu
// (layar di dalam tab Shell mengirim pesan ke Shell; di halaman yang sama langsung ke Layer Menu).
// openUrl terisi = halaman kanan ikut pindah ke alamat itu (core.js; form yang belum disimpan ditanya dulu).
// current: teks posisi saat ini (mis. "Form") -> item terakhir ber-url ikut jadi tautan (kembali ke grid layar itu).
class Breadcrumb extends DevlibComponent
{
    public function __construct(
        public array $items = [],
        public bool $menu = false,
        public ?string $current = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::breadcrumb';
    }

    public function render()
    {
        return <<<'BLADE'
            @php
                $rows = array_map(fn ($l, $u) => array_pad(is_array($u) ? $u : [is_int($l) ? $u : $l, $u], 3, null), array_keys($items), $items);
                $labels = array_column($rows, 0);
                $leaf = end($labels) ?: null;
            @endphp
            <nav {{ $attributes->class('dl-crumb') }} {!! $dl() !!} aria-label="breadcrumb">
                @foreach ($rows as [$text, $url, $open])
                    @php $isLast = $loop->last && $current === null; @endphp
                    @if (! $loop->first)<i class="fa-solid fa-chevron-right"></i>@endif
                    @if ($url)
                        <a href="{{ $url }}" @if ($isLast) class="is-current" aria-current="page" title="{{ \Devlib\Lang::text('Reload') }} {{ $text }}" @endif>{{ $text }}</a>
                    @elseif (! $url && $menu && ! $isLast)
                        <a href="#" data-dl-crumb-menu="{{ json_encode(array_slice($labels, 0, $loop->iteration)) }}" data-leaf="{{ $leaf }}" @if ($open) data-dl-crumb-url="{{ $open }}" @endif title="{{ \Devlib\Lang::text('Open menu') }} {{ $text }}">{{ $text }}</a>
                    @else
                        <span>{{ $text }}</span>
                    @endif
                @endforeach
                @if ($current !== null)<i class="fa-solid fa-chevron-right"></i><span>{{ $current }}</span>@endif
            </nav>
            BLADE;
    }
}
