<?php

namespace Tests\Feature;

use App\Models\Sdi\CoOrder;
use App\Models\Sdi\CoOrderExternalCost;
use App\Models\Sdi\CoOrderItem;
use App\Models\Sdi\QuoQuotation;
use App\Models\Sdi\QuoQuotationItem;
use App\Models\Sys\SysConstant;
use Tests\TestCase;

// Pajak dokumen penjualan: tarif dari Settings > Constants (PPN, PPH23_JASA) dan Non PPN = tanpa PPN.
// Tanpa menulis ke database: dokumen disusun di memori, hanya konstanta yang dibaca.
class SalesDocumentTaxTest extends TestCase
{
    private function order(bool $ppn): CoOrder
    {
        $o = new CoOrder(['is_ppn' => $ppn]);
        $o->setRelation('items', collect([
            new CoOrderItem(['line_kind' => 'PRODUCT', 'subtotal' => 1000000]),
            new CoOrderItem(['line_kind' => 'SERVICE', 'subtotal' => 200000]),
        ]));
        $o->setRelation('externalCosts', collect([new CoOrderExternalCost(['nominal' => 50000])]));

        return $o;
    }

    public function test_customer_order_ppn_uses_the_constant_rate(): void
    {
        $rate = SysConstant::number('PPN', 11);
        $t = $this->order(true)->totals();

        $this->assertSame(1250000.0, $t['dpp']);
        $this->assertSame($rate, $t['ppn_pct']);
        $this->assertSame(round(1250000 * $rate / 100, 2), $t['ppn']);
        $this->assertSame($t['dpp'] + $t['ppn'], $t['total_billing']);
    }

    public function test_customer_order_non_ppn_has_no_ppn(): void
    {
        $t = $this->order(false)->totals();

        $this->assertSame(0.0, $t['ppn_pct']);
        $this->assertSame(0.0, $t['ppn']);
        $this->assertSame($t['dpp'], $t['total_billing']);
    }

    public function test_customer_order_pph23_uses_the_constant_rate_on_service_lines(): void
    {
        $rate = SysConstant::number('PPH23_JASA', 2);
        $t = $this->order(false)->totals();

        $this->assertSame($rate, $t['wht23_pct']);
        $this->assertSame(round(200000 * $rate / 100, 2), $t['wht23']);
        $this->assertSame($t['total_billing'] - $t['wht23'], $t['net_receivable']);
    }

    public function test_quotation_ppn_uses_the_constant_rate_for_both_schemes(): void
    {
        $rate = SysConstant::number('PPN', 11);
        $make = function (string $scheme) {
            $q = new QuoQuotation(['tax_scheme' => $scheme]);
            $q->setRelation('items', collect([new QuoQuotationItem(['subtotal' => 1110000])]));

            return $q->totals();
        };

        $excl = $make('EXCL');
        $this->assertSame($rate, $excl['ppn_pct']);
        $this->assertSame(round(1110000 * $rate / 100, 2), $excl['ppn']);
        $this->assertSame(1110000.0 + $excl['ppn'], $excl['grand']);

        $incl = $make('INCL');
        $this->assertSame(1110000.0, $incl['grand']);
        $this->assertSame(round(1110000 / (1 + $rate / 100), 2), $incl['dpp']);
    }
}
