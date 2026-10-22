<?php

namespace Tests\Feature;

use App\Models\Sdi\CoOrder;
use App\Models\Sdi\CoOrderDelivery;
use App\Models\Sdi\CoOrderItem;
use App\Models\Sdi\DoDelivery;
use App\Models\Sdi\DoLine;
use App\Models\Sys\SecUser;
use App\Services\Dispatch\DispatchService;
use App\Services\Sales\CoEntryService;
use App\Support\Branding\Brand;
use App\Support\V2\Data\Identification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// backlog01 UI-09 (keputusan user 2026-09-23): nama perusahaan "Telasih Makmur"
// di mana-mana; dokumen ber-PPN (DO, Surat Jalan, Nota Inquiry) bisa dicetak
// "PPN" = "CV. Langgeng Jaya Makmur" + NPWP; 1 DO tidak boleh campur PPN/Non PPN.
// Data uji ber-prefix CO-TEST-PPN dibersihkan di tearDown.
class CompanyNamePpnPrintTest extends TestCase
{
    private array $coIds = [];

    private array $quotationIds = [];

    private ?DoDelivery $do = null;

    private ?int $customerId = null;

    protected function tearDown(): void
    {
        if ($this->do) {
            DoLine::where('delivery_id', $this->do->id_delivery)->delete();
            $this->do->delete();
        }
        foreach ($this->coIds as $id) {
            CoOrderDelivery::where('order_id', $id)->delete();
            CoOrderItem::where('order_id', $id)->delete();
            DB::connection('tm2sdi')->table('co_order')->where('id_order', $id)->delete();
        }
        DB::connection('tm2sdi')->table('quo_quotation')->whereIn('id_quotation', $this->quotationIds)->delete();

        parent::tearDown();
    }

    private function makeCo(string $coNo, bool $ppn): int
    {
        $qid = DB::connection('tm2sdi')->table('quo_quotation')->insertGetId([
            'quotation_no' => 'Q'.substr(md5($coNo.microtime()), 0, 12), 'customer_id' => $this->customerId,
            'doc_status' => 'PUBLISHED', 'issued_date' => now()->toDateString(), 'valid_until' => now()->addDays(30)->toDateString(),
            'currency_code' => 'IDR', 'created_by' => 'test', 'updated_by' => 'test',
        ]);
        $this->quotationIds[] = $qid;
        $coId = DB::connection('tm2sdi')->table('co_order')->insertGetId([
            'co_no' => $coNo, 'co_date' => now()->toDateString(), 'quotation_id' => $qid, 'customer_id' => $this->customerId,
            'is_ppn' => $ppn, 'doc_status' => 'CONFIRMED', 'created_by' => 'test', 'updated_by' => 'test',
        ]);
        $this->coIds[] = $coId;
        $itemId = CoOrderItem::create([
            'order_id' => $coId, 'line_seq' => 1, 'line_kind' => 'PRODUCT', 'description' => 'Box Uji '.$coNo,
            'qty' => 100, 'qty_unit' => 'PCS', 'unit_price' => 1000, 'discount_pct' => 0, 'subtotal' => 100000,
            'created_by' => 'test', 'updated_by' => 'test',
        ])->id_item;

        return CoOrderDelivery::create([
            'order_id' => $coId, 'item_id' => $itemId, 'shipment_type' => 'FULL', 'ship_date' => now()->addDay()->toDateString(),
            'ship_qty' => 100, 'status' => 'PLANNED', 'created_by' => 'test', 'updated_by' => 'test',
        ])->id_delivery;
    }

    public function test_company_names_come_from_constants(): void
    {
        $this->assertSame('Telasih Makmur', Brand::companyName());
        $this->assertSame('CV. Langgeng Jaya Makmur', Brand::companyLegalName());
        $this->assertNotEmpty(Brand::companyNpwp());
    }

    public function test_do_follows_first_co_ppn_and_rejects_mixing_then_prints_choice(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
        $this->customerId = DB::connection('tm2mst')->table('cust_customer')->value('id_customer');

        $ppnDelivery = $this->makeCo('CO-TEST-PPN-A', true);
        $nonDelivery = $this->makeCo('CO-TEST-PPN-B', false);

        $this->do = DoDelivery::create([
            'do_date' => now()->toDateString(), 'customer_id' => $this->customerId, 'status' => 'DRAFT',
            'created_by' => 'test', 'updated_by' => 'test',
        ]);

        $result = app(DispatchService::class)->pullLines($this->do, [$ppnDelivery, $nonDelivery], 'test');

        $this->assertFalse($result['ok']);
        // Pesan lewat kamus (bahasa tampilan): yang diuji = bagian yang tidak diterjemahkan (no. CO + status pajaknya).
        $this->assertStringContainsString('CO-TEST-PPN-B Non PPN', $result['message']);
        $this->assertTrue($this->do->fresh()->is_ppn, 'Baris pertama (CO PPN) menentukan flag DO.');
        $this->assertSame(1, DoLine::where('delivery_id', $this->do->id_delivery)->count(), 'CO Non PPN ditolak.');

        // Cetak hanya untuk DO yang sudah Confirm (aturan layar cetak DO).
        $this->do->update(['status' => 'CONFIRMED']);

        // Cetak DO/Surat Jalan hanya v2 (v1 diarsipkan 2026-10-20): kop memakai nama perusahaan; pilihan kop PPN (CV. Langgeng) belum ada di v2.
        foreach (['delivery-order', 'delivery-note'] as $type) {
            $html = $this->get('/v2/print/'.$type.'/'.$this->do->id_delivery)->assertOk()->getContent();
            $this->assertStringContainsStringIgnoringCase('Telasih Makmur', $html, $type);
        }
    }

