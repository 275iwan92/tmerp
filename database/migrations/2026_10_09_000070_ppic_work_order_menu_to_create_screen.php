<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// B0: Production > Planning > Work Order opened the plain grid (prod-work-order), so the Create Work Order card was unreachable from the menu.
// The menu now opens the Work Order (PPIC) screen, which holds the Create Work Order card and the same grid. Dummy menu database first (--env=mockup).
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'PROD_WO_LIST')->update(['target' => 'v2:production-wo', 'status_note' => 'v2: Create Work Order card + list; Open = Work Order (PPIC) screen']);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'PROD_WO_LIST')->update(['target' => 'v2:prod-work-order']);
    }
};
