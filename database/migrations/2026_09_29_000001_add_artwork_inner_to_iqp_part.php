<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Artwork sisi dalam (user 2026-09-29): kemasan cetak 2 sisi (mis. mailer box luar + dalam).
// artwork_path tetap = sisi luar (yang tampil di dieline & 3D).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sdi')->table('iqp_part', function (Blueprint $table): void {
            $table->string('artwork_inner_path', 255)->nullable()->after('artwork_original_filename');
            $table->string('artwork_inner_original_filename', 255)->nullable()->after('artwork_inner_path');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->table('iqp_part', function (Blueprint $table): void {
            $table->dropColumn(['artwork_inner_path', 'artwork_inner_original_filename']);
        });
    }
};
