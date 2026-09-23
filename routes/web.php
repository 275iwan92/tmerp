<?php

use App\Services\Portal\CandidateAuthService;
use App\Support\Branding\Brand;
use App\Support\Mockup\MockupCatalog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Situs tinjau mockup (mockuperp.test = link Herd ke aplikasi YANG SAMA, bukan
// app terpisah -- supaya library komponen tetap satu). Didaftarkan paling atas
// supaya '/' di domain ini tidak tertangkap redirect '/menu' di bawah.
Route::domain(config('app.mockup_host'))->middleware('auth')->group(function () {
    Route::livewire('/', 'pages::mockup.index')->name('mockup.index');
    Route::livewire('/lihat/{file}', 'pages::mockup.show')->where('file', '.*')->name('mockup.show');
    Route::get('/berkas/{file}', function (string $file) {
        $path = MockupCatalog::path($file);
        abort_unless($path, 404);

        return response()->file($path);
    })->where('file', '.*')->name('mockup.file');
});

Route::redirect('/', '/menu');

// PWA manifest -- dibuat dinamis (bukan public/manifest.json statis) supaya
// nama aplikasi ikut Brand::name() (tm2sys.sys_constant APP_NAME, bisa
// diedit lewat layar Configuration) tanpa perlu sinkron 2 tempat. Ikon
// masih placeholder statis (navy + "TM", lihat public/images/pwa-icon-*.png)
// -- BELUM auto-generate dari APP_LOGO_PATH kalau ada logo custom diupload,
// itu butuh resize/crop terpisah, dicatat sbg simplifikasi.
Route::get('/manifest.json', function () {
    return response()->json([
        'name' => Brand::name(),
        'short_name' => 'ERP TM',
        'start_url' => '/menu',
        'display' => 'standalone',
        'background_color' => '#2c3e69',
        'theme_color' => '#2c3e69',
        'icons' => [
            ['src' => asset('images/pwa-icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ['src' => asset('images/pwa-icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
        ],
    ])->header('Content-Type', 'application/manifest+json');
})->name('pwa.manifest');

// Portal Rekrutmen Profesional (publik, DI LUAR middleware 'auth' internal --
// akun kandidat TERPISAH dari sec_user, lihat App\Services\Portal\CandidateAuthService
// + docs/recruitmentportal_web_tier1.md). Alamat SENGAJA dipertahankan sama
// dengan portal simulasi lama (/hrm/recruitment/portal, instruksi user
// 2026-08-30) meski sekarang PUBLIK (bukan lagi di dalam grup 'auth'+'module:HRM'
// -- baris registrasi lama di grup itu sudah DIHAPUS, lihat git blame). Guard
// sesi sendiri 'portal.candidate' (BUKAN middleware 'auth' ERP internal).
Route::prefix('hrm/recruitment/portal')->name('careers.')->group(function () {
    Route::livewire('/', 'pages::careers.landing')->name('landing');
    Route::livewire('/lowongan/{vacancy_code}', 'pages::careers.vacancy-detail')->name('vacancy-detail');
    Route::livewire('/daftar', 'pages::careers.register')->name('register');
    Route::livewire('/masuk', 'pages::careers.login')->name('login');
    Route::get('/keluar', function () {
        session()->forget('portal_candidate_uid');
        session()->regenerate();

        return redirect()->route('careers.landing');
    })->name('logout');
    Route::get('/verifikasi/{uid}', function (string $uid) {
        if (! request()->hasValidSignature()) {
            abort(403, 'Tautan verifikasi tidak valid atau sudah kedaluwarsa.');
        }

        app(CandidateAuthService::class)->markEmailVerified($uid);

        return redirect()->route('careers.login')->with('verified', true);
    })->name('verify-email')->middleware('signed');

    Route::middleware('portal.candidate')->group(function () {
        Route::livewire('/lamar/{vacancy_code}', 'pages::careers.apply')->name('apply');
        Route::livewire('/lamaran-saya', 'pages::careers.dashboard')->name('dashboard');
    });
});

Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'pages::auth.login')->name('login');
    Route::livewire('/register', 'pages::auth.register')->name('register');
    Route::livewire('/forgot-password', 'pages::auth.forgot-password')->name('password.request');
});

