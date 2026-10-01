<?php

namespace App\Support\Menu;

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
            'SALES_PMKT' => route('lookup.product-market'),
            'SALES_COST' => route('inquiry.costing.list'),
            // Menu PREORDER (Inquiry & Quotation): layar v1 yang sama dibuka juga dari modul ini (migration
            // 2026_10_02_000008). Pro-Cost Instant = Mode #2 standalone, di v1 hanya lewat link di grid Pro-Cost.
            'PRE_COST_INSTANT' => route('inquiry.costing.instant.list'),
            // Pintasan master: dipakai selama baris asalnya belum ber-target v2 (tm2sys); di ddtsys target v2 yang menang.
            'PRE_MD_CUST' => route('master.customer'),
            'PRE_MD_PROD' => route('master.produk'),
            'PRE_MD_RAW' => route('master.raw-material.list'),
            'PRE_MD_MATCAT' => route('master.mat-category'),
            'PRE_MD_PROCESS' => route('production.master-process'),
            'PRE_MD_MACHINE' => route('master.machine'),
            // Halaman yang hanya ada di v2: menu v1 (top bar) membukanya apa adanya supaya barisnya bukan link mati.
            'SALES_MCARD' => route('v2.screen', 'master-card-collection'),
            'SALES_RPT_QUOOPEN' => route('v2.screen', 'quotation-outstanding'),
            'SALES_RPT_QUOFU' => route('v2.screen', 'quotation-followup'),
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
            'PROC_PR_APPR' => route('pur.pr.approval'),
            'PROC_PO' => route('pur.po.list'),
            'PROC_PO_APPR' => route('pur.po.approval'),
            'PROC_RCV' => route('pur.pv.list'),
            'PROC_PQ' => route('pur.pq.list'),
            'PROC_RET' => route('pur.prt.list'),
            'PROC_RET_APPR' => route('pur.prt.approval'),
            'PROC_MON_PR' => route('pur.report.outstanding-pr'),
            'PROC_MON_PO' => route('pur.report.outstanding-po'),
            'PROC_MON_PV' => route('pur.report.outstanding-pv'),
            'MD_PROD' => route('master.produk'),
            'MD_CUST' => route('master.customer'),
            'MD_SUPP' => route('master.supplier'),
            'MD_SUPCAT' => route('master.supplier-category'),
            'MD_RAW' => route('master.raw-material.list'),
            'MD_MATCAT' => route('master.mat-category'),
            'MD_WH' => route('master.warehouse.list'),
            'MD_UOM' => route('master.uom'),
            // Shortcut "Master Data Terkait" di dalam modul WM (2026-08-31) --
            // reuse route MDATA yang sama persis (bukan duplikasi layar/data).
            'INV_REF_RAW' => route('master.raw-material.list'),
            'INV_REF_MATCAT' => route('master.mat-category'),
            'INV_REF_PROD' => route('master.produk'),
            'INV_REF_WH' => route('master.warehouse.list'),
            'MD_MACHINE' => route('master.machine'),
            'MD_SHIFT' => route('master.shift'),
            // MD_CURRENCY ternyata belum pernah dipetakan sejak dibuat
            // 2026-08-30 (link mati) -- ditemukan & diperbaiki 2026-09-02
            // sekalian nambah MD_EXCHRATE (Master Kurs, baru).
            'MD_CURRENCY' => route('master.currency'),
            'MD_EXCHRATE' => route('master.exchange-rate'),
            'MD_HOLIDAY' => route('master.holiday'),
            'PROD_MST_PROCESS' => route('production.master-process'),
            'PROD_ROUTING_TPL' => route('production.routing-template'),
            'PROD_MST_BOP_RULE' => route('production.bop-rule'),
            'PROD_MST_CREW' => route('production.master-crew'),
            // Dua item ini activate=0 di sys_app_menu (belum pernah tayang);
            // dipetakan ke ProDev baru supaya tidak jadi link mati kalau nanti
            // diaktifkan -- Master Card lama ada di production.old-prodev.print.
            'PROD_CARD' => route('production.prodev'),
            'PROD_LAYOUT' => route('production.prodev'),
            'ACC_COA' => route('finance.coa'),
            'FIN_DP' => route('finance.dp-invoice'),
            'FIN_CASH' => route('finance.cash-bank'),
            // Accounting Process Engine (mockup blueprint, 2026-09-19)
            'FIN_ENGINE' => route('finance.engine'),
            'FIN_DASH' => route('finance.dashboard'),
            'FIN_AR' => route('finance.receivables'),
            'FIN_AP' => route('finance.payables'),
            'FIN_BANK' => route('finance.bank'),
            'ACC_JRN' => route('finance.journals'),
            'ACC_GL' => route('finance.ledger'),
            'ACC_FIXEDASSET' => route('finance.assets'),
            'ACC_AMORT' => route('finance.amortization'),
            'ACC_CLOSE' => route('finance.closing'),
            'ACC_TAX' => route('finance.tax'),
            'ACC_FS' => route('finance.statements'),
            'ACC_TB' => route('finance.trial-balance'),
            'ACC_BS' => route('finance.balance-sheet'),
            'ACC_PL' => route('finance.income-statement'),
            'ACC_CF' => route('finance.cash-flow'),
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
            'INV_RAW_RM' => route('inv.print.master-stock', ['category' => 'RM']),
            'INV_CARD_RM' => route('inv.print.stock-card', ['category' => 'RM']),
            'INV_SLOW_RM' => route('inv.print.slow-moving', ['category' => 'RM']),
            'INV_RAW_FG' => route('inv.print.master-stock', ['category' => 'FG']),
            'INV_CARD_FG' => route('inv.print.stock-card', ['category' => 'FG']),
            'INV_SLOW_FG' => route('inv.print.slow-moving', ['category' => 'FG']),
            'INV_RAW_SFG' => route('inv.print.master-stock', ['category' => 'SFG']),
            'INV_CARD_SFG' => route('inv.print.stock-card', ['category' => 'SFG']),
            'INV_SLOW_SFG' => route('inv.print.slow-moving', ['category' => 'SFG']),
            // Stock In (item pertama di tiap grup Input -- lihat migration
            // 2026_08_22_000007). Stock Out/Return/Adjustment (ITEM2-5) masih NOT_BUILT.
            'INV_RM_ITEM1' => route('inv.stock-in.index', ['category' => 'RM']),
            'INV_FG_ITEM1' => route('inv.stock-in.index', ['category' => 'FG']),
            'INV_SFG_ITEM1' => route('inv.stock-in.index', ['category' => 'SFG']),
            // Stock Out + Return from Production diperluas ke SFG 2026-08-22
            // (barang setengah jadi yg sempat nunggu di gudang lanjut ke proses
            // berikutnya, dan bisa diretur balik kalau cacat/tak kepakai). FG
            // TETAP ditolak di keduanya -- barang jadi keluar cuma lewat
            // Delivery Order (Dispatch, belum dibangun), bukan Stock Out
            // generik (fg_out_bukan_milik_modul, dikonfirmasi user 2026-08-22).
            'INV_RM_ITEM2' => route('inv.stock-out.index', ['category' => 'RM']),
            'INV_SFG_ITEM2' => route('inv.stock-out.index', ['category' => 'SFG']),
            'INV_RM_ITEM3' => route('inv.stock-return.index', ['category' => 'RM']),
            'INV_SFG_ITEM3' => route('inv.stock-return.index', ['category' => 'SFG']),
            'INV_RM_ITEM4' => route('inv.stock-adj.index', ['category' => 'RM']),
            'INV_FG_ITEM4' => route('inv.stock-adj.index', ['category' => 'FG']),
            'INV_SFG_ITEM4' => route('inv.stock-adj.index', ['category' => 'SFG']),
            'INV_RM_ITEM5' => route('inv.stock-adj.approval', ['category' => 'RM']),
            'INV_FG_ITEM5' => route('inv.stock-adj.approval', ['category' => 'FG']),
            'INV_SFG_ITEM5' => route('inv.stock-adj.approval', ['category' => 'SFG']),
            'PROD_MPS' => route('production.scheduling-board'),
            'PROD_WO_LIST' => route('production.wo.list'),
            // Production Output (F15) belum punya layar sendiri -- catat output
            // per task dari WO Detail (masuk lewat Worklist WO), lihat
            // wo-entry.blade.php "Catat Output". Menu ini cuma pintu masuk.
            'PROD_OUTPUT' => route('production.wo.list'),
            'PROD_SCRAP_TYPE' => route('production.scrap-type'),
            'PROD_PROCESS_RATE' => route('production.process-rate'),
            'PROD_MON_WO' => route('production.monitoring'),
            'PROD_MPS_SHEET' => route('production.mps-spreadsheet'),
            'PROD_RULE_LIB' => route('production.rule-library'),
            'PROD_MTS_FORECAST' => route('production.mts-forecast'),
            'PROD_SHIFT_ROSTER' => route('hrm.shift-roster'),
            'HRM_ORG_STRUCTURE' => route('hrm.position'),
            'HRM_ORG_CHART' => route('hrm.org-chart'),
            'HRM_SOP' => route('hrm.sop-documents'),
            'HRM_EMPLOYEE' => route('hrm.employee'),
            'HRM_ROSTER' => route('hrm.shift-roster'),
            'HRM_CORRECTION' => route('hrm.correction-requests'),
            'HRM_CONTACT_GAP' => route('hrm.employee-contact-gap'),
            'HRM_SELFSERVICE' => route('self-service.index'),
            'HRM_LEAVE_APPROVAL' => route('hrm.leave-requests'),
            'HRM_CLOCKIN' => route('self-service.clock-in'),
            'HRM_ATTENDANCE_UPLOAD' => route('hrm.attendance-upload'),
            'HRM_ATTENDANCE' => route('hrm.attendance-daily'),
            'HRM_GPS_APPROVAL' => route('hrm.gps-approval'),
            'HRM_PAYROLL_SALARY' => route('hrm.payroll.employee-salary'),
            'HRM_PAYROLL_BANK' => route('hrm.payroll.employee-bank'),
            'HRM_PAYROLL_CALC' => route('hrm.payroll.batch-run'),
            'HRM_PAYROLL_PAYSLIP' => route('hrm.payroll.payslip-list'),
            'HRM_PAYROLL_BANKXFER' => route('hrm.payroll.bank-transfer'),
            'HRM_PAYROLL_CASHROSTER' => route('hrm.payroll.cash-roster'),
            'HRM_DISCIPLINE_REPORT' => route('hrm.discipline-report'),
            // Recruitment (L1-L9, L8 dibangun 2026-09-02 + Portal Candidate
            // simulasi) -- lihat docs/recruitment_state.yaml.
            'HRM_RCR_REQUISITION' => route('hrm.recruitment.requisition.list'),
            'HRM_RCR_VACANCY' => route('hrm.recruitment.vacancy'),
            'HRM_RCR_TALENTPOOL' => route('hrm.recruitment.talent-pool'),
            'HRM_RCR_REVIEW' => route('hrm.recruitment.candidate-review.list'),
            'HRM_RCR_PIPELINE' => route('hrm.recruitment.pipeline-board'),
            'HRM_RCR_INTERVIEW' => route('hrm.recruitment.interview-schedule'),
            'HRM_RCR_ASSESSMENT' => route('hrm.recruitment.assessment-result'),
            'HRM_RCR_OFFER' => route('hrm.recruitment.offer.list'),
            'HRM_RCR_SYNCLOG' => route('hrm.recruitment.sync-log'),
            'HRM_RCR_PROVISION' => route('hrm.recruitment.hire-provision'),
            // 2026-08-30: portal simulasi internal digantikan Portal Rekrutmen
            // Profesional publik (URL sama, di luar auth) -- iframe tab ini
            // sekarang menampilkan landing page publik itu, bukan wizard KTP-only lama.
            'HRM_RCR_PORTAL' => route('careers.landing'),
            'SET_CONFIG' => route('setup.identification'),
            'SET_LANG' => route('setup.language'),
            'SET_LOCALIZATION' => route('setup.localization'),
            'SET_CONSTANTS' => route('setup.constants'),
            'SET_DATA_ORIGIN' => route('setup.data-origin'),
            'SET_UILIB' => route('setup.component-library'),
            'SET_USER' => route('security.index', ['tab' => 'users']),
            'SET_GROUP' => route('security.index', ['tab' => 'groups']),
            'SET_PERM' => route('security.index', ['tab' => 'perms']),
            'SET_MIGRATION' => route('security.index', ['tab' => 'migration']),
            'SET_TICKETS' => route('setup.support-tickets'),
        ];
    }
}
