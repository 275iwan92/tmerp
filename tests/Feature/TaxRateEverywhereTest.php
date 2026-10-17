<?php

/*
 * REMARK
 * Tujuan       : Menguji bahwa tarif PPN di akuntansi, Customer Order, Quotation, dan cetak mengikuti Tax Rate pada tanggal dokumen (10% sebelum 01-Apr-2022, 11% sesudahnya).
 * Sumber hulu  : FinTaxScheme::effectiveRate, ArApService::taxRate, CoOrder::totals, QuoQuotation::totals, TaxRate.
 * Input        : Model tanpa disimpan; tarif dari fin_tax_rate yang ada.
 * Output       : Lulus / gagal; tidak menulis ke database.
 * Dipakai oleh : php artisan test --filter=TaxRateEverywhereTest
 * Status       : Aktif.
 */

namespace Tests\Feature;

use App\Models\Master\FinTaxScheme;
use App\Models\Sdi\CoOrder;
use App\Models\Sdi\QuoQuotation;
use App\Services\Acc\ArApService;
use App\Support\Finance\TaxRate;
use Tests\TestCase;

class TaxRateEverywhereTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TaxRate::flush();
    }

    public function test_ppn_schemes_follow_the_tax_rate_by_date_and_pph_schemes_keep_their_own_rate(): void
    {
        $out = FinTaxScheme::find('PPN_OUT');
        $this->assertEqualsWithDelta(0.10, $out->effectiveRate('2022-03-31'), 1e-9);
        $this->assertEqualsWithDelta(0.11, $out->effectiveRate('2022-04-01'), 1e-9);
        $this->assertEqualsWithDelta(TaxRate::ppn() / 100, FinTaxScheme::find('PPN_IN')->effectiveRate(), 1e-9);
        $this->assertEqualsWithDelta(0.02, FinTaxScheme::find('PPH23')->effectiveRate('2022-03-31'), 1e-9);

        $ar = app(ArApService::class);
        $this->assertEqualsWithDelta(0.10, $ar->taxRate('PPN_IN', '2021-06-15'), 1e-9);
        $this->assertEqualsWithDelta(0.11, $ar->taxRate('PPN_OUT', '2026-10-17'), 1e-9);
    }

    public function test_customer_order_and_quotation_totals_use_the_rate_of_their_own_date(): void
    {
        $co = new CoOrder(['co_date' => '2022-03-15', 'is_ppn' => 1]);
        $this->assertEquals(10, $co->totals()['ppn_pct']);
        $co->co_date = '2026-10-17';
        $this->assertEquals(11, $co->totals()['ppn_pct']);
        $co->is_ppn = 0;
        $this->assertEquals(0, $co->totals()['ppn_pct']);

        $q = new QuoQuotation(['issued_date' => '2022-03-15', 'tax_scheme' => 'EXCL']);
        $this->assertEquals(10, $q->totals()['ppn_pct']);
        $q->issued_date = '2026-10-17';
        $this->assertEquals(11, $q->totals()['ppn_pct']);
    }
}
