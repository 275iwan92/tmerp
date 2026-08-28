<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Permintaan user 2026-08-28: user lihat 2 tab "MPS Kanban Board" (kosong,
// blank) dan "MPS - Cards" (isinya kanban beneran, data live). Ditelusuri:
// PROD_MPS_KANBAN adalah row nyasar -- ada di tm2sys.sys_app_menu (id 993,
// dibuat 2026-08-28 12:45:42) tapi TIDAK PERNAH lewat migration manapun di
// repo (status_updated_by/at NULL, beda dari row resmi lain), dan target-nya
// NULL sehingga tak ada mapping route di main-menu.blade.php -- makanya
// tampil kosong. Kanban yang benar = PROD_MON_WO (Screen #3 monitoring
// dashboard, dirombak jadi Kanban ala Trello di commit 2a68b1d), labelnya
// masih "MPS - Cards" peninggalan sebelum rombak. Fix: hapus row nyasar,
// rename label PROD_MON_WO supaya sesuai isi aslinya.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        $sys->table('sys_app_menu')->where('code', 'PROD_MPS_KANBAN')->delete();

        $sys->table('sys_app_menu')->where('code', 'PROD_MON_WO')->update([
            'label' => 'MPS - Kanban Board',
        ]);
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');
        $planId = $sys->table('sys_app_menu')->where('code', 'PROD_PLAN')->value('id');

        $sys->table('sys_app_menu')->where('code', 'PROD_MON_WO')->update([
            'label' => 'MPS - Cards',
        ]);

        $sys->table('sys_app_menu')->insert([
            'parent_id' => $planId,
            'code' => 'PROD_MPS_KANBAN',
            'label' => 'MPS Kanban Board',
            'trans_key' => null,
            'icon' => null,
            'menu_type' => 'item',
            'object_type' => 'grid',
            'target' => null,
            'target_kind' => 'app',
            'sort_order' => 11,
            'activate' => true,
            'required_object' => null,
            'created_at' => now(),
            'status_code' => 'READY_FOR_QC',
            'status_note' => null,
            'status_updated_by' => null,
            'status_updated_at' => null,
            'doc_slug' => null,
        ]);
    }
};
