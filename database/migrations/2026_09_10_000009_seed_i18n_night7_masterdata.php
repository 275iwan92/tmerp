<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// i18n Malam 7/7 Bagian A (docs/BACKLOG.md §13 baris #7): Master Data
// (tm2mst) -- HANYA toolbar/label grid, BUKAN form field lengkap. Target:
// master/raw-material/index.blade.php, master/produk.blade.php,
// master/uom.blade.php (grid + panel konversi), master/supplier.blade.php
// (grid), master/customer.blade.php (grid). Key prefix mdata_*, sebagian
// direuse dari malam-malam sebelumnya (proc_*/fin_coa_*/hrm_emp_* dsb) utk
// kata generik identik (Aktif/Nonaktifkan/Edit/Status/klik-2x).
return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            // Generik lintas Master Data
            ['mdata_active', 'Aktif', 'Active'],
            ['mdata_inactive', 'Nonaktif', 'Inactive'],
            ['mdata_active_upper', 'AKTIF', 'ACTIVE'],
            ['mdata_inactive_upper', 'NONAKTIF', 'INACTIVE'],
            ['mdata_draft', 'DRAFT', 'DRAFT'],
            ['mdata_confirmed', 'CONFIRMED', 'CONFIRMED'],

            // Raw Material
            ['mdata_rm_col_type', 'Tipe', 'Type'],
            ['mdata_rm_col_category', 'Kategori', 'Category'],
            ['mdata_rm_col_grammage', 'Gramatur', 'Grammage'],
            ['mdata_rm_col_method', 'Metode', 'Method'],
            ['mdata_rm_col_stock', 'Stok', 'Stock'],
            ['mdata_rm_col_avg_price', 'Harga Rata²', 'Average Price'],
            ['mdata_rm_col_last_price', 'Harga Terakhir', 'Last Price'],
            ['mdata_rm_col_min_stock', 'Stok Min', 'Min Stock'],
            ['mdata_rm_col_max_stock', 'Stok Max', 'Max Stock'],
            ['mdata_rm_col_reorder_point', 'Reorder Point', 'Reorder Point'],
            ['mdata_rm_col_brand', 'Merek', 'Brand'],
            ['mdata_rm_col_hs_code', 'HS Code', 'HS Code'],
            ['mdata_rm_col_lead_time', 'Lead Time (hari)', 'Lead Time (days)'],
            ['mdata_rm_search_col_code', 'Kode Item', 'Item Code'],
            ['mdata_rm_search_col_name', 'Nama Item', 'Item Name'],
            ['mdata_rm_stat_total', 'Total Master Item', 'Total Master Items'],
            ['mdata_rm_stat_material', 'Bahan Baku (Stok)', 'Raw Material (Stock)'],
            ['mdata_rm_stat_service', 'Jasa / Sample', 'Service / Sample'],
            ['mdata_rm_stat_low_stock', 'Stok Kritis', 'Critical Stock'],
            ['mdata_rm_stat_draft', 'Draft (Belum Confirm)', 'Draft (Not Confirmed)'],
            ['mdata_rm_stat_draft_subtitle', 'Belum muncul di PR/PQ/PRT', 'Not yet appearing in PR/PQ/PRT'],
            ['mdata_rm_search_placeholder', 'Cari kode, nama, HS code…', 'Search code, name, HS code…'],
            ['mdata_rm_bulk_selected_suffix', 'item draft dipilih', 'draft items selected'],
            ['mdata_rm_bulk_delete_confirm_suffix', 'item draft terpilih? Tindakan ini tidak bisa dibatalkan.', 'selected draft items? This action cannot be undone.'],
            ['mdata_rm_checkbox_bulk_hint', 'Pilih untuk hapus massal (draft) — shift+klik utk pilih rentang', 'Select for bulk delete (draft) — shift+click to select a range'],
            ['mdata_rm_action_edit_tooltip', 'Edit item ini — atau klik 2x pada baris', 'Edit this item — or double-click the row'],
            ['mdata_rm_toggle_deactivate_title', 'Klik untuk nonaktifkan item ini', 'Click to deactivate this item'],
            ['mdata_rm_toggle_activate_title', 'Klik untuk aktifkan item ini', 'Click to activate this item'],
            ['mdata_rm_empty', 'Tidak ada item cocok.', 'No matching items.'],

            // Produk
            ['mdata_prod_stat_total', 'Total Produk', 'Total Products'],
            ['mdata_prod_stat_confirmed', 'Terkonfirmasi', 'Confirmed'],
            ['mdata_prod_stat_confirmed_subtitle', 'SPEC TERKUNCI', 'SPEC LOCKED'],
            ['mdata_prod_search_placeholder', 'Cari kode / nama produk…', 'Search product code / name…'],
            ['mdata_prod_col_product', 'Produk', 'Product'],
            ['mdata_prod_col_dimension', 'Dimensi (cm)', 'Dimension (cm)'],
            ['mdata_prod_locked', 'TERKUNCI', 'LOCKED'],
            ['mdata_prod_empty', 'Tidak ada produk.', 'No products.'],
            ['mdata_prod_edit_title', 'Edit Produk', 'Edit Product'],
            ['mdata_prod_new_title', 'Produk Baru', 'New Product'],

            // UoM
            ['mdata_uom_search_placeholder', 'Cari kode / nama…', 'Search code / name…'],
            ['mdata_uom_count_suffix', 'satuan.', 'units.'],
            ['mdata_uom_click_conversion_hint', 'Klik ikon <b>🔁</b> untuk buka panel Konversi.', 'Click the <b>🔁</b> icon to open the Conversion panel.'],
            ['mdata_uom_base_hint', '<b>Dasar</b> = satuan dasar dimensi (hanya 1 per dimensi, dasar lama otomatis dilepas saat "Simpan Semua").', '<b>Base</b> = the base unit of a dimension (only 1 per dimension; the old base is automatically released when "Save All" runs).'],
            ['mdata_uom_conversion_label', 'Konversi', 'Conversion'],
            ['mdata_uom_btn_new_conversion', 'Konversi Baru', 'New Conversion'],
            ['mdata_uom_hide_inactive', 'Sembunyikan konversi nonaktif', 'Hide inactive conversions'],
            ['mdata_uom_direct_conversion', 'Konversi Langsung', 'Direct Conversion'],
            ['mdata_uom_no_direct_conversion_prefix', 'Belum ada pasangan konversi langsung untuk', 'No direct conversion pair yet for'],
            ['mdata_uom_no_direct_conversion_suffix', 'Klik <b>+ Konversi Baru</b> di atas.', 'Click <b>+ New Conversion</b> above.'],
            ['mdata_uom_derived_conversion', 'Turunan ke Semua Satuan se-Dimensi', 'Derived to All Units in the Dimension'],
            ['mdata_uom_edit_title', 'Edit Satuan', 'Edit Unit'],
            ['mdata_uom_new_title', 'Satuan Baru', 'New Unit'],

            // Supplier
            ['mdata_sup_stat_total', 'Total Supplier', 'Total Suppliers'],
            ['mdata_sup_stat_subcon', 'Subkontraktor', 'Subcontractor'],
            ['mdata_sup_stat_blocked', 'Terblokir', 'Blocked'],
            ['mdata_sup_search_placeholder', 'Cari nama / kode / PIC...', 'Search name / code / PIC...'],
            ['mdata_sup_col_contact', 'Kontak PIC', 'PIC Contact'],
            ['mdata_sup_col_terms', 'Termin', 'Payment Terms'],
            ['mdata_sup_blocked_badge', 'Blokir', 'Blocked'],
            ['mdata_sup_empty', 'Belum ada data supplier', 'No supplier data yet'],
            ['mdata_sup_empty_search_suffix', ' untuk pencarian ini', ' for this search'],

            // Customer
            ['mdata_cust_stat_total', 'Total Customer', 'Total Customers'],
            ['mdata_cust_stat_draft', 'Draft / Onboarding', 'Draft / Onboarding'],
            ['mdata_cust_col_industry', 'Industri', 'Industry'],
            ['mdata_cust_col_credit_limit', 'Limit Kredit', 'Credit Limit'],
            ['mdata_cust_type_import', 'Import', 'Import'],
            ['mdata_cust_type_local', 'Lokal', 'Local'],
            ['mdata_cust_prospect', 'Prospek', 'Prospect'],
            ['mdata_cust_empty', 'Belum ada data customer', 'No customer data yet'],
        ];

        $sys = DB::connection('tm2sys');

        foreach ($rows as [$key, $id, $en]) {
            $sys->table('sys_translations')->updateOrInsert(['lang_code' => 'id', 'trans_key' => $key], ['trans_value' => $id]);
            $sys->table('sys_translations')->updateOrInsert(['lang_code' => 'en', 'trans_key' => $key], ['trans_value' => $en]);
        }
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');
        $keys = [
            'mdata_active', 'mdata_inactive', 'mdata_active_upper', 'mdata_inactive_upper', 'mdata_draft', 'mdata_confirmed',
            'mdata_rm_col_type', 'mdata_rm_col_category', 'mdata_rm_col_grammage', 'mdata_rm_col_method', 'mdata_rm_col_stock',
            'mdata_rm_col_avg_price', 'mdata_rm_col_last_price', 'mdata_rm_col_min_stock', 'mdata_rm_col_max_stock',
            'mdata_rm_col_reorder_point', 'mdata_rm_col_brand', 'mdata_rm_col_hs_code', 'mdata_rm_col_lead_time',
            'mdata_rm_search_col_code', 'mdata_rm_search_col_name', 'mdata_rm_stat_total', 'mdata_rm_stat_material',
            'mdata_rm_stat_service', 'mdata_rm_stat_low_stock', 'mdata_rm_stat_draft', 'mdata_rm_stat_draft_subtitle',
            'mdata_rm_search_placeholder', 'mdata_rm_bulk_selected_suffix', 'mdata_rm_bulk_delete_confirm_suffix',
            'mdata_rm_checkbox_bulk_hint', 'mdata_rm_action_edit_tooltip', 'mdata_rm_toggle_deactivate_title',
            'mdata_rm_toggle_activate_title', 'mdata_rm_empty',
            'mdata_prod_stat_total', 'mdata_prod_stat_confirmed', 'mdata_prod_stat_confirmed_subtitle',
            'mdata_prod_search_placeholder', 'mdata_prod_col_product', 'mdata_prod_col_dimension', 'mdata_prod_locked',
            'mdata_prod_empty', 'mdata_prod_edit_title', 'mdata_prod_new_title',
            'mdata_uom_search_placeholder', 'mdata_uom_count_suffix', 'mdata_uom_click_conversion_hint',
            'mdata_uom_base_hint', 'mdata_uom_conversion_label', 'mdata_uom_btn_new_conversion', 'mdata_uom_hide_inactive',
            'mdata_uom_direct_conversion', 'mdata_uom_no_direct_conversion_prefix', 'mdata_uom_no_direct_conversion_suffix',
            'mdata_uom_derived_conversion', 'mdata_uom_edit_title', 'mdata_uom_new_title',
            'mdata_sup_stat_total', 'mdata_sup_stat_subcon', 'mdata_sup_stat_blocked', 'mdata_sup_search_placeholder',
            'mdata_sup_col_contact', 'mdata_sup_col_terms', 'mdata_sup_blocked_badge', 'mdata_sup_empty',
            'mdata_sup_empty_search_suffix',
            'mdata_cust_stat_total', 'mdata_cust_stat_draft', 'mdata_cust_col_industry', 'mdata_cust_col_credit_limit',
            'mdata_cust_type_import', 'mdata_cust_type_local', 'mdata_cust_prospect', 'mdata_cust_empty',
        ];
        $sys->table('sys_translations')->whereIn('trans_key', $keys)->delete();
    }
};
