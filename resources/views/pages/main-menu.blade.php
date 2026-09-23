<?php

use App\Models\Sys\SysAppMenu;
use App\Models\Sys\SysAppMenuColor;
use App\Support\Branding\Brand;
use App\Support\Localization\Translator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

// #[Title] PHP attribute WAJIB constant expression -- TIDAK BISA panggil
// Brand::name() (query DB) langsung di sini. SOLUSI 2026-09-02 (bukan lagi
// "di luar cakupan"): literal "TELASIH MAKMUR ERP" di string ini cuma
// PLACEHOLDER, diganti live jadi APP_NAME sungguhan oleh layouts::shell/
// guest/app (str_replace di <title>, lihat komentar di sana) -- jangan
// hapus/ubah literalnya sembarangan, string INI yang dicari-ganti.
new #[Layout('layouts::shell')] #[Title('Home — TELASIH MAKMUR ERP')] class extends Component
{
    use WithFileUploads;

    // Panel dropdown user (Notifikasi/Chat/AI/Edit profil/Ubah password/User
    // Configuration) dipindah ke App\Livewire\Shell\UserPanels 2026-09-18 --
    // dipakai bersama shell Laporan Manajemen, jangan dibuat ulang di sini.

    // Help & Support (tiket) DIPINDAH ke resources/views/pages/help/index.blade.php
    // 2026-08-30 (instruksi user: gabung ticketing + Help Center jadi 1 tempat) --
    // properti support_*/myTickets/submitSupportTicket/resetSupportForm yang
    // dulu ada di sini SUDAH DIHAPUS, jangan bikin lagi versi duplikat di sini.

    public function mount(): void
    {
        $user = Auth::user();

        // Direksi murni (bukan admin) tidak boleh menu operasional sama
        // sekali (keputusan user 2026-09-13) -- dicek di mount(), bukan cuma
        // disembunyikan di UI, supaya mengetik /menu langsung pun ditolak.
        if ($user?->isDireksiOnly()) {
            $this->redirect(route('report.executive'), navigate: false);

            return;
        }
    }

    // code sys_app_menu -> URL route Laravel nyata. Item yang tak disebut di
    // sini tetap tampil di menu (placeholder pane) — lihat resources/js/app-shell.js.
    protected function itemRoutes(): array
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

    // Identitas aplikasi (docs/configurationstandard.md) -- APP_NAME BUKAN
    // "TELASIH MAKMUR" hardcode lagi, dibaca dari tm2sys.sys_constant lewat
    // App\Support\Branding\Brand, diedit di layar Configuration.
    #[Computed]
    public function brandName(): string
    {
        return Brand::name();
    }

    #[Computed]
    public function brandTagline(): string
    {
        return Brand::tagline();
    }

    #[Computed]
    public function brandLogoUrl(): ?string
    {
        return Brand::logoUrl();
    }

    // Pohon menu (module->group->item) dari sys_app_menu, difilter izin lewat
    // SecUser::visibleAppMenuCodes() (-> sec_module). Dikirim ke Alpine sekali
    // via @js() — navigasi tab/sidebar sesudahnya murni client-side.
    #[Computed]
    public function menuTree(): array
    {
        $visible = Auth::user()->visibleAppMenuCodes();

        $modules = SysAppMenu::query()
            ->where('menu_type', 'module')
            ->where('activate', true)
            ->whereIn('code', $visible)
            ->orderBy('sort_order')
            ->get();

        $children = SysAppMenu::query()
            ->whereNotNull('parent_id')
            ->where('activate', true)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('parent_id');

        $routes = $this->itemRoutes();
        $statusColors = SysAppMenuColor::all()->keyBy('status_code');

        // module_status_system (globalrules.yaml): item bawa status_code sendiri
        // dari DB; module/group DERIVE dari anak-anaknya (worst-case menang) --
        // TIDAK disimpan sendiri, supaya "Sales" langsung kelihatan abu kalau ADA
        // satu submenu yang belum dibangun, tanpa perlu expand satu-satu.
        $deriveStatus = function (array $childStatuses): string {
            if (empty($childStatuses) || in_array('NOT_BUILT', $childStatuses, true)) {
                return 'NOT_BUILT';
            }

            return in_array('READY_FOR_QC', $childStatuses, true) ? 'READY_FOR_QC' : 'LOCKED';
        };

        $build = function (SysAppMenu $node) use (&$build, $children, $routes, $statusColors, $deriveStatus) {
            $builtChildren = ($children->get($node->id) ?? collect())->map($build)->values()->all();

            $statusCode = $node->menu_type === 'item'
                ? ($node->status_code ?: 'NOT_BUILT')
                : $deriveStatus(array_column($builtChildren, 'statusCode'));

            $color = $statusColors->get($statusCode);

            return [
                'id' => $node->id,
                'code' => $node->code,
                'label' => $node->label,
                'shortLabel' => $node->short_label,
                'tooltip' => $node->tooltip,
                'icon' => $this->iconFor($node),
                'menuType' => $node->menu_type,
                'objectType' => $node->object_type,
                'url' => $routes[$node->code] ?? null,
                'statusCode' => $statusCode,
                'statusColor' => $color->color_hex ?? '#9ca3af',
                'statusLabel' => $color->label ?? 'Belum Tersedia',
                'isClickable' => (bool) ($color->is_clickable ?? false),
                'children' => $builtChildren,
            ];
        };

        return $modules->map($build)->values()->all();
    }

    // Font Awesome: module pakai icon kolom (sudah FA, lihat migration
    // 2026_08_09_000003). Group tak butuh ikon (hanya label capsule di
    // sidebar). Item tanpa icon sendiri jatuh ke ikon per object_type,
    // mirror typeIcon() di docs/mockups/main_menu_v2.html.
    protected function iconFor(SysAppMenu $node): ?string
    {
        if ($node->menu_type === 'group') {
            return null;
        }

        return $node->icon ?: match ($node->object_type) {
            'grid' => 'fa-solid fa-table-list',
            'report' => 'fa-solid fa-chart-column',
            default => 'fa-solid fa-pen-to-square',
        };
    }

    // Daftar datar item (utk command palette & asisten AI preview).
    #[Computed]
    public function flatItems(): array
    {
        $flat = [];

        $walk = function (array $nodes, string $modLabel) use (&$walk, &$flat) {
            foreach ($nodes as $n) {
                $mod = $n['menuType'] === 'module' ? $n['label'] : $modLabel;

                if ($n['menuType'] === 'item') {
                    $flat[] = [
                        'code' => $n['code'],
                        'label' => $n['label'],
                        'icon' => $n['icon'],
                        'objectType' => $n['objectType'],
                        'url' => $n['url'],
                        'statusCode' => $n['statusCode'],
                        'statusColor' => $n['statusColor'],
                        'statusLabel' => $n['statusLabel'],
                        'isClickable' => $n['isClickable'],
                        'mod' => $mod,
                    ];
                }

                if (! empty($n['children'])) {
                    $walk($n['children'], $mod);
                }
            }
        };

        $walk($this->menuTree, '');

        return $flat;
    }

    // ================= TEMA (sys_theme, instruksi user 2026-08-31) =================
    // Light/dark BUKAN sumbu terpisah dari tema warna -- 1 baris sys_theme =
    // hue + mode sekaligus (mis. "Navy Gelap"), dipilih lewat sec_user.theme_code
    // yang sudah ada. theme-vars.blade.php baca ulang tiap full page load, jadi
    // ganti tema butuh reload (lihat pemanggil $wire.setTheme()/quickToggleTheme()
    // di Blade -- selalu diikuti location.reload()).
    // Matrix 4 mode (pastel/cerah/dark/hitam) x 3 corak (blue/green/red) --
    // instruksi user 2026-08-31 "matrix-kan dengan corak, taruh dalam tabel
    // supaya mudah diubah ke depannya". Data 100% dari sys_theme.family/hue
    // (migration 2026_08_31_000004) -- tambah baris baru di DB otomatis
    // muncul di sel yang sesuai, tak perlu ubah kode ini. Tema lama yang di
    // luar B/G/R (Slate/Indigo/Plum/Teal, family NULL) tampil terpisah di
    // grup "Klasik".
    #[Computed]
    public function themeMatrix(): array
    {
        $themes = \App\Models\Sys\SysTheme::where('activate', true)->orderBy('sort_order')->get();

        $families = ['pastel' => 'Pastel', 'cerah' => 'Cerah', 'dark' => 'Dark', 'hitam' => 'Hitam'];
        $hues = ['blue' => 'B', 'green' => 'G', 'red' => 'R'];

        $matrix = [];
        foreach ($families as $fKey => $fLabel) {
            $cells = [];
            foreach ($hues as $hKey => $hLabel) {
                $t = $themes->first(fn ($x) => $x->family === $fKey && $x->hue === $hKey);
                $cells[] = $t ? ['code' => $t->code, 'name' => $t->name, 'primary' => $t->primary_color] : null;
            }
            $matrix[] = ['label' => $fLabel, 'cells' => $cells];
        }

        $classic = $themes->whereNull('family')
            ->map(fn ($t) => ['code' => $t->code, 'name' => $t->name, 'primary' => $t->primary_color, 'mode' => $t->mode])
            ->values()->all();

        return ['families' => array_values($families), 'hues' => array_values($hues), 'matrix' => $matrix, 'classic' => $classic];
    }

    public function currentThemeCode(): string
    {
        return \App\Support\ActiveTheme::resolve()->code;
    }

    public function currentThemeMode(): string
    {
        return \App\Support\ActiveTheme::resolve()->mode ?? 'light';
    }

    public function setTheme(string $code): void
    {
        if (! \App\Models\Sys\SysTheme::where('code', $code)->where('activate', true)->exists()) {
            return;
        }

        Auth::user()->update(['theme_code' => $code]);
    }

    // Kerangka i18n (2026-09-07) -- mirror pola setTheme()/currentThemeCode()
    // di atas, cuma target kolomnya sec_user.lang_code. Lihat
    // App\Support\Localization\Translator utk cakupan & alasan baru shell
    // chrome yang ditarik (BELUM semua layar).
    public function currentLangCode(): string
    {
        return \App\Support\Localization\Translator::lang();
    }

    public function setLang(string $code): void
    {
        \App\Support\Localization\Translator::setLang($code);
    }

    // Flag emoji cuma UI sugar (bukan kolom DB, sys_languages tak punya kolom
    // flag) -- daftar bahasa AKTUAL tetap dari tabel (Settings > Language),
    // menambah baris baru otomatis nongol di sini dgn fallback ikon globe.
    #[Computed]
    public function availableLanguages()
    {
        $flags = ['id' => '🇮🇩', 'en' => '🇬🇧'];

        return \App\Models\Sys\SysLanguage::orderByDesc('is_default')->orderBy('code')->get()
            ->map(fn ($l) => ['code' => $l->code, 'name' => $l->name, 'flag' => $flags[$l->code] ?? null]);
    }

    public function quickToggleTheme(): void
    {
        $current = \App\Support\ActiveTheme::resolve();

        if (($current->mode ?? 'light') === 'dark') {
            $lightCode = str_ends_with($current->code, '_dark') ? substr($current->code, 0, -5) : null;
            $target = $lightCode && \App\Models\Sys\SysTheme::where('code', $lightCode)->where('activate', true)->exists()
                ? $lightCode
                : (\App\Models\Sys\SysTheme::where('is_default', true)->value('code') ?? 'navy');
        } else {
            $darkCode = $current->code.'_dark';
            $target = \App\Models\Sys\SysTheme::where('code', $darkCode)->where('activate', true)->exists()
                ? $darkCode
                : (\App\Models\Sys\SysTheme::where('mode', 'dark')->orderBy('sort_order')->value('code') ?? 'navy_dark');
        }

        $this->setTheme($target);
    }

    public function greetName(): string
    {
        $name = Auth::user()->name ?: 'Pengguna';

        return trim(strtok($name, ' '));
    }

    // Item FLAT dropdown avatar (Pesan & Info + Akun), sumbernya sys_user_menu
    // -- lihat App\Models\Sys\SysUserMenu docblock kenapa widget Theme/Bahasa
    // TIDAK ikut sini (tetap hardcode di bawah, terlalu bespoke).
    #[Computed]
    public function userMenuItems()
    {
        return $this->visibleUserMenuItems(['Pesan & Info', 'Akun'])->groupBy('section');
    }

    // Sub-item dalam grup collapsible "Configuration" (Aktivitas saya, User
    // Configuration) -- section SENDIRI (bukan ikut 'Akun') 2026-09-07 supaya
    // tetap dirender nested di dalam collapse, bukan ikut nongol di list flat
    // atas. Widget Tema/Bahasa TETAP hardcode (bukan baris label+klik biasa).
    #[Computed]
    public function configMenuItems()
    {
        return $this->visibleUserMenuItems(['Configuration']);
    }

    protected function visibleUserMenuItems(array $sections)
    {
        $user = Auth::user();

        return \App\Models\Sys\SysUserMenu::query()
            ->where('activate', 1)
            ->whereIn('section', $sections)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn ($item) => match ($item->visibility) {
                'employee_linked' => (bool) $user->employee_id,
                'non_employee_linked' => ! $user->employee_id,
                'setup_non_admin' => $user->canSetup() && ! $user->is_admin,
                default => true,
            });
    }

    public function logout()
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }

};
?>

