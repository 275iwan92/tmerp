<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Zona waktu aplikasi (keputusan user 2026-09-24): Asia/Jakarta (WIB), bisa diubah admin lewat Settings -> Constants.
// Dibaca AppServiceProvider saat boot; nilai tidak valid diabaikan (tetap config app.timezone).
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::connection('tm2sys')->table('sys_constant')->updateOrInsert(
            ['constant_key' => 'APP_TIMEZONE'],
            ['category_code' => 'BRANDING', 'constant_group' => 'BRANDING', 'constant_text' => 'Asia/Jakarta',
                'description' => 'Zona waktu aplikasi (nama IANA, mis. Asia/Jakarta = WIB). Berlaku untuk jam tampil & timestamp baru.',
                'activate' => true, 'created_at' => $now, 'updated_at' => $now]
        );
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_constant')->where('constant_key', 'APP_TIMEZONE')->delete();
    }
};
