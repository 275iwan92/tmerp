<?php

namespace Devlib\Components;

use Devlib\Icon;

// Menu panel kiri berlapis (layer 1 modul -> layer 2 menu -> layer 3 sub -> layer 4 ...), SEMUA di panel yang sama:
// klik cabang = panel bergeser ke layer berikutnya, Back = naik satu layer. Tiap layer punya judul (tanpa breadcrumb:
// breadcrumb hanya di layar), angka = jumlah layar di bawah cabang. Kedalaman bebas.
// Kepala panel: baris 1 logo + nama aplikasi (title), baris 2 ikon (tukar view | cari | kecilkan menu).
//   search      = tampilkan ikon cari -> event dl:layer-menu-action {key:'palette'}
//   collapsible = ikon kecilkan menu: panel jadi satu lajur ikon (diingat per browser). Saat mini, klik cabang /
//                 Back / avatar = menu lengkap melayang di atas layar, menutup setelah layar dipilih atau klik di luar.
// items: [['label','icon'?,'key'?,'url'?,'badge'?,'tone'?,'group'?,'items' => [ITEM..]]] — `group` = judul kelompok
// di layer itu (item berurutan dengan group sama tampil di bawah satu judul). Tanpa `items` = layar akhir.
// Klik layar akhir -> event dl:layer-menu {key,label,url,path} (cancelable). Posisi layer terakhir diingat per browser.
//
// Baris pengguna (prop user) + roda gigi = menu kecil yang naik dari bawah: Chat, Profile, lalu `actions`.
// Chat & Profile naik dari bawah MENUTUPI menu, lebar panel tetap. Selama chat terbuka, kepala panel punya
// tombol tukar (menu <-> chat) dan tombol dok (chat pindah ke sisi kanan layar, menu tetap di kiri).
//   slot chat    = satu x-dl::chat compact (elemen yang sama dipindah kiri <-> dok, percakapan tidak hilang)
//   slot profile = <section data-lm-page="kunci" data-label=".." data-icon="fa-..">isi</section> per halaman profil
//   dock         = selector wadah dok kanan di layout (kosong = laci tetap di tepi kanan layar)
//   actions      = [['key','label','icon','tone'?,'confirm'?]]: kunci = halaman profil -> buka halaman itu,
//                  selain itu -> event dl:layer-menu-action {key} (didahului konfirmasi bila `confirm` diisi).
//   views        = [['key','label','icon','items' => [ITEM..]]]: beberapa akar menu yang ditukar lewat ikon kecil di kepala
//                  panel (mis. Operasional | Laporan Manajemen). Diisi = `items` diabaikan. `initial-view` = key view awal (nama `view` dipakai Laravel sendiri).
class LayerMenu extends DevlibComponent
{
    public function __construct(
        public array $items = [],
        public string $title = 'Telasih Makmur',
        public string $logo = 'TM',
        public string $id = 'layer',
        public ?string $active = null,
        public ?string $user = null,
        public ?string $role = null,
        public ?string $login = null,
        public bool $counts = true,
        public string $rootCaption = 'Modules',
        public ?string $dock = null,
        public array $actions = [],
        public array $views = [],
        public ?string $initialView = null,
        public bool $search = false,
        public bool $collapsible = true,
    ) {}

    public function tag(): string
    {
        return 'x-dl::layer-menu';
    }

