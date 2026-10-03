<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// User 2026-10-04: "pejabat sementara harus di module HR bukan di setting ... tulis yang jelas pejabat pengganti sementara".
// Acting Officer (PLT) leaves Setup > Security and becomes HRM > Organization > Temporary Acting Officer (after Master Position).
// Row exists on ddt only (tm2 never had the v2 approval rows); on tm2 nothing happens.
return new class extends Migration
{
    protected $connection = 'tm2sys';

    private function menu()
    {
        return DB::connection('tm2sys')->table('sys_app_menu');
    }

    public function up(): void
    {
        $org = $this->menu()->where('code', 'HRM_ORG')->first();
        if (! $org || ! $this->menu()->where('code', 'SET_APPROVAL_PLT')->exists()) {
            return;
        }
        $this->menu()->where('code', 'SET_APPROVAL_PLT')->update(['code' => 'HRM_ACTING_OFFICER', 'parent_id' => $org->id, 'label' => 'Temporary Acting Officer',
            'icon' => 'fa-solid fa-people-arrows', 'sort_order' => 15, 'status_note' => 'v2 (moved from Setup > Security 2026-10-04)']);

        if (Schema::connection('tm2sys')->hasTable('sys_dictionary')) {
            $db = DB::connection('tm2sys');
            $db->table('sys_dictionary')->insertOrIgnore(['context' => 'ui', 'source_en' => 'Temporary Acting Officer', 'group_code' => 'menu', 'created_at' => now(), 'created_by' => 'migration']);
            $key = $db->table('sys_dictionary')->where('context', 'ui')->where('source_en', 'Temporary Acting Officer')->value('id_dictionary');
            $db->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $key, 'lang_code' => 'id'], ['text' => 'Pejabat Pengganti Sementara']);
            Dictionary::flush();
        }
    }

    public function down(): void
    {
        $sec = $this->menu()->where('code', 'SET_GRP_SECURITY')->first();
        if ($sec) {
            $this->menu()->where('code', 'HRM_ACTING_OFFICER')->update(['code' => 'SET_APPROVAL_PLT', 'parent_id' => $sec->id, 'label' => 'Acting Officer (PLT)', 'sort_order' => 60]);
        }
    }
};
