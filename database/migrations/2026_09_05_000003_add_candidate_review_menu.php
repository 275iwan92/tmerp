<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Review Formulir Kandidat -- layar baru utk HR meninjau isian lengkap
// "Data Calon Karyawan" (careers/apply, 11 seksi) per lamaran, read-only.
// Sebelumnya tidak ada satupun layar internal yang menampilkan isian
// lengkap ini (Talent Pool cuma riwayat status, Pipeline Board cuma
// nama+KTP per kartu). Ditaruh di grup Recruitment, antara Talent Pool
// (30) & Pipeline Board (40).
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        if ($sys->table('sys_app_menu')->where('code', 'HRM_RCR_REVIEW')->exists()) {
            return;
        }

        $groupId = $sys->table('sys_app_menu')->where('code', 'HRM_RECRUITMENT')->value('id');
        $nextId = (int) $sys->table('sys_app_menu')->max('id') + 1;

        $sys->table('sys_app_menu')->insert([
            'id' => $nextId,
            'parent_id' => $groupId,
            'code' => 'HRM_RCR_REVIEW',
            'label' => 'Review Formulir Kandidat',
            'menu_type' => 'item',
            'object_type' => 'grid',
            'target_kind' => 'app',
            'sort_order' => 35,
            'activate' => true,
            'status_code' => 'READY_FOR_QC',
            'status_updated_by' => 'claude_code',
            'status_updated_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'HRM_RCR_REVIEW')->delete();
    }
};
