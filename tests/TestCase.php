<?php

/*
 * REMARK
 * Tujuan       : Kelas dasar semua tes: CSRF dimatikan supaya tes POST berjalan di lingkungan mockup (--env=mockup) tanpa 419.
 *                Setelah tiap tes, saran ProCost (iqp_costing_recommendation) milik costing yang sudah dihapus tes ikut dibersihkan.
 * Sumber hulu  : bootstrap/app.php (middleware PreventRequestForgery aktif di semua lingkungan).
 * Input        : -
 * Output       : Permintaan tes tidak diperiksa token CSRF-nya; semua aturan lain (auth, modul) tetap berlaku.
 * Dipakai oleh : seluruh kelas di tests/Feature dan tests/Unit.
 * Status       : Aktif.
 */

namespace Tests;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    protected function tearDown(): void
    {
        // Hitung ProCost mencatat bahan belajar; tes yang menghapus costing-nya tidak tahu tabel ini.
        try {
            $sdi = DB::connection('tm2sdi');
            $sdi->table('iqp_costing_recommendation')->whereNotIn('costing_id', $sdi->table('iqp_costing')->select('id_costing'))->delete();
        } catch (\Throwable) {
            // tabel belum ada di lingkungan ini
        }

        parent::tearDown();
    }
}
