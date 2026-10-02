<?php

namespace Tests\Feature;

use App\Models\Sdi\CoOrderDelivery;
use App\Services\Sales\CpdelPromiseService as Promise;
use App\Support\WorkCalendar;
use Carbon\CarbonImmutable;
use Tests\TestCase;

// Warna baris CPDEL menurut jatuh tempo slot (user 2026-10-02): Sabtu kerja, Minggu + hari besar tidak dihitung.
// Tidak menulis ke database: kalender diberi lewat argumen, baris kirim tidak disimpan.
class CpdelDueColourTest extends TestCase
{
    private function line(?string $date, string $status = 'PLANNED'): CoOrderDelivery
    {
        return (new CoOrderDelivery)->forceFill(['ship_date' => $date, 'status' => $status]);
    }

    public function test_working_days_count_saturday_and_skip_sunday_and_holidays(): void
    {
        $cal = new WorkCalendar(['2026-10-06'], [7]);
        $fri = CarbonImmutable::parse('2026-10-02');

        $this->assertSame(1, $cal->workdaysBetween($fri, CarbonImmutable::parse('2026-10-03')));   // Sabtu kerja
        $this->assertSame(1, $cal->workdaysBetween($fri, CarbonImmutable::parse('2026-10-04')));   // Minggu tidak dihitung
        $this->assertSame(2, $cal->workdaysBetween($fri, CarbonImmutable::parse('2026-10-05')));   // Senin
        $this->assertSame(2, $cal->workdaysBetween($fri, CarbonImmutable::parse('2026-10-06')));   // Selasa = hari besar
        $this->assertSame(3, $cal->workdaysBetween($fri, CarbonImmutable::parse('2026-10-07')));
        $this->assertSame(0, $cal->workdaysBetween($fri, $fri));
    }

    public function test_row_tone_follows_the_customer_slot(): void
    {
        $cal = new WorkCalendar([], [7]);
        $today = CarbonImmutable::parse('2026-10-02');
        $due = fn (?string $date, string $status = 'PLANNED', int $soon = 2) => Promise::due($this->line($date, $status), $cal, $soon, $today);

        $this->assertSame(['OVERDUE', 'danger'], [$due('2026-09-29')['bucket'], $due('2026-09-29')['row_tone']]);
        $this->assertStringContainsString('3', $due('2026-09-29')['due']);
        $this->assertSame(['TODAY', 'tangerine', 1], [$due('2026-10-02')['bucket'], $due('2026-10-02')['row_tone'], $due('2026-10-02')['week']]);
        $this->assertSame(['SOON', 'yellow'], [$due('2026-10-03')['bucket'], $due('2026-10-03')['row_tone']]);
        $this->assertSame(['SOON', 'yellow'], [$due('2026-10-05')['bucket'], $due('2026-10-05')['row_tone']]);          // Senin = H-2
        $this->assertSame(['LATER', null, 1], [$due('2026-10-06')['bucket'], $due('2026-10-06')['row_tone'], $due('2026-10-06')['week']]);
        $this->assertSame(['SOON', 'yellow'], [$due('2026-10-06', 'PLANNED', 3)['bucket'], $due('2026-10-06', 'PLANNED', 3)['row_tone']]);   // batas diubah user
        $this->assertSame(0, $due('2026-10-15')['week']);
        $this->assertSame(['SHIPPED', 'muted'], [$due('2026-09-29', 'SHIPPED')['bucket'], $due('2026-09-29', 'SHIPPED')['row_tone']]);
    }
}
