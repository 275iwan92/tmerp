<?php

namespace Tests\Feature;

use App\Models\Master\CustCustomer;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\QuoActivity;
use App\Models\Sdi\QuoQuotation;
use App\Models\Sdi\QuoQuotationItem;
use App\Models\Sys\SecUser;
use App\Services\Sales\QuotationEntryService as Svc;
use App\Support\V2\Data\Quotation as QuotationData;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// Aturan entri Quotation (dipindah dari komponen v1 pages::quotation.entry). Menulis ke tm2sdi.quo_quotation(+item/cc/activity);
// quotation buatan test dihapus di tearDown. Aturan inti: hanya DRAFT yang bisa diubah, subtotal dihitung ulang, Publish perlu baris.
class QuotationEntryServiceTest extends TestCase
{
    private ?QuoQuotation $q = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(SecUser::query()->where('is_admin', 1)->firstOrFail());
        $this->q = QuoQuotation::create([
            'quotation_no' => 'QUO-TEST-ENTRY', 'customer_id' => CustCustomer::query()->where('activate', true)->value('id_customer'),
            'issued_date' => '2026-10-01', 'valid_until' => '2026-10-31', 'deadline_date' => '2026-10-15', 'tax_scheme' => 'EXCL', 'currency_code' => 'IDR',
            'doc_status' => 'DRAFT', 'revision_no' => 0, 'created_by' => 'test', 'updated_by' => 'test',
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->q) {
            QuoActivity::query()->where('quotation_id', $this->q->id_quotation)->delete();
            $this->q->items()->delete();
            $this->q->ccs()->delete();
            $this->q->delete();
        }
        parent::tearDown();
    }

    public function test_header_is_written_only_for_allowed_fields_while_draft(): void
    {
        Svc::updateHeader($this->q, 'payment_term', 'NET30');
        Svc::updateHeader($this->q, 'doc_status', 'PUBLISHED');
        $row = QuoQuotation::find($this->q->id_quotation);
        $this->assertSame('NET30', $row->payment_term);
        $this->assertSame('DRAFT', $row->doc_status);

        QuoQuotation::whereKey($this->q->id_quotation)->update(['doc_status' => 'PUBLISHED']);
        Svc::updateHeader($this->q->refresh(), 'payment_term', 'COD');
        $this->assertSame('NET30', QuoQuotation::find($this->q->id_quotation)->payment_term, 'Not editable once published.');
    }

    public function test_free_line_subtotal_and_publish_rule(): void
    {
        Svc::publish($this->q);
        $this->assertSame('DRAFT', QuoQuotation::find($this->q->id_quotation)->doc_status, 'No line, no publish.');

        Svc::addFreeform($this->q);
        $item = QuoQuotationItem::where('quotation_id', $this->q->id_quotation)->firstOrFail();
        Svc::updateItem($this->q, $item->id_item, 'qty', '10');
        Svc::updateItem($this->q, $item->id_item, 'unit_price', '2500');
        Svc::updateItem($this->q, $item->id_item, 'discount_pct', '10');
        $this->assertEquals(22500, (float) $item->refresh()->subtotal);
        Svc::updateItem($this->q, $item->id_item, 'subtotal', '1');
        $this->assertEquals(22500, (float) $item->refresh()->subtotal, 'Subtotal is not a writable field.');

        Svc::publish($this->q);
        $this->assertSame('PUBLISHED', QuoQuotation::find($this->q->id_quotation)->doc_status);
        Svc::removeItem($this->q->refresh(), $item->id_item);
        $this->assertSame(1, QuoQuotationItem::where('quotation_id', $this->q->id_quotation)->count(), 'Published quotation keeps its lines.');
    }

    public function test_cc_excludes_the_attn_person_and_keeps_pick_order(): void
    {
        Svc::updateHeader($this->q, 'attn_contact', 'Budi');
        Svc::updateCc($this->q, ['Sari', 'Budi', 'Andi', 'Sari']);
        $this->assertSame(['Sari', 'Andi'], $this->q->ccs()->pluck('contact_name')->all());

        Svc::updateHeader($this->q, 'attn_contact', 'Andi');
        $this->assertSame(['Sari'], $this->q->ccs()->pluck('contact_name')->all(), 'Choosing Attn removes that person from CC.');
    }

