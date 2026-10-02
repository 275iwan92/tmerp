<?php

namespace Tests\Feature;

use App\Models\Master\CustCustomer;
use App\Models\Prd\QcComplaintReview;
use App\Models\Sdi\CrmActivity;
use App\Models\Sdi\CrmComplaint;
use App\Models\Sdi\QuoActivity;
use App\Models\Sdi\QuoQuotation;
use App\Models\Sys\SecUser;
use App\Support\V2\Data\Crm;
use App\Support\V2\Data\CrmActivities;
use App\Support\V2\Data\CrmComplaints;
use App\Support\V2\Print\PrintDocs;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// CRM (App\Support\V2\Data\Crm*): aktivitas per customer + follow-up, komplain / retur dengan jawaban QA, Customer 360.
// Menulis ke tm2sdi.crm_activity, crm_complaint(+_item) dan tm2prd.qc_complaint_review; semua baris buatan test dihapus di
// tearDown (baris komplain ikut terhapus lewat FK cascade). Dilewati bila tabelnya belum ada.
class CrmTest extends TestCase
{
    private array $activities = [];

    private array $complaints = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::connection('tm2sdi')->hasTable('crm_complaint') || ! Schema::connection('tm2prd')->hasTable('qc_complaint_review')) {
            $this->markTestSkipped('Tabel CRM belum ada di database ini.');
        }
        $this->actingAs(SecUser::query()->where('is_admin', 1)->firstOrFail());
    }

    protected function tearDown(): void
    {
        QcComplaintReview::query()->whereIn('complaint_id', $this->complaints)->delete();
        CrmComplaint::query()->whereKey($this->complaints)->delete();
        CrmActivity::query()->whereKey($this->activities)->delete();
        parent::tearDown();
    }

    private function customer(): CustCustomer
    {
        return CustCustomer::query()->where('activate', true)->orderBy('id_customer')->firstOrFail();
    }

    private function activity(int $customerId, array $in, ?int $from = null): CrmActivity
    {
        $origin = $from ? CrmActivity::query()->find($from) : null;
        $a = CrmActivities::save(CrmActivities::blank($customerId, $origin), $in + ['activity_type' => 'CALL', 'topic' => 'ORDER', 'activity_date' => now()->toDateString()], $from);
        $this->activities[] = $a->id_activity;

        return $a;
    }

    public function test_follow_up_is_coloured_by_due_date_and_closed_by_logging_its_result(): void
    {
        $c = $this->customer();
        $a = $this->activity($c->id_customer, ['notes' => 'TEST crm follow-up', 'next_action_date' => now()->subDays(3)->toDateString(), 'next_action' => 'Call again']);
        $this->assertTrue($a->followUpOpen());
        $this->assertSame($c->id_customer, (int) $a->customer_id);

        $row = collect(CrmActivities::rows($c->id_customer))->firstWhere('id', $a->id_activity);
        $this->assertSame('OVERDUE', $row['bucket']);
        $this->assertSame('danger', $row['row_tone']);
        $mine = collect(Crm::rows())->firstWhere('id', $c->id_customer);
        $this->assertNotNull($mine['next_date'], 'Customer 360 shows the open follow-up.');

        // Mencatat hasil DARI follow-up itu menutup follow-up asalnya.
        $b = $this->activity($c->id_customer, ['notes' => 'TEST crm result', 'result' => 'POSITIVE'], $a->id_activity);
        $this->assertSame('ORDER', $b->topic);
        $this->assertTrue((bool) $a->refresh()->next_done);
        $this->assertSame('DONE', collect(CrmActivities::rows($c->id_customer))->firstWhere('id', $a->id_activity)['bucket']);

        CrmActivities::done($a, false);
        $this->assertTrue($a->refresh()->followUpOpen());
        // Tanggal follow-up dikosongkan = tidak ada follow-up lagi.
        CrmActivities::save($a, ['activity_type' => 'CALL', 'topic' => 'ORDER', 'activity_date' => $a->activity_date->toDateString(), 'next_action_date' => '']);
        $this->assertFalse($a->refresh()->followUpOpen());
        $this->assertNull($a->next_action);
    }

    public function test_quotation_follow_up_takes_the_customer_of_its_quotation(): void
    {
        $q = QuoQuotation::query()->orderBy('id_quotation')->first();
        if (! $q) {
            $this->markTestSkipped('Belum ada quotation di database ini.');
        }
        $a = QuoActivity::create(['quotation_id' => $q->id_quotation, 'activity_type' => 'CALL', 'activity_date' => now()->toDateString(), 'notes' => 'TEST crm quotation', 'created_by' => 'test']);
        $this->activities[] = $a->id_activity;
        $this->assertSame((int) $q->customer_id, (int) $a->customer_id);
        $this->assertSame('QUOTATION', $a->refresh()->topic);
    }

    public function test_complaint_goes_to_qa_and_is_closed_by_sales_after_the_answer(): void
    {
        $c = $this->customer();
        $doc = CrmComplaints::create($c->id_customer, 'RETURN');
        $this->complaints[] = $doc->id_complaint;
        $this->assertSame('DRAFT', $doc->status());
        $this->assertNotNull(CrmComplaints::act($doc, 'confirm'), 'A complaint without description and products must not be confirmed.');

        $base = ['complaint_date' => now()->toDateString(), 'category' => 'QUALITY', 'description' => 'TEST crm complaint', 'requested_action' => 'REPLACE'];
        CrmComplaints::save($doc, $base + ['items' => ['new1' => ['product_name' => 'TEST BOX', 'qty_complaint' => '100', 'qty_return' => '150', 'qty_unit' => 'pcs']]]);
        $doc = CrmComplaints::find($doc->id_complaint);
        $this->assertCount(1, $doc->items);
        $this->assertNotNull(CrmComplaints::act($doc, 'confirm'), 'Returned quantity above the complained quantity must be refused.');

        $item = $doc->items->first();
        CrmComplaints::save($doc, $base + ['items' => [$item->id_complaint_item => ['product_name' => 'TEST BOX', 'qty_complaint' => '100', 'qty_return' => '40', 'qty_unit' => 'pcs']]]);
        $this->assertNull(CrmComplaints::act(CrmComplaints::find($doc->id_complaint), 'confirm'));
        $doc = CrmComplaints::find($doc->id_complaint);
        $this->assertSame('CONFIRMED', $doc->status());
        $this->assertStringStartsWith('CC', $doc->complaint_no);
        $this->assertTrue($doc->needs_qa);
        $this->assertSame('WAITING', $doc->qaStage());
        $this->assertContains($doc->id_complaint, array_column(CrmComplaints::reviewRows(), 'id'));

        // Sales tidak bisa menutup sebelum QA menjawab.
        CrmComplaints::save($doc, ['resolution' => 'REPLACED', 'resolution_note' => 'TEST']);
        $this->assertNotNull(CrmComplaints::act(CrmComplaints::find($doc->id_complaint), 'close'));

        // QA: Confirm butuh keputusan + akar masalah, dan nasib barang yang kembali.
        $this->assertNotNull(CrmComplaints::saveReview($doc, ['verdict' => 'ACCEPTED'], true));
        $doc = CrmComplaints::find($doc->id_complaint);
        $this->assertNotNull(CrmComplaints::saveReview($doc, ['verdict' => 'ACCEPTED', 'root_cause' => 'TEST cause'], true));
        $doc = CrmComplaints::find($doc->id_complaint);
        $this->assertNull(CrmComplaints::saveReview($doc, ['verdict' => 'ACCEPTED', 'root_cause' => 'TEST cause', 'disposition' => 'REWORK'], true));
        $doc = CrmComplaints::find($doc->id_complaint);
        $this->assertSame('ANSWERED', $doc->qaStage());
        $this->assertNotNull(CrmComplaints::saveReview($doc, ['verdict' => 'REJECTED'], false), 'A confirmed QA answer cannot be changed.');

        $print = PrintDocs::make('customer-complaint', $doc->id_complaint);
        $this->assertSame($doc->complaint_no, $print->data['number']);
        $this->assertSame('Sales Return', $print->title);

        $this->assertNull(CrmComplaints::act($doc, 'close'));
        $doc = CrmComplaints::find($doc->id_complaint);
        $this->assertSame('CLOSED', $doc->status());
        $this->assertNotNull(CrmComplaints::act($doc, 'cancel', 'too late'), 'A closed complaint cannot be cancelled.');
    }

    public function test_complaint_without_qa_is_cancelled_with_a_reason_and_draft_can_be_deleted(): void
    {
        $c = $this->customer();
        $doc = CrmComplaints::create($c->id_customer, 'COMPLAINT');
        $this->complaints[] = $doc->id_complaint;
        CrmComplaints::save($doc, ['category' => 'DELIVERY', 'description' => 'TEST crm late', 'items' => ['new1' => ['product_name' => 'TEST BOX', 'qty_complaint' => '10']]]);
        $doc = CrmComplaints::find($doc->id_complaint);
        $this->assertFalse($doc->needs_qa);
        $this->assertNull(CrmComplaints::act($doc, 'confirm'));
        $doc = CrmComplaints::find($doc->id_complaint);
        $this->assertSame('NONE', $doc->qaStage());
        $this->assertNotContains($doc->id_complaint, array_column(CrmComplaints::reviewRows(), 'id'));
        $this->assertNull(CrmComplaints::reviewable($doc->id_complaint));
        $this->assertNotNull(CrmComplaints::act($doc, 'cancel', ''), 'Cancel needs a reason.');
        $this->assertNull(CrmComplaints::act($doc, 'cancel', 'Wrong customer'));
        $this->assertSame('CANCELLED', CrmComplaints::find($doc->id_complaint)->status());

        $draft = CrmComplaints::create($c->id_customer, 'COMPLAINT');
        $this->complaints[] = $draft->id_complaint;
        CrmComplaints::delete($draft);
        $this->assertNull(CrmComplaint::query()->find($draft->id_complaint));
    }

    public function test_customer_360_profile_reads_one_customer(): void
    {
        $c = Crm::find($this->customer()->id_customer);
        $p = Crm::profile($c);
        $this->assertCount(24, $p['chart']['labels']);
        $this->assertContains($p['summary']['segment'], array_keys(Crm::SEGMENT_TONES));
        foreach (['contacts', 'activities', 'quotations', 'orders', 'deliveries', 'complaints', 'products'] as $key) {
            $this->assertIsArray($p[$key]);
        }
    }
}
