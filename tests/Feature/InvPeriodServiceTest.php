<?php

namespace Tests\Feature;

use App\Models\Inventory\InvPeriod;
use App\Services\Inventory\Valuation\InvPeriodService;
use App\Services\Inventory\Valuation\ValuationRefused;
use Tests\TestCase;

// Tutup / buka periode inventory. Memakai periode tiruan (190001, 209901) supaya periode sungguhan di database dummy tidak berubah.
// Jalankan terhadap database dummy: powershell -NoProfile -File tools/test-ddt.ps1 tests/Feature/InvPeriodServiceTest.php
class InvPeriodServiceTest extends TestCase
{
    private const USER = 'periodtest';

    protected function tearDown(): void
    {
        InvPeriod::query()->where('created_by', self::USER)->delete();
        parent::tearDown();
    }

    private function fake(string $code): void
    {
        InvPeriod::create(['i_period_code' => $code, 'i_date_start' => substr($code, 0, 4).'-'.substr($code, 4).'-01', 'i_date_end' => substr($code, 0, 4).'-'.substr($code, 4).'-28',
            'i_status' => 'OPEN', 'created_by' => self::USER]);
    }

    public function test_an_oldest_period_closes_and_opens_again(): void
    {
        $this->fake('190001');
        $service = app(InvPeriodService::class);

        $closed = $service->close('190001', self::USER);
        $this->assertSame(['CLOSED', self::USER], [$closed->i_status, $closed->c_closed_by]);

        $open = $service->open('190001', self::USER);
        $this->assertSame(['OPEN', null], [$open->i_status, $open->c_closed_by]);
    }

    public function test_a_period_cannot_close_while_an_earlier_one_is_open(): void
    {
        $this->fake('209901');

        $this->expectException(ValuationRefused::class);
        app(InvPeriodService::class)->close('209901', self::USER);
    }

    public function test_only_a_closed_period_can_be_opened(): void
    {
        $this->fake('190002');

        $this->expectException(ValuationRefused::class);
        app(InvPeriodService::class)->open('190002', self::USER);
    }

    public function test_create_next_adds_the_month_after_the_last_period(): void
    {
        $this->fake('209901');
        $next = app(InvPeriodService::class)->createNext(self::USER);

        $this->assertSame('209902', $next->i_period_code);
        $this->assertSame('2099-02-28', $next->i_date_end->toDateString());
    }
}
