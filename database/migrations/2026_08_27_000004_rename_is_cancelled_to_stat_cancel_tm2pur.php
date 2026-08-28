<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DEC-FIELDNAME-STATUS fase 4/5: tm2pur. po_order/pr_requisition.is_cancelled
// -> stat_cancel. Tidak ada is_active/is_confirmed di tm2pur, tidak ada VIEW
// (sudah diaudit fase 2). Lihat CLAUDE.md §3, docs/prefixlist.md §D-E,
// docs/fieldrename_state.yaml.
return new class extends Migration
{
    private array $tables = ['po_order', 'pr_requisition'];

    public function up(): void
    {
        $db = Schema::connection('tm2pur');

        foreach ($this->tables as $table) {
            if ($db->hasColumn($table, 'is_cancelled') && ! $db->hasColumn($table, 'stat_cancel')) {
                DB::connection('tm2pur')->statement("ALTER TABLE `{$table}` RENAME COLUMN `is_cancelled` TO `stat_cancel`");
            }
        }
    }

    public function down(): void
    {
        $db = Schema::connection('tm2pur');

        foreach ($this->tables as $table) {
            if ($db->hasColumn($table, 'stat_cancel') && ! $db->hasColumn($table, 'is_cancelled')) {
                DB::connection('tm2pur')->statement("ALTER TABLE `{$table}` RENAME COLUMN `stat_cancel` TO `is_cancelled`");
            }
        }
    }
};
