<?php

namespace Tests\Feature;

use App\Models\Sdi\CoOrder;
use App\Models\Sdi\QuoQuotation;
use App\Models\Sys\SecUser;
use App\Support\V2\Print\QuotationPrint;
use Tests\TestCase;

// Cetak Quotation v2 (pengganti pages::quotation.print): hanya PUBLISHED yang bisa dicetak, selalu A4, kop memuat Revision, dan membawa
// lembar lampiran Terms & Alternatives (+ Product Mockup bila inquiry punya part). Cetak CO hanya untuk CO CONFIRMED (aturan cetak v1).
// Hanya membaca data yang sudah ada; tidak menulis ke database.
class QuotationPrintV2Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
    }

    public function test_published_quotation_prints_on_a4_with_appendix_sheets(): void
    {
        $q = QuoQuotation::where('doc_status', 'PUBLISHED')->orderByDesc('id_quotation')->firstOrFail();

        $html = $this->get('/v2/print/quotation/'.$q->id_quotation)->assertOk()->getContent();

        $this->assertStringContainsString($q->quotation_no, $html);
        $this->assertStringContainsString('data-paper="a4"', $html);
        $this->assertStringContainsString('dl-pdoc__sheet is-a4 dl-pdoc__appx', $html, 'Terms & Alternatives sheet is always present.');
        $this->assertStringContainsString('dl-pdoc__kv', $html, 'Payment facts block.');
    }

    public function test_appendix_blocks_carry_terms_payment_and_alternatives(): void
    {
        $withAlt = null;
        foreach (QuoQuotation::where('doc_status', 'PUBLISHED')->orderByDesc('id_quotation')->limit(40)->pluck('id_quotation') as $id) {
            $blocks = QuotationPrint::load($id)->appendices()[0]['blocks'];
            $this->assertContains('facts', array_column($blocks, 'kind'));
            $this->assertTrue(count(array_intersect(['list', 'text'], array_column($blocks, 'kind'))) === 1, 'Terms: default list, or the quotation notes as text.');
            $withAlt ??= collect($blocks)->firstWhere('kind', 'table');
        }
        if ($withAlt) {
            $this->assertSame(4, count($withAlt['head']));
            $this->assertNotEmpty($withAlt['rows']);
        }
    }

    public function test_only_published_quotation_and_confirmed_co_can_be_printed(): void
    {
        if ($q = QuoQuotation::where('doc_status', '!=', 'PUBLISHED')->first()) {
            $this->get('/v2/print/quotation/'.$q->id_quotation)->assertForbidden();
        }
        if ($o = CoOrder::where('doc_status', '!=', 'CONFIRMED')->first()) {
            $this->get('/v2/print/customer-order/'.$o->id_order)->assertForbidden();
        }
        if ($o = CoOrder::where('doc_status', 'CONFIRMED')->first()) {
            $this->get('/v2/print/customer-order/'.$o->id_order)->assertOk();
        }
    }
}
