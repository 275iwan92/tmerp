<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Setup menu regrouped (user 2026-10-04): the two old groups Settings (23 items) + Security (5) become eight groups
// fixed by the user -- Security, Support & Ticket, Help and Support, Language and Localization, Dummy Data & Migration,
// Constants, Dashboard and Widgets, Others. The old Settings row is reused as "Others" (same id, so nothing that points
// to it breaks); Theme (inactive v1 item that sat directly under the module) moves into Others and stays off.
// Runs on ddt* (--env=mockup) and tm2*: items missing on one site (e.g. Setting Approval on tm2) are simply skipped.
return new class extends Migration
{
    protected $connection = 'tm2sys';

    // code => [label, icon, sort, Indonesian label for the dictionary]
    private const GROUPS = [
        'SET_GRP_SECURITY' => ['Security', 'fa-solid fa-shield-halved', 100, 'Keamanan'],
        'SET_GRP_SUPPORT' => ['Support & Ticket', 'fa-solid fa-ticket', 200, 'Dukungan & Tiket'],
        'SET_GRP_HELP' => ['Help and Support', 'fa-solid fa-circle-question', 300, 'Bantuan dan Dukungan'],
        'SET_GRP_LANG' => ['Language and Localization', 'fa-solid fa-language', 400, 'Bahasa dan Lokalisasi'],
        'SET_GRP_DUMMY' => ['Dummy Data & Migration', 'fa-solid fa-flask', 500, 'Data Dummy & Migrasi'],
        'SET_GRP_CONSTANTS' => ['Constants', 'fa-solid fa-sliders', 600, 'Konstanta'],
        'SET_GRP_DASHBOARD' => ['Dashboard and Widgets', 'fa-solid fa-gauge-high', 700, 'Dasbor dan Widget'],
        'SET_GRP_OTHERS' => ['Others', 'fa-solid fa-ellipsis', 800, 'Lainnya'],
    ];

    // item code => [group code, sort]
    private const ITEMS = [
        'SET_USER' => ['SET_GRP_SECURITY', 10], 'SET_GROUP' => ['SET_GRP_SECURITY', 20], 'SET_PERM' => ['SET_GRP_SECURITY', 30],
        'SET_APPROVAL' => ['SET_GRP_SECURITY', 40], 'SET_APPROVAL_PLT' => ['SET_GRP_SECURITY', 50],
        'SET_TICKETS' => ['SET_GRP_SUPPORT', 10],
        'SET_HELP_TOPIC' => ['SET_GRP_HELP', 10], 'SET_HELP_LINE' => ['SET_GRP_HELP', 20], 'SET_ASSISTANT_Q' => ['SET_GRP_HELP', 30], 'SET_ASSISTANT_LOG' => ['SET_GRP_HELP', 40],
        'SET_LANGUAGES' => ['SET_GRP_LANG', 10], 'SET_LANG' => ['SET_GRP_LANG', 20], 'SET_LOCALIZATION' => ['SET_GRP_LANG', 30],
        'SET_DICTIONARY' => ['SET_GRP_LANG', 40], 'SET_DICT_GROUPS' => ['SET_GRP_LANG', 50],
        'SET_DATA_ORIGIN' => ['SET_GRP_DUMMY', 10], 'SET_MIGRATION' => ['SET_GRP_DUMMY', 20],
        'SET_CONSTANTS' => ['SET_GRP_CONSTANTS', 10], 'SET_CONSTANT_CAT' => ['SET_GRP_CONSTANTS', 20], 'SET_DOC_SERIES' => ['SET_GRP_CONSTANTS', 30],
        'SET_DASHBOARDS' => ['SET_GRP_DASHBOARD', 10], 'SET_WIDGETS' => ['SET_GRP_DASHBOARD', 20], 'SET_BOARD_TEMPLATE' => ['SET_GRP_DASHBOARD', 30],
        'SET_KPI_TARGETS' => ['SET_GRP_DASHBOARD', 40], 'SET_DAILY_QUOTES' => ['SET_GRP_DASHBOARD', 50],
        'SET_CONFIG' => ['SET_GRP_OTHERS', 10], 'SET_MENU' => ['SET_GRP_OTHERS', 20], 'SET_THEME' => ['SET_GRP_OTHERS', 30], 'SET_UILIB' => ['SET_GRP_OTHERS', 40],
    ];

    private function menu()
    {
        return DB::connection('tm2sys')->table('sys_app_menu');
    }

    public function up(): void
    {
        $settings = $this->menu()->where('code', 'SET_GRP_SETTINGS')->first();
        if (! $settings) {
            return;
        }
        $this->menu()->where('id', $settings->id)->update(['code' => 'SET_GRP_OTHERS']);

        $ids = [];
        foreach (self::GROUPS as $code => [$label, $icon, $sort]) {
            $row = ['label' => $label, 'icon' => $icon, 'sort_order' => $sort, 'menu_type' => 'group', 'activate' => 1];
            if ($id = $this->menu()->where('code', $code)->value('id')) {
                $this->menu()->where('id', $id)->update($row);
            } else {
                $id = $this->menu()->insertGetId($row + ['parent_id' => $settings->parent_id, 'code' => $code, 'target_kind' => 'app',
                    'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(), 'created_at' => now()]);
            }
            $ids[$code] = $id;
        }
        foreach (self::ITEMS as $code => [$group, $sort]) {
            $this->menu()->where('code', $code)->update(['parent_id' => $ids[$group], 'sort_order' => $sort]);
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
        $security = $this->menu()->where('code', 'SET_GRP_SECURITY')->first();
        if (! $others || ! $security) {
            return;
        }
        $new = array_diff(array_keys(self::GROUPS), ['SET_GRP_SECURITY', 'SET_GRP_OTHERS']);
        $this->menu()->whereIn('parent_id', $this->menu()->whereIn('code', $new)->pluck('id'))->update(['parent_id' => $others->id]);
        $this->menu()->whereIn('code', $new)->delete();
        foreach (['SET_APPROVAL', 'SET_APPROVAL_PLT'] as $i => $code) {
            $this->menu()->where('code', $code)->update(['parent_id' => $others->id, 'sort_order' => 42 + $i]);
        }
        $this->menu()->whereIn('code', ['SET_MIGRATION', 'SET_TICKETS'])->update(['parent_id' => $security->id]);
        $this->menu()->where('code', 'SET_THEME')->update(['parent_id' => $others->parent_id, 'sort_order' => 50]);
        $this->menu()->where('id', $others->id)->update(['code' => 'SET_GRP_SETTINGS', 'label' => 'Settings', 'icon' => 'fa-solid fa-toolbox', 'sort_order' => 100]);
        $this->menu()->where('id', $security->id)->update(['sort_order' => 200]);
    }
};
