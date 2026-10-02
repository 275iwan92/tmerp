<?php

namespace Tests\Feature;

use App\Models\Acc\FinInvoiceReceipt;
use App\Models\Master\SupSupplier;
use App\Models\Sys\SecUser;
use App\Support\V2\Data\SupplierInvoiceReceipt;
use App\Support\V2\Print\PrintDocs;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

// Tanda Terima Tagihan Supplier (App\Support\V2\Data\SupplierInvoiceReceipt): Draft -> baris invoice -> Confirm (nomor lahir)
// -> Cancel wajib alasan; cetakannya menjumlah nilai invoice. Menulis ke tm2acc.fin_invoice_receipt(+_line); semua dokumen
// buatan test dihapus di tearDown (baris ikut terhapus lewat FK cascade). Dilewati bila tabelnya belum ada.
class SupplierInvoiceReceiptTest extends TestCase
{
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::connection('tm2acc')->hasTable('fin_invoice_receipt')) {
            $this->markTestSkipped('Tabel fin_invoice_receipt belum ada di database ini.');
        }
        $this->actingAs(SecUser::query()->where('is_admin', 1)->firstOrFail());
    }

    protected function tearDown(): void
    {
        FinInvoiceReceipt::query()->whereKey($this->ids)->delete();
        parent::tearDown();
    }

    private function draft(): FinInvoiceReceipt
    {
        $supplier = SupSupplier::withoutGlobalScopes()->orderBy('id_supplier')->firstOrFail();
        $r = SupplierInvoiceReceipt::create($supplier->id_supplier);
        $this->ids[] = $r->id_invoice_receipt;

        return $r;
    }

    public function test_draft_is_saved_confirmed_with_a_number_and_cancelled_with_a_reason(): void
    {
        $r = $this->draft();
        $this->assertSame('DRAFT', $r->status());
        $this->assertNotNull(SupplierInvoiceReceipt::act($r, 'confirm'), 'A receipt without invoices must not be confirmed.');

        SupplierInvoiceReceipt::save($r, ['handed_by' => 'Test Courier', 'planned_payment_date' => '2026-11-01', 'payment_method' => 'TRANSFER', 'att_invoice' => '1',
            'lines' => ['new1' => ['supplier_invoice_no' => 'TEST-INV-001', 'invoice_date' => '2026-10-01', 'tax_invoice_no' => '010.000-26.00000001', 'amount' => '1500000.50'],
                'new2' => ['supplier_invoice_no' => 'TEST-INV-002', 'amount' => '499999.50'], 'new3' => ['supplier_invoice_no' => '', 'amount' => '0']]]);
        $r = SupplierInvoiceReceipt::find($r->id_invoice_receipt);
        $this->assertCount(2, $r->lines);
        $this->assertEqualsWithDelta(2000000.00, $r->total(), 0.001);
        $this->assertTrue($r->att_invoice);
        $this->assertFalse($r->att_tax_invoice);

        $this->assertNull(SupplierInvoiceReceipt::act($r, 'confirm'));
        $r->refresh();
        $this->assertSame('CONFIRMED', $r->status());
        $this->assertMatchesRegularExpression('/^TT\d{7}$/', $r->receipt_no);

        $doc = PrintDocs::make('supplier-invoice-receipt', $r->id_invoice_receipt);
        $this->assertEqualsWithDelta(2000000.00, $doc->totals()['grand'], 0.001);
        $this->assertCount(3, $doc->signatures());

        $this->assertNotNull(SupplierInvoiceReceipt::act($r, 'cancel', ''), 'Cancel needs a reason.');
        $this->assertNull(SupplierInvoiceReceipt::act($r, 'cancel', 'Wrong supplier'));
        $this->assertSame('CANCELLED', $r->refresh()->status());
    }

    public function test_confirmed_receipt_cannot_be_changed_or_deleted(): void
    {
        $r = $this->draft();
        SupplierInvoiceReceipt::save($r, ['lines' => ['new1' => ['supplier_invoice_no' => 'TEST-INV-010', 'amount' => '100']]]);
        $this->assertNull(SupplierInvoiceReceipt::act($r->refresh(), 'confirm'));
        $r->refresh();

        try {
            SupplierInvoiceReceipt::save($r, ['handed_by' => 'Someone else']);
            $this->fail('A Confirmed receipt was saved.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        try {
            SupplierInvoiceReceipt::delete($r);
            $this->fail('A Confirmed receipt was deleted.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertNotNull(FinInvoiceReceipt::find($r->id_invoice_receipt));
    }
}
