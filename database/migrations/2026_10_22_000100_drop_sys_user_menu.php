<?php

/*
 * REMARK
 * Tujuan       : Membuang tabel sys_user_menu (menu avatar klasik v1). Menu avatar v2 disusun dari kode LayerShell; model SysUserMenu sudah diarsipkan.
 * Sumber hulu  : T10 tahap 7e (Jo 2026-10-22): v1 dibuang seluruhnya, aturan D.25 / D.26. Isi 11 baris (ddtsys dan tm2sys) + DDL tersimpan di tmerp_unused/arsip_v1_2026-10-22.zip (sys_user_menu_dump_v1_2026-10-22.json).
 * Input        : -
 * Output       : tabel sys_user_menu di koneksi tm2sys dihapus (mockup = ddtsys, tmerp = tm2sys).
 * Dipakai oleh : tidak ada (tak ada kode yang membaca tabel ini); App\Console\Commands\DdtCopySys tidak lagi menyalinnya.
 * Status       : Aktif; idempoten; down() membuat ulang strukturnya KOSONG (baris lama ada di arsip).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sys')->dropIfExists('sys_user_menu');
    }

    public function down(): void
    {
        if (Schema::connection('tm2sys')->hasTable('sys_user_menu')) {
            return;
        }

        Schema::connection('tm2sys')->create('sys_user_menu', function (Blueprint $t): void {
            $t->unsignedInteger('id_umenu')->autoIncrement();
            $t->string('section', 40)->nullable();
            $t->string('code', 40)->unique('uq_umenu_code');
            $t->string('label', 80);
            $t->string('icon', 60)->nullable();
            $t->enum('action_type', ['drawer', 'modal', 'menu', 'url', 'lang', 'logout'])->default('url');
            $t->string('action_target', 120)->nullable();
            $t->string('badge_source', 20)->nullable();
            $t->boolean('is_danger')->default(false);
            $t->boolean('admin_only')->default(false);
            $t->enum('visibility', ['all', 'employee_linked', 'non_employee_linked', 'setup_non_admin'])->default('all');
            $t->unsignedSmallInteger('sort_order')->default(999);
            $t->boolean('activate')->default(true);
            $t->timestamps();
        });
    }
};
