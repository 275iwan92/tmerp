<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// PPIC baseline Master Mesin (user 2026-10-01: "PASTIKAN semua mesin berjalan dengan benar dan terdokumentasi dalam master
// mesin untuk kemudahan proses di PPIC planning production"). Audit 2026-10-01: dari 45 mesin, 0 punya target kecepatan,
// shift/hari, jam/shift, operator -> kapasitas & OEE PPIC (MachineOeeService, MachineCapabilityService) tidak bisa dihitung.
// Sumber angka (dicatat juga di prod_machine.notes tiap mesin):
//  - REAL: tm2old.prd_output 2021-01..2024-03 (121 rb catatan hasil produksi sistem lama) per nama mesin lama:
//    target = P75 (qty bagus / jam jalan, run 5 mnt-16 jam), shift/hari = 2 bila rentang kerja harian P50 >= 12 jam,
//    helper = median kru tambahan. Jam/shift: 2 shift = 7,8 (rata-rata SH1 05:00-13:15 istirahat 30 mnt = 7,75 dan SH2 13:15-21:15 = 8; kolom 1 desimal), 1 shift = 8 (Reguler).
//  - PABRIKAN: riset online per model -> katalog prod_machine_model; spek hanya diisi bila kolom masih kosong.
//  - DEFAULT SEMENTARA: mesin tanpa riwayat & tanpa model = 1 shift x 8 jam, 1 operator; target = 80% kecepatan maks
//    pabrikan (aturan yang sama dengan Scheduling\RateBandResolver). Wajib diganti PPIC begitu ada data nyata.
return new class extends Migration
{
    private const TAG = '[PPIC 2026-10-01]';

    public function up(): void
    {
        $db = DB::connection('tm2mst');
        $src = 'Riset online 2026-10-01 (situs pabrikan/penjual)';
        $model = fn (string $code, string $type, string $drive, string $maker, ?int $l, ?int $w, ?int $speed, ?string $note = null) => [
            'model_code' => $code, 'alias_codes' => null, 'machine_type' => $type, 'drive' => $drive, 'maker_hint' => $maker,
            'work_length_mm' => $l, 'work_width_mm' => $w, 'max_cut_length_mm' => null, 'max_cut_width_mm' => null, 'speed_per_hour' => $speed,
            'max_rule_length_m' => null, 'motor_kw' => null, 'weight_kg' => null, 'pressure_ton' => null,
            'source_note' => $src.($note ? ' — '.$note : ''), 'activate' => true, 'created_at' => now(), 'updated_at' => now(), 'created_by' => 'system',
        ];
        $new = [
            $model('KOMORI-GL440', 'Offset Printing', 'AUTO', 'Komori (Jepang) — Lithrone GL440, 4 warna', 1050, 750, 16500),
            $model('SAKURAI-OLIVER-58', 'Offset Printing', 'AUTO', 'Sakurai (Jepang) — 1 warna', 580, 450, 12000, 'area cetak 570x440, lembar min 270x210'),
            $model('HEIDELBERG-VERSAFIRE-EV', 'Digital Printing', 'AUTO', 'Heidelberg', 700, 330, 3180, '3.180 lembar A3/jam, s/d 360 gsm, banner 700 mm duplex'),
            $model('HANWAY-HIGHJET-2500B', 'Digital Printing', 'AUTO', 'Hanway (China) — inkjet karton', 2500, 2500, null, 's/d 700 m2/jam, tebal media 1,5-15 mm'),
            $model('SUNTHINKS-SC645', 'Digital Printing', 'AUTO', 'Sunthinks (China) — single pass', null, 645, null, 'lebar cetak 645 mm, 30 m/menit'),
            $model('TAIMES-T5S-512I', 'Digital Printing', 'AUTO', 'Taimes (China) — Konica 512i', null, 3200, null, 'lebar 3,2 m, ±1.200 sqft/jam (4 head, 2 pass)'),
            $model('ITOTEC-ERC-115', 'Paper Cutting', 'AUTO', 'Itotec/ITOH (Jepang) — guillotine', 1160, 1150, null, 'lebar potong 1.150 mm, 42 potongan/menit'),
            $model('SUMMA-D60', 'Cutting Plotter', 'AUTO', 'Summa (Belgia) — SummaCut D60R / S1 D60', null, 600, null, 'lebar potong 600 mm, 1.131 mm/detik'),
            $model('IECHO-BK4-2516', 'Cutting Plotter', 'AUTO', 'IECHO (China) — flatbed digital cutter', 2500, 1600, null, '1.800 mm/detik, tebal s/d 50 mm'),
            $model('SAGA-SC-A3PLUS-II-PRO', 'Cutting Plotter', 'AUTO', 'SAGA (China) — sheet cutter CCD', 483, 330, null, 'potong maks 475x320, s/d 350 gsm'),
            $model('FR-900', 'Seaming', 'AUTO', 'Band sealer kontinu (umum)', null, null, null, 'sealing 0-12 m/menit, lebar seal 6-12 mm'),
        ];
        foreach ($new as $row) {
            $db->table('prod_machine_model')->updateOrInsert(['model_code' => $row['model_code']], $row);
        }
        $ids = $db->table('prod_machine_model')->pluck('id_machine_model', 'model_code');

        // kode => [model, target/jam, shift, jam/shift, operator, helper, sumber]
        $real = fn (string $legacy) => 'REAL tm2old.prd_output 2021-2024 mesin lama "'.$legacy.'"';
        $def = 'DEFAULT SEMENTARA (belum ada riwayat produksi) — ganti dengan data nyata';
        $plan = [
            'DIE-01' => [null, 703, 2, 7.8, 1, 1, $real('PLONG 1')],
            'DIE-02' => [null, 679, 2, 7.8, 1, 1, $real('PLONG 2')],
            'DIE-03' => [null, 717, 2, 7.8, 1, 1, $real('PLONG 3')],
            'DIE-04' => [null, null, 2, 7.8, 1, 1, $def.'; shift mengikuti pond lain'],
            'OFF-01' => ['SAKURAI-OLIVER-58', 4528, 2, 7.8, 1, 1, $real('SAKURA')],
            'OFF-02' => ['KOMORI-GL440', 5058, 2, 7.8, 1, 1, $real('KOMORI_GL440 + varian per operator (YANTO, DIMAS, AGUS, FANDIK, KHOIRUL)')],
            'DIG-01' => ['HANWAY-HIGHJET-2500B', 108, 2, 7.8, 1, 1, $real('HANWAY')],
            'DIG-02' => ['HEIDELBERG-VERSAFIRE-EV', null, 1, 8, 1, 0, $def.' (riwayat "VERSAFIRE" hanya 2 catatan)'],
            'DIG-03' => ['SUNTHINKS-SC645', 370, 1, 8, 1, 1, $real('DIGITAL SUNTHINKS')],
            'DIG-04' => ['TAIMES-T5S-512I', null, 1, 8, 1, 0, $def],
            'PLT-01' => ['SUMMA-D60', 38, 1, 8, 1, 1, $real('CUTTING_SUMMA')],
            'PLT-02' => ['SUMMA-D60', 38, 1, 8, 1, 1, $real('CUTTING_SUMMA')],
            'PLT-06' => ['SUMMA-D60', 38, 1, 8, 1, 1, $real('CUTTING_SUMMA')],
            'PLT-03' => ['IECHO-BK4-2516', 93, 1, 8, 1, 1, $real('CUTTING_BK4')],
            'PLT-04' => ['SAGA-SC-A3PLUS-II-PRO', null, 1, 8, 1, 0, $def],
            'PLT-05' => ['SAGA-SC-A3PLUS-II-PRO', null, 1, 8, 1, 0, $def],
            'PLT-07' => ['SAGA-SC-A3PLUS-II-PRO', null, 1, 8, 1, 0, $def],
            'PLT-08' => ['SAGA-SC-A3PLUS-II-PRO', null, 1, 8, 1, 0, $def],
            'CUT-01' => ['ITOTEC-ERC-115', 10286, 1, 8, 1, 1, $real('POTONG ITOH').' (Robdcut ERC-115 = Itotec eRC-115)'],
            'CUT-03' => [null, 10667, 1, 8, 1, 1, $real('GUOXIN')],
            'FGL-03' => [null, 6200, 2, 7.8, 1, 1, $real('FOLDER GLUER')],
            'FGL-04' => [null, 7100, 2, 7.8, 1, 1, $real('FOLDER GLUER 2')],
            'LAM-01' => [null, 370, 1, 8, 1, 3, $real('LAMINASI MANUAL')],
            'LAM-03' => [null, 650, 2, 7.8, 1, 1, $real('LAMINATING')],
            'LAM-04' => [null, 1289, 1, 8, 1, 3, $real('LAM_YOUBOND')],
            'UVC-01' => [null, 660, 2, 7.8, 1, 1, $real('UV')],
            'WAX-01' => [null, 1800, 1, 8, 1, 1, $real('LILIN').' (P50 tidak wajar, dipakai P75)'],
            'SEA-01' => ['FR-900', null, 1, 8, 1, 0, $def],
            'SEA-02' => ['FR-900', null, 1, 8, 1, 0, $def],
        ];

        foreach ($db->table('prod_machine')->where('activate', true)->get() as $m) {
            [$mc, $target, $shifts, $hours, $op, $helper, $source] = $plan[$m->machine_code] ?? [null, null, 1, 8, 1, 0, $def];
            $mm = $mc ? $db->table('prod_machine_model')->where('id_machine_model', $ids[$mc])->first() : ($m->machine_model_id ? $db->table('prod_machine_model')->where('id_machine_model', $m->machine_model_id)->first() : null);
            $maxSpeed = $m->max_speed_per_hour ?: $mm?->speed_per_hour;
            $target ??= $maxSpeed ? (int) round($maxSpeed * 0.8) : null;
            $upd = array_filter([
                'machine_model_id' => $m->machine_model_id ?: ($mc ? $ids[$mc] : null),
                'max_sheet_length_mm' => $m->max_sheet_length_mm ?: $mm?->work_length_mm,
                'max_sheet_width_mm' => $m->max_sheet_width_mm ?: $mm?->work_width_mm,
                'max_speed_per_hour' => $maxSpeed,
                'speed_target_per_hour' => $m->speed_target_per_hour ?: $target,
                'shifts_per_day' => $m->shifts_per_day ?: $shifts,
                'hours_per_shift' => $m->hours_per_shift ?: $hours,
                'operator_min' => $m->operator_min ?: $op,
                'helper_min' => $m->helper_min ?: $helper,
            ], fn ($v) => $v !== null);
            $missing = [];
            if (! ($upd['max_sheet_length_mm'] ?? null)) {
                $missing[] = 'ukuran lembar maks';
            }
            if (! ($upd['speed_target_per_hour'] ?? null)) {
                $missing[] = 'target kecepatan';
            }
            $line = self::TAG.' target '.($upd['speed_target_per_hour'] ?? '—').'/jam, '.$upd['shifts_per_day'].' shift x '.$upd['hours_per_shift']
                .' jam, operator '.$upd['operator_min'].' + helper '.$upd['helper_min'].'. Sumber: '.$source
                .($mm ? '; spek model '.$mm->model_code.' ('.$mm->source_note.')' : '')
                .($missing ? '. BELUM ADA: '.implode(', ', $missing).' — isi dari nameplate/brosur.' : '.');
            $notes = trim((string) $m->notes);
            $upd['notes'] = str_contains($notes, self::TAG) ? $notes : trim($notes."\n".$line);
            $upd['updated_by'] = 'system';
            $upd['updated_at'] = now();
            $db->table('prod_machine')->where('machine_id', $m->machine_id)->update($upd);
        }
    }

    public function down(): void
    {
        // Data baseline sengaja tidak dihapus otomatis (bisa sudah diubah PPIC); model katalog tambahan dibiarkan.
    }
};
