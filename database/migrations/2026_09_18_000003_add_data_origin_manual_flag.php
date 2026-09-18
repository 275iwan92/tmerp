<?php

use App\Services\Data\DataOriginService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Penanda "asal data ditentukan manual" -- dipasang saat admin menaikkan
// LEGACY yang sudah lengkap menjadi LIVE (data real). Tanpa ini, klasifikasi
// berikutnya akan menandai baris itu LEGACY lagi karena created_by-nya tetap
// penanda migrasi. Aturan otomatis TIDAK pernah menyentuh baris ber-flag ini.
return new class extends Migration
{
    public function up(): void
    {
        $service = app(DataOriginService::class);

        foreach ($service->tables(true) as $t) {
            if (! $t['has_flag']) {
                continue;
            }
            Schema::connection($t['db'])->table($t['table'], function (Blueprint $b) {
                $b->boolean('data_origin_manual')->default(false)->comment('1 = asal data ditentukan manual, jangan diubah aturan');
            });
        }
    }

    public function down(): void
    {
        $service = app(DataOriginService::class);

        foreach ($service->tables(true) as $t) {
            if (! $t['has_flag']) {
                continue;
            }
            Schema::connection($t['db'])->table($t['table'], fn (Blueprint $b) => $b->dropColumn('data_origin_manual'));
        }
    }
};
