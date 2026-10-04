<?php

namespace Devlib\Components\SideScreen;

use Devlib\Components\Tabs;

// Isi Side Screen (user 2026-10-04): rel bagian VERTIKAL di kiri (menggantikan tab mendatar; 4 bagian Help / 11 bagian
// Profile terbaca sekaligus, boleh dikelompokkan dan berangka), judul bagian lengket di atas isi (nama + satu kalimat
// petunjuk + breadcrumb), isi tiap bagian = slot bernama sesuai kunci (sama dengan Tabs). Lebar sempit (< 900px) = rel
// menyusut jadi ikon. Kotak cari jendela induk menyaring rel + kartu lewat postMessage {dl: 'ss-search'}.
// sections: [kunci => [judul, ikon] | [judul, ikon, angka]]; groups: [kunci => caption] = caption sebelum kunci itu;
// hints: [kunci => kalimat petunjuk]; links: [caption => [[label, ikon, url], ...]] = tautan di bawah rel.
class Layout extends Tabs
{
    public function __construct(
        public array $sections = [],
        public ?string $active = null,
        public array $groups = [],
        public array $hints = [],
        public array $links = [],
        public array $crumbs = [],
        // Nama parameter query yang menyimpan bagian aktif (dipakai form yang kembali ke bagian yang sama).
        public string $param = 'tab',
    ) {
        parent::__construct(tabs: $sections, active: $active, vertical: true);
    }

    public function tag(): string
    {
        return 'x-dl::side-screen.layout';
    }

    // groups / hints: nilainya label (diterjemahkan); links: kuncinya caption, label tautan diterjemahkan di view.
    protected const TEXT_LISTS = ['sections', 'tabs', 'groups', 'hints'];

    protected const TEXT_KEYS = ['links'];

    public function render()
    {
        return <<<'BLADE'
            @php $cur = $active ?? array_key_first($sections); @endphp
            <div {{ $attributes->class(['dl-ssl']) }} {!! $dl() !!} data-dl-ss-layout data-dl-ss-param="{{ $param }}">
                <nav class="dl-ssl__rail" aria-label="{{ \Devlib\Lang::text('Sections') }}">
                    @php $first = true; @endphp
                    @foreach ($sections as $key => $s)
                        @if (isset($groups[$key]))<div class="dl-ssl__cap">{{ $groups[$key] }}</div>@elseif ($first)<div class="dl-ssl__cap">{{ \Devlib\Lang::text('Sections') }}</div>@endif
                        @php $first = false; $s = is_array($s) ? $s : [$s, null]; @endphp
                        <button type="button" class="dl-ssl__nav {{ $key === $cur ? 'is-on' : '' }}" data-dl-ss-nav="{{ $key }}" data-label="{{ $s[0] }}" data-icon="{{ $s[1] ? \Devlib\Icon::fa($s[1]) : '' }}" title="{{ $s[0] }}">
                            <i class="fa-solid {{ $s[1] ? \Devlib\Icon::fa($s[1]) : 'fa-circle' }}"></i><span>{{ $s[0] }}</span>
                            @if (isset($s[2]) && $s[2] !== null && $s[2] !== '')<b class="dl-ssl__n {{ is_numeric($s[2]) && (int) $s[2] > 0 && ($s[3] ?? 'alert') === 'alert' ? 'is-alert' : '' }}">{{ $s[2] }}</b>@endif
                        </button>
                    @endforeach
                    @foreach ($links as $cap => $items)
                        <div class="dl-ssl__cap dl-ssl__cap--links">{{ $cap }}</div>
                        @foreach ($items as $l)
                            <a class="dl-ssl__nav" href="{{ $l[2] }}" title="{{ \Devlib\Lang::text($l[0]) }}" @if (! empty($l[3])) target="{{ $l[3] }}" @endif><i class="fa-solid {{ \Devlib\Icon::fa($l[1]) }}"></i><span>{{ \Devlib\Lang::text($l[0]) }}</span></a>
                        @endforeach
                    @endforeach
                </nav>
                <div class="dl-ssl__body" data-dl-ss-body>
                    <div class="dl-ssl__sec">
                        <i class="fa-solid {{ ($sections[$cur][1] ?? null) ? \Devlib\Icon::fa($sections[$cur][1]) : 'fa-circle' }}" data-dl-ss-sec-icon></i>
                        <div class="dl-ssl__sec-text"><h2 data-dl-ss-sec-title>{{ $sections[$cur][0] ?? $cur }}</h2><p data-dl-ss-sec-hint>{{ $hints[$cur] ?? '' }}</p></div>
                        @if ($crumbs)<div class="dl-ssl__crumb"><x-dl::breadcrumb :items="$crumbs" /></div>@endif
                    </div>
                    {{-- Slot top: pesan flash / error yang berlaku untuk semua bagian. --}}
                    @isset($top)<div class="dl-ssl__top">{{ $top }}</div>@endisset
                    @foreach ($sections as $key => $s)
                        <div class="dl-ssl__panel {{ $key === $cur ? 'is-active' : '' }}" data-dl-ss-panel="{{ $key }}" data-hint="{{ $hints[$key] ?? '' }}">{{ ${$key} ?? '' }}</div>
                    @endforeach
                </div>
            </div>
            BLADE;
    }
}
