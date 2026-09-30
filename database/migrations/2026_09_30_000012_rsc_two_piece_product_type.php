<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Regular Slotted Carton 2-Piece (FEFCO 0201, user 2026-09-30): 1 part = setengah RSC -> panel L + panel W + lidah lem,
// flap atas/bawah = W/2 + tambahan; 2 part identik direkatkan 2 sisi (Double Join), mis. karton aki KS Power N120 MF.
// Tidak ada file dieline siap pakai di internet (boxshot/boxlab/packdroid hanya generator online), jadi ditulis sendiri
// sebagai DSL TMDieline; renderer TIDAK diubah. Ukur ulang dari proof DS003637: 35 + 507 + 187 = 729, 267 + 2*(93,5+5) = 464.
// Semua var hanya memakai parameter/alias: kolom JSON MySQL mengurutkan ulang key, var tidak boleh saling merujuk.
return new class extends Migration
{
    private const CODE = 'RSC_2PC';

    private const SPEC = [
        'gen' => 'RSC', 'style' => 'RSC_2PC',
        'vars' => [
            'W' => 'G+A+B', 'H' => 'C+B+2*flap_extra',
            'x1' => 'G', 'x2' => 'G+A', 'x3' => 'G+A+B', 'sL' => 'G+A-3', 'sR' => 'G+A+3',
            'yT' => 'B/2+flap_extra', 'yB' => 'B/2+flap_extra+C',
            'ga' => 'min(10, G/3)',
            'hw' => 'min(hand_hole_width, B-30)', 'hh' => 'min(30, hand_hole_width)',
            'hx' => 'G+A+B/2-min(hand_hole_width, B-30)/2', 'hy' => 'B/2+flap_extra+35',
        ],
        'flat' => ['w' => 'G+A+B+2*P', 'h' => 'C+B+2*flap_extra+2*P'],
        'canvas' => ['w' => 'W', 'h' => 'H'],
        'elements' => [
            ['t' => 'bleed', 'k' => 'BLEED', 'p' => 'P'],
            // Lipatan panel + garis flap (slot 6 mm di antara flap L dan W).
            ['t' => 'line', 'k' => 'CREASE', 'x1' => 'x1', 'y1' => 'yT', 'x2' => 'x1', 'y2' => 'yB'],
            ['t' => 'line', 'k' => 'CREASE', 'x1' => 'x2', 'y1' => 'yT', 'x2' => 'x2', 'y2' => 'yB'],
            ['t' => 'line', 'k' => 'CREASE', 'x1' => 'x1', 'y1' => 'yT', 'x2' => 'sL', 'y2' => 'yT'],
            ['t' => 'line', 'k' => 'CREASE', 'x1' => 'sR', 'y1' => 'yT', 'x2' => 'x3', 'y2' => 'yT'],
            ['t' => 'line', 'k' => 'CREASE', 'x1' => 'x1', 'y1' => 'yB', 'x2' => 'sL', 'y2' => 'yB'],
            ['t' => 'line', 'k' => 'CREASE', 'x1' => 'sR', 'y1' => 'yB', 'x2' => 'x3', 'y2' => 'yB'],
            ['t' => 'glue', 'k' => 'CUT', 'x0' => '0', 'x1' => 'x1', 'yT' => 'yT', 'yB' => 'yB', 'g' => 'ga'],
            // Flap atas L & W.
            ['t' => 'line', 'k' => 'CUT', 'x1' => 'x1', 'y1' => 'yT', 'x2' => 'x1', 'y2' => '0'],
            ['t' => 'line', 'k' => 'CUT', 'x1' => 'x1', 'y1' => '0', 'x2' => 'sL', 'y2' => '0'],
            ['t' => 'line', 'k' => 'CUT', 'x1' => 'sL', 'y1' => '0', 'x2' => 'sL', 'y2' => 'yT'],
            ['t' => 'line', 'k' => 'CUT', 'x1' => 'sL', 'y1' => 'yT', 'x2' => 'sR', 'y2' => 'yT'],
            ['t' => 'line', 'k' => 'CUT', 'x1' => 'sR', 'y1' => 'yT', 'x2' => 'sR', 'y2' => '0'],
            ['t' => 'line', 'k' => 'CUT', 'x1' => 'sR', 'y1' => '0', 'x2' => 'x3', 'y2' => '0'],
            ['t' => 'line', 'k' => 'CUT', 'x1' => 'x3', 'y1' => '0', 'x2' => 'x3', 'y2' => 'H'],
            // Flap bawah L & W.
            ['t' => 'line', 'k' => 'CUT', 'x1' => 'x1', 'y1' => 'yB', 'x2' => 'x1', 'y2' => 'H'],
            ['t' => 'line', 'k' => 'CUT', 'x1' => 'x1', 'y1' => 'H', 'x2' => 'sL', 'y2' => 'H'],
            ['t' => 'line', 'k' => 'CUT', 'x1' => 'sL', 'y1' => 'H', 'x2' => 'sL', 'y2' => 'yB'],
            ['t' => 'line', 'k' => 'CUT', 'x1' => 'sL', 'y1' => 'yB', 'x2' => 'sR', 'y2' => 'yB'],
            ['t' => 'line', 'k' => 'CUT', 'x1' => 'sR', 'y1' => 'yB', 'x2' => 'sR', 'y2' => 'H'],
            ['t' => 'line', 'k' => 'CUT', 'x1' => 'sR', 'y1' => 'H', 'x2' => 'x3', 'y2' => 'H'],
            // Lubang pegangan di panel W (lebar 0 = tanpa lubang).
            ['t' => 'rect', 'k' => 'CUT', 'x' => 'hx', 'y' => 'hy', 'w' => 'hw', 'h' => 'hh'],
        ],
    ];

    public function up(): void
    {
        $mst = DB::connection('tm2mst');
        if ($mst->table('prod_type')->where('type_code', self::CODE)->exists()) {
            return;
        }
        $now = now();
        $mst->table('prod_type')->insert([
            'type_code' => self::CODE, 'type_name' => 'Regular Slotted Carton 2-Piece (FEFCO 0201)', 'category' => 'PACKAGING', 'sub_category' => 'SLOTTED',
            'box_style' => 'RSC', 'dieline_spec' => json_encode(self::SPEC), 'part_type_default' => 'DC+SHEET', 'has_multi_part' => 0,
            'sort_order' => (int) $mst->table('prod_type')->where('sort_order', '<', 999)->max('sort_order') + 1, 'spec_version' => 1, 'data_ready' => 1,
            'notes' => 'Karton slot 2 bagian: tiap part = panel L + W + lidah lem, 2 part identik dilem 2 sisi (Double Join). Mis. karton aki.',
            'activate' => 1, 'created_at' => $now, 'updated_at' => $now, 'created_by' => 'system',
        ]);
        $num = fn ($key, $label, $default, $seq, $min = 1) => ['type_code' => self::CODE, 'param_key' => $key, 'param_label' => $label, 'param_type' => 'NUM',
            'is_required' => 1, 'default_value' => $default, 'min_value' => $min, 'unit' => 'mm', 'seq' => $seq, 'activate' => 1, 'created_at' => $now, 'updated_at' => $now];
        $mst->table('prod_type_param')->insert([
            $num('product_length', 'Length', 300, 10), $num('product_width', 'Width', 150, 20), $num('product_height', 'Height', 200, 30),
            $num('glue_flap', 'Glue Flap', 35, 40), $num('flap_extra', 'Flap Allowance (+W/2)', 5, 50, 0),
            $num('hand_hole_width', 'Hand Hole Width (0 = none)', 0, 60, 0), $num('overflow_margin', 'Bleed', 3, 70, 0),
        ]);
    }

    public function down(): void
    {
        $mst = DB::connection('tm2mst');
        $mst->table('prod_type_param')->where('type_code', self::CODE)->delete();
        $mst->table('prod_type')->where('type_code', self::CODE)->delete();
    }
};
