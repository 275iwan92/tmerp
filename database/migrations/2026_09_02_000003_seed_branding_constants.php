<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Konstanta identitas aplikasi (keputusan user 2026-09-02: "APP NAME", BUKAN
// nama company -- company_name tetap di org_company utk kebutuhan HR/cetak
// dokumen, lihat CLAUDE.md). Nilai APP_NAME default "ERP TM" (contoh user),
// APP_LOGO_PATH sengaja kosong -- belum ada file logo di repo, tetap pakai
// fallback teks sampai admin upload lewat layar Configuration.
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $rows = [
            ['constant_key' => 'APP_NAME', 'category_code' => 'BRANDING', 'constant_group' => 'BRANDING', 'constant_text' => 'ERP TM', 'description' => 'Nama aplikasi (topbar, judul login, brand Filament) -- BUKAN nama company'],
            ['constant_key' => 'APP_TAGLINE', 'category_code' => 'BRANDING', 'constant_group' => 'BRANDING', 'constant_text' => 'packaging solution', 'description' => 'Tagline di bawah nama aplikasi'],
            ['constant_key' => 'APP_LOGO_PATH', 'category_code' => 'BRANDING', 'constant_group' => 'BRANDING', 'constant_text' => null, 'description' => 'Path storage logo aplikasi (kosong = pakai badge teks)'],
            ['constant_key' => 'APP_FONT_FAMILY', 'category_code' => 'BRANDING', 'constant_group' => 'BRANDING', 'constant_text' => 'system-ui', 'description' => 'Kunci preset font aplikasi (lihat FontPreset di Configuration)'],
        ];

        foreach ($rows as $r) {
            DB::connection('tm2sys')->table('sys_constant')->updateOrInsert(
                ['constant_key' => $r['constant_key']],
                $r + ['activate' => true, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_constant')
            ->whereIn('constant_key', ['APP_NAME', 'APP_TAGLINE', 'APP_LOGO_PATH', 'APP_FONT_FAMILY'])
            ->delete();
    }
};
