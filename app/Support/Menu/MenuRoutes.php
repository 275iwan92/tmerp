<?php

namespace App\Support\Menu;

/* REMARK
 * Tujuan       : Peta code sys_app_menu -> URL layar v1 untuk item yang belum punya layar v2.
 * Sumber hulu  : sys_app_menu (tm2sys), rute v1 di routes/web.php.
 * Input        : -
 * Output       : array code => URL; HRM_SELFSERVICE dihapus 2026-10-18 (menu Employee Self-Service dihapus, jalur resmi Edit profile v2).
 * Dipakai oleh : pages/main-menu (v1), App\Support\V2\AppMenu.
 * Status       : Aktif; dikosongkan bertahap sampai semua layar v1 diganti v2.
 */

// code sys_app_menu -> URL layar v1 (Livewire tmerp). Dipakai menu v1 (pages/main-menu) DAN menu v2
// (App\Support\V2\AppMenu) untuk item yang belum punya layar v2 -- satu peta, jangan dibuat dua.
final class MenuRoutes
{
    public static function map(): array
    {
        return [
            'SALES_INQ' => route('inquiry.hub'),
            // Pro-Dev (2026-09-03, koreksi user, DIKOREKSI LAGI hari sama):
            // SALES_IMPO dulu nunjuk ke inquiry.imposition.list (Imposition
            // Engine lama, per-Inquiry) -- sempat dialihkan LANGSUNG ke layar
            // ProDev workbench (production.prodev) sesuai desain
            // productdevelopment_tier1.md §6 poin 2 "Imposition pindah dari
            // Inquiry ke Product Development". TERNYATA itu melanggar
            // griduistandard.md (grid dulu, baru klik-2x buka detail/tools/
            // engine) -- user menegaskan ulang butuh GRID depan (ringkasan
            // pekerjaan + status confirm per part) sebelum masuk workbench,
            // bukan langsung nyemplung form.
            // 2026-09-14 (instruksi user): ProDev diganti total ke layar baru
            // "Imposition Plano Optimizer" (port mockup_prodev_imposition.html)
            // -- SATU layar kerja, tanpa grid depan, jadi menu nunjuk langsung
            // ke production.prodev. Grid+workbench+Master Card LAMA diarsipkan
            // ke production.old-prodev* (route masih hidup untuk data lama,
            // tapi sengaja TIDAK ditaut dari menu mana pun). Product Costing
            // tetap module:COSTING (Sisi A rahasia, CLAUDE.md §4).
            // 2026-09-16: balik ke GRID depan (production.prodev.list) -- sempat
            // diarahkan langsung ke form 2026-09-14 dan user menegaskan grid
            // tidak boleh hilang; dari grid klik 2x baru masuk layar kerja.
            'SALES_IMPO' => route('production.prodev.list'),
            'SALES_COST' => route('inquiry.costing.list'),
            // Menu PREORDER (Inquiry & Quotation): layar v1 yang sama dibuka juga dari modul ini (migration
            // 2026_10_02_000008). Pro-Cost Instant = Mode #2 standalone, di v1 hanya lewat link di grid Pro-Cost.
            'PRE_COST_INSTANT' => route('inquiry.costing.instant.list'),
            // Meja per jenis dokumen (migration 2026_10_02_000021, rulebook A.20): hanya ada di v2.
            'PRE_INQ_NEW' => route('v2.screen', 'inquiry-list-new'),
            'PRE_INQ_REVISE' => route('v2.screen', 'inquiry-revise'),
            'PRE_INQ_RECALC' => route('v2.screen', 'inquiry-recalc'),
            'PRE_PRODEV_NEW' => route('v2.screen', 'prodev-new'),
            'PRE_PRODEV_REVISE' => route('v2.screen', 'prodev-revise'),
            'PRE_COST_REVISE' => route('v2.screen', 'sales-procost-revise'),
            'PRE_COST_RECALC' => route('v2.screen', 'sales-procost-recalc'),
            // Pintasan master: dipakai selama baris asalnya belum ber-target v2 (tm2sys); di ddtsys target v2 yang menang.
            'PRE_MD_PROD' => route('v2.screen', 'master-product'),
            'PRE_MD_MATCAT' => route('v2.screen', 'md-matcat'),
            // Halaman yang hanya ada di v2: menu v1 (top bar) membukanya apa adanya supaya barisnya bukan link mati.
            'SALES_MCARD' => route('v2.screen', 'master-card-collection'),
            'SALES_RPT_QUOOPEN' => route('v2.screen', 'quotation-outstanding'),
            'SALES_RPT_QUOFU' => route('v2.screen', 'quotation-followup'),
            // CRM (grup kecil di dalam Sales, 2026-10-02) + Complaint Review QA: hanya ada di v2.
            'CRM_CUST360' => route('v2.screen', 'crm-customer'),
            'CRM_ACTIVITY' => route('v2.screen', 'crm-activity'),
            'CRM_COMPLAINT' => route('v2.screen', 'crm-complaint'),
            'PROD_QC_COMPLAINT' => route('v2.screen', 'qc-complaint-review'),
            'PRE_F_SALES' => route('v2.flow', 'SALES'),
            'F-SALES' => route('v2.flow', 'SALES'),
            'F-COSTING' => route('v2.flow', 'COSTING'),
            'G-PRODUCT' => route('v2.guide', 'product-master'),
            // Nota TIDAK LAGI menu top-level -- dokumen per-inquiry, reachable lewat
            // tombol "🖨 Cetak Nota" di dalam Inquiry Product (inquiry/form.blade.php toolbar).
            // Quotation SEKARANG per-customer (redesain 2026-08-20, bukan lagi
            // per-inquiry) -> punya list sungguhan, tak perlu numpang Inquiry Hub.
            'SALES_QUOT' => route('quotation.list'),
            'SALES_SO' => route('co.list'),
            // Plan Delivery -> Customer Plan Delivery (CPDEL), dipindah ke grup
            // Sales Order (keputusan user 2026-09-03, lihat migration
            // rename_cpdel_and_regroup_delivery_menu).
            'SALES_CPDEL' => route('co.delivery-plan'),
            // Delivery Order/Surat Jalan (DO+SJ 1 tabel 2 cetakan) -- sebelumnya
            // modul "Dispatch" berdiri sendiri, digabung ke sini 2026-08-28 (lihat
            // migration move_dispatch_menu_into_sales_delivery). "Delivery Notes"
            // (SALES_DN, ditambah 2026-09-03) reuse grid yang sama -- lihat catatan
            // di migration rename_cpdel_and_regroup_delivery_menu.
            'SALES_DO' => route('dispatch.do.index'),
            'SALES_DN' => route('dispatch.do.index'),
            'SALES_WINRATE' => route('sales.win-rate'),
            'PROC_PR' => route('pur.pr.list'),
            'PROC_PR_APPR' => route('v2.screen', 'approval-pr'),
            'PROC_PO' => route('pur.po.list'),
            'PROC_PO_APPR' => route('v2.screen', 'approval-po'),
            'PROC_RCV' => route('pur.pv.list'),
            'PROC_PQ' => route('v2.screen', 'purchase-quotation'),
            'PROC_RET_APPR' => route('v2.screen', 'approval-prt'),
            'PROC_MON_PR' => route('v2.screen', 'pur-rpt-outstanding-pr'),
            'PROC_MON_PO' => route('v2.screen', 'pur-rpt-outstanding-po'),
            'PROC_MON_PV' => route('v2.screen', 'pur-rpt-outstanding-pv'),
            'MD_PROD' => route('v2.screen', 'master-product'),
            'MD_SUPCAT' => route('v2.screen', 'md-supplier-category'),
            // Material Category = pembentuk kode item: satu layar di kedua situs (v2, dropdown saling terkait).
            'MD_MATCAT' => route('v2.screen', 'md-matcat'),
            'MD_UOM' => route('v2.screen', 'md-uom'),
            // Konversi Satuan: di v1 panel di dalam Master Satuan; baris menunya sendiri hanya punya layar v2 (Grid Lookup).
            'MD_UOM_CONV' => route('v2.screen', 'md-uom-conversion'),
            'MD_PRODCAT' => route('v2.screen', 'md-product-category'),
            'SALES_DN_SUBCON' => route('v2.screen', 'subcon-delivery-note'),
            'FIN_AP_RECEIPT' => route('v2.screen', 'supplier-invoice-receipt'),
            // Shortcut "Master Data Terkait" di dalam modul WM (2026-08-31) --
            // reuse route MDATA yang sama persis (bukan duplikasi layar/data).
            // Laporan stok semua kelas (2026-10-02, hanya ada di v2): Stock by Location, Stock Card, Recapitulation, Summary,
            // Opname, Loss & Gain, Stock in Inspection. Di tm2sys (menu v1) satu grup "Stock Reports".
            'INV_WH_RPT_LOC' => route('v2.screen', 'inv-rpt-location'),
            'INV_WH_RPT_CARD' => route('v2.screen', 'inv-rpt-card'),
            'INV_WH_RPT_RECAP' => route('v2.screen', 'inv-rpt-recap'),
            'INV_WH_RPT_SUM' => route('v2.screen', 'inv-rpt-summary'),
            'INV_WH_RPT_OPN' => route('v2.screen', 'inv-rpt-opname'),
            'INV_WH_RPT_LG' => route('v2.screen', 'inv-rpt-loss-gain'),
            'INV_WH_RPT_QC' => route('v2.screen', 'inv-rpt-inspection'),
            'INV_REF_MATCAT' => route('v2.screen', 'md-matcat'),
            'INV_REF_PROD' => route('v2.screen', 'master-product'),
            // MD_CURRENCY ternyata belum pernah dipetakan sejak dibuat
            // 2026-08-30 (link mati) -- ditemukan & diperbaiki 2026-09-02
            // sekalian nambah MD_EXCHRATE (Master Kurs, baru).
            'MD_CURRENCY' => route('v2.screen', 'md-currency'),
            'MD_EXCHRATE' => route('v2.screen', 'md-exchange-rate'),
            'MD_HOLIDAY' => route('v2.screen', 'holiday-calendar'),
            // Dua item ini activate=0 di sys_app_menu (belum pernah tayang);
            // dipetakan ke ProDev baru supaya tidak jadi link mati kalau nanti
            // diaktifkan -- Master Card lama ada di production.old-prodev.print.
            'PROD_CARD' => route('production.prodev'),
            'PROD_LAYOUT' => route('production.prodev'),
            // Accounting Process Engine (mockup blueprint, 2026-09-19)
            'FIN_ENGINE' => route('v2.screen', 'acc-engine'),
            'FIN_DASH' => route('v2.screen', 'acc-dashboard'),
            'FIN_BANK' => route('v2.screen', 'acc-bank'),
            'ACC_GL' => route('v2.screen', 'acc-rpt-ledger'),
            'ACC_CLOSE' => route('v2.screen', 'acc-closing'),
            'ACC_TAX' => route('v2.screen', 'acc-tax'),
            'ACC_FS' => route('v2.screen', 'acc-statements'),
            'ACC_TB' => route('v2.screen', 'acc-rpt-trial-balance'),
            'ACC_BS' => route('v2.screen', 'acc-rpt-balance-sheet'),
            'ACC_PL' => route('v2.screen', 'acc-rpt-income-statement'),
            'ACC_CF' => route('v2.screen', 'acc-rpt-cash-flow'),
            // Laporan Manajemen (grup MGMT) -- MGMT_EXEC/SALES/PURCHASE sudah
            // ada di sys_app_menu sejak dibuat tapi TIDAK PERNAH dipetakan ke
            // sini (bug nyata, ditemukan 2026-09-13 saat user komplain "tidak
            // ada menu di panel kiri" -- menunya tampil tapi klik ke mana pun
            // tidak jalan/placeholder kosong). MGMT_ACCOUNTING/PRODUCTION/HR/
            // WAREHOUSE = 4 laporan baru (migration 2026_09_13_000004).
            'MGMT_EXEC' => route('report.executive'),
            'MGMT_SALES' => route('report.sales'),
            'MGMT_PURCHASE' => route('report.purchase'),
            'MGMT_ACCOUNTING' => route('report.accounting'),
            'MGMT_PRODUCTION' => route('report.production'),
            'MGMT_HR' => route('report.hr'),
            'MGMT_WAREHOUSE' => route('report.warehouse'),
            // 2026-08-22: Laporan Inventory dipecah 3 menu per kelas stok
            // (RM/FG/SFG) -- 1 route param'd, 6 menu code beda category.
            'INV_RAW_RM' => route('v2.screen', 'inv-rm-rpt-balance'),
            'INV_CARD_RM' => route('v2.screen', 'inv-rm-rpt-card'),
            'INV_SLOW_RM' => route('v2.screen', 'inv-rm-rpt-slow'),
            'INV_RAW_FG' => route('v2.screen', 'inv-fg-rpt-balance'),
            'INV_CARD_FG' => route('v2.screen', 'inv-fg-rpt-card'),
            'INV_SLOW_FG' => route('v2.screen', 'inv-fg-rpt-slow'),
            'INV_RAW_SFG' => route('v2.screen', 'inv-sfg-rpt-balance'),
            'INV_CARD_SFG' => route('v2.screen', 'inv-sfg-rpt-card'),
            'INV_SLOW_SFG' => route('v2.screen', 'inv-sfg-rpt-slow'),
            // Stock In (item pertama di tiap grup Input -- lihat migration
            // 2026_08_22_000007). Stock Out/Return/Adjustment (ITEM2-5) masih NOT_BUILT.
            // Stock Out + Return from Production diperluas ke SFG 2026-08-22
            // (barang setengah jadi yg sempat nunggu di gudang lanjut ke proses
            // berikutnya, dan bisa diretur balik kalau cacat/tak kepakai). FG
            // TETAP ditolak di keduanya -- barang jadi keluar cuma lewat
            // Delivery Order (Dispatch, belum dibangun), bukan Stock Out
            // generik (fg_out_bukan_milik_modul, dikonfirmasi user 2026-08-22).
            'INV_RM_ITEM5' => route('v2.screen', 'approval-stock'),
            'INV_FG_ITEM5' => route('v2.screen', 'approval-stock'),
            'INV_SFG_ITEM5' => route('v2.screen', 'approval-stock'),
            'PROD_WO_LIST' => route('production.wo.list'),
            'PROD_MPS' => route('v2.screen', 'production-scheduling'),
            // Production Output (F15) belum punya layar sendiri -- catat output
            // per task dari WO Detail (masuk lewat Worklist WO), lihat
            // wo-entry.blade.php "Catat Output". Menu ini cuma pintu masuk.
            'PROD_OUTPUT' => route('production.wo.list'),
            'PROD_PROCESS_RATE' => route('production.process-rate'),
            'PROD_MTS_FORECAST' => route('v2.screen', 'demand-forecast'),
            'PROD_SHIFT_ROSTER' => route('v2.screen', 'hrm-roster'),
            'HRM_ORG_STRUCTURE' => route('hrm.position'),
            'HRM_ORG_CHART' => route('hrm.org-chart'),
            'HRM_EMPLOYEE' => route('hrm.employee'),
            'HRM_ROSTER' => route('v2.screen', 'hrm-roster'),
            'HRM_CONTACT_GAP' => route('v2.screen', 'hrm-contact-gap'),
            'HRM_CLOCKIN' => route('v2.screen', 'hrm-clockin'),
            'HRM_ATTENDANCE_UPLOAD' => route('v2.screen', 'hrm-attendance-upload'),
            'HRM_PAYROLL_CALC' => route('hrm.payroll.batch-run'),
            'HRM_PAYROLL_PAYSLIP' => route('hrm.payroll.payslip-list'),
            'HRM_PAYROLL_BANKXFER' => route('hrm.payroll.bank-transfer'),
            'HRM_PAYROLL_CASHROSTER' => route('v2.screen', 'hrm-cash-roster'),
            'HRM_DISCIPLINE_REPORT' => route('v2.screen', 'hrm-discipline'),
            // Recruitment (L1-L9, L8 dibangun 2026-09-02 + Portal Candidate
            // simulasi) -- lihat docs/recruitment_state.yaml.
            'HRM_RCR_SYNCLOG' => route('v2.screen', 'hrm-rcr-sync-log'),
            // 2026-08-30: portal simulasi internal digantikan Portal Rekrutmen
            // Profesional publik (URL sama, di luar auth) -- iframe tab ini
            // sekarang menampilkan landing page publik itu, bukan wizard KTP-only lama.
            'HRM_RCR_PORTAL' => route('careers.landing'),
            'SET_CONFIG' => route('setup.identification'),
            'SET_LANG' => route('setup.language'),
            'SET_LOCALIZATION' => route('setup.localization'),
            'SET_CONSTANTS' => route('setup.constants'),
            'SET_DATA_ORIGIN' => route('setup.data-origin'),
            'SET_USER' => route('security.index', ['tab' => 'users']),
            'SET_GROUP' => route('security.index', ['tab' => 'groups']),
            'SET_PERM' => route('security.index', ['tab' => 'perms']),
            'SET_MIGRATION' => route('security.index', ['tab' => 'migration']),
            'SET_TICKETS' => route('setup.support-tickets'),
            // Menu Setup hanya punya layar v2 (Grid Lookup atas sys_app_menu).
            'SET_MENU' => route('v2.screen', 'menu-setup'),
            'SET_CONSTANT_CAT' => route('v2.screen', 'setup-constant-categories'),
        ];
    }
}
