<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Ikon untuk SETIAP item menu (user 2026-10-01: "perbanyak icon yang mudah dimengerti"). Layer Menu mode mini hanya
// menampilkan ikon, jadi tiap item dalam satu daftar harus punya ikon yang berbeda dan menggambarkan isinya.
// FILL = diisi hanya bila ikon masih kosong (ikon yang sudah dipilih tidak ditimpa). REPLACE = ikon umum yang
// dipakai berulang (grafik garis) diganti ikon khusus. Semua nama = Font Awesome 6 Free (solid).
return new class extends Migration
{
    protected $connection = 'tm2sys';

    private const FILL = [
        // Sales
        'SALES_TRX' => 'right-left', 'SALES_INQGRP' => 'comments-dollar', 'SALES_INQ' => 'box-open', 'SALES_IMPO' => 'pen-ruler',
        'SALES_COST' => 'calculator', 'SALES_QUOT' => 'file-signature', 'SALES_NOTA' => 'receipt', 'SALES_WINRATE' => 'trophy',
        'SALES_ORDGRP' => 'clipboard-list', 'SALES_SO' => 'file-contract', 'SALES_CPDEL' => 'calendar-days', 'SALES_CO_APPR' => 'stamp',
        'SALES_GRID_SO' => 'list', 'SALES_MON_ORD' => 'binoculars', 'SALES_RPT' => 'chart-column',
        'SALES_DLVGRP' => 'truck', 'SALES_DO' => 'truck-fast', 'SALES_POD' => 'signature',
        // Laporan Manajemen
        'MGMT_RINGKASAN' => 'gauge', 'MGMT_LAPORAN' => 'folder-open', 'MGMT_ACCOUNTING' => 'scale-balanced', 'MGMT_PRODUCTION' => 'industry',
        'MGMT_HR' => 'users', 'MGMT_WAREHOUSE' => 'warehouse',
        // Procurement
        'PROC_GRP_REQUISITION' => 'file-circle-plus', 'PROC_PR' => 'file-pen', 'PROC_PR_APPR' => 'stamp', 'PROC_MON_PR' => 'hourglass-half',
        'PROC_GRP_ORDER' => 'bag-shopping', 'PROC_PQ' => 'scale-unbalanced', 'PROC_PO' => 'file-invoice', 'PROC_PO_APPR' => 'stamp',
        'PROC_MON_PO' => 'hourglass-half', 'PROC_GRP_RECEIVE' => 'dolly', 'PROC_RCV' => 'truck-ramp-box', 'PROC_MON_PV' => 'hourglass-half',
        'PROC_GRP_RETURN' => 'rotate-left', 'PROC_RET' => 'reply', 'PROC_RET_APPR' => 'stamp',
        // Inventory
        'INV_TRX' => 'right-left', 'INV_IN' => 'arrow-right-to-bracket', 'INV_OUT' => 'arrow-right-from-bracket', 'INV_RTP' => 'arrow-rotate-left',
        'INV_ADJ2' => 'clipboard-check', 'INV_APRV' => 'stamp', 'INV_MOVE' => 'right-left', 'INV_ADJ' => 'clipboard-check',
        'INV_TRX_RM' => 'scroll', 'INV_TRX_FG' => 'box', 'INV_TRX_SFG' => 'layer-group',
        'INV_RM_ITEM1' => 'arrow-right-to-bracket', 'INV_RM_ITEM2' => 'arrow-right-from-bracket', 'INV_RM_ITEM3' => 'arrow-rotate-left',
        'INV_RM_ITEM4' => 'clipboard-check', 'INV_RM_ITEM5' => 'stamp',
        'INV_FG_ITEM1' => 'arrow-right-to-bracket', 'INV_FG_ITEM2' => 'arrow-right-from-bracket', 'INV_FG_ITEM3' => 'arrow-rotate-left',
        'INV_FG_ITEM4' => 'clipboard-check', 'INV_FG_ITEM5' => 'stamp',
        'INV_SFG_ITEM1' => 'arrow-right-to-bracket', 'INV_SFG_ITEM2' => 'arrow-right-from-bracket', 'INV_SFG_ITEM3' => 'arrow-rotate-left',
        'INV_SFG_ITEM4' => 'clipboard-check', 'INV_SFG_ITEM5' => 'stamp',
        'INV_LBL' => 'qrcode', 'INV_LBL_ITEM1' => 'pallet', 'INV_LBL_ITEM2' => 'tag', 'INV_LBL_ITEM3' => 'clock-rotate-left',
        'INV_SCAN' => 'barcode', 'INV_SCAN_ITEM1' => 'camera', 'INV_SCAN_ITEM2' => 'magnifying-glass-location',
        'INV_REF' => 'database', 'INV_REF_RAW' => 'scroll', 'INV_REF_MATCAT' => 'sitemap', 'INV_REF_PROD' => 'box-open', 'INV_REF_WH' => 'warehouse',
        'INV_RPT_RM' => 'chart-line', 'INV_RPT_FG' => 'chart-line', 'INV_RPT_SFG' => 'chart-line', 'INV_RPT' => 'chart-line',
        'INV_RAW' => 'list-check', 'INV_FG' => 'box', 'INV_CARD' => 'chart-column', 'INV_HIST' => 'clock-rotate-left', 'INV_PERIOD' => 'calendar',
        // Production
        'PROD_MASTER' => 'book', 'PROD_CARD' => 'id-card', 'PROD_LAYOUT' => 'table-cells', 'PROD_MST_PROCESS' => 'diagram-next',
        'PROD_ROUTING_TPL' => 'route', 'PROD_MST_BOP_RULE' => 'list-ol', 'MD_MACHINE' => 'gears', 'PROD_MST_CREW' => 'people-group',
        'PROD_PLAN' => 'calendar-check', 'PROD_WO_LIST' => 'clipboard-list', 'PROD_MPS' => 'chart-gantt', 'PROD_MON_WO' => 'table-columns',
        'PROD_MPS_SHEET' => 'table', 'PROD_RULE_LIB' => 'book-open', 'PROD_PROCESS_RATE' => 'gauge-high', 'PROD_MTS_FORECAST' => 'arrow-trend-up',
        'PROD_BOOKING' => 'bookmark', 'PROD_MRP' => 'cubes', 'PROD_SUPPORT' => 'life-ring', 'PROD_SHIFT_ROSTER' => 'user-clock',
        'PROD_MTN_SCH' => 'screwdriver-wrench', 'PROD_MTN_PREV' => 'calendar-check', 'PROD_OUT' => 'boxes-packing',
        'PROD_OUTPUT' => 'clipboard-check', 'PROD_SCRAP_TYPE' => 'trash-can',
        // Human Resources
        'HRM' => 'users', 'HRM_ORG' => 'sitemap', 'HRM_ORG_STRUCTURE' => 'id-badge', 'HRM_ORG_CHART' => 'diagram-project', 'HRM_SOP' => 'book-open',
        'HRM_MASTER' => 'user-group', 'HRM_EMPLOYEE' => 'address-card', 'HRM_CORRECTION' => 'user-pen', 'HRM_SELFSERVICE' => 'user-check',
        'HRM_CONTACT_GAP' => 'phone-slash', 'HRM_SCHEDULE' => 'clock', 'HRM_ROSTER' => 'calendar-week', 'HRM_CLOCKIN' => 'location-dot',
        'HRM_LEAVE_APPROVAL' => 'umbrella-beach', 'HRM_GPS_APPROVAL' => 'map-location-dot', 'HRM_OVERTIME' => 'business-time',
        'HRM_DISCIPLINE_REPORT' => 'user-clock', 'HRM_PAYROLL' => 'money-check-dollar', 'HRM_ATTENDANCE_UPLOAD' => 'file-arrow-up',
        'HRM_ATTENDANCE' => 'fingerprint', 'HRM_PAYROLL_CALC' => 'calculator', 'HRM_PAYROLL_PAYSLIP' => 'envelope-open-text',
        'HRM_PAYROLL_BANKXFER' => 'building-columns', 'HRM_PAYROLL_CASHROSTER' => 'money-bill-wave', 'HRM_PAYROLL_SALARY' => 'hand-holding-dollar',
        'HRM_PAYROLL_BANK' => 'credit-card', 'HRM_RECRUITMENT' => 'user-plus', 'HRM_RCR_REQUISITION' => 'file-circle-plus',
        'HRM_RCR_VACANCY' => 'bullhorn', 'HRM_RCR_TALENTPOOL' => 'users-viewfinder', 'HRM_RCR_REVIEW' => 'file-circle-check',
        'HRM_RCR_PIPELINE' => 'table-columns', 'HRM_RCR_INTERVIEW' => 'calendar-day', 'HRM_RCR_ASSESSMENT' => 'clipboard-question',
        'HRM_RCR_OFFER' => 'handshake', 'HRM_RCR_PROVISION' => 'user-gear', 'HRM_RCR_PORTAL' => 'globe', 'HRM_RCR_SYNCLOG' => 'rotate',
        // Finance & Accounting
        'ACC_CC' => 'sliders', 'FIN_ENGINE' => 'gears', 'FIN_DASH' => 'gauge-high', 'FIN' => 'money-bill-transfer', 'FIN_CASH' => 'cash-register',
        'FIN_BANK' => 'building-columns', 'FIN_DP' => 'hand-holding-dollar', 'FIN_AR' => 'file-invoice-dollar', 'FIN_AP' => 'money-check',
        'ACC' => 'book', 'ACC_COA' => 'list-ol', 'ACC_FIXEDASSET' => 'building', 'ACC_AMORT' => 'arrow-trend-down', 'ACC_JRN' => 'pen-to-square',
        'ACC_GL' => 'book-open', 'ACC_CTRL' => 'shield-halved', 'ACC_CLOSE' => 'lock', 'ACC_TAX' => 'percent', 'ACC_RPT' => 'chart-line',
        'ACC_FS' => 'file-lines', 'ACC_BS' => 'scale-balanced', 'ACC_PL' => 'chart-column', 'ACC_CF' => 'money-bill-trend-up', 'ACC_TB' => 'table-list',
        // Master Data
        'MD_CUST' => 'building-user', 'MD_CUST_RECV' => 'door-open', 'MD_DELAY_REASON' => 'hourglass-end', 'MD_SUPP' => 'truck-field',
        'MD_SUPCAT' => 'tags', 'MD_PROD' => 'box-open', 'MD_RAW' => 'scroll', 'MD_MATCAT' => 'sitemap', 'MD_USAGE_STD' => 'fill-drip',
        'MD_LEAD_TIME' => 'stopwatch', 'MD_UOM' => 'ruler', 'MD_WH' => 'warehouse', 'MD_SHIFT' => 'user-clock', 'MD_CURRENCY' => 'coins',
        'MD_EXCHRATE' => 'money-bill-transfer', 'MD_HOLIDAY' => 'calendar-xmark', 'MD_OFFICE_HOUR' => 'business-time',
        // Setup
        'SET_THEME' => 'palette', 'SET_MENU' => 'bars-staggered', 'SET_GRP_SETTINGS' => 'toolbox', 'SET_APPROVAL' => 'stamp',
        'SET_APPROVAL_PLT' => 'people-arrows', 'SET_DICTIONARY' => 'book-atlas', 'SET_DATA_ORIGIN' => 'flask', 'SET_GRP_SECURITY' => 'shield-halved',
    ];

    private const REPLACE = [
        'SALES_CO_RPT_SUMMARY' => ['chart-line', 'chart-pie'], 'SALES_CO_RPT_ORDER' => ['chart-line', 'table-list'],
        'SALES_CO_RPT_SHIP_DET' => ['chart-line', 'truck-ramp-box'], 'GENERAL_CO_SUMMARY' => ['chart-line', 'chart-pie'],
        'GENERAL_CO_ORDER' => ['chart-line', 'table-list'], 'GENERAL_CO_SHIP' => ['chart-line', 'truck-ramp-box'],
    ];

    public function up(): void
    {
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        foreach (self::FILL as $code => $icon) {
            $menu()->where('code', $code)->where(fn ($q) => $q->whereNull('icon')->orWhere('icon', ''))->update(['icon' => 'fa-solid fa-'.$icon]);
        }
        foreach (self::REPLACE as $code => [$old, $new]) {
            $menu()->where('code', $code)->where('icon', 'fa-solid fa-'.$old)->update(['icon' => 'fa-solid fa-'.$new]);
        }
    }

    public function down(): void
    {
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        foreach (self::FILL as $code => $icon) {
            $menu()->where('code', $code)->where('icon', 'fa-solid fa-'.$icon)->update(['icon' => null]);
        }
        foreach (self::REPLACE as $code => [$old, $new]) {
            $menu()->where('code', $code)->where('icon', 'fa-solid fa-'.$new)->update(['icon' => 'fa-solid fa-'.$old]);
        }
    }
};
