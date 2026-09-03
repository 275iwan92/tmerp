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

        // Sebelum CONFIRM -- tombol/aksi Kirim ke ProDev harus ditolak
        // (guard baru: Inquiry draft tidak boleh mengalir ke ProDev).
        $test->call('sendToProDev', 0);
        $iqpPartBeforeConfirm = IqpPart::find($partId);
        $this->assertNull($iqpPartBeforeConfirm->prod_part_id, 'sendToProDev SEBELUM confirm() harus ditolak, prod_part_id harus tetap null');

        $test->call('confirm')->assertHasNoErrors();

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

        // Panel referensi di ProDev harus tampil (Inquiry sudah CONFIRMED).
        $prodevTest = Livewire::test('pages::production.prodev', ['part' => $prodPart->id_part]);
        $prodevHtml = $prodevTest->html();
        $this->assertStringContainsString('Referensi Inquiry Asal', $prodevHtml);
        $this->assertStringContainsString('Test Part SendToProDev', $prodevHtml);
    }

    public function test_prodev_reference_panel_hides_draft_and_cancelled_inquiries(): void
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

        $this->inquiryId = $test->get('inquiry')->id_inquiry;
        $partId = $test->get('parts.0')['id'];

        $test->call('confirm');
        $test->call('sendToProDev', 0);

        $iqpPart = IqpPart::findOrFail($partId);
        $prodevTest = Livewire::test('pages::production.prodev', ['part' => $iqpPart->prod_part_id]);
        $this->assertStringContainsString('Referensi Inquiry Asal', $prodevTest->html(), 'Inquiry CONFIRMED biasa harus tampil di panel referensi');

        // Cancel Inquiry -- panel referensi HARUS hilang (stat_confirm tetap
        // true selamanya, jadi sub_state yg jadi penentu -- gap yg sempat
        // kelewat & diperbaiki 2026-09-03).
        $test->set('cancel_reason', 'Test cancel utk verifikasi panel referensi')->call('cancel');
        $inquiry = IqpInquiry::findOrFail($this->inquiryId);
        $this->assertSame('CANCELLED', $inquiry->sub_state);

        $prodevTest2 = Livewire::test('pages::production.prodev', ['part' => $iqpPart->prod_part_id]);
        $this->assertStringNotContainsString('Referensi Inquiry Asal', $prodevTest2->html(), 'Inquiry CANCELLED tidak boleh tampil di panel referensi ProDev');
    }
}
