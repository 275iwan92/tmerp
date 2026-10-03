<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// User 2026-10-04: Security = Accounts, Groups, Modules (new v2 lookup over sec_module), Groups on Modules, Approval Levels
// (was Setting Approval), Authorities (not yet defined by the user -- no row until it is). Acting Officer (PLT) stays last
// until the user decides. DevLib group + DevLib UI UX item are removed for good (devlib.test replaces that screen).
// Runs on ddt* (--env=mockup) and tm2*.
return new class extends Migration
{
    protected $connection = 'tm2sys';

    // code => [label, sort, Indonesian label]
    private const ITEMS = [
        'SET_USER' => ['Accounts', 10, 'Akun'],
        'SET_GROUP' => ['Groups', 20, 'Grup'],
        'SET_MODULE' => ['Modules', 30, 'Modul'],
        'SET_PERM' => ['Groups on Modules', 40, 'Grup per Modul'],
        'SET_APPROVAL' => ['Approval Levels', 50, 'Tingkat Persetujuan'],
        'SET_APPROVAL_PLT' => ['Acting Officer (PLT)', 60, 'Pejabat Pengganti (PLT)'],
    ];

    private function menu()
    {
        return DB::connection('tm2sys')->table('sys_app_menu');
    }

    public function up(): void
    {
        $security = $this->menu()->where('code', 'SET_GRP_SECURITY')->first();
        if (! $security) {
            return;
        }
        foreach (self::ITEMS as $code => [$label, $sort]) {
            if ($code === 'SET_MODULE' && ! $this->menu()->where('code', $code)->exists()) {
                $this->menu()->insert(['parent_id' => $security->id, 'code' => $code, 'label' => $label, 'icon' => 'fa-solid fa-cubes', 'menu_type' => 'item',
                    'object_type' => 'grid', 'target' => 'v2:security-modules', 'target_kind' => 'app', 'sort_order' => $sort, 'activate' => 1,
                    'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(), 'created_at' => now()]);

                continue;
            }
            $this->menu()->where('code', $code)->update(['parent_id' => $security->id, 'label' => $label, 'sort_order' => $sort]);
        }

        $this->menu()->where('code', 'SET_UILIB')->delete();
        $this->menu()->where('code', 'SET_GRP_DEVLIB')->delete();

        if (Schema::connection('tm2sys')->hasTable('sys_dictionary')) {
            $db = DB::connection('tm2sys');
            foreach (self::ITEMS as [$en, , $id]) {
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
        $this->menu()->where('code', 'SET_USER')->update(['label' => 'Account']);
        $this->menu()->where('code', 'SET_GROUP')->update(['label' => 'Group']);
        $this->menu()->where('code', 'SET_PERM')->update(['label' => 'Group per Submodule', 'sort_order' => 30]);
        $this->menu()->where('code', 'SET_APPROVAL')->update(['label' => 'Setting Approval', 'sort_order' => 40]);
        $this->menu()->where('code', 'SET_APPROVAL_PLT')->update(['sort_order' => 50]);
        $this->menu()->where('code', 'SET_MODULE')->delete();
        // DevLib rows are not restored: the screen was removed from the code.
    }
};
