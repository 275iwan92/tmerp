<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Generator otomatis tanpa AI (user 2026-10-05: "cara 1 ditambah cara 2 supaya langsung"): permintaan tipe Master boleh menunjuk
// tabel master yang sudah ada; tombol Generate Now membuat layarnya saat itu juga (php artisan devlib:generate di tmerp).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gen_request', function (Blueprint $table) {
            $table->string('source_table', 120)->nullable()->after('request_type');   // tm2mst.ref_delivery_method
            $table->text('build_log')->nullable()->after('screen_url');               // hasil / alasan penolakan generator otomatis
        });
    }

    public function down(): void
    {
        Schema::table('gen_request', function (Blueprint $table) {
            $table->dropColumn(['source_table', 'build_log']);
        });
    }
};
