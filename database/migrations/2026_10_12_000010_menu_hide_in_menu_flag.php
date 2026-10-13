<?php

/*
 * REMARK
 * Tujuan       : Kolom hide_in_menu pada sys_app_menu: item keluar dari sidebar tetapi layarnya tetap terbuka lewat URL.
 * Sumber hulu  : sys_app_menu (tm2sys).
 * Input        : -
 * Output       : Kolom boolean baru.
 * Dipakai oleh : AppMenu (menu v2).
 * Status       : Sudah dijalankan di ddt*; jalankan di tm2* saat rilis.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// hide_in_menu = item leaves the sidebar but its screen stays open by URL (activate=0 would close the screen, 404).
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('tm2sys');
        if (! $s->hasColumn('sys_app_menu', 'hide_in_menu')) {
            $s->table('sys_app_menu', fn (Blueprint $t) => $t->boolean('hide_in_menu')->default(false)->after('activate'));
        }
    }

    public function down(): void
    {
        $s = Schema::connection('tm2sys');
        if ($s->hasColumn('sys_app_menu', 'hide_in_menu')) {
            $s->table('sys_app_menu', fn (Blueprint $t) => $t->dropColumn('hide_in_menu'));
        }
    }
};
