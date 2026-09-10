<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// i18n Malam 4/7 (docs/BACKLOG.md §13 baris #4): Procurement inti -- HANYA
// grid list PR/PO/PV/PQ/PRT (pur/{pr,po,pv,pq,prt}/index.blade.php), toolbar
// & label status saja. entry.blade.php (5 file, 400-660 baris masing-masing,
// field kalkulasi harga/pajak/approval) SENGAJA BELUM disentuh -- di luar
// budget ~1 jam, dicatat sbg gap terbuka di BACKLOG.md. Key prefix proc_*,
// beberapa direuse dari malam sebelumnya (shared_*/sales_*) utk kata yang
// identik (Buka, klik-2x, Batal, Confirm, Draft).
return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            ['proc_col_date', 'Tanggal', 'Date'],
            ['proc_col_status', 'Status', 'Status'],
            ['proc_col_action', 'Aksi', 'Action'],
            ['proc_draft_placeholder', '(draft)', '(draft)'],
            ['proc_supplier_label', 'Supplier', 'Supplier'],
            ['proc_supplier_picker_search_placeholder', 'Ketik min. 2 huruf nama/kode supplier...', 'Type at least 2 letters of supplier name/code...'],
            ['proc_supplier_picker_min_chars', 'Ketik min. 2 huruf untuk cari.', 'Type at least 2 letters to search.'],
            ['proc_supplier_picker_no_match', 'Tidak ada supplier cocok.', 'No matching supplier.'],

            // PR
            ['proc_pr_stat_total', 'Total PR', 'Total PRs'],
            ['proc_pr_stat_submitted', 'Menunggu Approval', 'Awaiting Approval'],
            ['proc_pr_stat_progress', 'Disetujui / Diproses', 'Approved / In Progress'],
            ['proc_pr_stat_rejected', 'Ditolak / Revisi', 'Rejected / Revision'],
            ['proc_pr_search_placeholder', 'Cari no. PR / departemen / item…', 'Search PR no. / department / item…'],
            ['proc_pr_col_dept_no', 'Departemen / No. PR', 'Department / PR No.'],
            ['proc_pr_col_source', 'Sumber', 'Source'],
            ['proc_pr_col_need_date', 'Dibutuhkan', 'Needed'],
            ['proc_pr_col_est_value', 'Est. Nilai', 'Est. Value'],
            ['proc_pr_empty', 'Belum ada Purchase Requisition.', 'No Purchase Requisitions yet.'],
            ['proc_pr_picker_title', 'PR Baru — Pilih Departemen Peminta', 'New PR — Select Requesting Department'],

            // PO
            ['proc_po_stat_total', 'Total PO', 'Total POs'],
            ['proc_po_stat_rejected', 'Ditolak / Batal', 'Rejected / Cancelled'],
            ['proc_po_search_placeholder', 'Cari no. PO / item…', 'Search PO no. / item…'],
            ['proc_po_col_supplier_no', 'Supplier / No. PO', 'Supplier / PO No.'],
            ['proc_po_col_type', 'Tipe', 'Type'],
            ['proc_po_col_total_bill', 'Total Tagihan', 'Total Bill'],
            ['proc_po_empty', 'Belum ada Purchase Order.', 'No Purchase Orders yet.'],
            ['proc_po_picker_title', 'PO Baru — Pilih Supplier', 'New PO — Select Supplier'],

            // PV
            ['proc_pv_stat_total', 'Total SPB', 'Total Receipts'],
            ['proc_pv_stat_qc_pending', 'Menunggu QC', 'Awaiting QC'],
            ['proc_pv_stat_posted', 'Terposting', 'Posted'],
            ['proc_pv_stat_cancelled', 'Dibatalkan', 'Cancelled'],
            ['proc_pv_search_placeholder', 'Cari no. SPB / PO / surat jalan…', 'Search receipt no. / PO / delivery note…'],
            ['proc_pv_col_supplier_no', 'Supplier / No. SPB', 'Supplier / Receipt No.'],
            ['proc_pv_col_related_po', 'PO Terkait', 'Related PO'],
            ['proc_pv_col_warehouse', 'Gudang', 'Warehouse'],
            ['proc_pv_col_delivery_note', 'Surat Jalan', 'Delivery Note'],
            ['proc_pv_empty', 'Belum ada Penerimaan Barang.', 'No goods receipts yet.'],
            ['proc_pv_picker_title', 'Terima Barang — Langkah', 'Receive Goods — Step'],
            ['proc_pv_picker_step1', 'Pilih Supplier', 'Select Supplier'],
            ['proc_pv_picker_step2', 'Pilih Baris PO Terbuka', 'Select Open PO Lines'],
            ['proc_pv_col_open_po', 'PO Terbuka', 'Open PO'],
            ['proc_pv_col_lines', 'Baris', 'Lines'],
            ['proc_pv_col_oldest_po', 'PO Terlama', 'Oldest PO'],
            ['proc_pv_no_open_po_supplier', 'Tidak ada supplier dengan PO terbuka.', 'No supplier with an open PO.'],
            ['proc_pv_warehouse_placeholder', 'Kode gudang penerima (mis. GD-BB)', 'Receiving warehouse code (e.g. GD-BB)'],
            ['proc_pv_col_po_no', 'No PO', 'PO No.'],
            ['proc_pv_col_item', 'Item', 'Item'],
            ['proc_pv_col_remaining', 'Sisa', 'Remaining'],
            ['proc_pv_col_uom', 'UoM', 'UoM'],
            ['proc_pv_btn_back_step', 'Kembali', 'Back'],
            ['proc_pv_lines_selected_suffix', 'baris dipilih', 'lines selected'],
            ['proc_pv_btn_create', 'Buat SPB', 'Create Receipt'],

            // PQ
            ['proc_pq_stat_total', 'Total PQ', 'Total PQs'],
            ['proc_pq_search_placeholder', 'Cari no. PQ / item…', 'Search PQ no. / item…'],
            ['proc_pq_col_supplier_no', 'Supplier / No. PQ', 'Supplier / PQ No.'],
            ['proc_pq_col_valid_until', 'Berlaku s/d', 'Valid Until'],
            ['proc_pq_col_total_idr', 'Total (IDR)', 'Total (IDR)'],
            ['proc_pq_confirm_prefix', 'Konfirmasi PQ', 'Confirm PQ'],
            ['proc_pq_empty', 'Belum ada Purchase Quotation.', 'No Purchase Quotations yet.'],
            ['proc_pq_picker_title', 'PQ Baru — Pilih Supplier', 'New PQ — Select Supplier'],

            // PRT
            ['proc_prt_stat_total', 'Total Retur', 'Total Returns'],
            ['proc_prt_stat_value', 'Nilai Retur', 'Return Value'],
            ['proc_prt_search_placeholder', 'Cari no. retur / alasan…', 'Search return no. / reason…'],
            ['proc_prt_col_supplier_no', 'Supplier / No. Retur', 'Supplier / Return No.'],
            ['proc_prt_col_reason', 'Alasan', 'Reason'],
            ['proc_prt_col_disposition', 'Disposisi', 'Disposition'],
            ['proc_prt_source_from_pv', 'DARI PV', 'FROM RECEIPT'],
            ['proc_prt_source_from_stock', 'DARI STOK', 'FROM STOCK'],
            ['proc_prt_disposition_credit_note', 'Credit Note', 'Credit Note'],
            ['proc_prt_disposition_replace', 'Ganti Barang', 'Replace Goods'],
            ['proc_prt_empty', 'Belum ada Purchase Return.', 'No Purchase Returns yet.'],
            ['proc_prt_picker_title', 'Retur Baru — Pilih Sumber', 'New Return — Select Source'],
            ['proc_prt_picker_step_pv', 'Pilih Dokumen PV (ada barang reject QC)', 'Select Receipt Document (has QC-rejected goods)'],
            ['proc_prt_picker_step_choose_supplier', 'Pilih Supplier', 'Select Supplier'],
            ['proc_prt_source_pv_title', 'Dari PV (QC Reject)', 'From Receipt (QC Reject)'],
            ['proc_prt_source_pv_desc', 'Barang gagal QC saat penerimaan', 'Goods failed QC upon receipt'],
            ['proc_prt_source_stock_title', 'Dari Stok', 'From Stock'],
            ['proc_prt_source_stock_desc', 'Barang sudah di gudang, ditemukan masalah belakangan', 'Goods already in warehouse, problem found later'],
            ['proc_prt_col_pv_no', 'No. PV', 'Receipt No.'],
            ['proc_prt_col_qty_reject', 'Qty Reject', 'Reject Qty'],
            ['proc_prt_no_pv_reject', 'Tidak ada PV dengan barang reject QC.', 'No receipt with QC-rejected goods.'],
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
            'proc_col_date', 'proc_col_status', 'proc_col_action', 'proc_draft_placeholder', 'proc_supplier_label',
            'proc_supplier_picker_search_placeholder', 'proc_supplier_picker_min_chars', 'proc_supplier_picker_no_match',
            'proc_pr_stat_total', 'proc_pr_stat_submitted', 'proc_pr_stat_progress', 'proc_pr_stat_rejected',
            'proc_pr_search_placeholder', 'proc_pr_col_dept_no', 'proc_pr_col_source', 'proc_pr_col_need_date',
            'proc_pr_col_est_value', 'proc_pr_empty', 'proc_pr_picker_title',
            'proc_po_stat_total', 'proc_po_stat_rejected', 'proc_po_search_placeholder', 'proc_po_col_supplier_no',
            'proc_po_col_type', 'proc_po_col_total_bill', 'proc_po_empty', 'proc_po_picker_title',
            'proc_pv_stat_total', 'proc_pv_stat_qc_pending', 'proc_pv_stat_posted', 'proc_pv_stat_cancelled',
            'proc_pv_search_placeholder', 'proc_pv_col_supplier_no', 'proc_pv_col_related_po', 'proc_pv_col_warehouse',
            'proc_pv_col_delivery_note', 'proc_pv_empty', 'proc_pv_picker_title', 'proc_pv_picker_step1',
            'proc_pv_picker_step2', 'proc_pv_col_open_po', 'proc_pv_col_lines', 'proc_pv_col_oldest_po',
            'proc_pv_no_open_po_supplier', 'proc_pv_warehouse_placeholder', 'proc_pv_col_po_no', 'proc_pv_col_item',
            'proc_pv_col_remaining', 'proc_pv_col_uom', 'proc_pv_btn_back_step', 'proc_pv_lines_selected_suffix',
            'proc_pv_btn_create',
            'proc_pq_stat_total', 'proc_pq_search_placeholder', 'proc_pq_col_supplier_no', 'proc_pq_col_valid_until',
            'proc_pq_col_total_idr', 'proc_pq_confirm_prefix', 'proc_pq_empty', 'proc_pq_picker_title',
            'proc_prt_stat_total', 'proc_prt_stat_value', 'proc_prt_search_placeholder', 'proc_prt_col_supplier_no',
            'proc_prt_col_reason', 'proc_prt_col_disposition', 'proc_prt_source_from_pv', 'proc_prt_source_from_stock',
            'proc_prt_disposition_credit_note', 'proc_prt_disposition_replace', 'proc_prt_empty', 'proc_prt_picker_title',
            'proc_prt_picker_step_pv', 'proc_prt_picker_step_choose_supplier', 'proc_prt_source_pv_title',
            'proc_prt_source_pv_desc', 'proc_prt_source_stock_title', 'proc_prt_source_stock_desc',
            'proc_prt_col_pv_no', 'proc_prt_col_qty_reject', 'proc_prt_no_pv_reject',
        ];
        $sys->table('sys_translations')->whereIn('trans_key', $keys)->delete();
    }
};
