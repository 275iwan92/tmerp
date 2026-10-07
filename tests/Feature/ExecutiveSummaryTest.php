<?php

namespace Tests\Feature;

use App\Support\V2\Data\ExecutiveSummary;
use Tests\TestCase;

// Management Reports > Executive Summary v2 (C5): bentuk data untuk objek DevLib; angka dasar dari ManagementReportService (sudah diuji di v1).
class ExecutiveSummaryTest extends TestCase
{
    public function test_unknown_basis_falls_back_to_shipment(): void
    {
        $this->assertSame('shipment', ExecutiveSummary::for(null, 'nonsense', false)->basis);
        $this->assertSame('order', ExecutiveSummary::for(2024, 'order', true)->basis);
        $this->assertSame(2024, ExecutiveSummary::for(2024, 'order', true)->year);
    }

    public function test_default_year_is_the_latest_available(): void
    {
        $es = ExecutiveSummary::for(null, null, false);
        $this->assertSame($es->years()[0] ?? (int) now()->year, $es->year);
    }

    public function test_kpis_are_five_money_cards(): void
    {
        $kpis = ExecutiveSummary::for(2024, 'shipment', false)->kpis();
        $this->assertCount(5, $kpis);
        foreach ($kpis as $k) {
            $this->assertSame('money', $k['format']);
            $this->assertArrayHasKey('value', $k);
        }
    }

    public function test_charts_have_twelve_months_and_four_quarters(): void
    {
        $es = ExecutiveSummary::for(2024, 'order', false);
        $this->assertCount(12, ExecutiveSummary::months());
        $this->assertCount(12, $es->monthlyTrend()['labels']);
        $this->assertCount(2, $es->monthlyTrend()['series']);
        $this->assertSame(['Q1', 'Q2', 'Q3', 'Q4'], $es->quarterly()['labels']);
        $this->assertCount(12, $es->purchaseTrend()['series'][0]['data']);
    }

    public function test_shipment_warning_only_before_price_recording_year(): void
    {
        $this->assertTrue(ExecutiveSummary::for(2020, 'shipment', false)->shipmentDataWarning());
        $this->assertFalse(ExecutiveSummary::for(2020, 'order', false)->shipmentDataWarning());
    }
}
