<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// production_scheduling_tier1.md §9.7/Tier2 §9 permission matrix menyebut role
// approval "QA", "Manager Produksi", "Manager Operasional" -- belum ada satupun
// sec_group untuk ini (cuma DIREKSI/PRODUKSI generik/SALES_STAFF/SALES_SPV).
// Tambah 3 grup baru + grant akses modul PROD, konsisten pola existing
// (SecUser::groups()/maxMgmtLevel()/canAccessModule() -- bukan infra baru).
return new class extends Migration
{
    private array $groups = [
        ['group_code' => 'QA', 'group_name' => 'Quality Assurance', 'mgmt_level' => 1],
        ['group_code' => 'PROD_MGR', 'group_name' => 'Manager Produksi', 'mgmt_level' => 3],
        ['group_code' => 'OPS_MGR', 'group_name' => 'Manager Operasional', 'mgmt_level' => 4],
    ];

    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        foreach ($this->groups as $group) {
            $sys->table('sec_group')->updateOrInsert(
                ['group_code' => $group['group_code']],
                [
                    'group_name' => $group['group_name'],
                    'mgmt_level' => $group['mgmt_level'],
                    'activate' => true,
                    'created_by' => 'system',
                ]
            );
        }

        $prodModuleId = $sys->table('sec_module')->where('module_code', 'PROD')->value('id_module');

        // PRODUKSI (staf PPIC/produksi generik) + 3 grup baru semua butuh akses
        // modul PROD -- hanya DIREKSI yang ter-grant sejauh ini (2026_08_28_000002).
        $groupCodes = array_merge(['PRODUKSI'], array_column($this->groups, 'group_code'));

        foreach ($groupCodes as $code) {
            $groupId = $sys->table('sec_group')->where('group_code', $code)->value('id_group');
            if (! $groupId || ! $prodModuleId) {
                continue;
            }

            $sys->table('sec_group_permission')->updateOrInsert(
                ['group_id' => $groupId, 'module_id' => $prodModuleId],
                ['can_access' => true, 'can_input' => true, 'can_approve' => true, 'can_export' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');

        $groupIds = $sys->table('sec_group')->whereIn('group_code', array_column($this->groups, 'group_code'))->pluck('id_group');
        $sys->table('sec_group_permission')->whereIn('group_id', $groupIds)->delete();
        $sys->table('sec_group')->whereIn('group_code', array_column($this->groups, 'group_code'))->delete();
    }
};
