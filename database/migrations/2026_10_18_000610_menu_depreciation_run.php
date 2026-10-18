<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Finance T3e (Jo 2026-10-18): tombol Run Depreciation di halaman v1 Fixed Assets / Amortization diganti layar v2
// Depreciation Run. Tambah item menu Accounting > Depreciation Run (tm2sys.sys_app_menu), di sebelah Fixed Assets.
// Tidak mengubah skema atau data transaksi; berjalan di mockup (--env=mockup) dan tmerp.
return new class extends Migration
{
    public function up(): void
    {
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        $near = $menu()->where('code', 'ACC_FIXEDASSET')->first();
        if (! $near || $menu()->where('code', 'ACC_DEPR_RUN')->exists()) {
            return;
        }
        $menu()->insert(['parent_id' => $near->parent_id, 'code' => 'ACC_DEPR_RUN', 'label' => 'Depreciation Run', 'icon' => 'fa-solid fa-calendar-check', 'menu_type' => 'item',
            'object_type' => 'form', 'target' => 'v2:acc-depreciation-run', 'sort_order' => 9, 'activate' => 1, 'required_object' => $near->required_object, 'status_code' => 'READY_FOR_QC',
            'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(), 'created_at' => now()]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'ACC_DEPR_RUN')->delete();
    }
};
