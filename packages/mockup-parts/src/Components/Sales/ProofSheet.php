<?php

/*
 * REMARK
 * Tujuan       : Lembar ACC Digital / Proof Digital (format Proof Digital DS003637): judul + REVISION NOTE, dieline artwork per part, blok
 *                CUSTOMER / ITEM / Inner SIZE / PRINT COLOUR / DRAWING BY / APPROVED BY. Dokumen cetak, bukan form isian.
 * Sumber hulu  : App\Support\V2\Data\ProductMaster::doc() (kode, nama, dimensi, komponen + artwork + tinta, ACC Digital) dan ::lineage() (REVISION NOTE).
 * Input        : doc (array dokumen produk), revisions (riwayat revisi dari ProductMaster::lineage), digital (baris ACC DIGITAL atau null), inks (daftar tinta unik),
 *                swatch (tinta => warna CSS; hanya data, bukan gaya tulisan tangan), provenance (label sumber data).
 * Output       : <div class="mp-proof"> ... </div> yang dicetak / dibagikan lewat x-dl::doc-share (target .mp-proof); gaya .mp-proof* di mockup-parts.css.
 * Dipakai oleh : resources/views/v2/screens/product-acc.blade.php (zona beku dibuka Jo 13 Okt: hanya markup, isi dokumen tidak berubah).
 * Status       : Aktif.
 */

namespace MockupParts\Components\Sales;

use Devlib\Components\DevlibComponent;

class ProofSheet extends DevlibComponent
{
    public function __construct(
        public array $doc = [],
        public array $revisions = [],
        public ?array $digital = null,
        public array $inks = [],
        public array $swatch = ['CYAN' => '#00a0e3', 'MAGENTA' => '#e5007e', 'YELLOW' => '#ffed00', 'BLACK' => '#1d1d1b'],
        public ?string $provenance = null,
    ) {}

    public function tag(): string
    {
        return 'x-mp::sales.proof-sheet';
    }

    public function source(): string
    {
        return $this->provenance ?? parent::source();
    }

    public function render()
    {
        return <<<'BLADE'
            @php
                $mm = fn ($v) => $v === null ? '—' : \Devlib\Locale::decimal($v * 10, 1).' mm';
            @endphp
            <div {{ $attributes->class('mp-proof') }} {!! $dl() !!}>
                <div class="mp-proof__top">
                    <div class="mp-proof__title">{{ $doc['code'] }}<br>{{ collect($doc['dims'])->map(fn ($v) => $v === null ? '—' : \Devlib\Locale::decimal($v, 2))->implode(' x ') }} cm</div>
                    <table class="mp-proof__rev">
                        <tr><th>@t('NO')</th><th>@t('REVISION NOTE')</th><th>@t('DESIGN NUMBER')</th><th>@t('DATE')</th></tr>
                        @foreach ($revisions as $i => $l)
                            <tr><td>{{ str_pad((string) $i, 2, '0', STR_PAD_LEFT) }}</td><td>{{ $l['note'] }}</td><td>{{ $l['design'] }}</td><td>{{ $l['date'] }}</td></tr>
                        @endforeach
                    </table>
                </div>
                @foreach ($doc['components'] as $c)
                    <div class="mp-proof__art"><span>{{ preg_replace('/^Part\s+/i', '', $c['name']) }}</span>
                        @if ($c['artwork'])<img src="{{ $c['artwork'] }}" alt="Proof {{ $c['name'] }}">@else<div class="mp-partvis__empty">{{ tf('No artwork for :part yet.', ['part' => $c['name']]) }}</div>@endif
                    </div>
                @endforeach
                <table class="mp-proof__block">
                    <tr>
                        <td rowspan="4" class="mp-proof__logo"><b>{{ \App\Support\Branding\Brand::companyName() }}</b><br><small>{{ \App\Support\Branding\Brand::tagline() }}</small>
                            <div class="mp-proof__warn"><b>@t('Attention !!!')</b><br>@t('* The colours on this print out are not the standard for the production process; please check this print out and the attached print colours again.') @t('Mistakes after the ACC are not our responsibility.')<br>@t('* The unit of measure in this layout is millimetres (mm) unless stated otherwise.')</div></td>
                        <th>@t('CUSTOMER')</th><th>@t('ITEM')</th><th>@t('Inner SIZE')</th><th>@t('APPROVED BY')</th><th>@t('APPROVED BY')</th><th>@t('INQUIRE & APPROVED BY')</th><th>@t('NOTE')</th>
                    </tr>
                    <tr><td rowspan="2" class="mp-proof__big">{{ $doc['customer'] }}</td><td rowspan="2" class="mp-proof__big">{{ $doc['name'] }}</td>
                        <td class="mp-proof__mono">L : {{ $mm($doc['dims']['p']) }}<br>W : {{ $mm($doc['dims']['l']) }}<br>H : {{ $mm($doc['dims']['t']) }}</td>
                        <td rowspan="3" class="mp-proof__sign"></td><td rowspan="3" class="mp-proof__sign"></td>
                        <td rowspan="3" class="mp-proof__sign">@if ($digital){{ $digital['by'] }}<br><small>{{ $digital['at'] }} · {{ $digital['channel'] }}</small>@endif</td><td rowspan="3"></td></tr>
                    <tr><th>@t('DRAWING BY')</th></tr>
                    <tr><td class="mp-proof__mono"><small>@t('DATE')</small> {{ optional($doc['design_date'])->format('d/m/Y') ?? '—' }}<br><small>@t('DESIGN NUMBER')</small> {{ $doc['design_no'] ?? '—' }}</td>
                        <td><small>@t('PRINT COLOUR')</small><br>@foreach ($inks as $k)<i class="mp-proof__sw" style="background:{{ $swatch[$k] ?? '#999' }}" title="{{ $k }}"></i>@endforeach {{ implode(' · ', $inks) ?: '—' }}</td>
                        <td class="mp-proof__big">{{ $doc['drawing_by'] ?? '—' }}</td></tr>
                    <tr><td colspan="4"></td><td class="mp-proof__cap">@t('QA')</td><td class="mp-proof__cap">@t('PREPRESS')</td><td class="mp-proof__cap">@t('CUSTOMER')</td><td></td></tr>
                </table>
                <p class="mp-proof__red">@t('ACC PROOF PRINT (DIGITAL + PRINTED) MUST BE ATTACHED TO THE MASTER CARD')</p>
            </div>
            BLADE;
    }
}
