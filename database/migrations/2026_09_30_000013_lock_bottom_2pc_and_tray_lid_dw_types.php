<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 2 Tipe Produk dari master card Al Samsi (user 2026-09-30), DSL TMDieline ditulis sendiri, renderer TIDAK diubah.
// - LOCK_BOTTOM_2PC: karton 2 bagian, tiap part = panel L + W + lidah lem di KANAN, flap atas W/2 + tambahan,
//   dasar kunci (crash lock) disederhanakan: flap bawah + lipat diagonal. Ukur dari OUTER ALSAMSI CREAM LOCK:
//   375 + 197 + 30 = 602; 96,5 + 255 + 133 = 484,5 (pisau 96,9 x 60,2 cm = 1 set A+B).
// - TRAY_LID_DW: tutup tray dinding ganda (dinding dilipat ke dalam) + lidah kunci + sudut gusset.
//   ALSAMSI EXCLUSIVE TOP: 248 + 4*34 + 2*16,5 = 417 (pisau 83,4 x 70,1 cm = 4 UP).
// Var hanya memakai parameter/alias (MySQL JSON mengurutkan ulang key).
return new class extends Migration
{
    private const TYPES = ['LOCK_BOTTOM_2PC', 'TRAY_LID_DW'];

    private function line(string $k, string $x1, string $y1, string $x2, string $y2): array
    {
        return ['t' => 'line', 'k' => $k, 'x1' => $x1, 'y1' => $y1, 'x2' => $x2, 'y2' => $y2];
    }

    private function lockBottom(): array
    {
        $cut = fn (...$p) => $this->line('CUT', ...$p);
        $crease = fn (...$p) => $this->line('CREASE', ...$p);

        return [
            'gen' => 'RSC', 'style' => 'LOCK_BOTTOM_2PC',
            'vars' => [
                'W' => 'A+B+G', 'H' => 'B/2+flap_extra+C+lock_depth',
                'x1' => 'A', 'x2' => 'A+B', 'x3' => 'A+B+G', 'sL' => 'A-3', 'sR' => 'A+3',
                'yT' => 'B/2+flap_extra', 'yB' => 'B/2+flap_extra+C', 'yK' => 'B/2+flap_extra+C+lock_depth',
                'ga' => 'min(10, G/3)', 'kL' => 'B/2+flap_extra+C+lock_depth*0.55', 'dL' => 'min(A*0.45, lock_depth)', 'dW' => 'min(B*0.45, lock_depth)',
            ],
            'flat' => ['w' => 'A+B+G+2*P', 'h' => 'B/2+flap_extra+C+lock_depth+2*P'],
            'canvas' => ['w' => 'W', 'h' => 'H'],
            'elements' => [
                ['t' => 'bleed', 'k' => 'BLEED', 'p' => 'P'],
                $crease('x1', 'yT', 'x1', 'yB'), $crease('x2', 'yT', 'x2', 'yB'),
                $crease('0', 'yT', 'sL', 'yT'), $crease('sR', 'yT', 'x2', 'yT'),
                $crease('0', 'yB', 'sL', 'yB'), $crease('sR', 'yB', 'x2', 'yB'),
                // Flap atas L & W.
                $cut('0', 'yT', '0', '0'), $cut('0', '0', 'sL', '0'), $cut('sL', '0', 'sL', 'yT'), $cut('sL', 'yT', 'sR', 'yT'),
                $cut('sR', 'yT', 'sR', '0'), $cut('sR', '0', 'x2', '0'), $cut('x2', '0', 'x2', 'yT'),
                // Tepi kiri panel L + lidah lem di kanan panel W.
                $cut('0', 'yT', '0', 'yB'),
                $cut('x2', 'yT', 'x3', 'yT+ga'), $cut('x3', 'yT+ga', 'x3', 'yB-ga'), $cut('x3', 'yB-ga', 'x2', 'yB'),
                // Dasar kunci di bawah L: sisi luar lurus, ujung dalam menyerong; lipat diagonal dari sudut panel.
                $cut('0', 'yB', '0', 'kL'), $cut('0', 'kL', 'x1-dL', 'yK'), $cut('x1-dL', 'yK', 'sL', 'yK'), $cut('sL', 'yK', 'sL', 'yB'),
                $cut('sL', 'yB', 'sR', 'yB'),
                $crease('sL', 'yB', 'x1-dL', 'yK'),
                // Dasar kunci di bawah W: lidah pengunci menyerong ke kanan; lipat diagonal.
                $cut('sR', 'yB', 'sR', 'yK'), $cut('sR', 'yK', 'x2-dW', 'yK'), $cut('x2-dW', 'yK', 'x2', 'kL'), $cut('x2', 'kL', 'x2', 'yB'),
                $crease('sR', 'yB', 'sR+dW', 'yK'),
            ],
        ];
    }

    private function trayLid(): array
    {
        $cut = fn (...$p) => $this->line('CUT', ...$p);
        $crease = fn (...$p) => $this->line('CREASE', ...$p);
        // o = tepi alas; w1 = batas dinding luar/dalam; w2 = batas dinding dalam/lidah kunci.
        $v = [
            'W' => 'A+4*C+2*lock_tab', 'H' => 'B+4*C+2*lock_tab',
            'oL' => '2*C+lock_tab', 'oR' => '2*C+lock_tab+A', 'oT' => '2*C+lock_tab', 'oB' => '2*C+lock_tab+B',
            'aL' => 'C+lock_tab', 'aR' => '3*C+lock_tab+A', 'aT' => 'C+lock_tab', 'aB' => '3*C+lock_tab+B',
            'bL' => 'lock_tab', 'bR' => '4*C+lock_tab+A', 'bT' => 'lock_tab', 'bB' => '4*C+lock_tab+B',
            'ti' => 'min(lock_tab, B/6)',
        ];
        $e = [
            ['t' => 'bleed', 'k' => 'BLEED', 'p' => 'P'],
            ['t' => 'rect', 'k' => 'CREASE', 'x' => 'oL', 'y' => 'oT', 'w' => 'A', 'h' => 'B'],
            // Lipatan dinding luar -> dinding dalam, dinding dalam -> lidah kunci.
            $crease('aL', 'oT', 'aL', 'oB'), $crease('aR', 'oT', 'aR', 'oB'), $crease('oL', 'aT', 'oR', 'aT'), $crease('oL', 'aB', 'oR', 'aB'),
            $crease('bL', 'oT', 'bL', 'oB'), $crease('bR', 'oT', 'bR', 'oB'), $crease('oL', 'bT', 'oR', 'bT'), $crease('oL', 'bB', 'oR', 'bB'),
            // Lidah kunci atas/bawah (trapesium) & kiri/kanan (garis).
            ['t' => 'dust', 'k' => 'CUT', 'a' => 'oL', 'b' => 'oR', 'y' => 'bT', 'h' => 'lock_tab', 'dir' => 'up'],
            ['t' => 'dust', 'k' => 'CUT', 'a' => 'oL', 'b' => 'oR', 'y' => 'bB', 'h' => 'lock_tab', 'dir' => 'down'],
            $cut('bL', 'oT', '0', 'oT+ti'), $cut('0', 'oT+ti', '0', 'oB-ti'), $cut('0', 'oB-ti', 'bL', 'oB'),
            $cut('bR', 'oT', 'W', 'oT+ti'), $cut('W', 'oT+ti', 'W', 'oB-ti'), $cut('W', 'oB-ti', 'bR', 'oB'),
            // Ujung dinding dalam (bebas, dipotong).
            $cut('bL', 'oT', 'aL', 'oT'), $cut('bL', 'oB', 'aL', 'oB'), $cut('bR', 'oT', 'aR', 'oT'), $cut('bR', 'oB', 'aR', 'oB'),
            $cut('oL', 'bT', 'oL', 'aT'), $cut('oR', 'bT', 'oR', 'aT'), $cut('oL', 'bB', 'oL', 'aB'), $cut('oR', 'bB', 'oR', 'aB'),
        ];
        // Sudut gusset: tepi luar dipotong, lipat diagonal dari sudut alas.
        foreach ([['oL', 'oT', 'aL', 'aT'], ['oR', 'oT', 'aR', 'aT'], ['oL', 'oB', 'aL', 'aB'], ['oR', 'oB', 'aR', 'aB']] as [$x, $y, $ax, $ay]) {
            $e[] = $cut($ax, $y, $ax, $ay);
            $e[] = $cut($ax, $ay, $x, $ay);
            $e[] = $crease($x, $y, $ax, $ay);
        }

        return ['gen' => 'TRAY', 'style' => 'TRAY_LID_DW', 'vars' => $v,
            'flat' => ['w' => 'A+4*C+2*lock_tab+2*P', 'h' => 'B+4*C+2*lock_tab+2*P'], 'canvas' => ['w' => 'W', 'h' => 'H'], 'elements' => $e];
    }

    public function up(): void
    {
        $mst = DB::connection('tm2mst');
        $now = now();
        $sort = (int) $mst->table('prod_type')->where('sort_order', '<', 999)->max('sort_order');
        $num = fn ($code, $key, $label, $default, $seq, $min = 1) => ['type_code' => $code, 'param_key' => $key, 'param_label' => $label, 'param_type' => 'NUM',
            'is_required' => 1, 'default_value' => $default, 'min_value' => $min, 'unit' => 'mm', 'seq' => $seq, 'activate' => 1, 'created_at' => $now, 'updated_at' => $now];

        $types = [
            'LOCK_BOTTOM_2PC' => ['Crash Lock Bottom Carton 2-Piece', 'SLOTTED', 'RSC', 'DC+SF', $this->lockBottom(),
                'Karton 2 bagian dasar kunci: tiap part = panel L + W + lidah lem kanan, flap atas + dasar kunci; 2 part dilem (Double Join).', [
                    $num('LOCK_BOTTOM_2PC', 'product_length', 'Length', 300, 10), $num('LOCK_BOTTOM_2PC', 'product_width', 'Width', 150, 20),
                    $num('LOCK_BOTTOM_2PC', 'product_height', 'Height', 200, 30), $num('LOCK_BOTTOM_2PC', 'glue_flap', 'Glue Flap', 30, 40),
                    $num('LOCK_BOTTOM_2PC', 'flap_extra', 'Flap Allowance (+W/2)', 0, 50, -50), $num('LOCK_BOTTOM_2PC', 'lock_depth', 'Lock Bottom Depth', 110, 60),
                    $num('LOCK_BOTTOM_2PC', 'overflow_margin', 'Bleed', 3, 70, 0),
                ]],
            'TRAY_LID_DW' => ['Tray Lid Double Wall', 'TRAY', null, 'DC', $this->trayLid(),
                'Tutup tray dinding ganda: dinding dilipat ke dalam + lidah kunci, sudut gusset.', [
                    $num('TRAY_LID_DW', 'product_length', 'Length', 200, 10), $num('TRAY_LID_DW', 'product_width', 'Width', 150, 20),
                    $num('TRAY_LID_DW', 'product_height', 'Height', 30, 30), $num('TRAY_LID_DW', 'lock_tab', 'Lock Tab', 15, 40),
                    $num('TRAY_LID_DW', 'overflow_margin', 'Bleed', 3, 50, 0),
                ]],
        ];
        foreach ($types as $code => [$name, $sub, $style, $partType, $spec, $notes, $params]) {
            if ($mst->table('prod_type')->where('type_code', $code)->exists()) {
                continue;
            }
            $mst->table('prod_type')->insert([
                'type_code' => $code, 'type_name' => $name, 'category' => 'PACKAGING', 'sub_category' => $sub, 'box_style' => $style,
                'dieline_spec' => json_encode($spec), 'part_type_default' => $partType, 'has_multi_part' => 0, 'sort_order' => ++$sort,
                'spec_version' => 1, 'data_ready' => 1, 'notes' => $notes, 'activate' => 1, 'created_at' => $now, 'updated_at' => $now, 'created_by' => 'system',
            ]);
            $mst->table('prod_type_param')->insert($params);
        }
    }

    public function down(): void
    {
        $mst = DB::connection('tm2mst');
        $mst->table('prod_type_param')->whereIn('type_code', self::TYPES)->delete();
        $mst->table('prod_type')->whereIn('type_code', self::TYPES)->delete();
    }
};
