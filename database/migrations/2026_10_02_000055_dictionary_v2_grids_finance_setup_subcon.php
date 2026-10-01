<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Terjemahan Indonesia untuk teks English grid / layar v2 yang dibangun 2026-10-02 (lanjutan): Finance (Cash & Bank, tagihan,
// aset), Setup (Constants, Account, Group), Talent Pool, BOP Rule, Product Category, Subcontract Delivery Note.
// Nama dokumen / menu dan status tetap English. Hanya mengisi yang belum punya terjemahan.
return new class extends Migration
{
    private const UI = [
        // Finance
        'Search voucher no, paid to / received from...' => 'Cari no bukti, dibayar kepada / diterima dari...', 'Voucher No. / Party' => 'No Bukti / Pihak',
        'Cash / Bank Account' => 'Akun Kas / Bank', 'Search invoice no, customer, description...' => 'Cari no tagihan, customer, uraian...',
        'Search invoice no, supplier, description...' => 'Cari no tagihan, supplier, uraian...', 'Invoice No. / Customer' => 'No Tagihan / Customer',
        'Invoice No. / Supplier' => 'No Tagihan / Supplier', 'Due Date' => 'Jatuh Tempo', 'Paid' => 'Dibayar', 'Outstanding' => 'Sisa Tagihan', 'Days Overdue' => 'Hari Terlambat',
        'DP Not Yet Applied' => 'DP Belum Dipotong', 'Search asset code, asset name...' => 'Cari kode aset, nama aset...', 'Asset Code / Category' => 'Kode Aset / Kategori',
        'Asset Name' => 'Nama Aset', 'Acquired' => 'Tanggal Perolehan', 'Useful Life (months)' => 'Umur Manfaat (bulan)', 'Accumulated' => 'Akumulasi',
        'Net Book Value' => 'Nilai Buku', 'Source Document' => 'Dokumen Sumber', 'Cost' => 'Harga Perolehan',
        // Setup
        'Values read by the engines, prints and rules. A constant holds a number, a text, or both. The Key is read by the program, so a saved Key cannot be changed; switch the row off instead. Search by key or description.' => 'Nilai yang dibaca engine, cetakan, dan aturan. Satu konstanta berisi angka, teks, atau keduanya. Key dibaca program, jadi Key yang sudah tersimpan tidak bisa diubah; cukup nonaktifkan barisnya. Cari lewat key atau keterangan.',
        'Number Value' => 'Nilai Angka', 'Text Value' => 'Nilai Teks', 'Constant Categories' => 'Kategori Konstanta',
        'Groups for the rows of Constants. A saved code cannot be changed because constants refer to it.' => 'Kelompok untuk baris Constants. Kode yang sudah tersimpan tidak bisa diubah karena dirujuk konstanta.',
        'Search login, name, e-mail...' => 'Cari login, nama, e-mail...', 'Must change' => 'Wajib ganti', 'Must change password' => 'Wajib ganti password',
        'Search group code, group name...' => 'Cari kode grup, nama grup...', 'Access Level' => 'Level Akses', 'Level Name' => 'Nama Level', 'Members' => 'Anggota',
        'Membership' => 'Keanggotaan', 'Has members' => 'Ada anggota',
        // Talent Pool
        'Search candidate, phone, vacancy...' => 'Cari kandidat, no HP, lowongan...', 'Last Vacancy' => 'Lowongan Terakhir', 'Last Applied' => 'Lamaran Terakhir',
        'Applications' => 'Lamaran', 'Available From' => 'Bisa Mulai', 'Last Status' => 'Status Terakhir', 'Vacancy Code' => 'Kode Lowongan',
        // BOP Rule
        'Search step, process, material...' => 'Cari langkah, proses, bahan...', 'Step' => 'Langkah', 'Scope' => 'Lingkup', 'Merged As' => 'Digabung Sebagai',
        'Machine Role' => 'Peran Mesin', 'Condition' => 'Syarat', 'Material Role' => 'Peran Bahan', 'Usage Basis' => 'Dasar Pemakaian', 'Usage Rate' => 'Takaran Pakai',
        'Tolerance %' => 'Toleransi %', 'Rate Source' => 'Sumber Takaran',
        // Product Category
        'Categories chosen in Master Product. Stock Account and COGS Account are the defaults for products of the category; a product can override them, and an empty cell falls back to the system default.' => 'Kategori yang dipilih di Master Product. Akun Persediaan dan Akun HPP adalah bawaan untuk product kategori itu; product boleh menimpanya, dan sel kosong memakai bawaan sistem.',
        'Category Name' => 'Nama Kategori', 'Stock Account' => 'Akun Persediaan', 'COGS Account' => 'Akun HPP',
        // Subcontract Delivery Note
        'Subcontract Delivery Note' => 'Surat Jalan Subkon', 'Search note no., subcontractor, process, CO...' => 'Cari no surat jalan, subkon, proses, CO...',
        'Note No. / Subcontractor' => 'No Surat Jalan / Subkon', 'Subcontractor' => 'Subkon', 'Subcontract Process' => 'Proses Subkon', 'Note No.' => 'No Surat Jalan',
        'Choose the subcontractor and the process. Items are added on the next screen, each with its Customer Order line.' => 'Pilih subkon dan prosesnya. Barang ditambahkan di layar berikutnya, masing-masing dengan baris Customer Order-nya.',
        'e.g. Lamination, UV spot' => 'mis. Laminasi, UV spot', 'Purchase Requisition (subcontract)' => 'Purchase Requisition (subkon)', '— none —' => '— tidak ada —',
        '+ Item' => '+ Barang', 'Customer Order Line' => 'Baris Customer Order', 'Item Sent (semi-finished)' => 'Barang Dikirim (setengah jadi)',
        'Draft created. Add the items, then Confirm.' => 'Draft dibuat. Tambahkan barang, lalu Confirm.', 'Draft deleted.' => 'Draft dihapus.',
        'Delivery note cancelled.' => 'Surat jalan dibatalkan.', 'Confirmed. The delivery note is ready to print.' => 'Sudah Confirm. Surat jalan siap dicetak.', 'Saved.' => 'Tersimpan.',
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
