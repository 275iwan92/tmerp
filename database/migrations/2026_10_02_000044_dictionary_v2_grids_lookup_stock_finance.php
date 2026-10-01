<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Terjemahan Indonesia untuk teks English grid v2 baru (UI-20, 2026-10-02): lookup Master Data (Satuan, Konversi, Currency, Kurs,
// Kategori Supplier), laporan stok, Purchase Return, Support Tickets, Journal Entries, General Ledger, Trial Balance.
// Nama dokumen dan status tetap English (aturan C.5). Hanya mengisi yang belum punya terjemahan -- terjemahan yang sudah
// diubah user di Setup > Translation Dictionary tidak ditimpa.
return new class extends Migration
{
    private const UI = [
        // Master Data lookup
        'Units of Measure' => 'Satuan (UoM)', 'Unit Name' => 'Nama Satuan', 'Dimension' => 'Dimensi', 'Length' => 'Panjang', 'Area' => 'Luas', 'Mass' => 'Massa',
        'Count' => 'Jumlah', 'Specification' => 'Spesifikasi', 'Base' => 'Dasar', 'Unit Conversion' => 'Konversi Satuan', 'From (1 unit)' => 'Dari (1 satuan)',
        'Value' => 'Nilai', 'To' => 'Ke', 'Currency' => 'Mata Uang', 'Currency Name' => 'Nama Mata Uang', 'Symbol' => 'Simbol', 'Counted Word' => 'Kata Hitung (Terbilang)',
        'Exchange Rate' => 'Kurs', 'Rate to IDR' => 'Kurs ke IDR', 'Effective' => 'Berlaku Sejak', 'Supplier Category' => 'Kategori Supplier', 'Category Code' => 'Kode Kategori',
        'One base unit per dimension: ticking Base on a unit clears it on the others. Dimension Specification (gsm, micron) has no base unit and no conversion. A saved code cannot be changed, only deactivated.' => 'Satu satuan dasar per dimensi: mencentang Dasar di satu satuan menghapus centang di satuan lain. Dimensi Spesifikasi (gsm, mikron) tidak punya satuan dasar dan konversi. Kode yang sudah tersimpan tidak bisa diubah, hanya dinonaktifkan.',
        'One row reads: 1 From = Value x To (1 M = 100 CM). Both units must be of the same dimension, and a pair is entered once. Units are added in Units of Measure.' => 'Cara baca satu baris: 1 Dari = Nilai x Ke (1 M = 100 CM). Kedua satuan harus sedimensi, dan satu pasangan cukup diisi sekali. Satuan ditambah di Satuan (UoM).',
        'Counted Word is the currency name written in the amount in words on printed documents (Rupiah, Dollar). A saved code cannot be changed, only deactivated. Rates are entered in Exchange Rate.' => 'Kata Hitung adalah nama mata uang yang ditulis pada Terbilang di cetakan dokumen (Rupiah, Dollar). Kode yang sudah tersimpan tidak bisa diubah, hanya dinonaktifkan. Kurs diisi di layar Kurs.',
        'Rate = value of 1 unit of the currency in Rupiah. One Active row per currency is the rate in force. To change a rate, add a new row and tick Active: the older row is switched off and kept as history.' => 'Kurs = nilai 1 satuan mata uang dalam Rupiah. Satu baris Aktif per mata uang adalah kurs yang berlaku. Untuk mengubah kurs, tambah baris baru lalu centang Aktif: baris lama otomatis nonaktif dan tersimpan sebagai riwayat.',
        'Every vendor is a supplier, told apart by category (BANK = banks for payroll accounts, EKSPEDISI = forwarders). A saved code cannot be changed because suppliers refer to it; deactivate it instead.' => 'Semua vendor adalah supplier, dibedakan lewat kategori (BANK = bank untuk rekening gaji, EKSPEDISI = jasa angkut). Kode yang sudah tersimpan tidak bisa diubah karena dirujuk supplier; cukup dinonaktifkan.',
        'Dimension Specification cannot have a base unit.' => 'Dimensi Spesifikasi tidak boleh punya satuan dasar.', 'Unit not found.' => 'Satuan tidak ditemukan.',
        // Laporan stok
        'Search item code, item name...' => 'Cari kode barang, nama barang...', 'Search item, doc no, reference...' => 'Cari barang, no dokumen, referensi...',
        'Location' => 'Lokasi', 'Opening' => 'Saldo Awal', 'In' => 'Masuk', 'Out' => 'Keluar', 'Adjustment' => 'Penyesuaian', 'On Hand' => 'Saldo',
        'Minimum Stock' => 'Stok Minimum', 'Stock Value' => 'Nilai Stok', 'Last Movement' => 'Pergerakan Terakhir', 'Idle (days)' => 'Diam (hari)', 'Stock' => 'Stok',
        'Below minimum' => 'Di bawah minimum', 'Empty' => 'Kosong', 'Not usable' => 'Tidak bisa dipakai', 'Movement' => 'Pergerakan', 'Balance' => 'Saldo',
        'Idle' => 'Diam', '3-6 months' => '3-6 bulan', '6-9 months' => '6-9 bulan', '9-12 months' => '9-12 bulan', 'Over 12 months' => 'Lebih dari 12 bulan',
        'Never moved' => 'Belum pernah bergerak', 'No category' => 'Tanpa kategori', '(item not found in master)' => '(barang tidak ditemukan di master)',
        'On hand now, one row per item and location. Opening, In, Out and Adjustment are for the current month; other periods are in Stock Card. Below minimum compares usable stock with the minimum stock of the item.' => 'Saldo sekarang, satu baris per barang dan lokasi. Saldo Awal, Masuk, Keluar, dan Penyesuaian adalah bulan berjalan; periode lain ada di Stock Card. Di bawah minimum membandingkan stok yang bisa dipakai dengan stok minimum barang itu.',
        'Every stock movement, newest first. To read the card of one item, type its code in the search box or group by Item; Balance is the stock of that item in that location right after the movement.' => 'Semua pergerakan stok, terbaru di atas. Untuk membaca kartu satu barang, ketik kodenya di kotak cari atau kelompokkan per Item; Saldo adalah stok barang itu di lokasi itu tepat setelah pergerakan.',
        'Items that still have usable stock but have not moved for 3 months or more. An item is counted once, by the latest movement in any location. Never moved = old balance without a movement date.' => 'Barang yang masih punya stok yang bisa dipakai tetapi tidak bergerak 3 bulan atau lebih. Satu barang dihitung sekali, memakai pergerakan terakhir di lokasi mana pun. Belum pernah bergerak = saldo lama tanpa tanggal pergerakan.',
        // Purchase Return
        'Search return no, supplier, reason...' => 'Cari no retur, supplier, alasan...', 'Return No. / Supplier' => 'No Retur / Supplier', 'Source' => 'Sumber',
        'Settlement' => 'Penyelesaian', 'Credit Value' => 'Nilai Kredit', 'Returned On' => 'Tanggal Dikembalikan', 'Replacement' => 'Pengganti',
        'QC reject' => 'Ditolak QC', 'From stock' => 'Dari stok', 'Credit note' => 'Nota kredit',
        // Support Tickets
        'Search ticket no, sender, problem...' => 'Cari no tiket, pengirim, masalah...', 'Ticket No. / Sender' => 'No Tiket / Pengirim', 'Sent' => 'Dikirim',
        'Screen' => 'Layar', 'Problem' => 'Masalah', 'Replies' => 'Balasan', 'Resolved By' => 'Diselesaikan Oleh', 'Resolved' => 'Selesai',
        // Finance
        'Search journal no, description, reference...' => 'Cari no jurnal, uraian, referensi...', 'Journal No. / Kind' => 'No Jurnal / Jenis', 'Prepared By' => 'Dibuat Oleh',
        'Auto Reverse' => 'Balik Otomatis', 'Kind' => 'Jenis', 'Journal lines' => 'Baris jurnal', 'Account Code' => 'Kode Akun', 'Account Name' => 'Nama Akun',
        'Narration' => 'Uraian', 'Search journal no, account, description...' => 'Cari no jurnal, akun, uraian...', 'Period' => 'Periode', 'Journal No.' => 'No Jurnal',
        'Account' => 'Akun', 'Account Class' => 'Kelompok Akun', 'Normal Balance' => 'Saldo Normal', 'Has balance' => 'Bersaldo', 'Zero' => 'Nol',
        'Search account code, account name...' => 'Cari kode akun, nama akun...',
        'Every posted journal line, newest first. Group by Account to read one account with its debit and credit subtotal, or filter Period for one month. The balance of each account is in Trial Balance.' => 'Semua baris jurnal yang sudah Posted, terbaru di atas. Kelompokkan per Akun untuk membaca satu akun beserta subtotal debit dan kreditnya, atau saring Periode untuk satu bulan. Saldo tiap akun ada di Trial Balance.',
        'Balance of every postable account as of today, from posted journals. Total Debit and total Credit must be equal; the journal lines behind a balance are in General Ledger.' => 'Saldo setiap akun yang bisa diposting per hari ini, dari jurnal yang sudah Posted. Total Debit dan total Kredit harus sama; baris jurnal di balik sebuah saldo ada di General Ledger.',
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
        // Kamus di-cache: tanpa ini terjemahan baru baru tampil setelah cache habis.
        Dictionary::flush();
    }

    public function down(): void
    {
        // isi kamus dibiarkan (bisa sudah diubah user)
    }
};
