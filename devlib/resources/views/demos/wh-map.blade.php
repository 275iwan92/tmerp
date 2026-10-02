{{-- Contoh x-dl::wh-map: gedung berbentuk huruf L (30 x 20 m, sudut kanan-atas 10 x 8 m dipotong), 2 lantai.
     Data contoh di sini saja (devlib.test tidak punya database bisnis); di aplikasi isinya dari database. --}}
@php
    $bin = fn (string $code, string $status = 'EMPTY', array $items = [], ?float $pct = null, bool $dead = false, ?string $level = null, ?string $note = null) => [
        'code' => $code, 'title' => 'Demo area', 'status' => $status, 'dead' => $dead, 'pct' => $pct, 'level' => $level, 'note' => $note,
        'capacity' => $pct === null ? null : \Devlib\Locale::number($pct * 30).' / '.\Devlib\Locale::number(3000).' kg', 'capacityLabel' => 'Max 3,000 kg · 4 pallets', 'qr' => 'LOC:'.$code, 'items' => $items];
    $paper = ['code' => 'IV-300', 'name' => 'Ivory 300 gsm 79 x 109', 'qty' => '8,000 sheets', 'idle' => '12 days', 'dead' => false];
    $old = ['code' => 'KR-140', 'name' => 'White Kraft 140 gsm 79 x 97', 'qty' => '15,000 sheets', 'idle' => '275 days', 'dead' => true];
    $bins = [];
    $cells = function (string $prefix, float $x, float $y, int $lanes, int $slots, array $fill) use (&$bins, $bin, $paper, $old) {
        $out = [];
        for ($l = 1; $l <= $lanes; $l++) {
            for ($s = 1; $s <= $slots; $s++) {
                $code = sprintf('%s-L%02d-%02d', $prefix, $l, $s);
                $kind = $fill[$l.'-'.$s] ?? 'EMPTY';
                $bins[$code] = match ($kind) {
                    'IN_USE' => $bin($code, 'IN_USE', [$paper], 69),
                    'FULL' => $bin($code, 'FULL', [$paper], 95),
                    'OVER' => $bin($code, 'OVER', [$paper], 108),
                    'DEAD' => $bin($code, 'IN_USE', [$old], 54, true),
                    'BLOCKED' => $bin($code, 'BLOCKED', [], 0, false, null, 'Floor repair'),
                    default => $bin($code),
                };
                $out[] = ['x' => $x + ($l - 1) * 2.1, 'y' => $y + ($s - 1) * 1.8, 'w' => 1.9, 'h' => 1.6, 'label' => sprintf('L%02d-%02d', $l, $s), 'bins' => [$code]];
            }
        }

        return $out;
    };
    $bins['L-RCV'] = $bin('L-RCV', 'IN_USE', [$paper], 11);
    $bins['L-INK-R01-01-1'] = $bin('L-INK-R01-01-1', 'IN_USE', [['code' => 'INK-CY', 'name' => 'Process Cyan', 'qty' => '120 kg', 'idle' => '25 days', 'dead' => false]], 40, false, 'Level 1');
    $bins['L-INK-R01-01-2'] = $bin('L-INK-R01-01-2', 'IN_USE', [['code' => 'INK-WH', 'name' => 'White Ink', 'qty' => '40 kg', 'idle' => '335 days', 'dead' => true,
        'tags' => [['label' => 'Arrived 01-Nov-2025'], ['label' => 'Expires 27-Oct-2026', 'tone' => 'warn'], ['label' => '1 pallets']]]], 13, true, 'Level 2');
    // Beberapa baris kapasitas (berat, volume, luas tapak x tinggi) + tanda kedaluwarsa.
    $bins['L-INK-R01-01-2'] = ['expiry' => true, 'caps' => [['label' => 'Weight', 'value' => '40 / 300 kg', 'pct' => 13], ['label' => 'Volume', 'value' => '0.20 / 3.00 m³', 'pct' => 7],
        ['label' => 'Floor area × height', 'value' => '3.75 m² × 0.80 m'], ['label' => 'Pallets', 'value' => '1']]] + $bins['L-INK-R01-01-2'];
    $cellsPpr = $cells('L-PPR', 0.9, 1.5, 6, 5, ['1-1' => 'IN_USE', '1-2' => 'FULL', '2-1' => 'OVER', '2-3' => 'IN_USE', '3-1' => 'DEAD', '4-2' => 'IN_USE', '6-5' => 'BLOCKED']);
    // Tumpukan (stack): satuan dari ATAS ke bawah, nomor 1 = paling bawah; 'count' = angka di kotak bin; sisa (remnant) dan
    // tertimbun (buried) punya titik + saringan legenda sendiri.
    $unit = fn (int $id, int $no, string $kind, string $name, string $sub, array $tags = [], ?string $tone = null, bool $up = true, bool $down = true) => [
        'id' => $id, 'no' => $no, 'kind' => $kind, 'name' => $name, 'sub' => $sub, 'tags' => $tags, 'tone' => $tone, 'up' => $up, 'down' => $down];
    $bins['L-PPR-L03-01'] = ['count' => 3, 'remnant' => true, 'buried' => true, 'stack' => [
        $unit(3, 3, 'pack', 'Art Paper 150 gsm 79 x 109', '2 reams · Arrived 12-Mar-2026', [['label' => 'Pack'], ['label' => 'Old remnant 205 days', 'tone' => 'danger']], 'danger', false),
        $unit(2, 2, 'pallet', 'White Kraft 140 gsm 79 x 97', 'PL2600031 · 7,500 sheets · Arrived 20-Sep-2026', [['label' => 'Pallet', 'tone' => 'info']]),
        $unit(1, 1, 'pallet', 'White Kraft 140 gsm 79 x 97', 'PL2600012 · 7,500 sheets · Arrived 31-Dec-2025',
            [['label' => 'Pallet', 'tone' => 'info'], ['label' => 'Dead stock', 'tone' => 'danger'], ['label' => 'Buried · 2 above', 'tone' => 'orange']], 'danger', true, false),
    ], 'caps' => [['label' => 'Weight', 'value' => '1,721 / 3,000 kg', 'pct' => 57], ['label' => 'Stack', 'value' => '3 units · 2 pallets · 1 pack'], ['label' => 'Stack height', 'value' => '3.05 / 3.20 m', 'pct' => 95]],
        'note' => 'The unit that should be used first is under 2 other unit(s). Move those first.'] + $bins['L-PPR-L03-01'];
    $bins['L-PPR-L06-04'] = ['count' => 3, 'remnant' => true, 'status' => 'IN_USE', 'pct' => 9, 'capacity' => '270 / 3,000 kg', 'stack' => [
        $unit(13, 3, 'pack', 'Ambri Verge 25 gsm 51 x 76', '400 sheets · Arrived 02-Oct-2026', [['label' => 'Pack'], ['label' => 'Remnant', 'tone' => 'violet']], null, false),
        $unit(12, 2, 'pack', 'Ivory 250 gsm 79 x 109', '350 sheets · Arrived 18-Sep-2026', [['label' => 'Pack'], ['label' => 'Remnant', 'tone' => 'violet']]),
        $unit(11, 1, 'pack', 'Sheet E-Flute 90 x 120', '60 sheets · Arrived 21-Feb-2026', [['label' => 'Pack'], ['label' => 'Old remnant 224 days', 'tone' => 'danger']], 'danger', true, false),
    ], 'items' => [['code' => 'AM-25', 'name' => 'Ambri Verge 25 gsm 51 x 76', 'qty' => '400 sheets', 'idle' => '1 days', 'dead' => false],
        ['code' => 'IV-250', 'name' => 'Ivory 250 gsm 79 x 109', 'qty' => '350 sheets', 'idle' => '15 days', 'dead' => false],
        ['code' => 'SH-E', 'name' => 'Sheet E-Flute 90 x 120', 'qty' => '60 sheets', 'idle' => '224 days', 'dead' => false]]] + $bins['L-PPR-L06-04'];
    $bins['L-PPR-L01-01'] = ['count' => 2] + $bins['L-PPR-L01-01'];
    $bins['L-PPR-L01-02'] = ['count' => 3] + $bins['L-PPR-L01-02'];
    $floors = [
        ['no' => 1, 'label' => 'Floor 1', 'zones' => [
            ['id' => 1, 'code' => 'RCV', 'name' => 'Receiving', 'tone' => 'violet', 'x' => 0.5, 'y' => 12, 'w' => 6, 'h' => 7.5, 'cells' => [['x' => 0.9, 'y' => 13, 'w' => 5.2, 'h' => 6.1, 'label' => 'RCV', 'bins' => ['L-RCV']]]],
            ['id' => 2, 'code' => 'PPR', 'name' => 'Plano Paper', 'tone' => 'accent', 'x' => 0.5, 'y' => 0.5, 'w' => 13.5, 'h' => 10.5,
                'cells' => $cellsPpr],
            ['id' => 3, 'code' => 'INK', 'name' => 'Ink & Chemicals', 'tone' => 'lime', 'x' => 15, 'y' => 9, 'w' => 6, 'h' => 5,
                'cells' => [['x' => 15.4, 'y' => 10, 'w' => 2.4, 'h' => 1.6, 'label' => 'R01-01', 'bins' => ['L-INK-R01-01-1', 'L-INK-R01-01-2']]]],
            ['id' => 4, 'code' => 'SHP', 'name' => 'Shipping', 'tone' => 'green', 'x' => 22, 'y' => 9, 'w' => 7.5, 'h' => 10.5, 'cells' => []],
        ]],
        ['no' => 2, 'label' => 'Floor 2', 'zones' => [
            ['id' => 5, 'code' => 'FG', 'name' => 'Finished Goods', 'tone' => 'accent', 'x' => 1, 'y' => 9, 'w' => 13, 'h' => 10, 'cells' => $cells('L-FG', 1.4, 10, 5, 4, ['1-1' => 'IN_USE', '2-1' => 'DEAD'])],
        ]],
    ];
@endphp
<div class="dl-demo">
    <x-dl::wh-map id="demo-whmap" title="Building L · L shape 30 x 20 m" :width="30" :height="20"
        :outline="[[0, 0], [20, 0], [20, 8], [30, 8], [30, 20], [0, 20]]" :floors="$floors" :bins="$bins" selected="L-PPR-L03-01" restack-url="#restack-{id}-{dir}"
        :actions="[['label' => 'Transfer', 'icon' => 'fa-right-left', 'tone' => 'primary', 'when' => 'stock', 'url' => '#transfer-{code}'], ['label' => 'Label', 'icon' => 'fa-qrcode', 'url' => '#label-{code}']]" />
</div>
