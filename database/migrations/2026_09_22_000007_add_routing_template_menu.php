<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Menu item untuk layar baru Master Routing Template. Ditaruh di grup yang
// sama dengan Master Proses (parent_id 410 = grup Master Data produksi),
// sort_order 31 supaya persis di bawah Master Proses (30) -- template adalah
// lanjutan langsung dari master proses, bukan modul terpisah.
//
// status_code READY_FOR_QC, BUKAN LOCKED: hanya user yang boleh menaikkan ke
// LOCKED setelah QC sendiri (docs/globalrules.yaml -> module_status_system).
return new class extends Migration
{
    private const CODE = 'PROD_ROUTING_TPL';

    public function up(): void
    {
        $db = DB::connection('tm2sys');

        if ($db->table('sys_app_menu')->where('code', self::CODE)->exists()) {
            return;
        }

        $parentId = $db->table('sys_app_menu')->where('code', 'PROD_MST_PROCESS')->value('parent_id');

        // sys_app_menu.id tidak AUTO_INCREMENT (skema warisan ScriptCase),
        // jadi id harus dihitung sendiri -- pola sama seperti
        // 2026_09_21_000005_add_employee_contact_gap_menu.
        $db->table('sys_app_menu')->insert([
            'id' => (int) $db->table('sys_app_menu')->max('id') + 1,
            'parent_id' => $parentId,
            'code' => self::CODE,
            'label' => 'Master Routing Template',
            'menu_type' => 'item',
            'object_type' => 'grid',
            'target_kind' => 'app',
            'sort_order' => 31,
            'activate' => 1,
            'status_code' => 'READY_FOR_QC',
            'status_updated_by' => 'claude_code',
            'status_updated_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', self::CODE)->delete();
    }
};
