<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// PPIC: the three placeholder MPS rows (Gantt, Kanban, Spreadsheet) become ONE screen with four views. Production Scheduling replaces
// "MPS - Gantt Chart"; the Kanban and Spreadsheet placeholders are hidden because they are views inside it. Run on the dummy menu database first
// (--env=mockup); live at release. Only the user sets LOCKED.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        $sys->table('sys_app_menu')->where('code', 'PROD_MPS')->update([
            'label' => 'Production Scheduling', 'target' => 'v2:production-scheduling', 'object_type' => 'form', 'icon' => 'fa-solid fa-chart-gantt', 'sort_order' => 11,
            'status_code' => 'READY_FOR_QC', 'status_note' => 'v2 PPIC: engines plan a Draft, officer confirms. Gantt, Kanban, Calendar, Grid, Monitoring, Material Control, Incoming Purchases.',
        ]);
        $sys->table('sys_app_menu')->whereIn('code', ['PROD_MON_WO', 'PROD_MPS_SHEET'])->update(['activate' => 0, 'status_note' => 'A view inside Production Scheduling (Kanban / Grid).']);
        $sys->table('sys_app_menu')->where('code', 'PROD_WO_LIST')->update(['status_note' => 'v2 list; Open = Work Order (PPIC) screen']);
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');
        $sys->table('sys_app_menu')->where('code', 'PROD_MPS')->update(['label' => 'MPS - Gantt Chart', 'target' => 'form_mps.php', 'object_type' => 'form', 'status_code' => 'NOT_BUILT']);
        $sys->table('sys_app_menu')->whereIn('code', ['PROD_MON_WO', 'PROD_MPS_SHEET'])->update(['activate' => 1]);
    }
};
