<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Terjemahan Indonesia untuk teks English layar Customer Invoice v2, Company Bank Accounts, Document Number Series dan isian
// qty diterima di Proof of Delivery. Nama dokumen / menu, tombol, dan status tetap English. Hanya mengisi yang belum punya
// terjemahan. Sekaligus: menu Down Payment lama dimatikan -- uang muka sekarang dibuat di Customer Invoice (tagihan uang
// muka tidak lagi menjurnal piutang saat terbit; keputusan user 2026-10-02, customerinvoice_accounting.md).
return new class extends Migration
{
    private const UI = [
        // daftar
        'Search invoice no., customer, order no...' => 'Cari no invoice, customer, no order...', 'Invoice No. / Customer' => 'No Invoice / Customer',
        'Outstanding' => 'Sisa Tagihan', 'Qty Received' => 'Qty Diterima', 'Qty Invoiced' => 'Qty Ditagih', 'Partially paid' => 'Dibayar sebagian',
        'Due this week' => 'Jatuh tempo minggu ini', 'Waiting payment' => 'Menunggu pembayaran', 'Overdue' => 'Lewat jatuh tempo',
        // + New Record
        '+ New Record — Customer Invoice' => '+ New Record — Customer Invoice',
        'Delivery note lines the customer has received and that are not invoiced yet. Set Invoice to No for a line you do not bill now.' => 'Baris Surat Jalan yang sudah diterima customer dan belum ditagih. Ubah Invoice menjadi Tidak untuk baris yang belum ditagih sekarang.',
        'Invoice: choose the order. Only orders with delivery notes the customer has received (Proof of Delivery) and that are not fully invoiced are listed.' => 'Invoice: pilih order. Yang tampil hanya order yang Surat Jalannya sudah diterima customer (Proof of Delivery) dan belum habis ditagih.',
        'Down Payment: choose the confirmed order the customer pays in advance for. A down payment invoice posts no journal; the journal is made when the money is received.' => 'Down Payment: pilih order Confirmed yang dibayar di muka. Tagihan uang muka tidak menjurnal; jurnal dibuat saat uangnya diterima.',
        'Delivery Note Lines' => 'Baris Surat Jalan', 'Shipped' => 'Dikirim', 'Already Invoiced' => 'Sudah Ditagih', 'To Invoice' => 'Akan Ditagih',
        'No order has a received delivery note left to invoice. Record the Proof of Delivery first.' => 'Tidak ada order dengan Surat Jalan diterima yang belum ditagih. Catat Proof of Delivery lebih dulu.',
        'No confirmed order found.' => 'Tidak ada order Confirmed.',
        // form
        'In Words' => 'Terbilang', 'Summary' => 'Ringkasan', 'VAT' => 'PPN', 'Payments' => 'Pembayaran', 'Journal' => 'Jurnal', 'Price' => 'Harga', 'Paid' => 'Sudah Dibayar',
        '(on Confirm)' => '(saat Confirm)', 'Invoice Date' => 'Tgl Invoice', 'Bill Amount' => 'Nilai Tagihan', 'Balance Due' => 'Sisa Tagihan',
        'This invoice was made on the old Order to Cash screen: it has one amount and no delivery note lines.' => 'Invoice ini dibuat di layar Order to Cash lama: hanya satu nilai, tanpa baris Surat Jalan.',
        'Identity' => 'Identitas', 'Transfer To' => 'Transfer ke',
        'Printed on the invoice. The default follows the tax identity (Finance > Company Bank Accounts).' => 'Dicetak di invoice. Bawaan mengikuti identitas pajak (Finance > Company Bank Accounts).',
        'Bill To Address' => 'Alamat Tagih', 'Total Order Amount' => 'Nilai Order', 'Billed Before' => 'Sudah Ditagih Sebelumnya', 'Percent of Order' => 'Persen dari Order',
        'Leave empty to type the amount yourself.' => 'Kosongkan bila nilainya diketik sendiri.',
        'Used when Percent is empty. Includes VAT on a VAT order.' => 'Dipakai bila Persen kosong. Sudah termasuk PPN untuk order PPN.',
        'A down payment invoice posts no journal and is not a receivable. The journal is made when the money is received (Record Receipt).' => 'Tagihan uang muka tidak menjurnal dan bukan piutang. Jurnal dibuat saat uangnya diterima (Record Receipt).',
        'Other Charges' => 'Biaya Lain', 'Other Charges Note' => 'Keterangan Biaya Lain',
        'Freight or other cost billed to the customer; part of the tax base.' => 'Ongkos kirim atau biaya lain yang ditagihkan ke customer; masuk dasar pajak.',
        'VAT Rate' => 'Tarif PPN', 'Tax Base' => 'Dasar Pajak',
        'Given by Coretax after the tax invoice is uploaded. May be filled in later.' => 'Diberikan Coretax sesudah Faktur Pajak diunggah. Boleh diisi belakangan.',
        'Event' => 'Peristiwa', 'Document No.' => 'No Dokumen', 'No money received for this document yet.' => 'Belum ada uang yang diterima untuk dokumen ini.',
        'No journal: a down payment invoice is a request for payment. The journal appears here after Record Receipt.' => 'Belum ada jurnal: tagihan uang muka adalah permintaan pembayaran. Jurnal tampil di sini sesudah Record Receipt.',
        'Narration' => 'Keterangan', 'No journal yet.' => 'Belum ada jurnal.', 'Invoiced Before' => 'Ditagih Sebelumnya',
        'The price comes from the Customer Order. Change Qty Invoiced when you bill less than what is left; Save recalculates the amounts. A lump sum line (Qty Minim) is billed once, for its price.' => 'Harga dari Customer Order. Ubah Qty Invoiced bila menagih kurang dari sisanya; Save menghitung ulang nilainya. Baris borongan (Qty Minim) ditagih satu kali sebesar harganya.',
        'Down Payment of This Order' => 'Uang Muka Order Ini', 'Down Payment No.' => 'No Uang Muka', 'Used Before' => 'Terpakai Sebelumnya', 'Remaining' => 'Sisa',
        'Apply to This Invoice' => 'Dipotong di Invoice Ini', 'Price part' => 'Bagian harga', 'Not yet received' => 'Belum diterima',
        'lump sum' => 'borongan', 'on Confirm' => 'saat Confirm', 'Down payment received' => 'Uang muka diterima', 'Applied to invoice' => 'Dipotong di invoice',
        'Down payment applied' => 'Uang muka dipotong',
        // Record Receipt
        'Received Date' => 'Tgl Terima', 'Amount Received' => 'Nilai Diterima', 'Received In' => 'Diterima di', 'Tax Invoice No. (Down Payment)' => 'No Faktur Pajak (Uang Muka)',
        'May be filled in later.' => 'Boleh diisi belakangan.',
        'Confirm Receipt makes the bank receipt voucher and the journal: Bank to Customer Down Payment. After that the Down Payment Receipt can be printed.' => 'Confirm Receipt membuat bukti bank masuk dan jurnal: Bank pada Uang Muka Penjualan. Sesudah itu Tanda Terima Uang Muka bisa dicetak.',
        'Confirm Receipt makes the bank receipt voucher and the journal: Bank to Customer Down Payment and Output VAT. After that the Down Payment Receipt can be printed.' => 'Confirm Receipt membuat bukti bank masuk dan jurnal: Bank pada Uang Muka Penjualan dan PPN Keluaran. Sesudah itu Tanda Terima Uang Muka bisa dicetak.',
        // pesan
        'Choose at least one delivery note line to invoice.' => 'Pilih minimal satu baris Surat Jalan untuk ditagih.', 'Draft created. Check it, then Confirm.' => 'Draft dibuat. Periksa, lalu Confirm.',
        'Document cancelled.' => 'Dokumen dibatalkan.', 'Confirmed. The number is issued and the document is ready to print.' => 'Sudah Confirm. Nomor terbit dan dokumen siap dicetak.',
        'Down payment received. The receipt is ready to print.' => 'Uang muka diterima. Tanda terima siap dicetak.',
        'Nothing to invoice: no received delivery note line is left for this order.' => 'Tidak ada yang bisa ditagih: tidak ada baris Surat Jalan diterima yang tersisa untuk order ini.',
        'This order has no value yet.' => 'Order ini belum punya nilai.', 'This document is already Confirmed and cannot be changed.' => 'Dokumen ini sudah Confirm dan tidak bisa diubah.',
        'This bank account is not for this tax identity.' => 'Rekening ini bukan untuk identitas pajak ini.',
        'The down payment cannot be more than 100% of the order.' => 'Uang muka tidak boleh lebih dari 100% nilai order.',
        'Only a Confirmed document can be cancelled.' => 'Hanya dokumen Confirmed yang bisa di-Cancel.',
        'This down payment has been received. Refunding a down payment is not available yet.' => 'Uang muka ini sudah diterima. Pengembalian uang muka belum tersedia.',
        'This invoice already has a payment. Correct it with a credit note instead.' => 'Invoice ini sudah ada pembayarannya. Koreksi lewat credit note.',
        'Only a Confirmed down payment invoice can receive money.' => 'Hanya tagihan uang muka Confirmed yang bisa menerima uang.',
        'The amount received must be more than zero.' => 'Nilai diterima harus lebih dari nol.',
        'The amount received is more than what is left of this down payment invoice.' => 'Nilai diterima melebihi sisa tagihan uang muka ini.',
        'Choose the bank account that received the money.' => 'Pilih rekening yang menerima uang.',
        'This bank account has no ledger account yet (Finance > Company Bank Accounts).' => 'Rekening ini belum punya akun buku besar (Finance > Company Bank Accounts).',
        // Company Bank Accounts
        'Account No.' => 'No Rekening', 'Account Holder' => 'Atas Nama', 'Tax Identity' => 'Identitas Pajak', 'Ledger Account' => 'Akun Buku Besar', 'Both' => 'Keduanya',
        'Company bank accounts. Tax Identity = which documents may use the account (PPN, Non PPN, Both). Default = proposed on a new invoice of that identity. Ledger Account = its code in Master of Accounts; without it the account cannot receive money.' => 'Rekening perusahaan. Tax Identity = dokumen mana yang boleh memakainya (PPN, Non PPN, Both). Default = diusulkan di invoice baru identitas itu. Ledger Account = kodenya di Master of Accounts; tanpa itu rekening tidak bisa menerima uang.',
        // Document Number Series
        'Series Name' => 'Nama Seri', 'Number Format' => 'Format Nomor', 'Restart' => 'Urut Ulang', 'Every month' => 'Tiap bulan', 'Every year' => 'Tiap tahun', 'Never' => 'Tidak pernah',
        'Number format of Customer Invoice and Down Payment Invoice. Tokens: {SEQ} running number, {SEQ:5} padded to 5 digits, {ROMAN} Roman month, {MM} month, {YY} / {YYYY} year. The number is issued on Confirm and never reused.' => 'Format nomor Customer Invoice dan Down Payment Invoice. Token: {SEQ} nomor urut, {SEQ:5} urut 5 digit, {ROMAN} bulan romawi, {MM} bulan, {YY} / {YYYY} tahun. Nomor terbit saat Confirm dan tidak pernah dipakai ulang.',
        // Proof of Delivery
        'Received Quantity' => 'Qty Diterima',
        'Result Received: every line counts as fully received. Result Partially received: type the quantity the customer really received per line. The received quantity is what can be invoiced.' => 'Hasil Received: semua baris dianggap diterima penuh. Hasil Partially received: ketik qty yang benar-benar diterima customer per baris. Qty diterima adalah yang bisa ditagih.',
    ];

    public function up(): void
    {
        $db = DB::connection('tm2sys');
        // Uang muka dibuat di Customer Invoice; layar Down Payment lama menjurnal piutang saat tagihan terbit (cara lama).
        if ($db->table('sys_app_menu')->where('code', 'FIN_AR_INVOICE')->exists()) {
            $db->table('sys_app_menu')->where('code', 'FIN_DP')->update(['activate' => 0, 'status_note' => 'Replaced by Customer Invoice (type Down Payment), 2026-10-02', 'status_updated_at' => now()]);
        }
        if (! Schema::connection('tm2sys')->hasTable('sys_dictionary')) {
            return;
        }
        foreach (self::UI as $en => $id) {
            $db->table('sys_dictionary')->insertOrIgnore(['context' => 'ui', 'source_en' => $en, 'created_at' => now(), 'created_by' => 'migration']);
            $key = $db->table('sys_dictionary')->where('context', 'ui')->where('source_en', $en)->value('id_dictionary');
            if ($key && ! $db->table('sys_dictionary_lang')->where('id_dictionary', $key)->where('lang_code', 'id')->where('text', '<>', '')->exists()) {
                $db->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $key, 'lang_code' => 'id'], ['text' => $id]);
            }
        }
        Dictionary::flush();
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'FIN_DP')->update(['activate' => 1, 'status_note' => null]);
    }
};
