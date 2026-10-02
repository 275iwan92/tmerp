<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Dashboard Production, Quality Control & Assurance, Inventory & Warehousing, dan Work Instruction / Job Description / SOP
// (user 2026-10-02: "perlu ada production, quality control and quality assurance, inventory and warehousing, payroll, human
// resource, Work Instruction, job description, SOP. On production, target vs result, capacity planning").
//   sys_dashboard            empat dashboard baru + urutan tab
//   sys_board_template       template empat dashboard itu
//   sys_widget               widget produksi / stok yang sudah ada mendapat dashboard asalnya
//   tm2mst.sop_document      + doc_type (SOP | WI): Work Instruction disimpan di register yang sama dengan SOP
return new class extends Migration
{
    // kode => [judul, ikon, modul]
    private const DASHBOARDS = [
        'PRODUCTION' => ['Production', 'fa-solid fa-industry', 'PROD'],
        'QUALITY' => ['Quality Control & Assurance', 'fa-solid fa-clipboard-check', 'PROD'],
        'INVENTORY' => ['Inventory & Warehousing', 'fa-solid fa-warehouse', 'INV'],
        'DOCS' => ['Work Instruction, JD & SOP', 'fa-solid fa-book-open', null],
    ];

    // Urutan tab sesudah empat dashboard baru masuk. [lama, baru]: baris yang urutannya sudah diubah user tidak disentuh.
    private const ORDER = ['OVERALL' => [10, 10], 'SALES' => [20, 20], 'PURCHASING' => [30, 30], 'PRODUCTION' => [0, 40], 'QUALITY' => [0, 50], 'INVENTORY' => [0, 60],
        'AR_AP' => [40, 70], 'CASHFLOW' => [50, 80], 'PAYROLL' => [60, 90], 'HR' => [70, 100], 'DOCS' => [0, 110]];

    // dashboard => [[widget, lebar dari 12], ...] berurutan
    private const TEMPLATES = [
        'PRODUCTION' => [['production_output', 4], ['capacity_utilization', 4], ['machine_downtime', 4], ['production_trend', 8], ['operations_by_status', 4],
            ['machine_load', 6], ['late_work_orders', 6], ['work_order_status', 6], ['material_shortage', 6]],
        'QUALITY' => [['incoming_qc_waiting', 4], ['incoming_pass_rate', 4], ['production_qc', 4], ['complaints_by_category', 4], ['qa_review_waiting', 4], ['open_complaints', 4]],
        'INVENTORY' => [['stock_value', 4], ['stock_documents', 4], ['deadstock_value', 4], ['stock_by_warehouse', 6], ['receipts_waiting', 6], ['material_shortage', 6]],
        'DOCS' => [['my_job_description', 8], ['jd_coverage', 4], ['sop_documents', 6], ['work_instructions', 6]],
    ];

    private const HOME = ['work_order_status' => 'PRODUCTION', 'late_work_orders' => 'PRODUCTION', 'stock_value' => 'INVENTORY', 'deadstock_value' => 'INVENTORY'];

    private const UI = [
        'Quality Control & Assurance' => 'Quality Control & Assurance', 'Inventory & Warehousing' => 'Persediaan & Gudang', 'Work Instruction, JD & SOP' => 'Instruksi Kerja, Job Description & SOP',
        // Production
        'Production: Target vs Result' => 'Produksi: Target vs Hasil',
        'Good quantity recorded in the period against the quantity of Work Order operations planned to finish in the period.' => 'Qty baik yang dicatat pada periode itu dibanding qty operasi Work Order yang dijadwalkan selesai pada periode itu.',
        'No operation is planned to finish in this period' => 'Tidak ada operasi yang dijadwalkan selesai pada periode ini', ':a operations planned · :b output records' => ':a operasi dijadwalkan · :b catatan hasil',
        'Production per Period: Target vs Result' => 'Produksi per Periode: Target vs Hasil', 'Production result per period as bars and the planned quantity as a line.' => 'Hasil produksi per periode sebagai batang dan qty yang dijadwalkan sebagai garis.',
        'Bars = result, line = target' => 'Batang = hasil, garis = target', 'Result' => 'Hasil',
        'Capacity Load' => 'Beban Kapasitas', 'Hours of scheduled Work Order operations against the available hours of all process machines in the period.' => 'Jam operasi Work Order yang terjadwal dibanding jam tersedia semua mesin proses pada periode itu.',
        ':n machines without hours per shift in Master Machine' => ':n mesin belum diisi jam per shift di Master Mesin', 'Machine hours per shift are not filled in' => 'Jam per shift mesin belum diisi',
        ':a h planned of :b h available' => ':a jam terjadwal dari :b jam tersedia', ':a h planned' => ':a jam terjadwal',
        'Machine Load' => 'Beban Mesin', 'Scheduled hours against available hours per machine in the period, the fullest machine first.' => 'Jam terjadwal dibanding jam tersedia per mesin pada periode itu, mesin paling penuh di atas.',
        'No capacity set' => 'Kapasitas belum diisi', 'No operation is scheduled on a machine in this period' => 'Tidak ada operasi yang dijadwalkan di mesin pada periode ini',
        'Operations by Status' => 'Operasi per Status', 'Operations of open Work Orders by status: Pending, Assigned, Running, Breakdown, QC hold, QC passed.' => 'Operasi Work Order yang belum selesai per status: Pending, Assigned, Running, Breakdown, QC hold, QC passed.',
        'Open Work Orders, as of today' => 'Work Order yang belum selesai, per hari ini', 'Operations' => 'Operasi',
        'Unplanned Downtime' => 'Downtime Tidak Terencana', 'Hours machines stood still without a plan in the period, with the planned maintenance hours beside it.' => 'Jam mesin berhenti tanpa rencana pada periode itu, dengan jam perawatan terencana di sampingnya.',
        ':n downtime records, in hours' => ':n catatan downtime, dalam jam', 'Planned :v h · :n still stopped' => 'Terencana :v jam · :n masih berhenti',
        // Quality
        'Incoming QC Waiting' => 'QC Penerimaan Menunggu', 'Goods receipt lines that still wait for the quality check, as of today, with the checks done in the period.' => 'Baris penerimaan barang yang masih menunggu pemeriksaan mutu, per hari ini, dengan pemeriksaan yang selesai pada periode itu.',
        'Receipt lines' => 'Baris penerimaan', 'Checked in the period: passed :a · not passed :b' => 'Diperiksa pada periode itu: lulus :a · tidak lulus :b',
        'Incoming QC Pass Rate' => 'Tingkat Lulus QC Penerimaan', 'Share of goods receipt lines that passed the quality check, of those checked in the period.' => 'Bagian baris penerimaan barang yang lulus pemeriksaan mutu, dari yang diperiksa pada periode itu.',
        'No receipt line was checked in this period' => 'Tidak ada baris penerimaan yang diperiksa pada periode ini', 'Passed :a of :b lines' => 'Lulus :a dari :b baris',
        'Production QC' => 'QC Produksi', 'Operations of open Work Orders at the quality gate: on QC hold, rejected, passed.' => 'Operasi Work Order yang belum selesai di gerbang mutu: QC hold, ditolak, lulus.',
        'Complaints by Category' => 'Komplain per Kategori', 'Customer complaints and returns of the period by category: quality, quantity, delivery and others.' => 'Komplain dan retur customer pada periode itu per kategori: mutu, jumlah, pengiriman, dan lainnya.',
        'Complaints' => 'Komplain',
        'Waiting for QA Review' => 'Menunggu Review QA', 'Complaints that need a quality answer from QA and have no confirmed review yet, oldest first.' => 'Komplain yang perlu jawaban mutu dari QA dan belum punya review yang di-Confirm, yang paling lama di atas.',
        ':n complaints' => ':n komplain', 'No complaint waits for a QA review' => 'Tidak ada komplain yang menunggu review QA',
        // Inventory & Warehousing
        'Stock Value by Warehouse' => 'Nilai Stok per Gudang', 'Value of usable stock on hand per warehouse.' => 'Nilai stok on hand yang bisa dipakai per gudang.',
        'Stock Documents' => 'Dokumen Stok', 'Stock documents of the period by direction: goods in, goods out, adjustment or stock count, return.' => 'Dokumen stok pada periode itu menurut arah: barang masuk, barang keluar, penyesuaian atau opname, retur.',
        'Goods in' => 'Barang masuk', 'Goods out' => 'Barang keluar', 'Adjustment / count' => 'Penyesuaian / opname', 'Return' => 'Retur', 'Documents' => 'Dokumen',
        'Goods Receipts in Progress' => 'Penerimaan Barang Berjalan', 'Goods receipts that wait for the quality check, or are posted but not handed over yet.' => 'Penerimaan barang yang menunggu pemeriksaan mutu, atau sudah diposting tetapi belum diserahkan.',
        'Waiting for QC' => 'Menunggu QC', 'Not handed over' => 'Belum diserahkan', ':n receipts' => ':n penerimaan', 'No goods receipt is in progress' => 'Tidak ada penerimaan barang yang sedang berjalan',
        // Work Instruction, Job Description, SOP
        'My Job Description' => 'Job Description Saya', 'The job description of your own position, as written by HR in Master Position.' => 'Job Description jabatan Anda sendiri, seperti yang ditulis HR di Master Jabatan.',
        'The job description of your position is not filled in yet' => 'Job Description jabatan Anda belum diisi', 'Your account is not linked to a position yet' => 'Akun Anda belum terhubung ke jabatan',
        'Job Descriptions Filled' => 'Job Description Terisi', 'Active positions that already have a job description, of all active positions.' => 'Jabatan aktif yang sudah punya Job Description, dari seluruh jabatan aktif.',
        ':n positions still without a job description' => ':n jabatan masih tanpa Job Description',
        'Standard Operating Procedures that are in force, newest first.' => 'Standard Operating Procedure yang berlaku, yang terbaru di atas.', 'No SOP is registered yet' => 'Belum ada SOP yang didaftarkan',
        'Work Instructions' => 'Instruksi Kerja', 'Work Instructions that are in force, newest first.' => 'Instruksi Kerja yang berlaku, yang terbaru di atas.',
        'No Work Instruction is registered yet' => 'Belum ada Instruksi Kerja yang didaftarkan', ':n documents' => ':n dokumen', 'All departments' => 'Semua departemen',
        'SOP & Work Instruction' => 'SOP & Instruksi Kerja', 'Work Instruction' => 'Instruksi Kerja', 'Document Type' => 'Jenis Dokumen', 'Search title, document code...' => 'Cari judul, kode dokumen...',
    ];

    // Dokumen contoh, hanya di database dummy: [kode, jenis, judul, departemen, tujuan, isi]
    private const DUMMY = [
        ['901/SOP/TM/X/2026', 'SOP', 'Pemeriksaan Bahan Masuk', 'Quality Control', 'Memastikan bahan yang diterima sesuai pesanan sebelum masuk gudang.',
            "1. Cocokkan surat jalan supplier dengan Purchase Order.\n2. Periksa jumlah, ukuran, dan gramatur sesuai sampling.\n3. Catat hasil di Goods Receipt: lulus atau tidak lulus.\n4. Bahan tidak lulus diberi tanda dan dipisahkan."],
        ['902/SOP/TM/X/2026', 'SOP', 'Penanganan Komplain Customer', null, 'Setiap komplain dijawab dengan data dan ditutup dengan tindakan.',
            "1. Sales mencatat komplain di Complaint & Return.\n2. QA memeriksa contoh barang dan catatan produksi.\n3. QA mengisi review: penyebab, tindakan perbaikan, pencegahan.\n4. Sales menyampaikan jawaban ke customer lalu menutup komplain."],
        ['903/SOP/TM/X/2026', 'SOP', 'Serah Terima Barang Jadi ke Gudang', 'Gudang', 'Barang jadi tercatat di stok pada hari yang sama dengan selesai produksi.',
            "1. Produksi menghitung dan memberi label palet.\n2. Gudang menghitung ulang dan menandatangani serah terima.\n3. Gudang mencatat Stock In barang jadi."],
        ['901/WI/TM/X/2026', 'WI', 'Make-Ready Mesin Cetak Offset', 'Cetak', 'Urutan persiapan mesin cetak sebelum mulai order.',
            "1. Pasang plate sesuai urutan warna di Work Order.\n2. Atur ukuran kertas dan tekanan.\n3. Cetak lembar percobaan, cocokkan warna dengan proof yang disetujui.\n4. Minta paraf QC sebelum cetak jumlah penuh."],
        ['902/WI/TM/X/2026', 'WI', 'Setting Pisau Mesin Die Cut', 'Plong + DieCut', 'Hasil plong tepat pada garis cetak dan tidak merusak lembar.',
            "1. Periksa pisau: tidak tumpul, tidak renggang.\n2. Pasang pisau dan matras, atur tekanan bertahap.\n3. Uji 5 lembar, cocokkan dengan dieline.\n4. Catat jumlah waste setting."],
        ['903/WI/TM/X/2026', 'WI', 'Pembersihan Lini Folder Gluer', 'Folder Glue', 'Tidak ada barang order lama tercampur dengan order berikutnya.',
            "1. Kosongkan conveyor dan bak lem.\n2. Singkirkan semua sisa barang dan label order lama.\n3. Bersihkan nozzle lem.\n4. Operator dan QC menandatangani form line clearance."],
    ];

    public function up(): void
    {
        $sys = Schema::connection('tm2sys');
        $db = DB::connection('tm2sys');
        $mst = DB::connection('tm2mst');
        $by = 'claude (v2 build)';

        // Work Instruction memakai register yang sama dengan SOP; yang membedakan hanya jenisnya.
        if (Schema::connection('tm2mst')->hasTable('sop_document') && ! Schema::connection('tm2mst')->hasColumn('sop_document', 'doc_type')) {
            Schema::connection('tm2mst')->table('sop_document', function (Blueprint $t) {
                $t->string('doc_type', 10)->default('SOP')->after('sop_code');   // SOP | WI
                $t->index('doc_type');
            });
        }
        if (str_starts_with($mst->getDatabaseName(), 'ddt')) {
            foreach (self::DUMMY as [$code, $type, $title, $department, $purpose, $content]) {
                if (! $mst->table('sop_document')->where('sop_code', $code)->exists()) {
                    $mst->table('sop_document')->insert(['sop_code' => $code, 'doc_type' => $type, 'title' => $title, 'revision' => '0', 'effective_date' => '2026-10-01',
                        'department_id' => $department ? $mst->table('org_department')->where('department_name', $department)->value('department_id') : null,
                        'purpose' => $purpose, 'content' => $content, 'activate' => 1, 'data_origin' => 'DUMMY', 'created_at' => now(), 'created_by' => $by]);
                }
            }
        }
        $db->table('sys_app_menu')->where('code', 'HRM_SOP')->where('label', 'SOP')->update(['label' => 'SOP & Work Instruction']);

        if (! $sys->hasTable('sys_dashboard')) {
            return; // papan belum dipasang di database ini
        }
        foreach (self::DASHBOARDS as $code => [$title, $icon, $module]) {
            if (! $db->table('sys_dashboard')->where('dashboard_code', $code)->exists()) {
                $db->table('sys_dashboard')->insert(['dashboard_code' => $code, 'title' => $title, 'icon' => $icon, 'module_code' => $module, 'min_level' => 0, 'sort_order' => self::ORDER[$code][1],
                    'activate' => 1, 'created_at' => now(), 'created_by' => $by]);
            }
        }
        foreach (self::ORDER as $code => [$old, $new]) {
            if ($old !== $new && $old > 0) {
                $db->table('sys_dashboard')->where('dashboard_code', $code)->where('sort_order', $old)->update(['sort_order' => $new]);
            }
        }
        foreach (self::HOME as $key => $dashboard) {
            $db->table('sys_widget')->where('widget_key', $key)->whereNull('dashboard_code')->update(['dashboard_code' => $dashboard]);
        }
        foreach (self::TEMPLATES as $dashboard => $rows) {
            foreach ($rows as $i => [$key, $size]) {
                if (! $db->table('sys_board_template')->where('profile_code', $dashboard)->where('widget_key', $key)->exists()) {
                    $db->table('sys_board_template')->insert(['profile_code' => $dashboard, 'widget_key' => $key, 'sort_order' => ($i + 1) * 10, 'size' => $size,
                        'activate' => 1, 'created_at' => now(), 'created_by' => $by]);
                }
            }
        }

        if (! $sys->hasTable('sys_dictionary')) {
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
        $db = DB::connection('tm2sys');
        $db->table('sys_board_template')->whereIn('profile_code', array_keys(self::TEMPLATES))->delete();
        $db->table('sys_dashboard')->whereIn('dashboard_code', array_keys(self::DASHBOARDS))->delete();
        foreach (self::ORDER as $code => [$old, $new]) {
            if ($old > 0) {
                $db->table('sys_dashboard')->where('dashboard_code', $code)->where('sort_order', $new)->update(['sort_order' => $old]);
            }
        }
        $db->table('sys_app_menu')->where('code', 'HRM_SOP')->where('label', 'SOP & Work Instruction')->update(['label' => 'SOP']);
        DB::connection('tm2mst')->table('sop_document')->whereIn('sop_code', array_column(self::DUMMY, 0))->delete();
        if (Schema::connection('tm2mst')->hasColumn('sop_document', 'doc_type')) {
            Schema::connection('tm2mst')->table('sop_document', function (Blueprint $t) {
                $t->dropIndex(['doc_type']);
                $t->dropColumn('doc_type');
            });
        }
    }
};
