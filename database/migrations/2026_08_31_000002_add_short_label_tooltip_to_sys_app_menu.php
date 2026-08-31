<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Singkatan topbar (SD/MM/WM/PP/HR/FA/MD) dipindah dari map hardcode JS
// (app-shell.js topLabel()) ke DB -- instruksi user 2026-08-31 "menu ini
// harusnya pakai database tm2sys". short_label null = topbar pakai label
// penuh apa adanya (fallback), jadi kolom ini aman untuk baris item/group
// yang tak butuh singkatan.
return new class extends Migration
{
    public function up(): void
    {
        $db = Schema::connection('tm2sys');

        $db->table('sys_app_menu', function (Blueprint $table) {
            $table->string('short_label', 20)->nullable()->after('label');
            $table->string('tooltip', 150)->nullable()->after('short_label');
        });

        $rows = [
            'SALES' => ['SD', 'Sales & Distribution'],
            'PROC' => ['MM', 'Procurement (Materials Management)'],
            'INV' => ['WM', 'Inventory (Warehouse Management)'],
            'PROD' => ['PP', 'Production (Production Planning)'],
            'HRM' => ['HR', 'Human Resources'],
            'FINACC' => ['FA', 'Finance & Accounting'],
            'MDATA' => ['MD', 'Master Data'],
        ];

        foreach ($rows as $code => [$short, $tooltip]) {
            DB::connection('tm2sys')->table('sys_app_menu')
                ->where('code', $code)
                ->update(['short_label' => $short, 'tooltip' => $tooltip]);
        }

        // MDATA pindah ke ujung kanan topbar (setelah FINACC=60), sebelum
        // SETUP=99 yang memang disembunyikan dari topbar.
        DB::connection('tm2sys')->table('sys_app_menu')
            ->where('code', 'MDATA')
            ->update(['sort_order' => 65]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->where('code', 'MDATA')
            ->update(['sort_order' => 50]);

        Schema::connection('tm2sys')->table('sys_app_menu', function (Blueprint $table) {
            $table->dropColumn(['short_label', 'tooltip']);
        });
    }
};
