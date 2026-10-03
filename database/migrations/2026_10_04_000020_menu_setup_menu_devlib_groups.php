<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Setup groups 8-11 (user 2026-10-04, follow-up of 2026_10_04_000010): 8 Menu (Menu Setup), 9 DevLib (DevLib UI UX),
// 10 Identification, 11 Others (only the inactive v1 Theme for now). Runs on ddt* (--env=mockup) and tm2*.
return new class extends Migration
{
    protected $connection = 'tm2sys';

    // code => [label, icon, sort, Indonesian label, item codes]
    private const GROUPS = [
        'SET_GRP_MENU' => ['Menu', 'fa-solid fa-bars-staggered', 800, 'Menu', ['SET_MENU']],
        'SET_GRP_DEVLIB' => ['DevLib', 'fa-solid fa-shapes', 900, 'DevLib', ['SET_UILIB']],
        'SET_GRP_IDENT' => ['Identification', 'fa-solid fa-id-badge', 1000, 'Identifikasi', ['SET_CONFIG']],
        'SET_GRP_OTHERS' => ['Others', 'fa-solid fa-ellipsis', 1100, 'Lainnya', ['SET_THEME']],
    ];

    private function menu()
    {
        return DB::connection('tm2sys')->table('sys_app_menu');
    }

    public function up(): void
    {
        $others = $this->menu()->where('code', 'SET_GRP_OTHERS')->first();
        if (! $others) {
            return;
        }
        foreach (self::GROUPS as $code => [$label, $icon, $sort, , $items]) {
            $row = ['label' => $label, 'icon' => $icon, 'sort_order' => $sort, 'menu_type' => 'group', 'activate' => 1];
            if ($id = $this->menu()->where('code', $code)->value('id')) {
                $this->menu()->where('id', $id)->update($row);
            } else {
                $id = $this->menu()->insertGetId($row + ['parent_id' => $others->parent_id, 'code' => $code, 'target_kind' => 'app',
                    'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(), 'created_at' => now()]);
            }
            foreach ($items as $i => $item) {
                $this->menu()->where('code', $item)->update(['parent_id' => $id, 'sort_order' => ($i + 1) * 10]);
            }
        }

        if (Schema::connection('tm2sys')->hasTable('sys_dictionary')) {
            $db = DB::connection('tm2sys');
            foreach (self::GROUPS as [$en, , , $id]) {
                $db->table('sys_dictionary')->insertOrIgnore(['context' => 'ui', 'source_en' => $en, 'group_code' => 'menu', 'created_at' => now(), 'created_by' => 'migration']);
                $key = $db->table('sys_dictionary')->where('context', 'ui')->where('source_en', $en)->value('id_dictionary');
                if (! $db->table('sys_dictionary_lang')->where('id_dictionary', $key)->where('lang_code', 'id')->where('text', '<>', '')->exists()) {
                    $db->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $key, 'lang_code' => 'id'], ['text' => $id]);
                }
            }
            Dictionary::flush();
        }
    }

    public function down(): void
    {
        $others = $this->menu()->where('code', 'SET_GRP_OTHERS')->first();
        if (! $others) {
            return;
        }
        $this->menu()->where('code', 'SET_MENU')->update(['parent_id' => $others->id, 'sort_order' => 20]);
        $this->menu()->where('code', 'SET_UILIB')->update(['parent_id' => $others->id, 'sort_order' => 40]);
        $this->menu()->where('code', 'SET_CONFIG')->update(['parent_id' => $others->id, 'sort_order' => 10]);
        $this->menu()->where('code', 'SET_THEME')->update(['sort_order' => 30]);
        $this->menu()->whereIn('code', ['SET_GRP_MENU', 'SET_GRP_DEVLIB', 'SET_GRP_IDENT'])->delete();
        $this->menu()->where('id', $others->id)->update(['sort_order' => 800]);
    }
};
