<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DEC-FIELDNAME-STATUS fase 5/5 (terakhir): tm2sys, TERMASUK sec_user/
// sec_group/sec_module -- awalnya dikecualikan (resiko lockout), user
// eksplisit minta include 2026-08-27 setelah fase 1-4 terbukti aman.
//
// sec_user.active vs sec_user.is_active: DICEK DULU (146/146 baris selalu
// sama nilainya -- `active` = kolom asli legacy V1 "ex active varchar(1) ->
// tinyint", `is_active` = kolom V2 duplikat). Cuma is_active yang di-rename;
// `active` TIDAK disentuh (bukan bagian rename ini, beda kolom).
//
// Tidak ada VIEW tm2sys yang menyebut kolom ini (sudah diaudit fase 2 & 5).
// Lihat CLAUDE.md §3, docs/prefixlist.md §D-E, docs/fieldrename_state.yaml.
return new class extends Migration
{
    private array $tables = [
        'sec_user', 'sec_group', 'sec_module',
        'sys_app_menu', 'sys_doc_reference', 'sys_login_hero', 'sys_quotes', 'sys_theme', 'sys_user_menu',
    ];

    public function up(): void
    {
        $db = Schema::connection('tm2sys');

        foreach ($this->tables as $table) {
            if ($db->hasColumn($table, 'is_active') && ! $db->hasColumn($table, 'activate')) {
                DB::connection('tm2sys')->statement("ALTER TABLE `{$table}` RENAME COLUMN `is_active` TO `activate`");
            }
        }
    }

    public function down(): void
    {
        $db = Schema::connection('tm2sys');

        foreach ($this->tables as $table) {
            if ($db->hasColumn($table, 'activate') && ! $db->hasColumn($table, 'is_active')) {
                DB::connection('tm2sys')->statement("ALTER TABLE `{$table}` RENAME COLUMN `activate` TO `is_active`");
            }
        }
    }
};
