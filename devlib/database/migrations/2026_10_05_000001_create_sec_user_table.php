<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Database `devlib` (user 2026-10-05): login devlib.test terpisah dari mockup.test. Isi akun disalin dari
// sec_user mockup.test dengan `php artisan devlib:copy-user <login>` (termasuk hash password).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sec_user', function (Blueprint $table) {
            $table->id('id_user');
            $table->string('login', 60)->unique();
            $table->string('pswd');
            $table->string('name', 120);
            $table->string('email', 150)->nullable();
            $table->string('phone', 40)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('role', 120)->nullable();          // mis. Main Developer & System Analyst
            $table->boolean('developer')->default(false);     // Mode Developer + opening screen developer
            $table->string('lang_code', 10)->nullable();
            $table->string('theme_code', 40)->nullable();
            $table->boolean('activate')->default(true);
            $table->dateTime('pswd_last_updated')->nullable();
            $table->string('copied_from', 60)->nullable();    // database asal akun
            $table->timestamps();
            $table->string('created_by', 60)->nullable();
            $table->string('updated_by', 60)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sec_user');
    }
};