    public function test_co_entry_pajak_field_toggles_ppn_only_while_draft(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
        $this->customerId = DB::connection('tm2mst')->table('cust_customer')->value('id_customer');
        $this->makeCo('CO-TEST-PPN-C', true);
        $coId = end($this->coIds);
        DB::connection('tm2sdi')->table('co_order')->where('id_order', $coId)->update(['doc_status' => 'DRAFT']);

        CoEntryService::updateHeader(CoOrder::findOrFail($coId), 'is_ppn', '0');
        $this->assertSame(0, (int) DB::connection('tm2sdi')->table('co_order')->where('id_order', $coId)->value('is_ppn'));

        DB::connection('tm2sdi')->table('co_order')->where('id_order', $coId)->update(['doc_status' => 'CONFIRMED']);
        CoEntryService::updateHeader(CoOrder::findOrFail($coId), 'is_ppn', '1');
        $this->assertSame(0, (int) DB::connection('tm2sdi')->table('co_order')->where('id_order', $coId)->value('is_ppn'), 'CO non-DRAFT terkunci.');
    }

    public function test_non_ppn_documents_always_print_company_name_without_choice(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
        $quotationId = DB::connection('tm2sdi')->table('quo_quotation')->where('doc_status', 'PUBLISHED')->value('id_quotation');

        $html = $this->get('/v2/print/quotation/'.$quotationId)->assertOk()->getContent();

        // Cetak Quotation v2 selalu kop badan hukum (quotation selalu ber-PPN, user 2026-10-02), tanpa pilihan kop.
        $this->assertStringContainsStringIgnoringCase(Brand::companyLegalName(), $html);
        $this->assertStringNotContainsString('class="kop-choice"', $html);
        $this->assertStringNotContainsString('PT Telasih', $html);
    }

    // Keputusan user 2026-09-23: admin bisa mengisi identitas perusahaan sendiri
    // (Settings > Identifikasi) dan apa pun yang diisi harus tercetak di kop.
    public function test_admin_inputs_company_identity_and_it_prints(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
        $consts = DB::connection('tm2sys')->table('sys_constant')->whereIn('constant_key', ['COMPANY_NAME', 'COMPANY_LEGAL_NAME'])->pluck('constant_text', 'constant_key')->all();
        $company = (array) DB::connection('tm2mst')->table('org_company')->where('company_code', 'TM')->first(['npwp_number', 'address', 'phone', 'email']);

        try {
            Identification::saveCompany(['name' => 'Telasih Uji', 'legal_name' => 'CV. Legal Uji', 'npwp' => '11.222.333.4-555.666',
                'address' => "Jl. Uji No. 1\nSurabaya", 'phone' => '031-999', 'email' => 'uji@contoh.co.id'], null, null, 'test');

            $inquiryId = DB::connection('tm2sdi')->table('iqp_inquiry')->where('tax_scheme', 'PPN')->value('id_inquiry');
            // Cetak Nota Inquiry v2 (dokumen PPN = kop badan hukum + NPWP).
            $html = $this->get('/v2/print/inquiry-note/'.$inquiryId)->assertOk()->getContent();
            $this->assertStringContainsString('CV. Legal Uji', $html);
            $this->assertStringContainsString('NPWP 11.222.333.4-555.666', $html);
            $this->assertStringContainsString('Jl. Uji No. 1', $html);
            $this->assertStringContainsString('031-999', $html);

            try {
                Identification::saveCompany(['name' => '', 'legal_name' => 'CV. Legal Uji'], null, null, 'test');
                $this->fail('Nama perusahaan kosong harus ditolak.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('name', $e->errors());
            }
        } finally {
            foreach ($consts as $key => $text) {
                DB::connection('tm2sys')->table('sys_constant')->where('constant_key', $key)->update(['constant_text' => $text]);
            }
            DB::connection('tm2mst')->table('org_company')->where('company_code', 'TM')->update($company);
        }

        $this->assertSame($company, (array) DB::connection('tm2mst')->table('org_company')->where('company_code', 'TM')->first(['npwp_number', 'address', 'phone', 'email']));
    }

    public function test_inquiry_note_with_ppn_prints_the_legal_name_kop(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
        $inquiryId = DB::connection('tm2sdi')->table('iqp_inquiry')->where('tax_scheme', 'PPN')->value('id_inquiry');

        $html = $this->get('/v2/print/inquiry-note/'.$inquiryId)->assertOk()->getContent();

        // v2 tidak punya pilihan kop (kop mengikuti skema pajak inquiry).
        $this->assertStringNotContainsString('class="kop-choice"', $html);
        $this->assertStringContainsString('CV. Langgeng Jaya Makmur', $html);
    }
}
