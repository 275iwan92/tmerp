<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// i18n Malam 3/7 (docs/BACKLOG.md §13 baris #3): Sales inti -- Inquiry Hub
// (inquiry/hub.blade.php), header+toolbar Inquiry form (inquiry/form.blade.php,
// HANYA chrome, bukan isi form dieline/material/proses), Quotation list
// (quotation/list.blade.php), CO list (co/list.blade.php). Key prefix
// sales_*. 2 key baru dipromosikan ke shared_* (action-icon "Buka" +
// tooltip "Klik 2x untuk buka") krn polanya identik di semua grid non-master
// tapi belum pernah ditarik sebelumnya -- dipakai di quotation & CO list
// malam ini, layar grid lain (raw-material, dst) masih hardcode, jadi
// promosikan penuh (rollout ke semua grid) BUKAN scope malam ini.
return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            // shared/grid (dipakai lintas Sales malam ini, generalisasi lanjut menyusul)
            ['shared_grid_action_open', 'Buka', 'Open'],
            ['shared_grid_dblclick_hint', 'Klik 2x untuk buka', 'Double-click to open'],

            // Inquiry Hub
            ['sales_hub_type_new', 'Produk Baru', 'New Product'],
            ['sales_hub_type_recalc', 'Hitung Ulang Harga', 'Recalculate Price'],
            ['sales_hub_type_revise', 'Ubah Spesifikasi', 'Revise Specification'],
            ['sales_hub_stat_summary', 'Ringkasan', 'Summary'],
            ['sales_hub_stat_collapse', 'Ciutkan', 'Collapse'],
            ['sales_hub_stat_expand', 'Tampilkan', 'Expand'],
            ['sales_hub_stat_new_unconfirmed', 'NEW belum confirm', 'NEW not confirmed'],
            ['sales_hub_stat_revise_unconfirmed', 'REVISE belum confirm', 'REVISE not confirmed'],
            ['sales_hub_stat_recalc_unconfirmed', 'RECALC belum confirm', 'RECALC not confirmed'],
            ['sales_hub_stat_total_year', 'Total inquiry tahun ini', 'Total inquiries this year'],
            ['sales_hub_stat_new_priced', 'NEW sudah harga, belum ke customer', 'NEW priced, not yet sent to customer'],
            ['sales_hub_stat_revise_priced', 'REVISE sudah harga, belum ke customer', 'REVISE priced, not yet sent to customer'],
            ['sales_hub_stat_recalc_priced', 'RECALC sudah harga, belum ke customer', 'RECALC priced, not yet sent to customer'],
            ['sales_hub_stat_approved_year', 'Sudah di-approve tahun ini', 'Approved this year'],
            ['sales_hub_card_new_title', 'Produk Baru', 'New Product'],
            ['sales_hub_card_new_desc', 'Belum pernah dibuat. Sales menentukan tipe produk, dimensi, bahan, dan finishing dari nol.', 'Never made before. Sales determines the product type, dimensions, material, and finishing from scratch.'],
            ['sales_hub_card_new_req', 'Perlu: <b>tipe produk + dimensi + bahan</b><br>Alur: Sales → Design → Costing', 'Needs: <b>product type + dimensions + material</b><br>Flow: Sales → Design → Costing'],
            ['sales_hub_card_recalc_title', 'Hitung Ulang Harga', 'Recalculate Price'],
            ['sales_hub_card_recalc_desc', 'Produk & layout sudah ada, tidak ada yang berubah. Hanya perlu harga baru.', 'Product & layout already exist, nothing changed. Only a new price is needed.'],
            ['sales_hub_card_recalc_req', 'Perlu: <b>pilih produk existing + qty</b><br>Alur: Sales → Costing <span style="color:#177a4b">(lewati Design)</span>', 'Needs: <b>pick existing product + qty</b><br>Flow: Sales → Costing <span style="color:#177a4b">(skip Design)</span>'],
            ['sales_hub_card_revise_title', 'Ubah Spesifikasi', 'Revise Specification'],
            ['sales_hub_card_revise_desc', 'Produk sudah ada tapi ada yang berubah: dimensi, bahan, warna, atau finishing.', 'Product already exists but something changed: dimensions, material, color, or finishing.'],
            ['sales_hub_card_revise_req', 'Perlu: <b>pilih produk existing + apa yang berubah</b><br>Alur: Sales → Design → Costing', 'Needs: <b>pick existing product + what changed</b><br>Flow: Sales → Design → Costing'],
            ['sales_hub_btn_pick_saved', 'Pilih data tersimpan', 'Pick saved data'],
            ['sales_hub_btn_create_new', 'Buat data baru', 'Create new data'],
            ['sales_hub_picker_title', 'Pilih produk existing —', 'Pick existing product —'],
            ['sales_hub_picker_note_recalc', 'Semua spesifikasi akan disalin dari inkuiri terpilih dan <b>dikunci</b>. Anda hanya mengisi qty & deadline.', 'All specifications will be copied from the selected inquiry and <b>locked</b>. You only fill in qty & deadline.'],
            ['sales_hub_picker_note_revise', 'Spesifikasi disalin lalu <b>bisa diubah</b>. Tipe produk tetap terkunci — kalau tipe berubah, gunakan <b>NEW</b>.', 'Specification is copied and <b>can be changed</b>. Product type stays locked — if the type changes, use <b>NEW</b>.'],
            ['sales_hub_picker_search_placeholder', 'Cari no. inkuiri / customer / produk…', 'Search inquiry no. / customer / product…'],
            ['sales_hub_no_product_name', '(tanpa nama produk)', '(no product name)'],
            ['sales_hub_qty_label', 'qty', 'qty'],
            ['sales_hub_picker_empty', 'Tidak ada produk/inkuiri yang cocok.', 'No matching product/inquiry.'],
            ['sales_hub_picker_selected', 'Terpilih', 'Selected'],
            ['sales_hub_picker_pick_hint', 'Pilih satu baris untuk melanjutkan.', 'Pick one row to continue.'],
            ['sales_hub_picker_continue', 'Lanjut →', 'Continue →'],
            ['sales_hub_picker_pick_first', 'Pilih produk dulu', 'Pick a product first'],
            ['sales_hub_saved_title', 'Inkuiri Tersimpan', 'Saved Inquiries'],
            ['sales_hub_saved_count_suffix', 'inkuiri', 'inquiries'],
            ['sales_hub_saved_search_placeholder', 'Cari no. inkuiri / nama produk / prospek…', 'Search inquiry no. / product name / prospect…'],
            ['sales_hub_filter_all_status', 'Semua status', 'All statuses'],
            ['sales_hub_filter_all_type', 'Semua tipe', 'All types'],
            ['sales_hub_col_product_customer', 'Produk / Customer', 'Product / Customer'],
            ['sales_hub_col_inquiry_no', 'No. Inkuiri', 'Inquiry No.'],
            ['sales_hub_col_date', 'Tgl', 'Date'],
            ['sales_hub_col_product_type', 'Tipe Produk', 'Product Type'],
            ['sales_hub_col_qty_tier', 'Qty tier utama', 'Main tier qty'],
            ['sales_hub_col_status', 'Status', 'Status'],
            ['sales_hub_col_type', 'Jenis', 'Type'],
            ['sales_hub_col_action', 'Aksi', 'Action'],
            ['sales_hub_no_doc_number', '(draft, belum ada no.)', '(draft, no number yet)'],
            ['sales_hub_prospect_pill', 'prospek', 'prospect'],
            ['sales_hub_row_open_edit', 'Buka untuk diedit', 'Open to edit'],
            ['sales_hub_row_open_view', 'Buka (lihat-saja)', 'Open (view-only)'],
            ['sales_hub_no_matching_inquiry', 'Belum ada inkuiri yang cocok.', 'No matching inquiry yet.'],
            ['sales_hub_will_open_edit', 'akan dibuka untuk diedit', 'will open for editing'],
            ['sales_hub_will_open_view', 'dibuka lihat-saja (bukan DRAFT)', 'opens view-only (not DRAFT)'],
            ['sales_hub_click_row_hint', 'Klik satu baris untuk memilih.', 'Click a row to select it.'],
            ['sales_hub_btn_open_view', 'Buka (lihat-saja) →', 'Open (view-only) →'],
            ['sales_hub_btn_open_inquiry', 'Buka inkuiri →', 'Open inquiry →'],

            // Inquiry form (chrome only)
            ['sales_form_title_new', 'Inkuiri Produk Baru', 'New Product Inquiry'],
            ['sales_form_title_recalc', 'Hitung Ulang Harga', 'Recalculate Price'],
            ['sales_form_title_revise', 'Ubah Spesifikasi', 'Revise Specification'],
            ['sales_form_not_saved_yet', 'belum tersimpan', 'not saved yet'],
            ['sales_form_back_to_menu', 'Menu Utama', 'Main Menu'],
            ['sales_form_readonly_note_prefix', 'Inkuiri ini sudah dikonfirmasi (status', 'This inquiry is already confirmed (status'],
            ['sales_form_readonly_note_suffix', ') — dibuka lihat-saja, tidak bisa diedit.', ') — opened view-only, cannot be edited.'],
            ['sales_form_sales_officer_label', 'Sales Officer', 'Sales Officer'],
            ['sales_form_officer_hint', 'isi header, tier qty, part &amp; dieline, tooling, diskon, dan biaya eksternal di bawah. Costing (HPP) &amp; estimasi kecepatan akan menyusul di iterasi berikutnya.', 'fill in the header, tier qty, part &amp; dieline, tooling, discount, and external costs below. Costing (COGS) &amp; speed estimation will follow in a later iteration.'],
            ['sales_form_btn_confirm', 'Confirm', 'Confirm'],
            ['sales_form_btn_cancel_doc', 'Batalkan', 'Cancel'],
            ['sales_form_btn_mark_lost', 'Tandai Kalah', 'Mark Lost'],
            ['sales_form_link_imposition', 'Imposition', 'Imposition'],
            ['sales_form_link_print_nota', 'Cetak Nota', 'Print Note'],
            ['sales_form_cancel_reason_label', 'Alasan Pembatalan *', 'Cancellation Reason *'],
            ['sales_form_cancel_reason_placeholder', 'Wajib diisi — tercatat di riwayat', 'Required — recorded in history'],
            ['sales_form_confirm_cancel_doc', 'Ya, batalkan inkuiri', 'Yes, cancel inquiry'],
            ['sales_form_lost_reason_label', 'Alasan Kalah *', 'Lost Reason *'],
            ['sales_form_lost_reason_placeholder', 'mis. Harga kompetitor lebih rendah, customer batal order — wajib diisi, tercatat di riwayat & laporan win-rate', 'e.g. Competitor price is lower, customer cancelled order — required, recorded in history & win-rate report'],
            ['sales_form_confirm_mark_lost', 'Ya, tandai kalah', 'Yes, mark as lost'],
            ['sales_form_section_header', 'Header Inkuiri', 'Inquiry Header'],

            // Quotation list
            ['sales_quo_stat_total', 'Total Quotation', 'Total Quotations'],
            ['sales_quo_stat_draft', 'Draft', 'Draft'],
            ['sales_quo_stat_published', 'Published', 'Published'],
            ['sales_quo_search_placeholder', 'Cari no. quotation / customer…', 'Search quotation no. / customer…'],
            ['sales_quo_col_quotation', 'Quotation', 'Quotation'],
            ['sales_quo_col_date', 'Tanggal', 'Date'],
            ['sales_quo_col_status', 'Status', 'Status'],
            ['sales_quo_col_value', 'Nilai (excl PPN)', 'Value (excl. VAT)'],
            ['sales_quo_col_action', 'Aksi', 'Action'],
            ['sales_quo_action_confirm_publish', 'Confirm / Publish', 'Confirm / Publish'],
            ['sales_quo_confirm_publish_prefix', 'Publish quotation', 'Publish quotation'],
            ['sales_quo_confirm_publish_suffix', 'Harga akan terkunci.', 'The price will be locked.'],
            ['sales_quo_empty', 'Belum ada quotation.', 'No quotations yet.'],
            ['sales_quo_picker_title', 'Pilih Customer', 'Pick Customer'],
            ['sales_quo_picker_search_placeholder', 'Ketik min. 2 huruf nama/kode customer...', 'Type at least 2 letters of customer name/code...'],
            ['sales_quo_picker_min_chars', 'Ketik min. 2 huruf untuk cari.', 'Type at least 2 letters to search.'],
            ['sales_quo_picker_no_match', 'Tidak ada customer cocok.', 'No matching customer.'],

            // CO list
            ['sales_co_col_ref_quotation', 'Ref. Quotation', 'Ref. Quotation'],
            ['sales_co_col_date', 'Tanggal', 'Date'],
            ['sales_co_col_customer_po', 'No. PO Customer', 'Customer PO No.'],
            ['sales_co_col_ship_address_label', 'Label Alamat Kirim', 'Ship Address Label'],
            ['sales_co_col_discount_doc', 'Diskon Dokumen %', 'Document Discount %'],
            ['sales_co_col_discount_qty', 'Diskon Qty %', 'Qty Discount %'],
            ['sales_co_col_notes', 'Catatan', 'Notes'],
            ['sales_co_col_created_by', 'Dibuat Oleh', 'Created By'],
            ['sales_co_col_updated_by', 'Diubah Oleh', 'Updated By'],
            ['sales_co_search_col_no', 'No. CO', 'CO No.'],
            ['sales_co_search_col_customer', 'Nama Customer', 'Customer Name'],
            ['sales_co_stat_total', 'Total Customer Order', 'Total Customer Orders'],
            ['sales_co_stat_draft', 'Draft', 'Draft'],
            ['sales_co_stat_confirmed', 'Confirmed', 'Confirmed'],
            ['sales_co_no_new_record_note', 'CO dibuat dari Quotation yang sudah <b>PUBLISHED</b> — buka Quotation, klik "Buat Customer Order". Tidak ada "+ New Record" di sini.', 'A CO is created from a <b>PUBLISHED</b> Quotation — open the Quotation, click "Create Customer Order". There is no "+ New Record" here.'],
            ['sales_co_search_placeholder', 'Cari no. CO / customer…', 'Search CO no. / customer…'],
            ['sales_co_bulk_selected_suffix', 'CO draft dipilih', 'draft COs selected'],
            ['sales_co_bulk_delete_confirm_prefix', 'Hapus permanen', 'Permanently delete'],
            ['sales_co_bulk_delete_confirm_suffix', 'Customer Order draft terpilih beserta item-nya? Tindakan ini tidak bisa dibatalkan.', 'selected draft Customer Orders along with their items? This action cannot be undone.'],
            ['sales_co_bulk_delete_btn', 'Hapus', 'Delete'],
            ['sales_co_col_customer_order', 'Customer Order', 'Customer Order'],
            ['sales_co_col_status', 'Status', 'Status'],
            ['sales_co_col_net_receivable', 'Net Receivable', 'Net Receivable'],
            ['sales_co_close_item_preview', 'Tutup preview item', 'Close item preview'],
            ['sales_co_view_item_preview', 'Lihat item', 'View items'],
            ['sales_co_checkbox_bulk_hint', 'Pilih untuk hapus massal (draft) — shift+klik utk pilih rentang', 'Select for bulk delete (draft) — shift+click to select a range'],
            ['sales_co_action_open_tooltip', 'Buka Customer Order ini — atau klik 2x pada baris', 'Open this Customer Order — or double-click the row'],
            ['sales_co_action_confirm_confirm', 'Confirm CO', 'Confirm CO'],
            ['sales_co_detail_col_product_service', 'Produk / Jasa', 'Product / Service'],
            ['sales_co_detail_col_type', 'Type', 'Type'],
            ['sales_co_detail_col_qty', 'Qty', 'Qty'],
            ['sales_co_detail_col_price', 'Harga', 'Price'],
            ['sales_co_detail_col_disc', 'Disc%', 'Disc%'],
            ['sales_co_detail_col_subtotal', 'Subtotal', 'Subtotal'],
            ['sales_co_detail_col_due_date', 'Due Date', 'Due Date'],
            ['sales_co_detail_no_items', 'Belum ada item.', 'No items yet.'],
            ['sales_co_empty', 'Belum ada Customer Order.', 'No Customer Orders yet.'],
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
            'shared_grid_action_open', 'shared_grid_dblclick_hint',
            'sales_hub_type_new', 'sales_hub_type_recalc', 'sales_hub_type_revise', 'sales_hub_stat_summary',
            'sales_hub_stat_collapse', 'sales_hub_stat_expand', 'sales_hub_stat_new_unconfirmed',
            'sales_hub_stat_revise_unconfirmed', 'sales_hub_stat_recalc_unconfirmed', 'sales_hub_stat_total_year',
            'sales_hub_stat_new_priced', 'sales_hub_stat_revise_priced', 'sales_hub_stat_recalc_priced',
            'sales_hub_stat_approved_year', 'sales_hub_card_new_title', 'sales_hub_card_new_desc', 'sales_hub_card_new_req',
            'sales_hub_card_recalc_title', 'sales_hub_card_recalc_desc', 'sales_hub_card_recalc_req',
            'sales_hub_card_revise_title', 'sales_hub_card_revise_desc', 'sales_hub_card_revise_req',
            'sales_hub_btn_pick_saved', 'sales_hub_btn_create_new', 'sales_hub_picker_title',
            'sales_hub_picker_note_recalc', 'sales_hub_picker_note_revise', 'sales_hub_picker_search_placeholder',
            'sales_hub_no_product_name', 'sales_hub_qty_label', 'sales_hub_picker_empty', 'sales_hub_picker_selected',
            'sales_hub_picker_pick_hint', 'sales_hub_picker_continue', 'sales_hub_picker_pick_first',
            'sales_hub_saved_title', 'sales_hub_saved_count_suffix', 'sales_hub_saved_search_placeholder',
            'sales_hub_filter_all_status', 'sales_hub_filter_all_type', 'sales_hub_col_product_customer',
            'sales_hub_col_inquiry_no', 'sales_hub_col_date', 'sales_hub_col_product_type', 'sales_hub_col_qty_tier',
            'sales_hub_col_status', 'sales_hub_col_type', 'sales_hub_col_action', 'sales_hub_no_doc_number',
            'sales_hub_prospect_pill', 'sales_hub_row_open_edit', 'sales_hub_row_open_view',
            'sales_hub_no_matching_inquiry', 'sales_hub_will_open_edit', 'sales_hub_will_open_view',
            'sales_hub_click_row_hint', 'sales_hub_btn_open_view', 'sales_hub_btn_open_inquiry',
            'sales_form_title_new', 'sales_form_title_recalc', 'sales_form_title_revise', 'sales_form_not_saved_yet',
            'sales_form_back_to_menu', 'sales_form_readonly_note_prefix', 'sales_form_readonly_note_suffix',
            'sales_form_sales_officer_label', 'sales_form_officer_hint', 'sales_form_btn_confirm',
            'sales_form_btn_cancel_doc', 'sales_form_btn_mark_lost', 'sales_form_link_imposition',
            'sales_form_link_print_nota', 'sales_form_cancel_reason_label', 'sales_form_cancel_reason_placeholder',
            'sales_form_confirm_cancel_doc', 'sales_form_lost_reason_label', 'sales_form_lost_reason_placeholder',
            'sales_form_confirm_mark_lost', 'sales_form_section_header',
            'sales_quo_stat_total', 'sales_quo_stat_draft', 'sales_quo_stat_published', 'sales_quo_search_placeholder',
            'sales_quo_col_quotation', 'sales_quo_col_date', 'sales_quo_col_status', 'sales_quo_col_value',
            'sales_quo_col_action', 'sales_quo_action_confirm_publish', 'sales_quo_confirm_publish_prefix',
            'sales_quo_confirm_publish_suffix', 'sales_quo_empty', 'sales_quo_picker_title',
            'sales_quo_picker_search_placeholder', 'sales_quo_picker_min_chars', 'sales_quo_picker_no_match',
            'sales_co_col_ref_quotation', 'sales_co_col_date', 'sales_co_col_customer_po',
            'sales_co_col_ship_address_label', 'sales_co_col_discount_doc', 'sales_co_col_discount_qty',
            'sales_co_col_notes', 'sales_co_col_created_by', 'sales_co_col_updated_by', 'sales_co_search_col_no',
            'sales_co_search_col_customer', 'sales_co_stat_total', 'sales_co_stat_draft', 'sales_co_stat_confirmed',
            'sales_co_no_new_record_note', 'sales_co_search_placeholder', 'sales_co_bulk_selected_suffix',
            'sales_co_bulk_delete_confirm_prefix', 'sales_co_bulk_delete_confirm_suffix', 'sales_co_bulk_delete_btn',
            'sales_co_col_customer_order', 'sales_co_col_status', 'sales_co_col_net_receivable',
            'sales_co_close_item_preview', 'sales_co_view_item_preview', 'sales_co_checkbox_bulk_hint',
            'sales_co_action_open_tooltip', 'sales_co_action_confirm_confirm', 'sales_co_detail_col_product_service',
            'sales_co_detail_col_type', 'sales_co_detail_col_qty', 'sales_co_detail_col_price',
            'sales_co_detail_col_disc', 'sales_co_detail_col_subtotal', 'sales_co_detail_col_due_date',
            'sales_co_detail_no_items', 'sales_co_empty',
        ];
        $sys->table('sys_translations')->whereIn('trans_key', $keys)->delete();
    }
};
