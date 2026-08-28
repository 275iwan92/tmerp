<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DEC-FIELDNAME-STATUS fase 2/5: tm2acc. is_active -> activate (6 tabel),
// fin_voucher.is_cancelled -> stat_cancel. Lihat CLAUDE.md §3,
// docs/prefixlist.md §D-E, docs/fieldrename_state.yaml utk konvensi & progres.
return new class extends Migration
{
    // v1_fin_account_tree BUKAN base table (VIEW) -- ALTER TABLE RENAME COLUMN
    // tak berlaku utknya (di-skip lewat hasColumn check di bawah karena VIEW
    // yg rusak tak lagi mengekspos kolomnya ke information_schema). Definisi
    // VIEW-nya (dan v1_fin_trial_balance yg juga menyebut kolom lama) digambar
    // ulang terpisah di migration 2026_08_27_000002_fix_tm2acc_views_after_rename.
    private array $isActiveTables = [
        'fin_account', 'fin_cost_center', 'fin_journal', 'fin_partner_account',
        'fin_profit_center',
    ];

    public function up(): void
    {
        $db = Schema::connection('tm2acc');

        foreach ($this->isActiveTables as $table) {
            if ($db->hasTable($table) && $db->hasColumn($table, 'is_active') && ! $db->hasColumn($table, 'activate')) {
                DB::connection('tm2acc')->statement("ALTER TABLE `{$table}` RENAME COLUMN `is_active` TO `activate`");
            }
        }

        if ($db->hasColumn('fin_voucher', 'is_cancelled') && ! $db->hasColumn('fin_voucher', 'stat_cancel')) {
            DB::connection('tm2acc')->statement('ALTER TABLE `fin_voucher` RENAME COLUMN `is_cancelled` TO `stat_cancel`');
        }
    }

    public function down(): void
    {
        $db = Schema::connection('tm2acc');

        foreach ($this->isActiveTables as $table) {
            if ($db->hasTable($table) && $db->hasColumn($table, 'activate') && ! $db->hasColumn($table, 'is_active')) {
                DB::connection('tm2acc')->statement("ALTER TABLE `{$table}` RENAME COLUMN `activate` TO `is_active`");
            }
        }

        if ($db->hasColumn('fin_voucher', 'stat_cancel') && ! $db->hasColumn('fin_voucher', 'is_cancelled')) {
            DB::connection('tm2acc')->statement('ALTER TABLE `fin_voucher` RENAME COLUMN `stat_cancel` TO `is_cancelled`');
        }
    }
};
