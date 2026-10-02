<?php

namespace Devlib\Components;

use Devlib\Icon;

// Ribbon ala Microsoft Excel: tab -> grup berlabel -> tombol besar (ikon di atas teks) / kecil (3 bertumpuk per kolom).
// Tombol bisa: biasa, dropdown (menu + submenu bertingkat), split (aksi utama + panah menu), toggle (Tebal), atau
// toggle-radio satu grup (Rata kiri/tengah/kanan). Tab "File" = backstage (panel penuh). Quick Access di atas.
// Ribbon bisa diciutkan (dbl-klik tab / tombol ^): klik tab lalu tampil melayang. Alt = tampilkan KeyTips (huruf).
// tabs: [['key','label','groups' => [['label','items' => [ITEM]]]]] ; file: [ITEM] (backstage) ; quick: [ITEM]
// ITEM: ['key','label','icon','size' => lg|sm,'menu' => [ITEM|'-'],'split' => bool,'toggle' => bool,'radio' => nama,'on' => bool,'tip' => 'K']
// Klik -> event dl:ribbon {key, on} (cancelable; bila tidak dicegah muncul toast pratinjau).
class Ribbon extends DevlibComponent
{
    public function __construct(
        public array $tabs = [],
        public array $file = [],
        public array $quick = [],
        public string $title = '',
        public ?string $active = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::ribbon';
    }

    public function icon(?string $i): string
    {
        return $i ? '<i class="fa-solid '.e(Icon::fa($i)).'"></i>' : '';
    }

    public function menu(array $items): string
    {
        $h = '<div class="dl-rb__menu" role="menu">';
        foreach ($items as $it) {
            if ($it === '-') {
                $h .= '<hr>';

                continue;
            }
            $sub = ! empty($it['menu']);
            $h .= '<div class="dl-rb__mi'.($sub ? ' has-sub' : '').'"><button type="button" role="menuitem" '.($sub ? 'data-rb-sub' : 'data-rb-key="'.e($it['key'] ?? $it['label']).'"').'>'
                .'<span class="dl-rb__mico">'.$this->icon($it['icon'] ?? null).'</span><span>'.e($it['label']).'</span>'
                .(isset($it['shortcut']) ? '<kbd>'.e($it['shortcut']).'</kbd>' : '').($sub ? '<i class="fa-solid fa-chevron-right dl-rb__chev"></i>' : '').'</button>'
                .($sub ? $this->menu($it['menu']) : '').'</div>';
        }

        return $h.'</div>';
    }

    public function button(array $it): string
    {
        $key = e($it['key'] ?? $it['label']);
        $size = ($it['size'] ?? 'sm') === 'lg' ? 'lg' : 'sm';
        $hasMenu = ! empty($it['menu']);
        $tip = isset($it['tip']) ? ' data-rb-tip="'.e($it['tip']).'"' : '';
        $state = ! empty($it['toggle']) || ! empty($it['radio']) ? ' data-rb-toggle'.(! empty($it['radio']) ? ' data-rb-radio="'.e($it['radio']).'"' : '').' aria-pressed="'.(! empty($it['on']) ? 'true' : 'false').'"' : '';
        $label = '<span class="dl-rb__lbl">'.e($it['label']).'</span>';
        $title = e(($it['title'] ?? $it['label']).(isset($it['shortcut']) ? ' ('.$it['shortcut'].')' : ''));
        $icon = '<span class="dl-rb__ico">'.$this->icon($it['icon'] ?? null).'</span>';
        $cls = 'dl-rb__btn is-'.$size.(! empty($it['on']) ? ' is-on' : '').(! empty($it['iconOnly']) ? ' is-icon' : '');

        if ($hasMenu && ! empty($it['split'])) {
            return '<div class="dl-rb__split is-'.$size.'"><button type="button" class="'.$cls.'" data-rb-key="'.$key.'" title="'.$title.'"'.$tip.'>'.$icon.($size === 'lg' ? '' : $label).'</button>'
                .'<button type="button" class="dl-rb__arrow" data-rb-drop aria-label="'.e($it['label'].' — '.\Devlib\Lang::text('options')).'">'.($size === 'lg' ? $label : '').'<i class="fa-solid fa-caret-down"></i></button>'.$this->menu($it['menu']).'</div>';
        }
        if ($hasMenu) {
            return '<div class="dl-rb__drop"><button type="button" class="'.$cls.'" data-rb-drop title="'.$title.'"'.$tip.'>'.$icon.$label.'<i class="fa-solid fa-caret-down dl-rb__caret"></i></button>'.$this->menu($it['menu']).'</div>';
        }

        return '<button type="button" class="'.$cls.'" data-rb-key="'.$key.'" title="'.$title.'"'.$tip.$state.'>'.$icon.(empty($it['iconOnly']) ? $label : '').'</button>';
    }

