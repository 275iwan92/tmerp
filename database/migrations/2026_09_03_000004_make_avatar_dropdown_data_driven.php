<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// BACKLOG.md §10 "QUEUE item #9" -- sys_user_menu punya 10 baris tapi tidak
// pernah dibaca main-menu.blade.php (dropdown avatar 100% hardcode Blade).
// Isi lama juga sudah usang (mis. "Edit profil"/"Ubah password" tunggal,
// belum ada My Security/Configuration group yang lahir 2026-08-29..09-03).
// Migration ini: (1) tambah kolom visibility utk kondisi tampil yang tidak
// tertangkap admin_only saja, (2) reseed 8 item FLAT yang benar-benar
// dipindah data-driven (Pesan & Info + Akun) -- widget Theme/Bahasa/
// Configuration nested TETAP hardcode, lihat App\Models\Sys\SysUserMenu.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sys')->table('sys_user_menu', function (Blueprint $table): void {
            $table->enum('visibility', ['all', 'employee_linked', 'non_employee_linked', 'setup_non_admin'])
                ->default('all')->after('admin_only');
        });

        DB::connection('tm2sys')->table('sys_user_menu')->truncate();

        DB::connection('tm2sys')->table('sys_user_menu')->insert([
            ['section' => 'Pesan & Info', 'code' => 'UM_CHAT', 'label' => 'Chat', 'icon' => 'fa-solid fa-comment-dots', 'action_type' => 'drawer', 'action_target' => 'chatOpen', 'badge_source' => 'chat', 'visibility' => 'all', 'sort_order' => 10],
            ['section' => 'Pesan & Info', 'code' => 'UM_NOTIF', 'label' => 'Notifikasi', 'icon' => 'fa-solid fa-bell', 'action_type' => 'drawer', 'action_target' => 'notifOpen', 'badge_source' => 'notif', 'visibility' => 'all', 'sort_order' => 20],
            ['section' => 'Pesan & Info', 'code' => 'UM_AI', 'label' => 'Asisten AI', 'icon' => 'fa-solid fa-wand-magic-sparkles', 'action_type' => 'drawer', 'action_target' => 'aiOpen', 'badge_source' => null, 'visibility' => 'all', 'sort_order' => 30],
            ['section' => 'Akun', 'code' => 'UM_MYSECURITY', 'label' => 'My Security', 'icon' => 'fa-solid fa-shield-halved', 'action_type' => 'url', 'action_target' => 'self-service.index', 'badge_source' => null, 'visibility' => 'employee_linked', 'sort_order' => 40],
            ['section' => 'Akun', 'code' => 'UM_EDITPROFIL', 'label' => 'Edit profil', 'icon' => 'fa-solid fa-user-pen', 'action_type' => 'modal', 'action_target' => 'profileOpen', 'badge_source' => null, 'visibility' => 'non_employee_linked', 'sort_order' => 50],
            ['section' => 'Akun', 'code' => 'UM_PASSWORD', 'label' => 'Ubah password', 'icon' => 'fa-solid fa-key', 'action_type' => 'modal', 'action_target' => 'passwordOpen', 'badge_source' => null, 'visibility' => 'non_employee_linked', 'sort_order' => 60],
            ['section' => 'Akun', 'code' => 'UM_SETUP', 'label' => 'Pengaturan (Setup)', 'icon' => 'fa-solid fa-gear', 'action_type' => 'menu', 'action_target' => 'SET_USER', 'badge_source' => null, 'visibility' => 'setup_non_admin', 'sort_order' => 70],
            ['section' => 'Akun', 'code' => 'UM_HELP', 'label' => 'Bantuan & Support', 'icon' => 'fa-solid fa-circle-question', 'action_type' => 'url', 'action_target' => 'help.index', 'badge_source' => null, 'visibility' => 'all', 'sort_order' => 80],
        ]);
    }

    public function down(): void
    {
        Schema::connection('tm2sys')->table('sys_user_menu', function (Blueprint $table): void {
            $table->dropColumn('visibility');
        });
    }
};
