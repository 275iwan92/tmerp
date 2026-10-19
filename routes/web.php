<?php

/*
 * REMARK
 * Tujuan       : Pintu rute web: PWA, login, portal kandidat, dan pemuatan rute v2 (v2*.php) di dalam grup auth.
 * Sumber hulu  : Komponen Livewire halaman dan file rute v2 per modul (v2_review.php = tombol Process with Claude di Setup > Others).
 * Input        : Permintaan HTTP.
 * Output       : Halaman Livewire, rute v2, JSON.
 * Dipakai oleh : bootstrap/app.php.
 * Status       : Aktif.
 */

use App\Models\Sys\SysDocShare;
use App\Services\Portal\CandidateAuthService;
use App\Support\Branding\Brand;
use App\Support\Branding\PwaIcon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/', fn () => redirect('/v2'));

// PWA manifest -- dibuat dinamis (bukan public/manifest.json statis) supaya
// nama aplikasi ikut Brand::name() (tm2sys.sys_constant APP_NAME, bisa
// diedit lewat layar Configuration) tanpa perlu sinkron 2 tempat. Ikon =
// App\Support\Branding\PwaIcon: dibuat dari logo (APP_LOGO_PATH) bila ada,
// selain itu ikon bawaan public/images/pwa-icon-*.png.
Route::get('/manifest.json', function () {
    return response()->json([
        'name' => Brand::name(),
        'short_name' => 'ERP TM',
        'start_url' => '/menu',
        'display' => 'standalone',
        'background_color' => '#2c3e69',
        'theme_color' => '#2c3e69',
        'icons' => [
            ['src' => PwaIcon::url(192), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ['src' => PwaIcon::url(512), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
        ],
    ])->header('Content-Type', 'application/manifest+json');
})->name('pwa.manifest');

Route::get('/pwa-icon/{size}.png', fn (int $size) => response()->file(PwaIcon::file($size), ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=86400']))
    ->whereIn('size', array_map('strval', PwaIcon::SIZES))->name('pwa.icon');

// Dokumen online hasil Print/Email (QR serah terima) -- publik tanpa login, token acak 128-bit, isi = snapshot saat dicetak.
Route::get('/d/{token}', function (string $token) {
    $share = SysDocShare::where('token', $token)->first();
    abort_if(! $share, 404);
    abort_if($share->isExpired(), 410, t('This document link has expired.'));
    $share->timestamps = false;
    $share->increment('view_count', 1, ['last_viewed_at' => now()]);
    $nonce = Str::random(24);

    return response()->view('v2.share', ['share' => $share, 'nonce' => $nonce])
        ->header('Content-Security-Policy', "script-src 'nonce-{$nonce}' https://cdnjs.cloudflare.com; object-src 'none'; base-uri 'none'; form-action 'none'")
        ->header('X-Robots-Tag', 'noindex');
})->where('token', '[a-f0-9]{32}')->name('doc.share');

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
        // Situs v2 (mockup.test): menu lama tidak dipakai -> ke Menu v2.
        config('app.ui') === 'v2'
            ? Route::redirect('/menu', '/v2')->name('menu')
            : Route::livewire('/menu', 'pages::main-menu')->name('menu');

        require __DIR__.'/v2.php';
        require __DIR__.'/v2_master_card.php';
        require __DIR__.'/v2_maintenance.php';
        require __DIR__.'/v2_planning.php';
        require __DIR__.'/v2_ppic.php';
        require __DIR__.'/v2_account.php';
        require __DIR__.'/v2_finance.php'; require __DIR__.'/v2_master.php'; require __DIR__.'/v2_stockdocs.php'; require __DIR__.'/v2_purchase_return.php'; require __DIR__.'/v2_production.php'; require __DIR__.'/v2_hrm.php';
        require __DIR__.'/v2_warehouse.php';
        require __DIR__.'/v2_picklist.php';
        require __DIR__.'/v2_stockflow.php';
        require __DIR__.'/v2_valuation.php';
        require __DIR__.'/v2_review.php';
        require __DIR__.'/v2_dummy_mastercard.php'; require __DIR__.'/v2_techguide.php'; // TG diagram route (same line: docs/tg cards cite web.php line numbers)

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
            Route::livewire('/quotation/new', 'pages::quotation.entry')->name('quotation.create');
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
        Route::livewire('/setup/support-tickets', 'pages::setup.support-tickets')->name('setup.support-tickets')->middleware('admin');

        // Help Center -- panduan pakai aplikasi (bukan tiket support, itu modal
        // "Help & Support" terpisah di profile dropdown). SEMUA user login boleh
        // akses (tanpa middleware 'admin' seperti /security di atas).
        Route::livewire('/bantuan', 'pages::help.index')->name('help.index');

        // Self-service v1 dihapus 2026-10-18 (aturan D.25): layar resmi = Edit profile v2 (v2.profile).
        // URL lama hanya diteruskan ke sana (?tab ikut dibawa) supaya bookmark tidak 404.
        // Jumlah baris blok ini sengaja tetap 4 supaya nomor baris yang dikutip kartu TG tidak bergeser.
        Route::get('/self-service', fn (\Illuminate\Http\Request $r) => redirect()->route('v2.profile', $r->query()));

        // Personal, bukan modul administratif -- sama pola dgn self-service (tanpa module: gate).
        Route::livewire('/notifications', 'pages::notification.index')->name('notifications.index');

        Route::middleware('module:MDATA')->group(function () {
            Route::redirect('/master/customer', '/v2/s/md-customer');
            Route::redirect('/master/supplier', '/v2/s/md-supplier');
            Route::get('/master/machine', fn (Illuminate\Http\Request $r) => redirect($r->query('open') ? '/v2/s/prod-machine-form?id='.(int) $r->query('open') : ($r->boolean('new') ? '/v2/s/prod-machine-form?new=1' : '/v2/s/prod-machine')))->name('master.machine');
            Route::redirect('/master/shift', '/v2/s/md-shift');

            Route::redirect('/master/raw-material', '/v2/s/md-raw')->name('master.raw-material.list');
            Route::redirect('/master/raw-material/create', '/v2/s/md-raw-form?new=1')->name('master.raw-material.create');
            Route::get('/master/raw-material/{material}/edit', fn (int $material) => redirect('/v2/s/md-raw-form?id='.$material))->name('master.raw-material.edit');

            Route::redirect('/master/warehouse', '/v2/s/wh-buildings');
            Route::redirect('/master/warehouse/create', '/v2/s/wh-buildings');
            Route::redirect('/master/warehouse/{warehouse}/edit', '/v2/s/wh-buildings');
            Route::redirect('/master/warehouse/{warehouse}/print-labels', '/v2/s/label-location');
        });

        Route::middleware('module:FINACC')->group(function () {
            Route::redirect('/finance/coa', '/v2/s/acc-coa');
            Route::redirect('/finance/cash-bank', '/v2/s/acc-cash-bank');

            // Accounting Process Engine (mockup MODULE_accounting-*-blueprint.html, 2026-09-19)
            Route::redirect('/finance/payables', '/v2/s/acc-payables');
            Route::redirect('/finance/journals', '/v2/s/acc-journal');
            Route::redirect('/finance/assets', '/v2/s/acc-fixed-asset');
            Route::redirect('/finance/amortization', '/v2/s/acc-amortization');
        });

        Route::middleware('module:PROC')->group(function () {
            Route::livewire('/purchasing/pr', 'pages::pur.pr.index')->name('pur.pr.list');
            Route::livewire('/purchasing/pr/{pr}/edit', 'pages::pur.pr.entry')->name('pur.pr.entry');
            Route::livewire('/purchasing/pr/{pr}/print', 'pages::pur.pr.print')->name('pur.pr.print');
            Route::livewire('/purchasing/po', 'pages::pur.po.index')->name('pur.po.list');
            Route::livewire('/purchasing/po/{po}/edit', 'pages::pur.po.entry')->name('pur.po.entry');
            Route::livewire('/purchasing/po/{po}/print', 'pages::pur.po.print')->name('pur.po.print');
            Route::livewire('/purchasing/pv', 'pages::pur.pv.index')->name('pur.pv.list');
            Route::livewire('/purchasing/pv/{pv}/edit', 'pages::pur.pv.entry')->name('pur.pv.entry');
            Route::get('/purchasing/prt', fn (Illuminate\Http\Request $r) => redirect($r->boolean('new') ? '/v2/s/pur-return-form?new=1' : '/v2/s/purchase-return'))->name('pur.prt.list');
            Route::get('/purchasing/prt/{prt}/edit', fn (int $prt) => redirect('/v2/s/pur-return-form?id='.$prt))->name('pur.prt.entry');
        });

        Route::middleware('module:INV')->group(function () {
            // {category} = RM|FG|SFG, wajib di URL -- menu sidebar sekarang
            // 3 entri terpisah per kelas (bukan toggle dalam halaman), lihat
            // sidebarmenustandard.md §5. {material?} tetap opsional utk deep-link.

            // inventory_tier4.md #1.1 -- {category} = RM|FG|SFG, pola sama seperti Laporan.
            Route::get('/inventory/stock-in/{category}', fn (Illuminate\Http\Request $r, string $category) => redirect($r->query('open') ? '/v2/s/inv-stock-doc-form?kind=in&class='.strtoupper($category).'&id='.(int) $r->query('open') : ($r->boolean('new') ? '/v2/s/inv-stock-doc-form?kind=in&class='.strtoupper($category).'&new=1' : '/v2/s/inv-'.strtolower($category).'-stock-in')))->name('inv.stock-in.index');
            // Stock Out & Return v1 dikunci RM saja (inventory_rules.yaml out_wajib_wo /
            // fg_out_bukan_milik_modul) -- {category} tetap di URL, ditolak di
            // level route (mount()) kalau bukan RM, bukan cuma disembunyikan di UI.
            Route::get('/inventory/stock-out/{category}', fn (Illuminate\Http\Request $r, string $category) => redirect($r->query('open') ? '/v2/s/inv-stock-doc-form?kind=out&class='.strtoupper($category).'&id='.(int) $r->query('open') : ($r->boolean('new') ? '/v2/s/inv-stock-doc-form?kind=out&class='.strtoupper($category).'&new=1' : '/v2/s/inv-'.strtolower($category).'-stock-out')))->name('inv.stock-out.index');
            Route::get('/inventory/stock-return/{category}', fn (Illuminate\Http\Request $r, string $category) => redirect($r->query('open') ? '/v2/s/inv-stock-doc-form?kind=return&class='.strtoupper($category).'&id='.(int) $r->query('open') : ($r->boolean('new') ? '/v2/s/inv-stock-doc-form?kind=return&class='.strtoupper($category).'&new=1' : '/v2/s/inv-'.strtolower($category).'-stock-return')))->name('inv.stock-return.index');
            // Adjustment & Opname mencakup RM/FG/SFG (inventory_tier4.md #4) -- entri
            // & approval route TERPISAH (inventory_rules.yaml layar_terpisah).
            // {doc?} opsional -- dipakai layar Persetujuan (approval) utk deep-link
            // langsung ke form dokumen tertentu (grid<->form tetap 1 komponen).
            Route::get('/inventory/stock-adjustment/{category}/{doc?}', fn (Illuminate\Http\Request $r, string $category, ?string $doc = null) => redirect(($doc || $r->query('open')) ? '/v2/s/inv-stock-adj-form?class='.strtoupper($category).'&id='.(int) ($doc ?: $r->query('open')) : ($r->boolean('new') ? '/v2/s/inv-stock-adj-form?class='.strtoupper($category).'&new=1' : '/v2/s/inv-'.strtolower($category).'-stock-adj')))->name('inv.stock-adj.index');
        });

        Route::middleware('module:HRM')->group(function () {
            // Layar v1 Master Position dipensiunkan 2026-10-17: alamat lama -> grid v2; nama rute dipertahankan.
            Route::redirect('/hrm/position', '/v2/s/hrm-position')->name('hrm.position');
            Route::livewire('/hrm/sop', 'pages::hrm.sop-documents')->name('hrm.sop-documents');
            Route::livewire('/hrm/org-chart', 'pages::hrm.org-chart')->name('hrm.org-chart');
            // Layar v1 Master Employee dipensiunkan 2026-10-16: alamat lama (bookmark, tautan v1) -> grid v2; nama rute dipertahankan.
            Route::redirect('/hrm/employee', '/v2/s/hrm-employee')->name('hrm.employee');
            Route::livewire('/hrm/shift-roster/print', 'pages::hrm.shift-roster-print')->name('hrm.shift-roster.print');
            Route::livewire('/hrm/correction-requests', 'pages::hrm.correction-requests')->name('hrm.correction-requests');
            Route::livewire('/hrm/leave-requests', 'pages::hrm.leave-requests')->name('hrm.leave-requests');
            Route::livewire('/hrm/attendance-daily', 'pages::hrm.attendance-daily')->name('hrm.attendance-daily');
            Route::livewire('/hrm/payroll/employee-salary', 'pages::hrm.payroll-employee-salary')->name('hrm.payroll.employee-salary')->middleware('payroll:process');
            Route::livewire('/hrm/payroll/employee-bank', 'pages::hrm.payroll-employee-bank')->name('hrm.payroll.employee-bank')->middleware('payroll:process');
            Route::get('/hrm/gps-approval', fn (Illuminate\Http\Request $r) => redirect($r->query('open') ? '/v2/s/hrm-gps-form?id='.(int) $r->query('open') : '/v2/s/hrm-gps'))->name('hrm.gps-approval');
            Route::livewire('/hrm/payroll/batch-run', 'pages::hrm.payroll-batch-run')->name('hrm.payroll.batch-run')->middleware('payroll:process');
            Route::livewire('/hrm/payroll/payslip', 'pages::hrm.payroll-payslip-list')->name('hrm.payroll.payslip-list')->middleware('payroll:payslip');
            Route::livewire('/hrm/payroll/payslip/print-all/{run}', 'pages::hrm.payroll-payslip-print-all')->name('hrm.payroll.payslip-print-all')->middleware('payroll:payslip')->where('run', '[0-9]+');
            Route::livewire('/hrm/payroll/payslip/{payslip}/print', 'pages::hrm.payroll-payslip-print')->name('hrm.payroll.payslip-print')->middleware('payroll:payslip')->where('payslip', '[0-9]+');
            Route::livewire('/hrm/payroll/bank-transfer', 'pages::hrm.payroll-bank-transfer')->name('hrm.payroll.bank-transfer')->middleware('payroll:process');

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
            Route::livewire('/hrm/recruitment/hire-provision', 'pages::recruitment.hire-provision')->name('hrm.recruitment.hire-provision');
        });

        Route::middleware('module:PROD')->group(function () {
            Route::livewire('/production/wo', 'pages::production.wo-list')->name('production.wo.list');
            Route::livewire('/production/wo/new', 'pages::production.wo-entry')->name('production.wo.new');
            Route::livewire('/production/wo/{wo}/print', 'pages::production.wo-print')->name('production.wo.print')->where('wo', '[0-9]+');
            Route::livewire('/production/wo/{wo}', 'pages::production.wo-entry')->name('production.wo.entry')->where('wo', '[0-9]+');
            Route::get('/production/scrap-type', fn (Illuminate\Http\Request $r) => redirect($r->query('open') ? '/v2/s/prod-scrap-form?id='.(int) $r->query('open') : ($r->boolean('new') ? '/v2/s/prod-scrap-form?new=1' : '/v2/s/prod-scrap')))->name('production.scrap-type');
            Route::livewire('/production/process-rate', 'pages::production.process-rate')->name('production.process-rate');
            Route::get('/production/master-process', fn (Illuminate\Http\Request $r) => redirect($r->query('open') ? '/v2/s/prod-process-form?id='.(int) $r->query('open') : ($r->boolean('new') ? '/v2/s/prod-process-form?new=1' : '/v2/s/prod-process')))->name('production.master-process');
            Route::get('/production/routing-template', fn (Illuminate\Http\Request $r) => redirect($r->query('open') ? '/v2/s/prod-routing-form?id='.(int) $r->query('open') : ($r->boolean('new') ? '/v2/s/prod-routing-form?new=1' : '/v2/s/prod-routing')))->name('production.routing-template');
            Route::get('/production/bop-rule', fn (Illuminate\Http\Request $r) => redirect($r->query('open') ? '/v2/s/prod-bop-form?id='.(int) $r->query('open') : ($r->boolean('new') ? '/v2/s/prod-bop-form?new=1' : '/v2/s/prod-bop-rule')))->name('production.bop-rule');
            // ProDev BARU (2026-09-14) -- Imposition Plano Optimizer, port dari
            // docs/mockups/mockup_prodev_imposition.html. Ini yang dipasang di
            // menu (SALES_IMPO). Layar ProDev LAMA (workbench 3-kolom +
            // grid + Master Card print) diarsipkan jadi old_prodev* di bawah:
            // route-nya SENGAJA dipertahankan supaya data/alur lama tidak
            // putus (ProCost Mode #1 masih baca prod_layout_group yang cuma
            // bisa dibuat di sana), tapi TIDAK muncul di menu mana pun.
            // Grid depan dulu (griduistandard.md), klik 2x baru buka layar kerja.
            Route::livewire('/production/prodev-list', 'pages::production.prodev-list')->name('production.prodev.list');
            // Lookup Product Market (officer ProDev): sumber "Kategori Produk" di Inquiry New.
            Route::redirect('/lookup/product-market', '/v2/s/sales-product-market');
            Route::redirect('/lookup/product-market/new', '/v2/s/sales-market-form?new=1');
            Route::get('/lookup/product-market/{market}/edit', fn (int $market) => redirect('/v2/s/sales-market-form?id='.$market));
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
        });
    });
});
