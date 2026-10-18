<?php

/*
 * REMARK
 * Tujuan       : Menjaga tagihan vendor v2: catat draft (PPN, PPh, total), aturan dobel / tanggal / nilai, default vendor, Confirm di atas batas persetujuan jadi Pending approval, Cancel, Duplicate, pembayaran ditolak bila melebihi sisa atau bukan tagihan vendor yang sama, pemetaan status, form dan grid terbuka, v1 sudah pindah ke arsip.
 * Sumber hulu  : App\Services\Acc\VendorBillService, ArApService, layar acc-payables-form, grid acc-payables, routes/v2_finance.php.
 * Input        : User johiwan (admin); tagihan uji ber-no. vendor ZZTEST* di tm2acc.fin_invoice (dihapus di tearDown); tagihan Posted yang sudah ada hanya dibaca. Tes tidak memposting ke GL dan tidak membayar.
 * Output       : Lulus bila aturan terpenuhi dan tidak ada jalur ke halaman v1 finance.payables.
 * Dipakai oleh : php artisan test --filter=VendorBillServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinInvoice;
use App\Models\Master\SupSupplier;
use App\Models\Sys\SecUser;
use App\Services\Acc\ArApService;
use App\Services\Acc\JournalPostingService;
use App\Services\Acc\VendorBillService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class VendorBillServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        FinInvoice::withoutGlobalScopes()->where('partner_ref', 'like', 'ZZTEST%')->delete();
        $this->assertSame(0, FinInvoice::withoutGlobalScopes()->where('partner_ref', 'like', 'ZZTEST%')->count(), 'tagihan uji harus bersih');
        parent::tearDown();
    }

    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    private function supplier(): SupSupplier
    {
        return SupSupplier::where('activate', true)->orderBy('id_supplier')->firstOrFail();
    }

    private function input(array $over = []): array
    {
        return $over + ['supplier_id' => $this->supplier()->id_supplier, 'partner_ref' => 'ZZTEST-1', 'bill_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'description' => 'ZZTEST bill', 'expense_account_id' => (int) FinAccount::where('account_code', '211900')->value('account_id'), 'subtotal' => 1000000, 'ppn' => true, 'pph_code' => 'PPH23'];
    }

    public function test_draft_bill_gets_tax_amounts_and_total_without_posting(): void
    {
        $svc = app(VendorBillService::class);
        $ap = app(ArApService::class);
        $b = $svc->create($this->input(), $this->admin());

        $this->assertSame('DRAFT', $b->doc_status);
        $this->assertNull($b->voucher_id);
        $this->assertSame(round(1000000 * $ap->taxRate('PPN_IN'), 2), (float) $b->tax_amount);
        $this->assertSame(round(1000000 * $ap->taxRate('PPH23'), 2), (float) $b->pph_amount);
        $this->assertSame(1000000 + (float) $b->tax_amount, (float) $b->total_amount);
        $this->assertFalse((bool) $b->tax_validated);
        $this->assertSame('MANUAL', $b->source_type);
        $this->assertSame(['draft', null], $svc->formState($b));
    }

    public function test_input_rules_are_refused(): void
    {
        $svc = app(VendorBillService::class);
        $user = $this->admin();
        $svc->create($this->input(), $user);

        foreach ([
            $this->input(['subtotal' => 0]),
            $this->input(['partner_ref' => '', 'description' => 'x']),
            $this->input(['partner_ref' => 'ZZTEST-2', 'due_date' => now()->subDay()->toDateString()]),
            $this->input(['partner_ref' => 'ZZTEST-3', 'pph_code' => 'PPH99']),
        ] as $bad) {
            try {
                $svc->create($bad, $user);
                $this->fail('input must be refused');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
        $this->expectException(HttpException::class);
        $svc->create($this->input(), $user);
    }

    public function test_vendor_defaults_confirm_over_limit_cancel_and_duplicate(): void
    {
        $svc = app(VendorBillService::class);
        $user = $this->admin();
        $s = $this->supplier();
        $def = $svc->defaults($s, '2026-10-01');
        $this->assertSame($s->is_subcon ? 'PPH23' : '', $def['pph_code']);
        $this->assertGreaterThanOrEqual('2026-10-01', $def['due_date']);

        $b = $svc->create($this->input(['partner_ref' => 'ZZTEST-BIG', 'subtotal' => 150000000]), $user);
        if (! app(JournalPostingService::class)->requiresApproval('VENDOR_BILL', (float) $b->total_amount)) {
            $this->markTestSkipped('No VENDOR_BILL approval rule in this database: Confirm would post to the GL.');
        }
        $this->assertSame('submitted', $svc->confirm($b, $user));
        $b->refresh();
        $this->assertSame('SUBMITTED', $b->doc_status);
        $this->assertNull($b->voucher_id);
        $this->assertSame(['approval', 'Pending approval'], $svc->formState($b));
        $this->assertTrue($svc->canApprove($b, $user));

        $svc->cancel($b, $user);
        $this->assertSame(['cancelled', null], $svc->formState($b->fresh()));
        $copy = $svc->duplicate($b->fresh(), $user);
        $this->assertSame('DRAFT', $copy->doc_status);
        $this->assertSame((float) $b->subtotal, (float) $copy->subtotal);
    }

    public function test_posted_bill_cannot_be_cancelled_and_payment_guards_hold(): void
    {
        $svc = app(VendorBillService::class);
        $user = $this->admin();
        $posted = FinInvoice::where('invoice_type', 'SUPPLIER_BILL')->where('doc_status', 'POSTED')->where('payment_status', '<>', 'PAID')->first();
        if (! $posted) {
            $this->markTestSkipped('No open posted vendor bill in this database.');
        }
        $before = [$posted->doc_status, (float) $posted->paid_amount];

        try {
            $svc->cancel($posted, $user);
            $this->fail('a posted bill must not be cancelled');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $header = ['pay_date' => now()->toDateString(), 'pay_bank' => (int) FinAccount::where('account_code', 'like', '111%')->where('is_postable', true)->value('account_id')];
        try {
            $svc->pay($posted, $header, [$posted->invoice_id => ['pay' => $posted->outstanding() + 1000]], $user);
            $this->fail('paying more than outstanding must be refused');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        try {
            $svc->pay($posted, $header, [999999999 => ['pay' => 10]], $user);
            $this->fail('paying a bill that is not open for this vendor must be refused');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertSame($before, [$posted->fresh()->doc_status, (float) $posted->fresh()->paid_amount]);
    }

    public function test_form_state_mapping(): void
    {
        $svc = app(VendorBillService::class);
        $mk = fn (array $a) => new FinInvoice($a + ['due_date' => now()->addDays(30)]);
        $this->assertSame(['closed', 'Paid'], $svc->formState($mk(['doc_status' => 'POSTED', 'payment_status' => 'PAID'])));
        $this->assertSame(['confirmed', 'Overdue'], $svc->formState($mk(['doc_status' => 'POSTED', 'payment_status' => 'UNPAID', 'due_date' => now()->subDays(3)])));
        $this->assertSame(['confirmed', 'Due this week'], $svc->formState($mk(['doc_status' => 'POSTED', 'payment_status' => 'UNPAID', 'due_date' => now()->addDays(3)])));
        $this->assertSame(['confirmed', 'Partially paid'], $svc->formState($mk(['doc_status' => 'POSTED', 'payment_status' => 'PARTIAL'])));
        $this->assertSame(['confirmed', 'Open'], $svc->formState($mk(['doc_status' => 'POSTED', 'payment_status' => 'UNPAID'])));
    }

    public function test_form_grid_and_save_route_work_for_admin(): void
    {
        $this->actingAs($this->admin());
        $s = $this->supplier();
        $this->get(route('v2.screen', 'acc-payables'))->assertOk();
        $this->get(route('v2.screen', 'acc-payables-form').'?new=1')->assertOk()->assertSee('Start a vendor bill');
        $this->get(route('v2.screen', 'acc-payables-form').'?supplier='.$s->id_supplier)->assertOk()->assertSee('New Vendor Bill');

        $this->post(route('v2.acc.payables.save'), $this->input(['partner_ref' => 'ZZTEST-ROUTE']))->assertRedirect();
        $b = FinInvoice::where('partner_ref', 'ZZTEST-ROUTE')->firstOrFail();
        $this->get(route('v2.screen', 'acc-payables-form').'?id='.$b->invoice_id)->assertOk()->assertSee('Vendor Bill');

        $this->post(route('v2.acc.payables.save'), $this->input(['partner_ref' => 'ZZTEST-ROUTE']))->assertSessionHasErrors('bill');

        $this->post(route('v2.acc.payables.save'), ['id' => $b->invoice_id, 'action' => 'cancel'])->assertRedirect();
        $this->assertSame('CANCELLED', $b->fresh()->doc_status);

        $posted = FinInvoice::where('invoice_type', 'SUPPLIER_BILL')->where('doc_status', 'POSTED')->first();
        if ($posted) {
            $this->get(route('v2.screen', 'acc-payables-form').'?id='.$posted->invoice_id)->assertOk()->assertSee($posted->invoice_number);
        }
    }

    public function test_grid_opens_v2_form_and_v1_is_gone(): void
    {
        $grid = Grids::find('acc-payables');
        $this->assertStringContainsString('/v2/s/acc-payables-form?id={id}', $grid->openUrl());
        $this->assertStringContainsString('/v2/s/acc-payables-form?new=1', $grid->newUrl());

        $this->assertFileDoesNotExist(resource_path('views/pages/finance/payables.blade.php'));
        $this->assertArrayNotHasKey('FIN_AP', MenuRoutes::map());
        $this->actingAs($this->admin())->get('/finance/payables')->assertRedirect('/v2/s/acc-payables');
    }
}
