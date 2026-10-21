<?php

/*
 * REMARK
 * Tujuan       : Ilustrasi imposition BACA-SAJA dari layout ProDev asli (prod_layout_group.layout_detail) di layar ProCost: plano, color bar kalibrasi, crop mark, tiap blank = kotak "ART". Dua format layout_detail: optimizer {canvas_l_cm, canvas_w_cm, objects[x,y,w,h cm]} dan engine grid {layout: cols, rows, iw, ih mm}.
 * Sumber hulu  : prod_layout_group.layout_detail + ukuran plano hasil ProCostContextService::layoutGeometry (angka tidak dihitung ulang di sini, hanya digambar).
 * Input        : detail (array layout_detail), planoL, planoW (cm).
 * Output       : <svg> inline, warna lewat token tema.
 * Dipakai oleh : resources/views/v2/screens/procost.blade.php (accordion Visual Imposition).
 * Status       : Aktif (T10 tahap 3 pindahan komponen v1 costing.imposition-svg, 2026-10-21).
 */

namespace MockupParts\Components\Prodev;

use Devlib\Components\DevlibComponent;
use MockupParts\Src;

class LayoutSheet extends DevlibComponent
{
    public function __construct(
        public array $detail = [],
        public float $planoL = 0,
        public float $planoW = 0,
    ) {}

    public function tag(): string
    {
        return 'x-mp::prodev.layout-sheet';
    }

    public function source(): string
    {
        return Src::of('tm2mst.prod_layout_group.layout_detail');
    }

    // [skala, lebar, tinggi, lembar [[x,y,w,h]...], blank [[x,y,w,h]...]] dalam cm (lembar / blank) dan px (skala).
    public function shapes(): array
    {
        $d = $this->detail;
        $pl = max(1, $this->planoL);
        $pw = max(1, $this->planoW);
        $scale = 520 / max($pl, $pw);
        $blanks = [];
        $sheets = [];
        if (! empty($d['objects'])) {
            $cl = (float) ($d['canvas_l_cm'] ?? $pl);
            $cw = (float) ($d['canvas_w_cm'] ?? $pw);
            $nx = max(1, (int) floor($pl / max($cl, 0.01)));
            $ny = max(1, (int) floor($pw / max($cw, 0.01)));
            for ($i = 0; $i < $nx; $i++) {
                for ($j = 0; $j < $ny; $j++) {
                    $sheets[] = [$i * $cl, $j * $cw, $cl, $cw];
                    foreach ($d['objects'] as $o) {
                        $blanks[] = [$i * $cl + (float) $o['x'], $j * $cw + (float) $o['y'], (float) $o['w'], (float) $o['h']];
                    }
                }
            }
        } elseif (! empty($d['layout'])) {
            $g = $d['layout'];
            $iw = (float) ($g['iw'] ?? 0) / 10;
            $ih = (float) ($g['ih'] ?? 0) / 10;
            $gx = max(0, ($pl - $iw * (int) $g['cols']) / 2);
            $gy = max(0, ($pw - $ih * (int) $g['rows']) / 2);
            for ($i = 0; $i < (int) $g['cols']; $i++) {
                for ($j = 0; $j < (int) $g['rows']; $j++) {
                    $blanks[] = [$gx + $i * $iw, $gy + $j * $ih, $iw, $ih];
                }
            }
        }

        return [$scale, $pl * $scale, $pw * $scale, $sheets, $blanks];
    }

    public function render()
    {
        return <<<'BLADE'
            @php([$scale, $W, $H, $sheets, $blanks] = $shapes())
            @php($ox = 40)
            @php($oy = 40)
            <svg {{ $attributes->class('mp-layout-sheet') }} {!! $dl() !!} viewBox="0 0 {{ $W + 80 }} {{ $H + 80 }}" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:auto;max-height:420px">
                <rect x="{{ $ox }}" y="{{ $oy }}" width="{{ $W }}" height="{{ $H }}" fill="var(--dl-surface)" stroke="var(--dl-border)" stroke-width="2" />
                <g transform="translate({{ $ox }}, {{ $oy - 24 }})">
                    <rect x="6" width="22" height="14" fill="#00FFFF" /><rect x="28" width="22" height="14" fill="#FF00FF" />
                    <rect x="50" width="22" height="14" fill="#FFFF00" /><rect x="72" width="22" height="14" fill="#000000" />
                    <text x="100" y="11" font-size="10" fill="currentColor" font-family="sans-serif">{{ \Devlib\Lang::text('Color Calibration') }}</text>
                </g>
                @foreach ([[$ox, $oy], [$ox + $W, $oy + $H]] as [$cx, $cy])
                    <circle cx="{{ $cx }}" cy="{{ $cy }}" r="8" fill="none" stroke="currentColor" stroke-width="1" />
                    <path d="M {{ $cx - 12 }} {{ $cy }} h 24 M {{ $cx }} {{ $cy - 12 }} v 24" stroke="currentColor" stroke-width="1" />
                @endforeach
                @foreach ($sheets as [$sx, $sy, $sl, $sw])
                    <rect x="{{ $ox + $sx * $scale }}" y="{{ $oy + $sy * $scale }}" width="{{ $sl * $scale }}" height="{{ $sw * $scale }}" fill="none" stroke="var(--dl-border)" stroke-width="1.5" stroke-dasharray="8,6" />
                @endforeach
                @foreach ($blanks as [$bx, $by, $bw, $bh])
                    <rect x="{{ $ox + $bx * $scale }}" y="{{ $oy + $by * $scale }}" width="{{ $bw * $scale }}" height="{{ $bh * $scale }}" fill="var(--dl-chip)" stroke="var(--dl-accent)" stroke-width="1.2" />
                    <text x="{{ $ox + ($bx + $bw / 2) * $scale }}" y="{{ $oy + ($by + $bh / 2) * $scale + 4 }}" font-size="{{ max(8, min(14, $bw * $scale / 5)) }}" text-anchor="middle" fill="currentColor" font-weight="bold" font-family="sans-serif">ART</text>
                @endforeach
                <text x="{{ $ox + $W / 2 }}" y="{{ $oy + $H + 26 }}" text-anchor="middle" fill="currentColor" font-size="12" font-family="sans-serif">{{ \Devlib\Lang::text('Plano') }} {{ (float) $planoL }} × {{ (float) $planoW }} cm</text>
                @if (empty($blanks))
                    <text x="{{ $ox + $W / 2 }}" y="{{ $oy + $H / 2 }}" text-anchor="middle" fill="currentColor" font-size="13" font-family="sans-serif">{{ \Devlib\Lang::text('The layout detail is not saved in ProDev yet') }}</text>
                @endif
            </svg>
            BLADE;
    }
}
