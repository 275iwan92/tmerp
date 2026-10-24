<?php

/*
 * REMARK
 * Tujuan       : Menjaga gerbang layar Default Passwords (SEMENTARA UAT): hanya login di daftar password_viewers, tanpa beda huruf besar/kecil dan spasi; daftar kosong = tak seorang pun. Tes murni tanpa database dan tanpa boot aplikasi.
 * Sumber hulu  : App\Services\Security\DefaultPasswordViewer::permits.
 * Input        : Login contoh fiktif dan daftar "a,b".
 * Output       : Lulus bila hanya login dalam daftar yang diizinkan.
 * Dipakai oleh : php artisan test --filter=DefaultPasswordViewerGateTest
 * Status       : Aktif, SEMENTARA (dihapus bersama layar setelah UAT).
 */

namespace Tests\Unit;

use App\Services\Security\DefaultPasswordViewer;
use PHPUnit\Framework\TestCase;

class DefaultPasswordViewerGateTest extends TestCase
{
    public function test_only_listed_logins_pass_case_insensitive(): void
    {
        $list = ' Budi.Contoh , siti ';
        $this->assertTrue(DefaultPasswordViewer::permits('budi.contoh', $list));
        $this->assertTrue(DefaultPasswordViewer::permits('SITI', $list));
        $this->assertFalse(DefaultPasswordViewer::permits('andi', $list));
        $this->assertFalse(DefaultPasswordViewer::permits('budi', $list), 'bukan awalan');
    }

    public function test_empty_list_or_login_means_nobody(): void
    {
        foreach ([null, '', ' , '] as $list) {
            $this->assertFalse(DefaultPasswordViewer::permits('budi.contoh', $list));
        }
        $this->assertFalse(DefaultPasswordViewer::permits('', 'budi.contoh'));
        $this->assertFalse(DefaultPasswordViewer::permits(null, 'budi.contoh'));
    }
}
