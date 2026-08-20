<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// module_status_system per GLOBAL_RULES.yaml + QUEUE.md #2 — menu jadi "peta"
// visual progress build, bukan cuma checklist_mockup_erp.md tertulis.
// NOT_BUILT (default, abu, tak bisa diklik) -> READY_FOR_QC (Claude Code
// selesai bangun, kuning, klik-able) -> LOCKED (user QC & konfirmasi, hijau,
// HANYA user yang boleh set ini).
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        Schema::connection('tm2sys')->table('sys_app_menu', function (Blueprint $table) {
            $table->enum('status_code', ['NOT_BUILT', 'READY_FOR_QC', 'LOCKED'])
                ->default('NOT_BUILT')
                ->comment('Kesiapan build (menu_type=item saja) -- lihat module_status_system di GLOBAL_RULES.yaml');
            $table->string('status_note', 255)->nullable();
            $table->string('status_updated_by', 100)->nullable();
            $table->dateTime('status_updated_at')->nullable();
            $table->index('status_code', 'idx_sys_app_menu_status');
        });

        Schema::connection('tm2sys')->create('sys_app_menu_color', function (Blueprint $table) {
            $table->string('status_code', 20)->primary();
            $table->string('color_hex', 7);
            $table->string('label', 50);
            $table->boolean('is_clickable')->default(true);
            $table->string('updated_by', 100)->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        $sys->table('sys_app_menu_color')->insert([
            ['status_code' => 'NOT_BUILT', 'color_hex' => '#9ca3af', 'label' => 'Belum Tersedia', 'is_clickable' => false],
            ['status_code' => 'READY_FOR_QC', 'color_hex' => '#f59e0b', 'label' => 'Siap di-QC', 'is_clickable' => true],
            ['status_code' => 'LOCKED', 'color_hex' => '#16a34a', 'label' => 'OK', 'is_clickable' => true],
        ]);

        $now = now();

        // Imposition & Nota Inquiry belum punya baris menu sama sekali (dibangun
        // 2026-08-20, sesudah seed menu awal) -- slot di antara Inquiry/Costing/Quotation
        // yang sudah ada (sort_order gap 10, lihat SALES_INQ=10, SALES_COST=20, SALES_QUOT=30).
        $sys->table('sys_app_menu')->insert([
            [
                'id' => 930, 'parent_id' => 110, 'code' => 'SALES_IMPO', 'label' => 'Imposition Engine',
                'menu_type' => 'item', 'object_type' => 'form', 'target_kind' => 'app', 'sort_order' => 15,
                'is_active' => true, 'created_at' => $now,
            ],
            [
                'id' => 931, 'parent_id' => 110, 'code' => 'SALES_NOTA', 'label' => 'Nota Inquiry',
                'menu_type' => 'item', 'object_type' => 'form', 'target_kind' => 'app', 'sort_order' => 25,
                'is_active' => true, 'created_at' => $now,
            ],
        ]);

        // Item yang SUDAH punya route Laravel nyata (lihat itemRoutes() di
        // main-menu.blade.php) -> READY_FOR_QC. Semua kode lain diam di default
        // NOT_BUILT. Claude Code menandai ini sendiri per aturan module_status_system
        // ("siapa_nandai": Claude Code, otomatis, tiap 1 komponen selesai) --
        // backfill retroaktif utk yang sudah dibangun sebelum kolom ini ada.
        $readyCodes = [
            'SALES_INQ', 'SALES_COST', 'SALES_QUOT', 'SALES_IMPO', 'SALES_NOTA',
            'MD_CUST', 'MD_SUPP', 'MD_UOM', 'MD_MACHINE', 'MD_SHIFT',
            'ACC_COA', 'FIN_DP',
            'HRM_EMPLOYEE', 'HRM_ROSTER',
            'SET_USER', 'SET_GROUP', 'SET_PERM', 'SET_MIGRATION',
        ];

        $sys->table('sys_app_menu')
            ->whereIn('code', $readyCodes)
            ->update([
                'status_code' => 'READY_FOR_QC',
                'status_updated_by' => 'claude_code',
                'status_updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');

        $sys->table('sys_app_menu')->whereIn('code', ['SALES_IMPO', 'SALES_NOTA'])->delete();

        Schema::connection('tm2sys')->dropIfExists('sys_app_menu_color');

        Schema::connection('tm2sys')->table('sys_app_menu', function (Blueprint $table) {
            $table->dropIndex('idx_sys_app_menu_status');
            $table->dropColumn(['status_code', 'status_note', 'status_updated_by', 'status_updated_at']);
        });
    }
};
