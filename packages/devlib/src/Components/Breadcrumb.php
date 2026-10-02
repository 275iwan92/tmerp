<?php

namespace Devlib\Components;

// Jejak lokasi halaman: Modul › Grup › Layar [› current].
// items: [label => url|null]. menu=true: item tanpa url (Modul, Grup) bisa diklik = membuka Layer Menu pada cabang itu
// (layar di dalam tab Shell mengirim pesan ke Shell; di halaman yang sama langsung ke Layer Menu).
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
                $labels = array_map(fn ($l, $u) => is_int($l) ? $u : $l, array_keys($items), $items);
                $leaf = end($labels) ?: null;
            @endphp
            <nav {{ $attributes->class('dl-crumb') }} {!! $dl() !!} aria-label="breadcrumb">
                @foreach ($items as $label => $url)
                    @php $text = is_int($label) ? $url : $label; $isLast = $loop->last && $current === null; @endphp
                    @if (! $loop->first)<i class="fa-solid fa-chevron-right"></i>@endif
                    @if ($url)
                        <a href="{{ $url }}" @if ($isLast) class="is-current" aria-current="page" title="{{ \Devlib\Lang::text('Reload') }} {{ $text }}" @endif>{{ $text }}</a>
                    @elseif (! $url && $menu && ! $isLast)
                        <a href="#" data-dl-crumb-menu="{{ json_encode(array_slice($labels, 0, $loop->iteration)) }}" data-leaf="{{ $leaf }}" title="{{ \Devlib\Lang::text('Open menu') }} {{ $text }}">{{ $text }}</a>
                    @else
                        <span>{{ $text }}</span>
                    @endif
                @endforeach
                @if ($current !== null)<i class="fa-solid fa-chevron-right"></i><span>{{ $current }}</span>@endif
            </nav>
            BLADE;
    }
}
