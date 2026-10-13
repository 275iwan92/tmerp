<?php

/*
 * REMARK
 * Tujuan       : Menjaga aturan rotasi Quote of the Day: slot dari jam, kutipan sama dalam satu slot hari itu, tanpa ulang dalam 7 hari.
 * Sumber hulu  : App\Services\Sys\QuoteRotation, tm2sys.sys_quotes dan sys_quote_shown (ddt* di mockup).
 * Input        : user uji 999999 (catatan dibersihkan di akhir tes).
 * Output       : Lulus bila tiga aturan di atas terpenuhi.
 * Dipakai oleh : php artisan test --env=mockup.
 * Status       : Aktif.
 */

namespace Tests\Feature;

use App\Services\Sys\QuoteRotation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuoteRotationTest extends TestCase
{
    private const USER = 999999;

    protected function tearDown(): void
    {
        DB::connection('tm2sys')->table('sys_quote_shown')->where('id_user', self::USER)->delete();
        parent::tearDown();
    }

    public function test_slot_boundaries(): void
    {
        $d = CarbonImmutable::parse('2026-10-13');
        $this->assertSame('MORNING', QuoteRotation::slot($d->setTime(8, 0)));
        $this->assertSame('AFTERNOON', QuoteRotation::slot($d->setTime(13, 0)));
        $this->assertSame('EVENING', QuoteRotation::slot($d->setTime(19, 0)));
    }

    public function test_same_slot_same_day_returns_same_quote(): void
    {
        $now = CarbonImmutable::parse('2026-10-13 09:00');
        $first = QuoteRotation::pick(self::USER, $now);
        $this->assertNotNull($first);
        $this->assertSame($first->id, QuoteRotation::pick(self::USER, $now->setTime(11, 30))->id);
    }

    public function test_no_repeat_within_seven_days(): void
    {
        $seen = [];
        foreach (range(0, 6) as $i) {
            $q = QuoteRotation::pick(self::USER, CarbonImmutable::parse('2026-10-13 09:00')->addDays($i));
            $this->assertNotNull($q);
            $seen[] = $q->id;
        }
        $this->assertCount(7, array_unique($seen));
    }
}
