<?php

namespace Tests\Feature;

use App\Models\Sdi\IqpCosting;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sys\SecUser;
use App\Support\V2\Print\InquiryNotePrint;
use Tests\TestCase;

// Cetak Nota Inquiry v2 (pengganti pages::inquiry.nota) dan layar daftar nota-inquiry. Hanya membaca data yang ada.
// Aturan inti (Sisi A/B): harga jual tercetak hanya bila costing PUBLISHED dan punya price tier; selain itu hanya spesifikasi.
class InquiryNotePrintTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
    }

    public function test_priced_note_has_tier_prices_and_unpriced_note_has_none(): void
    {
        $pubIds = IqpCosting::where('published_flag', 1)->pluck('inquiry_id');
        $priced = IqpInquiry::whereIn('id_inquiry', $pubIds)->whereHas('parts')->orderByDesc('id_inquiry')->get()->first(fn ($i) => $i->costing?->priceTiers()->exists());
        $unpriced = IqpInquiry::whereNotIn('id_inquiry', $pubIds)->whereHas('parts')->orderByDesc('id_inquiry')->first();
        if (! $priced || ! $unpriced) {
            $this->markTestSkipped('Needs one inquiry with a published costing and one without.');
        }

        $p = InquiryNotePrint::load($priced->id_inquiry);
        $this->assertTrue($p->priced);
        $this->assertSame($priced->tiers()->count(), count($p->rows()));
        $this->assertNotNull($p->rows()[0]['price']);
        $this->assertNotEmpty($p->totals()['lines'], 'Order value of the primary tier.');

        $u = InquiryNotePrint::load($unpriced->id_inquiry);
        $this->assertFalse($u->priced);
        $this->assertSame([], $u->extra);
        $this->assertNull($u->rows()[0]['price'] ?? null, 'No selling price before Direksi publishes the costing.');
        $this->assertSame([], $u->totals()['lines']);
    }

    public function test_appendices_and_the_list_screen(): void
    {
        $inquiry = IqpInquiry::whereHas('parts')->orderByDesc('id_inquiry')->firstOrFail();
        $titles = array_column(InquiryNotePrint::load($inquiry->id_inquiry)->appendices(), 'title');
        $this->assertSame('Product Specification', $titles[0]);
        $this->assertSame('Terms & Customer Confirmation', end($titles));

        $this->get('/v2/s/nota-inquiry')->assertOk();
        $this->get('/v2/print/inquiry-note/'.$inquiry->id_inquiry)->assertOk();
    }
}
