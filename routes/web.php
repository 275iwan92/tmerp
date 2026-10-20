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
        require __DIR__.'/v2_finance.php'; require __DIR__.'/v2_master.php'; require __DIR__.'/v2_stockdocs.php'; require __DIR__.'/v2_purchase_return.php'; require __DIR__.'/v2_production.php'; require __DIR__.'/v2_hrm.php'; require __DIR__.'/v2_setup.php';
        require __DIR__.'/v2_warehouse.php';
        require __DIR__.'/v2_picklist.php';
        require __DIR__.'/v2_stockflow.php';
        require __DIR__.'/v2_valuation.php';
        require __DIR__.'/v2_review.php';
        require __DIR__.'/v2_dummy_mastercard.php'; require __DIR__.'/v2_techguide.php'; // TG diagram route (same line: docs/tg cards cite web.php line numbers)

        Route::middleware('module:SALES')->group(function () {
            Route::redirect('/inquiry', '/v2/s/inquiry')->name('inquiry.hub');
            Route::livewire('/inquiry/new', 'pages::inquiry.form')->name('inquiry.form');
            Route::get('/inquiry/recalc', fn (Illuminate\Http\Request $r) => redirect('/v2/s/inquiry-recalc'.($r->filled('id') ? '?id='.(int) $r->query('id') : '')))->name('inquiry.recalc');
            Route::get('/inquiry/revise', fn (Illuminate\Http\Request $r) => redirect('/v2/s/inquiry-revise'.($r->filled('id') ? '?id='.(int) $r->query('id') : '')))->name('inquiry.revise');
            Route::livewire('/inquiry/imposition', 'pages::inquiry.imposition-list')->name('inquiry.imposition.list');
            Route::livewire('/inquiry/{inquiry}/imposition', 'pages::inquiry.imposition')->name('inquiry.imposition');
            Route::livewire('/inquiry/{inquiry}/nota', 'pages::inquiry.nota')->name('inquiry.nota');
            Route::redirect('/sales/win-rate', '/v2/s/sales-win-rate')->name('sales.win-rate');

            Route::redirect('/quotation', '/v2/s/quotation')->name('quotation.list');
            Route::redirect('/quotation/new', '/v2/s/quotation?pick=1')->name('quotation.create');
            Route::get('/quotation/{quotation}/edit', fn (int $quotation) => redirect('/v2/s/quotation?id='.$quotation))->name('quotation.entry');
            Route::get('/quotation/{quotation}/print', fn (int $quotation) => redirect('/v2/print/quotation/'.$quotation))->name('quotation.print');

            Route::redirect('/co', '/v2/s/customer-order')->name('co.list');
            Route::get('/co/{order}/edit', fn (int $order) => redirect('/v2/s/customer-order?id='.$order))->name('co.entry');
            Route::get('/co/{order}/print', fn (int $order) => redirect('/v2/print/customer-order/'.$order))->name('co.print');
            Route::redirect('/co/delivery-plan', '/v2/s/cpdel')->name('co.delivery-plan');

            // Delivery (DO/Surat Jalan) -- sebelumnya modul "Dispatch" berdiri sendiri,
            // digabung ke Sales > grup "Delivery" (menu SALES_DO) 2026-08-28 atas
            // permintaan user (kepanjangan Delivery Order/Delivery Notes, bukan
            // "Dispatch"). {doId} wajib di sini (beda dari spec teks asli "{doId?}")
            // krn DoList SELALU pre-create header via picker customer sebelum redirect
            // ke Entry (lihat catatan deviasi di dispatch/do/list.blade.php) -- tak
            // pernah ada Entry tanpa doId.
            Route::redirect('/dispatch/do', '/v2/s/delivery-order')->name('dispatch.do.index');
            Route::get('/dispatch/do/{doId}', fn (int $doId) => redirect('/v2/s/delivery-order?id='.$doId))->name('dispatch.do.entry')->where('doId', '[0-9]+');
            Route::get('/dispatch/do/{doId}/print', fn (int $doId) => redirect('/v2/print/delivery-order/'.$doId))->name('dispatch.do.print')->where('doId', '[0-9]+');
            Route::get('/dispatch/do/{doId}/print-surat-jalan', fn (int $doId) => redirect('/v2/print/delivery-note/'.$doId))->name('dispatch.do.print-surat-jalan')->where('doId', '[0-9]+');
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

        Route::get('/security', fn (Illuminate\Http\Request $r) => redirect(match ($r->query('tab')) { 'groups' => $r->filled('open') ? '/v2/s/security-group-form?id='.(int) $r->query('open') : ($r->boolean('new') ? '/v2/s/security-group-form?new=1' : '/v2/s/security-groups'), 'perms' => '/v2/s/security-permissions', 'migration' => '/v2/s/setup-migration', default => $r->filled('open') ? '/v2/s/security-account-form?id='.(int) $r->query('open') : ($r->boolean('new') ? '/v2/s/security-account-form?new=1' : '/v2/s/security-accounts') }))->name('security.index')->middleware('admin');
        Route::redirect('/setup/identification', '/v2/s/setup-identification')->name('setup.identification')->middleware('admin');
        Route::redirect('/setup/language', '/v2/s/languages')->name('setup.language')->middleware('admin');
        Route::redirect('/setup/localization', '/v2/s/localization')->name('setup.localization')->middleware('admin');
        Route::redirect('/setup/constants', '/v2/s/setup-constants')->name('setup.constants')->middleware('admin');
        Route::redirect('/setup/data-origin', '/v2/s/setup-data-origin')->name('setup.data-origin')->middleware('admin');
        Route::get('/setup/support-tickets', fn (Illuminate\Http\Request $r) => redirect($r->filled('open') ? '/v2/s/support-ticket-form?id='.(int) $r->query('open') : '/v2/s/support-tickets'))->name('setup.support-tickets')->middleware('admin');

        // Help Center -- panduan pakai aplikasi (bukan tiket support, itu modal
        // "Help & Support" terpisah di profile dropdown). SEMUA user login boleh
        // akses (tanpa middleware 'admin' seperti /security di atas).
        Route::get('/bantuan', fn () => redirect()->route('v2.help'))->name('help.index');

        // Self-service v1 dihapus 2026-10-18 (aturan D.25): layar resmi = Edit profile v2 (v2.profile).
        // URL lama hanya diteruskan ke sana (?tab ikut dibawa) supaya bookmark tidak 404.
        // Jumlah baris blok ini sengaja tetap 4 supaya nomor baris yang dikutip kartu TG tidak bergeser.
        Route::get('/self-service', fn (\Illuminate\Http\Request $r) => redirect()->route('v2.profile', $r->query()));

        // Personal, bukan modul administratif -- sama pola dgn self-service (tanpa module: gate).
        Route::redirect('/notifications', '/v2/notifications')->name('notifications.index');

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
            Route::redirect('/purchasing/pr', '/v2/s/purchase-requisition')->name('pur.pr.list');
            Route::get('/purchasing/pr/{pr}/edit', fn (int $pr) => redirect('/v2/s/purchase-requisition?id='.$pr))->name('pur.pr.entry')->where('pr', '[0-9]+');
            Route::get('/purchasing/pr/{pr}/print', fn (int $pr) => redirect('/v2/print/purchase-requisition/'.$pr))->name('pur.pr.print')->where('pr', '[0-9]+');
            Route::redirect('/purchasing/po', '/v2/s/purchase-order')->name('pur.po.list');
            Route::get('/purchasing/po/{po}/edit', fn (int $po) => redirect('/v2/s/purchase-order?id='.$po))->name('pur.po.entry')->where('po', '[0-9]+');
            Route::get('/purchasing/po/{po}/print', fn (int $po) => redirect('/v2/print/purchase-order/'.$po))->name('pur.po.print')->where('po', '[0-9]+');
            Route::redirect('/purchasing/pv', '/v2/s/purchase-receive')->name('pur.pv.list');
            Route::get('/purchasing/pv/{pv}/edit', fn (int $pv) => redirect('/v2/s/purchase-receive?id='.$pv))->name('pur.pv.entry')->where('pv', '[0-9]+');
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
            Route::get('/hrm/sop', fn (Illuminate\Http\Request $r) => redirect($r->query('open') ? '/v2/s/hrm-sop-form?id='.(int) $r->query('open') : '/v2/s/hrm-sop'))->name('hrm.sop-documents');
            Route::redirect('/hrm/org-chart', '/v2/s/hrm-org-chart')->name('hrm.org-chart');
            // Layar v1 Master Employee dipensiunkan 2026-10-16: alamat lama (bookmark, tautan v1) -> grid v2; nama rute dipertahankan.
            Route::redirect('/hrm/employee', '/v2/s/hrm-employee')->name('hrm.employee');
            Route::get('/hrm/shift-roster/print', fn (Illuminate\Http\Request $r) => redirect('/v2/hrm/roster/print'.($r->query() ? '?'.http_build_query($r->query()) : '')))->name('hrm.shift-roster.print');
            Route::get('/hrm/correction-requests', fn (Illuminate\Http\Request $r) => redirect($r->query('open') ? '/v2/s/hrm-correction-form?id='.(int) $r->query('open') : '/v2/s/hrm-correction'))->name('hrm.correction-requests');
            Route::get('/hrm/leave-requests', fn (Illuminate\Http\Request $r) => redirect($r->query('open') ? '/v2/s/hrm-leave-form?id='.(int) $r->query('open') : '/v2/s/hrm-leave'))->name('hrm.leave-requests');
            Route::get('/hrm/attendance-daily', fn (Illuminate\Http\Request $r) => redirect($r->query('open') ? '/v2/s/hrm-attendance-form?id='.(int) $r->query('open') : '/v2/s/hrm-attendance'))->name('hrm.attendance-daily');
            Route::get('/hrm/payroll/employee-salary', fn (Illuminate\Http\Request $r) => redirect($r->filled('open') ? '/v2/s/hrm-salary-form?id='.(int) $r->query('open') : '/v2/s/hrm-salary'))->name('hrm.payroll.employee-salary')->middleware('payroll:process');
            Route::get('/hrm/payroll/employee-bank', fn (Illuminate\Http\Request $r) => redirect($r->query('open') ? '/v2/s/hrm-employee-form?id='.(int) $r->query('open').'&tab=bank' : '/v2/s/hrm-bank'))->name('hrm.payroll.employee-bank')->middleware('payroll:process');
            Route::get('/hrm/gps-approval', fn (Illuminate\Http\Request $r) => redirect($r->query('open') ? '/v2/s/hrm-gps-form?id='.(int) $r->query('open') : '/v2/s/hrm-gps'))->name('hrm.gps-approval');
            Route::get('/hrm/payroll/batch-run', fn (Illuminate\Http\Request $r) => redirect($r->filled('open') ? '/v2/s/hrm-payroll-run-form?id='.(int) $r->query('open') : ($r->boolean('new') ? '/v2/s/hrm-payroll-run-form?new=1' : '/v2/s/hrm-payroll-run')))->name('hrm.payroll.batch-run')->middleware('payroll:process');
            Route::get('/hrm/payroll/payslip', fn () => redirect('/v2/s/hrm-payslip'))->name('hrm.payroll.payslip-list')->middleware('payroll:payslip');
            Route::get('/hrm/payroll/payslip/print-all/{run}', fn (int $run) => redirect('/v2/hrm/payslip/print-all/'.$run))->name('hrm.payroll.payslip-print-all')->middleware('payroll:payslip')->where('run', '[0-9]+');
            Route::get('/hrm/payroll/payslip/{payslip}/print', fn (int $payslip) => redirect('/v2/print/payslip/'.$payslip))->name('hrm.payroll.payslip-print')->middleware('payroll:payslip')->where('payslip', '[0-9]+');
            Route::redirect('/hrm/payroll/bank-transfer', '/v2/s/hrm-bank-transfer')->name('hrm.payroll.bank-transfer')->middleware('payroll:process');

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
            Route::get('/hrm/recruitment/requisition', fn (Illuminate\Http\Request $r) => redirect($r->boolean('new') ? '/v2/s/hrm-rcr-requisition-form?new=1' : '/v2/s/hrm-rcr-requisition'))->name('hrm.recruitment.requisition.list');
            Route::get('/hrm/recruitment/requisition/{requisition}', fn (int $requisition) => redirect('/v2/s/hrm-rcr-requisition-form?id='.$requisition))->name('hrm.recruitment.requisition.entry')->where('requisition', '[0-9]+');
            Route::get('/hrm/recruitment/vacancy', fn (Illuminate\Http\Request $r) => redirect($r->boolean('new') ? '/v2/s/hrm-rcr-vacancy-form?new=1' : '/v2/s/hrm-rcr-vacancy'))->name('hrm.recruitment.vacancy');
            Route::get('/hrm/recruitment/talent-pool', fn (Illuminate\Http\Request $r) => redirect($r->filled('open') ? '/v2/s/rcr-talent-pool-form?id='.(int) $r->query('open') : '/v2/s/rcr-talent-pool'))->name('hrm.recruitment.talent-pool');
            Route::get('/hrm/recruitment/candidate-review', fn () => redirect('/v2/s/hrm-rcr-review'))->name('hrm.recruitment.candidate-review.list');
            Route::get('/hrm/recruitment/candidate-review/{application}', fn (int $application) => redirect('/v2/s/hrm-rcr-review-form?application='.$application))->name('hrm.recruitment.candidate-review.detail')->where('application', '[0-9]+');
            Route::get('/hrm/recruitment/pipeline-board', fn (Illuminate\Http\Request $r) => redirect('/v2/s/hrm-pipeline-board'.($r->filled('vacancy') ? '?vacancy='.(int) $r->query('vacancy') : '')))->name('hrm.recruitment.pipeline-board');
            Route::get('/hrm/recruitment/interview-schedule', fn (Illuminate\Http\Request $r) => redirect($r->filled('open') ? '/v2/s/hrm-rcr-interview-form?id='.(int) $r->query('open') : '/v2/s/hrm-rcr-interview'))->name('hrm.recruitment.interview-schedule');
            Route::get('/hrm/recruitment/assessment-result', fn (Illuminate\Http\Request $r) => redirect($r->boolean('new') ? '/v2/s/hrm-rcr-assessment-form?new=1' : '/v2/s/hrm-rcr-assessment'))->name('hrm.recruitment.assessment-result');
            Route::get('/hrm/recruitment/offer', fn () => redirect('/v2/s/hrm-rcr-offer'))->name('hrm.recruitment.offer.list');
            Route::get('/hrm/recruitment/offer/{application}', fn (int $application) => redirect('/v2/s/hrm-rcr-offer-form?application='.$application))->name('hrm.recruitment.offer.entry')->where('application', '[0-9]+');
            Route::get('/hrm/recruitment/hire-provision', fn (Illuminate\Http\Request $r) => redirect($r->filled('open') ? '/v2/s/hrm-rcr-provision-form?id='.(int) $r->query('open') : '/v2/s/hrm-rcr-provision'))->name('hrm.recruitment.hire-provision');
        });

        Route::middleware('module:PROD')->group(function () {
            Route::redirect('/production/wo', '/v2/s/production-wo')->name('production.wo.list');
            Route::redirect('/production/wo/new', '/v2/s/production-wo?new=1')->name('production.wo.new');
            Route::get('/production/wo/{wo}/print', fn (int $wo) => redirect('/v2/production/wo/'.$wo.'/print'))->name('production.wo.print')->where('wo', '[0-9]+');
            Route::get('/production/wo/{wo}', fn (int $wo) => redirect('/v2/s/production-wo?id='.$wo))->name('production.wo.entry')->where('wo', '[0-9]+');
            Route::get('/production/scrap-type', fn (Illuminate\Http\Request $r) => redirect($r->query('open') ? '/v2/s/prod-scrap-form?id='.(int) $r->query('open') : ($r->boolean('new') ? '/v2/s/prod-scrap-form?new=1' : '/v2/s/prod-scrap')))->name('production.scrap-type');
            Route::redirect('/production/process-rate', '/v2/s/production-process-rate')->name('production.process-rate');
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
            Route::redirect('/production/prodev-list', '/v2/s/prodev')->name('production.prodev.list');
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
            Route::get('/eksekutif', fn (Illuminate\Http\Request $r) => redirect('/v2/s/mgmt-executive'.($r->query() ? '?'.http_build_query($r->query()) : '')))->name('report.executive');
            Route::get('/penjualan', fn (Illuminate\Http\Request $r) => redirect('/v2/s/mgmt-sales'.($r->query() ? '?'.http_build_query($r->query()) : '')))->name('report.sales');
            Route::get('/pembelian', fn (Illuminate\Http\Request $r) => redirect('/v2/s/mgmt-purchase'.($r->query() ? '?'.http_build_query($r->query()) : '')))->name('report.purchase');
            Route::get('/customer/{customer}', fn (Illuminate\Http\Request $r, int $customer) => redirect('/v2/s/mgmt-customer?'.http_build_query(['customer' => $customer] + $r->query())))->name('report.customer')->where('customer', '[0-9]+');
            // 4 "macam laporan" tambahan (keputusan user 2026-09-13, lihat
            // migration 2026_09_13_000004_add_report_menu_items.php) -- semua
            // data nyata, sama pola dgn 4 di atas.
            Route::get('/akuntansi', fn (Illuminate\Http\Request $r) => redirect('/v2/s/mgmt-accounting'.($r->query() ? '?'.http_build_query($r->query()) : '')))->name('report.accounting');
            Route::get('/produksi', fn (Illuminate\Http\Request $r) => redirect('/v2/s/mgmt-production'.($r->query() ? '?'.http_build_query($r->query()) : '')))->name('report.production');
            Route::get('/sdm', fn (Illuminate\Http\Request $r) => redirect('/v2/s/mgmt-hr'.($r->query() ? '?'.http_build_query($r->query()) : '')))->name('report.hr');
            Route::get('/gudang', fn (Illuminate\Http\Request $r) => redirect('/v2/s/mgmt-warehouse'.($r->query() ? '?'.http_build_query($r->query()) : '')))->name('report.warehouse');
        });
    });
});
