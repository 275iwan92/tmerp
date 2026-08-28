<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Worklist WO, Rule Library, MTS Forecast Entry (production_scheduling_tier4.md
// §1) -- 3 layar baru di bawah grup PROD_PLAN (induk sudah ada: PROD_MPS/
// PROD_MRP). Disisipkan di antara keduanya (sort_order 15), pola sama migration
// 2026_08_27_000015_add_hrm_correction_requests_menu.php.
return new class extends Migration
{
    private array $items = [
        ['code' => 'PROD_WO_LIST', 'label' => 'Worklist Work Order', 'sort_order' => 15],
        ['code' => 'PROD_RULE_LIB', 'label' => 'Rule Library', 'sort_order' => 16],
        ['code' => 'PROD_MTS_FORECAST', 'label' => 'Forecast MTS', 'sort_order' => 17],
    ];

    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        $planId = $sys->table('sys_app_menu')->where('code', 'PROD_PLAN')->value('id');
        if (! $planId) {
            return;
        }

        $nextId = (int) $sys->table('sys_app_menu')->max('id') + 1;

        foreach ($this->items as $item) {
            if ($sys->table('sys_app_menu')->where('code', $item['code'])->exists()) {
                continue;
            }

            $sys->table('sys_app_menu')->insert([
                'id' => $nextId,
                'parent_id' => $planId,
                'code' => $item['code'],
                'label' => $item['label'],
                'menu_type' => 'item',
                'object_type' => 'grid',
                'target_kind' => 'app',
                'sort_order' => $item['sort_order'],
                'activate' => true,
                // NOT_BUILT sengaja -- flip ke READY_FOR_QC di migration terpisah
                // SETELAH tiap layar sungguhan diverifikasi jalan (bukan diklaim
                // duluan), pola sama seed_prd_scheduling_board_dummy.php.
                'status_code' => 'NOT_BUILT',
                'created_at' => now(),
            ]);
            $nextId++;
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->whereIn('code', array_column($this->items, 'code'))
            ->delete();
    }
};
