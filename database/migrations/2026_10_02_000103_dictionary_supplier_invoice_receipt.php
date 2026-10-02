<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Terjemahan Indonesia untuk teks English layar Supplier Invoice Receipt v2 dan tombol pindah format di halaman cetak
// (Goods Receipt / QC Inspection, Invoice / Receipt). Nama dokumen / menu dan status tetap English.
// Hanya mengisi yang belum punya terjemahan.
return new class extends Migration
{
    private const UI = [
        'Supplier Invoice Receipt' => 'Tanda Terima Tagihan Supplier', 'Search receipt no., supplier, invoice no...' => 'Cari no tanda terima, supplier, no invoice...',
        'Receipt No. / Supplier' => 'No Tanda Terima / Supplier', 'Handed Over By' => 'Diserahkan oleh', 'Planned Payment' => 'Rencana Bayar', 'Payment Method' => 'Cara Bayar',
        'Supplier Invoice No.' => 'No Invoice Supplier', 'Invoice Date' => 'Tgl Invoice', 'Tax Invoice No.' => 'No Faktur Pajak', 'Invoice' => 'Invoice',
        'Choose the supplier who hands over the invoices. The invoices are added on the next screen.' => 'Pilih supplier yang menyerahkan invoice. Invoice ditambahkan di layar berikutnya.',
        'Receipt No.' => 'No Tanda Terima', 'Name of the supplier\'s person' => 'Nama orang dari supplier', 'Payment Term' => 'Syarat Bayar', 'Cancel Reason' => 'Alasan Batal',
        'Attachments' => 'Lampiran', 'Original invoice' => 'Invoice asli', 'Tax invoice' => 'Faktur Pajak', 'Delivery note / goods receipt' => 'Surat Jalan / SPB',
        'Payment receipt (kuitansi)' => 'Kuitansi', 'Bank Transfer' => 'Transfer', 'Giro / Cheque' => 'Giro / Cek', '+ Invoice' => '+ Invoice',
        'Recorded Bill (optional)' => 'Tagihan Tercatat (boleh kosong)',
        'Pick a recorded bill and leave the other columns empty: Save fills in its number, date, tax invoice and amount. An invoice that is not recorded yet is typed in directly.' => 'Pilih tagihan yang sudah dicatat dan biarkan kolom lain kosong: Save mengisi nomor, tanggal, faktur pajak, dan nilainya. Invoice yang belum dicatat diketik langsung.',
        'Draft created. Add the invoices, then Confirm.' => 'Draft dibuat. Tambahkan invoice, lalu Confirm.', 'Receipt cancelled.' => 'Tanda terima dibatalkan.',
        'Confirmed. The receipt is ready to print.' => 'Sudah Confirm. Tanda terima siap dicetak.',
        'Add at least one supplier invoice.' => 'Tambahkan minimal satu invoice supplier.', 'Every invoice needs its amount.' => 'Setiap invoice wajib diisi nilainya.',
        'The same invoice number appears twice.' => 'Nomor invoice yang sama muncul dua kali.', 'One of the invoices is already on another receipt.' => 'Salah satu invoice sudah ada di tanda terima lain.',
        'Only a Draft can be confirmed.' => 'Hanya Draft yang bisa di-Confirm.', 'Only a Confirmed receipt can be cancelled.' => 'Hanya tanda terima Confirmed yang bisa di-Cancel.',
        'Fill in the reason for cancelling.' => 'Isi alasan pembatalan.',
        // tombol pindah format di halaman cetak
        'Goods Receipt' => 'Penerimaan Barang', 'QC Inspection' => 'QC Inspection', 'Receipt' => 'Tanda Terima',
    ];

    public function up(): void
    {
        if (! Schema::connection('tm2sys')->hasTable('sys_dictionary')) {
            return;
        }
        $db = DB::connection('tm2sys');
        foreach (self::UI as $en => $id) {
            $db->table('sys_dictionary')->insertOrIgnore(['context' => 'ui', 'source_en' => $en, 'created_at' => now(), 'created_by' => 'migration']);
            $key = $db->table('sys_dictionary')->where('context', 'ui')->where('source_en', $en)->value('id_dictionary');
            if (! $db->table('sys_dictionary_lang')->where('id_dictionary', $key)->where('lang_code', 'id')->where('text', '<>', '')->exists()) {
                $db->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $key, 'lang_code' => 'id'], ['text' => $id]);
            }
        }
        Dictionary::flush();
    }

    public function down(): void
    {
        // isi kamus dibiarkan (bisa sudah diubah user)
    }
};
