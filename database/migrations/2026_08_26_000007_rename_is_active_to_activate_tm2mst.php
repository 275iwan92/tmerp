<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DEC-FIELDNAME-STATUS (2026-08-26, keputusan user): is_active -> activate,
// is_confirmed/is_cancelled -> stat_confirm/stat_cancel, retroaktif ke semua
// tabel KECUALI sec_user/sec_group/sec_module (tabel login/permission,
// dikecualikan permanen -- resiko lockout). Fase 1/5: tm2mst (database
// terbesar, 58 tabel is_active + 1 tabel is_confirmed). Lihat CLAUDE.md §3 &
// docs/prefixlist.md §D-E utk konvensi barunya, docs/fieldrename_state.yaml
// utk progres tiap fase.
return new class extends Migration
{
    private array $isActiveTables = [
        'cust_category', 'cust_customer', 'cust_customer_bank', 'cust_customer_branch', 'cust_customer_contact',
        'emp_bank_account', 'emp_bpjs_kesehatan', 'emp_bpjs_ketenagakerjaan', 'emp_contract', 'emp_document',
        'emp_employee', 'emp_family', 'emp_shift',
        'fin_currency', 'fin_payment_term',
        'flow_config', 'flow_position_acting',
        'geo_city', 'geo_country', 'geo_district', 'geo_village',
        'm_plano_size', 'm_product_type', 'm_product_type_param', 'm_product_type_size', 'm_raw_material',
        'm_ref_param_option', 'm_warehouse', 'm_wh_loc',
        'mat_category', 'mat_coating_rate', 'mat_flute_allowance', 'mat_grammage', 'mat_raw',
        'mat_sheet_discount_tier', 'mat_sheet_rate',
        'org_company', 'org_department', 'org_division', 'org_level', 'org_position',
        'prod_category', 'prod_dieline_template', 'prod_machine', 'prod_part', 'prod_part_artwork',
        'prod_part_type', 'prod_process', 'prod_product', 'prod_type', 'prod_type_param', 'prod_type_size',
        'ref_param_option',
        'sup_category', 'sup_supplier', 'sup_supplier_bank', 'sup_supplier_branch', 'sup_supplier_contact',
        'sys_constant',
        'uom_conversion', 'uom_unit',
    ];

    public function up(): void
    {
        $db = Schema::connection('tm2mst');

        // RENAME COLUMN (MySQL 8.0.8+, project pakai 8.0.46) -- tipe/nullable/
        // default kolom asli otomatis dipertahankan persis, tak perlu
        // spesifikasi ulang (beda tabel bisa beda default/unsigned).
        foreach ($this->isActiveTables as $table) {
            if ($db->hasTable($table) && $db->hasColumn($table, 'is_active') && ! $db->hasColumn($table, 'activate')) {
                DB::connection('tm2mst')->statement("ALTER TABLE `{$table}` RENAME COLUMN `is_active` TO `activate`");
            }
        }

        if ($db->hasColumn('prod_product', 'is_confirmed') && ! $db->hasColumn('prod_product', 'stat_confirm')) {
            DB::connection('tm2mst')->statement('ALTER TABLE `prod_product` RENAME COLUMN `is_confirmed` TO `stat_confirm`');
        }
    }

    public function down(): void
    {
        $db = Schema::connection('tm2mst');

        foreach ($this->isActiveTables as $table) {
            if ($db->hasTable($table) && $db->hasColumn($table, 'activate') && ! $db->hasColumn($table, 'is_active')) {
                DB::connection('tm2mst')->statement("ALTER TABLE `{$table}` RENAME COLUMN `activate` TO `is_active`");
            }
        }

        if ($db->hasColumn('prod_product', 'stat_confirm') && ! $db->hasColumn('prod_product', 'is_confirmed')) {
            DB::connection('tm2mst')->statement('ALTER TABLE `prod_product` RENAME COLUMN `stat_confirm` TO `is_confirmed`');
        }
    }
};
