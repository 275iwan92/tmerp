<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// GAP tertunda ditambal via ALTER (pola sudah dipakai di CLAUDE.md §5) — form
// register (mockup register.html) butuh tipe pendaftar & NIK/NRP, belum ada
// kolomnya di TIER_3A_SECURITY.sql sec_user. Nullable, non-breaking.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sec_user', function (Blueprint $table) {
            $table->string('reg_type', 20)->nullable()->after('is_active')
                ->comment('customer|supplier|internal — hanya diisi dari self-registration');
            $table->string('id_number', 30)->nullable()->after('reg_type')
                ->comment('NIK (customer/supplier) atau NRP (internal) dari self-registration');
        });
    }

    public function down(): void
    {
        Schema::table('sec_user', function (Blueprint $table) {
            $table->dropColumn(['reg_type', 'id_number']);
        });
    }
};
