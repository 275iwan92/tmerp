<?php

/*
 * REMARK
 * Tujuan       : Menguji due date CO otomatis: hari kerja (Sabtu kerja, Minggu dan hari besar libur) dan dasar tanggal CO untuk CO direct.
 * Sumber hulu  : App\Support\WorkCalendar::addWorkdays, App\Services\Sales\CoDueDate::suggest (kalender disuntik, tanpa menulis data).
 * Input        : Tanggal tetap Oktober 2026; konstanta CO_LEAD_WORKDAYS dibaca (bawaan 14).
 * Output       : Lulus / gagal.
 * Dipakai oleh : php artisan test --filter=CoDueDateTest
 * Status       : Aktif.
 */

namespace Tests\Feature;

use App\Models\Sdi\CoOrder;
use App\Models\Sdi\CoOrderItem;
use App\Services\Sales\CoDueDate;
use App\Support\WorkCalendar;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class CoDueDateTest extends TestCase
{
    public function test_workdays_skip_sunday_and_holidays_but_count_saturday(): void
    {
        $cal = new WorkCalendar([], [7]);

        $this->assertSame('2026-10-12', $cal->addWorkdays(CarbonImmutable::parse('2026-10-09'), 2)->toDateString()); // Sabtu 1, Senin 2
        $this->assertSame('2026-10-09', $cal->addWorkdays(CarbonImmutable::parse('2026-10-09'), 0)->toDateString());
        $this->assertSame('2026-10-13', (new WorkCalendar(['2026-10-12'], [7]))->addWorkdays(CarbonImmutable::parse('2026-10-09'), 2)->toDateString());
    }

    public function test_direct_co_counts_from_the_co_date(): void
    {
        $item = new CoOrderItem(['line_kind' => 'PRODUCT', 'product_id' => 1]);
        $item->setRelation('order', new CoOrder(['entry_mode' => 'INSTANT', 'co_date' => '2026-10-02']));

        // 14 hari kerja sesudah Jumat 02-Okt-2026, Sabtu kerja: jatuh Senin 19-Okt-2026.
        $this->assertSame('2026-10-19', CoDueDate::suggest($item, new WorkCalendar([], [7]))?->toDateString());
    }
}
