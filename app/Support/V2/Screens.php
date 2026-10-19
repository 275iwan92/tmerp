<?php

namespace App\Support\V2;

// Menu mockup.test: satu modul = lengkap sendiri (transaksi + master penyertanya), supaya hak akses per modul
// cukup membuka satu menu. Master yang dipakai beberapa modul = SATU layar, tampil di menu tiap modul.
// Layar: 'ref' = acuan tmerp sekarang (alur & field), 'mockup' = mockup HTML asli (tampilan), 'status' todo|review.
final class Screens
{
    // Layar bersama (dipakai beberapa modul).
    private static function shared(): array
    {
        return [
            'master-product' => ['Master Product', 'fa-solid fa-box', 'docs/flow_product_master.md (baru, v2)', null],
            'product-acc' => ['ACC Digital / Proof Digital', 'fa-solid fa-stamp', 'docs/flow_product_master.md §5', null],
            'master-raw' => ['Master Raw Material', 'fa-solid fa-layer-group', 'v2: md-raw (RawMaterialGrid) + md-raw-form', 'mockup_master_rawmaterial.html'],
            'master-customer' => ['Master Customer', 'fa-solid fa-building', 'v2: md-customer (CustomerGrid) + md-customer-form', 'mockup_master_customer.html'],
            'master-matcat' => ['Raw Material Categories', 'fa-solid fa-sitemap', 'v2: md-matcat (App\Support\V2\Grid\Master\MatCategoryGrid)', null],
            'master-uom' => ['Units of Measure', 'fa-solid fa-ruler', 'master/uom.blade.php', 'mockup_master_uom_v4.html'],
            'master-currency' => ['Currencies & Exchange Rates', 'fa-solid fa-coins', 'master/currency.blade.php', null],
            'master-card' => ['Master Card', 'fa-solid fa-id-card', 'production/prodev.blade.php (Master Card)', 'mockup_mastercard_ver2.jpg'],
            'prodev' => ['Pro-Dev (Product Development)', 'fa-solid fa-table-cells', 'production/prodev.blade.php + prodev-list.blade.php', 'mockup_prodev_imposition.html'],
        ];
    }

