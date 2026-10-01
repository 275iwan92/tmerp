<?php

namespace App\Support\V2\Print;

// Istilah cetakan dokumen dalam bahasa Indonesia (teks English di kelas cetak => Indonesia), persis seperti format yang
// disetujui user 2026-10-01 (docs/mockups/print_doc). Istilah baku semua dokumen ada di Devlib\PrintDoc\Labels.
final class Labels
{
    public const ID = [
        // Surat Jalan
        'Delivery Note' => 'Surat Jalan',
        'Delivery Note No.' => 'No Surat Jalan',
        'Customer PO No.' => 'No PO Customer',
        'CO Ref.' => 'Ref. CO',
        'Vehicle' => 'Kendaraan',
        'Driver' => 'Pengemudi',
        'Departed' => 'Diberangkatkan',
        'Ship To' => 'Dikirim ke',
        'Complaints no later than 7 days after the goods are received' => 'Pengaduan maksimum 7 hari setelah barang diterima',
        'Weight (kg)' => 'Berat (kg)',
        'Bundles' => 'Ikat',
        'Receiver' => 'Penerima',
        'online document & proof of receipt' => 'dokumen online & bukti terima',
        // Customer Order
        'Sales Order · No Prices' => 'Sales Order · Tanpa Harga',
        'CO No.' => 'No CO',
        'Quotation Ref.' => 'Ref. Quotation',
        'Payment Terms' => 'Syarat Bayar',
        'Payment Payable To' => 'Pembayaran a.n.',
        'The order is binding once confirmed. Changes of quantity / specification through a written revision.' => 'Pesanan mengikat setelah dikonfirmasi. Perubahan jumlah / spesifikasi melalui revisi tertulis.',
        'Product Name' => 'Nama Produk',
        'Type' => 'Type',
        'Ship Date' => 'Tgl Kirim',
        'Ordered By' => 'Pemesan',
        'Other Costs' => 'Biaya Lain',
        // Customer Plan Delivery
        'Delivery Plan · No Prices' => 'Rencana Kirim · Tanpa Harga',
        'CO Date' => 'Tanggal CO',
        'SPLIT = one product shipped in stages (thick bar on the left). QTY is the quantity per shipment.' => 'SPLIT = satu produk dikirim bertahap (garis tebal di kiri). JUMLAH adalah jumlah per kiriman.',
        'Shipment' => 'Kiriman',
        'Time' => 'Jam',
        'CO Due' => 'Due CO',
        'Full' => 'Penuh',
        'Agrees to the schedule' => 'Menyetujui jadwal',
        // Inquiry
        'Product Request · Internal' => 'Permintaan Produk · Internal',
        'Inquiry No.' => 'No Inquiry',
        'Kind' => 'Jenis',
        'Source' => 'Sumber',
        'Quotation Deadline' => 'Batas Quotation',
        'Product Type' => 'Tipe Produk',
        'Internal document, without prices. Prices are issued in the Quotation after Pro-Dev and Pro-Cost are done.' => 'Dokumen internal, tanpa harga. Harga hanya terbit di Quotation setelah Pro-Dev dan Pro-Cost selesai.',
        'Product / Part' => 'Nama Produk / Part',
        'Finished Size (mm)' => 'Ukuran Jadi (mm)',
        'Material' => 'Bahan',
        'Colour' => 'Warna',
        'Received By' => 'Diterima',
        'Alternative Qty' => 'Qty alternatif',
        'Tax / Material' => 'Pajak / Bahan',
        'Sales Notes' => 'Catatan Sales',
        'own material' => 'bahan sendiri',
        'customer material' => 'bahan dari customer',
        // Purchase Requisition
        'Purchase Request · Internal' => 'Permintaan Pembelian · Internal',
        'PR No.' => 'No PR',
        'For' => 'Untuk',
        'Requested By' => 'Pemohon',
        'Requested by' => 'Diminta oleh',
        'Prices are estimates. Final prices follow the Purchase Quotation and the Purchase Order.' => 'Harga adalah estimasi. Harga final mengikuti Purchase Quotation dan Purchase Order.',
        'Needed Date' => 'Tgl Perlu',
        'Production' => 'Produksi',
        'Non Production' => 'Non Produksi',
        'Level' => 'Level',
        // Purchase Order
        'Order Letter' => 'Surat Pesanan',
        'PO No.' => 'No PO',
        'PR Ref.' => 'Ref. PR',
        'Delivered / Picked Up' => 'Dikirim / Diambil',
        'Estimated Date' => 'Estimasi Tanggal',
        'Destination' => 'Tujuan',
        'To' => 'Kepada Yth.',
        'Dear Sir / Madam, we hereby order the following goods:' => 'Dengan hormat, dengan ini kami memesan barang sebagai berikut:',
        'Goods / Services' => 'Nama Barang / Jasa',
        'Disc.' => 'Disk.',
        'Accepting the order' => 'Menerima pesanan',
        'Thank you for your cooperation.' => 'Atas kerjasamanya kami ucapkan terima kasih.',
    ];
}
