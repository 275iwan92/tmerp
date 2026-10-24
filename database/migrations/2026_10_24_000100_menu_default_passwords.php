<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// SEMENTARA (Jo 2026-10-24, tahap UAT): menu Setup > Security > Default Passwords (daftar semua password bawaan + Reset to Default).
// Barisnya ada untuk semua, tetapi AppMenu menyembunyikannya dari login yang tidak ada di config uat.password_viewers (PayrollAccess::menuAllowed),
// dan rutenya menolak mereka dengan 403. SETELAH UAT SELESAI: rollback migration ini (menghapus barisnya) bersama penghapusan layar.
// Runs on ddt* (--env=mockup) and tm2*.
return new class extends Migration
{
    protected $connection = 'tm2sys';

    public function up(): void
    {
        $db = DB::connection('tm2sys');
        $security = $db->table('sys_app_menu')->where('code', 'SET_GRP_SECURITY')->first();
        if ($security && ! $db->table('sys_app_menu')->where('code', 'SET_DEFAULT_PW')->exists()) {
            $db->table('sys_app_menu')->insert(['parent_id' => $security->id, 'code' => 'SET_DEFAULT_PW', 'label' => 'Default Passwords', 'icon' => 'fa-solid fa-key',
                'menu_type' => 'item', 'object_type' => 'grid', 'target' => '/v2/setup/default-passwords', 'target_kind' => 'app', 'sort_order' => 70, 'activate' => 1,
                'status_code' => 'READY_FOR_QC', 'status_note' => 'Sementara UAT: hanya login password_viewers', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(), 'created_at' => now()]);
        }
        if (Schema::connection('tm2sys')->hasTable('sys_dictionary')) {
            $db->table('sys_dictionary')->insertOrIgnore(['context' => 'ui', 'source_en' => 'Default Passwords', 'group_code' => 'menu', 'created_at' => now(), 'created_by' => 'migration']);
            $key = $db->table('sys_dictionary')->where('context', 'ui')->where('source_en', 'Default Passwords')->value('id_dictionary');
            if (! $db->table('sys_dictionary_lang')->where('id_dictionary', $key)->where('lang_code', 'id')->where('text', '<>', '')->exists()) {
                $db->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $key, 'lang_code' => 'id'], ['text' => 'Password Bawaan']);
            }
            Dictionary::flush();
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'SET_DEFAULT_PW')->delete();
    }
};
