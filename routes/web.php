<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/menu');

Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'pages::auth.login')->name('login');
    Route::livewire('/register', 'pages::auth.register')->name('register');
    Route::livewire('/forgot-password', 'pages::auth.forgot-password')->name('password.request');
});

Route::middleware('auth')->group(function () {
    // Di luar force.password: halaman paksa-ganti-password sendiri, tak boleh
    // ikut digate olehnya (redirect loop) — security_defaultpassword_spec.md §6.
    Route::livewire('/password/change', 'pages::auth.password-change')->name('password.change');

    // Sama alasannya dgn password.change — halaman setup 4 pertanyaan keamanan
    // sendiri juga di luar force.password (redirect loop kalau ikut digate).
    Route::livewire('/security/setup', 'pages::auth.security-setup')->name('security.setup');

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

            Route::livewire('/quotation', 'pages::quotation.list')->name('quotation.list');
            Route::livewire('/quotation/{quotation}/edit', 'pages::quotation.entry')->name('quotation.entry');
            Route::livewire('/quotation/{quotation}/print', 'pages::quotation.print')->name('quotation.print');

            Route::livewire('/co', 'pages::co.list')->name('co.list');
            Route::livewire('/co/{order}/edit', 'pages::co.entry')->name('co.entry');
            Route::livewire('/co/delivery-plan', 'pages::co.delivery-plan')->name('co.delivery-plan');
        });

        Route::middleware('module:COSTING')->group(function () {
            Route::livewire('/inquiry/costing', 'pages::inquiry.costing-list')->name('inquiry.costing.list');
            Route::livewire('/inquiry/{inquiry}/costing', 'pages::inquiry.costing')->name('inquiry.costing');
        });

        Route::livewire('/security', 'pages::security.index')->name('security.index')->middleware('admin');

        // Portal self-service karyawan — bukan modul HR administratif (tanpa
        // module: gate), tapi personal & hanya milik akun sendiri. Guard
        // "linked employee" ditangani di dalam komponen (masteremployee_tier1.md §5.1).
        Route::livewire('/self-service', 'pages::self-service.index')->name('self-service.index');

        // Personal, bukan modul administratif -- sama pola dgn self-service (tanpa module: gate).
        Route::livewire('/notifications', 'pages::notification.index')->name('notifications.index');

        Route::middleware('module:MDATA')->group(function () {
            Route::livewire('/master/produk', 'pages::master.produk')->name('master.produk');
            Route::livewire('/master/customer', 'pages::master.customer')->name('master.customer');
            Route::livewire('/master/supplier', 'pages::master.supplier')->name('master.supplier');
            Route::livewire('/master/uom', 'pages::master.uom')->name('master.uom');
            Route::livewire('/master/machine', 'pages::master.machine')->name('master.machine');
            Route::livewire('/master/shift', 'pages::master.shift')->name('master.shift');

            Route::livewire('/master/raw-material', 'pages::master.raw-material.index')->name('master.raw-material.list');
            Route::livewire('/master/raw-material/create', 'pages::master.raw-material.entry')->name('master.raw-material.create');
            Route::livewire('/master/raw-material/{material}/edit', 'pages::master.raw-material.entry')->name('master.raw-material.edit');

            Route::livewire('/master/mat-category', 'pages::master.mat-category')->name('master.mat-category');

            Route::livewire('/master/warehouse', 'pages::master.warehouse.index')->name('master.warehouse.list');
            Route::livewire('/master/warehouse/create', 'pages::master.warehouse.entry')->name('master.warehouse.create');
            Route::livewire('/master/warehouse/{warehouse}/edit', 'pages::master.warehouse.entry')->name('master.warehouse.edit');
        });

        Route::middleware('module:FINACC')->group(function () {
            Route::livewire('/finance/coa', 'pages::finance.coa')->name('finance.coa');
            Route::livewire('/finance/dp-invoice', 'pages::finance.dp-invoice')->name('finance.dp-invoice');
            Route::livewire('/finance/cash-bank', 'pages::finance.cash-bank')->name('finance.cash-bank');
        });

        Route::middleware('module:PROC')->group(function () {
            Route::livewire('/purchasing/pr', 'pages::pur.pr.index')->name('pur.pr.list');
            Route::livewire('/purchasing/pr/{pr}/edit', 'pages::pur.pr.entry')->name('pur.pr.entry');
            Route::livewire('/purchasing/pr-approval', 'pages::pur.pr.approval')->name('pur.pr.approval');
            Route::livewire('/purchasing/po', 'pages::pur.po.index')->name('pur.po.list');
            Route::livewire('/purchasing/po/{po}/edit', 'pages::pur.po.entry')->name('pur.po.entry');
            Route::livewire('/purchasing/po-approval', 'pages::pur.po.approval')->name('pur.po.approval');
            Route::livewire('/purchasing/pv', 'pages::pur.pv.index')->name('pur.pv.list');
            Route::livewire('/purchasing/pv/{pv}/edit', 'pages::pur.pv.entry')->name('pur.pv.entry');
            Route::livewire('/purchasing/pq', 'pages::pur.pq.index')->name('pur.pq.list');
            Route::livewire('/purchasing/pq/{pq}/edit', 'pages::pur.pq.entry')->name('pur.pq.entry');
            Route::livewire('/purchasing/prt', 'pages::pur.prt.index')->name('pur.prt.list');
            Route::livewire('/purchasing/prt/{prt}/edit', 'pages::pur.prt.entry')->name('pur.prt.entry');
            Route::livewire('/purchasing/prt-approval', 'pages::pur.prt.approval')->name('pur.prt.approval');
            Route::livewire('/purchasing/report/outstanding-pr', 'pages::pur.report.outstanding-pr')->name('pur.report.outstanding-pr');
            Route::livewire('/purchasing/report/outstanding-po', 'pages::pur.report.outstanding-po')->name('pur.report.outstanding-po');
        });

        Route::middleware('module:INV')->group(function () {
            // {category} = RM|FG|SFG, wajib di URL -- menu sidebar sekarang
            // 3 entri terpisah per kelas (bukan toggle dalam halaman), lihat
            // sidebarmenustandard.md §5. {material?} tetap opsional utk deep-link.
            Route::livewire('/inventory/print/stock-card/{category}/{material?}', 'pages::inventory.print.stock-card')->name('inv.print.stock-card');
            Route::livewire('/inventory/print/master-stock/{category}', 'pages::inventory.print.master-stock')->name('inv.print.master-stock');

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
            Route::livewire('/hrm/employee', 'pages::master.employee')->name('hrm.employee');
            Route::livewire('/hrm/shift-roster', 'pages::hrm.shift-roster')->name('hrm.shift-roster');
            Route::livewire('/hrm/correction-requests', 'pages::hrm.correction-requests')->name('hrm.correction-requests');
        });

        Route::middleware('module:PROD')->group(function () {
            Route::livewire('/production/scheduling-board', 'pages::production.scheduling-board')->name('production.scheduling-board');
            Route::livewire('/production/wo', 'pages::production.wo-list')->name('production.wo.list');
            Route::livewire('/production/wo/{wo}', 'pages::production.wo-entry')->name('production.wo.entry');
            Route::livewire('/production/rule-library', 'pages::production.rule-library')->name('production.rule-library');
            Route::livewire('/production/mts-forecast', 'pages::production.mts-forecast')->name('production.mts-forecast');
            Route::livewire('/production/scrap-type', 'pages::production.scrap-type')->name('production.scrap-type');
        });
    });
});
