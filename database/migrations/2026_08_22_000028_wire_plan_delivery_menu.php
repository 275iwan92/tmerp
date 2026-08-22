<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Plan Delivery dibangun 2026-08-22 dari docs/mockups/co_delivery_plan_mockup.html
// -- hub logistik lintas-CO, tidak menyentuh stok. Placeholder menu SALES_PLAN
// (id 115, under "Delivery" group SALES_DLVGRP) sudah ada sejak draf awal,
// tinggal dipindah NOT_BUILT -> READY_FOR_QC. SALES_DO "Delivery Order" tetap
// NOT_BUILT (domain Dispatch, belum dibangun).
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'SALES_PLAN')->update(['status_code' => 'READY_FOR_QC']);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'SALES_PLAN')->update(['status_code' => 'NOT_BUILT']);
    }
};
