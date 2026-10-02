<?php

namespace Tests\Feature;

use App\Models\Acc\FinCompanyBank;
use App\Models\Acc\FinDpAllocation;
use App\Models\Acc\FinInvoice;
use App\Models\Acc\FinPaymentAlloc;
use App\Models\Acc\FinVoucher;
use App\Models\Acc\FinVoucherLine;
use App\Models\Master\CustCustomer;
use App\Models\Sdi\CoOrder;
use App\Models\Sdi\CoOrderItem;
use App\Models\Sdi\DoDelivery;
use App\Models\Sdi\DoDeliveryPod;
use App\Models\Sdi\DoLine;
use App\Models\Sys\SecUser;
use App\Services\Acc\ArApService;
use App\Services\Acc\CustomerInvoiceService;
use App\Services\Sales\DeliveryPromiseService;
use App\Support\Docs\DocSeries;
use App\Support\Finance\TaxRate;
use App\Support\V2\Data\CustomerInvoice;
use App\Support\V2\Print\PrintDocs;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// Customer Invoice + uang muka (App\Services\Acc\CustomerInvoiceService; ketentuan: customerinvoice_accounting.md).
// Menulis ke tm2sdi (order + Surat Jalan buatan test), tm2acc (invoice, alokasi uang muka, voucher) dan penghitung seri
// nomor di tm2sys; semuanya dihapus / dikembalikan di tearDown. Dilewati bila tabel modulnya belum ada di database ini.
class CustomerInvoiceTest extends TestCase
{
    private CustomerInvoiceService $svc;

    private array $orders = [];

    private array $deliveries = [];

    private array $counters = [];

    private int $firstInvoice = 0;

    private int $firstVoucher = 0;

