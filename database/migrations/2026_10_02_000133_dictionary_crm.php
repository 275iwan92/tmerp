<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Terjemahan Indonesia untuk teks English layar CRM (Customer 360, Activities & Follow-up, Complaint & Return) dan
// Complaint Review (QA). Nama menu, tombol, dan status dokumen tetap English. Hanya mengisi yang belum punya terjemahan.
return new class extends Migration
{
    private const UI = [
        // Customer 360
        'Search customer, code, group, industry...' => 'Cari customer, kode, grup, industri...',
        'Segment' => 'Segmen', 'Sleeping' => 'Lama tidak order', 'No order' => 'Belum pernah order',
        'Last Order' => 'Order Terakhir', 'Orders (12 months)' => 'Order (12 bulan)', 'Open Quotations' => 'Quotation Terbuka', 'Open Orders' => 'Order Terbuka',
        'Last Contact' => 'Kontak Terakhir', 'Next Follow-up' => 'Follow-up Berikutnya', 'Open Complaints' => 'Komplain Terbuka', 'Open complaint' => 'Ada komplain',
        'First Order' => 'Order Pertama', 'Orders (all time)' => 'Total Order', 'Order Value (all time)' => 'Total Nilai Order', 'Open Follow-ups' => 'Follow-up Terbuka',
        'Overview' => 'Ringkasan', 'Activities' => 'Aktivitas', 'Quotations' => 'Quotation', 'Orders' => 'Order', 'Deliveries' => 'Kiriman', 'Complaints' => 'Komplain', 'Contacts' => 'Kontak',
        'Orders per month' => 'Order per bulan', 'Last 24 months, new system + archive' => '24 bulan terakhir, sistem baru + arsip', 'Order value' => 'Nilai order',
        'Order value per month' => 'Nilai order per bulan', 'Last 24 months, archive of the old system' => '24 bulan terakhir, arsip sistem lama',
        'Most Ordered Products' => 'Product Paling Sering Dipesan',
        'What this customer buys most often: use it when talking about a repeat order.' => 'Yang paling sering dibeli customer ini: bahan pembicaraan saat menawarkan repeat order.',
        'Times Ordered' => 'Kali Order', 'Last Ordered' => 'Terakhir Order', 'No order history yet.' => 'Belum ada riwayat order.',
        'Customer Profile' => 'Profil Customer', 'Read from Master Customer. Change it there.' => 'Dibaca dari Master Customer. Ubah di sana.',
        'Phone' => 'Telepon', 'Address' => 'Alamat', 'Main Product' => 'Product Utama', 'Packing Instruction' => 'Instruksi Packing', 'Quality Notes' => 'Catatan Mutu',
        'Search notes, contact, next action...' => 'Cari catatan, kontak, tindak lanjut...', 'Next Action' => 'Tindak Lanjut',
        'Search quotation no....' => 'Cari no. quotation...', 'Quotation No.' => 'No. Quotation', 'Issued' => 'Terbit',
        'Search order no., customer PO...' => 'Cari no. order, PO customer...', 'Order No.' => 'No. Order', 'Customer PO' => 'PO Customer',
        'Search delivery no....' => 'Cari no. kiriman...', 'Delivery No.' => 'No. Kiriman', 'New system' => 'Sistem baru', 'Archive' => 'Arsip',
        'Search complaint no., product...' => 'Cari no. komplain, product...', 'Complaint No.' => 'No. Komplain', 'Resolution' => 'Penyelesaian',
        'Contact Persons' => 'Kontak Person', 'Maintained in Master Customer.' => 'Diisi di Master Customer.', 'Name' => 'Nama',
        'No contact person yet. Add one in Master Customer.' => 'Belum ada kontak. Tambahkan di Master Customer.',
        'Branches / Delivery Addresses' => 'Cabang / Alamat Kirim', 'Branch' => 'Cabang', 'No branch yet.' => 'Belum ada cabang.',
        // Activities & Follow-up
        'Call' => 'Telepon', 'Visit' => 'Kunjungan', 'Positive' => 'Positif', 'Neutral' => 'Netral', 'Negative' => 'Negatif', 'No answer' => 'Tidak terhubung',
        'Order / Delivery' => 'Order / Kiriman', 'Prospecting' => 'Cari order baru', 'Complaint' => 'Komplain', 'Payment' => 'Pembayaran', 'Relationship' => 'Hubungan baik',
        'Done' => 'Selesai', 'Later' => 'Nanti', 'Search customer, contact, notes, next action...' => 'Cari customer, kontak, catatan, tindak lanjut...',
        'Activity / Customer' => 'Aktivitas / Customer', 'Activity' => 'Aktivitas', '+ New Record — Activity' => '+ New Record — Aktivitas',
        'Choose the customer you contacted. The details are filled in on the next screen.' => 'Pilih customer yang dihubungi. Rinciannya diisi di layar berikutnya.',
        'Other Contact (not in master)' => 'Kontak Lain (belum di master)', 'Quotation (optional)' => 'Quotation (bila ada)',
        'What was discussed, what the customer said...' => 'Apa yang dibicarakan, apa kata customer...', 'Follow-up Date' => 'Tanggal Follow-up',
        'What to do on the follow-up date' => 'Apa yang dilakukan pada tanggal follow-up', 'Follow-up open' => 'Follow-up terbuka', 'Follow-up done' => 'Follow-up selesai',
        'Result of the follow-up' => 'Hasil dari follow-up',
        'Only the person who recorded this activity can change it. Use Log Result to record what happened next.' => 'Hanya pencatat aktivitas ini yang bisa mengubahnya. Pakai Log Result untuk mencatat kelanjutannya.',
        'Activity recorded.' => 'Aktivitas tercatat.', 'Activity deleted.' => 'Aktivitas dihapus.', 'Follow-up marked as done.' => 'Follow-up ditandai selesai.', 'Follow-up reopened.' => 'Follow-up dibuka lagi.',
        // Complaint & Return
        'Sales Return' => 'Retur Penjualan', 'Product quality' => 'Mutu barang', 'Quantity (short / over)' => 'Jumlah (kurang / lebih)',
        'Delivery (late / wrong address)' => 'Kiriman (telat / salah alamat)', 'Wrong item' => 'Salah barang', 'Document' => 'Dokumen', 'Service' => 'Pelayanan',
        'Replace goods' => 'Ganti barang', 'Rework / repair' => 'Perbaiki (rework)', 'Price reduction' => 'Potongan harga', 'Refund' => 'Uang kembali', 'Explanation only' => 'Penjelasan saja',
        'Goods replaced' => 'Barang diganti', 'Reworked / repaired' => 'Diperbaiki (rework)', 'Price reduced' => 'Harga dipotong', 'Refunded' => 'Uang dikembalikan',
        'Explained to customer' => 'Dijelaskan ke customer', 'Claim not accepted' => 'Klaim tidak diterima',
        'Search complaint no., customer, product...' => 'Cari no. komplain, customer, product...', 'Complaint No. / Customer' => 'No. Komplain / Customer',
        'Customer Asks' => 'Permintaan Customer', 'Customer Asks For' => 'Permintaan Customer', 'QA Verdict' => 'Keputusan QA', 'Waiting QA' => 'Menunggu QA', 'QA answered' => 'QA sudah menjawab',
        '+ New Record — Complaint & Return' => '+ New Record — Komplain & Retur',
        'Choose the customer and whether goods come back. The details are filled in on the next screen.' => 'Pilih customer dan apakah ada barang yang kembali. Rinciannya diisi di layar berikutnya.',
        'QA Review' => 'Review QA', 'Received Via' => 'Diterima Lewat', 'Reference Doc No.' => 'No. Dokumen Acuan',
        'Old CO no. / delivery note no. / customer PO' => 'No. CO lama / no. surat jalan / PO customer', 'Send to QA for review' => 'Teruskan ke QA untuk direview',
        'What the Customer Complains About' => 'Isi Keluhan Customer',
        'Waiting for QA. The answer appears here after QA confirms it in Production > Complaint Review.' => 'Menunggu QA. Jawabannya muncul di sini setelah QA melakukan Confirm di Production > Complaint Review.',
        'QA receives this complaint after Confirm.' => 'QA menerima komplain ini setelah Confirm.',
        'This complaint is not sent to QA. Tick Send to QA for review while it is still a Draft when quality has to be checked.' => 'Komplain ini tidak diteruskan ke QA. Centang Teruskan ke QA selagi masih Draft bila mutunya perlu diperiksa.',
        'Resolution Given to the Customer' => 'Penyelesaian untuk Customer', 'Resolution Note' => 'Catatan Penyelesaian', 'Close is possible after QA has answered.' => 'Close baru bisa setelah QA menjawab.',
        'The resolution is filled in after Confirm, when the answer for the customer is known. Close ends the complaint.' => 'Penyelesaian diisi setelah Confirm, saat jawaban untuk customer sudah ada. Close mengakhiri komplain.',
        'Product Name (if not in master)' => 'Nama Product (bila belum di master)', 'Qty Complained' => 'Qty Dikeluhkan', 'Qty Returned' => 'Qty Kembali',
        'Draft created. Describe the complaint and add the products, then Confirm.' => 'Draft dibuat. Isi keluhan dan tambahkan product, lalu Confirm.',
        'Complaint cancelled.' => 'Komplain dibatalkan.', 'Confirmed. The complaint is now on the QA desk.' => 'Confirmed. Komplain sekarang ada di meja QA.', 'Complaint closed.' => 'Komplain ditutup.',
        'Describe what the customer complains about.' => 'Isi dulu keluhan customer.', 'Add at least one product.' => 'Tambahkan minimal satu product.',
        'Every product needs the quantity complained about.' => 'Setiap product perlu qty yang dikeluhkan.', 'A Sales Return needs the quantity that came back.' => 'Retur Penjualan perlu qty barang yang kembali.',
        'Returned quantity cannot be more than the quantity complained about.' => 'Qty kembali tidak boleh lebih dari qty yang dikeluhkan.',
        'Only a Confirmed complaint can be cancelled or closed.' => 'Hanya komplain Confirmed yang bisa di-Cancel atau di-Close.', 'QA has not answered this complaint yet.' => 'QA belum menjawab komplain ini.',
        'Choose the resolution given to the customer before closing.' => 'Pilih penyelesaian untuk customer sebelum Close.',
        // Complaint Review (QA)
        'Complaint Review' => 'Review Komplain', 'Verdict' => 'Keputusan', 'Returned Goods' => 'Barang Kembali', 'Answered' => 'Dijawab', 'To review' => 'Perlu direview',
        'Accepted (our fault)' => 'Diterima (salah kita)', 'Partly accepted' => 'Diterima sebagian', 'Not accepted' => 'Tidak diterima',
        'Scrap' => 'Scrap (dibuang)', 'Back to stock' => 'Kembali ke stok', 'No goods returned' => 'Tidak ada barang kembali',
        'Reviewed By' => 'Direview Oleh', 'Reviewed At' => 'Direview Pada', 'Root Cause' => 'Akar Masalah', 'Corrective Action' => 'Tindakan Perbaikan', 'Preventive Action' => 'Tindakan Pencegahan',
        'Confirmed By' => 'Di-Confirm Oleh', 'This answer is already Confirmed and cannot be changed.' => 'Jawaban ini sudah Confirmed dan tidak bisa diubah.',
        'Fill in the verdict and the root cause before Confirm.' => 'Isi keputusan dan akar masalah sebelum Confirm.',
        'Goods came back: choose what happens to them (Rework, Scrap or Back to stock).' => 'Ada barang kembali: pilih nasibnya (Rework, Scrap atau Kembali ke stok).',
        'Answer confirmed. Sales can now close the complaint.' => 'Jawaban di-Confirm. Sales sekarang bisa menutup komplain.',
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
