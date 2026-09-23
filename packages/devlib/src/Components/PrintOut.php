<?php

namespace Devlib\Components;

use Devlib\Terbilang;

// Dokumen cetak (nota_co_mockup, nota_pv_spb, printnotastandard). Layar cukup deklarasi isi dokumen.
// company / companyPpn: ['name','tagline','address','contact','npwp','initials'] -- Non PPN vs PPN (kop berganti, keputusan user 2026-09-23).
// parties: [label => nilai | [baris..]] (2 kolom).  columns: [['key','label','type' => text|number|money,
//   'price' => true (hilang saat "Tanpa Harga"), 'sub' => kolom baris kecil kedua, 'group' => judul header atas, 'width']]
// totals: ['of' => kolom, 'discount' => angka, 'ppn' => 11, 'extra' => [[label, angka]], 'net' => 'Net Diterima']
// Halaman dipotong otomatis per ukuran kertas; header tabel diulang; total + tanda tangan di halaman terakhir.
class PrintOut extends DevlibComponent
{
    public function __construct(
        public string $title = '',
        public ?string $subtitle = null,
        public ?string $number = null,
        public ?string $date = null,
        public ?string $status = null,
        public array $company = [],
        public array $companyPpn = [],
        public bool $ppn = false,
        public array $parties = [],
        public array $columns = [],
        public array $rows = [],
        public array $totals = [],
        public string $currency = 'IDR',
        public array $signatures = [],
        public string $paper = 'a4',
        public bool $prices = true,
        public ?string $printedBy = null,
        public ?string $source = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::print-out';
    }

    public function source(): string
    {
        return $this->source ?? 'dokumen cetak';
    }

    public function config(): array
    {
        return ['columns' => array_map(fn ($c) => $c + ['type' => 'text'], $this->columns), 'rows' => $this->rows, 'totals' => (object) $this->totals,
            'currency' => Terbilang::CURRENCIES[$this->currency] ?? null, 'hasPpnKop' => (bool) $this->companyPpn];
    }

    public function render()
    {
        return view('devlib::components.print-out');
    }
}
