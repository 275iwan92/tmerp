<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Menu v2 Setup > Settings > Translation Dictionary (LOCKED user 2026-10-01: DB English, kamus menerjemahkan layar/cetak).
// Layar = App\Support\V2\Grid\Setup\TranslationDictionaryLookup (Grid Lookup DevLib, isi langsung di sel).
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tm2sys');
        $group = $db->table('sys_app_menu')->where('code', 'SET_GRP_SETTINGS')->value('id');
        if ($group && ! $db->table('sys_app_menu')->where('code', 'SET_DICTIONARY')->exists()) {
            $db->table('sys_app_menu')->insert(['parent_id' => $group, 'code' => 'SET_DICTIONARY', 'label' => 'Translation Dictionary', 'menu_type' => 'item',
                'object_type' => 'grid', 'target' => 'v2:translation-dictionary', 'target_kind' => 'app', 'sort_order' => 44, 'activate' => 1, 'created_at' => now(),
                'status_code' => 'READY_FOR_QC', 'status_note' => 'v2 Translation Dictionary (sys_dictionary)', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'SET_DICTIONARY')->delete();
    }
};
