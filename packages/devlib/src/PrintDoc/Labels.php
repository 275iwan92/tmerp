<?php

namespace Devlib\PrintDoc;

// Istilah baku CETAKAN dokumen dalam bahasa Indonesia (teks English -> Indonesia), terpisah dari kamus layar: kata yang
// sama boleh berbeda di kertas (mis. "Qty" di layar, "Jumlah" di cetakan yang disetujui user 2026-10-01).
// Bahasa lain memakai kamus aplikasi (Devlib\Lang::text). Dokumen aplikasi menambah istilahnya lewat Document::labels().
final class Labels
{
    public const ID = [
        'Date' => 'Tanggal',
        'Item Name' => 'Nama Barang',
        'Qty' => 'Jumlah',
        'Unit' => 'Satuan',
        'Price' => 'Harga',
        'Subtotal' => 'Subtotal',
        'Discount' => 'Diskon',
        'VAT' => 'PPN',
        'Income Tax 23' => 'PPh 23',
        'Total' => 'Total',
        'In Words' => 'Terbilang',
        'Notes' => 'Catatan',
        'Approved By' => 'Menyetujui',
        'Acknowledged By' => 'Mengetahui',
        'Prepared By' => 'Pembuat',
        'Printed on' => 'Dicetak pada',
        'Sheet' => 'Lembar',
        'Scan for' => 'Scan untuk',
        'online document' => 'dokumen online',
        'Remarks' => 'Keterangan',
        'Phone / Attn' => 'Telp / UP',
        'Phone' => 'Telp',
    ];
}
