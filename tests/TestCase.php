<?php

/*
 * REMARK
 * Tujuan       : Kelas dasar semua tes: CSRF dimatikan supaya tes POST berjalan di lingkungan mockup (--env=mockup) tanpa 419.
 * Sumber hulu  : bootstrap/app.php (middleware PreventRequestForgery aktif di semua lingkungan).
 * Input        : -
 * Output       : Permintaan tes tidak diperiksa token CSRF-nya; semua aturan lain (auth, modul) tetap berlaku.
 * Dipakai oleh : seluruh kelas di tests/Feature dan tests/Unit.
 * Status       : Aktif.
 */

namespace Tests;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }
}
