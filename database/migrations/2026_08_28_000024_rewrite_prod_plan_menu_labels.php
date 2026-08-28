<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Permintaan user 2026-08-28: sederhanakan label & urutan menu Production >
// Planning jadi 3 baris berurutan -- "Work Order", "MPS - Gantt Chart",
// "MPS - Cards" (Gantt=Scheduling Board existing, Cards=Screen #3 monitoring
// dashboard yg tadinya nyasar ke grup Output/PROD_MON_WO, dipindah ke sini
// supaya nempel sama pasangan Gantt-nya). Forecast MTS disembunyikan
// (activate=false) sementara atas permintaan user, BUKAN dihapus -- data &
// layar tetap ada, gampang dinyalakan lagi.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        $planId = $sys->table('sys_app_menu')->where('code', 'PROD_PLAN')->value('id');

        $sys->table('sys_app_menu')->where('code', 'PROD_WO_LIST')->update([
            'label' => 'Work Order',
            'sort_order' => 10,
        ]);

        $sys->table('sys_app_menu')->where('code', 'PROD_MPS')->update([
            'label' => 'MPS - Gantt Chart',
            'sort_order' => 11,
        ]);

        $sys->table('sys_app_menu')->where('code', 'PROD_MON_WO')->update([
            'label' => 'MPS - Cards',
            'parent_id' => $planId,
            'sort_order' => 12,
        ]);

        $sys->table('sys_app_menu')->where('code', 'PROD_MTS_FORECAST')->update([
            'activate' => false,
        ]);
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');
        $outId = $sys->table('sys_app_menu')->where('code', 'PROD_OUT')->value('id');

        $sys->table('sys_app_menu')->where('code', 'PROD_WO_LIST')->update([
            'label' => 'Worklist Work Order',
            'sort_order' => 15,
        ]);

        $sys->table('sys_app_menu')->where('code', 'PROD_MPS')->update([
            'label' => 'MPS (Master Production Schedule)',
            'sort_order' => 10,
        ]);

        $sys->table('sys_app_menu')->where('code', 'PROD_MON_WO')->update([
            'label' => 'Monitoring Work Order',
            'parent_id' => $outId,
            'sort_order' => 20,
        ]);

        $sys->table('sys_app_menu')->where('code', 'PROD_MTS_FORECAST')->update([
            'activate' => true,
        ]);
    }
};
