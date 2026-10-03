<?php

namespace App\Support;

use Devlib\PrintDoc\Document;

// Contoh pemakaian Devlib\PrintDoc\Document untuk halaman demo x-dl::print-doc: satu dokumen = satu kelas turunan yang
// hanya mendeklarasikan field; data contoh diserahkan saat dibuat. Aplikasi (tmerp) membaca datanya dari database.
final class DemoPrintDocs
{
    // Istilah Indonesia milik dokumen contoh (teks English => Indonesia); istilah baku sudah ada di Devlib\PrintDoc\Labels.
    private const LABELS = [
        'Delivery Note' => 'Surat Jalan', 'Delivery Note No.' => 'No Surat Jalan', 'Customer PO No.' => 'No PO Customer', 'CO Ref.' => 'Ref. CO', 'Vehicle' => 'Kendaraan',
        'Driver' => 'Pengemudi', 'Departed' => 'Diberangkatkan', 'Ship To' => 'Dikirim ke', 'Weight (kg)' => 'Berat (kg)', 'Bundles' => 'Ikat', 'Receiver' => 'Penerima',
        'Complaints no later than 7 days after the goods are received' => 'Pengaduan maksimum 7 hari setelah barang diterima',
        'PO No.' => 'No PO', 'PR Ref.' => 'Ref. PR', 'Payment Terms' => 'Syarat Bayar', 'Delivered / Picked Up' => 'Dikirim / Diambil', 'Estimated Date' => 'Estimasi Tanggal',
        'Destination' => 'Tujuan', 'To' => 'Kepada Yth.', 'Goods / Services' => 'Nama Barang / Jasa', 'Disc.' => 'Disk.', 'Ship Date' => 'Tgl Kirim',
        'Accepting the order' => 'Menerima pesanan', 'Dear Sir / Madam, we hereby order the following goods:' => 'Dengan hormat, dengan ini kami memesan barang sebagai berikut:',
    ];

    public static function boot(): void
    {
        Document::labels(self::LABELS);
        Document::companyUsing(fn (bool $ppn) => $ppn
            ? ['name' => config('devlib.company_ppn.name'), 'initials' => 'LJM', 'lines' => [config('devlib.company_ppn.address'), config('devlib.company_ppn.contact')], 'npwp' => config('devlib.company_ppn.npwp')]
            : ['name' => config('devlib.company.name'), 'initials' => 'TM', 'lines' => [config('devlib.company.tagline'), config('devlib.company.address'), config('devlib.company.contact')], 'npwp' => null]);
    }

    // Tanpa nilai uang: tanpa total; 2 baris -> A5 landscape.
    public static function deliveryNote(string $status = 'CONFIRMED'): Document
    {
        self::boot();

        return new class(['number' => '10262874', 'date' => '2026-10-01', 'status' => $status, 'printedBy' => 'Yuna', 'co_no' => '2602282',
            'party' => ['name' => 'PAK A. YANI', 'lines' => ['PERUMAHAN CITRA KAYANE RESIDENCE', 'MALANG']],
            'rows' => [['name' => 'YAN OB KINGZANA KRIPIK PISANG (DS000)', 'qty' => 508, 'unit' => 'PCS', 'weight_kg' => 12.7, 'bundles' => 6, 'remark' => '100 / bundle'],
                ['name' => 'JD BOX DONAT UK 20 X 20 X 6 CM (DS000)', 'qty' => 3000, 'unit' => 'PCS', 'weight_kg' => 81, 'bundles' => 60, 'remark' => '50 / bundle']]]) extends Document
        {
            public string $type = 'demo-delivery-note';

            public string $title = 'Delivery Note';

            public string $numberLabel = 'Delivery Note No.';

            public array $refs = [['key' => 'customer_po_no', 'label' => 'Customer PO No.', 'hand' => true], ['key' => 'co_no', 'label' => 'CO Ref.'],
                ['key' => 'vehicle', 'label' => 'Vehicle', 'hand' => true], ['key' => 'driver', 'label' => 'Driver', 'hand' => true], ['key' => 'departed_at', 'label' => 'Departed', 'hand' => true]];

            public string $partyLabel = 'Ship To';

            public ?array $side = ['key' => 'contact', 'label' => 'Phone / Attn', 'hand' => true];

            public string $nb = 'Complaints no later than 7 days after the goods are received';

            public array $extra = [['key' => 'weight_kg', 'label' => 'Weight (kg)', 'width' => 21, 'type' => 'money'], ['key' => 'bundles', 'label' => 'Bundles', 'width' => 13, 'type' => 'int'],
                ['key' => 'remark', 'label' => 'Remarks', 'width' => 50]];

            public array $receivers = [['Receiver', 'Customer'], ['Driver', '']];
        };
    }

    // Berharga: harga, PPN (kop badan hukum + NPWP), terbilang; 8 baris -> A4 portrait.
    public static function purchaseOrder(): Document
    {
        self::boot();
        $rows = array_map(fn ($i) => ['name' => 'IVORY 300 GSM 79 X 109 CM · LOT '.$i, 'qty' => 10 + $i, 'unit' => 'RIM', 'price' => 1185000, 'subtotal' => (10 + $i) * 1185000], range(1, 8));

        return new class(['number' => 'PO26000001976', 'date' => '2026-10-01', 'status' => 'CONFIRMED', 'printedBy' => 'Eka', 'approvedBy' => 'Hendra', 'knownBy' => 'Wiwik', 'ppn' => 11,
            'pr_no' => 'PR2600000166', 'payment_term' => 'Net 30', 'ship_to' => 'Main warehouse', 'party' => ['name' => 'PT MAKMUR JAYA USAHA', 'lines' => ['Surabaya']],
            'notes' => [['Notes', 'Thank you for your cooperation.']], 'rows' => $rows]) extends Document
        {
            public string $type = 'demo-purchase-order';

            public string $title = 'Purchase Order';

            public string $numberLabel = 'PO No.';

            public array $refs = [['key' => 'pr_no', 'label' => 'PR Ref.'], ['key' => 'payment_term', 'label' => 'Payment Terms'],
                ['key' => 'handover', 'label' => 'Delivered / Picked Up', 'hand' => true], ['key' => 'delivery_date', 'label' => 'Estimated Date', 'type' => 'date', 'hand' => true],
                ['key' => 'ship_to', 'label' => 'Destination']];

            public string $partyLabel = 'To';

            public ?array $side = ['key' => 'contact', 'label' => 'Phone / Attn'];

            public string $nb = 'Dear Sir / Madam, we hereby order the following goods:';

            public bool $nbPlain = true;

            public string $nameLabel = 'Goods / Services';

            public array $extra = [['key' => 'disc', 'label' => 'Disc.', 'width' => 17], ['key' => 'due_date', 'label' => 'Ship Date', 'width' => 21, 'type' => 'date']];

            public bool $priced = true;

            public array $receivers = [['Supplier', 'Accepting the order']];
        };
    }
}
