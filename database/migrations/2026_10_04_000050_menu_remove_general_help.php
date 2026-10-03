<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// User 2026-10-04: "koq ada help di general center? kenapa bukannya help semua ada di Help & support di halaman user profile?"
// General > Help > Help Center leaves the main menu; Help Center stays reachable from Profile > Help & support (and F1),
// a screen without a menu row stays open (AppMenu::open). Runs on ddt* (--env=mockup) and tm2*.
return new class extends Migration
{
    protected $connection = 'tm2sys';

    public function up(): void
    {
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        $menu()->where('code', 'GENERAL_HELP_CENTER')->delete();
        $menu()->where('code', 'GENERAL_HELP')->delete();
    }

    public function down(): void
    {
        // Rows are not restored on purpose: all help lives in Profile > Help & support.
    }
};
