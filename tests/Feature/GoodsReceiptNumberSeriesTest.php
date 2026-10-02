<?php

namespace Tests\Feature;

use App\Models\Pur\PvReceipt;
use App\Support\V2\Print\GoodsReceiptPrint;
use Tests\TestCase;

// Seri nomor penerimaan per jenis (user 2026-10-02): SPB hanya untuk penerimaan dari supplier supaya nomornya urut saat
// accounting memeriksa tagihan supplier; bahan baku customer = PBC, barang dari subkon = PBS. Hanya membaca database.
class GoodsReceiptNumberSeriesTest extends TestCase
{
    public function test_each_receipt_type_has_its_own_number_series(): void
    {
        $yy = now()->format('y');
        $this->assertMatchesRegularExpression("/^SPB{$yy}\\d{5}$/", PvReceipt::nextDocNo());
        $this->assertMatchesRegularExpression("/^SPB{$yy}\\d{5}$/", PvReceipt::nextDocNo('PO'));
        $this->assertMatchesRegularExpression("/^PBC{$yy}\\d{5}$/", PvReceipt::nextDocNo('CUSTOMER'));
        $this->assertMatchesRegularExpression("/^PBS{$yy}\\d{5}$/", PvReceipt::nextDocNo('SUBCON'));
    }

    public function test_the_supplier_series_is_not_advanced_by_the_other_types(): void
    {
        $last = PvReceipt::where('doc_no', 'like', 'SPB'.now()->format('y').'%')->orderByDesc('doc_no')->value('doc_no');
        $expected = 'SPB'.now()->format('y').str_pad((string) (($last ? (int) substr($last, 5) : 0) + 1), 5, '0', STR_PAD_LEFT);
        $this->assertSame($expected, PvReceipt::nextDocNo('PO'));
        $this->assertNotSame(substr($expected, 0, 3), substr(PvReceipt::nextDocNo('CUSTOMER'), 0, 3));
    }

    public function test_the_print_names_the_number_by_receipt_type(): void
    {
        $this->assertSame('SPB No.', GoodsReceiptPrint::numberLabelFor('PO'));
        $this->assertSame('Receiving No.', GoodsReceiptPrint::numberLabelFor('CUSTOMER'));
        $this->assertSame('Receiving No.', GoodsReceiptPrint::numberLabelFor('SUBCON'));
        $this->assertSame('CO No.', GoodsReceiptPrint::referenceLabelFor('CUSTOMER'));
    }
}
