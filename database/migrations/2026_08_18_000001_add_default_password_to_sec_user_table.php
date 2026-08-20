<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SECURITY_default_password_spec.md §3 — admin butuh password default yang
// bisa dibaca (migrasi user lama + fallback manual selama gateway email/WA
// belum pasti jalan di Docker). default_password = ciphertext Laravel Crypt
// (bukan hash), must_change_password memaksa ganti password di login berikut.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sec_user', function (Blueprint $table) {
            $table->text('default_password')->nullable()->after('pswd')
                ->comment('Ciphertext Laravel Crypt (AES-256, key=APP_KEY). Password default terbaca oleh admin. Permanen, timpa saat reset.');
            $table->boolean('must_change_password')->default(false)->after('default_password')
                ->comment('1 = user wajib ganti password saat login berikutnya (habis reset / user baru).');
        });
    }

    public function down(): void
    {
        Schema::table('sec_user', function (Blueprint $table) {
            $table->dropColumn(['default_password', 'must_change_password']);
        });
    }
};
