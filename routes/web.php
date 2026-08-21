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
    // ikut digate olehnya (redirect loop) — SECURITY_default_password_spec.md §6.
    Route::livewire('/password/change', 'pages::auth.password-change')->name('password.change');

    Route::middleware('force.password')->group(function () {
        Route::livewire('/menu', 'pages::main-menu')->name('menu');

        Route::middleware('module:SALES')->group(function () {
            Route::livewire('/inquiry', 'pages::inquiry.hub')->name('inquiry.hub');
            Route::livewire('/inquiry/new', 'pages::inquiry.form')->name('inquiry.form');
            Route::livewire('/inquiry/recalc', 'pages::inquiry.recalc')->name('inquiry.recalc');
            Route::livewire('/inquiry/revise', 'pages::inquiry.revise')->name('inquiry.revise');
            Route::livewire('/inquiry/{inquiry}/imposition', 'pages::inquiry.imposition')->name('inquiry.imposition');
            Route::livewire('/inquiry/{inquiry}/nota', 'pages::inquiry.nota')->name('inquiry.nota');

            Route::livewire('/quotation', 'pages::quotation.list')->name('quotation.list');
            Route::livewire('/quotation/{quotation}/edit', 'pages::quotation.entry')->name('quotation.entry');
            Route::livewire('/quotation/{quotation}/print', 'pages::quotation.print')->name('quotation.print');

            Route::livewire('/co', 'pages::co.list')->name('co.list');
            Route::livewire('/co/{order}/edit', 'pages::co.entry')->name('co.entry');
        });

        Route::middleware('module:COSTING')->group(function () {
            Route::livewire('/inquiry/{inquiry}/costing', 'pages::inquiry.costing')->name('inquiry.costing');
        });

        Route::livewire('/security', 'pages::security.index')->name('security.index')->middleware('admin');

        // Portal self-service karyawan — bukan modul HR administratif (tanpa
        // module: gate), tapi personal & hanya milik akun sendiri. Guard
        // "linked employee" ditangani di dalam komponen (TIER_1_HRM.md §5.1).
        Route::livewire('/self-service', 'pages::self-service.index')->name('self-service.index');

        // Personal, bukan modul administratif -- sama pola dgn self-service (tanpa module: gate).
        Route::livewire('/notifications', 'pages::notification.index')->name('notifications.index');

        Route::middleware('module:MDATA')->group(function () {
            Route::livewire('/master/customer', 'pages::master.customer')->name('master.customer');
            Route::livewire('/master/supplier', 'pages::master.supplier')->name('master.supplier');
            Route::livewire('/master/uom', 'pages::master.uom')->name('master.uom');
            Route::livewire('/master/machine', 'pages::master.machine')->name('master.machine');
            Route::livewire('/master/shift', 'pages::master.shift')->name('master.shift');

            Route::livewire('/master/raw-material', 'pages::master.raw-material.index')->name('master.raw-material.list');
            Route::livewire('/master/raw-material/create', 'pages::master.raw-material.entry')->name('master.raw-material.create');
            Route::livewire('/master/raw-material/{material}/edit', 'pages::master.raw-material.entry')->name('master.raw-material.edit');
        });

        Route::middleware('module:FINACC')->group(function () {
            Route::livewire('/finance/coa', 'pages::finance.coa')->name('finance.coa');
            Route::livewire('/finance/dp-invoice', 'pages::finance.dp-invoice')->name('finance.dp-invoice');
        });

        Route::middleware('module:PROC')->group(function () {
            Route::livewire('/purchasing/pr', 'pages::pur.pr.index')->name('pur.pr.list');
            Route::livewire('/purchasing/pr/{pr}/edit', 'pages::pur.pr.entry')->name('pur.pr.entry');
            Route::livewire('/purchasing/pr-approval', 'pages::pur.pr.approval')->name('pur.pr.approval');
            Route::livewire('/purchasing/po', 'pages::pur.po.index')->name('pur.po.list');
            Route::livewire('/purchasing/po/{po}/edit', 'pages::pur.po.entry')->name('pur.po.entry');
            Route::livewire('/purchasing/pv', 'pages::pur.pv.index')->name('pur.pv.list');
            Route::livewire('/purchasing/pv/{pv}/edit', 'pages::pur.pv.entry')->name('pur.pv.entry');
        });

        Route::middleware('module:HRM')->group(function () {
            Route::livewire('/hrm/employee', 'pages::master.employee')->name('hrm.employee');
            Route::livewire('/hrm/shift-roster', 'pages::hrm.shift-roster')->name('hrm.shift-roster');
        });
    });
});