    public function test_follow_up_validation_and_owner_only_delete(): void
    {
        try {
            Svc::saveActivity($this->q, ['activity_type' => 'NOPE', 'activity_date' => '']);
            $this->fail('Validation expected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('act.activity_type', $e->errors());
            $this->assertArrayHasKey('act.activity_date', $e->errors());
        }

        Svc::saveActivity($this->q, ['activity_type' => 'CALL', 'activity_date' => '2026-10-02', 'notes' => 'TEST', 'result' => '']);
        $act = QuoActivity::where('quotation_id', $this->q->id_quotation)->firstOrFail();
        $this->assertNull($act->result);

        QuoActivity::whereKey($act->getKey())->update(['created_by' => 'someone-else']);
        Svc::deleteActivity($this->q, $act->getKey());
        $this->assertSame(1, QuoActivity::where('quotation_id', $this->q->id_quotation)->count(), 'Only the creator can delete a follow-up.');
    }

    public function test_mark_lost_needs_published_and_a_reason_and_marks_inquiries(): void
    {
        $inquiry = IqpInquiry::query()->whereNotIn('sub_state', ['CANCELLED', 'CONVERTED', 'LOST'])->first();
        if (! $inquiry) {
            $this->markTestSkipped('No open inquiry in this database.');
        }
        $item = QuoQuotationItem::create(['quotation_id' => $this->q->id_quotation, 'line_seq' => 1, 'source_type' => 'INQUIRY_TIER', 'inquiry_id' => $inquiry->id_inquiry,
            'description' => 'TEST', 'qty' => 1, 'qty_unit' => 'Pcs', 'unit_price' => 1, 'discount_pct' => 0, 'subtotal' => 1, 'created_by' => 'test', 'updated_by' => 'test']);
        $before = [$inquiry->sub_state, $inquiry->cancel_reason];

        try {
            Svc::markLost($this->q, 'x');
            $this->assertSame($before[0], IqpInquiry::find($inquiry->id_inquiry)->sub_state, 'A draft cannot be marked lost.');

            QuoQuotation::whereKey($this->q->id_quotation)->update(['doc_status' => 'PUBLISHED']);
            $this->q->refresh();
            try {
                Svc::markLost($this->q, '');
                $this->fail('Reason required.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('lostReason', $e->errors());
            }

            Svc::markLost($this->q, 'TEST harga kalah');
            $this->assertSame('LOST', IqpInquiry::find($inquiry->id_inquiry)->sub_state);
        } finally {
            IqpInquiry::whereKey($inquiry->id_inquiry)->update(['sub_state' => $before[0], 'cancel_reason' => $before[1]]);
            $item->delete();
        }
    }
    // Jalur layar v2: Data\Quotation::save memanggil service per field yang berubah (Berlaku s/d ikut tanggal dokumen).
    public function test_v2_save_applies_changed_fields_through_the_service(): void
    {
        QuotationData::addLine($this->q);
        $id = QuoQuotationItem::where('quotation_id', $this->q->id_quotation)->value('id_item');

        QuotationData::save($this->q->refresh(), ['issued_date' => '2026-12-01', 'notes' => 'TEST catatan'], ['Sari'], [$id => ['description' => 'Box', 'qty' => '5', 'unit_price' => '200']]);

        $row = QuoQuotation::find($this->q->id_quotation);
        $this->assertSame('2026-12-01', $row->issued_date->toDateString());
        $this->assertSame('TEST catatan', $row->notes);
        $this->assertSame(['Sari'], $row->ccs()->pluck('contact_name')->all());
        $this->assertEquals(1000, (float) QuoQuotationItem::find($id)->subtotal);

        QuotationData::confirm($row);
        $this->assertSame('PUBLISHED', QuoQuotation::find($this->q->id_quotation)->doc_status);
    }
    public function test_option_lists_for_the_form(): void
    {
        Svc::updateHeader($this->q, 'attn_contact', 'Budi');
        Svc::updateHeader($this->q, 'sales_pic', 'Sales Uji');
        $this->q->load('customer', 'ccs');
        $this->assertArrayHasKey('Budi', Svc::contactOptions($this->q));
        $this->assertNotContains('Budi', array_column(Svc::ccOptions($this->q), 'value'), 'The Attn person is not offered as CC.');
        $this->assertArrayHasKey('Sales Uji', Svc::salesOptions($this->q));
    }
}