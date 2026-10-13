<?php

/*
 * REMARK
 * Tujuan       : Daily Quotes digabung ke layar Quotes (tab Per Hari); menu Daily Quotes keluar dari sidebar.
 * Sumber hulu  : sys_app_menu (tm2sys), kolom hide_in_menu.
 * Input        : -
 * Output       : SET_DAILY_QUOTES tersembunyi; layarnya tetap terbuka lewat URL.
 * Dipakai oleh : AppMenu (menu v2).
 * Status       : Jalankan di ddt* (--env=mockup); di tm2* saat rilis.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'SET_DAILY_QUOTES')->update(['hide_in_menu' => 1]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'SET_DAILY_QUOTES')->update(['hide_in_menu' => 0]);
    }
};
