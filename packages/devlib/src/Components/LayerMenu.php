<?php

namespace Devlib\Components;

use Devlib\Icon;

// Menu panel kiri berlapis (layer 1 modul -> layer 2 menu -> layer 3 sub -> layer 4 ...), SEMUA di panel yang sama:
// klik cabang = panel bergeser ke layer berikutnya, Back = naik satu layer. Tiap layer punya judul + breadcrumb,
// indikator layer di kepala panel, angka = jumlah layar di bawah cabang. Kedalaman bebas (indikator ikut tree).
// items: [['label','icon'?,'key'?,'url'?,'badge'?,'tone'?,'group'?,'items' => [ITEM..]]] — `group` = judul kelompok
// di layer itu (item berurutan dengan group sama tampil di bawah satu judul). Tanpa `items` = layar akhir.
// Klik layar akhir -> event dl:layer-menu {key,label,url,path} (cancelable). Posisi layer terakhir diingat per browser.
class LayerMenu extends DevlibComponent
{
    public function __construct(
        public array $items = [],
        public string $title = 'Telasih Makmur',
        public string $subtitle = 'ERP v2',
        public string $logo = 'TM',
        public string $id = 'layer',
        public ?string $active = null,
        public ?string $user = null,
        public bool $counts = true,
        public string $rootCaption = 'Modules',
    ) {}

    public function tag(): string
    {
        return 'x-dl::layer-menu';
    }

    // Ikon diterjemahkan di server (arti -> kelas FA) supaya JS cukup menaruh kelasnya.
    public function tree(?array $items = null): array
    {
        return array_map(function ($it) {
            $n = ['label' => $it['label'], 'icon' => 'fa-solid '.Icon::fa($it['icon'] ?? 'fa-circle-dot')];
            foreach (['key', 'url', 'badge', 'tone', 'group'] as $k) {
                if (isset($it[$k])) {
                    $n[$k] = $it[$k];
                }
            }
            if (! empty($it['items'])) {
                $n['items'] = $this->tree($it['items']);
            } else {
                $n['key'] ??= $it['label'];
            }

            return $n;
        }, $items ?? $this->items);
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim((string) $this->user)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    }

    public function render()
    {
        return <<<'BLADE'
            <aside {{ $attributes->class('dl-lm') }} {!! $dl() !!} data-dl-layer-menu data-id="{{ $id }}" data-active="{{ $active }}" data-counts="{{ $counts ? 1 : 0 }}" data-root="{{ $rootCaption }}" data-tree="{{ json_encode($tree()) }}">
                <div class="dl-lm__head">
                    <span class="dl-lm__logo">{{ $logo }}</span>
                    <span class="dl-lm__brand"><b>{{ $title }}</b><small>{{ $subtitle }}</small></span>
                    <span class="dl-lm__dots" data-lm-dots aria-hidden="true"></span>
                </div>
                <div class="dl-lm__vp"><div class="dl-lm__track" data-lm-track></div></div>
                @if ($user)
                    <div class="dl-lm__foot">
                        <span class="dl-lm__avatar">{{ $initials() }}</span>
                        <span class="dl-lm__user">{{ $user }}</span>
                        {{ $footer ?? '' }}
                    </div>
                @endif
            </aside>
            BLADE;
    }
}
