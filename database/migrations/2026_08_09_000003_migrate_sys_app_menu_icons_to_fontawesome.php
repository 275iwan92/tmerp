<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// sys_app_menu.icon di 7 baris module masih emoji (docs/tm2sys.sql), sedangkan
// sec_module.icon (padanan layer izin) sudah Font Awesome sejak awal. Mockup
// Tier 4 terbaru (login.html/main_menu_v2.html) pindah total ke Font Awesome —
// selaraskan sys_app_menu supaya satu sumber ikon (bukan dua standar berbeda).
return new class extends Migration
{
    private array $icons = [
        'SALES' => 'fa-solid fa-file-invoice-dollar',
        'PROC' => 'fa-solid fa-cart-shopping',
        'INV' => 'fa-solid fa-boxes-stacked',
        'PROD' => 'fa-solid fa-industry',
        'MDATA' => 'fa-solid fa-database',
        'FINACC' => 'fa-solid fa-sack-dollar',
        'SETUP' => 'fa-solid fa-gear',
    ];

    private array $emoji = [
        'SALES' => '🧾',
        'PROC' => '🛒',
        'INV' => '📦',
        'PROD' => '🏭',
        'MDATA' => '🧱',
        'FINACC' => '💰',
        'SETUP' => '⚙️',
    ];

    public function up(): void
    {
        foreach ($this->icons as $code => $icon) {
            DB::table('sys_app_menu')->where('code', $code)->update(['icon' => $icon]);
        }
    }

    public function down(): void
    {
        foreach ($this->emoji as $code => $icon) {
            DB::table('sys_app_menu')->where('code', $code)->update(['icon' => $icon]);
        }
    }
};
