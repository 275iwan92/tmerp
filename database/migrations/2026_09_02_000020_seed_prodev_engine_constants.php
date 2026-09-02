<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// docs/inquiry_product_costing/productdevelopment_tier1.md §4.2a/4.3/7 --
// 3 angka placeholder dari diskusi desain ProDev (2026-09-02) DISENGAJA
// disimpan sbg sys_constant (bukan hardcode PHP) supaya admin bisa adjust
// dari layar Configuration tanpa deploy kode, begitu ada data trial/riil:
// - PRODEV_UPLOAD_MAX_MB: batas upload JPEG/PDF/AI (mulai 25MB, user blm
//   punya data ukuran file riil, mudah dinaikkan kalau kurang).
// - PRODEV_INTERLOCK_SAVING_MIN/MAX_PCT: estimasi hemat Interlocking
//   Nesting (12-22%, angka dari riset luar/Gemini, BUKAN terverifikasi
//   Esko resmi -- dicek langsung ke docs.esko.com, tidak ketemu angka
//   ini di sumber resmi manapun). Dipakai dulu sbg target awal, "nanti
//   ditentukan lagi" stlh trial nyata -- makanya WAJIB gampang di-adjust.
// - PRODEV_FLAP_MIN_MM: panjang lidah minimum (2cm/20mm) -- SEMENTARA
//   angka FLAT, user eksplisit bilang seharusnya bergantung panjang/lebar
//   tutup (formula, bukan konstanta tunggal) tapi itu belum diimplementasi,
//   dicatat di description supaya tidak dikira final.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        $nextId = (int) $sys->table('sys_constant')->max('id_constant') + 1;

        $rows = [
            [
                'constant_key' => 'PRODEV_UPLOAD_MAX_MB',
                'constant_number' => 25,
                'description' => 'Batas maks ukuran file upload JPEG/PDF/AI di ProDev (MB). Mulai 25MB (user 2026-09-02), naikkan kalau file dieline AI kompleks kepotong.',
            ],
            [
                'constant_key' => 'PRODEV_INTERLOCK_SAVING_MIN_PCT',
                'constant_number' => 12,
                'description' => 'Estimasi hemat MIN Interlocking Nesting (%). Dari riset industri, BUKAN angka resmi Esko (dicek 2026-09-02, tak ketemu di docs.esko.com). Adjust stlh trial nyata.',
            ],
            [
                'constant_key' => 'PRODEV_INTERLOCK_SAVING_MAX_PCT',
                'constant_number' => 22,
                'description' => 'Estimasi hemat MAX Interlocking Nesting (%) -- pasangan PRODEV_INTERLOCK_SAVING_MIN_PCT, sumber & status sama.',
            ],
            [
                'constant_key' => 'PRODEV_FLAP_MIN_MM',
                'constant_number' => 20,
                'description' => 'Lidah (flap) minimum default, mm (2cm). SEMENTARA flat -- user 2026-09-02: seharusnya formula dari panjang/lebar tutup (blm diimplementasi), pakai sampai mat_flap_rule siap.',
            ],
        ];

        foreach ($rows as $i => $row) {
            if ($sys->table('sys_constant')->where('constant_key', $row['constant_key'])->exists()) {
                continue;
            }

            $sys->table('sys_constant')->insert([
                'id_constant' => $nextId + $i,
                'constant_key' => $row['constant_key'],
                'category_code' => 'PRODUCTION',
                'constant_group' => 'PRODEV',
                'constant_number' => $row['constant_number'],
                'constant_text' => null,
                'description' => $row['description'],
                'activate' => true,
                'created_by' => 'claude_code',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_constant')->whereIn('constant_key', [
            'PRODEV_UPLOAD_MAX_MB',
            'PRODEV_INTERLOCK_SAVING_MIN_PCT',
            'PRODEV_INTERLOCK_SAVING_MAX_PCT',
            'PRODEV_FLAP_MIN_MM',
        ])->delete();
    }
};
