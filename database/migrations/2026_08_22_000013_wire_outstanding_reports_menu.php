<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// PROC_MON (id 280, "Monitoring") + PROC_MON_PR/PROC_MON_PO (id 281/282) sudah
// ada dari draft awal tapi masih NOT_BUILT dgn target ScriptCase lama
// (grid_monitoring_pr.php) -- sekarang diisi dgn layar Outstanding PR/PO yang
// baru (docs/SIDEBAR_MENU_STANDARD.md: grup laporan wajib berlabel polos
// "Laporan", nama item ikut #[Title] layar asli). Outstanding PV SENGAJA
// ditambah sbg placeholder NOT_BUILT -- user minta ditunda dulu, baru
// dikerjakan setelah Stock Out (lihat memory project_outstanding_reports).
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tm2sys');

        $db->table('sys_app_menu')->where('code', 'PROC_MON')->update([
            'label' => 'Laporan',
        ]);

        $db->table('sys_app_menu')->where('code', 'PROC_MON_PR')->update([
            'label' => 'Outstanding PR',
            'target' => '',
            'target_kind' => 'app',
            'status_code' => 'READY_FOR_QC',
        ]);

        $db->table('sys_app_menu')->where('code', 'PROC_MON_PO')->update([
            'label' => 'Outstanding PO',
            'target' => '',
            'target_kind' => 'app',
            'status_code' => 'READY_FOR_QC',
        ]);

        $maxId = (int) $db->table('sys_app_menu')->max('id');

        if (! $db->table('sys_app_menu')->where('code', 'PROC_MON_PV')->exists()) {
            $db->table('sys_app_menu')->insert([
                'id' => $maxId + 1,
                'parent_id' => 280,
                'code' => 'PROC_MON_PV',
                'label' => 'Outstanding PV',
                'menu_type' => 'item',
                'object_type' => 'grid',
                'target' => '',
                'target_kind' => 'app',
                'sort_order' => 30,
                'is_active' => 1,
                'status_code' => 'NOT_BUILT',
                'status_note' => 'Ditunda -- butuh mekanisme serah terima barang ke pemohon (belum ada di skema). Dikerjakan setelah Stock Out.',
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $db = DB::connection('tm2sys');

        $db->table('sys_app_menu')->where('code', 'PROC_MON')->update(['label' => 'Monitoring']);
        $db->table('sys_app_menu')->where('code', 'PROC_MON_PR')->update(['label' => 'Monitoring PR', 'target' => 'grid_monitoring_pr.php', 'target_kind' => null, 'status_code' => 'NOT_BUILT']);
        $db->table('sys_app_menu')->where('code', 'PROC_MON_PO')->update(['label' => 'Monitoring PO', 'target' => 'grid_monitoring_po.php', 'target_kind' => null, 'status_code' => 'NOT_BUILT']);
        $db->table('sys_app_menu')->where('code', 'PROC_MON_PV')->delete();
    }
};
