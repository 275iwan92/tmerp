<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// Permintaan user 2026-09-03: 7 akun contoh UAT lintas 4 level (skala
// sec_group.mgmt_level, CLAUDE.md konsep "Level x Bidang" dari diskusi
// Fase 2 Report System 2026-09-02): operator(1)/supervs(2)/
// mgr-sal,mgr-pur,mgr-inv,mgr-acc(3, 1 per bidang)/direksi(existing grup,
// level 5 -- "level 4" di penomoran user cuma label UAT, DB tetap skala 1-5
// asli). Semua password sama 'contoh!23' supaya gampang dicoba tim UAT.
//
// Level 3 (Manager per-bidang) belum ada grupnya sama sekali sebelum ini --
// PROD_MGR("Manager Produksi") yang sudah ada BUKAN salah satu yang diminta
// (user minta Sales/Purchasing/Inventory/Accounting, bukan Produksi), jadi
// 4 grup baru MGR_SALES/MGR_PUR/MGR_INV/MGR_ACC dibuat dari nol.
//
// Sekalian PERBAIKAN GAP nyata yang ketemu saat investigasi: SALES_STAFF &
// SALES_SPV TIDAK punya akses modul PROD sama sekali -- padahal menu
// "Pro-Dev" baru saja dipindah masuk ke sidebar Sales (migration
// 2026_09_03_000001) dan LANGSUNG akan 403 kalau diklik staf/supervisor
// Sales biasa. Ditambahkan di sini supaya akun `operator`/`supervs` UAT
// benar-benar bisa coba alur Inquiry->Pro-Dev->Pro-Cost end-to-end.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        $now = now();

        // --- 1. Grup baru Level 3 per-bidang ---
        $nextGroupId = (int) $sys->table('sec_group')->max('id_group') + 1;
        $groups = [
            ['code' => 'MGR_SALES', 'name' => 'Manager Sales'],
            ['code' => 'MGR_PUR', 'name' => 'Manager Purchasing'],
            ['code' => 'MGR_INV', 'name' => 'Manager Inventory'],
            ['code' => 'MGR_ACC', 'name' => 'Manager Finance & Accounting'],
        ];
        $groupIds = [];
        foreach ($groups as $i => $g) {
            $existing = $sys->table('sec_group')->where('group_code', $g['code'])->value('id_group');
            if ($existing) {
                $groupIds[$g['code']] = $existing;

                continue;
            }
            $id = $nextGroupId + $i;
            $sys->table('sec_group')->insert([
                'id_group' => $id,
                'group_code' => $g['code'],
                'group_name' => $g['name'],
                'mgmt_level' => 3,
                'activate' => true,
                'created_by' => 'claude_code',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $groupIds[$g['code']] = $id;
        }

        $direksiId = $sys->table('sec_group')->where('group_code', 'DIREKSI')->value('id_group');
        $salesStaffId = $sys->table('sec_group')->where('group_code', 'SALES_STAFF')->value('id_group');
        $salesSpvId = $sys->table('sec_group')->where('group_code', 'SALES_SPV')->value('id_group');

        // --- 2. FIX gap: SALES_STAFF/SALES_SPV perlu akses modul PROD (Pro-Dev) ---
        $modules = $sys->table('sec_module')->whereIn('module_code', ['SALES', 'PROC', 'INV', 'PROD', 'MDATA', 'FINACC', 'COSTING'])
            ->pluck('id_module', 'module_code');

        $this->grantPermission($sys, $salesStaffId, $modules['PROD'], canApprove: false);
        $this->grantPermission($sys, $salesSpvId, $modules['PROD'], canApprove: true);

        // --- 3. Permission modul per grup manager baru ---
        $grants = [
            'MGR_SALES' => ['SALES', 'COSTING', 'PROD', 'MDATA'],
            'MGR_PUR' => ['PROC', 'MDATA'],
            'MGR_INV' => ['INV', 'MDATA'],
            'MGR_ACC' => ['FINACC', 'MDATA'],
        ];
        foreach ($grants as $code => $moduleCodes) {
            foreach ($moduleCodes as $mc) {
                $this->grantPermission($sys, $groupIds[$code], $modules[$mc], canApprove: true);
            }
        }

        // --- 4. 7 akun user UAT, password sama 'contoh!23' ---
        $pswdHash = Hash::make('contoh!23');
        $motherHash = Hash::make('ibu uat');
        $answerHash = Hash::make('jakarta');

        $accounts = [
            ['login' => 'operator', 'name' => 'UAT Operator (Level 1)', 'group_id' => $salesStaffId],
            ['login' => 'supervs', 'name' => 'UAT Supervisor (Level 2)', 'group_id' => $salesSpvId],
            ['login' => 'mgr-sal', 'name' => 'UAT Manager Sales (Level 3)', 'group_id' => $groupIds['MGR_SALES']],
            ['login' => 'mgr-pur', 'name' => 'UAT Manager Purchasing (Level 3)', 'group_id' => $groupIds['MGR_PUR']],
            ['login' => 'mgr-inv', 'name' => 'UAT Manager Inventory (Level 3)', 'group_id' => $groupIds['MGR_INV']],
            ['login' => 'mgr-acc', 'name' => 'UAT Manager Finance & Accounting (Level 3)', 'group_id' => $groupIds['MGR_ACC']],
            ['login' => 'direksi', 'name' => 'UAT Direksi (Level 4)', 'group_id' => $direksiId],
        ];

        $nextUserId = (int) $sys->table('sec_user')->max('id_user') + 1;
        foreach ($accounts as $i => $a) {
            if ($sys->table('sec_user')->where('login', $a['login'])->exists()) {
                continue;
            }
            $userId = $nextUserId + $i;
            $sys->table('sec_user')->insert([
                'id_user' => $userId,
                'login' => $a['login'],
                'pswd' => $pswdHash,
                'name' => $a['name'],
                'email' => $a['login'].'@uat.telasih.co.id',
                'phone' => '+6280000000'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'active' => true,
                'activate' => true,
                'is_admin' => false,
                'must_change_password' => false,
                'approval_status' => 'approved',
                'birth_date' => '1990-01-01',
                'mother_name_hash' => $motherHash,
                'security_question_id' => 1,
                'security_answer_hash' => $answerHash,
                'security_setup_at' => $now,
                'created_by' => 'claude_code',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $sys->table('sec_user_group')->insert([
                'user_id' => $userId,
                'group_id' => $a['group_id'],
                'created_at' => $now,
            ]);
        }
    }

    private function grantPermission($sys, int $groupId, int $moduleId, bool $canApprove): void
    {
        $sys->table('sec_group_permission')->updateOrInsert(
            ['group_id' => $groupId, 'module_id' => $moduleId],
            [
                'can_access' => true,
                'can_input' => true,
                'can_approve' => $canApprove,
                'can_export' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');

        $logins = ['operator', 'supervs', 'mgr-sal', 'mgr-pur', 'mgr-inv', 'mgr-acc', 'direksi'];
        $userIds = $sys->table('sec_user')->whereIn('login', $logins)->pluck('id_user');
        $sys->table('sec_user_group')->whereIn('user_id', $userIds)->delete();
        $sys->table('sec_user')->whereIn('login', $logins)->delete();

        $groupCodes = ['MGR_SALES', 'MGR_PUR', 'MGR_INV', 'MGR_ACC'];
        $groupIds = $sys->table('sec_group')->whereIn('group_code', $groupCodes)->pluck('id_group');
        $sys->table('sec_group_permission')->whereIn('group_id', $groupIds)->delete();
        $sys->table('sec_group')->whereIn('group_code', $groupCodes)->delete();
    }
};
