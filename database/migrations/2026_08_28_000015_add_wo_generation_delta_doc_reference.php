<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Daftarkan docs/production_scheduling_wo_generation_delta.md ke
// sys_doc_reference -- delta F1 WO Generation dari Plan Delivery + 3 layar
// WO (picklist/detail BOP-BOM/monitoring kartu) + print form, 2026-08-28.
// Pola sama migration 2026_08_26_000004_add_matcategory_delta_doc_reference.php
// (kolom aktual `activate`, BUKAN `is_active` -- sudah di-rename di tm2sys
// per DEC-FIELDNAME-STATUS, jangan copy migration lama verbatim).
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        if ($sys->table('sys_doc_reference')->where('file_name', 'production_scheduling_wo_generation_delta.md')->exists()) {
            return;
        }

        $nextSort = (int) $sys->table('sys_doc_reference')->max('sort_order') + 1;

        $sys->table('sys_doc_reference')->insert([
            'file_name' => 'production_scheduling_wo_generation_delta.md',
            'title' => 'Delta WO Generation dari Plan Delivery + Layar Work Order',
            'category' => 'ENGINE',
            'related_menu_code' => 'PROD_WO_LIST',
            'sort_order' => $nextSort,
            'activate' => true,
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_doc_reference')
            ->where('file_name', 'production_scheduling_wo_generation_delta.md')
            ->delete();
    }
};
