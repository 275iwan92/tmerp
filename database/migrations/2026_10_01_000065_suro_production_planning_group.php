<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Officer PPIC (user 2026-10-01, ralat: bukan "Soleh" tapi Suro): Tia, Fardan, Suro Nur Widianto (TM-00142).
// Tia & Fardan sudah masuk grup PROD_PLANNING lewat migration 000061; ini menambahkan Suro (akun login-nya sudah ada).
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        $employeeId = DB::connection('tm2mst')->table('emp_employee')->where('employee_code', 'TM-00142')->value('employee_id');
        $userId = $employeeId ? $sys->table('sec_user')->where('employee_id', $employeeId)->value('id_user') : null;
        $groupId = $sys->table('sec_group')->where('group_code', 'PROD_PLANNING')->value('id_group');
        if ($userId && $groupId) {
            $sys->table('sec_user_group')->insertOrIgnore(['user_id' => $userId, 'group_id' => $groupId, 'created_at' => now()]);
        }
    }

    public function down(): void
    {
        // keanggotaan grup dibiarkan
    }
};
