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
    $bins['L-INK-R01-01-2'] = $bin('L-INK-R01-01-2', 'IN_USE', [['code' => 'INK-WH', 'name' => 'White Ink', 'qty' => '40 kg', 'idle' => '335 days', 'dead' => true]], 13, true, 'Level 2');
    $floors = [
        ['no' => 1, 'label' => 'Floor 1', 'zones' => [
            ['id' => 1, 'code' => 'RCV', 'name' => 'Receiving', 'tone' => 'violet', 'x' => 0.5, 'y' => 12, 'w' => 6, 'h' => 7.5, 'cells' => [['x' => 0.9, 'y' => 13, 'w' => 5.2, 'h' => 6.1, 'label' => 'RCV', 'bins' => ['L-RCV']]]],
            ['id' => 2, 'code' => 'PPR', 'name' => 'Plano Paper', 'tone' => 'accent', 'x' => 0.5, 'y' => 0.5, 'w' => 13.5, 'h' => 10.5,
                'cells' => $cells('L-PPR', 0.9, 1.5, 6, 5, ['1-1' => 'IN_USE', '1-2' => 'FULL', '2-1' => 'OVER', '2-3' => 'IN_USE', '3-1' => 'DEAD', '4-2' => 'IN_USE', '6-5' => 'BLOCKED'])],
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
        :outline="[[0, 0], [20, 0], [20, 8], [30, 8], [30, 20], [0, 20]]" :floors="$floors" :bins="$bins" selected="L-PPR-L03-01"
        :actions="[['label' => 'Transfer', 'icon' => 'fa-right-left', 'tone' => 'primary', 'when' => 'stock', 'url' => '#transfer-{code}'], ['label' => 'Label', 'icon' => 'fa-qrcode', 'url' => '#label-{code}']]" />
</div>
