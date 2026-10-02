<?php

namespace Devlib\Components;

use Devlib\Icon;

// Menu panel samping (kiri ATAU kanan). Mode lebar: akordeon bertingkat (grup -> sub -> item), cari (buka otomatis
// cabang yang cocok), item aktif disorot, badge angka. Mode ciut (rail ikon): arahkan / klik ikon -> flyout berisi
// submenu. Bintang pada item = Favorit (grup teratas), klik item = masuk "Terakhir dibuka". Mode & favorit diingat per browser.
// items: [['label','icon','key'?,'url'?,'badge'?,'tone'?,'items' => [ITEM..]]] (bertingkat bebas); caption: ['caption' => 'LAPORAN']
// Klik item -> event dl:side-menu {key,label} (cancelable).
class SideMenu extends DevlibComponent
{
    public function __construct(
        public array $items = [],
        public string $side = 'left',
        public string $title = 'Menu',
        public string $id = 'side',
        public bool $collapsed = false,
        public bool $search = true,
        public bool $favorites = true,
        public ?string $active = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::side-menu side='.$this->side;
    }

    public function icon(?string $i): string
    {
        return '<i class="fa-solid '.e(Icon::fa($i ?: 'fa-circle')).'"></i>';
    }

    public function tree(array $items, int $depth = 0): string
    {
        $h = '<ul class="dl-sm__ul" data-depth="'.$depth.'">';
        foreach ($items as $it) {
            if (isset($it['caption'])) {
                $h .= '<li class="dl-sm__cap"><span class="dl-sm__t">'.e($it['caption']).'</span></li>';

                continue;
            }
            $key = e($it['key'] ?? $it['label']);
            $badge = isset($it['badge']) ? '<em class="dl-sm__badge is-'.e($it['tone'] ?? 'accent').'">'.e($it['badge']).'</em>' : '';
            $ico = $depth === 0 || isset($it['icon']) ? '<span class="dl-sm__ico">'.$this->icon($it['icon'] ?? null).'</span>' : '<span class="dl-sm__dot"></span>';
            if (! empty($it['items'])) {
                $h .= '<li class="dl-sm__node" data-sm-node><button type="button" class="dl-sm__a" data-sm-toggle aria-expanded="false" title="'.e($it['label']).'">'.$ico.'<span class="dl-sm__t">'.e($it['label']).'</span>'.$badge.'<i class="fa-solid fa-chevron-right dl-sm__chev"></i></button>'.$this->tree($it['items'], $depth + 1).'</li>';
            } else {
                $h .= '<li class="dl-sm__leaf'.($this->active === ($it['key'] ?? $it['label']) ? ' is-active' : '').'" data-sm-leaf="'.$key.'"><a href="'.e($it['url'] ?? '#').'" class="dl-sm__a" data-sm-key="'.$key.'" title="'.e($it['label']).'">'.$ico.'<span class="dl-sm__t">'.e($it['label']).'</span>'.$badge.'</a>'
                    .($this->favorites ? '<button type="button" class="dl-sm__star" data-sm-star="'.$key.'" aria-label="'.e(\Devlib\Lang::text('Favourite')).'"><i class="fa-regular fa-star"></i></button>' : '').'</li>';
            }
        }

        return $h.'</ul>';
    }

    public function render()
    {
        return <<<'BLADE'
            <aside {{ $attributes->class(['dl-sm', 'dl-sm--'.$side, 'is-mini' => $collapsed]) }} {!! $dl() !!} data-dl-side-menu data-id="{{ $id }}">
                <div class="dl-sm__head">
                    <b class="dl-sm__title">{{ $title }}</b>
                    <button type="button" class="dl-sm__mini" data-sm-mini title="{{ \Devlib\Lang::text('Collapse / expand') }}" aria-label="{{ \Devlib\Lang::text('Collapse menu') }}"><i class="fa-solid fa-angles-{{ $side === 'right' ? 'right' : 'left' }}"></i></button>
                </div>
                @if ($search)<div class="dl-sm__search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="{{ \Devlib\Lang::text('Search menu…') }}" data-sm-q aria-label="{{ \Devlib\Lang::text('Search menu') }}"></div>@endif
                <div class="dl-sm__scroll">
                    @if ($favorites)
                        <div class="dl-sm__extra" data-sm-fav hidden><div class="dl-sm__cap"><i class="fa-solid fa-star"></i> <span class="dl-sm__t">{{ \Devlib\Lang::text('Favourites') }}</span></div><ul class="dl-sm__ul" data-sm-favlist></ul></div>
                        <div class="dl-sm__extra" data-sm-recent hidden><div class="dl-sm__cap"><i class="fa-solid fa-clock-rotate-left"></i> <span class="dl-sm__t">{{ \Devlib\Lang::text('Recently opened') }}</span></div><ul class="dl-sm__ul" data-sm-recentlist></ul></div>
                    @endif
                    <nav data-sm-tree>{!! $tree($items) !!}</nav>
                </div>
                <div class="dl-sm__fly" data-sm-fly hidden></div>
            </aside>
            BLADE;
    }
}
