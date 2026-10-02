<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Job Description jabatan General Manager (user 2026-10-02, melihat widget "My Job Description" kosong: "build for me").
// Disusun dari bagan pimpinan 2026-09-28 (atasan Presiden Direktur; bawahan langsung Operational Manager yang membawahi
// Sales & Marketing, Technical Support & Maintenance, General Affair, Human Capital, Supply Chain, Offset Production,
// Digital Production; Finance & Accounting dan Product Costing TIDAK di bawah General Manager) dan dari apa yang dipantau
// jabatan ini di layar pembuka. Hanya mengisi bila masih kosong -- teks yang sudah ditulis HR tidak pernah ditimpa.
return new class extends Migration
{
    private const CODE = 'GENERALMANAGER';

    private const BY = 'claude (draft)';

    private const TEXT = <<<'TXT'
TUJUAN JABATAN
Memimpin operasional perusahaan sehari-hari supaya order customer selesai tepat waktu, tepat mutu, dan menguntungkan, sesuai arah yang ditetapkan Presiden Direktur.

ATASAN LANGSUNG
Presiden Direktur.

BAWAHAN LANGSUNG
Operational Manager, yang membawahi Sales & Marketing, Technical Support & Maintenance, General Affair, Human Capital, Supply Chain, Offset Production, dan Digital Production.

TANGGUNG JAWAB UTAMA
1. Menjabarkan arah Presiden Direktur menjadi target bulanan: nilai order, order baru, customer baru, pengiriman tepat waktu, hasil produksi.
2. Memantau pencapaian target setiap hari dan setiap minggu, lalu memutuskan tindakan bila ada yang meleset.
3. Memastikan alur order berjalan utuh: Inquiry, Pro-Dev, Pro-Cost, Quotation, Customer Order, rencana kirim, produksi, pengiriman, penagihan.
4. Mengawasi rencana dan hasil produksi: target vs hasil, beban kapasitas mesin, Work Order yang terlambat, downtime mesin.
5. Mengawasi mutu: hasil QC, komplain dan retur customer, serta tindak lanjut review QA sampai komplain ditutup.
6. Mengawasi ketersediaan bahan dan persediaan: kekurangan bahan, pembelian yang belum datang, stok yang tidak bergerak.
7. Memutuskan prioritas ketika kapasitas, bahan, atau jadwal kirim berbenturan, termasuk permintaan percepatan.
8. Memberi keputusan atas dokumen yang menunggu persetujuannya sesuai aturan approval yang berlaku.
9. Membina Operational Manager dan para kepala departemen; menilai kinerja, kebutuhan orang, dan kebutuhan pelatihan.
10. Memastikan SOP dan Work Instruction tersedia, dipahami, dan dijalankan; meminta pembaruan bila cara kerja berubah.
11. Memastikan data di sistem ERP diisi lengkap dan tepat waktu oleh setiap bagian, karena laporan manajemen dibaca dari sana.
12. Melaporkan kinerja operasional, masalah, dan usulan perbaikan kepada Presiden Direktur secara berkala.

WEWENANG
1. Menetapkan prioritas kerja dan jadwal lintas departemen di bawah Operational Manager.
2. Menyetujui atau menolak dokumen sesuai batas kewenangan di aturan approval.
3. Mengusulkan kebutuhan orang, mesin, dan investasi kepada Presiden Direktur.
4. Meminta data dan penjelasan dari semua bagian operasional.

DI LUAR TANGGUNG JAWAB JABATAN INI
Finance & Accounting (di bawah Direktur Finance & Accounting) dan Product Costing (langsung ke Presiden Direktur). General Manager bekerja sama dengan keduanya, tetapi tidak memimpinnya.

UKURAN KEBERHASILAN
1. Nilai order dan jumlah order baru terhadap target.
2. Pengiriman tepat waktu.
3. Hasil produksi terhadap target dan pemakaian kapasitas mesin.
4. Jumlah komplain customer dan lama penyelesaiannya.
5. Work Order terlambat dan jam downtime mesin yang tidak terencana.
6. Kelengkapan data dan dokumen kerja (SOP, Work Instruction, Job Description) di sistem.

HUBUNGAN KERJA
Ke dalam: Presiden Direktur, Direktur Finance & Accounting, Product Costing, Operational Manager, para kepala departemen.
Ke luar: customer utama, supplier utama, dan pihak lain atas penugasan Presiden Direktur.
TXT;

    public function up(): void
    {
        DB::connection('tm2mst')->table('org_position')->where('position_code', self::CODE)
            ->where(fn ($q) => $q->whereNull('job_description')->orWhereRaw("TRIM(job_description) = ''"))
            ->update(['job_description' => self::TEXT, 'job_description_updated_by' => self::BY, 'job_description_updated_at' => now()]);
    }

    public function down(): void
    {
        DB::connection('tm2mst')->table('org_position')->where('position_code', self::CODE)->where('job_description_updated_by', self::BY)
            ->update(['job_description' => null, 'job_description_updated_by' => null, 'job_description_updated_at' => null]);
    }
};
