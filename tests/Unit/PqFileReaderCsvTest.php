<?php

namespace Tests\Unit;

use App\Services\Pur\PqFileReader;
use Tests\TestCase;

// File penawaran supplier berbentuk CSV: angka gaya Indonesia ("12.500" = dua belas ribu lima ratus) tidak boleh terbaca 12,5.
// Tidak menyentuh database (hanya file sementara).
class PqFileReaderCsvTest extends TestCase
{
    private array $files = [];

    protected function tearDown(): void
    {
        array_map('unlink', array_filter($this->files, 'is_file'));
        parent::tearDown();
    }

    private function read(string $content): array
    {
        config(['services.anthropic.key' => null]);
        $this->files[] = $path = tempnam(sys_get_temp_dir(), 'pq').'.csv';
        file_put_contents($path, $content);

        return PqFileReader::read($path, 'csv');
    }

    public function test_semicolon_csv_keeps_indonesian_thousands(): void
    {
        $data = $this->read("PENAWARAN HARGA;;;\nNama Barang;Qty;Satuan;Harga\nIvory 300 gsm;10;RIM;12.500\nHVS 58 gsm;5;RIM;1.185.000,50\nTotal;;;1.197.500,50\n");

        $this->assertSame('EXCEL', $data['via']);
        $this->assertCount(2, $data['lines']);
        $this->assertSame('Ivory 300 gsm', $data['lines'][0]['text']);
        $this->assertSame(12500.0, $data['lines'][0]['price']);
        $this->assertSame(10.0, $data['lines'][0]['qty']);
        $this->assertSame('RIM', $data['lines'][0]['uom']);
        $this->assertSame(1185000.5, $data['lines'][1]['price']);
    }

    public function test_comma_csv_with_quoted_english_numbers_and_bom(): void
    {
        $data = $this->read("\xEF\xBB\xBFItem,Qty,Unit,Price\n\"Duplex 260 gsm\",20,RIM,\"1,240,000.00\"\n\"Glue, PVAc\",25,KG,31500\n");

        $this->assertCount(2, $data['lines']);
        $this->assertSame(1240000.0, $data['lines'][0]['price']);
        $this->assertSame('Glue, PVAc', $data['lines'][1]['text']);
        $this->assertSame(31500.0, $data['lines'][1]['price']);
    }

    public function test_number_reads_both_styles(): void
    {
        $this->assertSame(12500.0, PqFileReader::number('12.500'));
        $this->assertSame(12.5, PqFileReader::number('12,5'));
        $this->assertSame(1185000.5, PqFileReader::number('Rp 1.185.000,50'));
        $this->assertSame(1185000.5, PqFileReader::number('1,185,000.50'));
        $this->assertNull(PqFileReader::number(''));
    }
}
