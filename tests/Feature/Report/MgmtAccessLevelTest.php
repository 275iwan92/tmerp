<?php

namespace Tests\Feature\Report;

use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Instruksi eksplisit user 2026-09-13: "check level dulu, kalau Operational
// Manager keatas -> default Management Report, kalau kebawah -> Operational
// Menu". Ini butuh 2 hal jalan bareng: (1) canAccessModule('MGMT') dipakai
// login.blade.php utk menentukan landing default, (2) min_mgmt_level modul
// MGMT harus BENAR-BENAR diturunkan (migration 2026_09_13_000011) supaya
// user level 4 tidak didaratkan ke halaman yang lalu 403.
class MgmtAccessLevelTest extends TestCase
{
    public function test_mgmt_module_min_level_lowered_to_operational_manager(): void
    {
        $level = DB::connection('tm2sys')->table('sec_module')->where('module_code', 'MGMT')->value('min_mgmt_level');

        $this->assertSame(4, $level, 'Manager Operasional (level 4) harus bisa akses MGMT, bukan cuma Direksi (5).');
    }

    public function test_operational_manager_group_granted_mgmt_access(): void
    {
        $moduleId = DB::connection('tm2sys')->table('sec_module')->where('module_code', 'MGMT')->value('id_module');
        $groupId = DB::connection('tm2sys')->table('sec_group')->where('group_name', 'Manager Operasional')->value('id_group');

        $perm = DB::connection('tm2sys')->table('sec_group_permission')
            ->where('module_id', $moduleId)->where('group_id', $groupId)->first();

        $this->assertNotNull($perm, 'Grup Manager Operasional harus punya baris sec_group_permission utk MGMT.');
        $this->assertSame(1, (int) $perm->can_access);
    }

    public function test_functional_manager_level_3_still_denied_mgmt_access(): void
    {
        // Manager Sales/Produksi/Purchasing/Inventory/Finance & Accounting
        // semua level 3 -- HANYA "Manager Operasional" (level 4) yang naik,
        // bukan "semua Manager" (threshold di pesan user spesifik "Operational
        // Manager keatas").
        $mgrSales = SecUser::where('login', 'mgr-sal')->first();
        $this->assertNotNull($mgrSales, 'Fixture mgr-sal tidak ditemukan -- sesuaikan login user test.');
        $this->assertSame(3, $mgrSales->maxMgmtLevel());
        $this->assertFalse($mgrSales->canAccessModule('MGMT'));
    }

    public function test_direksi_and_admin_still_have_mgmt_access(): void
    {
        $direksi = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->assertTrue($direksi->canAccessModule('MGMT'));
    }

    public function test_user_placed_in_operational_manager_group_gains_mgmt_access(): void
    {
        $groupId = DB::connection('tm2sys')->table('sec_group')->where('group_name', 'Manager Operasional')->value('id_group');
        $user = SecUser::where('login', 'mgr-sal')->firstOrFail();

        // Pinjam akun mgr-sal sbg simulasi "Manager Operasional" sementara --
        // capture-then-restore exact prior state (feedback_tinker_restore_assumed_state).
        $originalGroupIds = $user->groups()->pluck('sec_group.id_group')->all();

        try {
            $user->groups()->sync([$groupId]);
            $user->refresh();

            $this->assertSame(4, $user->maxMgmtLevel());
            $this->assertTrue($user->canAccessModule('MGMT'));
        } finally {
            $user->groups()->sync($originalGroupIds);
        }
    }
}
