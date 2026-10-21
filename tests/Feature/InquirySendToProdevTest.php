<?php

namespace Tests\Feature;

use App\Models\Master\ProdPart;
use App\Models\Master\ProdProduct;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\IqpPart;
use App\Models\Sys\SecUser;
use App\Services\Sales\InquiryWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\InquiryDriver;
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
            if ($inquiry && $inquiry->product_id) {
                ProdProduct::whereKey($inquiry->product_id)->delete();
            }
        }

        parent::tearDown();
    }

    public function test_send_to_prodev_creates_product_and_part_and_links_back(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);
        $writer = InquiryDriver::writer();

        $inquiry = InquiryDriver::create('Test SendToProDev Product', ['parts' => [['part_name' => 'Test Part SendToProDev', 'part_type' => 'DC', 'qty_per_sku' => 1, 'cfg_color' => '4/4']]]);
        $this->inquiryId = $inquiry->id_inquiry;
        $partId = $inquiry->parts()->firstOrFail()->id_part;

        // Sebelum CONFIRM -- Kirim ke ProDev harus ditolak
        // (guard: Inquiry draft tidak boleh mengalir ke ProDev).
        try {
            $writer->sendToProDev($inquiry, IqpPart::findOrFail($partId));
            $this->fail('sendToProDev SEBELUM confirm harus ditolak');
        } catch (ValidationException) {
            $this->assertNull(IqpPart::find($partId)->prod_part_id, 'prod_part_id harus tetap null');
        }

        $inquiry = InquiryDriver::confirm($inquiry);
        $writer->sendToProDev($inquiry, IqpPart::findOrFail($partId));

        $iqpPart = IqpPart::findOrFail($partId);
        $this->assertNotNull($iqpPart->prod_part_id, 'iqp_part.prod_part_id harus terisi setelah sendToProDev');

        $inquiry = IqpInquiry::findOrFail($this->inquiryId);
        $this->assertNotNull($inquiry->product_id, 'iqp_inquiry.product_id harus terisi (prod_product auto-dibuat)');

        $prodPart = ProdPart::findOrFail($iqpPart->prod_part_id);
        $this->assertSame('Test Part SendToProDev', $prodPart->part_name);
        $this->assertSame($inquiry->product_id, $prodPart->product_id);

        // Panggil lagi -- harus idempoten (tidak bikin ProdProduct/ProdPart dobel).
        $writer->sendToProDev($inquiry->refresh(), $iqpPart);
        $iqpPart->refresh();
        $this->assertSame($prodPart->id_part, $iqpPart->prod_part_id, 'Panggil 2x tidak boleh bikin ProdPart baru');
    }

    // Jo 2026-10-18: part_type, join_type_id, dieline_spec ikut ke prod_part hanya bila masih kosong (hasil edit officer tidak ditimpa).
    public function test_writer_copies_type_join_and_spec_only_when_prod_part_is_empty(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());

        $created = InquiryDriver::create('Test SendToProDev Copy', ['parts' => [['part_name' => 'Test Part Copy', 'part_type' => 'DC', 'qty_per_sku' => 1, 'cfg_color' => '4/4']]]);
        $this->inquiryId = $created->id_inquiry;
        InquiryDriver::confirm($created);

        $part = $created->parts()->firstOrFail();
        $spec = ['type_code' => 'RTE_BOX', 'dims' => ['product_length' => 230], 'elements' => [['t' => 'cut']]];
        $part->update(['part_type' => 'DC', 'join_type_id' => 3, 'dieline_spec' => $spec]);
        $inquiry = IqpInquiry::findOrFail($this->inquiryId);
        $writer = new InquiryWriter('test');

        $prodPartId = $writer->sendToProDev($inquiry, $part->fresh());
        $prodPart = ProdPart::findOrFail($prodPartId);
        $this->assertSame('DC', $prodPart->part_type);
        $this->assertSame(3, (int) $prodPart->join_type_id);
        $this->assertEqualsCanonicalizing($spec, $prodPart->dieline_spec);

        // Officer mengubah di ProDev, lalu Inquiry dibuka ulang: nilai officer tetap.
        $prodPart->update(['part_type' => 'DC+SF', 'join_type_id' => 4]);
        $writer->sendToProDev($inquiry, $part->fresh());
        $prodPart->refresh();
        $this->assertSame('DC+SF', $prodPart->part_type);
        $this->assertSame(4, (int) $prodPart->join_type_id);
    }
}
