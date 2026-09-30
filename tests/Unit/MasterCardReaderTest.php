<?php

namespace Tests\Unit;

use App\Services\MasterCard\MasterCardReader;
use PHPUnit\Framework\TestCase;

// Pembaca Excel master card (sheet "MC") terhadap 5 kartu asli di docs/attachments (tanpa DB).
class MasterCardReaderTest extends TestCase
{
    private function read(string $file): array
    {
        return (new MasterCardReader)->read(__DIR__.'/../../docs/attachments/'.$file, $file);
    }

    public function test_reads_identity_sizes_and_knife_in_mm(): void
    {
        $c = $this->read('MC-Osima MS N100 CV (DS003146).xlsx');
        $this->assertSame(['SJJ-OSM N100 CV DS3146', 'DS003146', 'Osima MS N100 CV', '2026-03-14', 'PT. SELATAN JADI JAYA'],
            [$c['card_code'], $c['design_no'], $c['product_name'], $c['card_date'], $c['customer_text']]);
        $this->assertSame([413.0, 180.0, 260.0, 623.0, 452.0], [$c['dim_l'], $c['dim_w'], $c['dim_h'], $c['knife_w'], $c['knife_h']]);
        $this->assertSame(['Double Join', '1 UP'], [$c['join_text'], $c['up_text']]);
        $this->assertCount(2, $c['materials']);
        $this->assertSame('M125/M125/X3/K125', $c['materials'][1]['quality']);
        $this->assertCount(11, $c['process']);
        $this->assertSame(['BLACK', 'CYAN', 'MAGENTA', 'YELLOW'], $c['colors']['first']);
        $this->assertSame('Pak Habibi', $c['acc']['acc_by']);
        $this->assertSame(['PROOF', 'CUT_PLAN'], array_column($c['images'], 'kind'));
    }

    public function test_messy_numbers_part_label_and_missing_design_no(): void
    {
        $yama = $this->read('MC-YAMA Din170 MF (DS003026) B.xlsx');
        $this->assertSame([257.0, 'B', '1/2 UP'], [$yama['dim_h'], $yama['part_label'], $yama['up_text']]); // "25, 7 cm"

        $outer = $this->read('MC-OUTER ALSAMSI CREAM LOCK.xlsx');
        $this->assertNull($outer['design_no']);
        $this->assertSame(['1 SET', 969.0, 602.0], [$outer['up_text'], $outer['knife_w'], $outer['knife_h']]);

        $yahya = $this->read('MC-Yahya 250g (24pcs) C22 (DS002159) 1020x650mm.xlsx');
        $this->assertSame('8 UP', $yahya['up_text']);
        $this->assertStringContainsString('102x65', $yahya['notes']);
        $this->assertCount(1, $yahya['images']); // gambar ber-anchor khusus tetap terbaca dari zip
    }
}
