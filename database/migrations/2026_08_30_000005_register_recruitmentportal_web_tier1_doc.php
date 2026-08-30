<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// docs/recruitmentportal_web_tier1.md -- Portal Rekrutmen Profesional (web),
// kanal Laravel+Firebase publik menggantikan simulasi lama di URL yang sama.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        if (! $sys->table('sys_doc_reference')->where('file_name', 'recruitmentportal_web_tier1.md')->exists()) {
            $sys->table('sys_doc_reference')->insert([
                'file_name' => 'recruitmentportal_web_tier1.md',
                'title' => 'Portal Rekrutmen Profesional (Web)',
                'category' => 'STANDARD',
                'related_menu_code' => 'HRM_RCR_PORTAL',
                'description' => 'Portal kandidat publik (Laravel+Firebase), akun real, di /hrm/recruitment/portal -- menggantikan simulasi KTP-only lama.',
                'sort_order' => 174,
                'activate' => true,
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_doc_reference')->where('file_name', 'recruitmentportal_web_tier1.md')->delete();
    }
};