    private string $today;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::connection('tm2acc')->hasTable('fin_invoice_line') || ! Schema::connection('tm2sys')->hasTable('sys_doc_series')) {
            $this->markTestSkipped('Tabel Customer Invoice (fin_invoice_line, sys_doc_series) belum ada di database ini.');
        }
        $this->actingAs(SecUser::query()->where('is_admin', 1)->firstOrFail());
        $this->svc = app(CustomerInvoiceService::class);
        $this->today = now()->toDateString();
        $this->firstInvoice = (int) FinInvoice::withoutGlobalScopes()->max('invoice_id');
        $this->firstVoucher = (int) FinVoucher::withoutGlobalScopes()->max('voucher_id');
        $this->counters = DB::connection('tm2sys')->table('sys_doc_series_counter')->get()->map(fn ($r) => (array) $r)->all();
    }

    protected function tearDown(): void
    {
        if ($this->firstInvoice || $this->orders) {
            $invoices = FinInvoice::withoutGlobalScopes()->where('invoice_id', '>', $this->firstInvoice)->whereIn('co_id', $this->orders ?: [0])->pluck('invoice_id');
            FinDpAllocation::query()->whereIn('final_invoice_id', $invoices)->orWhereIn('dp_invoice_id', $invoices)->delete();
            FinPaymentAlloc::query()->whereIn('invoice_id', $invoices)->delete();
            $vouchers = FinVoucher::withoutGlobalScopes()->where('voucher_id', '>', $this->firstVoucher)
                ->where(fn ($q) => $q->whereIn('source_type', ['CUSTOMER_INVOICE', 'CUSTOMER_DP_PAYMENT', 'AR_RECEIPT'])->whereIn('source_id', $invoices))->pluck('voucher_id');
            $vouchers = $vouchers->merge(FinVoucher::withoutGlobalScopes()->where('source_type', 'REVERSAL')->whereIn('source_id', $vouchers)->pluck('voucher_id'));
            FinInvoice::withoutGlobalScopes()->whereKey($invoices)->update(['voucher_id' => null]);
            FinVoucher::withoutGlobalScopes()->whereKey($vouchers)->update(['reversal_of_id' => null, 'reversed_by_id' => null]);
            FinVoucherLine::withoutGlobalScopes()->whereIn('voucher_id', $vouchers)->delete();
            FinVoucher::withoutGlobalScopes()->whereKey($vouchers)->delete();
            FinInvoice::withoutGlobalScopes()->whereKey($invoices)->delete();
            DoDeliveryPod::query()->whereIn('delivery_id', $this->deliveries ?: [0])->delete();
            DoLine::query()->whereIn('delivery_id', $this->deliveries ?: [0])->delete();
            DoDelivery::query()->whereKey($this->deliveries)->delete();
            CoOrderItem::query()->whereIn('order_id', $this->orders ?: [0])->delete();
            CoOrder::query()->whereKey($this->orders)->delete();
            // Penghitung seri nomor kembali persis seperti sebelum test (nomor tidak boleh "terpakai" oleh test).
            $sys = DB::connection('tm2sys');
            $before = collect($this->counters)->keyBy('id_doc_series_counter');
            $sys->table('sys_doc_series_counter')->whereNotIn('id_doc_series_counter', $before->keys()->all() ?: [0])->delete();
            foreach ($before as $id => $row) {
                $sys->table('sys_doc_series_counter')->where('id_doc_series_counter', $id)->update(['last_no' => $row['last_no']]);
            }
        }
        parent::tearDown();
    }

    /** Order Confirmed + satu Surat Jalan yang sudah diterima customer. $lines: [[qty kirim, qty diterima, harga, borongan?]]. */
    private function order(bool $ppn, array $lines): CoOrder
    {
        $customer = CustCustomer::withoutGlobalScopes()->orderBy('id_customer')->firstOrFail();
        $tag = 'T'.substr((string) microtime(true), -9, 6).random_int(10, 99);
        $co = CoOrder::create(['co_no' => 'TI'.$tag, 'co_date' => $this->today, 'entry_mode' => 'INSTANT', 'customer_id' => $customer->id_customer, 'payment_term' => 'NET30',
            'currency_code' => 'IDR', 'is_ppn' => $ppn, 'customer_po_no' => 'PO-TEST', 'doc_status' => 'CONFIRMED', 'created_by' => 'test', 'updated_by' => 'test']);
        $this->orders[] = $co->id_order;
        $do = DoDelivery::create(['do_no' => 'TD'.$tag, 'do_type' => 'CUSTOMER', 'is_ppn' => $ppn, 'do_date' => $this->today, 'customer_id' => $customer->id_customer,
            'status' => 'CONFIRMED', 'created_by' => 'test', 'updated_by' => 'test']);
        $this->deliveries[] = $do->id_delivery;
        foreach ($lines as $i => [$shipped, $received, $price, $lump]) {
            $item = new CoOrderItem(['order_id' => $co->id_order, 'line_seq' => $i + 1, 'line_kind' => 'PRODUCT', 'description' => 'Test box '.($i + 1), 'qty' => $shipped,
                'qty_unit' => 'PCS', 'qty_minim' => $lump, 'unit_price' => $price, 'discount_pct' => 0, 'created_by' => 'test', 'updated_by' => 'test']);
            $item->recalcSubtotal();
            $item->save();
            $line = new DoLine(['delivery_id' => $do->id_delivery, 'line_no' => $i + 1, 'co_order_id' => $co->id_order, 'co_order_item_id' => $item->id_item,
                'item_description' => $item->description, 'qty_do' => $shipped, 'qty_received' => $received, 'qty_unit' => 'PCS', 'unit_price' => $price, 'qty_invoiced' => 0,
                'status' => 'PLANNED', 'created_by' => 'test', 'updated_by' => 'test']);
            $line->recalcSubtotal();
            $line->save();
        }

        return $co->fresh();
    }

    private function bank(bool $ppn): FinCompanyBank
    {
        $bank = FinCompanyBank::query()->where('activate', 1)->orderByDesc('is_default')->get()->first(fn (FinCompanyBank $b) => $b->usableFor($ppn) && $b->glAccountId());
        if (! $bank) {
            // Database nyata: akun buku besar per rekening diisi Finance di layar Company Bank Accounts; test tidak mengisinya.
            $this->markTestSkipped('Belum ada rekening perusahaan dengan akun buku besar di database ini.');
        }

        return $bank;
    }

    private function balanced(FinVoucher $v): void
    {
        $v->load('lines');
        $this->assertEqualsWithDelta((float) $v->lines->sum('debit'), (float) $v->lines->sum('credit'), 0.005, 'Journal is not balanced.');
    }

    private function account(string $code): int
    {
        return app(ArApService::class)->accountId($code);
    }

    public function test_number_format_follows_the_series_tokens(): void
    {
        $d = CarbonImmutable::parse('2026-09-30');
        $this->assertSame('275/TM/IX/2026', DocSeries::format('{SEQ}/TM/{ROMAN}/{YYYY}', 275, $d));
        $this->assertSame('26000069', DocSeries::format('{YY}{SEQ:6}', 69, $d));
        $this->assertSame('5/UM-LJM/09/26', DocSeries::format('{SEQ}/UM-LJM/{MM}/{YY}', 5, $d));
    }

    // Contoh lampiran sistem lama: 508 pcs x 7.200 = 3.657.600, uang muka 2.000.000, sisa 1.657.600 (accounting.md 6.1).
    public function test_non_vat_invoice_deducts_the_down_payment_and_can_be_cancelled(): void
    {
        $co = $this->order(false, [[508, 508, 7200, false]]);

        $dp = $this->svc->createDownPayment($co, 'test');
        $this->assertSame('DRAFT', $dp->doc_status);
        $this->assertNull($dp->invoice_number, 'A Draft has no number.');
        $this->svc->saveDraft($dp, ['dp_percent' => null, 'bill_amount' => 2000000.0], 'test');
        $this->svc->confirm($dp->refresh(), 'test');
        $dp->refresh();
        $this->assertMatchesRegularExpression('#^\d+/UM-TM/[IVX]+/\d{4}$#', $dp->invoice_number);
        $this->assertNull($dp->voucher_id, 'A down payment invoice posts no journal.');
        $this->assertEqualsWithDelta(0.0, (float) $dp->tax_amount, 0.001);
        $this->assertTrue($this->svc->downPaymentsAvailable($co->id_order)->isEmpty(), 'An unpaid down payment cannot be deducted.');

        $receipt = $this->svc->receiveDownPayment($dp, $this->today, $this->bank(false)->id_company_bank, 2000000, null, 'test');
        $this->balanced($receipt);
        $this->assertEqualsWithDelta(2000000.0, (float) $receipt->lines->where('account_id', $this->account('213200'))->sum('credit'), 0.005);
        $this->assertSame('PAID', $dp->refresh()->payment_status);

        $inv = $this->svc->createInvoice($co, [], 'test');
        $this->assertEqualsWithDelta(3657600.0, (float) $inv->total_amount, 0.005);
        $this->assertEqualsWithDelta(2000000.0, (float) ($inv->dp_plan[$dp->invoice_id] ?? 0), 0.005, 'The paid down payment is proposed automatically.');
        $this->assertEqualsWithDelta(1657600.0, $this->svc->totals($inv)['balance'], 0.005);

        $this->svc->confirm($inv, 'test');
        $inv->refresh();
        $this->assertSame('POSTED', $inv->doc_status);
        $this->assertMatchesRegularExpression('#^\d+/TM/[IVX]+/\d{4}$#', $inv->invoice_number);
        $this->assertSame('PARTIAL', $inv->payment_status);
        $this->assertEqualsWithDelta(1657600.0, $inv->outstanding(), 0.005);
        $voucher = FinVoucher::withoutGlobalScopes()->findOrFail($inv->voucher_id);
        $this->balanced($voucher);
        $this->assertEqualsWithDelta(3657600.0, (float) $voucher->lines->where('account_id', $this->account('411000'))->sum('credit'), 0.005);
        $this->assertEqualsWithDelta(2000000.0, (float) $voucher->lines->where('account_id', $this->account('213200'))->sum('debit'), 0.005);
        $this->assertEqualsWithDelta(508.0, (float) DoLine::query()->where('co_order_id', $co->id_order)->value('qty_invoiced'), 0.005);
        $this->assertTrue($this->svc->billable($co->id_order)->isEmpty(), 'A fully invoiced delivery line cannot be invoiced again.');
        $this->assertTrue($this->svc->downPaymentsAvailable($co->id_order)->isEmpty(), 'The down payment is used up.');

        $doc = PrintDocs::make('customer-invoice', $inv->invoice_id);
        $this->assertEqualsWithDelta(1657600.0, $doc->totals()['grand'], 0.005);

        try {
            $this->svc->saveDraft($inv, ['notes' => 'changed'], 'test');
            $this->fail('A Confirmed invoice was changed.');
        } catch (DomainException) {
        }
        try {
            $this->svc->cancel($inv, '', 'test');
            $this->fail('Cancel without a reason was accepted.');
        } catch (DomainException) {
        }
        $number = $inv->invoice_number;
        $this->svc->cancel($inv, 'Wrong quantity', 'test');
        $inv->refresh();
        $this->assertSame('CANCELLED', $inv->doc_status);
        $this->assertSame($number, $inv->invoice_number, 'A cancelled invoice keeps its number.');
        $this->assertNotNull($voucher->refresh()->reversed_by_id, 'The journal is reversed.');
        $this->assertEqualsWithDelta(0.0, (float) DoLine::query()->where('co_order_id', $co->id_order)->value('qty_invoiced'), 0.005);
        $this->assertEqualsWithDelta(2000000.0, $this->svc->downPaymentsAvailable($co->id_order)->first()['remaining'], 0.005, 'The down payment is free again.');

        $again = $this->svc->createInvoice($co, [], 'test');
        $this->svc->confirm($again, 'test');
        $this->assertNotSame($number, $again->refresh()->invoice_number, 'A number is never reused.');
    }

    // Uang muka 50% atas order PPN: PPN diakui saat uang diterima, invoice akhir memuat sisanya (accounting.md 5.1-5.3, 6.3).
    public function test_vat_down_payment_splits_price_and_vat_and_is_reclassified_on_the_invoice(): void
    {
        $rate = TaxRate::ppn();
        $co = $this->order(true, [[10000, 10000, 1000, false]]);
        $orderTotal = round(10000000 * (1 + $rate / 100), 2);

        $dp = $this->svc->createDownPayment($co, 'test');
        $this->svc->saveDraft($dp, ['dp_percent' => 50.0], 'test');
        $dp->refresh();
        $half = round($orderTotal / 2, 2);
        $this->assertEqualsWithDelta($half, (float) $dp->total_amount, 0.005);
        $this->assertEqualsWithDelta(5000000.0, (float) $dp->subtotal, 0.01);
        $this->svc->confirm($dp, 'test');
        $this->assertMatchesRegularExpression('#^\d+/UM-LJM/[IVX]+/\d{4}$#', $dp->refresh()->invoice_number);

        $receipt = $this->svc->receiveDownPayment($dp, $this->today, $this->bank(true)->id_company_bank, $half, 'FP-TEST-UM', 'test');
        $this->balanced($receipt);
        $this->assertEqualsWithDelta(5000000.0, (float) $receipt->lines->where('account_id', $this->account('213200'))->sum('credit'), 0.01);
        $this->assertEqualsWithDelta($half - 5000000.0, (float) $receipt->lines->where('account_id', $this->account('212100'))->sum('credit'), 0.01);
        $this->assertSame('FP-TEST-UM', $dp->refresh()->tax_invoice_no);

        $inv = $this->svc->createInvoice($co, [], 'test');
        $this->svc->confirm($inv, 'test');
        $inv->refresh();
        $this->assertMatchesRegularExpression('#^\d+/LJM/[IVX]+/\d{4}$#', $inv->invoice_number);
        $this->assertEqualsWithDelta($orderTotal, (float) $inv->total_amount, 0.01);
        $this->assertEqualsWithDelta($half, (float) $inv->dp_applied_amount, 0.01);
        $this->assertEqualsWithDelta($orderTotal - $half, $inv->outstanding(), 0.01);
        $voucher = FinVoucher::withoutGlobalScopes()->findOrFail($inv->voucher_id);
        $this->balanced($voucher);
        $vat = $voucher->lines->where('account_id', $this->account('212100'));
        // PPN bersih invoice = PPN penuh dikurangi PPN yang sudah diakui saat uang muka.
        $this->assertEqualsWithDelta(($orderTotal - 10000000.0) - ($half - 5000000.0), (float) $vat->sum('credit') - (float) $vat->sum('debit'), 0.01);
        $alloc = FinDpAllocation::query()->where('final_invoice_id', $inv->invoice_id)->firstOrFail();
        $this->assertEqualsWithDelta(5000000.0, (float) $alloc->allocated_base, 0.01);
    }

    public function test_partial_quantity_leaves_the_rest_billable_and_over_quantity_is_refused(): void
    {
        $co = $this->order(false, [[508, 500, 7200, false]]);
        $this->assertEqualsWithDelta(500.0, $this->svc->remaining($this->svc->billable($co->id_order)->first()), 0.005, 'Billing follows the received quantity, not the shipped one.');

        $inv = $this->svc->createInvoice($co, [], 'test');
        $line = $inv->lines->first();
        $this->svc->saveDraft($inv, ['lines' => [$line->id_invoice_line => ['qty_invoiced' => 600.0]]], 'test');
        $this->assertNotEmpty($this->svc->confirmErrors($inv->refresh()), 'More than the received quantity must be refused.');

        $this->svc->saveDraft($inv, ['lines' => [$line->id_invoice_line => ['qty_invoiced' => 300.0]]], 'test');
        $this->assertEqualsWithDelta(2160000.0, (float) $inv->refresh()->total_amount, 0.005);
        $this->svc->confirm($inv, 'test');
        $this->assertEqualsWithDelta(200.0, $this->svc->remaining($this->svc->billable($co->id_order)->first()), 0.005);
    }

    public function test_lump_sum_line_is_billed_once_for_its_price(): void
    {
        $co = $this->order(false, [[573, 573, 798142, true]]);
        $inv = $this->svc->createInvoice($co, [], 'test');
        $line = $inv->lines->first();
        $this->assertTrue($line->is_lump_sum);
        $this->svc->saveDraft($inv, ['lines' => [$line->id_invoice_line => ['qty_invoiced' => 300.0]]], 'test');
        $this->assertEqualsWithDelta(798142.0, (float) $inv->refresh()->total_amount, 0.005, 'A lump sum line is worth its price, not qty x price.');
        $this->svc->confirm($inv, 'test');

        $rest = $this->svc->createInvoice($co, [], 'test');
        $this->assertEqualsWithDelta(0.0, (float) $rest->total_amount, 0.005, 'The lump sum is not billed a second time.');
        $this->assertNotEmpty($this->svc->confirmErrors($rest));
    }

    // Layar v2, kiriman form, dan cetakan: daftar -> pilih order -> Draft -> Save -> Confirm -> cetak; uang muka -> Record Receipt.
    public function test_screens_forms_and_prints_work_end_to_end(): void
    {
        if (! DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'FIN_AR_INVOICE')->where('activate', 1)->exists()) {
            $this->markTestSkipped('Menu Customer Invoice belum ada di database ini.');
        }
        $co = $this->order(true, [[1000, 1000, 2500, false]]);
        $home = route('v2.screen', 'customer-invoice');
        $doLine = DoLine::query()->where('co_order_id', $co->id_order)->firstOrFail();

        $this->get($home)->assertOk();
        $this->get($home.'?new=1')->assertOk()->assertSee($co->co_no);
        $this->get($home.'?new=1&type=DOWN_PAYMENT')->assertOk()->assertSee($co->co_no);
        $this->get($home.'?new=1&type=INVOICE&co_id='.$co->id_order)->assertOk()->assertSee($doLine->delivery->do_no);
        foreach (['fin-company-bank', 'setup-doc-series'] as $slug) {
            $this->get(route('v2.screen', $slug))->assertOk();
        }

        // Uang muka: buat -> Save (30%) -> Confirm -> Record Receipt.
        $this->post(route('v2.customer-invoice.create'), ['type' => 'DOWN_PAYMENT', 'co_id' => $co->id_order])->assertRedirect();
        $dp = FinInvoice::withoutGlobalScopes()->where('co_id', $co->id_order)->where('invoice_type', CustomerInvoiceService::DOWN_PAYMENT)->firstOrFail();
        // Nilai bulat di kotak isian tidak boleh terpotong nolnya (50% dari 2.775.000 = 1.387.500).
        $page = $this->get($home.'?id='.$dp->invoice_id)->assertOk()->assertSee('Draft #'.$dp->invoice_id);
        $this->assertMatchesRegularExpression('/name="bill_amount"[^>]*value="1[.,]387[.,]500/', $page->getContent());
        $this->post(route('v2.customer-invoice.save', $dp->invoice_id), ['action' => 'confirm', 'invoice_date' => $this->today, 'dp_percent' => '30', 'payment_term_code' => 'NET30',
            'company_bank_id' => $dp->company_bank_id])->assertRedirect()->assertSessionHasNoErrors();
        $dp->refresh();
        $this->assertSame('POSTED', $dp->doc_status);
        $this->assertEqualsWithDelta(round((float) $dp->order_amount * 0.30, 2), (float) $dp->total_amount, 0.005);
        $this->get($home.'?id='.$dp->invoice_id)->assertOk()->assertSee($dp->invoice_number)->assertSee('Record Receipt');
        $this->get(route('v2.print', ['type' => 'dp-invoice', 'id' => $dp->invoice_id]))->assertOk()->assertSee($dp->invoice_number);
        $this->post(route('v2.customer-invoice.receipt', $dp->invoice_id), ['received_date' => $this->today, 'company_bank_id' => $this->bank(true)->id_company_bank,
            'amount' => (string) round((float) $dp->total_amount, 2)])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('PAID', $dp->refresh()->payment_status);
        $this->get(route('v2.print', ['type' => 'dp-receipt', 'id' => $dp->invoice_id]))->assertOk();

        // Invoice: centang baris Surat Jalan -> Draft -> Save -> Confirm -> cetak.
        $this->post(route('v2.customer-invoice.create'), ['type' => 'INVOICE', 'co_id' => $co->id_order, 'lines' => [$doLine->id_line => ['pick' => 'false']]])
            ->assertSessionHasErrors('lines');
        $this->post(route('v2.customer-invoice.create'), ['type' => 'INVOICE', 'co_id' => $co->id_order, 'lines' => [$doLine->id_line => ['pick' => 'true']]])->assertRedirect()->assertSessionHasNoErrors();
        $inv = FinInvoice::withoutGlobalScopes()->where('co_id', $co->id_order)->where('invoice_type', CustomerInvoiceService::INVOICE)->firstOrFail();
        $line = $inv->lines()->firstOrFail();
        $this->get($home.'?id='.$inv->invoice_id)->assertOk()->assertSee($dp->invoice_number);
        $this->post(route('v2.customer-invoice.save', $inv->invoice_id), ['action' => 'confirm', 'invoice_date' => $this->today, 'payment_term_code' => 'NET30',
            'company_bank_id' => $inv->company_bank_id, 'charge_amount' => '0', 'lines' => [$line->id_invoice_line => ['qty_invoiced' => '1000']],
            'dp' => [$dp->invoice_id => ['apply' => (string) round((float) $dp->total_amount, 2)]]])->assertRedirect()->assertSessionHasNoErrors();
        $inv->refresh();
        $this->assertSame('POSTED', $inv->doc_status);
        $this->assertEqualsWithDelta((float) $dp->total_amount, (float) $inv->dp_applied_amount, 0.005);
        $this->get($home.'?id='.$inv->invoice_id)->assertOk()->assertSee($inv->invoice_number);
        $this->get(route('v2.print', ['type' => 'customer-invoice', 'id' => $inv->invoice_id]))->assertOk()->assertSee($inv->invoice_number);
        // Baris grid dikirim sebagai JSON: garis miring pada nomor tertulis "\/".
        $this->get($home)->assertOk()->assertSee(str_replace('/', '\/', $inv->invoice_number), false);

        // Pelunasan lewat dialog Record Payment: sebagian dulu, lalu sisanya -> Closed; sesudah ada pembayaran Cancel ditolak.
        $this->get($home.'?id='.$inv->invoice_id)->assertOk()->assertSee('Record Payment');
        $left = $inv->outstanding();
        $this->post(route('v2.customer-invoice.payment', $inv->invoice_id), ['received_date' => $this->today, 'company_bank_id' => $this->bank(true)->id_company_bank,
            'pay' => [$inv->invoice_id => ['paid' => '1000']]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('PARTIAL', $inv->refresh()->payment_status);
        $this->post(route('v2.customer-invoice.save', $inv->invoice_id), ['action' => 'cancel', '_reason' => 'Test cancel'])->assertSessionHasErrors('cancel');
        $this->post(route('v2.customer-invoice.payment', $inv->invoice_id), ['received_date' => $this->today, 'company_bank_id' => $this->bank(true)->id_company_bank,
            'pay' => [$inv->invoice_id => ['paid' => (string) round($left - 1000, 2)]]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('PAID', $inv->refresh()->payment_status);
        $this->assertSame('closed', CustomerInvoice::formStatus($inv));
        $this->get($home.'?id='.$inv->invoice_id)->assertOk()->assertDontSee('data-dl-open="invoice-payment"', false)
            ->assertSee('Proof of Delivery · '.$doLine->delivery->do_no);

        // Jejak: uang muka dan invoice terlihat di Customer Order (tab Billing), di buku uang muka, dan di laporan.
        $this->get($home.'?id='.$dp->invoice_id)->assertOk()->assertSee($inv->invoice_number);
        $this->get(route('v2.screen', 'customer-order').'?id='.$co->id_order)->assertOk()->assertSee($inv->invoice_number)->assertSee($dp->invoice_number);
        foreach (['acc-to-invoice', 'acc-rpt-ar-aging'] as $slug) {
            $this->get(route('v2.screen', $slug))->assertOk();
        }
        $this->get(route('v2.screen', 'acc-rpt-dp-ledger'))->assertOk()->assertSee(str_replace('/', '\/', $dp->invoice_number), false);
    }

    // User 2026-10-02: tagihan mengikuti yang divalidasi customer di Surat Jalan (retur / rusak di jalan tidak ditagih).
    public function test_invoice_follows_what_the_customer_received_and_keeps_the_reason_for_a_difference(): void
    {
        if (! Schema::connection('tm2sdi')->hasColumn('do_line', 'receive_diff_reason')) {
            $this->markTestSkipped('Kolom alasan selisih terima belum ada di database ini.');
        }
        $co = $this->order(false, [[100, null, 5000, false], [40, null, 2000, false]]);
        $do = DoDelivery::query()->findOrFail($this->deliveries[0]);
        [$a, $b] = $do->lines()->orderBy('line_no')->get()->all();
        $this->assertTrue($this->svc->billable($co->id_order)->isEmpty(), 'A delivery note without proof of delivery cannot be invoiced.');
        $this->assertSame('Waiting proof of delivery', $this->svc->deliveryChain($co->id_order)->first()['state']);
        $arrival = ['arrived_at' => now()->format('Y-m-d H:i'), 'result' => 'PARTIAL', 'received_by' => 'Tester', 'miss_cause' => 'QUALITY'];

        try {
            DeliveryPromiseService::record($do, $arrival + ['received' => [$a->id_line => ['qty_received' => '90'], $b->id_line => ['qty_received' => '40']]], null, 'test');
            $this->fail('Receiving less than shipped without a reason was accepted.');
        } catch (ValidationException) {
        }
        $this->assertSame(0, DoDeliveryPod::query()->where('delivery_id', $do->id_delivery)->count(), 'A refused arrival leaves no record.');

        DeliveryPromiseService::record($do, $arrival + ['received' => [$a->id_line => ['qty_received' => '90', 'reason' => 'DAMAGED', 'note' => '10 pcs wet'], $b->id_line => ['qty_received' => '40']]], null, 'test');
        $this->assertEqualsWithDelta(90.0, (float) $a->refresh()->qty_received, 0.005);
        $this->assertSame('DAMAGED', $a->receive_diff_reason);
        $this->assertSame('10 pcs wet', $a->receive_note);
        $this->assertNull($b->refresh()->receive_diff_reason, 'A fully received line has no reason.');

        $inv = $this->svc->createInvoice($co, [], 'test');
        $this->assertEqualsWithDelta(90 * 5000 + 40 * 2000, (float) $inv->total_amount, 0.005, 'Only what the customer received is invoiced.');
        $rows = CustomerInvoice::deliveryRows($inv->load('lines'));
        $this->assertCount(2, $rows);
        $this->assertSame('Tester', $rows[0]['received_by']);
        $this->assertNotSame('', $rows[0]['reason']);
        $this->svc->confirm($inv, 'test');
        $this->assertSame(['Fully invoiced'], $this->svc->deliveryChain($co->id_order)->pluck('state')->unique()->values()->all());
    }

    // User 2026-10-02: uang muka jelas dari Customer Order yang mana dan riwayatnya terlacak.
    public function test_down_payment_history_of_an_order_is_traceable(): void
    {
        $co = $this->order(false, [[100, 100, 10000, false], [100, 100, 10000, false]]);
        $dp = $this->svc->createDownPayment($co, 'test');
        $this->svc->saveDraft($dp, ['dp_percent' => null, 'bill_amount' => 800000.0], 'test');
        $this->svc->confirm($dp->refresh(), 'test');
        $this->svc->receiveDownPayment($dp->refresh(), $this->today, $this->bank(false)->id_company_bank, 800000, null, 'test');

        $lines = $this->svc->billable($co->id_order);
        $first = $this->svc->createInvoice($co, [$lines[0]->id_line], 'test');
        $this->svc->saveDraft($first, ['dp' => [$dp->invoice_id => ['apply' => 300000.0]]], 'test');
        $this->svc->confirm($first->refresh(), 'test');

        $ledger = $this->svc->downPaymentLedger($co->id_order);
        $this->assertSame(['Down payment invoiced', 'Money received', 'Applied to invoice'], array_column($ledger['rows'], 'event'));
        $this->assertSame($first->refresh()->invoice_number, $ledger['rows'][2]['doc_no']);
        $this->assertEqualsWithDelta(800000.0, $ledger['billed'], 0.005);
        $this->assertEqualsWithDelta(800000.0, $ledger['received'], 0.005);
        $this->assertEqualsWithDelta(300000.0, $ledger['applied'], 0.005);
        $this->assertEqualsWithDelta(500000.0, $ledger['balance'], 0.005);
        $this->assertEqualsWithDelta(500000.0, CustomerInvoice::downPaymentUse($dp->refresh())['left'], 0.005);

        $bill = $this->svc->orderBilling($co);
        $this->assertEqualsWithDelta(2000000.0, $bill['order_value'], 0.005);
        $this->assertEqualsWithDelta(1000000.0, $bill['invoiced'], 0.005);
        $this->assertEqualsWithDelta(1000000.0, $bill['ready_value'], 0.005, 'The second delivery line is still to be invoiced.');
        $this->assertEqualsWithDelta(700000.0, $bill['outstanding'], 0.005);

        // Uang muka tanpa Customer Order ditolak saat Confirm.
        $orphan = $this->svc->createDownPayment($co, 'test');
        $orphan->forceFill(['co_id' => null])->save();
        $this->assertNotEmpty(array_filter($this->svc->confirmErrors($orphan), fn ($e) => str_contains($e, 'Customer Order')));
        $orphan->forceFill(['co_id' => $co->id_order])->save();
    }

    public function test_payment_can_cover_several_invoices_and_never_exceeds_what_is_left(): void
    {
        $co = $this->order(false, [[100, 100, 10000, false], [50, 50, 4000, false]]);
        $lines = $this->svc->billable($co->id_order);
        $a = $this->svc->createInvoice($co, [$lines[0]->id_line], 'test');
        $b = $this->svc->createInvoice($co, [$lines[1]->id_line], 'test');
        $this->svc->confirm($a, 'test');
        $this->svc->confirm($b, 'test');
        $customer = (int) $co->customer_id;
        $bank = $this->bank(false)->id_company_bank;

        try {
            $this->svc->receivePayment($customer, $this->today, $bank, [$a->invoice_id => 1000001.0], 'test');
            $this->fail('A payment above the invoice balance was accepted.');
        } catch (DomainException) {
        }
        $voucher = $this->svc->receivePayment($customer, $this->today, $bank, [$a->invoice_id => 1000000.0, $b->invoice_id => 50000.0], 'test', 'Transfer');
        $this->balanced($voucher);
        $this->assertEqualsWithDelta(1050000.0, (float) $voucher->lines->sum('debit'), 0.005);
        $this->assertSame('PAID', $a->refresh()->payment_status);
        $this->assertSame('PARTIAL', $b->refresh()->payment_status);
        $this->assertEqualsWithDelta(150000.0, $b->outstanding(), 0.005);
        $this->assertCount(1, $this->svc->openInvoices($customer, false)->whereIn('invoice_id', [$a->invoice_id, $b->invoice_id]));

        $this->expectException(DomainException::class);
        $this->svc->cancel($a, 'Paid invoice', 'test');
    }

    public function test_draft_can_be_deleted_and_a_received_down_payment_cannot_be_cancelled(): void
    {
        $co = $this->order(false, [[100, 100, 5000, false]]);
        $draft = $this->svc->createInvoice($co, [], 'test');
        $this->svc->delete($draft);
        $this->assertNull(FinInvoice::withoutGlobalScopes()->find($draft->invoice_id));

        $dp = $this->svc->createDownPayment($co, 'test');
        $this->svc->confirm($dp, 'test');
        $this->svc->receiveDownPayment($dp->refresh(), $this->today, $this->bank(false)->id_company_bank, (float) $dp->total_amount, null, 'test');
        $this->expectException(DomainException::class);
        $this->svc->cancel($dp->refresh(), 'Customer asked', 'test');
    }
}