    // Ikon diterjemahkan di server (arti -> kelas FA) supaya JS cukup menaruh kelasnya.
    public function tree(?array $items = null): array
    {
        return array_map(function ($it) {
            $n = ['label' => $it['label'], 'icon' => $this->fa($it['icon'] ?? 'fa-circle-dot')];
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

    public function viewData(): array
    {
        return array_map(fn ($v) => ['key' => $v['key'], 'label' => $v['label'], 'icon' => $this->fa($v['icon'] ?? 'fa-circle-dot'), 'items' => $this->tree($v['items'])], $this->views);
    }

    public function fa(string $icon): string
    {
        $icon = Icon::fa($icon);

        return str_contains($icon, ' ') ? $icon : 'fa-solid '.$icon;
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim((string) $this->user)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    }

    public function render()
    {
        return <<<'BLADE'
            <aside {{ $attributes->class('dl-lm') }} {!! $dl() !!} data-dl-layer-menu data-id="{{ $id }}" data-active="{{ $active }}" data-counts="{{ $counts ? 1 : 0 }}" data-root="{{ $rootCaption }}" data-dock="{{ $dock }}" data-tree="{{ $views ? '[]' : json_encode($tree()) }}" data-views="{{ json_encode($viewData()) }}" data-view="{{ $initialView }}">
                <div class="dl-lm__head">
                    <span class="dl-lm__logo">{{ $logo }}</span>
                    <span class="dl-lm__brand"><b>{{ $title }}</b><small data-lm-mode hidden></small></span>
                </div>
                <div class="dl-lm__bar">
                    <span class="dl-lm__views" data-lm-views role="group" aria-label="{{ \Devlib\Lang::text('Choose a menu') }}" hidden></span>
                    <span class="dl-lm__gap"></span>
                    <button type="button" class="dl-lm__ib" data-lm-swap title="Tukar menu / chat" aria-label="{{ \Devlib\Lang::text('Swap menu and chat') }}" hidden><i class="fa-solid fa-right-left"></i><em class="dl-lm__dot-badge" data-lm-unread="swap" hidden></em></button>
                    <button type="button" class="dl-lm__ib" data-lm-dockbtn title="{{ \Devlib\Lang::text('Chat on the right side of the screen') }}" aria-label="{{ \Devlib\Lang::text('Move chat to the right') }}" hidden><i class="fa-solid fa-table-columns"></i></button>
                    @if ($search)
                        <button type="button" class="dl-lm__ib" data-lm-search title="{{ \Devlib\Lang::text('Search (Ctrl K)') }}" aria-label="{{ \Devlib\Lang::text('Search (Ctrl K)') }}"><i class="fa-solid fa-magnifying-glass"></i></button>
                    @endif
                    @if ($collapsible)
                        <button type="button" class="dl-lm__ib" data-lm-mini data-t-min="{{ \Devlib\Lang::text('Minimize menu') }}" data-t-max="{{ \Devlib\Lang::text('Expand menu') }}" title="{{ \Devlib\Lang::text('Minimize menu') }}" aria-label="{{ \Devlib\Lang::text('Minimize menu') }}" aria-pressed="false"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="2.5"/><path d="M9.5 4v16"/></svg></button>
                    @endif
                </div>
                <div class="dl-lm__modes">
                    <div class="dl-lm__vp"><div class="dl-lm__track" data-lm-track></div></div>
                    @isset($chat)
                        <div class="dl-lm__ov" data-lm-ov="chat"><div class="dl-lm__chathost" data-lm-chathost>{{ $chat }}</div></div>
                    @endisset
                    @isset($profile)
                        <div class="dl-lm__ov" data-lm-ov="profile">
                            <div class="dl-lm__vp"><div class="dl-lm__track" data-lm-ptrack>
                                <div class="dl-lm__pane">
                                    <div class="dl-lm__ovbar"><button type="button" class="dl-lm__ib" data-lm-close aria-label="{{ \Devlib\Lang::text('Close') }}"><i class="fa-solid fa-xmark"></i></button></div>
                                    <div class="dl-lm__me"><span class="dl-lm__avatar is-lg">{{ $initials() }}</span><b>{{ $user }}</b><small>{{ collect([$login, $role])->filter()->implode(' · ') }}</small></div>
                                    <div data-lm-plist></div>
                                </div>
                                <div class="dl-lm__pane" data-lm-pdet></div>
                            </div></div>
                            <div hidden data-lm-pages>{{ $profile }}</div>
                        </div>
                    @endisset
                </div>
                @if ($user)
                    <div class="dl-lm__pop" data-lm-pop role="menu">
                        @isset($chat)<button type="button" class="dl-lm__a" data-lm-go="chat"><span class="dl-lm__ico"><i class="fa-solid fa-comment-dots"></i></span><span class="dl-lm__t">Chat</span><em class="dl-lm__badge is-danger" data-lm-unread="pop" hidden></em></button>@endisset
                        @isset($profile)<button type="button" class="dl-lm__a" data-lm-go="profile"><span class="dl-lm__ico"><i class="fa-solid fa-circle-user"></i></span><span class="dl-lm__t">Profile</span></button>@endisset
                        @if ($actions)<div class="dl-lm__sep"></div>@endif
                        @foreach ($actions as $a)
                            <button type="button" class="dl-lm__a{{ ($a['tone'] ?? null) === 'danger' ? ' is-danger' : '' }}" data-lm-act="{{ $a['key'] }}" data-label="{{ $a['label'] }}" data-icon="{{ $fa($a['icon'] ?? 'fa-circle-dot') }}" @if (! empty($a['confirm'])) data-confirm="{{ $a['confirm'] }}" @endif><span class="dl-lm__ico"><i class="{{ $fa($a['icon'] ?? 'fa-circle-dot') }}"></i></span><span class="dl-lm__t">{{ $a['label'] }}</span></button>
                        @endforeach
                    </div>
                    <div class="dl-lm__foot" data-lm-foot>
                        <span class="dl-lm__avatar" title="{{ $user }}">{{ $initials() }}</span>
                        <span class="dl-lm__user"><b>{{ $user }}</b>@if ($role)<small>{{ $role }}</small>@endif</span>
                        <button type="button" class="dl-lm__ib" data-lm-gear title="Pengaturan" aria-label="Pengaturan" aria-haspopup="menu"><i class="fa-solid fa-gear"></i><em class="dl-lm__dot-badge" data-lm-unread="gear" hidden></em></button>
                    </div>
                @endif
            </aside>
            BLADE;
    }
}
