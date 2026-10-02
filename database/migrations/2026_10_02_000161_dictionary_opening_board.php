<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Terjemahan Indonesia untuk teks English papan widget opening screen: judul + uraian widget, teks papan (x-dl::widget-board),
// salam manajemen, jawaban perintah asisten, dan layar Setup-nya. Nama menu / layar, tombol, dan status tetap English.
// Hanya mengisi yang belum punya terjemahan.
return new class extends Migration
{
    private const UI = [
        // papan
        'My Board' => 'Papan Saya', 'Move, resize or remove a widget, or add one from the catalog.' => 'Pindahkan, ubah lebar, atau lepas widget, atau tambah dari katalog.',
        'Put the board back to the template of your position? Your own arrangement is removed.' => 'Kembalikan papan ke template jabatan Anda? Susunan Anda sendiri dihapus.',
        'No widgets on this board' => 'Belum ada widget di papan ini', 'Click Customize, then Add Widget to choose from the catalog.' => 'Klik Customize, lalu Add Widget untuk memilih dari katalog.',
        'No data in this period' => 'Belum ada data di periode ini', 'of target' => 'dari target', 'Actual' => 'Pencapaian', 'Nothing to show' => 'Tidak ada yang ditampilkan',
        'Every widget you may use is already on the board' => 'Semua widget yang boleh Anda pakai sudah ada di papan', 'Move earlier' => 'Pindah lebih awal', 'Move later' => 'Pindah lebih akhir',
        'Narrower' => 'Lebih sempit', 'Wider' => 'Lebih lebar', 'Remove from board' => 'Lepas dari papan', 'This widget could not be loaded' => 'Widget ini tidak bisa dimuat',
        'Delivery' => 'Pengiriman', 'Management' => 'Manajemen',
        // salam
        'Management Dashboard' => 'Dashboard Manajemen', 'Order value this month' => 'Nilai order bulan ini', 'Left to target' => 'Sisa ke target',
        'Waiting for your decision' => 'Menunggu keputusan Anda', 'Working days left this month' => 'Sisa hari kerja bulan ini', 'Reports to' => 'Atasan',
        // widget: judul + uraian katalog
        'Order Value This Month' => 'Nilai Order Bulan Ini', 'Confirmed Customer Orders of this month against the target and last month.' => 'Customer Order Confirmed bulan ini terhadap target dan bulan lalu.',
        'New Customer Orders' => 'Customer Order Baru', 'Number of Customer Orders entered this month, without the cancelled ones.' => 'Jumlah Customer Order yang masuk bulan ini, tanpa yang Cancelled.',
        'On-time Delivery' => 'Pengiriman Tepat Waktu', 'Share of customer deliveries that left on or before the promised date this month.' => 'Bagian pengiriman ke customer yang berangkat tepat atau sebelum tanggal janji bulan ini.',
        'Overdue Receivables' => 'Piutang Lewat Jatuh Tempo', 'Unpaid customer invoices that are past their due date, as of today.' => 'Tagihan customer yang belum lunas dan sudah lewat jatuh tempo, per hari ini.',
        'Sales Today' => 'Sales Hari Ini', 'Value of the Customer Orders entered today: Draft and Confirmed together.' => 'Nilai Customer Order yang masuk hari ini: Draft dan Confirmed digabung.',
        'Order Value: Target vs Actual' => 'Nilai Order: Target vs Pencapaian', 'Confirmed order value of the last 12 months as bars, the monthly target as a line.' => 'Nilai order Confirmed 12 bulan terakhir sebagai batang, target bulanan sebagai garis.',
        'Targets by Area' => 'Target per Bidang', 'Company targets of this month and what has been reached so far.' => 'Target perusahaan bulan ini dan pencapaiannya sampai hari ini.',
        'Sales Pipeline' => 'Pipeline Penjualan', 'Documents of this month at each sales step: Inquiry, price published, Quotation, Customer Order.' => 'Dokumen bulan ini di tiap tahap penjualan: Inquiry, harga terbit, Quotation, Customer Order.',
        'Target Achievement per Sales' => 'Pencapaian Target per Sales', 'Confirmed order value of each sales person this month against the personal target.' => 'Nilai order Confirmed tiap sales person bulan ini terhadap target pribadinya.',
        'Waiting for Your Decision' => 'Menunggu Keputusan Anda', 'Documents in the approval queue that wait for your Approve, Reject or Hold.' => 'Dokumen di antrian approval yang menunggu Approve, Reject, atau Hold dari Anda.',
        'Customer Orders by Status' => 'Customer Order per Status', 'Customer Orders of this year split by status: Draft, Waiting for approval, Confirmed, Closed.' => 'Customer Order tahun ini per status: Draft, Waiting for approval, Confirmed, Closed.',
        'Top Customers' => 'Customer Teratas', 'The five customers with the highest confirmed order value this year.' => 'Lima customer dengan nilai order Confirmed tertinggi tahun ini.',
        'Receivables Aging' => 'Umur Piutang', 'Unpaid customer invoices grouped by days past the due date.' => 'Tagihan customer yang belum lunas, dikelompokkan menurut hari lewat jatuh tempo.',
        'Material Shortage' => 'Kekurangan Bahan', 'Materials that are short, with the first date they are needed and the orders waiting for them.' => 'Bahan yang kurang, dengan tanggal pertama dibutuhkan dan order yang menunggunya.',
        'Deadstock Value' => 'Nilai Deadstock', 'Value of stock that has not moved for months: raw material, semi finished and finished goods together.' => 'Nilai stok yang berbulan-bulan tidak bergerak: bahan baku, barang setengah jadi, dan barang jadi digabung.',
        'Quote of the Day' => 'Kata Hari Ini', 'One line for every day of the year.' => 'Satu baris untuk setiap hari dalam setahun.',
        // widget: isi
        'No target set for this month' => 'Target bulan ini belum diisi', ':n confirmed' => ':n Confirmed', 'No delivery has left this month' => 'Belum ada pengiriman yang berangkat bulan ini',
        ':a of :b deliveries on time' => ':a dari :b pengiriman tepat waktu', ':n invoices past due' => ':n invoice lewat jatuh tempo', 'limit :v' => 'batas :v',
        ':n documents · confirmed :v' => ':n dokumen · Confirmed :v', 'Last 12 months · bars = actual, line = target' => '12 bulan terakhir · batang = pencapaian, garis = target',
        'This month against target' => 'Bulan ini terhadap target', 'confirmed Customer Orders' => 'Customer Order Confirmed', 'documents entered' => 'dokumen masuk',
        'New Customers' => 'Customer Baru', 'first order' => 'order pertama', 'against the promised date' => 'terhadap tanggal janji', 'lower is better' => 'makin kecil makin baik',
        'Documents dated this month' => 'Dokumen bertanggal bulan ini', 'Inquiries' => 'Inquiry', 'Price published (Pro-Cost)' => 'Harga terbit (Pro-Cost)',
        'Quotations issued' => 'Quotation terbit', 'Customer Orders' => 'Customer Order', 'Documents' => 'Dokumen', 'This month, highest first' => 'Bulan ini, tertinggi di atas',
        'No sales person' => 'Tanpa sales person', 'This year, documents' => 'Tahun ini, jumlah dokumen', 'This year, confirmed order value' => 'Tahun ini, nilai order Confirmed',
        'Days past due, as of today' => 'Hari lewat jatuh tempo, per hari ini', 'Not due' => 'Belum jatuh tempo', ':n materials need attention' => ':n bahan perlu perhatian',
        'No material is short' => 'Tidak ada bahan yang kurang', ':n documents' => ':n dokumen', 'Nothing waits for your decision' => 'Tidak ada yang menunggu keputusan Anda',
        ':n items not moved for more than :m months' => ':n item tidak bergerak lebih dari :m bulan',
        // aksi papan + asisten
        'Unknown board action.' => 'Aksi papan tidak dikenal.', 'This widget is not available for your account.' => 'Widget ini tidak tersedia untuk akun Anda.',
        'This widget is already on your board.' => 'Widget ini sudah ada di papan Anda.', 'This widget is not on your board.' => 'Widget ini tidak ada di papan Anda.',
        'Opening board' => 'Papan pembuka', 'Opening board widgets' => 'Widget papan pembuka',
        'The widget board is open for top management first. Other positions still use the opening screen template.' => 'Papan widget dibuka untuk top management dulu. Jabatan lain masih memakai template layar pembuka.',
        'Your board is back to the template of your position.' => 'Papan Anda kembali ke template jabatan Anda.', 'On your board' => 'Sudah di papan Anda', 'Not installed' => 'Belum dipasang',
        'Widgets you may install:' => 'Widget yang boleh Anda pasang:',
        'Which widget? Say for example: add widget Top Customers. Widgets you may install:' => 'Widget yang mana? Contoh: pasang widget Customer Teratas. Widget yang boleh Anda pasang:',
        'Installed on your board. Use Customize on the board to move or resize it.' => 'Sudah dipasang di papan Anda. Pakai Customize di papan untuk memindah atau mengubah lebarnya.',
        'Removed from your board.' => 'Sudah dilepas dari papan Anda.',
        // layar Setup
        'Widgets a user may put on the opening board. The content is calculated by the program; here you set the title, who may install it (module and minimum level), the default width and the words the assistant listens for.' => 'Widget yang boleh dipasang user di papan pembuka. Isinya dihitung program; di sini diatur judul, siapa yang boleh memasang (modul dan level minimum), lebar bawaan, dan kata yang didengar asisten.',
        'The board a user starts with, per profile: which widgets, in which order and how wide. It is copied when the board is first opened and on Reset to Template; boards already arranged by their owner do not change.' => 'Papan awal user per profil: widget apa, urutan, dan lebarnya. Disalin saat papan pertama dibuka dan saat Reset to Template; papan yang sudah diatur pemiliknya tidak berubah.',
        'Monthly targets shown on the opening board. Leave Sales Person at Company for a company target; choose a person for a personal order-value target. Any date of the month is stored as the first day of that month.' => 'Target bulanan yang tampil di papan pembuka. Biarkan Sales Person = Company untuk target perusahaan; pilih orang untuk target nilai order pribadi. Tanggal berapa pun disimpan sebagai tanggal 1 bulan itu.',
        'The quote shown on the opening board, one row per day of the year. Day 1 = 1 January, day 60 = 29 February, day 366 = 31 December; the same date shows the same row every year.' => 'Kata yang tampil di papan pembuka, satu baris per hari dalam setahun. Hari 1 = 1 Januari, hari 60 = 29 Februari, hari 366 = 31 Desember; tanggal yang sama menampilkan baris yang sama tiap tahun.',
        'Width (of 12)' => 'Lebar (dari 12)', 'Assistant words' => 'Kata asisten', 'Minimum level' => 'Level minimum', 'Profile' => 'Profil', 'Target Value' => 'Nilai Target',
        'Quote' => 'Kata', 'Author' => 'Penulis', 'Day' => 'Hari', 'Month' => 'Bulan', 'Company' => 'Perusahaan',
        'This Key has no program class. Ask the developer to add the widget first.' => 'Key ini belum punya kelas program. Minta developer menambah widget-nya dulu.',
        'This widget is already in the template of that profile.' => 'Widget ini sudah ada di template profil itu.',
        'A personal target is only used for Order value. Choose Company for the other targets.' => 'Target pribadi hanya dipakai untuk Nilai order. Pilih Company untuk target lain.',
        'This sales person is not in Master Sales Person.' => 'Sales person ini tidak ada di Master Sales Person.', 'This target already exists for that month.' => 'Target ini sudah ada untuk bulan itu.',
        'Order value (confirmed Customer Orders)' => 'Nilai order (Customer Order Confirmed)', 'New Customer Orders (documents)' => 'Customer Order baru (dokumen)',
        'New customers (first order)' => 'Customer baru (order pertama)', 'On-time delivery (percent)' => 'Pengiriman tepat waktu (persen)',
        'Overdue receivables (upper limit)' => 'Piutang lewat jatuh tempo (batas atas)', 'Management (President Director, Director, General Manager)' => 'Manajemen (Presiden Direktur, Direktur, General Manager)',
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
            if ($key && ! $db->table('sys_dictionary_lang')->where('id_dictionary', $key)->where('lang_code', 'id')->where('text', '<>', '')->exists()) {
                $db->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $key, 'lang_code' => 'id'], ['text' => $id]);
            }
        }
        Dictionary::flush();
    }

    public function down(): void {}
};
