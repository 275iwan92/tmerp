<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DEC-FIELDNAME-STATUS fase 3/5: tm2sdi. iqp_type.is_active -> activate,
// iqp_inquiry/iqp_layout.is_confirmed -> stat_confirm, iqp_inquiry.is_cancelled
// -> stat_cancel. tm2sdi tak punya VIEW (sudah diaudit fase 2) jadi aman
// langsung RENAME COLUMN tanpa efek samping. Lihat CLAUDE.md §3,
// docs/prefixlist.md §D-E, docs/fieldrename_state.yaml.
return new class extends Migration
{
    public function up(): void
    {
        $db = Schema::connection('tm2sdi');

        if ($db->hasColumn('iqp_type', 'is_active') && ! $db->hasColumn('iqp_type', 'activate')) {
            DB::connection('tm2sdi')->statement('ALTER TABLE `iqp_type` RENAME COLUMN `is_active` TO `activate`');
        }

        if ($db->hasColumn('iqp_inquiry', 'is_confirmed') && ! $db->hasColumn('iqp_inquiry', 'stat_confirm')) {
            DB::connection('tm2sdi')->statement('ALTER TABLE `iqp_inquiry` RENAME COLUMN `is_confirmed` TO `stat_confirm`');
        }

        if ($db->hasColumn('iqp_inquiry', 'is_cancelled') && ! $db->hasColumn('iqp_inquiry', 'stat_cancel')) {
            DB::connection('tm2sdi')->statement('ALTER TABLE `iqp_inquiry` RENAME COLUMN `is_cancelled` TO `stat_cancel`');
        }

        if ($db->hasColumn('iqp_layout', 'is_confirmed') && ! $db->hasColumn('iqp_layout', 'stat_confirm')) {
            DB::connection('tm2sdi')->statement('ALTER TABLE `iqp_layout` RENAME COLUMN `is_confirmed` TO `stat_confirm`');
        }
    }

    public function down(): void
    {
        $db = Schema::connection('tm2sdi');

        if ($db->hasColumn('iqp_type', 'activate') && ! $db->hasColumn('iqp_type', 'is_active')) {
            DB::connection('tm2sdi')->statement('ALTER TABLE `iqp_type` RENAME COLUMN `activate` TO `is_active`');
        }

        if ($db->hasColumn('iqp_inquiry', 'stat_confirm') && ! $db->hasColumn('iqp_inquiry', 'is_confirmed')) {
            DB::connection('tm2sdi')->statement('ALTER TABLE `iqp_inquiry` RENAME COLUMN `stat_confirm` TO `is_confirmed`');
        }

        if ($db->hasColumn('iqp_inquiry', 'stat_cancel') && ! $db->hasColumn('iqp_inquiry', 'is_cancelled')) {
            DB::connection('tm2sdi')->statement('ALTER TABLE `iqp_inquiry` RENAME COLUMN `stat_cancel` TO `is_cancelled`');
        }

        if ($db->hasColumn('iqp_layout', 'stat_confirm') && ! $db->hasColumn('iqp_layout', 'is_confirmed')) {
            DB::connection('tm2sdi')->statement('ALTER TABLE `iqp_layout` RENAME COLUMN `stat_confirm` TO `is_confirmed`');
        }
    }
};