Route::middleware(['auth', 'locale'])->group(function () {
    // Di luar force.password: halaman paksa-ganti-password sendiri, tak boleh
    // ikut digate olehnya (redirect loop) — security_defaultpassword_spec.md §6.
    Route::livewire('/password/change', 'pages::auth.password-change')->name('password.change');

    // Sama alasannya dgn password.change — halaman setup 4 pertanyaan keamanan
    // sendiri juga di luar force.password (redirect loop kalau ikut digate).
    Route::livewire('/security/setup', 'pages::auth.security-setup')->name('security.setup');

    // Route logout GLOBAL, di luar shell lama -- sebelumnya logout HANYA
    // method Livewire di dalam main-menu.blade.php (wire:click="logout"),
    // jadi TIDAK bisa dipanggil dari halaman lain (mis. Laporan Manajemen).
    // Ditemukan 2026-09-13 saat user tanya "gimana cara logout kalau report
    // managemen begitu" -- layout laporan yang baru butuh link logout sendiri.
    // Nama route 'logout' sebelumnya belum dipakai di level global (yang ada
    // cuma 'careers.logout', beda namespace, portal kandidat eksternal).
    Route::get('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');

    Route::middleware('force.password')->group(function () {
        Route::livewire('/menu', 'pages::main-menu')->name('menu');

        Route::middleware('module:SALES')->group(function () {
            Route::livewire('/inquiry', 'pages::inquiry.hub')->name('inquiry.hub');
            Route::livewire('/inquiry/new', 'pages::inquiry.form')->name('inquiry.form');
            Route::livewire('/inquiry/recalc', 'pages::inquiry.recalc')->name('inquiry.recalc');
            Route::livewire('/inquiry/revise', 'pages::inquiry.revise')->name('inquiry.revise');
            Route::livewire('/inquiry/imposition', 'pages::inquiry.imposition-list')->name('inquiry.imposition.list');
            Route::livewire('/inquiry/{inquiry}/imposition', 'pages::inquiry.imposition')->name('inquiry.imposition');
            Route::livewire('/inquiry/{inquiry}/nota', 'pages::inquiry.nota')->name('inquiry.nota');
            Route::livewire('/sales/win-rate', 'pages::sales.win-rate')->name('sales.win-rate');

            Route::livewire('/quotation', 'pages::quotation.list')->name('quotation.list');
            Route::livewire('/quotation/{quotation}/edit', 'pages::quotation.entry')->name('quotation.entry');
            Route::livewire('/quotation/{quotation}/print', 'pages::quotation.print')->name('quotation.print');

            Route::livewire('/co', 'pages::co.list')->name('co.list');
            Route::livewire('/co/{order}/edit', 'pages::co.entry')->name('co.entry');
            Route::livewire('/co/{order}/print', 'pages::co.print')->name('co.print');
            Route::livewire('/co/delivery-plan', 'pages::co.delivery-plan')->name('co.delivery-plan');

            // Delivery (DO/Surat Jalan) -- sebelumnya modul "Dispatch" berdiri sendiri,
            // digabung ke Sales > grup "Delivery" (menu SALES_DO) 2026-08-28 atas
            // permintaan user (kepanjangan Delivery Order/Delivery Notes, bukan
            // "Dispatch"). {doId} wajib di sini (beda dari spec teks asli "{doId?}")
            // krn DoList SELALU pre-create header via picker customer sebelum redirect
            // ke Entry (lihat catatan deviasi di dispatch/do/list.blade.php) -- tak
            // pernah ada Entry tanpa doId.
            Route::livewire('/dispatch/do', 'pages::dispatch.do.list')->name('dispatch.do.index');
            Route::livewire('/dispatch/do/{doId}', 'pages::dispatch.do.entry')->name('dispatch.do.entry');
            Route::livewire('/dispatch/do/{doId}/print', 'pages::dispatch.do.print')->name('dispatch.do.print');
            Route::livewire('/dispatch/do/{doId}/print-surat-jalan', 'pages::dispatch.do.print-surat-jalan')->name('dispatch.do.print-surat-jalan');
        });

        Route::middleware('module:COSTING')->group(function () {
            Route::livewire('/inquiry/costing', 'pages::inquiry.costing-list')->name('inquiry.costing.list');
            Route::livewire('/inquiry/{inquiry}/costing', 'pages::inquiry.costing')->name('inquiry.costing');
            // Mode Instant (standalone, keputusan user 2026-09-18): grid & form
            // sendiri. Form meniru program HPP lama + mockup Kalkulasi_HPP_UAT.html
            // (tata letak beda total dari Integrated). Rumus tetap 1 sumber:
            // App\Services\Costing\CostingEngine. iqp_costing.mode=INSTANT.
            Route::livewire('/costing/instant', 'pages::inquiry.costing-instant-list')->name('inquiry.costing.instant.list');
            Route::livewire('/costing/instant/{costing}', 'pages::inquiry.costing-instant')->name('inquiry.costing.instant');
        });

        Route::livewire('/security', 'pages::security.index')->name('security.index')->middleware('admin');
        Route::livewire('/setup/identification', 'pages::setup.identification')->name('setup.identification')->middleware('admin');
        Route::livewire('/setup/language', 'pages::setup.language')->name('setup.language')->middleware('admin');
        Route::livewire('/setup/localization', 'pages::setup.localization')->name('setup.localization')->middleware('admin');
        Route::livewire('/setup/constants', 'pages::setup.constants')->name('setup.constants')->middleware('admin');
        Route::livewire('/setup/data-origin', 'pages::setup.data-origin')->name('setup.data-origin')->middleware('admin');
        Route::livewire('/setup/component-library', 'pages::setup.component-library')->name('setup.component-library')->middleware('admin');
        Route::livewire('/setup/support-tickets', 'pages::setup.support-tickets')->name('setup.support-tickets')->middleware('admin');

        // Help Center -- panduan pakai aplikasi (bukan tiket support, itu modal
        // "Help & Support" terpisah di profile dropdown). SEMUA user login boleh
        // akses (tanpa middleware 'admin' seperti /security di atas).
        Route::livewire('/bantuan', 'pages::help.index')->name('help.index');

        // Portal self-service karyawan — bukan modul HR administratif (tanpa
        // module: gate), tapi personal & hanya milik akun sendiri. Guard
        // "linked employee" ditangani di dalam komponen (masteremployee_tier1.md §5.1).
        Route::livewire('/self-service', 'pages::self-service.index')->name('self-service.index');
        Route::livewire('/self-service/clock-in', 'pages::self-service.clock-in')->name('self-service.clock-in');

        // Personal, bukan modul administratif -- sama pola dgn self-service (tanpa module: gate).
        Route::livewire('/notifications', 'pages::notification.index')->name('notifications.index');

        Route::middleware('module:MDATA')->group(function () {
            Route::livewire('/master/produk', 'pages::master.produk')->name('master.produk');
            Route::livewire('/master/customer', 'pages::master.customer')->name('master.customer');
            Route::livewire('/master/supplier', 'pages::master.supplier')->name('master.supplier');
            Route::livewire('/master/supplier-category', 'pages::master.supplier-category')->name('master.supplier-category');
            Route::livewire('/master/uom', 'pages::master.uom')->name('master.uom');
            Route::livewire('/master/currency', 'pages::master.currency')->name('master.currency');
            Route::livewire('/master/exchange-rate', 'pages::master.exchange-rate')->name('master.exchange-rate');
            Route::livewire('/master/holiday', 'pages::master.holiday')->name('master.holiday');
            Route::livewire('/master/machine', 'pages::master.machine')->name('master.machine');
            Route::livewire('/master/shift', 'pages::master.shift')->name('master.shift');

            Route::livewire('/master/raw-material', 'pages::master.raw-material.index')->name('master.raw-material.list');
            Route::livewire('/master/raw-material/create', 'pages::master.raw-material.entry')->name('master.raw-material.create');
            Route::livewire('/master/raw-material/{material}/edit', 'pages::master.raw-material.entry')->name('master.raw-material.edit');

            Route::livewire('/master/mat-category', 'pages::master.mat-category')->name('master.mat-category');

            Route::livewire('/master/warehouse', 'pages::master.warehouse.index')->name('master.warehouse.list');
            Route::livewire('/master/warehouse/create', 'pages::master.warehouse.entry')->name('master.warehouse.create');
            Route::livewire('/master/warehouse/{warehouse}/edit', 'pages::master.warehouse.entry')->name('master.warehouse.edit');
            Route::livewire('/master/warehouse/{warehouse}/print-labels', 'pages::master.warehouse.print-labels')->name('master.warehouse.print-labels');
        });

        Route::middleware('module:FINACC')->group(function () {
            Route::livewire('/finance/coa', 'pages::finance.coa')->name('finance.coa');
            Route::livewire('/finance/dp-invoice', 'pages::finance.dp-invoice')->name('finance.dp-invoice');
            Route::livewire('/finance/cash-bank', 'pages::finance.cash-bank')->name('finance.cash-bank');

            // Accounting Process Engine (mockup MODULE_accounting-*-blueprint.html, 2026-09-19)
            Route::livewire('/finance/engine', 'pages::finance.engine')->name('finance.engine');
            Route::livewire('/finance/dashboard', 'pages::finance.dashboard')->name('finance.dashboard');
            Route::livewire('/finance/receivables', 'pages::finance.receivables')->name('finance.receivables');
            Route::livewire('/finance/payables', 'pages::finance.payables')->name('finance.payables');
            Route::livewire('/finance/bank', 'pages::finance.bank')->name('finance.bank');
            Route::livewire('/finance/journals', 'pages::finance.journals')->name('finance.journals');
            Route::livewire('/finance/ledger', 'pages::finance.ledger')->name('finance.ledger');
            Route::livewire('/finance/assets', 'pages::finance.assets')->name('finance.assets');
            Route::livewire('/finance/amortization', 'pages::finance.amortization')->name('finance.amortization');
            Route::livewire('/finance/closing', 'pages::finance.closing')->name('finance.closing');
            Route::livewire('/finance/tax', 'pages::finance.tax')->name('finance.tax');
            Route::livewire('/finance/statements', 'pages::finance.statements')->name('finance.statements');
            Route::livewire('/finance/trial-balance', 'pages::finance.trial-balance')->name('finance.trial-balance');
            Route::livewire('/finance/balance-sheet', 'pages::finance.balance-sheet')->name('finance.balance-sheet');
            Route::livewire('/finance/income-statement', 'pages::finance.income-statement')->name('finance.income-statement');
            Route::livewire('/finance/cash-flow', 'pages::finance.cash-flow')->name('finance.cash-flow');
        });

        Route::middleware('module:PROC')->group(function () {
            Route::livewire('/purchasing/pr', 'pages::pur.pr.index')->name('pur.pr.list');
            Route::livewire('/purchasing/pr/{pr}/edit', 'pages::pur.pr.entry')->name('pur.pr.entry');
            Route::livewire('/purchasing/pr/{pr}/print', 'pages::pur.pr.print')->name('pur.pr.print');
            Route::livewire('/purchasing/pr-approval', 'pages::pur.pr.approval')->name('pur.pr.approval');
            Route::livewire('/purchasing/po', 'pages::pur.po.index')->name('pur.po.list');
            Route::livewire('/purchasing/po/{po}/edit', 'pages::pur.po.entry')->name('pur.po.entry');
            Route::livewire('/purchasing/po/{po}/print', 'pages::pur.po.print')->name('pur.po.print');
            Route::livewire('/purchasing/po-approval', 'pages::pur.po.approval')->name('pur.po.approval');
            Route::livewire('/purchasing/pv', 'pages::pur.pv.index')->name('pur.pv.list');
            Route::livewire('/purchasing/pv/{pv}/edit', 'pages::pur.pv.entry')->name('pur.pv.entry');
            Route::livewire('/purchasing/pq', 'pages::pur.pq.index')->name('pur.pq.list');
            Route::livewire('/purchasing/pq/{pq}/edit', 'pages::pur.pq.entry')->name('pur.pq.entry');
            Route::livewire('/purchasing/pq/{pq}/print', 'pages::pur.pq.print')->name('pur.pq.print');
            Route::livewire('/purchasing/prt', 'pages::pur.prt.index')->name('pur.prt.list');
            Route::livewire('/purchasing/prt/{prt}/edit', 'pages::pur.prt.entry')->name('pur.prt.entry');
            Route::livewire('/purchasing/prt-approval', 'pages::pur.prt.approval')->name('pur.prt.approval');
            Route::livewire('/purchasing/report/outstanding-pr', 'pages::pur.report.outstanding-pr')->name('pur.report.outstanding-pr');
            Route::livewire('/purchasing/report/outstanding-po', 'pages::pur.report.outstanding-po')->name('pur.report.outstanding-po');
            Route::livewire('/purchasing/report/outstanding-pv', 'pages::pur.report.outstanding-pv')->name('pur.report.outstanding-pv');
        });

        Route::middleware('module:INV')->group(function () {
            // {category} = RM|FG|SFG, wajib di URL -- menu sidebar sekarang
            // 3 entri terpisah per kelas (bukan toggle dalam halaman), lihat
            // sidebarmenustandard.md §5. {material?} tetap opsional utk deep-link.
            Route::livewire('/inventory/print/stock-card/{category}/{material?}', 'pages::inventory.print.stock-card')->name('inv.print.stock-card');
            Route::livewire('/inventory/print/master-stock/{category}', 'pages::inventory.print.master-stock')->name('inv.print.master-stock');
            Route::livewire('/inventory/print/slow-moving/{category}', 'pages::inventory.print.slow-moving')->name('inv.print.slow-moving');

            // inventory_tier4.md #1.1 -- {category} = RM|FG|SFG, pola sama seperti Laporan.
            Route::livewire('/inventory/stock-in/{category}', 'pages::inventory.stock-in')->name('inv.stock-in.index');
            // Stock Out & Return v1 dikunci RM saja (inventory_rules.yaml out_wajib_wo /
            // fg_out_bukan_milik_modul) -- {category} tetap di URL, ditolak di
            // level route (mount()) kalau bukan RM, bukan cuma disembunyikan di UI.
            Route::livewire('/inventory/stock-out/{category}', 'pages::inventory.stock-out')->name('inv.stock-out.index');
            Route::livewire('/inventory/stock-return/{category}', 'pages::inventory.stock-return')->name('inv.stock-return.index');
            // Adjustment & Opname mencakup RM/FG/SFG (inventory_tier4.md #4) -- entri
            // & approval route TERPISAH (inventory_rules.yaml layar_terpisah).
            // {doc?} opsional -- dipakai layar Persetujuan (approval) utk deep-link
            // langsung ke form dokumen tertentu (grid<->form tetap 1 komponen).
            Route::livewire('/inventory/stock-adjustment/{category}/{doc?}', 'pages::inventory.stock-adjustment')->name('inv.stock-adj.index');
            Route::livewire('/inventory/stock-adjustment-approval/{category}', 'pages::inventory.stock-adjustment-approval')->name('inv.stock-adj.approval');
        });

        Route::middleware('module:HRM')->group(function () {
            Route::livewire('/hrm/position', 'pages::hrm.position')->name('hrm.position');
            Route::livewire('/hrm/sop', 'pages::hrm.sop-documents')->name('hrm.sop-documents');
            Route::livewire('/hrm/org-chart', 'pages::hrm.org-chart')->name('hrm.org-chart');
            Route::livewire('/hrm/employee', 'pages::master.employee')->name('hrm.employee');
            Route::livewire('/hrm/shift-roster', 'pages::hrm.shift-roster')->name('hrm.shift-roster');
            Route::livewire('/hrm/shift-roster/print', 'pages::hrm.shift-roster-print')->name('hrm.shift-roster.print');
            Route::livewire('/hrm/employee-contact-gap', 'pages::hrm.employee-contact-gap')->name('hrm.employee-contact-gap');
            Route::livewire('/hrm/correction-requests', 'pages::hrm.correction-requests')->name('hrm.correction-requests');
            Route::livewire('/hrm/leave-requests', 'pages::hrm.leave-requests')->name('hrm.leave-requests');
            Route::livewire('/hrm/attendance-upload', 'pages::hrm.attendance-upload')->name('hrm.attendance-upload');
            Route::livewire('/hrm/attendance-daily', 'pages::hrm.attendance-daily')->name('hrm.attendance-daily');
            Route::livewire('/hrm/payroll/employee-salary', 'pages::hrm.payroll-employee-salary')->name('hrm.payroll.employee-salary');
            Route::livewire('/hrm/payroll/employee-bank', 'pages::hrm.payroll-employee-bank')->name('hrm.payroll.employee-bank');
            Route::livewire('/hrm/gps-approval', 'pages::hrm.gps-approval')->name('hrm.gps-approval');
            Route::livewire('/hrm/payroll/batch-run', 'pages::hrm.payroll-batch-run')->name('hrm.payroll.batch-run');
            Route::livewire('/hrm/payroll/payslip', 'pages::hrm.payroll-payslip-list')->name('hrm.payroll.payslip-list');
            Route::livewire('/hrm/payroll/payslip/print-all/{run}', 'pages::hrm.payroll-payslip-print-all')->name('hrm.payroll.payslip-print-all')->where('run', '[0-9]+');
            Route::livewire('/hrm/payroll/payslip/{payslip}/print', 'pages::hrm.payroll-payslip-print')->name('hrm.payroll.payslip-print')->where('payslip', '[0-9]+');
            Route::livewire('/hrm/payroll/bank-transfer', 'pages::hrm.payroll-bank-transfer')->name('hrm.payroll.bank-transfer');
            Route::livewire('/hrm/payroll/cash-roster', 'pages::hrm.payroll-cash-roster')->name('hrm.payroll.cash-roster');
            Route::livewire('/hrm/discipline-report', 'pages::hrm.discipline-report')->name('hrm.discipline-report');

            // Recruitment (L1-L9 minus L8, lihat docs/recruitment_state.yaml) --
            // portal simulasi (Portal Candidate) sengaja di bawah middleware
            // HRM yang sama, ditaruh sementara di grup menu Recruitment.
            // CATATAN 2026-08-30: route 'hrm.recruitment.portal' (portal
            // SIMULASI di dalam shell admin, butuh login internal) SUDAH
            // DIHAPUS dari sini -- diganti Portal Rekrutmen Profesional publik
            // di URL yang SAMA (/hrm/recruitment/portal, lihat grup route
            // 'careers.*' di atas file ini, DI LUAR middleware auth). File
            // Livewire lama (pages::recruitment.portal) masih ada di repo
            // (tak dihapus, cuma tak lagi ada route yang menunjuknya) --
            // lihat docs/recruitmentportal_web_tier1.md kenapa dipertahankan.
            Route::livewire('/hrm/recruitment/requisition', 'pages::recruitment.requisition.list')->name('hrm.recruitment.requisition.list');
            Route::livewire('/hrm/recruitment/requisition/{requisition}', 'pages::recruitment.requisition.entry')->name('hrm.recruitment.requisition.entry')->where('requisition', '[0-9]+');
            Route::livewire('/hrm/recruitment/vacancy', 'pages::recruitment.vacancy')->name('hrm.recruitment.vacancy');
            Route::livewire('/hrm/recruitment/talent-pool', 'pages::recruitment.talent-pool')->name('hrm.recruitment.talent-pool');
            Route::livewire('/hrm/recruitment/candidate-review', 'pages::recruitment.candidate-review.list')->name('hrm.recruitment.candidate-review.list');
            Route::livewire('/hrm/recruitment/candidate-review/{application}', 'pages::recruitment.candidate-review.detail')->name('hrm.recruitment.candidate-review.detail')->where('application', '[0-9]+');
            Route::livewire('/hrm/recruitment/pipeline-board', 'pages::recruitment.pipeline-board')->name('hrm.recruitment.pipeline-board');
            Route::livewire('/hrm/recruitment/interview-schedule', 'pages::recruitment.interview-schedule')->name('hrm.recruitment.interview-schedule');
            Route::livewire('/hrm/recruitment/assessment-result', 'pages::recruitment.assessment-result')->name('hrm.recruitment.assessment-result');
            Route::livewire('/hrm/recruitment/offer', 'pages::recruitment.offer.list')->name('hrm.recruitment.offer.list');
            Route::livewire('/hrm/recruitment/offer/{application}', 'pages::recruitment.offer.entry')->name('hrm.recruitment.offer.entry')->where('application', '[0-9]+');
            Route::livewire('/hrm/recruitment/sync-log', 'pages::recruitment.sync-log')->name('hrm.recruitment.sync-log');
            Route::livewire('/hrm/recruitment/hire-provision', 'pages::recruitment.hire-provision')->name('hrm.recruitment.hire-provision');
        });

        Route::middleware('module:PROD')->group(function () {
            Route::livewire('/production/scheduling-board', 'pages::production.scheduling-board')->name('production.scheduling-board');
            Route::livewire('/production/monitoring', 'pages::production.monitoring-dashboard')->name('production.monitoring');
            Route::livewire('/production/mps-spreadsheet', 'pages::production.mps-spreadsheet')->name('production.mps-spreadsheet');
            Route::livewire('/production/wo', 'pages::production.wo-list')->name('production.wo.list');
            Route::livewire('/production/wo/new', 'pages::production.wo-entry')->name('production.wo.new');
            Route::livewire('/production/wo/{wo}/print', 'pages::production.wo-print')->name('production.wo.print')->where('wo', '[0-9]+');
            Route::livewire('/production/wo/{wo}', 'pages::production.wo-entry')->name('production.wo.entry')->where('wo', '[0-9]+');
            Route::livewire('/production/rule-library', 'pages::production.rule-library')->name('production.rule-library');
            Route::livewire('/production/mts-forecast', 'pages::production.mts-forecast')->name('production.mts-forecast');
            Route::livewire('/production/scrap-type', 'pages::production.scrap-type')->name('production.scrap-type');
            Route::livewire('/production/process-rate', 'pages::production.process-rate')->name('production.process-rate');
            Route::livewire('/production/master-process', 'pages::production.master-process')->name('production.master-process');
            Route::livewire('/production/routing-template', 'pages::production.routing-template')->name('production.routing-template');
            Route::livewire('/production/bop-rule', 'pages::production.bop-rule')->name('production.bop-rule');
            Route::livewire('/production/master-crew', 'pages::production.master-crew')->name('production.master-crew');
            // ProDev BARU (2026-09-14) -- Imposition Plano Optimizer, port dari
            // docs/mockups/mockup_prodev_imposition.html. Ini yang dipasang di
            // menu (SALES_IMPO). Layar ProDev LAMA (workbench 3-kolom +
            // grid + Master Card print) diarsipkan jadi old_prodev* di bawah:
            // route-nya SENGAJA dipertahankan supaya data/alur lama tidak
            // putus (ProCost Mode #1 masih baca prod_layout_group yang cuma
            // bisa dibuat di sana), tapi TIDAK muncul di menu mana pun.
            // Grid depan dulu (griduistandard.md), klik 2x baru buka layar kerja.
            Route::livewire('/production/prodev-list', 'pages::production.prodev-list')->name('production.prodev.list');
            Route::livewire('/production/prodev/{part?}', 'pages::production.prodev')->name('production.prodev')->where('part', '[0-9]+');
            Route::livewire('/production/old-prodev-list', 'pages::production.old_prodev_list')->name('production.old-prodev.list');
            Route::livewire('/production/old-prodev/{part?}', 'pages::production.old_prodev')->name('production.old-prodev')->where('part', '[0-9]+');
            Route::livewire('/production/old-prodev/{part}/print', 'pages::production.old_prodev_print')->name('production.old-prodev.print')->where('part', '[0-9]+');
        });

        // Laporan Manajemen -- lintas modul (penjualan + pembelian + master),
        // jadi TIDAK digate 'module:SALES'/'module:PROC' seperti layar operasional.
        // Dibatasi lewat modul sendiri (MGMT, min_mgmt_level=5 -- Direksi only,
        // lihat migration 2026_09_13_000009). Sebelum ini grup route TIDAK
        // digate sama sekali -- siapa saja yang login bisa membuka /laporan/*.
        Route::prefix('laporan')->middleware('module:MGMT')->group(function () {
            Route::livewire('/eksekutif', 'pages::report.executive')->name('report.executive');
            Route::livewire('/penjualan', 'pages::report.sales')->name('report.sales');
            Route::livewire('/pembelian', 'pages::report.purchase')->name('report.purchase');
            Route::livewire('/customer/{customer}', 'pages::report.customer')->name('report.customer')->where('customer', '[0-9]+');
            // 4 "macam laporan" tambahan (keputusan user 2026-09-13, lihat
            // migration 2026_09_13_000004_add_report_menu_items.php) -- semua
            // data nyata, sama pola dgn 4 di atas.
            Route::livewire('/akuntansi', 'pages::report.accounting')->name('report.accounting');
            Route::livewire('/produksi', 'pages::report.production')->name('report.production');
            Route::livewire('/sdm', 'pages::report.hr')->name('report.hr');
            Route::livewire('/gudang', 'pages::report.warehouse')->name('report.warehouse');
            Route::livewire('/estimasi-costing', 'pages::report.costing')->name('report.costing');

            // 4 menu di mockup (QMS/CMMS/EHS/Aset & Modal) TANPA modul sumber
            // data nyata di ERP ini -- 1 komponen coming-soon.blade.php dipakai
            // ulang, isi section/reason di-map dari {slug} URI (Route::livewire
            // macro Livewire TIDAK mendukung passing data tambahan lewat array
            // ke-3, jadi lewat parameter URI, bukan lewat closure param).
            Route::livewire('/kontrol/{slug}', 'pages::report.coming-soon')
                ->name('report.control-tower')
                ->where('slug', 'qms|cmms|ehs|assets');
        });
    });
});
