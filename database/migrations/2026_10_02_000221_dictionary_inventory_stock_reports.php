<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Terjemahan Indonesia untuk teks English laporan stok semua kelas (Stock Card, Stock Recapitulation, Stock Summary, Stock
// Opname, Stock Loss & Gain, Stock in Inspection), bilah periode, dan cetakannya. Nama laporan / menu, tombol, status
// dokumen, dan nilai keadaan di baris (Gain, Loss, Match, QC hold ...) tetap English. Hanya mengisi yang belum punya terjemahan.
return new class extends Migration
{
    private const UI = [
        // bilah periode + cetakan
        'Period' => 'Periode', 'As Of' => 'Per Tanggal', 'Rows' => 'Jumlah Baris', 'Prepared By' => 'Dibuat Oleh', 'Checked By' => 'Diperiksa Oleh', 'Approved By' => 'Disetujui Oleh',
        'Counted By' => 'Dihitung Oleh', 'Witness' => 'Saksi', 'Head of Warehouse' => 'Kepala Gudang',
        // kolom
        'Movement Value' => 'Nilai Pergerakan', 'Average Price' => 'Harga Rata-rata', 'Balance in Location' => 'Saldo di Lokasi', 'Closing' => 'Saldo Akhir',
        'Opening Value' => 'Nilai Awal', 'In Value' => 'Nilai Masuk', 'Out Value' => 'Nilai Keluar', 'Adjustment Value' => 'Nilai Penyesuaian', 'Closing Value' => 'Nilai Akhir',
        'Activity' => 'Aktivitas', 'Turnover' => 'Perputaran', 'Items' => 'Jumlah Item', 'Items Moved' => 'Item Bergerak', 'Change' => 'Perubahan', 'Share (%)' => 'Porsi (%)',
        'System Qty' => 'Qty Sistem', 'Counted Qty' => 'Qty Fisik', 'Difference' => 'Selisih', 'Difference Value' => 'Nilai Selisih', 'Result' => 'Hasil',
        'Month' => 'Bulan', 'Gain' => 'Selisih Lebih', 'Loss' => 'Selisih Kurang', 'Net' => 'Selisih Bersih', 'Gain Value' => 'Nilai Selisih Lebih',
        'Loss Value' => 'Nilai Selisih Kurang', 'Net Value' => 'Nilai Selisih Bersih', 'Documents' => 'Jumlah Dokumen', 'State' => 'Keadaan',
        'Receipt No. / Bin' => 'No Penerimaan / Bin', 'Supplier / Warehouse' => 'Supplier / Gudang', 'Since' => 'Sejak', 'Days' => 'Hari',
        // kotak cari
        'Search class, category...' => 'Cari kelas, kategori...', 'Search doc no, item, bin, reason...' => 'Cari no dokumen, item, bin, alasan...',
        'Search item, receipt no, bin, supplier...' => 'Cari item, no penerimaan, bin, supplier...',
        // panduan di atas laporan (paling panjang 255 karakter = lebar kolom kamus)
        'Stock card of the period, grouped by item: opening balance, then every movement by date with the running balance in all bins. Transfers have their own column and do not change the balance. For one item, type its code or name in Item and click Show.' => 'Kartu stok periode itu, dikelompokkan per item: saldo awal, lalu tiap pergerakan urut tanggal dengan saldo berjalan di semua bin. Transfer punya kolom sendiri dan tidak mengubah saldo. Untuk satu item, ketik kode atau namanya di Item lalu klik Show.',
        'Only the first 20,000 rows are shown. Shorten the period or fill in Item, then click Show.' => 'Hanya 20.000 baris pertama yang tampil. Persempit periode atau isi Item, lalu klik Show.',
        'One row per item for the period: opening, in, out, adjustment and closing balance, with the closing value. Transfers between bins are not counted. Value per movement and Turnover are in Columns; the movements behind a row are in Stock Card.' => 'Satu baris per item untuk periode itu: saldo awal, masuk, keluar, penyesuaian, dan saldo akhir, beserta nilai akhirnya. Transfer antar bin tidak dihitung. Nilai per pergerakan dan Turnover ada di Columns; pergerakannya ada di Stock Card.',
        'Value of all stock for the period, one row per stock class and item category. Opening + In - Out + Adjustment = Closing. Quantities are not added up here because items have different units; the items are in Stock Recapitulation.' => 'Nilai semua stok untuk periode itu, satu baris per kelas stok dan kategori item. Awal + Masuk - Keluar + Penyesuaian = Akhir. Qty tidak dijumlah di sini karena satuan tiap item berbeda; rincian per item ada di Stock Recapitulation.',
        'Physical count and adjustment documents of the period, one row per item: system quantity, counted quantity and the difference. Gain = more than the system, Loss = less. Only Posted documents have changed the stock; monthly totals are in Stock Loss & Gain.' => 'Dokumen stock opname dan penyesuaian stok periode itu, satu baris per item: qty sistem, qty fisik, dan selisihnya. Gain = lebih dari sistem, Loss = kurang. Hanya dokumen Posted yang sudah mengubah stok; total per bulan ada di Stock Loss & Gain.',
        'Stock gained and lost per item per month, from posted physical counts and adjustments. Gain = stock added, Loss = stock taken off, valued at the average price of that moment. The count documents behind a month are in Stock Opname.' => 'Selisih lebih dan kurang stok per item per bulan, dari stock opname dan penyesuaian yang sudah Posted. Gain = stok ditambah, Loss = stok dikurangi, dinilai dengan harga rata-rata saat itu. Dokumen hitungnya ada di Stock Opname.',
        'Goods in the warehouse now that cannot be used: waiting for quality inspection, rejected and not returned yet, in a QC Hold area, or in a blocked bin. Days = days since the receipt or inspection; for stock in a bin, since the item last moved.' => 'Barang di gudang yang belum boleh dipakai: menunggu pemeriksaan QC, ditolak dan belum diretur, di area QC Hold, atau di bin yang diblokir. Days = hari sejak penerimaan atau pemeriksaan; untuk stok di bin, sejak item terakhir bergerak.',
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
