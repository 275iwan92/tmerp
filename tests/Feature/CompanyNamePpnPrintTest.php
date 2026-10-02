<?php

namespace Tests\Feature;

use App\Models\Sdi\CoOrderDelivery;
use App\Models\Sdi\CoOrderItem;
use App\Models\Sdi\DoDelivery;
use App\Models\Sdi\DoLine;
use App\Models\Sys\SecUser;
use App\Services\Dispatch\DispatchService;
use App\Support\Branding\Brand;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
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

        foreach (['pages::dispatch.do.print', 'pages::dispatch.do.print-surat-jalan'] as $page) {
            $html = Livewire::test($page, ['doId' => $this->do->id_delivery])->html();
            $this->assertStringContainsString('ppnKop = false">Non PPN', $html, $page);
            $this->assertStringContainsString('ppnKop = true">PPN', $html, $page);
            $this->assertStringContainsString('Telasih Makmur', $html, $page);
            $this->assertStringContainsString('CV. Langgeng Jaya Makmur', $html, $page);
            $this->assertStringContainsString('NPWP '.Brand::companyNpwp(), $html, $page);
        }
    }

    public function test_co_entry_pajak_field_toggles_ppn_only_while_draft(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
        $this->customerId = DB::connection('tm2mst')->table('cust_customer')->value('id_customer');
        $this->makeCo('CO-TEST-PPN-C', true);
        $coId = end($this->coIds);
        DB::connection('tm2sdi')->table('co_order')->where('id_order', $coId)->update(['doc_status' => 'DRAFT']);

        $c = Livewire::test('pages::co.entry', ['order' => $coId])->assertSee('Non PPN');
        $c->call('updateHeader', 'is_ppn', '0');
        $this->assertSame(0, (int) DB::connection('tm2sdi')->table('co_order')->where('id_order', $coId)->value('is_ppn'));

        DB::connection('tm2sdi')->table('co_order')->where('id_order', $coId)->update(['doc_status' => 'CONFIRMED']);
        Livewire::test('pages::co.entry', ['order' => $coId])->call('updateHeader', 'is_ppn', '1');
        $this->assertSame(0, (int) DB::connection('tm2sdi')->table('co_order')->where('id_order', $coId)->value('is_ppn'), 'CO non-DRAFT terkunci.');
    }

    public function test_non_ppn_documents_always_print_company_name_without_choice(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
        $quotationId = DB::connection('tm2sdi')->table('quo_quotation')->where('doc_status', 'PUBLISHED')->value('id_quotation');

        $html = Livewire::test('pages::quotation.print', ['quotation' => $quotationId])->html();

        $this->assertStringContainsString('Telasih Makmur', $html);
        $this->assertStringNotContainsString('CV. Langgeng', $html);
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
            Livewire::test('pages::setup.identification')
                ->set('companyName', 'Telasih Uji')
                ->set('companyLegalName', 'CV. Legal Uji')
                ->set('companyNpwp', '11.222.333.4-555.666')
                ->set('companyAddress', "Jl. Uji No. 1\nSurabaya")
                ->set('companyPhone', '031-999')
                ->set('companyEmail', 'uji@contoh.co.id')
                ->call('saveCompany')
                ->assertHasNoErrors();

            $inquiryId = DB::connection('tm2sdi')->table('iqp_inquiry')->where('tax_scheme', 'PPN')->value('id_inquiry');
            $html = Livewire::test('pages::inquiry.nota', ['inquiry' => $inquiryId])->html();
            $this->assertStringContainsString('Telasih Uji', $html);
            $this->assertStringContainsString('CV. Legal Uji', $html);
            $this->assertStringContainsString('NPWP 11.222.333.4-555.666', $html);
            $this->assertStringContainsString('Jl. Uji No. 1<br />', $html, 'Alamat multi-baris tetap berbaris.');
            $this->assertStringContainsString('Telp 031-999', $html);

            Livewire::test('pages::setup.identification')->set('companyName', '')->call('saveCompany')->assertHasErrors(['companyName']);
        } finally {
            foreach ($consts as $key => $text) {
                DB::connection('tm2sys')->table('sys_constant')->where('constant_key', $key)->update(['constant_text' => $text]);
            }
            DB::connection('tm2mst')->table('org_company')->where('company_code', 'TM')->update($company);
        }

        $this->assertSame($company, (array) DB::connection('tm2mst')->table('org_company')->where('company_code', 'TM')->first(['npwp_number', 'address', 'phone', 'email']));
    }

    public function test_inquiry_nota_with_ppn_offers_choice(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
        $inquiryId = DB::connection('tm2sdi')->table('iqp_inquiry')->where('tax_scheme', 'PPN')->value('id_inquiry');

        $html = Livewire::test('pages::inquiry.nota', ['inquiry' => $inquiryId])->html();

        $this->assertStringContainsString('class="kop-choice"', $html);
        $this->assertStringContainsString('CV. Langgeng Jaya Makmur', $html);
    }
}
