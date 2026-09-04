<?php

use App\Models\Master\MatRaw;
use App\Models\Master\ProdType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Follow-up dummy workload (user 2026-09-04, screenshot layar ringkasan Inquiry
// Product cross-nav: "tidak diecut nya yang sudah di define di inquiry
// product... informasi finishing, bahan baku"). Investigasi: migration 000012/
// 000013 (66 iqp_part dummy tag IQ2690%) HANYA menulis dieline_spec sebagai
// {type_code, dims} -- bukan DSL lengkap (canvas/vars/elements/flat) dari
// prod_type asal, jadi window.TMDieline.svg() (readonly-summary.blade.php)
// tidak bisa gambar diecut asli, cuma fallback kotak polos. iqp_part_material
// juga 0 baris di semua 66 dummy (form aslinya MEMANG punya UI isi bahan,
// dummy-nya saja belum diisi). Migration ini MURNI enrichment kolom pada baris
// yang SUDAH ADA dari 000012 (sama seperti pola 000013) -- tidak bikin/hapus
// baris, sejalan feedback_keep_dummy_data.md (dummy data aditif, tidak dihapus).
//
// Proses/finishing (iqp_part_process) SENGAJA TIDAK diisi di sini -- form
// Inquiry Product (mode BARU) memang tidak punya UI untuk itu sama sekali
// (lihat komentar di readonly-summary.blade.php), jadi mengarang baris di situ
// justru menyesatkan (seolah-olah fitur itu ada). Kosong = representasi jujur
// dari gap yang sebenarnya.
return new class extends Migration
{
    public function up(): void
    {
        $sdi = DB::connection('tm2sdi');
        $now = now();

        $boxTypes = ProdType::whereIn('type_code', [
            'CARD_BOX', 'RTE_BOX', 'STE_BOX', 'CRASH_LOCK', 'SLEEVE', 'GIFT_BOX',
        ])->get()->keyBy('type_code');

        // Material representatif per tipe box (nama & harga nyata dari MatRaw
        // hasil migrasi tm2old, bukan dikarang) -- dipilih via LIKE dari 3
        // keluarga bahan yang benar-benar ada: Ivory (card/gift box), Duplex
        // (RTE/STE/crash lock), Kraft (sleeve).
        $familyByType = [
            'CARD_BOX' => 'Ivory', 'GIFT_BOX' => 'Ivory',
            'RTE_BOX' => 'Duplex', 'STE_BOX' => 'Duplex', 'CRASH_LOCK' => 'Duplex',
            'SLEEVE' => 'Kraft',
        ];
        $materialPool = [];
        foreach (array_unique($familyByType) as $family) {
            $materialPool[$family] = MatRaw::where('activate', true)->where('stat_confirm', true)
                ->where('material_name', 'like', "%{$family}%")
                ->inRandomOrder()->limit(10)->get(['material_id', 'material_name'])->all();
        }

        $colorCycle = ['4/0', '4/4', '2/0', '1/0'];

        $parts = $sdi->table('iqp_part')
            ->join('iqp_inquiry', 'iqp_part.inquiry_id', '=', 'iqp_inquiry.id_inquiry')
            ->where('iqp_inquiry.inquiry_no', 'like', 'IQ2690%')
            ->select('iqp_part.id_part', 'iqp_part.dieline_spec', 'iqp_part.cfg_color')
            ->orderBy('iqp_part.id_part')
            ->get();

        foreach ($parts as $i => $row) {
            $spec = json_decode($row->dieline_spec ?? '{}', true) ?: [];
            $typeCode = $spec['type_code'] ?? null;
            $type = $typeCode ? ($boxTypes[$typeCode] ?? null) : null;

            $update = [];

            // 1. Dieline DSL lengkap (canvas/vars/elements/flat) -- sama persis
            //    logika freeze asli di inquiry/form.blade.php (~line 1076-1079),
            //    ditambal belakangan karena 000012/000013 cuma nulis type_code+dims.
            if ($type && $type->dieline_spec && empty($spec['elements'])) {
                $full = $type->dieline_spec;
                $full['dims'] = $spec['dims'] ?? [];
                $full['type_code'] = $typeCode;
                $update['dieline_spec'] = json_encode($full);
            }

            // 2. Warna cetak (cfg_color) -- siklus 4 kode standar, bukan acak murni,
            //    supaya reproducible kalau migration di-inspect ulang.
            if (empty($row->cfg_color)) {
                $update['cfg_color'] = $colorCycle[$i % count($colorCycle)];
            }

            if ($update) {
                $update['updated_by'] = 'system';
                $update['updated_at'] = $now;
                $sdi->table('iqp_part')->where('id_part', $row->id_part)->update($update);
            }

            // 3. Bahan baku utama (iqp_part_material) -- 1 baris primary per part,
            //    dari MatRaw riil (bukan nama dikarang), gramatur di-parse dari nama
            //    materialnya sendiri kalau ada polanya ("250gr"/"400 GR"), fallback
            //    default masuk akal per keluarga bahan kalau tidak ketemu.
            $family = $type ? ($familyByType[$typeCode] ?? null) : null;
            $pool = $family ? ($materialPool[$family] ?? []) : [];

            if ($pool && ! $sdi->table('iqp_part_material')->where('part_id', $row->id_part)->exists()) {
                $mat = $pool[$i % count($pool)];
                $gsm = null;
                if (preg_match('/(\d{2,4})\s*gr/i', $mat->material_name, $m)) {
                    $gsm = (float) $m[1];
                }
                $gsm ??= match ($family) {
                    'Ivory' => 250.0,
                    'Duplex' => 300.0,
                    'Kraft' => 150.0,
                    default => 200.0,
                };

                $sdi->table('iqp_part_material')->insert([
                    'part_id' => $row->id_part,
                    'layer_seq' => 1,
                    'rawmat_id' => $mat->material_id,
                    'rawmat_name' => $mat->material_name,
                    'gram_weight' => $gsm,
                    'flute_type' => null,
                    'size_p' => null,
                    'size_l' => null,
                    'is_primary' => true,
                    'material_source' => 'OWN',
                    'created_by' => 'system',
                    'updated_by' => 'system',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $sdi = DB::connection('tm2sdi');

        $sdi->table('iqp_part_material')
            ->where('created_by', 'system')
            ->whereIn('part_id', function ($q) {
                $q->select('iqp_part.id_part')
                    ->from('iqp_part')
                    ->join('iqp_inquiry', 'iqp_part.inquiry_id', '=', 'iqp_inquiry.id_inquiry')
                    ->where('iqp_inquiry.inquiry_no', 'like', 'IQ2690%');
            })
            ->delete();
    }
};
