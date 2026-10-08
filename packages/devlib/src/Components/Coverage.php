<?php

namespace Devlib\Components;

// Matriks kelengkapan: satu baris per kelompok (mis. set mesin), kolom = jenis dokumen / item, tiap sel = chip status yang
// bisa diklik. Menjawab "apa yang belum lengkap" sekali lihat; panah di baris membuka rincian (daftar kelompok teks).
// columns: [kunci => label] ; rows: [['id','code','title','sub','cells' => [kunci => ['label','tone','url'?,'icon'?]|null],
//   'detail' => [['title','items' => [teks],'wide'? => bool (rentang penuh, daftar 2 kolom)]], 'flags' => [kunci filter]]] ; filters: [kunci flag => label] (chip + jumlah).
// Cari teks + chip filter jalan di browser (coverage.js). Teks di sel (status) selalu English; judul / sub / rincian = data penelepon.
class Coverage extends DevlibComponent
{
    public function __construct(
        public array $columns = [],
        public array $rows = [],
        public array $filters = [],
        public string $head = 'Item',
        public string $subHead = '',
        public string $search = 'Search…',
        public string $empty = 'Nothing matches.',
        public ?string $source = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::coverage';
    }

    public function source(): string
    {
        return $this->source ?? 'penelepon (baris + status per sel)';
    }

    public function jsClass(): ?string
    {
        return 'Coverage';
    }

    public function render()
    {
        return <<<'BLADE'
            @php($count = fn ($k) => collect($rows)->filter(fn ($r) => in_array($k, $r['flags'] ?? [], true))->count())
            <div {{ $attributes->class('dl-cov') }} {!! $dl() !!} data-dl-cov>
                <div class="dl-cov__bar">
                    <div class="dl-grid__search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="{{ $search }}" data-cv-q aria-label="{{ $search }}"></div>
                    <button type="button" class="dl-chipbtn is-active" data-cv-filter="">{{ \Devlib\Lang::text('All') }} <b>{{ count($rows) }}</b></button>
                    @foreach ($filters as $k => $label)<button type="button" class="dl-chipbtn" data-cv-filter="{{ $k }}">{{ $label }} <b>{{ $count($k) }}</b></button>@endforeach
                </div>
                <div class="dl-cov__scroll"><table class="dl-cov__table">
                    <thead><tr><th class="dl-cov__arrow"></th><th>{{ $head }}</th>@if ($subHead !== '')<th>{{ $subHead }}</th>@endif @foreach ($columns as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
                    <tbody>
                    @foreach ($rows as $r)
                        <tr class="dl-cov__row" data-cv-row data-flags="{{ implode(' ', $r['flags'] ?? []) }}" data-text="{{ mb_strtolower(($r['code'] ?? '').' '.($r['title'] ?? '').' '.($r['sub'] ?? '')) }}">
                            <td class="dl-cov__arrow">@if (! empty($r['detail']))<button type="button" class="dl-cov__toggle" data-cv-toggle aria-expanded="false" aria-label="{{ \Devlib\Lang::text('Details') }}"><i class="fa-solid fa-chevron-right"></i></button>@endif</td>
                            <td class="dl-cov__title">@if (! empty($r['code']))<b>{{ $r['code'] }}</b> @endif{{ $r['title'] ?? '' }}</td>
                            @if ($subHead !== '')<td class="dl-cov__sub">{{ $r['sub'] ?? '' }}</td>@endif
                            @foreach (array_keys($columns) as $k)
                                @php($c = $r['cells'][$k] ?? null)
                                <td>
                                @if ($c)
                                    @if (! empty($c['url']))<a href="{{ $c['url'] }}" class="dl-badge dl-badge--{{ $c['tone'] ?? 'neutral' }} dl-badge--pill dl-cov__chip">@if (! empty($c['icon']))<i class="fa-solid {{ \Devlib\Icon::fa($c['icon']) }}"></i>@endif{{ $c['label'] }}</a>
                                    @else<span class="dl-badge dl-badge--{{ $c['tone'] ?? 'neutral' }} dl-badge--pill">@if (! empty($c['icon']))<i class="fa-solid {{ \Devlib\Icon::fa($c['icon']) }}"></i>@endif{{ $c['label'] }}</span>@endif
                                @else<span class="dl-cov__none">—</span>@endif
                                </td>
                            @endforeach
                        </tr>
                        @if (! empty($r['detail']))
                        <tr class="dl-cov__detail" data-cv-detail hidden><td></td><td colspan="{{ count($columns) + ($subHead !== '' ? 2 : 1) }}"><div class="dl-cov__groups">
                            @foreach ($r['detail'] as $g)<div class="dl-cov__group{{ ! empty($g['wide']) ? ' dl-cov__group--wide' : '' }}"><h4>{{ $g['title'] }}</h4><ol>@foreach ($g['items'] as $it)<li>{{ $it }}</li>@endforeach</ol></div>@endforeach
                        </div></td></tr>
                        @endif
                    @endforeach
                    </tbody>
                </table></div>
                <p class="dl-cov__empty" data-cv-empty hidden>{{ $empty }}</p>
            </div>
            BLADE;
    }
}
