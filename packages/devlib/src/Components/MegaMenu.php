<?php

namespace Devlib\Components;

use Devlib\Icon;

// Menu atas ala ScriptCase: bilah berwarna berisi item; item bisa tautan, dropdown biasa (bisa bertingkat), atau
// MEGA MENU (panel lebar beberapa kolom; tiap kolom = judul ber-ikon + daftar item ber-ikon, sorot saat hover).
// Desktop: buka saat hover / klik, Esc menutup, panah kiri-kanan pindah item. Layar sempit: hamburger + akordeon.
// items: [['label','icon','url'?,'key'?,'badge'?,'items' => [ITEM] (dropdown),'columns' => [['label','icon','items' => [ITEM]]] (mega)]]
// ITEM: ['label','icon','url'?,'key'?,'items' => [ITEM] (submenu)]. Klik -> event dl:menu {key,label} (cancelable).
class MegaMenu extends DevlibComponent
{
    public function __construct(
        public array $items = [],
        public string $brand = '',
        public string $tone = 'green',
        public ?string $active = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::mega-menu';
    }

    public function icon(?string $i): string
    {
        return $i ? '<i class="fa-solid '.e(Icon::fa($i)).'"></i>' : '';
    }

    public function link(array $it, string $cls): string
    {
        $key = e($it['key'] ?? $it['label']);
        $attrs = isset($it['url']) ? 'href="'.e($it['url']).'"' : 'href="#" data-mm-key="'.$key.'"';

        return '<a '.$attrs.' class="'.$cls.'">'.$this->icon($it['icon'] ?? null).'<span>'.e($it['label']).'</span>'.(isset($it['badge']) ? '<em class="dl-mm__badge">'.e($it['badge']).'</em>' : '').'</a>';
    }

    public function list(array $items): string
    {
        $h = '<ul class="dl-mm__list">';
        foreach ($items as $it) {
            $sub = ! empty($it['items']);
            $h .= '<li class="'.($sub ? 'has-sub' : '').'">'.($sub
                ? '<button type="button" class="dl-mm__li" data-mm-sub>'.$this->icon($it['icon'] ?? null).'<span>'.e($it['label']).'</span><i class="fa-solid fa-chevron-right dl-mm__chev"></i></button>'.$this->list($it['items'])
                : $this->link($it, 'dl-mm__li')).'</li>';
        }

        return $h.'</ul>';
    }

    public function render()
    {
        return <<<'BLADE'
            <nav {{ $attributes->class(['dl-mm', 'dl-mm--'.$tone]) }} {!! $dl() !!} data-dl-mega aria-label="{{ \Devlib\Lang::text('Main menu') }}">
                @if ($brand)<span class="dl-mm__brand">{{ $brand }}</span>@endif
                <button type="button" class="dl-mm__burger" data-mm-burger aria-label="{{ \Devlib\Lang::text('Open menu') }}"><i class="fa-solid fa-bars"></i></button>
                <ul class="dl-mm__bar" data-mm-bar>
                    @foreach ($items as $i => $it)
                        @php
                            $kind = ! empty($it['columns']) ? 'mega' : (! empty($it['items']) ? 'drop' : 'link');
                        @endphp
                        <li class="dl-mm__top is-{{ $kind }} {{ $active === ($it['key'] ?? $it['label']) ? 'is-current' : '' }}">
                            @if ($kind === 'link')
                                {!! $link($it, 'dl-mm__tl') !!}
                            @else
                                <button type="button" class="dl-mm__tl" data-mm-open aria-expanded="false">{!! $icon($it['icon'] ?? null) !!}<span>{{ $it['label'] }}</span><i class="fa-solid fa-caret-down dl-mm__caret"></i></button>
                                @if ($kind === 'mega')
                                    <div class="dl-mm__panel is-mega" style="--cols: {{ count($it['columns']) }}">
                                        @foreach ($it['columns'] as $col)
                                            <div class="dl-mm__col"><div class="dl-mm__head">{!! $icon($col['icon'] ?? null) !!}<span>{{ $col['label'] }}</span></div>{!! $list($col['items']) !!}</div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="dl-mm__panel">{!! $list($it['items']) !!}</div>
                                @endif
                            @endif
                        </li>
                    @endforeach
                </ul>
                <span class="dl-mm__end">{{ $slot }}</span>
            </nav>
            BLADE;
    }
}
