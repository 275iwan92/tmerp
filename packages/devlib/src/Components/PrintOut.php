<?php

namespace Devlib\Components;

use Devlib\Terbilang;

// Dokumen cetak (nota_co_mockup, nota_pv_spb, printnotastandard). Layar cukup deklarasi isi dokumen.
// company / companyPpn: ['name','tagline','address','contact','npwp','initials','logo','logoBw'] -- Non PPN vs PPN (kop berganti, keputusan user 2026-09-23).
//   logo (url) diisi = gambar logo menggantikan kotak inisial + nama + tagline; logoBw = versi hitam-putih, tampil saat Hemat Tinta.
// parties: [label => nilai | [baris..]] (2 kolom).  columns: [['key','label','type' => text|number|money,
//   'price' => true (hilang saat "Tanpa Harga"), 'sub' => kolom baris kecil kedua, 'group' => judul header atas, 'width']]
// totals: ['of' => kolom, 'discount' => angka, 'ppn' => 11, 'extra' => [[label, angka]], 'net' => 'Net Diterima']
// Halaman dipotong otomatis per ukuran kertas; header tabel diulang; total + tanda tangan di halaman terakhir.
// Laporan (x-dl::report): criteria [label => nilai] (kotak kriteria), summary [['label', 'sum' => kolom | 'value', 'signed']],
//   groups [kolom => label] (dipilih di bilah, subtotal per kelompok), groupBy, opening (saldo awal kolom berjalan).
//   Kolom tambahan: 'sum' => true, 'signed' => true (+ hijau / - merah), 'type' => badge + 'tones' [nilai => tone],
//   'rowTones' [nilai => tone] (sorot baris), 'running' => 'in - out + adj' (+ 'prev' => kolom saldo awal baris).
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
        public array $criteria = [],
        public array $summary = [],
        public array $groups = [],
        public ?string $groupBy = null,
        public ?float $opening = null,
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
            'currency' => Terbilang::CURRENCIES[$this->currency] ?? null, 'hasPpnKop' => (bool) $this->companyPpn,
            'summary' => $this->summary, 'groups' => (object) $this->groups, 'opening' => $this->opening];
    }

    public function render()
    {
        return view('devlib::components.print-out');
    }
}
