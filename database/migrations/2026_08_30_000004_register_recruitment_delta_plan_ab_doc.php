<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// docs/recruitment_delta_plan_ab_local.md -- delta atas recruitment_tier3.md
// §1-3 (skema crp_submission diperluas, interface diperluas, Firebase pakai
// Local Emulator). Pola sama itemmaster_delta.md/matcategory_delta.md.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        if (! $sys->table('sys_doc_reference')->where('file_name', 'recruitment_delta_plan_ab_local.md')->exists()) {
            $sys->table('sys_doc_reference')->insert([
                'file_name' => 'recruitment_delta_plan_ab_local.md',
                'title' => 'Delta Portal Kandidat Plan A/B (implementasi lokal)',
                'category' => 'REFERENCE',
                'related_menu_code' => null,
                'description' => 'Delta atas recruitment_tier3.md §1-3: skema crp_submission diperluas, interface adapter ditambah sisi-portal, Firebase pakai Local Emulator.',
                'sort_order' => 173,
                'activate' => true,
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_doc_reference')->where('file_name', 'recruitment_delta_plan_ab_local.md')->delete();
    }
};
