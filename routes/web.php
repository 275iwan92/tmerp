<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/menu');

Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'pages::auth.login')->name('login');
    Route::livewire('/register', 'pages::auth.register')->name('register');
    Route::livewire('/forgot-password', 'pages::auth.forgot-password')->name('password.request');
});

Route::middleware('auth')->group(function () {
    Route::livewire('/menu', 'pages::main-menu')->name('menu');

    Route::middleware('module:INQUIRY')->group(function () {
        Route::livewire('/inquiry', 'pages::inquiry.hub')->name('inquiry.hub');
        Route::livewire('/inquiry/new', 'pages::inquiry.form')->name('inquiry.form');
        Route::livewire('/inquiry/recalc', 'pages::inquiry.recalc')->name('inquiry.recalc');
        Route::livewire('/inquiry/revise', 'pages::inquiry.revise')->name('inquiry.revise');
    });

    Route::livewire('/security', 'pages::security.index')->name('security.index')->middleware('admin');
});
