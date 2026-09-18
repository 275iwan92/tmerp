{{--
    Ilustrasi imposition dari layout ProDev ASLI (prod_layout_group.layout_detail) --
    fitur yang program HPP lama tidak punya (arahan user 2026-09-18). Gaya mengikuti
    mockup ERP_Product_Costing_Mockup.html: plano putih, color-bar kalibrasi, crop mark,
    tiap blank = kotak biru "ART". Dua format layout_detail didukung:
    optimizer {canvas_l_cm, canvas_w_cm, objects[x,y,w,h cm]} & engine grid {layout: cols, rows, iw, ih mm}.
    Props: group (ProdLayoutGroup), geo (hasil ProCostContextService::layoutGeometry).
--}}
@props(['group', 'geo'])
@php
    $d = $group->layout_detail ?? [];
    $pl = max(1, (float) $geo['plano_l']);
    $pw = max(1, (float) $geo['plano_w']);
    $scale = 520 / max($pl, $pw);
    $W = $pl * $scale;
    $H = $pw * $scale;
    $ox = 40;
    $oy = 40;
    $blanks = [];
    $sheets = [];
    if (! empty($d['objects'])) {
        $cl = (float) ($d['canvas_l_cm'] ?? $pl);
        $cw = (float) ($d['canvas_w_cm'] ?? $pw);
        $nx = max(1, (int) floor($pl / max($cl, 0.01)));
        $ny = max(1, (int) floor($pw / max($cw, 0.01)));
        for ($i = 0; $i < $nx; $i++) {
            for ($j = 0; $j < $ny; $j++) {
                $sx = $i * $cl;
                $sy = $j * $cw;
                $sheets[] = [$sx, $sy, $cl, $cw];
                foreach ($d['objects'] as $o) {
                    $blanks[] = [$sx + (float) $o['x'], $sy + (float) $o['y'], (float) $o['w'], (float) $o['h']];
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
@endphp
<svg viewBox="0 0 {{ $W + 80 }} {{ $H + 80 }}" class="w-full h-auto" xmlns="http://www.w3.org/2000/svg">
    <rect x="{{ $ox }}" y="{{ $oy }}" width="{{ $W }}" height="{{ $H }}" fill="#ffffff" stroke="#94a3b8" stroke-width="2" />
    <g transform="translate({{ $ox }}, {{ $oy - 24 }})">
        <rect x="6" width="22" height="14" fill="#00FFFF" /><rect x="28" width="22" height="14" fill="#FF00FF" />
        <rect x="50" width="22" height="14" fill="#FFFF00" /><rect x="72" width="22" height="14" fill="#000000" />
        <text x="100" y="11" font-size="10" fill="#64748b" font-family="sans-serif">Color Calibration</text>
    </g>
    @foreach ([[$ox, $oy], [$ox + $W, $oy + $H]] as [$cx, $cy])
        <circle cx="{{ $cx }}" cy="{{ $cy }}" r="8" fill="none" stroke="#000" stroke-width="1" />
        <path d="M {{ $cx - 12 }} {{ $cy }} h 24 M {{ $cx }} {{ $cy - 12 }} v 24" stroke="#000" stroke-width="1" />
    @endforeach
    @foreach ($sheets as [$sx, $sy, $sl, $sw])
        <rect x="{{ $ox + $sx * $scale }}" y="{{ $oy + $sy * $scale }}" width="{{ $sl * $scale }}" height="{{ $sw * $scale }}" fill="none" stroke="#cbd5e1" stroke-width="1.5" stroke-dasharray="8,6" />
    @endforeach
    @foreach ($blanks as [$bx, $by, $bw, $bh])
        <rect x="{{ $ox + $bx * $scale }}" y="{{ $oy + $by * $scale }}" width="{{ $bw * $scale }}" height="{{ $bh * $scale }}" fill="#eff6ff" stroke="#3b82f6" stroke-width="1.2" />
        <text x="{{ $ox + ($bx + $bw / 2) * $scale }}" y="{{ $oy + ($by + $bh / 2) * $scale + 4 }}" font-size="{{ max(8, min(14, $bw * $scale / 5)) }}" text-anchor="middle" fill="#1e3a8a" font-weight="bold" font-family="sans-serif">ART</text>
    @endforeach
    <text x="{{ $ox + $W / 2 }}" y="{{ $oy + $H + 26 }}" text-anchor="middle" fill="#64748b" font-size="12" font-family="sans-serif">Plano {{ (float) $pl }} × {{ (float) $pw }} cm</text>
    @if (empty($blanks))
        <text x="{{ $ox + $W / 2 }}" y="{{ $oy + $H / 2 }}" text-anchor="middle" fill="#94a3b8" font-size="13" font-family="sans-serif">Detail susunan belum tersimpan di ProDev</text>
    @endif
</svg>
