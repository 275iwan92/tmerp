<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Dashboard per bidang + Overall dengan tanda centang (user 2026-10-02: "kerjakan widget sebanyak mungkin ... direksi tinggal
// pilih tickmark widget mana saja yang mau terlihat di dashboard overall"; "dashboard untuk sales, purchasing, AR AP, cashflow,
// payroll, human resource sudah disediakan templatenya"; "kumpulan widget yang ada di purchasing, sales dll inilah yang bisa
// dipilih untuk masuk ke dashboard overall").
//   sys_dashboard                 daftar dashboard: OVERALL (disusun pemiliknya) + enam dashboard bidang (template)
//   sys_widget.dashboard_code     dashboard asal widget = kelompoknya di daftar centang Choose Widgets
//   sys_user_board.dashboard_code dashboard yang sedang dibuka user
//   sys_board_template            profil MANAGEMENT -> OVERALL; template enam dashboard bidang
return new class extends Migration
{
    // kode => [judul, ikon, modul, urut]
    private const DASHBOARDS = [
        'OVERALL' => ['Overall', 'fa-solid fa-gauge-high', null, 10],
        'SALES' => ['Sales', 'fa-solid fa-file-signature', 'SALES', 20],
        'PURCHASING' => ['Purchasing', 'fa-solid fa-cart-shopping', 'PROC', 30],
        'AR_AP' => ['AR & AP', 'fa-solid fa-file-invoice-dollar', 'FINACC', 40],
        'CASHFLOW' => ['Cashflow', 'fa-solid fa-arrow-right-arrow-left', 'FINACC', 50],
        'PAYROLL' => ['Payroll', 'fa-solid fa-money-check-dollar', 'HRM', 60],
        'HR' => ['Human Resource', 'fa-solid fa-users', 'HRM', 70],
    ];

    // dashboard => [[widget, lebar dari 12], ...] berurutan
    private const TEMPLATES = [
        'SALES' => [['order_value', 3], ['new_orders', 3], ['quotation_win_rate', 3], ['ontime_delivery', 3], ['revenue_trend', 8], ['sales_pipeline', 4],
            ['sales_ranking', 6], ['top_customers', 6], ['deliveries_due', 4], ['waiting_price', 4], ['open_complaints', 4],
            ['open_quotations', 3], ['sales_today', 3], ['orders_by_status', 6]],
        'PURCHASING' => [['purchase_value', 4], ['open_purchase_orders', 4], ['purchase_requests', 4], ['top_suppliers', 6], ['material_shortage', 6]],
        'AR_AP' => [['overdue_receivables', 3], ['payables_overdue', 3], ['collections', 3], ['supplier_payments', 3], ['receivables_aging', 6], ['payables_aging', 6], ['top_debtors', 6]],
        'CASHFLOW' => [['net_cash_flow', 4], ['collections', 4], ['supplier_payments', 4], ['cash_in_out', 8], ['cash_balance', 4], ['top_expenses', 4]],
        'PAYROLL' => [['payroll_cost', 4], ['payroll_run_status', 4], ['payroll_breakdown', 4], ['payroll_trend', 12]],
        'HR' => [['headcount', 4], ['headcount_by_gender', 4], ['attendance_summary', 4], ['headcount_by_department', 6], ['recruitment_pipeline', 6], ['leave_waiting', 6]],
    ];

    // Dashboard asal widget yang barisnya sudah ada sebelum kolom dashboard_code (widget baru mendapatnya dari kelasnya).
    private const HOME = ['order_value' => 'SALES', 'new_orders' => 'SALES', 'ontime_delivery' => 'SALES', 'sales_today' => 'SALES', 'revenue_trend' => 'SALES',
        'sales_pipeline' => 'SALES', 'sales_ranking' => 'SALES', 'orders_by_status' => 'SALES', 'top_customers' => 'SALES',
        'overdue_receivables' => 'AR_AP', 'receivables_aging' => 'AR_AP', 'material_shortage' => 'PURCHASING'];

    private const UI = [
        // dashboard + papan
        'Overall' => 'Overall', 'Purchasing' => 'Purchasing', 'AR & AP' => 'Piutang & Hutang', 'Cashflow' => 'Arus Kas', 'Payroll' => 'Payroll', 'Human Resource' => 'SDM',
        'Finance' => 'Keuangan', 'Production' => 'Produksi',
        'Move, resize or remove a widget. Tick the widgets to show in Choose Widgets.' => 'Pindahkan, ubah lebar, atau lepas widget. Centang widget yang mau ditampilkan di Choose Widgets.',
        'Choose Widgets' => 'Choose Widgets', 'Tick the widgets you want on this board. Every tick is saved at once.' => 'Centang widget yang ingin tampil di papan ini. Setiap centang langsung tersimpan.',
        'Click Choose Widgets and tick the widgets you want to see.' => 'Klik Choose Widgets lalu centang widget yang ingin Anda lihat.',
        'This dashboard has no widget your account may see.' => 'Dashboard ini tidak punya widget yang boleh dilihat akun Anda.',
        'Add widget' => 'Tambah widget', 'Search widget...' => 'Cari widget...',
        'Tap a widget to add it; tap again to take it off. Saved at once.' => 'Ketuk widget untuk memasangnya; ketuk lagi untuk melepasnya. Langsung tersimpan.',
        'Drag a widget to move it; use the buttons to resize or remove it.' => 'Seret widget untuk memindahnya; pakai tombol untuk mengubah lebar atau melepasnya.',
        'This dashboard is now shown on your opening screen.' => 'Dashboard ini sekarang tampil di layar pembuka Anda.',
        'No widget is available for your account' => 'Tidak ada widget yang tersedia untuk akun Anda', 'This dashboard is not available for your account.' => 'Dashboard ini tidak tersedia untuk akun Anda.',
        // Sales
        'Deliveries Due & Late' => 'Pengiriman Jatuh Tempo & Terlambat', 'Planned deliveries that are past the promised date, due today, or due in the next 7 days.' => 'Rencana pengiriman yang lewat tanggal janji, jatuh hari ini, atau jatuh dalam 7 hari ke depan.',
        'Late :n days' => 'Terlambat :n hari', 'Today' => 'Hari ini', ':n days left' => ':n hari lagi', ':a late · :b today · :c in 7 days' => ':a terlambat · :b hari ini · :c dalam 7 hari',
        'No delivery is due' => 'Tidak ada pengiriman yang jatuh tempo',
        'Waiting for Price' => 'Menunggu Harga', 'Inquiries confirmed by Sales that still wait for Pro-Dev or Pro-Cost, longest wait first.' => 'Inquiry yang sudah di-Confirm Sales dan masih menunggu Pro-Dev atau Pro-Cost, yang paling lama menunggu di atas.',
        'Waiting for Pro-Dev' => 'Menunggu Pro-Dev', 'Waiting for Pro-Cost' => 'Menunggu Pro-Cost', ':n days' => ':n hari', 'Pro-Dev :a · Pro-Cost :b' => 'Pro-Dev :a · Pro-Cost :b',
        'No inquiry waits for a price' => 'Tidak ada inquiry yang menunggu harga',
        'Win Rate' => 'Tingkat Menang', 'Share of closed inquiries of the period that became a Customer Order: won against won plus lost.' => 'Bagian inquiry yang selesai pada periode itu dan menjadi Customer Order: menang dibanding menang ditambah kalah.',
        'No inquiry was closed in this period' => 'Tidak ada inquiry yang selesai pada periode ini', 'Won :a · Lost :b' => 'Menang :a · Kalah :b',
        'Open Quotations' => 'Quotation Terbuka', 'Value of quotations issued to customers that have not become a Customer Order yet, as of today.' => 'Nilai quotation yang sudah terbit ke customer dan belum menjadi Customer Order, per hari ini.',
        ':n quotations wait for the customer' => ':n quotation menunggu customer', ':n past the valid-until date' => ':n lewat tanggal berlaku',
        'Open Complaints' => 'Komplain Terbuka', 'Customer complaints and sales returns that are not closed yet, oldest first.' => 'Komplain customer dan retur penjualan yang belum ditutup, yang paling lama di atas.',
        ':n open' => ':n terbuka', 'No complaint is open' => 'Tidak ada komplain yang terbuka',
        // Purchasing
        'Purchase Value' => 'Nilai Pembelian', 'Approved Purchase Orders of the period against the period before, with the amount still waiting for approval.' => 'Purchase Order yang disetujui pada periode itu dibanding periode sebelumnya, dengan nilai yang masih menunggu approval.',
        ':n purchase orders' => ':n purchase order', 'Waiting for approval :a · Draft :b' => 'Menunggu approval :a · Draft :b',
        'Open Purchase Orders' => 'Purchase Order Terbuka', 'Purchase Orders approved or sent to the supplier that are not fully received yet, and how many are past the delivery date.' => 'Purchase Order yang sudah disetujui atau dikirim ke supplier dan belum diterima penuh, dan berapa yang lewat tanggal kirim.',
        'Value :v' => 'Nilai :v', ':n past the delivery date' => ':n lewat tanggal kirim',
        'Purchase Requisitions Waiting' => 'Purchase Requisition Menunggu', 'Purchase Requisitions that wait for approval, as of today.' => 'Purchase Requisition yang menunggu approval, per hari ini.',
        'Approved :a · Draft :b' => 'Disetujui :a · Draft :b',
        'Top Suppliers' => 'Supplier Teratas', 'The five suppliers with the highest approved Purchase Order value in the period.' => 'Lima supplier dengan nilai Purchase Order disetujui tertinggi pada periode itu.',
        'approved purchase orders' => 'purchase order disetujui', 'No supplier' => 'Tanpa supplier', 'Purchase value' => 'Nilai pembelian',
        // AR & AP
        'Overdue Payables' => 'Hutang Lewat Jatuh Tempo', 'Unpaid supplier invoices that are past their due date, as of today, and what falls due in the next 7 days.' => 'Tagihan supplier yang belum dibayar dan lewat jatuh tempo, per hari ini, dan yang jatuh tempo 7 hari ke depan.',
        'Due in 7 days :v' => 'Jatuh tempo 7 hari ke depan :v',
        'Payables Aging' => 'Umur Hutang', 'Unpaid supplier invoices grouped by days past the due date.' => 'Tagihan supplier yang belum dibayar, dikelompokkan menurut hari lewat jatuh tempo.',
        'Customer Payments Received' => 'Pembayaran Customer Diterima', 'Money received from customers and applied to their invoices in the period.' => 'Uang yang diterima dari customer dan dialokasikan ke invoice-nya pada periode itu.',
        'Supplier Payments Made' => 'Pembayaran ke Supplier', 'Money paid to suppliers and applied to their invoices in the period.' => 'Uang yang dibayarkan ke supplier dan dialokasikan ke invoice-nya pada periode itu.',
        'Top Debtors' => 'Piutang Terbesar', 'The five customers with the largest unpaid invoice balance, as of today.' => 'Lima customer dengan sisa tagihan terbesar, per hari ini.',
        'Unpaid balance, as of today' => 'Sisa tagihan, per hari ini',
        // Cashflow + Finance
        'Cash & Bank Balance' => 'Saldo Kas & Bank', 'Balance of every cash and bank account from posted vouchers, as of today.' => 'Saldo tiap akun kas dan bank dari voucher yang sudah diposting, per hari ini.',
        'Total :v' => 'Total :v', 'No cash or bank account has a posted entry' => 'Belum ada akun kas atau bank yang punya jurnal terposting',
        'Net Cash Flow' => 'Arus Kas Bersih', 'Cash in minus cash out of the period on the cash and bank accounts.' => 'Kas masuk dikurangi kas keluar pada periode itu di akun kas dan bank.',
        'In :a · Out :b' => 'Masuk :a · Keluar :b',
        'Cash In vs Cash Out' => 'Kas Masuk vs Kas Keluar', 'Cash received and cash paid per period on the cash and bank accounts.' => 'Kas yang diterima dan dibayarkan per periode di akun kas dan bank.',
        'Posted cash and bank entries' => 'Jurnal kas dan bank yang sudah diposting', 'Cash in' => 'Kas masuk', 'Cash out' => 'Kas keluar',
        'Top Expenses' => 'Biaya Terbesar', 'The largest expense accounts of the period from posted vouchers.' => 'Akun biaya terbesar pada periode itu dari voucher yang sudah diposting.',
        'Other' => 'Lainnya', 'Expense' => 'Biaya',
        'Income vs Expense' => 'Pendapatan vs Biaya', 'Income and expense per period from posted vouchers.' => 'Pendapatan dan biaya per periode dari voucher yang sudah diposting.',
        'Posted vouchers' => 'Voucher yang sudah diposting', 'Income' => 'Pendapatan',
        'Net Profit' => 'Laba Bersih', 'Income minus expense of the period from posted vouchers, with the margin.' => 'Pendapatan dikurangi biaya pada periode itu dari voucher yang sudah diposting, beserta marginnya.',
        'No income posted in this period' => 'Belum ada pendapatan yang diposting pada periode ini', 'Margin :p' => 'Margin :p', 'Income :a · Expense :b' => 'Pendapatan :a · Biaya :b',
        // Payroll
        'Payroll Net Pay' => 'Gaji Bersih Payroll', 'Net pay of the latest payroll run against the run before, with overtime and deductions.' => 'Gaji bersih perhitungan gaji terakhir dibanding yang sebelumnya, dengan lembur dan potongan.',
        'No payroll run yet' => 'Belum ada perhitungan gaji', ':n payslips' => ':n slip gaji', 'Overtime :a · Deductions :b' => 'Lembur :a · Potongan :b',
        'Net Pay per Payroll Run' => 'Gaji Bersih per Perhitungan Gaji', 'Net pay of the last eight payroll runs.' => 'Gaji bersih delapan perhitungan gaji terakhir.',
        'By period end date' => 'Menurut tanggal akhir periode', 'Net pay' => 'Gaji bersih',
        'Payroll Components' => 'Komponen Gaji', 'Earnings of the latest payroll run by component: basic pay, allowances, bonus, overtime.' => 'Penghasilan perhitungan gaji terakhir per komponen: gaji pokok, tunjangan, bonus, lembur.',
        'Deductions :v' => 'Potongan :v', 'Basic pay' => 'Gaji pokok', 'Allowances' => 'Tunjangan', 'Bonus' => 'Bonus', 'Overtime' => 'Lembur', 'Earnings' => 'Penghasilan',
        'Payroll Runs' => 'Perhitungan Gaji', 'The latest payroll runs with their status, number of payslips and payslips flagged with an anomaly.' => 'Perhitungan gaji terakhir dengan status, jumlah slip, dan slip yang ditandai anomali.',
        ':a payslips · :b with anomaly' => ':a slip gaji · :b beranomali',
        // Human Resource
        'Headcount' => 'Jumlah Pegawai', 'Active employees today, with those who joined and left in the period.' => 'Pegawai aktif hari ini, dengan yang masuk dan keluar pada periode itu.',
        'Active employees' => 'Pegawai aktif', 'Joined :a · Left :b' => 'Masuk :a · Keluar :b',
        'Headcount by Department' => 'Pegawai per Departemen', 'Active employees per department, the eight largest.' => 'Pegawai aktif per departemen, delapan terbanyak.',
        'Active employees today' => 'Pegawai aktif hari ini', 'Employees' => 'Pegawai',
        'Headcount by Gender' => 'Pegawai per Jenis Kelamin', 'Active employees split into male and female.' => 'Pegawai aktif menurut pria dan wanita.',
        'Male' => 'Pria', 'Female' => 'Wanita', 'Not filled in' => 'Belum diisi',
        'Attendance' => 'Kehadiran', 'Attendance of the period in person-days: present, late, leave or permit, sick, absent.' => 'Kehadiran pada periode itu dalam hari-orang: hadir, terlambat, cuti atau izin, sakit, alpa.',
        'person-days' => 'hari-orang', 'Person-days' => 'Hari-orang', 'Present' => 'Hadir', 'Late' => 'Terlambat', 'Leave / permit' => 'Cuti / izin', 'Sick' => 'Sakit', 'Absent' => 'Alpa',
        'Leave Requests Waiting' => 'Permohonan Cuti Menunggu', 'Leave, permit and sick requests that wait for a decision.' => 'Permohonan cuti, izin, dan sakit yang menunggu keputusan.',
        ':n requests' => ':n permohonan', 'No leave request waits for a decision' => 'Tidak ada permohonan cuti yang menunggu keputusan',
        'Recruitment Pipeline' => 'Pipeline Rekrutmen', 'Job applications by status, with the number of vacancies that are open now.' => 'Lamaran kerja per status, dengan jumlah lowongan yang sedang dibuka.',
        ':n open vacancies' => ':n lowongan dibuka', 'Applied' => 'Melamar', 'In progress' => 'Diproses', 'Hired' => 'Diterima', 'Rejected' => 'Ditolak', 'Applications' => 'Lamaran',
        // Produksi & stok
        'Work Orders by Status' => 'Work Order per Status', 'Work Orders by status: Draft, Simulated, Scheduled, In progress, On hold, Completed.' => 'Work Order per status: Draft, Simulated, Scheduled, In progress, On hold, Completed.',
        'As of today' => 'Per hari ini', 'Work Orders' => 'Work Order',
        'Late Work Orders' => 'Work Order Terlambat', 'Work Orders that are past their due date and not completed yet, longest overdue first.' => 'Work Order yang lewat due date dan belum selesai, yang paling lama terlambat di atas.',
        ':n work orders' => ':n work order', 'No Work Order is late' => 'Tidak ada Work Order yang terlambat',
        'Stock Value' => 'Nilai Stok', 'Value of usable stock on hand: raw material, semi finished goods and finished goods.' => 'Nilai stok on hand yang bisa dipakai: bahan baku, barang setengah jadi, dan barang jadi.',
        'Raw material' => 'Bahan baku', 'Semi finished goods' => 'Barang setengah jadi', 'Finished goods' => 'Barang jadi', 'Stock value' => 'Nilai stok',
        // layar Setup
        'The dashboards of the opening screen. Overall is arranged by its owner; the others are templates per area. Set the title, icon, the module a user must be allowed to open, the order and whether it is active.' => 'Dashboard layar pembuka. Overall disusun pemiliknya; yang lain template per bidang. Atur judul, ikon, modul yang harus boleh dibuka user, urutan, dan aktif tidaknya.',
        'Dashboard' => 'Dashboard', 'Icon' => 'Ikon', 'The Overall dashboard cannot be switched off.' => 'Dashboard Overall tidak bisa dimatikan.',
        'Widgets of the opening dashboards. The content is calculated by the program; here you set the title, the dashboard it belongs to, who may see it (module and minimum level), the default width and the words the assistant listens for.' => 'Widget dashboard pembuka. Isinya dihitung program; di sini diatur judul, dashboard asalnya, siapa yang boleh melihat (modul dan level minimum), lebar bawaan, dan kata yang didengar asisten.',
        'Widgets of each dashboard: which, in which order and how wide. An area dashboard shows its rows as they are; the Overall rows are the board a user starts with and returns to on Reset to Template.' => 'Widget tiap dashboard: yang mana, urutannya, dan lebarnya. Dashboard bidang menampilkan barisnya apa adanya; baris Overall adalah papan awal user dan tujuan Reset to Template.',
        'This widget is already in the template of that dashboard.' => 'Widget ini sudah ada di template dashboard itu.',
    ];

    public function up(): void
    {
        $sys = Schema::connection('tm2sys');
        $db = DB::connection('tm2sys');
        $by = 'claude (v2 build)';
        if (! $sys->hasTable('sys_widget')) {
            return; // papan belum dipasang di database ini
        }
        if (! $sys->hasTable('sys_dashboard')) {
            $sys->create('sys_dashboard', function (Blueprint $t) {
                $t->id('id_dashboard');
                $t->string('dashboard_code', 30)->unique();
                $t->string('title', 60);
                $t->string('icon', 60)->nullable();
                $t->string('module_code', 20)->nullable();        // sec_module: user wajib boleh membuka modul ini (atau Laporan Manajemen)
                $t->unsignedTinyInteger('min_level')->default(0);
                $t->unsignedInteger('sort_order')->default(0);
                $t->boolean('activate')->default(true);
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 50)->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->string('updated_by', 50)->nullable();
            });
        }
        if (! $sys->hasColumn('sys_widget', 'dashboard_code')) {
            $sys->table('sys_widget', fn (Blueprint $t) => $t->string('dashboard_code', 30)->nullable()->after('group_name'));
        }
        if (! $sys->hasColumn('sys_user_board', 'dashboard_code')) {
            $sys->table('sys_user_board', fn (Blueprint $t) => $t->string('dashboard_code', 30)->default('OVERALL')->after('period_code'));
        }

        foreach (self::DASHBOARDS as $code => [$title, $icon, $module, $sort]) {
            if (! $db->table('sys_dashboard')->where('dashboard_code', $code)->exists()) {
                $db->table('sys_dashboard')->insert(['dashboard_code' => $code, 'title' => $title, 'icon' => $icon, 'module_code' => $module, 'min_level' => 0, 'sort_order' => $sort,
                    'activate' => 1, 'created_at' => now(), 'created_by' => $by]);
            }
        }
        foreach (self::HOME as $key => $dashboard) {
            $db->table('sys_widget')->where('widget_key', $key)->whereNull('dashboard_code')->update(['dashboard_code' => $dashboard]);
        }

        // Template papan pemilik: profil MANAGEMENT -> dashboard OVERALL.
        $db->table('sys_board_template')->where('profile_code', 'MANAGEMENT')->update(['profile_code' => 'OVERALL']);
        foreach (self::TEMPLATES as $dashboard => $rows) {
            foreach ($rows as $i => [$key, $size]) {
                if (! $db->table('sys_board_template')->where('profile_code', $dashboard)->where('widget_key', $key)->exists()) {
                    $db->table('sys_board_template')->insert(['profile_code' => $dashboard, 'widget_key' => $key, 'sort_order' => ($i + 1) * 10, 'size' => $size,
                        'activate' => 1, 'created_at' => now(), 'created_by' => $by]);
                }
            }
        }

        $menu = fn () => $db->table('sys_app_menu');
        $near = $menu()->where('code', 'SET_WIDGETS')->first();
        if ($near && ! $menu()->where('code', 'SET_DASHBOARDS')->exists()) {
            $menu()->insert(['parent_id' => $near->parent_id, 'code' => 'SET_DASHBOARDS', 'label' => 'Opening Dashboards', 'icon' => 'fa-solid fa-gauge-high', 'menu_type' => 'item',
                'object_type' => 'grid', 'target' => 'v2:setup-dashboards', 'sort_order' => 50, 'activate' => 1, 'required_object' => $near->required_object,
                'status_code' => 'READY_FOR_QC', 'status_updated_by' => $by, 'status_updated_at' => now(), 'created_at' => now()]);
        }

        // Perintah asisten "buka dashboard ...": baris katalog yang sudah ada mendapat kata pemicu baru (baris baru lahir dari kelasnya).
        if ($sys->hasTable('sys_assistant_metric')) {
            $row = $db->table('sys_assistant_metric')->where('metric_key', 'board_widgets')->first();
            if ($row && ! str_contains((string) $row->phrases, 'dashboard')) {
                $db->table('sys_assistant_metric')->where('metric_key', 'board_widgets')->update(['phrases' => trim($row->phrases.', dashboard, dasbor', ', ')]);
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
        $db->table('sys_app_menu')->where('code', 'SET_DASHBOARDS')->delete();
        $db->table('sys_board_template')->whereIn('profile_code', array_keys(self::TEMPLATES))->delete();
        $db->table('sys_board_template')->where('profile_code', 'OVERALL')->update(['profile_code' => 'MANAGEMENT']);
        Schema::connection('tm2sys')->dropIfExists('sys_dashboard');
    }
};