    // Tombol kecil ditumpuk 3 per kolom seperti Excel; tombol besar = satu kolom sendiri.
    public function columns(array $items): string
    {
        $h = '';
        $stack = [];
        $flush = function () use (&$stack, &$h) {
            if ($stack) {
                $h .= '<div class="dl-rb__stack">'.implode('', $stack).'</div>';
                $stack = [];
            }
        };
        foreach ($items as $it) {
            if (($it['size'] ?? 'sm') === 'lg') {
                $flush();
                $h .= $this->button($it);

                continue;
            }
            if (! empty($it['row'])) {
                $stack[] = '<div class="dl-rb__row">'.implode('', array_map(fn ($x) => $this->button($x + ['iconOnly' => true]), $it['row'])).'</div>';
            } else {
                $stack[] = $this->button($it);
            }
            if (count($stack) === 3) {
                $flush();
            }
        }
        $flush();

        return $h;
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-rb') }} {!! $dl() !!} data-dl-ribbon>
                <div class="dl-rb__title">
                    <div class="dl-rb__quick">@foreach ($quick as $q)<button type="button" data-rb-key="{{ $q['key'] ?? $q['label'] }}" title="{{ $q['label'] }}">{!! $icon($q['icon'] ?? null) !!}</button>@endforeach</div>
                    <span class="dl-rb__doc">{{ $title }}</span>
                    <div class="dl-rb__tell"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="{{ \Devlib\Lang::text('Search commands…') }}" data-rb-find aria-label="{{ \Devlib\Lang::text('Search commands') }}"><div class="dl-rb__found" data-rb-found hidden></div></div>
                </div>
                <div class="dl-rb__tabs" role="tablist">
                    @if ($file)<button type="button" class="dl-rb__tab is-file" data-rb-file data-rb-tip="F">File</button>@endif
                    @foreach ($tabs as $i => $t)
                        <button type="button" role="tab" class="dl-rb__tab {{ ($active ?? $tabs[0]['key']) === $t['key'] ? 'is-active' : '' }}" data-rb-tab="{{ $t['key'] }}" data-rb-tip="{{ $t['tip'] ?? strtoupper(mb_substr($t['label'], 0, 1)) }}">{{ $t['label'] }}</button>
                    @endforeach
                    <span class="dl-print__spacer"></span>
                    <button type="button" class="dl-rb__pin" data-rb-pin title="{{ \Devlib\Lang::text('Collapse / pin the ribbon (Ctrl+F1)') }}" aria-label="{{ \Devlib\Lang::text('Collapse ribbon') }}"><i class="fa-solid fa-chevron-up"></i></button>
                </div>
                <div class="dl-rb__body" data-rb-body>
                    @foreach ($tabs as $t)
                        <div class="dl-rb__panel {{ ($active ?? $tabs[0]['key']) === $t['key'] ? 'is-active' : '' }}" data-rb-panel="{{ $t['key'] }}" role="tabpanel">
                            @foreach ($t['groups'] as $g)
                                <div class="dl-rb__group"><div class="dl-rb__items">{!! $columns($g['items']) !!}</div><div class="dl-rb__glabel">{{ $g['label'] }}</div></div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
                @if ($file)
                    <div class="dl-rb__stage" data-rb-stage hidden>
                        <nav class="dl-rb__stagenav"><button type="button" data-rb-stagex><i class="fa-solid fa-circle-arrow-left"></i> {{ \Devlib\Lang::text('Back') }}</button>
                            @foreach ($file as $f)<button type="button" data-rb-key="{{ $f['key'] ?? $f['label'] }}">{!! $icon($f['icon'] ?? null) !!} {{ $f['label'] }}</button>@endforeach
                        </nav>
                        <div class="dl-rb__stagebody">{{ $slot }}</div>
                    </div>
                @endif
            </div>
            BLADE;
    }
}
