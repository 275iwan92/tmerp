<?php

/*
 * REMARK
 * Tujuan       : Kelas dasar semua tes: CSRF dimatikan supaya tes POST berjalan di lingkungan mockup (--env=mockup) tanpa 419.
 *                assertArchived(): berkas usang harus ada di salah satu ZIP tmerp_unused (aturan D.26).
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

    // Berkas v1 yang diarsipkan ada sebagai entri di tmerp_unused/*.zip, bukan berkas lepas (aturan D.26).
    protected function assertArchived(string $entry): void
    {
        foreach (glob(base_path('tmerp_unused/*.zip')) ?: [] as $file) {
            $zip = new \ZipArchive;
            if ($zip->open($file) === true && $zip->locateName($entry) !== false) {
                $zip->close();
                $this->assertFileDoesNotExist(base_path('tmerp_unused/'.$entry));

                return;
            }
        }

        $this->fail('Tidak ada di ZIP arsip: '.$entry);
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
