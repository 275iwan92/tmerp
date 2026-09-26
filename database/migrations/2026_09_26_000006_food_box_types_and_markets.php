<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Dus makanan (user 2026-09-26): 2 Tipe Produk baru + Product Market + Ukuran Pasaran standar pasar.
// Pola dieline ditulis sendiri sebagai DSL TMDieline (bentuk mengacu Pacdora "mailer / tray with lid" dan
// "lunch box with lid"; file Pacdora perlu akun, tidak diambil). Renderer TMDieline TIDAK diubah.
// Ukuran (sumber pasar): R3 14x10,5x7 | R5 16x12x7 | R6 16x16x7 | R8 18x18x7 | R10 20x20x7 | R10B 22x22x7 cm
// (hqpacks.com, pamanpack.co.id, tokopedia); PPL S/M/L alas + atas + tinggi (kemasanfnb.com, irppapercup.com).
return new class extends Migration
{
    private const RICE = '{"vars":{"TF":"C*0.8","LF":"C*0.9","EAR":"C*0.85","IN":"C*0.9","xL":"C","xR":"C+A","y1":"TF","y2":"TF+B","y3":"TF+B+C","y4":"TF+2*B+C","y5":"TF+2*B+2*C","y6":"TF+2*B+3*C*0.9+C*0.2","W":"A+2*C","H":"TF+2*B+2*C+IN"},"flat":{"w":"A+2*C","h":"TF+2*B+2*C+C*0.9"},"canvas":{"w":"W","h":"H"},"elements":[{"t":"tuck","k":"CUT","a":"xL","b":"xR","y":"y1","h":"TF","dir":"up"},{"t":"line","k":"CREASE","x1":"xL","y1":"y1","x2":"xR","y2":"y1"},{"t":"line","k":"CUT","x1":"xL","y1":"y1","x2":"xL-LF","y2":"y1+LF*0.35"},{"t":"line","k":"CUT","x1":"xL-LF","y1":"y1+LF*0.35","x2":"xL-LF","y2":"y2-LF*0.35"},{"t":"line","k":"CUT","x1":"xL-LF","y1":"y2-LF*0.35","x2":"xL","y2":"y2-2"},{"t":"line","k":"CUT","x1":"xR","y1":"y1","x2":"xR+LF","y2":"y1+LF*0.35"},{"t":"line","k":"CUT","x1":"xR+LF","y1":"y1+LF*0.35","x2":"xR+LF","y2":"y2-LF*0.35"},{"t":"line","k":"CUT","x1":"xR+LF","y1":"y2-LF*0.35","x2":"xR","y2":"y2-2"},{"t":"line","k":"CREASE","x1":"xL","y1":"y1","x2":"xL","y2":"y2"},{"t":"line","k":"CREASE","x1":"xR","y1":"y1","x2":"xR","y2":"y2"},{"t":"line","k":"CREASE","x1":"xL","y1":"y2","x2":"xR","y2":"y2"},{"t":"line","k":"CUT","x1":"xL","y1":"y2","x2":"xL-EAR","y2":"y2+C*0.2"},{"t":"line","k":"CUT","x1":"xL-EAR","y1":"y2+C*0.2","x2":"xL-EAR","y2":"y3-2"},{"t":"line","k":"CUT","x1":"xL-EAR","y1":"y3-2","x2":"xL","y2":"y3-2"},{"t":"line","k":"CUT","x1":"xR","y1":"y2","x2":"xR+EAR","y2":"y2+C*0.2"},{"t":"line","k":"CUT","x1":"xR+EAR","y1":"y2+C*0.2","x2":"xR+EAR","y2":"y3-2"},{"t":"line","k":"CUT","x1":"xR+EAR","y1":"y3-2","x2":"xR","y2":"y3-2"},{"t":"line","k":"CREASE","x1":"xL","y1":"y2","x2":"xL","y2":"y3"},{"t":"line","k":"CREASE","x1":"xR","y1":"y2","x2":"xR","y2":"y3"},{"t":"line","k":"CREASE","x1":"xL","y1":"y3","x2":"xR","y2":"y3"},{"t":"line","k":"CREASE","x1":"xL","y1":"y4","x2":"xR","y2":"y4"},{"t":"line","k":"CREASE","x1":"xL","y1":"y3","x2":"xL","y2":"y4"},{"t":"line","k":"CREASE","x1":"xR","y1":"y3","x2":"xR","y2":"y4"},{"t":"line","k":"CUT","x1":"xL","y1":"y3","x2":"0","y2":"y3"},{"t":"line","k":"CUT","x1":"0","y1":"y3","x2":"0","y2":"y4"},{"t":"line","k":"CUT","x1":"0","y1":"y4","x2":"xL","y2":"y4"},{"t":"line","k":"CUT","x1":"xR","y1":"y3","x2":"W","y2":"y3"},{"t":"line","k":"CUT","x1":"W","y1":"y3","x2":"W","y2":"y4"},{"t":"line","k":"CUT","x1":"W","y1":"y4","x2":"xR","y2":"y4"},{"t":"circle","k":"CUT","cx":"C*0.5","cy":"y3+B*0.25","r":"C*0.12"},{"t":"circle","k":"CUT","cx":"C*0.5","cy":"y4-B*0.25","r":"C*0.12"},{"t":"circle","k":"CUT","cx":"W-C*0.5","cy":"y3+B*0.25","r":"C*0.12"},{"t":"circle","k":"CUT","cx":"W-C*0.5","cy":"y4-B*0.25","r":"C*0.12"},{"t":"line","k":"CUT","x1":"xL","y1":"y4+2","x2":"xL-EAR","y2":"y4+2"},{"t":"line","k":"CUT","x1":"xL-EAR","y1":"y4+2","x2":"xL-EAR","y2":"y5-C*0.2"},{"t":"line","k":"CUT","x1":"xL-EAR","y1":"y5-C*0.2","x2":"xL","y2":"y5"},{"t":"line","k":"CUT","x1":"xR","y1":"y4+2","x2":"xR+EAR","y2":"y4+2"},{"t":"line","k":"CUT","x1":"xR+EAR","y1":"y4+2","x2":"xR+EAR","y2":"y5-C*0.2"},{"t":"line","k":"CUT","x1":"xR+EAR","y1":"y5-C*0.2","x2":"xR","y2":"y5"},{"t":"line","k":"CREASE","x1":"xL","y1":"y4","x2":"xL","y2":"y5"},{"t":"line","k":"CREASE","x1":"xR","y1":"y4","x2":"xR","y2":"y5"},{"t":"line","k":"CREASE","x1":"xL","y1":"y5","x2":"xR","y2":"y5"},{"t":"tuck","k":"CUT","a":"xL+2","b":"xR-2","y":"y5","h":"IN","dir":"down"},{"t":"circle","k":"CUT","cx":"xL+A*0.3","cy":"y4","r":"C*0.25"},{"t":"circle","k":"CUT","cx":"xR-A*0.3","cy":"y4","r":"C*0.25"}]}';

    private const PPL = '{"vars":{"TA":"max(top_length, A)","TB":"max(top_width, B)","dx":"(TA-A)/2","dy":"(TB-B)/2","SL":"sqrt(C*C+dy*dy)","SW":"sqrt(C*C+dx*dx)","TF":"C*0.8","LF":"C*0.7","OX":"max(SW, dx+LF)","xL":"OX","xR":"OX+A","yL0":"TF","yL1":"TF+TB","yb0":"TF+TB+SL","yb1":"TF+TB+SL+B","yE":"TF+TB+2*SL+B","W":"A+2*OX","H":"TF+TB+2*SL+B"},"flat":{"w":"W","h":"H"},"canvas":{"w":"W","h":"H"},"elements":[{"t":"tuck","k":"CUT","a":"xL-dx","b":"xR+dx","y":"yL0","h":"TF","dir":"up"},{"t":"line","k":"CREASE","x1":"xL-dx","y1":"yL0","x2":"xR+dx","y2":"yL0"},{"t":"line","k":"CUT","x1":"xL-dx","y1":"yL0","x2":"xL-dx-LF","y2":"yL0+LF*0.4"},{"t":"line","k":"CUT","x1":"xL-dx-LF","y1":"yL0+LF*0.4","x2":"xL-dx-LF","y2":"yL1-LF*0.4"},{"t":"line","k":"CUT","x1":"xL-dx-LF","y1":"yL1-LF*0.4","x2":"xL-dx","y2":"yL1"},{"t":"line","k":"CUT","x1":"xR+dx","y1":"yL0","x2":"xR+dx+LF","y2":"yL0+LF*0.4"},{"t":"line","k":"CUT","x1":"xR+dx+LF","y1":"yL0+LF*0.4","x2":"xR+dx+LF","y2":"yL1-LF*0.4"},{"t":"line","k":"CUT","x1":"xR+dx+LF","y1":"yL1-LF*0.4","x2":"xR+dx","y2":"yL1"},{"t":"line","k":"CREASE","x1":"xL-dx","y1":"yL0","x2":"xL-dx","y2":"yL1"},{"t":"line","k":"CREASE","x1":"xR+dx","y1":"yL0","x2":"xR+dx","y2":"yL1"},{"t":"line","k":"CREASE","x1":"xL-dx","y1":"yL1","x2":"xR+dx","y2":"yL1"},{"t":"line","k":"CREASE","x1":"xL","y1":"yb0","x2":"xR","y2":"yb0"},{"t":"line","k":"CREASE","x1":"xL","y1":"yb1","x2":"xR","y2":"yb1"},{"t":"line","k":"CREASE","x1":"xL","y1":"yb0","x2":"xL","y2":"yb1"},{"t":"line","k":"CREASE","x1":"xR","y1":"yb0","x2":"xR","y2":"yb1"},{"t":"line","k":"CREASE","x1":"xL","y1":"yb0","x2":"xL-dx","y2":"yL1"},{"t":"line","k":"CREASE","x1":"xR","y1":"yb0","x2":"xR+dx","y2":"yL1"},{"t":"line","k":"CREASE","x1":"xL","y1":"yb1","x2":"xL-dx","y2":"yE"},{"t":"line","k":"CREASE","x1":"xR","y1":"yb1","x2":"xR+dx","y2":"yE"},{"t":"line","k":"CUT","x1":"xL-dx","y1":"yE","x2":"xR+dx","y2":"yE"},{"t":"line","k":"CREASE","x1":"xL","y1":"yb0","x2":"xL-SW","y2":"yb0-dy"},{"t":"line","k":"CREASE","x1":"xL","y1":"yb1","x2":"xL-SW","y2":"yb1+dy"},{"t":"line","k":"CUT","x1":"xL-SW","y1":"yb0-dy","x2":"xL-SW","y2":"yb1+dy"},{"t":"line","k":"CREASE","x1":"xR","y1":"yb0","x2":"xR+SW","y2":"yb0-dy"},{"t":"line","k":"CREASE","x1":"xR","y1":"yb1","x2":"xR+SW","y2":"yb1+dy"},{"t":"line","k":"CUT","x1":"xR+SW","y1":"yb0-dy","x2":"xR+SW","y2":"yb1+dy"},{"t":"line","k":"CUT","x1":"xL-dx","y1":"yL1","x2":"xL-SW","y2":"yL1"},{"t":"line","k":"CUT","x1":"xL-SW","y1":"yL1","x2":"xL-SW","y2":"yb0-dy"},{"t":"line","k":"CREASE","x1":"xL","y1":"yb0","x2":"xL-SW","y2":"yL1"},{"t":"line","k":"CUT","x1":"xR+dx","y1":"yL1","x2":"xR+SW","y2":"yL1"},{"t":"line","k":"CUT","x1":"xR+SW","y1":"yL1","x2":"xR+SW","y2":"yb0-dy"},{"t":"line","k":"CREASE","x1":"xR","y1":"yb0","x2":"xR+SW","y2":"yL1"},{"t":"line","k":"CUT","x1":"xL-dx","y1":"yE","x2":"xL-SW","y2":"yE"},{"t":"line","k":"CUT","x1":"xL-SW","y1":"yE","x2":"xL-SW","y2":"yb1+dy"},{"t":"line","k":"CREASE","x1":"xL","y1":"yb1","x2":"xL-SW","y2":"yE"},{"t":"line","k":"CUT","x1":"xR+dx","y1":"yE","x2":"xR+SW","y2":"yE"},{"t":"line","k":"CUT","x1":"xR+SW","y1":"yE","x2":"xR+SW","y2":"yb1+dy"},{"t":"line","k":"CREASE","x1":"xR","y1":"yb1","x2":"xR+SW","y2":"yE"},{"t":"circle","k":"CUT","cx":"xL+A/2","cy":"yE-SL*0.35","r":"C*0.1"}]}';

    public function up(): void
    {
        $mst = DB::connection('tm2mst');
        $now = now();
        $num = fn ($key, $label, $default, $seq) => ['param_key' => $key, 'param_label' => $label, 'param_type' => 'NUM', 'is_required' => 1,
            'default_value' => $default, 'min_value' => 1, 'unit' => 'mm', 'seq' => $seq, 'activate' => 1, 'created_at' => $now, 'updated_at' => $now];

        $types = [
            'RICE_BOX' => ['Dus Nasi / Rice Box (R-series)', self::RICE, 'Dus nasi & kue kotak: tutup menyatu + tuck, kuping pengunci, dinding depan dobel.', [
                $num('product_length', 'Panjang', 180, 10), $num('product_width', 'Lebar', 180, 20), $num('product_height', 'Tinggi', 70, 30),
            ]],
            'PAPER_LUNCH_BOX' => ['Paper Lunch Box (PPL)', self::PPL, 'Paper lunch box: dinding miring (atas > alas), sudut gusset dilipat, tutup menyatu.', [
                $num('product_length', 'Panjang Alas', 158, 10), $num('product_width', 'Lebar Alas', 90, 20), $num('product_height', 'Tinggi', 50, 30),
                $num('top_length', 'Panjang Atas', 177, 40), $num('top_width', 'Lebar Atas', 110, 50),
            ]],
        ];

        $sort = (int) $mst->table('prod_type')->where('sort_order', '<', 999)->max('sort_order');
        foreach ($types as $code => [$name, $spec, $notes, $params]) {
            if ($mst->table('prod_type')->where('type_code', $code)->exists()) {
                continue;
            }
            $mst->table('prod_type')->insert([
                'type_code' => $code, 'type_name' => $name, 'category' => 'PACKAGING', 'sub_category' => 'TRAY', 'dieline_spec' => $spec,
                'part_type_default' => 'DC', 'has_multi_part' => 0, 'sort_order' => ++$sort, 'spec_version' => 1, 'notes' => $notes,
                'activate' => 1, 'created_at' => $now, 'updated_at' => $now, 'created_by' => 'system',
            ]);
            $mst->table('prod_type_param')->insert(array_map(fn ($p) => $p + ['type_code' => $code], $params));
        }

        $kraft = $mst->table('mat_category as c')->join('mat_category as p', 'p.id_category', '=', 'c.parent_id')
            ->whereNull('p.parent_id')->where('p.category_name', 'Paper')->where('c.category_name', 'Kraft')->value('c.id_category');
        $marketSort = (int) $mst->table('prod_market')->where('sort_order', '<', 999)->max('sort_order');

        // [label, alas P, alas L, tinggi, (atas P, atas L)] dalam mm
        $markets = [
            ['DUS_NASI_KOTAK', 'Dus Nasi Kotak (R-series)', 'RICE_BOX', 'Dus nasi/kue kraft food grade; makanan berminyak pakai kraft lapis PE (XTRU).', [
                ['R3', 140, 105, 70], ['R5', 160, 120, 70], ['R6', 160, 160, 70], ['R8', 180, 180, 70], ['R10', 200, 200, 70], ['R10B', 220, 220, 70],
            ]],
            ['PAPER_LUNCH_BOX', 'Paper Lunch Box (PPL)', 'PAPER_LUNCH_BOX', 'PPL kraft food grade; ukuran = alas x tinggi, atas lebih besar (dinding miring).', [
                ['PPL S', 130, 75, 48, 150, 95], ['PPL M', 158, 90, 50, 177, 110], ['PPL L', 177, 105, 50, 196, 124],
            ]],
        ];
        $cm = fn ($mm) => str_replace('.', ',', (string) ($mm / 10));

        foreach ($markets as [$code, $label, $type, $notes, $sizes]) {
            if ($mst->table('prod_market')->where('market_code', $code)->exists()) {
                continue;
            }
            $marketId = $mst->table('prod_market')->insertGetId([
                'market_code' => $code, 'market_label' => $label, 'type_code' => $type, 'paper_category_id' => $kraft,
                'default_color' => '4/0', 'default_tiers' => '1000,3000,5000', 'notes' => $notes, 'sort_order' => $marketSort += 10,
                'activate' => 1, 'created_at' => $now, 'updated_at' => $now, 'created_by' => 'system',
            ]);

            foreach ($sizes as $i => $s) {
                $dims = ['product_length' => $s[1], 'product_width' => $s[2], 'product_height' => $s[3]]
                    + (isset($s[4]) ? ['top_length' => $s[4], 'top_width' => $s[5]] : []);
                $sizeLabel = $s[0].' ('.$cm($s[1]).' x '.$cm($s[2]).' x '.$cm($s[3]).' cm'.(isset($s[4]) ? ', atas '.$cm($s[4]).' x '.$cm($s[5]) : '').')';
                $sizeId = $mst->table('prod_type_size')->insertGetId([
                    'type_code' => $type, 'market_id' => $marketId, 'size_code' => $code.'_'.str_replace(' ', '_', $s[0]), 'size_label' => $sizeLabel,
                    'sort_order' => ($i + 1) * 10, 'activate' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
                foreach ($dims as $k => $v) {
                    $mst->table('prod_type_size_val')->insert(['type_size_id' => $sizeId, 'param_key' => $k, 'value_num' => $v, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
        }
    }

    public function down(): void
    {
        $mst = DB::connection('tm2mst');
        $markets = $mst->table('prod_market')->whereIn('market_code', ['DUS_NASI_KOTAK', 'PAPER_LUNCH_BOX'])->pluck('id_market');
        $sizes = $mst->table('prod_type_size')->whereIn('market_id', $markets)->pluck('id_type_size');
        $mst->table('prod_type_size_val')->whereIn('type_size_id', $sizes)->delete();
        $mst->table('prod_type_size')->whereIn('id_type_size', $sizes)->delete();
        $mst->table('prod_market')->whereIn('id_market', $markets)->delete();
        $mst->table('prod_type_param')->whereIn('type_code', ['RICE_BOX', 'PAPER_LUNCH_BOX'])->delete();
        $mst->table('prod_type')->whereIn('type_code', ['RICE_BOX', 'PAPER_LUNCH_BOX'])->delete();
    }
};