    public static function modules(): array
    {
        $s = self::shared();
        $it = fn (string $slug, ?array $def = null) => self::item($slug, $def ?? $s[$slug]);

        return [
            ['code' => 'SALES', 'short' => 'SLS', 'label' => 'Sales', 'icon' => 'fa-solid fa-cart-shopping', 'groups' => [
                ['label' => 'Inquiry & Quotation', 'items' => [
                    $it('inquiry', ['Inquiry Product (worklist)', 'fa-solid fa-inbox', 'inquiry/hub.blade.php', 'inquiry_main_hub.html']),
                    $it('inquiry-new', ['New Inquiry', 'fa-solid fa-file-circle-plus', 'inquiry/form.blade.php', 'inquiry_new.html']),
                    $it('inquiry-revise', ['Inquiry Revise', 'fa-solid fa-file-pen', 'inquiry/revise.blade.php', 'inquiry_revise_v2.html']),
                    $it('inquiry-recalc', ['Inquiry Recalc', 'fa-solid fa-calculator', 'inquiry/recalc.blade.php', 'inquiry_recalc_v2.html']),
                    $it('prodev'),
                    $it('master-card-collection', ['Master Card Collection', 'fa-solid fa-id-card', 'v2 baru: upload master card lama (Excel/foto) -> Master Product / Inquiry', 'master_card_collection_v2.jpg']),
                    $it('sales-price', ['Prices from Pro-Cost (tiers & prices)', 'fa-solid fa-tags', 'inquiry/costing.blade.php (hanya tier & harga terpublish)', null]),
                    $it('quotation', ['Quotation', 'fa-solid fa-file-signature', 'quotation/entry.blade.php + list', 'mockup_sales_quotation_entry.html']),
                    $it('nota-inquiry', ['Inquiry Note', 'fa-solid fa-receipt', 'inquiry/nota.blade.php', 'nota_inquiry_mockup.html']),
                ]],
                ['label' => 'Order', 'items' => [
                    $it('customer-order', ['Customer Order', 'fa-solid fa-bag-shopping', 'co/entry.blade.php + list', 'mockup_co_entry.html']),
                    $it('cpdel', ['Customer Plan Delivery', 'fa-solid fa-truck-ramp-box', 'co/delivery-plan.blade.php', 'co_delivery_plan_mockup.html']),
                    $it('approval-co', ['CO Approval Center', 'fa-solid fa-stamp', 'v2 baru: DevLib Form Approval + flow engine CO (F&A SPV -> Operational Manager)', null]),
                ]],
                ['label' => 'Delivery', 'items' => [
                    $it('delivery-order', ['Delivery Order', 'fa-solid fa-truck', 'dispatch/do/entry.blade.php + list', null]),
                    $it('delivery-notes', ['Delivery Notes', 'fa-solid fa-file-lines', 'dispatch/do/print-surat-jalan.blade.php', null]),
                    $it('proof-of-delivery', ['Proof of Delivery', 'fa-solid fa-clipboard-check', 'v2 baru: tm2sdi.do_delivery_pod (KPI janji kirim)', null]),
                    $it('subcon-delivery-note', ['Subcontract Delivery Note', 'fa-solid fa-dolly', 'v2 baru: tm2sdi.do_delivery do_type SUBCON (backlog02 19.1)', null]),
                ]],
                ['label' => 'CRM', 'items' => [
                    $it('crm-customer', ['Customer 360', 'fa-solid fa-address-card', 'v2 baru: ringkasan satu customer (docs/md_for_systems/sales_tier1.md)', null]),
                    $it('crm-activity', ['Activities & Follow-up', 'fa-solid fa-phone-volume', 'v2 baru: tm2sdi.crm_activity (perluasan quo_activity)', null]),
                    $it('crm-complaint', ['Complaint & Return', 'fa-solid fa-triangle-exclamation', 'v2 baru: tm2sdi.crm_complaint + crm_complaint_item', null]),
                ]],
                ['label' => 'Master', 'items' => [$it('master-product'), $it('product-acc'), $it('master-raw'), $it('master-customer'), $it('master-card')]],
                ['label' => 'Lookup', 'items' => [$it('master-matcat'), $it('master-uom'), $it('master-currency')]],
                ['label' => t('Reports'), 'items' => [
                    $it('win-rate', ['Win-Rate Sales', 'fa-solid fa-trophy', 'sales/win-rate.blade.php', null]),
                    $it('quotation-outstanding', ['Open Quotations', 'fa-solid fa-hourglass-half', 'v2 baru: quo_quotation PUBLISHED belum WIN/LOST', null]),
                    $it('quotation-followup', ['Quotation Follow-up', 'fa-solid fa-phone', 'v2 baru: quo_activity', null]),
                ]],
                ['label' => t('Flow'), 'items' => [self::flowItem('SALES', 'Sales Flow: Inquiry → CPDEL')]],
                ['label' => 'Product Market', 'items' => [
                    $it('sales-market-form', ['Master Product Market Form', 'fa-solid fa-tags', 'v2 baru: Product Market (kategori, detail kategori, default Inquiry New, bahan, ukuran standar) di atas tm2mst.prod_market dan prod_type_size; dibuka dari grid sales-product-market', null]),
                ]],
            ]],
            ['code' => 'PROC', 'short' => 'PRC', 'label' => 'Procurement', 'icon' => 'fa-solid fa-cart-flatbed', 'groups' => [
                ['label' => 'Requisition', 'items' => [
                    $it('purchase-requisition', ['Purchase Requisition (PR)', 'fa-solid fa-file-circle-plus', 'pur/pr/index + entry.blade.php', 'mockup_purchase_requisition.html']),
                    $it('approval-pr', ['PR Approval Center', 'fa-solid fa-stamp', 'pur/pr/approval.blade.php', 'mockup_pr_approval_center.html']),
                ]],
                ['label' => 'Order', 'items' => [
                    $it('purchase-quotation', ['Purchase Quotation (PQ)', 'fa-solid fa-file-import', 'pur/pq/index + entry.blade.php (v2: upload file penawaran)', null]),
                    $it('purchase-order', ['Purchase Order (PO)', 'fa-solid fa-file-invoice-dollar', 'pur/po/index + entry.blade.php', null]),
                    $it('approval-po', ['PO Approval Center', 'fa-solid fa-stamp', 'pur/po/approval.blade.php', 'mockup_po_approval_center.html']),
                ]],
                ['label' => 'Receive', 'items' => [
                    $it('purchase-receive', ['Goods Receipt (SPB)', 'fa-solid fa-truck-ramp-box', 'pur/pv/index + entry.blade.php', null]),
                ]],
                ['label' => 'Return', 'items' => [
                    $it('approval-prt', ['Purchase Return Approval Center', 'fa-solid fa-stamp', 'pur/prt/approval.blade.php', null]), $it('pur-return-form', ['Purchase Return Form', 'fa-solid fa-reply', 'v2 baru: Purchase Return (NRB) di atas tm2pur.prt_return + prt_line; sumber penerimaan QC reject atau stok; Submit = flow approval PRT, keputusan di Approval Center; dibuka dari grid purchase-return', null]),
                ]],
                ['label' => 'Master', 'items' => [$it('master-raw')]],
            ]],
            ['code' => 'COSTING', 'short' => 'PCO', 'label' => 'Product Costing', 'icon' => 'fa-solid fa-scale-balanced', 'groups' => [
                ['label' => 'Costing', 'items' => [
                    $it('procost', ['Pro-Cost (Costing Hub)', 'fa-solid fa-scale-balanced', 'inquiry/costing.blade.php + costing-list', 'inquiry_costing_hub.html']),
                    $it('procost-instant', ['Pro-Cost Instant (manual)', 'fa-solid fa-bolt', 'inquiry/costing-instant.blade.php + list', 'inquiry_costing_manual.html']),
                ]],
                ['label' => 'Master', 'items' => [$it('master-product'), $it('master-raw'), $it('prodev')]],
                ['label' => t('Flow'), 'items' => [self::flowItem('COSTING', 'Costing Flow: ProDev → ProCost → Publish')]],
            ]],
            ['code' => 'FINACC', 'short' => 'FIN', 'label' => 'Finance', 'icon' => 'fa-solid fa-coins', 'groups' => [
                ['label' => 'Master', 'items' => [
                    $it('acc-coa-form', ['Master of Accounts Form', 'fa-solid fa-book', 'v2 baru: akun GL di atas tm2acc.fin_account; dibuka dari grid acc-coa', null]),
                    $it('acc-fixed-asset-form', ['Fixed Asset Form', 'fa-solid fa-building', 'v2 baru: aset tetap di atas tm2acc.fin_asset + jurnal kapitalisasi / pelepasan; dibuka dari grid acc-fixed-asset', null]),
                    $it('acc-amortization-form', ['Amortization Schedule Form', 'fa-solid fa-arrow-trend-down', 'v2 baru: jadwal amortisasi (prabayar, tak berwujud, leasehold) di atas tm2acc.fin_asset + jurnal kapitalisasi; dibuka dari grid acc-amortization', null]),
                    $it('acc-depreciation-run',['Depreciation Run', 'fa-solid fa-calendar-check', 'v2 baru: pratinjau dan jurnal penyusutan / amortisasi bulanan di atas tm2acc.fin_asset + fin_asset_depr (AssetService)', null]),
                    $it('acc-payables-form',['Vendor Bill Form', 'fa-solid fa-file-invoice-dollar', 'v2 baru: tagihan vendor (SUPPLIER_BILL) di atas tm2acc.fin_invoice + jurnal PUR + fin_payment_alloc; dibuka dari grid acc-payables', null]),
                    $it('acc-cash-bank-form',['Cash and Bank Voucher Form', 'fa-solid fa-money-check-dollar', 'v2 baru: voucher kas & bank (BKM / BKK / BBM / BBK) di atas tm2acc.fin_voucher + fin_voucher_line; dibuka dari grid acc-cash-bank', null]),
                    $it('acc-journal-form',['Journal Entry Form', 'fa-solid fa-file-pen', 'v2 baru: jurnal umum manual (header + baris debit / kredit) di atas tm2acc.fin_voucher + fin_voucher_line; dibuka dari grid acc-journal', null]),
                ]],
                ['label' => t('Cash & Bank'), 'items' => [
                    $it('petty-cash', ['Petty Cash', 'fa-solid fa-wallet', 'v2 baru: tm2acc.fin_petty_cash + fin_petty_cash_tx (PO Kontan)', null]),
                ]],
                ['label' => 'Control Center', 'items' => [
                    $it('acc-dashboard', ['Accounting Dashboard', 'fa-solid fa-gauge-high', 'v2: App\Support\V2\Data\FinanceDesk (FinanceMetrics)', null, true]),
                    $it('acc-engine', ['Process Engine', 'fa-solid fa-gears', 'v2: FinanceDesk (FinanceMetrics + fin_approval_rule)', null]),
                ]],
                ['label' => 'Controls & Tax', 'items' => [
                    $it('acc-tax', ['Indonesian Tax', 'fa-solid fa-percent', 'v2: App\Support\V2\Data\TaxDesk (TaxEngine, fin_tax_register)', null, true]),
                    $it('acc-closing', ['Period Closing', 'fa-solid fa-lock', 'v2: App\Support\V2\Data\ClosingDesk (CloseService, FinPeriodGuard)', null, true]),
                ]],
                ['label' => 'Financial Reports', 'items' => [
                    $it('acc-statements', ['Financial Statements', 'fa-solid fa-file-invoice-dollar', 'v2: App\Support\V2\Data\FinancialStatements (LedgerService, ringkasan Neraca + Laba Rugi + Arus Kas)', null, true]),
                    $it('acc-rpt-balance-sheet', ['Balance Sheet', 'fa-solid fa-scale-balanced', 'v2: FinancialStatements + x-dl::statement (LedgerService::balanceSheet)', null, true]),
                    $it('acc-rpt-income-statement', ['Income Statement', 'fa-solid fa-chart-line', 'v2: FinancialStatements + x-dl::statement (LedgerService::incomeStatement)', null, true]),
                    $it('acc-rpt-cash-flow', ['Cash Flow', 'fa-solid fa-money-bill-transfer', 'v2: FinancialStatements + x-dl::statement (LedgerService::cashFlow)', null, true]),
                ]],
                ['label' => 'Transaction Flows', 'items' => [
                    $it('acc-bank', ['Bank & Reconciliation', 'fa-solid fa-building-columns', 'v2: App\Support\V2\Data\BankDesk (BankReconService)', null, true]),
                    $it('customer-invoice', ['Customer Invoice', 'fa-solid fa-file-invoice-dollar', 'v2 baru: tm2acc.fin_invoice + fin_invoice_line (invoice dari Surat Jalan + tagihan uang muka)', 'customer_invoice_v2.jpg']),
                    $it('supplier-invoice-receipt', ['Supplier Invoice Receipt', 'fa-solid fa-file-circle-check', 'v2 baru: tm2acc.fin_invoice_receipt + fin_invoice_receipt_line (tanda terima tagihan supplier)', null]),
                ]],
            ]],
            ['code' => 'INV', 'short' => 'INV', 'label' => 'Inventory', 'icon' => 'fa-solid fa-warehouse', 'groups' => [
                ['label' => 'Warehouse', 'items' => [
                    $it('warehouse-map', ['Warehouse Map', 'fa-solid fa-map-location-dot', 'v2 baru: docs/warehouselayout_tier1.md (tm2mst.wh_warehouse + wh_zone + wh_loc)', 'warehouse_layout_v2.jpg', true]),
                    $it('stock-transfer', ['Stock Transfer', 'fa-solid fa-right-left', 'v2 baru: tm2inv.inv_transfer + inv_transfer_line (pindah stok antar bin / gudang)', null]),
                    $it('wh-receiving-putaway', ['Receiving & Putaway', 'fa-solid fa-dolly', 'v2 baru: tm2inv.wh_task (tugas putaway) + inv_pallet + inv_batch; usulan bin dari aturan area (WarehouseTaskService, PutawayPlanner)', 'wh_v201_receiving_putaway.jpg']),
                    $it('wh-task-board', ['Task Board', 'fa-solid fa-table-columns', 'v2 baru: papan baca-saja tm2inv.wh_task + wh_pick_list + inv_transfer (In Transit) + area hitung jatuh tempo (WarehouseTaskBoard)', 'menu_v201_split.jpg']),
                    $it('approval-tf', ['Stock Transfer Approval Center', 'fa-solid fa-stamp', 'v2 baru: flow engine modul TF (bawaan tidak aktif, Setup > Security > Approval Levels)', null]),
                ]],
                ['label' => 'Label & QR Code', 'items' => [
                    $it('label-location', ['Print Location Label (QR)', 'fa-solid fa-location-dot', 'v2 baru: label bin (kode, kapasitas, QR + barcode), tm2inv.inv_label_print', null, true]),
                    $it('label-pallet', ['Print Pallet Label (QR)', 'fa-solid fa-pallet', 'v2 baru: tm2inv.inv_pallet + inv_pallet_log (label kedatangan, label ulang sesudah dipakai, kode palet supplier, kedaluwarsa)', null, true]),
                    $it('label-item', ['Print Item Label (QR)', 'fa-solid fa-tag', 'v2 baru: label posisi stok (item, bin, qty, tanggal datang, QR + barcode)', null, true]),
                    $it('scan-track', ['Track via QR/Barcode', 'fa-solid fa-magnifying-glass-location', 'v2 baru: lacak bin / item / transfer dari kode hasil pindai', null, true]),
                ]],
                ['label' => 'Stock Documents', 'items' => [
                    $it('inv-stock-doc-form', ['Stock Document Form', 'fa-solid fa-arrow-right-to-bracket', 'v2 baru: dokumen stok manual (Stock In RM / FG / SFG, Stock Out RM / SFG) di atas tm2inv.rm_stock / fg_stock / sfg_stock; Confirm = posting ke Kartu Stok lewat StockPostingService; dibuka dari grid inv-*-stock-in dan inv-*-stock-out', null]), $it('inv-stock-adj-form', ['Stock Adjustment Form', 'fa-solid fa-scale-balanced', 'v2 baru: Stock Adjustment dan Stock Opname (RM / FG / SFG) di atas tm2inv.rm_stock / fg_stock / sfg_stock (doc_cat AD / OP); Submit = flow approval AD / OP, keputusan di Approval Center, level terakhir memposting ke Kartu Stok; dibuka dari grid inv-*-stock-adj', null]),
                ]],
                ['label' => 'Stock Adjustment', 'items' => [
                    $it('approval-stock', ['Adjustment & Opname Approval Center', 'fa-solid fa-stamp', 'inventory/stock-adjustment-approval.blade.php', null]),
                ]],
                ['label' => 'Batch & Quality', 'items' => [
                    $it('conditional-stock', ['Conditional Stock', 'fa-solid fa-hourglass-half', 'v2 baru: tm2inv.inv_batch (Conditional / Quarantine) + inv_qc_decision + inv_qc_hold + inv_supplier_claim (QcDecisionService, QcTraceService)', 'inv_v201_batch_form.jpg']),
                    $it('qc-first-article', ['First Article', 'fa-solid fa-clipboard-check', 'v2 baru: tm2inv.inv_qc_first_article (QcDecisionService::firstArticle)', null]),
                    // Alur kurang kirim lintas peran (gudang, PPIC, pembelian): tanpa gerbang modul, hak tiap langkah dijaga QcAuthority.
                    [...$it('supplier-shortage', ['Shortage Request', 'fa-solid fa-boxes-packing', 'v2 baru: tm2inv.inv_shortage_request (ShortageRequestService, ShortageDetector)', null]), 'module_code' => 'GENERAL'],
                ]],
                ['label' => 'Valuation & Period', 'items' => [
                    $it('inv-valuation', ['Valuation & Period', 'fa-solid fa-scale-balanced', 'v2 baru: tm2inv.inv_period (InvPeriodService) + nilai per kelas + jalan pintas laporan valuasi', null]),
                    $it('landed-cost', ['Landed Cost', 'fa-solid fa-truck-ramp-box', 'v2 baru: tm2inv.inv_landed_cost + inv_landed_cost_line (LandedCostService, RepostService)', null]),
                    $it('obsolete-stock', ['Obsolete Stock', 'fa-solid fa-hourglass-end', 'v2 baru: tm2inv.inv_obsolete_provision (ObsolescenceService)', null]),
                ]],
            ]],
            ['code' => 'PROD', 'short' => 'PP', 'label' => 'Production', 'icon' => 'fa-solid fa-industry', 'groups' => [
                ['label' => 'Planning', 'items' => [
                    $it('ppic-color-sequence', ['Colour Sequence', 'fa-solid fa-palette', 'v2 baru: urutan job di proses cetak per mesin, tinta tiap job (ProDev) dan menit cuci sebelum job', null]),
                    $it('ppic-settings', ['PPIC Settings', 'fa-solid fa-sliders', 'v2 baru: satu pintu untuk semua pengaturan PPIC dalam 8 kategori, dengan kotak cari', null]),
                    $it('production-scheduling', ['PPIC Workspace', 'fa-solid fa-chart-gantt', 'v2 baru: PPIC (engine Draft + Confirm officer) -- Gantt, Kanban, Calendar, Grid, Monitoring', 'ppic_sched_gantt.jpg']),
                    $it('production-wo', ['Work Order (PPIC)', 'fa-solid fa-clipboard-list', 'v2 baru: Work Order dari ProDev (BOP, routing, BOM, mesin yang bisa antre)', 'ppic_wo_detail.jpg']),
                    $it('production-live', ['Live Schedule', 'fa-solid fa-chart-gantt', 'v2 baru: pemantauan read-only jadwal yang berlaku (operasi CONFIRMED / berjalan) per proses dan mesin, plus rincian good, bad, status, operator', null]),
                    $it('crew-form', ['Assign Crew Form', 'fa-solid fa-people-group', 'v2 baru: crew per mesin SBU Offset (nama = nickname + indeks, maks 4), periode dari-sampai, anggota maks 10 dari karyawan produksi, shift dari Master Shift', null]),
                    $it('production-learning', ['Learning Changes', 'fa-solid fa-graduation-cap', 'v2 baru: nilai changeover Assumed dikoreksi dari waktu setup nyata, tiap perubahan tercatat dan bisa dikembalikan', null]), $it('prod-scrap-form', ['Scrap Type Form', 'fa-solid fa-trash-can-arrow-up', 'v2 baru: master Tipe Scrap (Reject / Waste) di atas tm2prd.prd_scrap_type; kode unik per kategori, kategori dan kode terkunci bila sudah dipakai di catatan output produksi; dibuka dari grid prod-scrap', null]), $it('prod-routing-form', ['Routing Template Form', 'fa-solid fa-route', 'v2 baru: master Routing Template di atas tm2mst.prod_routing_template + prod_routing_step; header dan semua langkah disimpan bersama Save, mesin pilihan harus mampu mengerjakan proses, hasil pemeriksaan urutan dan usulan mesin hanya-baca; dibuka dari grid prod-routing', null]), $it('prod-bop-form', ['BOP Rule Form', 'fa-solid fa-list-check', 'v2 baru: aturan Bill of Process di atas tm2mst.prod_bop_rule (dibaca engine ProDev); Seq diisi langsung, tab Usage suggestions (Apply suggestion, Auto apply, hitung ulang), hanya admin yang mengubah; dibuka dari grid prod-bop-rule', null]), $it('prod-process-form', ['Process Form', 'fa-solid fa-gears', 'v2 baru: master Process di atas tm2mst.prod_process + prod_process_machine (data real); empat tab Main info, Parameters and capacity, Cost and tooling, Executing machines (matriks mesin disimpan bersama Save, angka kosong tetap kosong, pasangan dinonaktifkan bukan dihapus), nilai efektif hanya-baca; dibuka dari grid prod-process', null]), $it('prod-machine-form', ['Machine Form', 'fa-solid fa-industry', 'v2 baru: master Machine di atas tm2mst.prod_machine (data real) dengan enam tab (identitas, spesifikasi teknis, kapasitas, biaya dan aset, perawatan dan OEE, proses); matriks proses dan log downtime disimpan bersama Save, angka kosong tetap kosong, OEE hanya-baca; dibuka dari grid prod-machine', null]),
                ]],
                ['label' => 'Entry OUTPUT', 'items' => [
                    $it('production-operator', ['Production Output', 'fa-solid fa-stopwatch', 'v2 baru: entry hasil produksi di mesin untuk crew yang login: timer SETUP dan PRODUCTION, LIST OF JOBS dari PPIC Planning, input GOOD / BAD', null]),
                ]],
                ['label' => 'Quality', 'items' => [
                    $it('qc-complaint-review', ['Complaint Review (QA)', 'fa-solid fa-magnifying-glass-chart', 'v2 baru: tm2prd.qc_complaint_review (jawaban QA atas tm2sdi.crm_complaint)', null]),
                ]],
            ]],
            ['code' => 'MAINT', 'short' => 'MT', 'label' => 'Maintenance', 'icon' => 'fa-solid fa-screwdriver-wrench', 'groups' => [
                ['label' => 'Dashboard', 'items' => [
                    $it('maintenance-dashboard', ['Maintenance Dashboard', 'fa-solid fa-gauge-high', 'v2 baru: angka dari KpiService (OEE dari Production, order dari tm2mnt)', 'maintenance_v2_dashboard.jpg']),
                ]],
                ['label' => 'Preventive Maintenance', 'items' => [
                    $it('maintenance-plan', ['Preventive Plan', 'fa-solid fa-calendar-check', 'v2 baru: tm2mnt.mnt_plan + mnt_plan_task + mnt_plan_part (mockup maintenance_v2_work.jpg)', 'maintenance_v2_work.jpg']),
                    $it('maintenance-pmo', ['Preventive Maintenance Order', 'fa-solid fa-screwdriver-wrench', 'v2 baru: tm2mnt.mnt_order (PMO: Preventive, Inspection)', 'maintenance_v2_work.jpg']),
                    $it('maintenance-pm-calendar', ['PM Calendar', 'fa-solid fa-calendar-days', 'v2 baru: kalender PMO dari tm2mnt.mnt_order', 'maintenance_v2_flow.jpg']),
                    $it('maintenance-daily-check', ['Daily Check', 'fa-solid fa-clipboard-check', 'v2 baru: tm2mnt.mnt_daily_check + mnt_daily_check_line', 'maintenance_v2_flow.jpg']),
                ]],
                ['label' => 'Breakdown Maintenance', 'items' => [
                    $it('maintenance-request', ['Maintenance Request', 'fa-solid fa-triangle-exclamation', 'v2 baru: tm2mnt.mnt_request (Accept membuat BMO, P1 otomatis)', null]),
                    $it('maintenance-my-work', ['My Work', 'fa-solid fa-user-gear', 'v2 baru: tm2mnt.mnt_order milik teknisi yang login', null]),
                    $it('maintenance-bmo', ['Breakdown Maintenance Order', 'fa-solid fa-screwdriver-wrench', 'v2 baru: tm2mnt.mnt_order (BMO: Breakdown, Corrective)', 'maintenance_v2_work.jpg']),
                ]],
                ['label' => 'Master Data', 'items' => [
                    $it('maintenance-profile', ['Asset Maintenance Profile', 'fa-solid fa-gears', 'v2 baru: tm2mnt.mnt_asset + mnt_watch_point + mnt_asset_part (mockup maintenance_v2_master.jpg)', 'maintenance_v2_master.jpg']),
                    $it('maintenance-documents', ['SOP and Work Instructions', 'fa-solid fa-file-lines', 'v2 baru: tm2mnt.mnt_document + mnt_document_line (draf: resources/data/maintenance/sop_wi.json)', null]),
                ]],
            ]],
            ['code' => 'HRM', 'short' => 'HR', 'label' => 'Human Resource', 'icon' => 'fa-solid fa-people-group', 'groups' => [
                ['label' => 'Time Attendance', 'items' => [
                    // Milik sendiri: semua karyawan boleh absen (modul GENERAL = tanpa gerbang modul), seperti self-service v1.
                    $it('hrm-roster', ['Shift Roster', 'fa-solid fa-calendar-week', 'v2: App\Support\V2\Data\ShiftRoster + x-mp::roster-board (emp_shift_roster, emp_shift_roster_batch)', null, true]),
                    $it('hrm-overtime', ['Overtime', 'fa-solid fa-business-time', 'v2: App\Support\V2\Data\Overtime (tm2mst.emp_overtime_request)', null]),
                    [...$it('hrm-clockin', ['GPS Clock-In (Mobile)', 'fa-solid fa-location-crosshairs', 'v2: App\Support\V2\Data\GpsClock + x-dl::geo-clock (att_mobile_gps_log)', null]), 'module_code' => 'GENERAL'],
                ]],
                ['label' => 'Recruitment', 'items' => [
                    $it('hrm-pipeline-board', ['Pipeline Board', 'fa-solid fa-diagram-next', 'v2: App\Support\V2\Data\RecruitmentPipeline + x-dl::kanban (app_application, app_pipeline_stage_config)', null, true]),
                ]],
                ['label' => 'Payroll', 'items' => [
                    $it('hrm-bank-transfer', ['Bank Transfer Generator', 'fa-solid fa-building-columns', 'v2: App\Support\V2\Data\PayrollDisbursement (pyr_payslip x emp_bank_account)', null, true]),
                    $it('hrm-cash-roster', ['Cash Roster & Denominations', 'fa-solid fa-money-bill-wave', 'v2: App\Support\V2\Data\PayrollDisbursement + print cash-roster', null, true]),
                    $it('hrm-attendance-upload', ['Attendance Upload', 'fa-solid fa-file-arrow-up', 'v2: App\Support\V2\Data\AttendanceUpload (att_finger_import / att_finger_raw)', null]),
                ]],
                ['label' => 'Organization', 'items' => [
                    $it('hrm-employee-form', ['Master Employee Form', 'fa-solid fa-id-badge', 'v2 baru: profil pegawai (banner + KPI + tab) di atas tm2mst.emp_employee; dibuka dari grid hrm-employee', null]),
                    $it('hrm-position-form', ['Master Position Form', 'fa-solid fa-user-tie', 'v2 baru: jabatan, job description (HR Officer) dan KPI jabatan di atas tm2mst.org_position + org_position_kpi; dibuka dari grid hrm-position', null]), $it('hrm-correction-form', ['Correction Request Form', 'fa-solid fa-user-pen', 'v2 baru: keputusan HR atas pengajuan koreksi data pegawai di atas tm2mst.emp_correction_request; Approve menerapkan kolom emp_employee atau rekening gaji, Reject wajib beralasan dan memberi notifikasi, rekening gaji disamarkan bagi yang tidak berhak payroll; dibuka dari grid hrm-correction', null]), $it('hrm-leave-form', ['Leave Request Form', 'fa-solid fa-calendar-check', 'v2 baru: keputusan HR atas pengajuan cuti / sakit / izin di atas tm2mst.emp_leave_request; Approve menambah used_days saldo hanya untuk yang memotong saldo, Reject wajib beralasan, kartu saldo cuti hanya-baca, notifikasi ke pegawai; dibuka dari grid hrm-leave', null]), $it('hrm-attendance-form', ['Attendance Form', 'fa-solid fa-user-clock', 'v2 baru: presensi harian satu pegawai di atas tm2mst.att_daily_status; override status dengan alasan audit (dilindungi dari hitung ulang) dan Recalculate this day; dibuka dari grid hrm-attendance', null]), $it('hrm-gps-form', ['GPS Approval Form', 'fa-solid fa-location-dot', 'v2 baru: keputusan HR atas presensi GPS di luar radius di atas tm2mst.att_mobile_gps_log; Approve menulis att_finger_raw dan menghitung ulang status harian, Reject wajib beralasan; dibuka dari grid hrm-gps', null]),
                    $it('hrm-org-chart', ['Organization Chart', 'fa-solid fa-sitemap', 'v2: App\Support\V2\Data\OrgStructure + x-dl::org-chart (org_position)', null]),
                    $it('approval-acting', ['Temporary Acting Officer', 'fa-solid fa-people-arrows', 'v2 baru: tm2mst.flow_position_acting (dipindah dari Setup > Security, user 2026-10-04)', null]),
                ]],
            ]],
            ['code' => 'MGMT', 'short' => 'MGT', 'label' => 'Management Reports', 'icon' => 'fa-solid fa-chart-line', 'groups' => [
                ['label' => 'Summary', 'items' => [
                    $it('mgmt-executive', ['Executive Summary', 'fa-solid fa-gauge-high', 'v2: App\Support\V2\Data\ExecutiveSummary (App\Services\Report\ManagementReportService)', null, true]),
                ]],
            ]],
            ['code' => 'SETUP', 'short' => 'SET', 'label' => 'Setup', 'icon' => 'fa-solid fa-gear', 'groups' => [
                ['label' => 'Identification', 'items' => [
                    $it('setup-identification', ['Identification', 'fa-solid fa-id-badge', 'v2: App\Support\V2\Data\Identification (sys_constant APP_* / COMPANY_*, org_company TM)', null]),
                ]],
                ['label' => 'Dummy Data & Migration', 'items' => [
                    $it('setup-migration', ['Migration', 'fa-solid fa-truck-ramp-box', 'v2: App\Support\V2\Data\MigrationDesk (App\Services\Migration\*)', null]),
                    $it('setup-data-origin', ['Dummy & Legacy Data', 'fa-solid fa-database', 'v2: App\Support\V2\Data\DataOriginDesk (App\Services\Data\DataOriginService)', null]),
                ]],
                ['label' => 'Security', 'items' => [
                    $it('security-permissions', ['Groups on Modules', 'fa-solid fa-user-lock', 'v2: App\Support\V2\Data\GroupPermissions (sec_group_permission)', null]),
                    $it('approval-setting', ['Approval Levels', 'fa-solid fa-list-ol', 'v2 baru: tm2mst.flow_config + flow_config_step (pengganti m_authorize)', null]),
                ]],
            ]],
            ['code' => 'MDATA', 'short' => 'MD', 'label' => 'Master Data', 'icon' => 'fa-solid fa-database', 'groups' => [
                ['label' => 'Customer', 'items' => [
                    $it('md-customer-form', ['Master Customer Form', 'fa-solid fa-building', 'v2 baru: customer (identitas, spek cetak, penjualan dan keuangan, alamat, kontak dan cabang tambahan) di atas tm2mst.cust_customer; dibuka dari grid md-customer', null]),
                ]],
                ['label' => 'Supplier', 'items' => [
                    $it('md-supplier-form', ['Master Supplier Form', 'fa-solid fa-truck-field', 'v2 baru: supplier (identitas, alamat + cabang, PIC + kontak, termin dan pajak) di atas tm2mst.sup_supplier; dibuka dari grid md-supplier', null]),
                ]],
                ['label' => 'Raw Material', 'items' => [
                    $it('md-raw-form', ['Master Raw Material Form', 'fa-solid fa-layer-group', 'v2 baru: item bahan baku (kategori penyusun kode item, spesifikasi dan QC, UoM dan pengadaan, stok dan lokasi, akun GL, Confirm) di atas tm2mst.mat_raw; dibuka dari grid md-raw', null]),
                ]],
                ['label' => 'Shift', 'items' => [
                    $it('md-shift-form', ['Master Shift Form', 'fa-solid fa-clock', 'v2 baru: shift (nama, departemen, kode, jam kerja tujuh hari dengan istirahat) di atas tm2mst.emp_shift dan emp_shift_detail; dibuka dari grid md-shift', null]),
                ]],
            ]],
            // Khusus developer: tidak tampil untuk akun biasa.
            ...(auth()->user()?->is_admin ? [['code' => 'DEV', 'short' => 'DEV', 'label' => 'Developer', 'icon' => 'fa-solid fa-code', 'groups' => [
                ['label' => 'Database', 'items' => [
                    ['code' => 'S-dictionary', 'label' => t('Data Dictionary'), 'icon' => 'fa-solid fa-database', 'url' => route('v2.dictionary'), 'type' => 'report'],
                ]],
            ]]] : []),
        ];
    }

