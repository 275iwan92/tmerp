<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Menu "ACC Digital / Proof Digital" (Pro-Dev) ditiadakan (user 2026-10-01): dokumen itu cukup dibuka dari
// Print ▾ / Email ▾ > ACC Digital di form ProDev. Layar v2 product-acc tetap ada (tanpa baris menu = selalu terbuka,
// lihat App\Support\V2\AppMenu::open).
return new class extends Migration
{
    protected $connection = 'tm2sys';

    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'PRE_PROD_ACC')->delete();
    }

    public function down(): void
    {
        $parent = DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'SALES_PRODEVGRP')->value('id');
        if (! $parent || DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'PRE_PROD_ACC')->exists()) {
            return;
        }
        DB::connection('tm2sys')->table('sys_app_menu')->insert([
            'parent_id' => $parent, 'code' => 'PRE_PROD_ACC', 'label' => 'ACC Digital / Proof Digital', 'icon' => 'fa-solid fa-stamp',
            'menu_type' => 'item', 'object_type' => 'form', 'target' => 'v2:product-acc', 'sort_order' => 40, 'activate' => 1,
            'status_code' => 'READY_FOR_QC', 'created_at' => now(),
        ]);
    }
};
