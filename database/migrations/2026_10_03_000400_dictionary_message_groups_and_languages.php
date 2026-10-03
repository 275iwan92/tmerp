<?php

use Devlib\Lang;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Translation Dictionary dua panel ala ScriptCase (user 2026-10-03): pohon kelompok pesan di kiri, satu kolom per bahasa di kanan.
// - sys_dictionary_group = kelompok pesan (Buttons, Menu, Grid, Form, Messages, ...) -- bisa ditambah user.
// - sys_dictionary: group_code (kelompok), origin ('app' | 'devlib' = kata bawaan objek DevLib yang kini boleh ditimpa dari
//   layar), first_seen (layar tempat teks pertama kali tampil, kolom "Used on").
// - Kamus bawaan DevLib (Devlib\Lang::DICT) disalin ke sys_dictionary (origin devlib) supaya tampak dan bisa diubah; kamus
//   proyek menang atas DICT (Devlib\Lang::text).
// - sys_languages: Jepang & Mandarin disiapkan nonaktif -- cukup dinyalakan di Setup > Languages, kolomnya langsung muncul.
// - Menu Setup > Settings: Languages, Message Groups.
return new class extends Migration
{
    private const GROUPS = [
        ['buttons', 'Buttons', 'fa-hand-pointer', 10], ['menu', 'Menu', 'fa-bars', 20], ['grid', 'Grid columns & filters', 'fa-table-list', 30],
        ['form', 'Form labels', 'fa-rectangle-list', 40], ['messages', 'Messages (flash / error)', 'fa-comment-dots', 50],
        ['validation', 'Validation', 'fa-circle-check', 60], ['print', 'Print & e-mail', 'fa-print', 70], ['help', 'Help & assistant', 'fa-circle-question', 80],
        ['dates', 'Dates & months', 'fa-calendar', 90], ['lookup', 'Lookup values', 'fa-database', 95], ['general', 'General', 'fa-folder', 99],
    ];

    // Kelompok kata bawaan DevLib dari awalan kuncinya (Devlib\Lang::DICT): g_/s_/gt_/k_/pmx_ = grid, f_/cc_/i_/a_ = form, ...
    private const DICT_PREFIX = ['g_' => 'grid', 's_' => 'grid', 'sh_' => 'grid', 'gt_' => 'grid', 'k_' => 'grid', 'pmx_' => 'grid', 'pr_' => 'grid',
        'f_' => 'form', 'cc_' => 'form', 'i_' => 'form', 'a_' => 'form', 'l_' => 'form', 'ph_' => 'form',
        'c_' => 'dates', 'ch_' => 'messages', 'eng_' => 'general', 'ds_' => 'print', 'oc_' => 'general', 'dev_' => 'general'];

    private const DICT_BUTTONS = ['save', 'cancel', 'close', 'delete', 'discard', 'search', 'dirty_nosave', 'today', 'pick_date', 'print_lang', 'printed'];

    public function up(): void
    {
        $s = Schema::connection('tm2sys');
        $db = DB::connection('tm2sys');
        $now = now();

        if (! $s->hasTable('sys_dictionary_group')) {
            $s->create('sys_dictionary_group', function (Blueprint $t) {
                $t->id('id_dictionary_group');
                $t->string('code', 40)->unique();
                $t->string('label', 80)->comment('English; diterjemahkan kamus saat tampil');
                $t->string('icon', 40)->nullable();
                $t->unsignedSmallInteger('sort_order')->default(0);
                $t->boolean('activate')->default(true);
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 50)->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->string('updated_by', 50)->nullable();
            });
        }
        foreach (self::GROUPS as [$code, $label, $icon, $sort]) {
            if (! $db->table('sys_dictionary_group')->where('code', $code)->exists()) {
                $db->table('sys_dictionary_group')->insert(['code' => $code, 'label' => $label, 'icon' => $icon, 'sort_order' => $sort, 'created_at' => $now, 'created_by' => 'migration']);
            }
        }

        if (! $s->hasColumn('sys_dictionary', 'group_code')) {
            $s->table('sys_dictionary', function (Blueprint $t) {
                $t->string('group_code', 40)->default('general')->after('source_en')->index()->comment('sys_dictionary_group.code');
                $t->string('origin', 10)->default('app')->after('group_code')->comment('app | devlib (kata bawaan objek DevLib, boleh ditimpa)');
                $t->string('first_seen', 120)->nullable()->after('origin')->comment('layar tempat teks pertama kali tampil');
            });
        }

        // Kelompok awal untuk baris yang sudah ada (tebakan dari bentuk teks; user bisa memindahkan lewat kolom Group).
        $db->table('sys_dictionary')->where('context', 'help')->update(['group_code' => 'help']);
        $db->table('sys_dictionary')->where('context', 'like', 'date.%')->update(['group_code' => 'dates']);
        $db->table('sys_dictionary')->whereNotIn('context', ['ui', 'help'])->where('context', 'not like', 'date.%')->update(['group_code' => 'lookup']);
        $menuLabels = $db->table('sys_app_menu')->whereNotNull('label')->distinct()->pluck('label')->all();
        foreach (array_chunk($menuLabels, 300) as $chunk) {
            $db->table('sys_dictionary')->where('context', 'ui')->where('group_code', 'general')->whereIn('source_en', $chunk)->update(['group_code' => 'menu']);
        }
        $db->table('sys_dictionary')->where('context', 'ui')->where('group_code', 'general')
            ->where(fn ($q) => $q->where('source_en', 'like', '%.')->orWhere('source_en', 'like', '%!')->orWhere('source_en', 'like', '%?')->orWhere('source_en', 'regexp', ':[a-z_]+'))
            ->update(['group_code' => 'messages']);
        $db->table('sys_dictionary')->where('context', 'ui')->where('group_code', 'general')->where('source_en', 'like', 'Search %')->update(['group_code' => 'grid']);

        // Kata bawaan DevLib -> baris kamus origin devlib (+ terjemahan Indonesia dari DICT bila belum ada).
        foreach (Lang::all() as $key => $row) {
            $row = array_is_list($row) ? ['id' => $row[0], 'en' => $row[1]] : $row;
            $en = trim((string) ($row['en'] ?? ''));
            if ($en === '' || mb_strlen($en) > 255) {
                continue;
            }
            $group = in_array($key, self::DICT_BUTTONS, true) ? 'buttons' : 'general';
            foreach (self::DICT_PREFIX as $prefix => $g) {
                if (str_starts_with((string) $key, $prefix)) {
                    $group = $g;
                    break;
                }
            }
            $existing = $db->table('sys_dictionary')->where('context', 'ui')->where('source_en', $en)->first();
            if ($existing) {
                $id = $existing->id_dictionary;
                $db->table('sys_dictionary')->where('id_dictionary', $id)->update(['origin' => 'devlib'] + ($existing->group_code === 'general' ? ['group_code' => $group] : []));
            } else {
                $id = $db->table('sys_dictionary')->insertGetId(['context' => 'ui', 'source_en' => $en, 'group_code' => $group, 'origin' => 'devlib',
                    'note' => 'DevLib default', 'created_at' => $now, 'created_by' => 'devlib']);
            }
            foreach ($row as $lang => $text) {
                if ($lang === 'en' || ! is_string($text) || trim($text) === '' || $text === $en) {
                    continue;
                }
                if (! $db->table('sys_dictionary_lang')->where('id_dictionary', $id)->where('lang_code', $lang)->exists()) {
                    $db->table('sys_dictionary_lang')->insert(['id_dictionary' => $id, 'lang_code' => $lang, 'text' => $text, 'created_at' => $now, 'created_by' => 'devlib']);
                }
            }
        }

        foreach ([['ja', 'Japanese', '日本語', 'ja-JP', '.', ',', 3], ['zh', 'Chinese', '中文', 'zh-CN', '.', ',', 4]] as [$code, $name, $native, $intl, $dec, $grp, $sort]) {
            if (! $db->table('sys_languages')->where('code', $code)->exists()) {
                $db->table('sys_languages')->insert(['code' => $code, 'name' => $name, 'native_name' => $native, 'intl_locale' => $intl,
                    'decimal_separator' => $dec, 'thousand_separator' => $grp, 'is_default' => 0, 'activate' => 0, 'sort_order' => $sort]);
            }
        }

        $group = $db->table('sys_app_menu')->where('code', 'SET_GRP_SETTINGS')->value('id');
        foreach ([['SET_LANGUAGES', 'Languages', 'v2:languages', 45, 'v2 Languages (sys_languages): switch which languages are active'],
            ['SET_DICT_GROUPS', 'Message Groups', 'v2:message-groups', 46, 'v2 Message Groups (sys_dictionary_group): tree of the Translation Dictionary']] as [$code, $label, $target, $sort, $note]) {
            if ($group && ! $db->table('sys_app_menu')->where('code', $code)->exists()) {
                $db->table('sys_app_menu')->insert(['parent_id' => $group, 'code' => $code, 'label' => $label, 'menu_type' => 'item', 'object_type' => 'grid',
                    'target' => $target, 'target_kind' => 'app', 'sort_order' => $sort, 'activate' => 1, 'created_at' => $now,
                    'status_code' => 'READY_FOR_QC', 'status_note' => $note, 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        $s = Schema::connection('tm2sys');
        $db = DB::connection('tm2sys');
        $db->table('sys_app_menu')->whereIn('code', ['SET_LANGUAGES', 'SET_DICT_GROUPS'])->delete();
        $db->table('sys_dictionary')->where('created_by', 'devlib')->delete();
        if ($s->hasColumn('sys_dictionary', 'group_code')) {
            $s->table('sys_dictionary', fn (Blueprint $t) => $t->dropColumn(['group_code', 'origin', 'first_seen']));
        }
        $s->dropIfExists('sys_dictionary_group');
    }
};
