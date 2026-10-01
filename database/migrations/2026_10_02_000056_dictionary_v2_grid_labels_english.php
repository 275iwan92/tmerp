<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Label kelas grid v2 yang dulu ditulis Indonesia (lolos dari MultiLanguageRatchetTest) diganti English di kode
// 2026-10-02; migrasi ini mengisi terjemahan Indonesianya supaya tampilan bahasa Indonesia tetap seperti semula.
// Termasuk teks panduan (guide) grid yang belum punya terjemahan. Hanya mengisi yang belum punya terjemahan.
return new class extends Migration
{
    private const UI = [
        // Kolom, filter, grup
        'Account No.' => 'No. Rekening', 'Anomaly' => 'Anomali', 'Blocked' => 'Diblokir', 'Brand' => 'Merek', 'Brand / Model' => 'Merek / Model',
        'CO Mode' => 'Mode CO', 'CO No.' => 'No CO', 'CO Status' => 'Status CO', 'Cash' => 'Tunai', 'Category' => 'Kategori', 'Clock-out Time' => 'Pulang',
        'Contact Person' => 'Kontak PIC', 'Credit Limit' => 'Limit Kredit', 'Currency' => 'Mata Uang', 'Customer PO No.' => 'No PO Customer',
        'Default Process' => 'Proses Default', 'Department' => 'Departemen', 'Department Scope' => 'Cakupan Departemen', 'Distance (m)' => 'Jarak (m)',
        'Drive' => 'Penggerak', 'Effective' => 'Berlaku', 'Grammage' => 'Gramatur', 'Group' => 'Grup', 'In Progress' => 'Berjalan', 'Industry' => 'Industri',
        'Item Type' => 'Jenis Item', 'Late' => 'Terlambat', 'Late (min)' => 'Telat (Mnt)', 'Location' => 'Lokasi', 'Lost Reason' => 'Alasan Kalah',
        'Manufacturer' => 'Pabrikan', 'Max Cut L' => 'Potong Maks P', 'Max Cut W' => 'Potong Maks L', 'Max Knife (m)' => 'Pisau Maks (m)',
        'Max Sheet (mm)' => 'Lembar Maks (mm)', 'Mobile No.' => 'No. HP', 'Model (catalogue)' => 'Model (katalog)', 'Month' => 'Bulan', 'Monthly' => 'Bulanan',
        'Nearest Due Date' => 'Due Date terdekat', 'Normal Balance' => 'Saldo Normal', 'Old Value' => 'Nilai Lama', 'Order Value' => 'Nilai Order',
        'Parent' => 'Induk', 'Pay Frequency' => 'Gaji', 'Payment Term' => 'Termin', 'Performed By' => 'Dikerjakan', 'Period' => 'Periode', 'Photo' => 'Foto',
        'Position' => 'Posisi', 'Present' => 'Hadir', 'Pressure (ton)' => 'Tekanan (ton)', 'Product' => 'Produk', 'Product Type' => 'Tipe Produk',
        'Reason' => 'Alasan', 'Receiver' => 'Penerima', 'Result' => 'Hasil', 'Revision' => 'Revisi', 'Role' => 'Peran', 'SOP Document' => 'Dokumen SOP',
        'Salary Method' => 'Metode Gaji', 'Salary Setup' => 'Setelan', 'Sales PIC' => 'PIC Sales', 'Scrap Type' => 'Tipe Scrap', 'Send Status' => 'Status Kirim',
        'Ship Date' => 'Tgl Kirim', 'Shipped' => 'Terkirim', 'Shipped (items)' => 'Terkirim (item)', 'Shipping Type' => 'Jenis Kirim', 'Source' => 'Sumber',
        'Stage' => 'Tahap', 'Stock Class' => 'Kelas Stok', 'Subcontractor' => 'Subkon', 'Submitted' => 'Diajukan',
        'Supporting (non-production)' => 'Penunjang (non-produksi)', 'Type' => 'Jenis', 'Unit' => 'Satuan', 'Weekly' => 'Mingguan', 'Work Status' => 'Status Kerja',
        // Kotak cari
        'Search CO no., customer, PO no., product...' => 'Cari no. CO, customer, no. PO, product...', 'Search category, parent...' => 'Cari kategori, induk...',
        'Search code, account name...' => 'Cari kode, nama akun...', 'Search code, name, brand...' => 'Cari kode, nama, merek...',
        'Search code, name, brand/model...' => 'Cari kode, nama, merek/model...', 'Search code, process name...' => 'Cari kode, nama proses...',
        'Search code, template name...' => 'Cari kode, nama template...', 'Search code, type name...' => 'Cari kode, nama tipe...',
        'Search code, warehouse name...' => 'Cari kode, nama gudang...', 'Search crew, process...' => 'Cari crew, proses...',
        'Search employee, category...' => 'Cari karyawan, kategori...', 'Search employee, field, value...' => 'Cari karyawan, field, nilai...',
        'Search employee, period...' => 'Cari pegawai, periode...', 'Search employee, status...' => 'Cari karyawan, status...',
        'Search employee, type, reason...' => 'Cari karyawan, jenis, alasan...', 'Search employee...' => 'Cari karyawan...',
        'Search inquiry, PIC, reason...' => 'Cari inquiry, PIC, alasan...', 'Search name, code, NIK, position...' => 'Cari nama, kode, NIK, jabatan...',
        'Search name, code, account no....' => 'Cari nama, kode, no. rekening...', 'Search name, code, category, contact...' => 'Cari nama, kode, kategori, kontak...',
        'Search name, code, group, industry...' => 'Cari nama, kode, grup, industri...', 'Search name, code...' => 'Cari nama, kode...',
        'Search name, employee code...' => 'Cari nama, kode pegawai...', 'Search period, status...' => 'Cari periode, status...',
        'Search position, code, department...' => 'Cari posisi, kode, departemen...', 'Search product market, product type...' => 'Cari product market, tipe produk...',
        'Search shift name, department...' => 'Cari nama shift, departemen...', 'Search title, SOP code...' => 'Cari judul, kode SOP...', 'Search...' => 'Cari...',
        // Teks panduan grid
        'One report for every need. Columns switches on extra columns (Status, Unit Price, Discount, Subtotal, Planned Shipment, Shipped, Remaining, Month, Mode). The boxes above filter by status; Group totals Qty & Subtotal = Total Sales.' => 'Satu laporan untuk semua kebutuhan. Tombol Kolom menyalakan kolom tambahan (Status, Harga Satuan, Diskon, Subtotal, Rencana Kirim, Terkirim, Sisa, Bulan, Mode). Kotak di atas menyaring per status; Grup menjumlahkan Qty & Subtotal = Total Sales.',
        'One report for every need. Columns switches on extra columns (Status, Planned Shipment, Shipped, Remaining, Month, Mode). The boxes above filter by status; Group (Customer, Month, Status, Sales By) totals the Qty.' => 'Satu laporan untuk semua kebutuhan. Tombol Kolom menyalakan kolom tambahan (Status, Rencana Kirim, Terkirim, Sisa, Bulan, Mode). Kotak di atas menyaring per status; Grup (Customer, Bulan, Status, Sales By) menjumlahkan Qty.',
        'One row = one CO. Group by Customer / Month / Sales By totals Total Qty & Order Value (= Total Sales). Use Columns to add Mode, Planned Shipment, Remaining, Note, Nearest Due Date.' => 'Satu baris = satu CO. Grup per Customer / Bulan / Sales By menjumlahkan Total Qty & Nilai Order (= Total Sales). Klik tombol Kolom untuk menambah Mode, Rencana Kirim, Sisa, Note, Due Date terdekat.',
        'One row = one CO. Group by Customer / Month / Sales By totals Total Qty. Use Columns to add Mode, Planned Shipment, Remaining, Note, Nearest Due Date.' => 'Satu baris = satu CO. Grup per Customer / Bulan / Sales By menjumlahkan Total Qty. Klik tombol Kolom untuk menambah Mode, Rencana Kirim, Sisa, Note, Due Date terdekat.',
        'One row = one shipment schedule. Use Columns to add Sales By, Due Date, CO Status, Price, Shipped, Remaining; Group by Customer / Ship Date / CO No. totals Ship Qty.' => 'Satu baris = satu jadwal kirim. Klik tombol Kolom untuk menambah Sales By, Due Date, Status CO, Harga, Terkirim, Sisa; Grup per Customer / Tgl Kirim / No CO menjumlahkan Qty Kirim.',
        'One row = one shipment schedule. Use Columns to add Sales By, Due Date, CO Status, Shipped, Remaining; Group by Customer / Ship Date / CO No. totals Ship Qty.' => 'Satu baris = satu jadwal kirim. Klik tombol Kolom untuk menambah Sales By, Due Date, Status CO, Terkirim, Sisa; Grup per Customer / Tgl Kirim / No CO menjumlahkan Qty Kirim.',
        'Expected orders per customer, product and month (first day of the month). A Customer Order for the same customer, product and month uses up the forecast; only the remainder is planned. Forecast never reserves stock: it shows what to buy in time.' => 'Perkiraan order per customer, product dan bulan (tanggal 1). Customer Order untuk customer, product dan bulan yang sama memakai habis forecast; hanya sisanya yang direncanakan. Forecast tidak pernah mem-booking stok: hanya menunjukkan yang perlu dibeli.',
        'Ink, varnish, coating, film and glue used per job; Material Planning multiplies them by the sheet area or quantity. A standard other than ink applies when a trigger word is in the part processes or finishing. Starting values are general: correct them.' => 'Tinta, varnish, coating, film dan lem per pekerjaan; Material Planning mengalikannya dengan luas lembar atau qty. Standar selain tinta berlaku bila kata pemicunya ada di proses atau finishing part. Nilai awal masih umum: mohon dikoreksi.',
        'Working days from order to goods received, per material category; used for "Order By (latest)" in Material Planning. An item can override it in Master Raw Material; an empty category follows its parent, then PURCHASE_LEAD_DAYS_DEFAULT in Constants.' => 'Hari kerja dari pesan sampai barang diterima, per kategori bahan; dipakai untuk "Order By (latest)" di Material Planning. Item bisa menimpanya di Master Raw Material; kategori kosong ikut induknya, lalu PURCHASE_LEAD_DAYS_DEFAULT di Constants.',
        'Telasih office hours: when the company ships and receives goods. Delivery slots (CPDEL) must fall inside these hours and outside the Holiday Calendar. Production is not limited by this table: machines run by shift (Master Shift), up to 24/7.' => 'Jam kantor Telasih: kapan perusahaan mengirim dan menerima barang. Slot kirim (CPDEL) harus di dalam jam ini dan di luar Kalender Libur. Produksi tidak dibatasi tabel ini: mesin jalan per shift (Master Shift), sampai 24/7.',
        'Rows without a customer are the default for every customer (normal Indonesian office hours). Add rows for a customer only when their receiving hours differ; that customer then uses its own row for that day.' => 'Baris tanpa customer adalah bawaan untuk semua customer (jam kantor normal Indonesia). Tambah baris untuk satu customer hanya bila jam terimanya berbeda; customer itu lalu memakai barisnya sendiri untuk hari itu.',
        'Reasons used when production cannot meet the customer slot or the slot is changed. The department is who answers for it in the delivery promise KPI.' => 'Alasan yang dipakai saat produksi tidak bisa memenuhi slot customer atau slot diubah. Departemen = pihak yang bertanggung jawab di KPI janji kirim.',
        'Goods already on a Delivery Order (quick-job shortcut after Post to Production) whose Work Order has no production output yet. Enter the output: it is the KPI basis for all departments.' => 'Barang yang sudah masuk Delivery Order (jalan pintas pekerjaan cepat setelah Post to Production) tetapi Work Order-nya belum punya output produksi. Isi outputnya: itu dasar KPI semua departemen.',
        'One row = one Work Order. Group by Machine / Month / Type / Problem to see where breakdowns and cost come from. Use Columns for Cause, Action, Findings, cost split.' => 'Satu baris = satu Work Order. Grup per Machine / Month / Type / Problem untuk melihat asal kerusakan dan biaya. Pakai Columns untuk Cause, Action, Findings, rincian biaya.',
        'Write English in the code; translate here. Empty cell = shown in English. context "ui" = screen & print labels; "date.month_short" = month names; "<db>.<table>.<column>" = lookup values stored in English.' => 'Tulis English di kode; terjemahkan di sini. Sel kosong = tampil English. context "ui" = label layar & cetak; "date.month_short" = nama bulan; "<db>.<table>.<column>" = nilai lookup yang disimpan English.',
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
        // Terjemahan tidak dihapus: bisa sudah diubah user lewat Setup > Translation Dictionary.
    }
};
