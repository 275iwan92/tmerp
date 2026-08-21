<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Stock In (item pertama di tiap grup Input -- INV_RM_ITEM1/INV_FG_ITEM1/
// INV_SFG_ITEM1) sekarang live (resources/views/pages/inventory/stock-in.blade.php
// + StockPostingService/StockDocumentService). Stock Out/Return/Adjustment
// (ITEM2-5) TETAP NOT_BUILT, belum digarap.
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->whereIn('code', ['INV_RM_ITEM1', 'INV_FG_ITEM1', 'INV_SFG_ITEM1'])
            ->update([
                'status_code' => 'READY_FOR_QC',
                'status_note' => 'Stock In manual live 2026-08-22 (stock-in.blade.php + StockPostingService)',
                'status_updated_by' => 'claude_code',
                'status_updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->whereIn('code', ['INV_RM_ITEM1', 'INV_FG_ITEM1', 'INV_SFG_ITEM1'])
            ->update(['status_code' => 'NOT_BUILT']);
    }
};