<div class="shell"
     x-data="appShell(@js($this->menuTree), @js($this->flatItems), @js(Translator::t('shell_home', 'Home')))"
     :class="{ mini: mini, nosidebar: nosidebar, notopbar: notopbar }"
     @keydown.ctrl.k.window.prevent="paletteOpen ? closePalette() : openPalette()"
     @keydown.cmd.k.window.prevent="paletteOpen ? closePalette() : openPalette()"
     @keydown.escape.window="closePalette()"
     @click.outside="ddOpen = false">

    <aside>
        <div class="side-head">
            <button type="button" class="logo-home" @click="selectModule('HOME')" title="Kembali ke Home">
                <span class="logo-badge">
                    @if ($this->brandLogoUrl)
                        <img src="{{ $this->brandLogoUrl }}" alt="{{ $this->brandName }}" class="w-full h-full object-cover rounded-[9px]">
                    @endif
                </span>
                <span class="logo-txt"><b>{{ $this->brandName }}</b>@if ($this->brandTagline)<br><span>{{ $this->brandTagline }}</span>@endif</span>
            </button>
            <button type="button" class="side-collapse" @click="mini = !mini" title="Ciutkan sidebar (mini)">⊟</button>
        </div>
        <div class="mod-title">
            <template x-if="currentModule">
                <span class="ic"><i :class="currentModule.icon"></i></span>
            </template>
            <template x-if="!currentModule">
                <span class="ic"><i class="fa-solid fa-house"></i></span>
            </template>
            <span class="lbl" x-text="currentModule ? currentModule.label.toUpperCase() : 'HOME'"></span>
        </div>
        <div class="side-nav tm-scroll">
            <template x-if="activeMod === 'HOME'">
                <div class="side-hint">Modul <b>Home</b>. Pilih modul lain di atas untuk menampilkan submenunya di sini.</div>
            </template>
            <template x-for="grp in sidebarGroups" :key="grp.id">
                <div x-data="{ open: false }">
                    <template x-if="grp.menuType === 'group'">
                        <button type="button" class="side-cap-btn" @click="open = !open" :title="open ? 'Ciutkan' : 'Buka'">
                            <span class="side-cap" x-text="grp.label"></span>
                            <i class="fa-solid fa-chevron-down chev" :class="{ collapsed: !open }"></i>
                        </button>
                    </template>
                    <template x-for="it in (grp.menuType === 'group' ? grp.children : [grp])" :key="it.id">
                        <button type="button" class="leaf" x-show="grp.menuType !== 'group' || open"
                            :class="{ active: activeTabCode === it.code, 'not-built': !it.isClickable }" :title="it.statusLabel" @click="openTab(it.code)">
                            <span class="lic"><i :class="it.icon || 'fa-solid fa-file-lines'"></i></span>
                            <span class="lbl" x-text="it.label"></span>
                            <span class="status-dot" :style="{ background: it.statusColor }"></span>
                        </button>
                    </template>
                </div>
            </template>
        </div>
        @if (Auth::user()->is_admin)
            {{-- Footer "Settings"/"Security" -- 2026-09-07: dulu 2 array Alpine
            literal hardcode di sini (label/icon/urutan tulis manual di Blade,
            beda sumber dari sys_app_menu.label yang dibaca modul lain) --
            sekarang 100% data-driven dari sys_app_menu lewat setupGroups
            (code SETUP -> group SET_GRP_SETTINGS/SET_GRP_SECURITY -> leaf
            item, migration 2026_09_07_000004). Supaya menu ini gampang
            dikonfigurasi (ubah label/icon/urutan/tambah-hapus item) cukup
            edit baris tabel, TIDAK perlu sentuh Blade lagi -- sama seperti
            semua modul lain. Markup persis mirror blok `sidebarGroups` di
            atas. docs/sidebarmenustandard.md. is_admin only (SETUP module
            gate) -- Portal Karyawan tab Security (profil+password non-admin)
            TIDAK berubah/tidak kena gate ini. --}}
            <template x-for="grp in setupGroups" :key="grp.id">
                <div class="side-footer" x-data="{ open: false }">
                    <template x-if="grp.menuType === 'group'">
                        <button type="button" class="side-cap-btn" @click="open = !open" :title="open ? 'Ciutkan' : 'Buka'">
                            <span class="side-cap" x-text="grp.label"></span>
                            <i class="fa-solid fa-chevron-down chev" :class="{ collapsed: !open }"></i>
                        </button>
                    </template>
                    <template x-for="it in (grp.menuType === 'group' ? grp.children : [grp])" :key="it.id">
                        <button type="button" class="leaf" x-show="grp.menuType !== 'group' || open"
                            :class="{ active: activeTabCode === it.code, 'not-built': !it.isClickable }" :title="it.statusLabel" @click="openTab(it.code)">
                            <span class="lic"><i :class="it.icon || 'fa-solid fa-file-lines'"></i></span>
                            <span class="lbl" x-text="it.label"></span>
                            <span class="status-dot" :style="{ background: it.statusColor }"></span>
                        </button>
                    </template>
                </div>
            </template>
        @endif
    </aside>

    <div class="main">
        <header class="topbar">
            <button type="button" class="tb-btn" @click="nosidebar = !nosidebar" title="Sembunyikan sidebar">☰</button>
            <nav class="mega">
                {{--
                    SETUP sengaja tidak muncul di sini -- khusus admin, aksesnya
                    lewat menu profil ("Pengaturan (Setup)" di dropdown avatar).

                    MGMT (Laporan Manajemen) JUGA dikeluarkan dari daftar module
                    biasa (keluhan user 2026-09-13, screenshot: "kebodohan kamu,
                    buat Laporan Manajemen di Operational menu" -- MGMT bukan
                    modul operasional yg diklik-lalu-expand-groups seperti
                    Sales/Purchasing, itu APLIKASI TERPISAH dgn shell dashboard
                    sendiri (layouts::report, dark sidebar). Menaruhnya sejajar
                    modul lain di mega-nav lalu expand ke grup "Ringkasan" yg
                    isinya cuma 1 item terasa aneh & duplikat. Diganti 1 tombol
                    langsung di sebelah kanan (seperti tombol lain di topbar
                    ini), tampil HANYA kalau user punya akses modul MGMT.
                --}}
                <template x-for="m in menuTree.filter(x => x.code !== 'SETUP' && x.code !== 'MGMT')" :key="m.id">
                    <button type="button" class="mega-item" :class="{ active: activeMod === m.code }" :title="(m.tooltip || m.label) + (m.statusLabel ? ' — '+m.statusLabel : '')" @click="selectModule(m.code)">
                        <span class="mi"><i :class="m.icon"></i></span> <span x-text="m.shortLabel || m.label"></span>
                        <span class="status-dot" :style="{ background: m.statusColor }"></span>
                    </button>
                </template>
            </nav>
            <div class="tb-spacer"></div>
            @if (collect($this->menuTree)->contains('code', 'MGMT'))
                {{--
                    Pill switcher, meniru persis .rs-switch di sisi Laporan
                    Manajemen (keputusan user 2026-09-13, lihat app.css §mm-switch).
                    Kontainer pakai style INLINE (bukan class .mm-switch murni) --
                    gotcha build: rule CSS kontainer selector-2-class tanpa
                    elemen anak (".shell .mm-switch{...}") selalu hilang dari
                    hasil build @tailwindcss/vite, sudah dicoba ganti nama
                    class & lepas @apply, tetap hilang -- didiagnosis di app.css.
                --}}
                <div class="mm-switch" style="display:flex;align-items:center;border-radius:9px;padding:3px;gap:2px;flex-shrink:0;background:var(--side-hover-soft)">
                    <a class="active"><i class="fa-solid fa-grip"></i> Operasional</a>
                    <a href="{{ route('report.executive') }}"><i class="fa-solid fa-chart-line"></i> Laporan Manajemen</a>
                </div>
            @endif
            {{--
                Dirapikan (keluhan user 2026-09-14, dua putaran): pertama
                tombol Cari/Palette, Chat, Notifikasi, Toggle Tema dihapus,
                "Sembunyikan top bar" sempat dipertahankan (ikon diganti
                fa-angles-up). Putaran kedua: "hilangkan icon hide menu di
                menu operational" -- tombol itu DIHAPUS juga, topbar kanan
                sekarang cuma switch MGMT (kalau ada aksesnya) + avatar
                user. Fitur di baliknya (openPalette/chatOpen/notifOpen/
                quickToggleTheme/notopbar) TIDAK dihapus dari komponen --
                cuma tombol pemicunya yang dilepas dari topbar.
            --}}
            {{--
                Dropdown avatar diekstrak ke komponen reusable
                app/Livewire/Shell/UserMenu.php (instruksi user 2026-09-14:
                "user menu di management report dan di operational menu
                harus sama") -- SATU implementasi dipakai di sini DAN di
                layouts/report.blade.php, bukan 2 salinan yang bisa
                menyimpang. Alpine ddOpen/configOpen/openTabs/activeTabCode/
                openTab() yang dipakai di dalam komponen tetap datang dari
                x-data root .shell ini (TIDAK diubah) -- Alpine scope
                menembus batas komponen Livewire lewat DOM biasa, jadi
                perilaku persis sama seperti sebelum diekstrak.
            --}}
            <livewire:shell.user-menu />
        </header>
        <div id="topbar-restore"><button type="button" @click="notopbar = false">▾ {{ Translator::t('shell_show_topbar', 'Tampilkan top bar') }}</button></div>

        <div class="tabbar">
            <template x-for="t in openTabs" :key="t.code">
                <div class="tab" :class="{ active: activeTabCode === t.code }" @click="activate(t.code)">
                    <i :class="t.icon || 'fa-solid fa-file-lines'"></i>
                    <span class="tlbl" x-text="t.label"></span>
                    <button type="button" class="tx" @click.stop="closeTab(t.code)" title="{{ Translator::t('shell_tab_close_title', 'Tutup') }}">×</button>
                </div>
            </template>
        </div>

        <div class="content">
            <div class="pane" :class="{ active: activeTabCode === null }">
                <div class="home">
                    <div class="hero"></div>
                    <h1>{{ Translator::t('shell_home_greeting', 'Selamat datang,') }} {{ $this->greetName() }} 👋</h1>
                    <p>{!! Translator::t('shell_home_intro', 'Pilih modul di <b>mega menu</b> atas — submenunya muncul di sidebar. Setiap form/grid terbuka sebagai <b>tab</b> di frame ini dan tetap terbuka meski Anda berpindah modul.') !!}</p>
                    <div class="hint">{!! Translator::t('shell_home_hint', 'Ctrl+K cari cepat · <kbd>F1</kbd> bantuan · <kbd>⊟</kbd> mini sidebar · <kbd>☰</kbd> sembunyikan sidebar · <kbd>︿</kbd> sembunyikan top bar.') !!}</div>
                </div>
            </div>
            <template x-for="t in openTabs" :key="'pane-'+t.code">
                <div class="pane" :class="{ active: activeTabCode === t.code, framed: !!t.url }">
                    <template x-if="t.url"><iframe :src="t.url" :title="t.label" :data-tab-code="t.code" x-on:load="try { $el.dataset.home ??= $el.contentWindow.location.pathname + $el.contentWindow.location.search } catch (e) {}"></iframe></template>
                    <template x-if="!t.url"><div x-html="placeholderHtml(t)"></div></template>
                </div>
            </template>
        </div>
    </div>

    <livewire:shell.user-panels />

    {{-- Command palette --}}
    <div class="pal-ov" :class="{ open: paletteOpen }" @click="$event.target === $el && closePalette()">
        <div class="pal">
            <div class="pal-in"><span>🔎</span>
                <input type="text" x-ref="paletteInput" x-model="paletteQuery" placeholder="{{ Translator::t('shell_palette_placeholder', 'Ketik nama form, grid, atau laporan…') }}" autocomplete="off"
                       @keydown.down.prevent="paletteMove(1)" @keydown.up.prevent="paletteMove(-1)"
                       @keydown.enter="paletteChoose()" @keydown.escape="closePalette()">
            </div>
            <div class="pal-list">
                <template x-if="paletteResults.length === 0">
                    <div class="pal-empty">{{ Translator::t('shell_palette_no_match', 'Tak ada yang cocok.') }}</div>
                </template>
                <template x-for="(o, i) in paletteResults" :key="o.code">
                    <div class="pal-row" :class="{ sel: i === paletteSel }" @click="openTab(o.code); closePalette()">
                        <span class="lic"><i :class="o.icon || 'fa-solid fa-file-lines'"></i></span>
                        <div><div class="nm" x-text="o.label"></div><div class="mm" x-text="o.mod"></div></div>
                    </div>
                </template>
            </div>
            <div class="pal-foot"><span><kbd>↑</kbd><kbd>↓</kbd> {{ Translator::t('shell_palette_hint_select', 'pilih') }}</span><span><kbd>↵</kbd> {{ Translator::t('shell_palette_hint_open', 'buka') }}</span><span><kbd>esc</kbd> {{ Translator::t('shell_palette_hint_close', 'tutup') }}</span></div>
        </div>
    </div>

    <div x-text="toastMsg"
         style="position:fixed;left:50%;bottom:26px;transform:translateX(-50%);background:#2f3a5e;color:#fff;padding:10px 18px;border-radius:8px;font-size:13px;transition:opacity .25s;z-index:130;pointer-events:none"
         :style="{ opacity: toastShow ? 1 : 0 }"></div>
</div>
