<?php

/*
 * REMARK
 * Tujuan       : Menjaga pencocokan nama sistem lama -> sec_user: persis hanya bila sama setelah huruf besar/kecil + spasi dinormalisasi dan tunggal dua arah; sisanya meragukan atau tidak ada. Tes murni tanpa database.
 * Sumber hulu  : App\Services\Security\LegacyNameMatcher.
 * Input        : Nama contoh fiktif.
 * Output       : Lulus bila persis/ragu/tidak ada terbagi benar.
 * Dipakai oleh : php artisan test --filter=LegacyNameMatcherTest
 * Status       : Aktif, SEMENTARA UAT.
 */

namespace Tests\Unit;

use App\Services\Security\LegacyNameMatcher as M;
use PHPUnit\Framework\TestCase;

class LegacyNameMatcherTest extends TestCase
{
    public function test_normalize_only_case_and_spaces(): void
    {
        $this->assertSame('budi contoh', M::normalize("  BUDI   Contoh \t"));
        $this->assertNotSame(M::normalize('Budi C.'), M::normalize('Budi C'), 'tanda baca tidak dibuang');
    }

    public function test_exact_single_pairs_only(): void
    {
        $r = M::match([1 => 'Budi Contoh', 2 => 'siti  fiktif'], [10 => 'BUDI CONTOH ', 11 => 'Siti Fiktif', 12 => 'Orang Lain']);
        $this->assertSame([1 => 10, 2 => 11], $r['exact']);
        $this->assertSame([], $r['doubtful']);
    }

    public function test_duplicate_names_are_doubtful_not_applied(): void
    {
        $r = M::match([1 => 'Andi Fiktif', 2 => 'andi fiktif', 3 => 'Dewi Contoh'], [10 => 'Andi Fiktif', 11 => 'Dewi Contoh', 12 => 'dewi contoh']);
        $this->assertSame([], $r['exact']);
        $this->assertSame([[10], 'nama kembar di target'], $r['doubtful'][1]);
        $this->assertSame([[11, 12], 'nama kembar di sumber'], $r['doubtful'][3]);
    }

    public function test_similar_names_are_doubtful(): void
    {
        $r = M::match([1 => 'Budi Contoh', 2 => 'Rina Fiktif Sari', 3 => 'Hendrawan Fiktif', 4 => 'Tidak Ada'],
            [10 => 'Contoh Budi', 11 => 'Rina Fiktif', 12 => 'Hendrawan Fiktip', 13 => 'Lain Sekali']);
        $this->assertSame([], $r['exact']);
        $this->assertSame([10], $r['doubtful'][1][0]);
        $this->assertSame([11], $r['doubtful'][2][0]);
        $this->assertSame([12], $r['doubtful'][3][0]);
        $this->assertSame([4], $r['none']);
    }

    public function test_punctuation_difference_is_doubtful(): void
    {
        $r = M::match([1 => 'Budi C.'], [10 => 'Budi C']);
        $this->assertSame([], $r['exact']);
        $this->assertSame([10], $r['doubtful'][1][0]);
    }

    public function test_exact_source_is_not_offered_as_candidate_elsewhere(): void
    {
        $r = M::match([1 => 'Rina Fiktif', 2 => 'Rina Fiktif Sari'], [10 => 'Rina Fiktif']);
        $this->assertSame([1 => 10], $r['exact']);
        $this->assertSame([2], $r['none']);
    }
}
