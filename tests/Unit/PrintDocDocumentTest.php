<?php

namespace Tests\Unit;

use Devlib\PrintDoc\Document;
use InvalidArgumentException;
use Tests\TestCase;

// Objek cetak dokumen DevLib (Devlib\PrintDoc\Document): aturan kertas, lembar, total, terbilang, urutan tanda tangan.
// Tanpa database: dokumen contoh dibuat di sini, labelnya hanya istilah baku yang ada di Devlib\PrintDoc\Labels.
class PrintDocDocumentTest extends TestCase
{
    private function plain(array $rows, array $data = []): Document
    {
        return new class(['number' => 'X1', 'date' => '2026-10-01', 'rows' => $rows] + $data) extends Document
        {
            public string $title = 'Item Name';

            public array $extra = [['key' => 'remark', 'label' => 'Remarks', 'width' => 84]];

            public array $receivers = [['Remarks', ''], ['Notes', '']];
        };
    }

    private function priced(array $rows, array $data = []): Document
    {
        return new class(['number' => 'X2', 'date' => '2026-10-01', 'rows' => $rows] + $data) extends Document
        {
            public string $title = 'Item Name';

            public array $extra = [['key' => 'remark', 'label' => 'Remarks', 'width' => 38]];

            public bool $priced = true;
        };
    }

    private function rows(int $n): array
    {
        return array_map(fn ($i) => ['name' => 'Item '.$i, 'qty' => 10, 'unit' => 'PCS', 'price' => 100.5, 'subtotal' => 1005], range(1, $n));
    }

    public function test_one_to_five_rows_print_a5_landscape_more_rows_print_a4_portrait(): void
    {
        $this->assertSame('a5', $this->plain($this->rows(1))->paper());
        $this->assertSame('a5', $this->plain($this->rows(5))->paper());
        $this->assertSame('a4', $this->plain($this->rows(6))->paper());
    }

    public function test_sheets_are_padded_to_capacity_and_only_the_last_sheet_closes_the_document(): void
    {
        $a5 = $this->plain($this->rows(2))->sheets();
        $this->assertCount(1, $a5);
        $this->assertCount(5, $a5[0]['rows']);
        $this->assertNull($a5[0]['rows'][4]);
        $this->assertTrue($a5[0]['last']);

        $a4 = $this->plain($this->rows(30))->sheets();
        $this->assertCount(2, $a4);
        $this->assertCount(22, $a4[0]['rows']);
        $this->assertFalse($a4[0]['last']);
        $this->assertSame(22, $a4[1]['from']);
        $this->assertTrue($a4[1]['last']);
        $this->assertSame([2, 2], [$a4[1]['page'], $a4[1]['pages']]);
    }

    public function test_variant_columns_must_fill_the_fixed_zone_width(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new class(['rows' => []]) extends Document
        {
            public array $extra = [['key' => 'remark', 'label' => 'Remarks', 'width' => 60]];
        };
    }

    public function test_documents_without_amounts_have_no_total_and_no_amount_in_words(): void
    {
        $doc = $this->plain($this->rows(2));
        $this->assertSame([], $doc->totals()['lines']);
        $this->assertNull($doc->totals()['grand']);
        $this->assertSame([], $doc->notes());
    }

    public function test_priced_documents_total_with_tax_and_spell_the_amount_down_to_sen(): void
    {
        $doc = $this->priced([['name' => 'A', 'qty' => 23, 'unit' => 'RIM', 'price' => 1567737.28, 'subtotal' => 36057957.44]], ['ppn' => 11]);
        $totals = $doc->totals();
        $this->assertEqualsWithDelta(40024332.76, $totals['grand'], 0.001);
        $this->assertCount(3, $totals['lines']);
        $this->assertTrue($totals['lines'][2][2]);
        $this->assertStringContainsString('tujuh puluh enam sen', $doc->amountWords($totals['grand']));
        $this->assertSame(1, count($doc->notes()));
    }

    // Ketentuan tetap user 2026-10-01: dari kiri = pihak luar, Menyetujui, Mengetahui, Pembuat (nama = user yang login).
    public function test_signatures_run_outside_party_approver_acknowledger_maker(): void
    {
        $sign = $this->plain($this->rows(1), ['printedBy' => 'Yuna', 'approvedBy' => 'Hendra', 'knownBy' => 'Wiwik'])->signatures();
        $this->assertCount(5, $sign);
        $this->assertSame([null, null, 'Hendra', 'Wiwik', 'Yuna'], array_column($sign, 2));
        $this->assertStringContainsString('Yuna', $this->plain($this->rows(1), ['printedBy' => 'Yuna'])->printedLine());
    }
}
