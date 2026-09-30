<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Spek mesin yang masih kosong (user 2026-10-01: "Cari di internet dan pasang, tetap buat interface agar user bisa
// mengubahnya sesuai realita"). Model katalog = padanan kelas dari riset online (URL di source_note); mesin "Daxiang"
// generik = mesin China, dipasangkan ke model sekelas. Pilihan per mesin SEMENTARA — hanya mengisi kolom yang masih
// kosong, tidak pernah menimpa angka yang sudah ada (Master Mesin = data real, CLAUDE.md §6B).
return new class extends Migration
{
    private const TAG = '[MODEL 2026-10-01]';

    public function up(): void
    {
        $db = DB::connection('tm2mst');
        $m = fn (string $code, string $type, string $drive, string $maker, ?int $l, ?int $w, ?int $speed, ?float $kw, ?int $kg, ?int $ton, string $src) => [
            'model_code' => $code, 'alias_codes' => null, 'machine_type' => $type, 'drive' => $drive, 'maker_hint' => $maker,
            'work_length_mm' => $l, 'work_width_mm' => $w, 'max_cut_length_mm' => null, 'max_cut_width_mm' => null, 'speed_per_hour' => $speed,
            'max_rule_length_m' => null, 'motor_kw' => $kw, 'weight_kg' => $kg, 'pressure_ton' => $ton,
            'source_note' => mb_substr($src, 0, 255), 'activate' => true, 'updated_at' => now(), 'created_by' => 'system',
        ];
        $models = [
            $m('AVALON-C8-30', 'CTP Plate Setter', 'AUTO', 'Agfa (Belgia) — thermal CTP 8-up/B1', 1163, 940, 24, null, null, null,
                'Online 2026-10-01: plate max 1163x940; 24 (E) - 47 (XXT) plates/h at 1030x800. https://www.agfa.com/printing/wp-content/uploads/sites/19/2019/09/avalon-8-up-thermal-platesetters-brochure-en-web.pdf'),
            $m('AVALON-C4-30', 'CTP Plate Setter', 'AUTO', 'Agfa (Belgia) — thermal CTP 4-up/B2', 925, 675, 21, null, null, null,
                'Online 2026-10-01: plate max 925x675; 21-29 plates/h. https://www.agfa.com/printing/products/avalon-4-up-series/'),
            $m('QZYK-1150', 'Paper Cutting', 'AUTO', 'China (Guoxin dan pabrik lain) — guillotine hidrolik terprogram', 1160, 1150, null, null, 3500, null,
                'Online 2026-10-01: cut width 1150, depth 1160, height 165 mm, 45 cycles/min (stack cuts, not sheets). http://www.hpmpapercuttingmachine.com/showroom/qzyk-1150-computer-control-paper-cutter-guillotine.html'),
            $m('YDS-1100BT', 'Folder Gluer', 'AUTO', 'YDM / Guangdong Yadu (China)', null, 1100, null, null, null, null,
                'Online 2026-10-01: YDS-600BT..1450BT, width = model number (mm), 250-300 m/min belt speed. https://dwimaputramesinsejati.com/product/automatic-folder-gluer-ydm-brand-200-800g-e-flute-b-flute-c-flute/'),
            $m('FHSGJ-1050', 'Water-Based Coating', 'AUTO', 'China — coater otomatis water-based/UV', 1050, 730, 9000, null, null, null,
                'Online 2026-10-01: sheet max 1050x730, min 406x310, 128-600 gsm, 9000 sheets/h. https://newstar-machine.en.made-in-china.com/product/tKvJyfZxYQGC/China-Fhsgj-1050-Automatic-Spot-UV-Varnish-Coating-Machine-Part-UV-Paper-Coater-Machine.html'),
            $m('SGUV-650', 'UV Coating', 'MANUAL', 'China — UV coater umpan manual', 650, null, 1400, null, 600, null,
                'Online 2026-10-01: sheet width 650, coating width 630, 80-500 gsm, max 1400 sheets/h. https://www.goldsupplier.com/provide/p169097127.html'),
            $m('FM-1100', 'Laminating', 'MANUAL', 'China (Xinxing dll) — laminasi film thermal semi-otomatis', null, 1050, null, null, null, null,
                'Online 2026-10-01: BOPP thermal film, width max 1050 mm, 0-30 m/min (sheets/h depends on sheet length). https://www.zjxinxing.com/semi-automatic-film-laminating-machine'),
            $m('YB-1450E', 'Laminating', 'AUTO', 'Wenzhou Youbond (China) — flute/litho laminator otomatis', 1450, 1100, null, null, null, null,
                'Online 2026-10-01: sheet max 1450x1100, up to 80 m/min. Legacy name LAM_YOUBOND. https://www.flute-laminator.com/fully-automatic-laminating-machine/yb-1300e-1450e-1650e-automatic-flute-laminating-machine'),
            $m('TYMB-750', 'Pressing', 'MANUAL', 'China (Dayuan dll) — hot foil stamping + emboss + plong manual', 750, 520, 1380, 2.2, 2500, 100,
                'Online 2026-10-01: platen 750x520, foil area 740x510, 1300-1380 strokes/h, 100 t. https://www.printyoung.com/shop/tymb-750-manual-hot-stamping-and-paper-die-cutting-machine'),
            $m('LQ-QF-1080C', 'Stripping', 'AUTO', 'Shanghai UPG (China) — blanking otomatis', 1080, 780, null, null, null, null,
                'Online 2026-10-01: 500-800 blanking cycles/h, stack 60-95 mm (50-200 sheets per cycle). https://www.shanghai-upg.com/LQ-QF-1080C-Automatic-Blanking-Machine-pd46586496.html'),
            $m('TD-202', 'Stitching', 'MANUAL', 'China — wire stitcher 1 kepala (flat & saddle)', 450, 305, 5400, 0.55, 250, null,
                'Online 2026-10-01: 90 stitches/min, book 450x305 max, thickness 0.2-4 mm. https://www.bestsuppliers.com/products/pgwlhe6lfzbf/td202-saddle-stitching-machineiron-wire-book-binding-machine'),
            $m('SG-ASC-530A01', 'Cutting Plotter', 'AUTO', 'SAGA (China) — back creasing + sheet cutter', 740, 530, null, null, null, null,
                'Online 2026-10-01: sheet max 740x530, min 420x297, 250-400 gsm, creasing 0-2500 gf. https://digitalstarq.com/saga-sg-asc-530a01/'),
            $m('WM-700A', 'Gluing', 'MANUAL', 'China — mesin lem rol (lem hewan/animal glue) untuk kertas pembungkus rigid box', null, 700, null, null, null, null,
                'Online 2026-10-01: glue width max 700 mm, 28 m/min. https://www.rigidbox-machine.com/quality-11243287-desktop-gluing-machine-manual-gluing-machine-used-for-gluing-the-cover-papers'),
            $m('XENONS-X3S', 'Digital Printing', 'AUTO', 'Xenons (China) — UV printer 1,8 m Ricoh GEN5', null, 1880, null, null, null, null,
                'Online 2026-10-01: print width 1880 mm (speed in m2/h). https://www.dpitechnology.com/products/xenonsx3s'),
        ];
        foreach ($models as $row) {
            if (! $db->table('prod_machine_model')->where('model_code', $row['model_code'])->exists()) {
                $db->table('prod_machine_model')->insert($row + ['created_at' => now()]);
            }
        }

        // Pilihan SEMENTARA per mesin. Alasan singkat ikut dicatat supaya user tahu kenapa model ini.
        $picks = [
            'CTP-01' => ['AVALON-C8-30', 'brand AGFA Avalon C; 8-up because OFF-02 Komori GL440 runs B1 plates'],
            'CUT-02' => ['QZYK-1150', 'brand Echo not found online; class-equivalent 115 cm programmable guillotine'],
            'CUT-03' => ['QZYK-1150', 'Guoxin builds the QZYK series; 115 cm assumed'],
            'FGL-04' => ['YDS-1100BT', 'brand YDM; width class 1100 assumed'],
            'WBC-01' => ['FHSGJ-1050', 'Daxiang = Chinese machine; 1050 class matches the B1 press'],
            'UVC-01' => ['SGUV-650', 'legacy output 660 sheets/h fits a manual-feed 650 coater (max 1400/h)'],
            'LAM-03' => ['FM-1100', 'Daxiang film laminator (glossy/doff) = semi-auto thermal film class'],
            'LAM-04' => ['YB-1450E', 'legacy name LAM_YOUBOND = Youbond automatic laminator'],
            'PRS-01' => ['TYMB-750', 'Daxiang press doing emboss/deboss/hot print/foil = TYMB class'],
            'STR-02' => ['LQ-QF-1080C', 'Daxiang blanking stripper = automatic blanking class 1080'],
            'STI-02' => ['TD-202', 'book stitcher = single-head wire stitcher class'],
            'PLT-09' => ['SG-ASC-530A01', 'brand SAGA back creasing'],
            'AGL-01' => ['WM-700A', 'animal glue applicator = roller gluing machine class'],
            'DIG-06' => ['XENONS-X3S', 'brand Xenons; exact model unknown, 1.8 m UV class'],
        ];
        $ids = $db->table('prod_machine_model')->get()->keyBy('model_code');
        foreach ($picks as $code => [$modelCode, $why]) {
            $mc = $db->table('prod_machine')->where('machine_code', $code)->first();
            $mm = $ids[$modelCode] ?? null;
            if (! $mc || ! $mm || str_contains((string) $mc->notes, self::TAG)) {
                continue;
            }
            $upd = array_filter([
                'machine_model_id' => $mc->machine_model_id ? null : $mm->id_machine_model,
                'max_sheet_length_mm' => $mc->max_sheet_length_mm ? null : $mm->work_length_mm,
                'max_sheet_width_mm' => $mc->max_sheet_width_mm ? null : $mm->work_width_mm,
                'max_speed_per_hour' => $mc->max_speed_per_hour ? null : $mm->speed_per_hour,
                'power_kw' => $mc->power_kw ? null : $mm->motor_kw,
            ], fn ($v) => $v !== null);
            $upd['notes'] = trim(((string) $mc->notes)."\n".self::TAG.' TEMPORARY model '.$modelCode.' ('.$why.'); specs are the maker catalogue, only empty fields were filled — correct them in Master Machine if the real machine differs.');
            $upd['updated_by'] = 'system';
            $upd['updated_at'] = now();
            $db->table('prod_machine')->where('machine_id', $mc->machine_id)->update($upd);
        }
    }

    public function down(): void
    {
        // Sengaja tidak menghapus: angka bisa sudah dikoreksi user.
    }
};
