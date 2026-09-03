<?php

namespace Tests\Feature;

use App\Models\Master\CustCustomer;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdProduct;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\IqpPart;
use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

// Mode #1 -- link Inquiry(BARU) -> ProDev yang sebelumnya tidak ada sama
// sekali (CLAUDE.md §4 VITAL, gap ditemukan 2026-09-03).
class InquirySendToProdevTest extends TestCase
{
    protected ?int $inquiryId = null;

    protected function tearDown(): void
    {
        if ($this->inquiryId) {
            $partIds = DB::connection('tm2sdi')->table('iqp_part')->where('inquiry_id', $this->inquiryId)->pluck('prod_part_id')->filter();
            $inquiry = IqpInquiry::find($this->inquiryId);
            DB::connection('tm2sdi')->table('iqp_tier')->where('inquiry_id', $this->inquiryId)->delete();
            DB::connection('tm2sdi')->table('iqp_part')->where('inquiry_id', $this->inquiryId)->delete();
            DB::connection('tm2sdi')->table('iqp_inquiry')->where('id_inquiry', $this->inquiryId)->delete();
            ProdPart::whereIn('id_part', $partIds)->delete();
            if ($inquiry && $inquiry->parent_product_id) {
                ProdProduct::whereKey($inquiry->parent_product_id)->delete();
            }
        }

        parent::tearDown();
    }

    public function test_send_to_prodev_creates_product_and_part_and_links_back(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);
        $customer = CustCustomer::firstOrFail();

        $test = Livewire::test('pages::inquiry.form', ['mode' => 'NEW'])
            ->set('inquiry_date', now()->toDateString())
            ->set('customer_id', $customer->id_customer)
            ->set('product_draft_name', 'Test SendToProDev Product')
            ->set('parts.0.part_name', 'Test Part SendToProDev')
            ->set('tiers.0.qty', 1000)
            ->call('save');

        $test->assertHasNoErrors();

        $this->inquiryId = $test->get('inquiry')->id_inquiry;
        $partId = $test->get('parts.0')['id'];
        $this->assertNotNull($partId, 'Part harus punya id setelah save()');

        $test->call('sendToProDev', 0)->assertHasNoErrors();

        $iqpPart = IqpPart::findOrFail($partId);
        $this->assertNotNull($iqpPart->prod_part_id, 'iqp_part.prod_part_id harus terisi setelah sendToProDev');

        $inquiry = IqpInquiry::findOrFail($this->inquiryId);
        $this->assertNotNull($inquiry->parent_product_id, 'iqp_inquiry.parent_product_id harus terisi (prod_product auto-dibuat)');

        $prodPart = ProdPart::findOrFail($iqpPart->prod_part_id);
        $this->assertSame('Test Part SendToProDev', $prodPart->part_name);
        $this->assertSame($inquiry->parent_product_id, $prodPart->product_id);

        // Panggil lagi -- harus idempoten (tidak bikin ProdProduct/ProdPart dobel).
        $test->call('sendToProDev', 0)->assertHasNoErrors();
        $iqpPart->refresh();
        $this->assertSame($prodPart->id_part, $iqpPart->prod_part_id, 'Panggil 2x tidak boleh bikin ProdPart baru');
    }
}
