<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Aturan PK (user 2026-09-30, CLAUDE.md §3): semua tabel PK tunggal BIGINT auto number.
// Tahap 1: PK yang masih INT/TINYINT (atau INT tanpa auto number) -> BIGINT UNSIGNED AUTO_INCREMENT,
// ditambah semua kolom yang merujuknya (FK fisik & logis) -> BIGINT UNSIGNED supaya tipenya sama.
// Atribut kolom (NULL, default, komentar) dibaca dari information_schema, jadi sama persis di ddt* maupun tm2*.
// FOREIGN_KEY_CHECKS=0 per koneksi: MySQL 8 menolak ubah tipe kolom ber-FK selama cek FK aktif.
return new class extends Migration
{
    // koneksi => [tabel => kolom PK]
    private const PKS = [
        'tm2mst' => ['fin_currency' => 'id_currency', 'mat_category' => 'id_category', 'mat_plano_size' => 'id_plano_size',
            'uom_conversion' => 'id_conversion', 'uom_unit' => 'id_uom'],
        'tm2sys' => ['sec_group' => 'id_group', 'sec_module' => 'id_module', 'sec_recovery_question' => 'id_question',
            'sys_quotes' => 'id', 'sys_translations' => 'id', 'sys_user_menu' => 'id_umenu', 'sys_app_menu' => 'id'],
        'tm2hrr' => ['rcr_track' => 'id_track'],
        'tm2acc' => ['fin_account' => 'account_id'],
    ];

    // koneksi => [tabel => [kolom rujukan..]] (hasil audit information_schema 2026-09-30)
    private const REFS = [
        'tm2acc' => ['fin_account' => ['default_cc_id', 'parent_id'], 'fin_asset' => ['credit_account_id'],
            'fin_asset_category' => ['accum_account_id', 'asset_account_id', 'expense_account_id'], 'fin_bank_recon' => ['account_id'],
            'fin_bank_statement' => ['account_id'], 'fin_invoice' => ['revenue_account_id'],
            'fin_partner_account' => ['ap_account_id', 'ar_account_id'], 'fin_voucher_line' => ['account_id']],
        'tm2hrr' => ['app_pipeline_stage_config' => ['id_track'], 'rcr_approval_matrix' => ['id_track'],
            'rcr_offer_approval_matrix' => ['id_track'], 'rcr_requisition' => ['id_track']],
        'tm2mst' => ['fin_exchange_rate' => ['currency_id'], 'geo_country' => ['currency_id'], 'mat_category' => ['parent_id'],
            'mat_raw' => ['prefix5_id'], 'prod_layout_group' => ['plano_size_id'], 'prod_market' => ['paper_category_id', 'sheet_category_id'],
            'prod_part_material' => ['category_id']],
        'tm2sdi' => ['co_order' => ['ship_area_id'], 'do_delivery' => ['ship_area_id'], 'iqp_layout' => ['plano_size_id']],
        'tm2sys' => ['sec_group_permission' => ['group_id', 'module_id'], 'sec_module' => ['parent_id'],
            'sec_user' => ['security_question_id'], 'sec_user_group' => ['group_id'], 'sys_app_menu' => ['parent_id']],
    ];

    public function up(): void
    {
        foreach (array_unique([...array_keys(self::PKS), ...array_keys(self::REFS)]) as $conn) {
            $db = DB::connection($conn);
            $touched = [];
            foreach (self::PKS[$conn] ?? [] as $t => $c) {
                $touched["$t.$c"] = true;
            }
            foreach (self::REFS[$conn] ?? [] as $t => $cols) {
                foreach ($cols as $c) {
                    $touched["$t.$c"] = true;
                }
            }
            // MySQL 8 menolak ubah tipe kolom ber-FK walau FOREIGN_KEY_CHECKS=0: lepas FK terkait, ubah, pasang lagi persis.
            $fks = $this->foreignKeys($db, $touched);
            $db->statement('SET FOREIGN_KEY_CHECKS=0');
            try {
                foreach ($fks as $fk) {
                    $db->statement("ALTER TABLE `{$fk['table']}` DROP FOREIGN KEY `{$fk['name']}`");
                }
                foreach (self::PKS[$conn] ?? [] as $table => $col) {
                    $db->statement("ALTER TABLE `$table` MODIFY ".$this->definition($db, $table, $col, 'BIGINT UNSIGNED', ' AUTO_INCREMENT'));
                }
                foreach (self::REFS[$conn] ?? [] as $table => $cols) {
                    $db->statement("ALTER TABLE `$table` ".implode(', ', array_map(fn ($c) => 'MODIFY '.$this->definition($db, $table, $c, 'BIGINT UNSIGNED'), $cols)));
                }
            } finally {
                foreach ($fks as $fk) {
                    $db->statement("ALTER TABLE `{$fk['table']}` ADD CONSTRAINT `{$fk['name']}` FOREIGN KEY (`".implode('`, `', $fk['cols']).'`) '
                        ."REFERENCES `{$fk['ref']}` (`".implode('`, `', $fk['refCols'])."`) ON DELETE {$fk['del']} ON UPDATE {$fk['upd']}");
                }
                $db->statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }
    }

    // FK di DB koneksi ini yang kolomnya atau kolom rujukannya ikut diubah (FK lintas-DB memang tidak ada).
    private function foreignKeys($db, array $touched): array
    {
        $rows = $db->select('SELECT k.CONSTRAINT_NAME name, k.TABLE_NAME t, k.COLUMN_NAME c, k.REFERENCED_TABLE_NAME rt, k.REFERENCED_COLUMN_NAME rc, r.DELETE_RULE del, r.UPDATE_RULE upd
            FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME
            WHERE k.TABLE_SCHEMA = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION', [$db->getDatabaseName()]);
        $fks = [];
        foreach ($rows as $r) {
            $fk = &$fks["$r->t.$r->name"];
            $fk ??= ['table' => $r->t, 'name' => $r->name, 'ref' => $r->rt, 'del' => $r->del, 'upd' => $r->upd, 'cols' => [], 'refCols' => [], 'hit' => false];
            $fk['cols'][] = $r->c;
            $fk['refCols'][] = $r->rc;
            $fk['hit'] = $fk['hit'] || isset($touched["$r->t.$r->c"]) || isset($touched["$r->rt.$r->rc"]);
            unset($fk);
        }

        return array_values(array_filter($fks, fn ($f) => $f['hit']));
    }

    // Satu arah: menurunkan BIGINT ke INT berisiko memotong nilai; kembalikan lewat baseline ddt / backup bila perlu.
    public function down(): void {}

    // Definisi kolom lengkap dengan tipe baru; NULL/default/komentar dipertahankan.
    private function definition($db, string $table, string $col, string $type, string $extra = ''): string
    {
        $c = $db->selectOne('SELECT IS_NULLABLE n, COLUMN_DEFAULT d, COLUMN_COMMENT m FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$db->getDatabaseName(), $table, $col]) ?? throw new RuntimeException("Kolom {$db->getDatabaseName()}.$table.$col tidak ada");

        return "`$col` $type".($c->n === 'YES' ? ' NULL' : ' NOT NULL')
            .($c->d !== null ? ' DEFAULT '.$db->getPdo()->quote($c->d) : ($c->n === 'YES' ? ' DEFAULT NULL' : ''))
            .$extra
            .($c->m !== '' ? ' COMMENT '.$db->getPdo()->quote($c->m) : '');
    }
};