    private static function item(string $slug, array $def): array
    {
        // $filters = true: layar memakai query GET sebagai saringan (gedung, lantai, kode), bukan penanda form baru.
        [$label, $icon, $ref, $mockup, $filters] = array_pad($def, 5, false);
        $status = is_file(resource_path("views/v2/screens/{$slug}.blade.php")) ? 'review' : 'todo';

        return ['code' => 'S-'.$slug, 'slug' => $slug, 'label' => t($label), 'icon' => $icon, 'ref' => $ref, 'mockup' => $mockup, 'status' => $status,
            'url' => route('v2.screen', $slug), 'type' => 'form', 'badge' => $status === 'review' ? 'R' : 'To Do', 'badgeTone' => $status === 'review' ? 'partial' : 'todo', 'filters' => (bool) $filters];
    }

    private static function flowItem(string $module, string $label): array
    {
        return ['code' => 'F-'.$module, 'label' => t($label), 'icon' => 'fa-solid fa-diagram-project', 'url' => route('v2.flow', $module), 'type' => 'report'];
    }

    // Bentuk untuk <x-dl::shell :modules>.
    public static function shell(): array
    {
        return array_map(fn ($m) => ['code' => $m['code'], 'short' => $m['short'], 'label' => $m['label'], 'icon' => $m['icon'],
            'children' => array_map(fn ($g) => ['label' => $g['label'], 'items' => $g['items']], $m['groups'])], self::modules());
    }

    public static function screen(string $slug): ?array
    {
        foreach (self::modules() as $m) {
            foreach ($m['groups'] as $g) {
                foreach ($g['items'] as $i) {
                    if (($i['slug'] ?? null) === $slug) {
                        return $i + ['module' => $m['label'], 'module_code' => $m['code'], 'group' => $g['label']];
                    }
                }
            }
        }

        return null;
    }
}
