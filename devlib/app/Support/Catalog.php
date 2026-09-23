<?php

namespace App\Support;

use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

// Menu situs DevLib. Tiap modul = satu kelompok komponen library:
//  - "Komponen": halaman komponen hidup (demo = kode pemakaian yang tampil di ikon </>)
//  - "Mockup belum dibangun": mockup user yang komponennya belum ada.
// status komponen: review = menunggu penilaian user; hanya user yang boleh
// menaikkan ke approved.
final class Catalog
{
    public const FIELDS = [
        'text' => ['Text', 'Text'], 'password' => ['Password', 'Password'], 'select' => ['Select (searchable)', 'Select'],
        'color' => ['Color', 'Color'], 'radio' => ['Radio', 'Radio'], 'toggle' => ['Toggle', 'Toggle'],
        'checkboxes' => ['Checkboxes', 'Checkboxes'], 'slider' => ['Slider', 'Slider'], 'date' => ['Date', 'Date'], 'time' => ['Time', 'Time'],
        'date-time' => ['Date Time', 'DateTime'], 'date-range' => ['Date Range', 'DateRange'],
        'multi-select' => ['Multi Select', 'MultiSelect'], 'otp' => ['OTP', 'Otp'], 'rating' => ['Rating', 'Rating'],
        'masked' => ['Masked (NIK)', 'Masked'], 'phone' => ['Phone', 'Phone'], 'credit-card' => ['Credit Card', 'CreditCard'], 'signature' => ['Signature', 'Signature'], 'date-slider' => ['Date Slider', 'DateSlider'], 'money' => ['Money', 'Money'], 'number-words' => ['Number + Terbilang', 'NumberWords'],
        'decimal' => ['Decimal', 'Decimal'], 'number' => ['Number (spinner)', 'Number'], 'upload' => ['Upload', 'Upload'],
    ];

    public const LOGIN_CLASSES = ['Devlib\Components\Frame', 'Devlib\Components\LoginBox', 'Devlib\Components\Clock', 'Devlib\Components\Quote',
        'Devlib\Components\Greeting', 'Devlib\Components\Weather', 'Devlib\Components\Announce', 'Devlib\Components\UatAccounts'];

    public static function groups(): array
    {
        $fieldPages = [];
        foreach (self::FIELDS as $slug => [$label, $cls]) {
            $fieldPages['field-'.$slug] = ['label' => 'Field · '.$label, 'icon' => 'fa-solid fa-i-cursor', 'classes' => ['Devlib\\Components\\Field\\'.$cls], 'mockup' => 'UIUX_components_part1.html'];
        }
        $basic = fn ($label, $icon, $classes = [], $mockup = 'UIUX_components_part1.html') => ['label' => $label, 'icon' => $icon, 'classes' => $classes, 'mockup' => $mockup];

        // Urutan = urutan membangun (bawah ke atas): tiap Part memakai Part sebelumnya.
        return [
            ['code' => 'P1', 'short' => 'BT', 'label' => '1 · Basic & Themes', 'icon' => 'fa-solid fa-palette',
                'pages' => [
                    'basic-colors' => $basic('Colors', 'fa-solid fa-droplet', ['Devlib\\Components\\TokenTable'], 'mockup_dashboardcolor.html'),
                    'basic-typography' => $basic('Typography', 'fa-solid fa-font', ['Devlib\\Components\\TokenTable']),
                    'basic-shape' => $basic('Spacing · Radius · Shadow', 'fa-solid fa-vector-square', ['Devlib\\Components\\TokenTable']),
                    'basic-themes' => $basic('Themes', 'fa-solid fa-swatchbook', ['Devlib\\Components\\ThemePicker', 'Devlib\\Components\\ThemeBoot'], 'bukti_komponen_tema_navy.html'),
                    'basic-density' => $basic('Density', 'fa-solid fa-table-cells', ['Devlib\\Components\\ThemePicker'], 'grid_kerapatan_baris.html'),
                    'basic-icons' => $basic('Icons', 'fa-solid fa-icons', ['Devlib\\Components\\Icon'], 'pilihan_ikon_aksi_grid.html'),
                    'basic-breakpoints' => $basic('Breakpoints', 'fa-solid fa-mobile-screen', ['Devlib\\Components\\TokenTable']),
                    'basic-locale' => $basic('Locale & Formats', 'fa-solid fa-earth-asia', []),
                ],
                'files' => ['Mockup' => ['mockup_dashboardcolor.html', 'bukti_komponen_tema_navy.html', 'bukti_komponen_tema_emerald.html', 'bukti_komponen_tema_cerah_red.html']]],
            ['code' => 'P2', 'short' => 'SC', 'label' => '2 · Small Components', 'icon' => 'fa-solid fa-shapes',
                'pages' => self::smallCategories(),
                'files' => ['Mockup' => ['bukti_komponen_input_tema.html', 'penjelasan_standar_input_teks.html']]],
            ['code' => 'P3', 'short' => 'FS', 'label' => '3 · Feedback & States', 'icon' => 'fa-solid fa-bell', 'pages' => self::feedbackCategories(), 'files' => []],
            ['code' => 'P4', 'short' => 'GB', 'label' => '4 · Global Behaviors', 'icon' => 'fa-solid fa-gears', 'pages' => self::behaviorCategories(), 'files' => []],
            ['code' => 'P5', 'short' => 'BC', 'label' => '5 · Big Components', 'icon' => 'fa-solid fa-table-list',
                'pages' => ['p5-grid' => ['label' => 'Grid (10 varian)', 'icon' => 'fa-solid fa-table', 'part' => '5 · Big Components', 'mockup' => 'grid_kerapatan_baris.html',
                    'members' => [
                        'grid-master' => ['1 · Grid for Master (Edit + Aktif/Nonaktif)', ['Devlib\\Components\\Grid']],
                        'grid-worklist' => ['2 · Grid for Single Transaction (worklist)', ['Devlib\\Components\\Grid']],
                        'grid-master-detail' => ['3 · Grid for Master-Detail (baris dibuka = item)', ['Devlib\\Components\\Grid']],
                        'grid-drilldown' => ['4 · Grid Summary → Details (drill-down)', ['Devlib\\Components\\Drilldown', 'Devlib\\Components\\Grid']],
                        'grid-pivot' => ['5 · Grid Pivot (baris × kolom × nilai)', ['Devlib\\Components\\Pivot']],
                        'grid-group' => ['6 · Grid Group By (+ subtotal)', ['Devlib\\Components\\Grid']],
                        'grid-photos' => ['7 · Grid with Photos (tabel ↔ kartu)', ['Devlib\\Components\\Grid']],
                        'grid-status' => ['8 · Grid with Status Bar (tahapan + progres)', ['Devlib\\Components\\Grid']],
                        'grid-mix' => ['9 · Grid with Mix Component', ['Devlib\\Components\\Grid']],
                        'grid-sheet' => ['10 · Grid Spreadsheet (edit langsung, Excel copy/paste, drag-fill)', ['Devlib\\Components\\Sheet']],
                    ],
                    'classes' => ['Devlib\\Components\\Grid', 'Devlib\\Components\\Drilldown', 'Devlib\\Components\\Pivot', 'Devlib\\Components\\Sheet']],
                    'p5-forms' => ['label' => 'Form (9 varian)', 'icon' => 'fa-solid fa-pen-to-square', 'part' => '5 · Big Components', 'mockup' => 'UIUX_components_part1.html',
                    'members' => [
                        'form' => ['1 · Form Standard (semua jenis field)', ['Devlib\\Components\\Form']],
                        'form-tabs' => ['2 · Form with Tabs (tabular)', ['Devlib\\Components\\Form', 'Devlib\\Components\\Tabs']],
                        'form-md' => ['3 · Form Master-Detail (header + baris item)', ['Devlib\\Components\\Form', 'Devlib\\Components\\Lines']],
                        'form-approval' => ['4 · Form with Approval', ['Devlib\\Components\\Form', 'Devlib\\Components\\ApprovalBar']],
                        'form-view' => ['5 · Form Read-only (mode lihat + Edit)', ['Devlib\\Components\\Form']],
                        'form-popup' => ['6 · Form Pop-up (modal & drawer)', ['Devlib\\Components\\Modal', 'Devlib\\Components\\Drawer', 'Devlib\\Components\\Form']],
                        'wizard-customer' => ['7 · Form Wizard (bertahap)', ['Devlib\\Components\\Wizard']],
                        'form-filter' => ['8 · Form Filter / Kriteria Laporan', ['Devlib\\Components\\FilterForm']],
                        'form-quickadd' => ['9 · Form Quick-add di dalam Grid', ['Devlib\\Components\\Grid']],
                    ],
                    'classes' => ['Devlib\\Components\\Form', 'Devlib\\Components\\ApprovalBar', 'Devlib\\Components\\FilterForm']],
                    'p5-master' => ['label' => 'Master', 'icon' => 'fa-solid fa-address-book', 'part' => '5 · Big Components', 'mockup' => 'mockup_master_customer.html',
                        'members' => ['master-employee' => ['Master Pegawai (grid + form edit)', ['Devlib\\Components\\Master', 'Devlib\\Components\\Grid']]],
                        'classes' => ['Devlib\\Components\\Master', 'Devlib\\Components\\Grid']],
                    'p5-master-detail' => ['label' => 'Master-Detail', 'icon' => 'fa-solid fa-file-invoice', 'part' => '5 · Big Components', 'mockup' => 'mockup_purchase_requisition.html',
                        'members' => ['master-detail-po' => ['Purchase Order (header + baris item)', ['Devlib\\Components\\Master', 'Devlib\\Components\\Lines', 'Devlib\\Components\\Grid']]],
                        'classes' => ['Devlib\\Components\\Lines', 'Devlib\\Components\\Master']],
                    'p5-approval' => ['label' => 'Approval', 'icon' => 'fa-solid fa-stamp', 'part' => '5 · Big Components', 'mockup' => 'mockup_po_approval_center.html',
                        'members' => ['approval-po' => ['Approval Center PO', ['Devlib\\Components\\Approval']]],
                        'classes' => ['Devlib\\Components\\Approval']],
                    'p5-wizard' => ['label' => 'Wizard', 'icon' => 'fa-solid fa-shoe-prints', 'part' => '5 · Big Components', 'mockup' => null,
                        'members' => ['wizard-customer' => ['Registrasi Customer (3 langkah + Review)', ['Devlib\\Components\\Wizard', 'Devlib\\Components\\Wizard\\Step']]],
                        'classes' => ['Devlib\\Components\\Wizard', 'Devlib\\Components\\Wizard\\Step']],
                    'p5-kanban' => ['label' => 'Kanban', 'icon' => 'fa-solid fa-table-columns', 'part' => '5 · Big Components', 'mockup' => 'mockup_UIUX_kanban.html',
                        'members' => ['kanban-production' => ['Papan Produksi (drag, WIP, aturan pindah, detail kartu)', ['Devlib\\Components\\Kanban']]],
                        'classes' => ['Devlib\\Components\\Kanban']],
                    'p5-gantt' => ['label' => 'Gantt', 'icon' => 'fa-solid fa-bars-staggered', 'part' => '5 · Big Components', 'mockup' => 'mockup_scheduling_board.html',
                        'members' => ['gantt-scheduling' => ['Scheduling Board (resource x jam)', ['Devlib\\Components\\Gantt']]],
                        'classes' => ['Devlib\\Components\\Gantt']],
                    'p5-calendar' => ['label' => 'Calendar', 'icon' => 'fa-solid fa-calendar-days', 'part' => '5 · Big Components', 'mockup' => 'mockup_UIUX_calendar.html',
                        'members' => ['calendar-events' => ['Kalender Acara (hari / minggu / bulan)', ['Devlib\\Components\\Calendar']]],
                        'classes' => ['Devlib\\Components\\Calendar']],
                    'p5-print' => ['label' => 'Print Out', 'icon' => 'fa-solid fa-print', 'part' => '5 · Big Components', 'mockup' => 'nota_co_mockup.html',
                        'members' => ['print-co' => ['Nota Customer Order (harga, PPN, terbilang, multi-halaman)', ['Devlib\\Components\\PrintOut']], 'print-spb' => ['Surat Penerimaan Barang (header bertingkat, tanpa harga)', ['Devlib\\Components\\PrintOut']]],
                        'classes' => ['Devlib\\Components\\PrintOut', 'Devlib\\Terbilang']],
                    'p5-report' => ['label' => 'Report', 'icon' => 'fa-solid fa-file-lines', 'part' => '5 · Big Components', 'mockup' => 'mockup_print_master_stock.html',
                        'members' => ['report-stock-card' => ['Kartu Stok (saldo berjalan, per bulan)', ['Devlib\\Components\\Report']], 'report-master-stock' => ['Master Stok (KPI, kelompok + subtotal, sorot < MIN)', ['Devlib\\Components\\Report']]],
                        'classes' => ['Devlib\\Components\\Report', 'Devlib\\Components\\PrintOut']],
                    'p5-import' => ['label' => 'Import / Export', 'icon' => 'fa-solid fa-file-import', 'part' => '5 · Big Components', 'mockup' => null,
                        'members' => ['import-items' => ['Import CSV (cocokkan kolom, validasi, baris gagal)', ['Devlib\\Components\\Import']], 'grid-master' => ['Export CSV dari Grid (kolom yang tampil)', ['Devlib\\Components\\Grid']]],
                        'classes' => ['Devlib\\Components\\Import', 'Devlib\\Components\\Grid']],
                    'p5-org' => ['label' => 'Org Chart', 'icon' => 'fa-solid fa-sitemap', 'part' => '5 · Big Components', 'mockup' => 'Organization-Structure-Mockup.html',
                        'members' => ['org-chart' => ['Struktur Organisasi (drag pindah atasan, lowong, multi pemegang)', ['Devlib\\Components\\OrgChart']]],
                        'classes' => ['Devlib\\Components\\OrgChart']],
                    'form' => ['label' => 'Form', 'icon' => 'fa-solid fa-pen-to-square', 'full' => true,
                    'classes' => ['Devlib\\Components\\Form', 'Devlib\\Components\\Form\\Col', 'Devlib\\Components\\Form\\Row', 'Devlib\\Components\\DeviceFrame'], 'mockup' => 'UIUX_components_part1.html']],
                'files' => [
                    'Mockup · Grid & Master' => ['grid_kerapatan_baris.html', 'pilihan_ikon_aksi_grid.html', 'pilihan_ikon_aksi_grid_konteks.html', 'mockup_master_employee.html', 'filament_table_demo.html', 'powergrid_demo.html', 'mockup_master_uom_v4.html', 'mockup_master_customer.html',
                        'mockup_master_rawmaterial.html', 'mockup_master_warehouse.html', 'mockup_master_produksi.html', 'master_mesin_update_2026.html', 'master_mesin_update_2026.jpg', 'master_coa_mockup.html'],
                    'Mockup · Dokumen & Approval' => ['mockup_purchase_requisition.html', 'mockup_pr_approval_center.html', 'mockup_po_approval_center.html', 'mockup_purchase_quotation.html',
                        'mockup_purchase_receive.html', 'mockup_purchase_return.html', 'mockup_flow_engine.html', 'mockup_sales_quotation_entry.html', 'mockup_co_entry.html',
                        'co_delivery_plan_mockup.html', 'mockup_down_payment.html', 'mockup_stock_in.html', 'mockup_stock_out.html', 'mockup_stock_return.html', 'mockup_stock_adjustment.html',
                        'mockup_personal_data_self_service.html', 'MODULE_accounting-mockup-blueprint.html', 'MODULE_accounting-process-engine-blueprint.html'],
                    'Mockup · Kanban, Kalender, Gantt' => ['mockup_UIUX_kanban.html', 'mockup_UIUX_calendar.html', 'mockup_scheduling_board.html', 'mockup_roster_shift.html',
                        'mockup_roster_formasi_harian.html', 'mockup_roster_formasi_harian.jpg', 'real_roster_formasi_editor.jpg', 'real_roster_formasi_ringkas.jpg', 'real_roster_formasi_harian.jpg'],
                    'Mockup · Report & Print Out' => ['nota_co_mockup.html', 'nota_pv_spb.html', 'mockup_print_stock_card.html', 'mockup_print_master_stock.html',
                        'mockup_sales_quotation_letter_print.html', 'payroll_payslip_list.html', 'payroll_payslip_list.jpg'],
                    'Mockup · Inquiry & ProCost (zona beku)' => ['inquiry_main_hub.html', 'inquiry_costing_hub.html', 'inquiry_costing_manual.html', 'inquiry_proof.html', 'inquiry_quotation.html',
                        'inquiry_nota_new.html', 'inquiry_nota_revise.html', 'inquiry_nota_recalc.html', 'inquiry_nota_quotation.html', 'nota_inquiry_mockup.html', 'nota_inquiry_mockup_bw.html',
                        'ERP_Product_Costing_Mockup.html', 'Kalkulasi_HPP_UAT.html', 'product_costing.html', 'mockup_legacy_product_costing.png',
                        'inquiry_product_costing/mockup_product_costing_v1_scriptcase.png', 'procost_instant_hasil_build.png', 'procost_integrasi_hasil_build.png',
                        'procost_instant_deviasi.jpg', 'procost_instant_deviasi_v2.jpg', 'procost_integrasi_deviasi.jpg', 'procost_integrasi_deviasi_v2.jpg'],
                ]],
            ['code' => 'P6', 'short' => 'SP', 'label' => '6 · Special Components', 'icon' => 'fa-solid fa-draw-polygon',
                'pages' => ['p6-dieline' => ['label' => 'Dieline (mockup kemasan)', 'icon' => 'fa-solid fa-box-open', 'part' => '6 · Special Components', 'mockup' => 'inquiry_new.html',
                    'members' => ['dieline' => ['v1 · Dieline hidup: tipe, ukuran pasaran, dimensi, bentang', ['Devlib\\Components\\Dieline']], 'dieline-v2' => ['v2 · + garis ukuran, istilah internasional, mm/cm', ['Devlib\\Components\\DielineV2']], 'dieline-v3' => ['v3 · + judul, panjang cutting/creasing, 3D statis', ['Devlib\\Components\\DielineV3']]], 'classes' => ['Devlib\\Components\\Dieline', 'Devlib\\Components\\DielineV2', 'Devlib\\Components\\DielineV3']],
                    'p6-imposition' => ['label' => 'Imposition (tata letak plano)', 'icon' => 'fa-solid fa-table-cells', 'part' => '6 · Special Components', 'mockup' => 'mockup_prodev_imposition.html',
                    'members' => ['imposition' => ['Kanvas imposition: hasil ImpositionEngine + dieline + PCS/PLANO', ['Devlib\\Components\\Imposition']]], 'classes' => ['Devlib\\Components\\Imposition']],
                    'p6-charts' => ['label' => 'Charts', 'icon' => 'fa-solid fa-chart-pie', 'part' => '6 · Special Components', 'mockup' => 'mockup_dashboardcolor.html',
                    'members' => ['chart-bars' => ['Batang: bar, stacked, horizontal, combo', ['Devlib\\Components\\Chart']], 'chart-lines' => ['Garis, area, radar, heatmap', ['Devlib\\Components\\Chart']], 'chart-pies' => ['Pie, donut, funnel', ['Devlib\\Components\\Chart']]],
                    'classes' => ['Devlib\\Components\\Chart']],
                    'p6-indicators' => ['label' => 'Dashboard Indicators', 'icon' => 'fa-solid fa-gauge-high', 'part' => '6 · Special Components', 'mockup' => null,
                    'members' => ['indicators' => ['KPI, gauge, progress ring, bullet, traffic light', ['Devlib\\Components\\Kpi', 'Devlib\\Components\\Gauge', 'Devlib\\Components\\ProgressRing', 'Devlib\\Components\\Bullet', 'Devlib\\Components\\TrafficLight']]],
                    'classes' => ['Devlib\\Components\\Kpi', 'Devlib\\Components\\Gauge', 'Devlib\\Components\\ProgressRing', 'Devlib\\Components\\Bullet', 'Devlib\\Components\\TrafficLight']],
                    'p6-codes' => ['label' => 'QR & Barcode', 'icon' => 'fa-solid fa-qrcode', 'part' => '6 · Special Components', 'mockup' => null,
                    'members' => ['codes' => ['QR & Barcode (Code128, EAN-13)', ['Devlib\\Components\\Qr', 'Devlib\\Components\\Barcode']]], 'classes' => ['Devlib\\Components\\Qr', 'Devlib\\Components\\Barcode']],
                    'p6-idcard' => ['label' => 'ID Card', 'icon' => 'fa-solid fa-id-badge', 'part' => '6 · Special Components', 'mockup' => 'mockup_ID_card.png',
                    'members' => ['id-cards' => ['Kartu pegawai: 6 desain (lanskap & potret)', ['Devlib\\Components\\IdCard', 'Devlib\\Components\\Qr']]], 'classes' => ['Devlib\\Components\\IdCard']],
                    'p6-flow' => ['label' => 'Flow Config (alur approval)', 'icon' => 'fa-solid fa-diagram-project', 'part' => '6 · Special Components', 'mockup' => 'mockup_flow_engine.html',
                    'members' => ['flow-config' => ['Setup alur approval per dokumen + simulator nilai', ['Devlib\\Components\\FlowConfig', 'Devlib\\Components\\Lines']]], 'classes' => ['Devlib\\Components\\FlowConfig']],
                    'p6-roster' => ['label' => 'Roster Board (shift mingguan)', 'icon' => 'fa-solid fa-user-clock', 'part' => '6 · Special Components', 'mockup' => 'mockup_roster_shift.html',
                    'members' => ['roster-board' => ['Roster mingguan: shift x hari, pool pegawai, drag / centang+klik', ['Devlib\\Components\\RosterBoard']]], 'classes' => ['Devlib\\Components\\RosterBoard']]],
                'files' => ['Mockup' => ['inquiry_new.html', 'inquiry_revise_v2.html', 'inquiry_recalc_v2.html', 'mockup_inquiry_imposition_grid_form.html', 'mockup_prodev_imposition.html',
                    'tata layout layar imposistion.jpg', 'Organization-Structure-Mockup.html', 'mockup_ID_card.png', 'mockup_mastercard.jpg', 'mockup_mastercard_ver2.jpg']]],
            ['code' => 'P7', 'short' => 'MD', 'label' => '7 · Menu & Dashboard', 'icon' => 'fa-solid fa-table-columns',
                'pages' => ['shell' => ['label' => 'Shell (menu utama)', 'icon' => 'fa-solid fa-table-columns', 'full' => true, 'classes' => ['Devlib\\Components\\Shell'], 'mockup' => 'main_menu_v2.html']],
                'files' => ['Mockup' => ['main_menu_v2.html']]],
            ['code' => 'P8', 'short' => 'LA', 'label' => '8 · Login & Account', 'icon' => 'fa-solid fa-right-to-bracket',
                'pages' => [
                    'login-1' => ['label' => 'Login 1 · sama dengan tmerp', 'icon' => 'fa-solid fa-right-to-bracket', 'full' => true, 'classes' => self::LOGIN_CLASSES, 'mockup' => 'login.html'],
                    'login-2' => ['label' => 'Login 2 · popup kaca', 'icon' => 'fa-solid fa-right-to-bracket', 'full' => true, 'classes' => self::LOGIN_CLASSES, 'mockup' => 'login.html'],
                    'login-3' => ['label' => 'Login 3 · panel kiri navy', 'icon' => 'fa-solid fa-right-to-bracket', 'full' => true, 'classes' => self::LOGIN_CLASSES, 'mockup' => 'login.html'],
                ],
                'files' => ['Mockup' => ['login.html', 'register.html', 'forgot-password.html', 'security.html']]],
        ];
    }

    // Mockup yang sudah punya komponen -> tidak diulang di daftar "belum dibangun".
    public static function builtMockups(): array
    {
        return ['login.html', 'main_menu_v2.html', 'UIUX_components_part1.html', 'mockup_notification.jpg', 'mockup_dashboardcolor.html', 'bukti_komponen_tema_navy.html',
            'bukti_komponen_tema_emerald.html', 'bukti_komponen_tema_cerah_red.html', 'bukti_komponen_input_tema.html', 'penjelasan_standar_input_teks.html',
            'grid_kerapatan_baris.html', 'pilihan_ikon_aksi_grid.html', 'pilihan_ikon_aksi_grid_konteks.html', 'mockup_master_employee.html', 'filament_table_demo.html', 'powergrid_demo.html', 'mockup_master_customer.html', 'mockup_master_uom_v4.html', 'mockup_purchase_requisition.html', 'mockup_pr_approval_center.html', 'mockup_po_approval_center.html', 'mockup_UIUX_kanban.html', 'mockup_scheduling_board.html', 'mockup_UIUX_calendar.html', 'nota_co_mockup.html', 'nota_pv_spb.html', 'mockup_print_stock_card.html', 'mockup_print_master_stock.html', 'Organization-Structure-Mockup.html'];
    }

    // Part 2 dikelompokkan per kategori (menu kiri ringkas); isi kategori dipilih lewat breadcrumb.
    // member: slug demo => [label, [class...]]
    public static function smallCategories(): array
    {
        $f = fn ($cls) => ['Devlib\\Components\\Field\\'.$cls];
        $c = fn (...$cls) => array_map(fn ($x) => 'Devlib\\Components\\'.$x, $cls);

        $cats = [
            'p2-inputs' => ['Inputs', 'fa-solid fa-i-cursor', [
                'field-text' => ['Text', $f('Text')], 'text-affix' => ['Prefix · Suffix · Counter', $f('Text')], 'field-password' => ['Password', $f('Password')],
                'field-masked' => ['Masked (NIK)', $f('Masked')], 'field-phone' => ['Phone (HP & kantor, per negara)', $f('Phone')], 'field-credit-card' => ['Credit Card', $f('CreditCard')], 'field-signature' => ['Signature', $f('Signature')], 'field-tags' => ['Tags', $f('Tags')],
                'field-key-value' => ['Key Value', $f('KeyValue')], 'field-rich-text' => ['Rich Text', $f('RichText')], 'input-theme' => ['Input · Themes', $f('Text')],
            ]],
            'p2-choices' => ['Choices & Lookup', 'fa-solid fa-list-check', [
                'field-lookup' => ['Lookup', $f('Lookup')], 'field-select' => ['Select', $f('Select')], 'field-multi-select' => ['Multi Select', $f('MultiSelect')],
                'field-radio' => ['Radio', $f('Radio')], 'field-checkboxes' => ['Checkboxes', $f('Checkboxes')], 'field-checkbox' => ['Checkbox', $f('Checkbox')],
                'field-toggle' => ['Toggle', $f('Toggle')], 'field-toggle-group' => ['Toggle Group', $f('ToggleGroup')], 'field-rating' => ['Rating (half · read-only)', $f('Rating')],
                'field-color' => ['Color', $f('Color')], 'field-slider' => ['Slider (range · vertical)', $f('Slider')], 'field-otp' => ['OTP', $f('Otp')],
            ]],
            'p2-datetime' => ['Date & Time', 'fa-solid fa-calendar-days', [
                'field-date' => ['Date', $f('Date')], 'field-time' => ['Time', $f('Time')], 'field-date-time' => ['Date Time', $f('DateTime')], 'field-date-range' => ['Date Range', $f('DateRange')], 'field-date-slider' => ['Date Slider', $f('DateSlider')],
            ]],
            'p2-numbers' => ['Numbers & Money', 'fa-solid fa-calculator', [
                'field-money' => ['Money', $f('Money')], 'field-number-words' => ['Number + Terbilang', $f('NumberWords')], 'field-decimal' => ['Decimal', $f('Decimal')], 'field-number' => ['Number (spinner)', $f('Number')],
            ]],
            'p2-files' => ['Files', 'fa-solid fa-paperclip', ['field-upload' => ['Upload', $f('Upload')]]],
            'p2-buttons' => ['Buttons', 'fa-solid fa-hand-pointer', [
                'button' => ['Button · Preset · Split', $c('Button', 'SplitButton', 'BackButton')], 'icon-button' => ['Icon Button', $c('IconButton')],
            ]],
            'p2-display' => ['Display & Status', 'fa-solid fa-tag', [
                'field-display' => ['Display (read-only)', $f('Display')], 'badge' => ['Badge', $c('Badge')], 'stat-box' => ['Stat Box', $c('StatBox')], 'status-card' => ['Status Card', $c('StatusCard')],
                'avatar-progress' => ['Avatar · Progress', $c('Avatar', 'Progress')], 'tooltip' => ['Tooltip', $c('Tooltip')], 'misc' => ['Alert · Divider · Brand · Language', $c('Alert', 'Divider', 'Brand', 'LangSwitch')],
            ]],
            'p2-containers' => ['Containers & Navigation', 'fa-solid fa-table-cells-large', [
                'card-actionbar' => ['Card · Action Bar', $c('Card', 'ActionBar')], 'table' => ['Table', $c('Table')], 'tabs-accordion' => ['Tabs', $c('Tabs')], 'accordion' => ['Accordion (vertical · horizontal)', $c('Accordion', 'AccordionGroup')],
                'breadcrumb' => ['Breadcrumb', $c('Breadcrumb')], 'pagination' => ['Pagination', $c('Pagination')],
            ]],
        ];

        $out = [];
        foreach ($cats as $slug => [$label, $icon, $members]) {
            $out[$slug] = ['label' => $label, 'icon' => $icon, 'members' => $members, 'part' => '2 · Small Components',
                'classes' => array_values(array_unique(array_merge(...array_column($members, 1)))), 'mockup' => 'UIUX_components_part1.html'];
        }

        return $out;
    }

    public static function feedbackCategories(): array
    {
        $c = fn (...$cls) => array_map(fn ($x) => 'Devlib\\Components\\'.$x, $cls);
        $cats = [
            'p3-feedback' => ['Toast · Confirm · Modal · Drawer', 'fa-solid fa-comment-dots', [
                'toast' => ['Toast', []], 'confirm' => ['Confirm modal', $c('Button', 'IconButton')], 'modal-drawer' => ['Modal · Drawer', $c('Modal', 'Drawer')],
            ]],
            'p3-loading' => ['Loading · Empty', 'fa-solid fa-spinner', [
                'skeleton' => ['Skeleton', $c('Skeleton')], 'spinner' => ['Spinner', $c('Spinner')], 'empty-state' => ['Empty State', $c('EmptyState')],
            ]],
            'p3-notifications' => ['Notifications', 'fa-solid fa-bell', ['notification-list' => ['Notification List', $c('NotificationList')]], 'mockup_notification.jpg'],
            'p3-errors' => ['Error Pages', 'fa-solid fa-triangle-exclamation', ['error-pages' => ['403 · 404 · 419 · 500 · 503 · Offline', $c('ErrorPage')]]],
        ];
        $out = [];
        foreach ($cats as $slug => $cat) {
            [$label, $icon, $members] = $cat;
            $out[$slug] = ['label' => $label, 'icon' => $icon, 'members' => $members, 'part' => '3 · Feedback & States',
                'classes' => array_values(array_unique(array_merge([], ...array_column($members, 1)))), 'mockup' => $cat[3] ?? null];
        }

        return $out;
    }

    public static function behaviorCategories(): array
    {
        $c = fn (...$cls) => array_map(fn ($x) => 'Devlib\\Components\\'.$x, $cls);
        $cats = [
            'p4-developer' => ['Developer Mode', 'fa-solid fa-bug', ['dev-mode' => ['Developer Mode', $c('DevToggle')]]],
            'p4-guard' => ['Dirty Guard', 'fa-solid fa-shield-halved', ['dirty-guard' => ['Dirty Guard', $c('Form', 'BackButton')]]],
            'p4-shortcuts' => ['Keyboard Shortcuts', 'fa-solid fa-keyboard', ['shortcuts' => ['Shortcuts', $c('Button')]]],
            'p4-permissions' => ['Permissions', 'fa-solid fa-user-lock', ['permissions' => ['can="..." di semua komponen', $c('DevlibComponent')]]],
            'p4-i18n' => ['Multi-language', 'fa-solid fa-language', ['i18n' => ['ID / EN', $c('LangSwitch', 'LangText')]]],
            'p4-metadata' => ['Metadata', 'fa-solid fa-circle-info', ['metadata' => ['Dibuat / diubah oleh', $c('Meta')]]],
        ];
        $out = [];
        foreach ($cats as $slug => [$label, $icon, $members]) {
            $out[$slug] = ['label' => $label, 'icon' => $icon, 'members' => $members, 'part' => '4 · Global Behaviors',
                'classes' => array_values(array_unique(array_merge([], ...array_column($members, 1)))), 'mockup' => null];
        }

        return $out;
    }

    public static function page(string $slug): ?array
    {
        foreach (self::groups() as $g) {
            if (isset($g['pages'][$slug])) {
                return $g['pages'][$slug] + ['slug' => $slug, 'full' => false];
            }
        }

        return null;
    }

    // Bentuk siap-pakai untuk <x-dl::shell :modules>.
    public static function modules(): array
    {
        $all = self::files();
        $built = self::builtMockups();
        $modules = [];

        foreach (self::groups() as $g) {
            $children = [];
            if (! empty($g['pages'])) {
                $items = [];
                foreach ($g['pages'] as $slug => $p) {
                    $items[] = ['code' => 'P-'.$slug, 'label' => $p['label'], 'icon' => $p['icon'], 'url' => route('lib', ['slug' => $slug]),
                        'type' => 'form', 'badge' => 'Review', 'badgeTone' => 'partial'];
                }
                $children[] = ['label' => 'Komponen', 'items' => $items];
            }
            foreach ($g['files'] as $caption => $files) {
                $pending = [];
                foreach ($files as $f) {
                    if (isset($all[$f])) {
                        if (! in_array($f, $built, true)) {
                            $pending[] = self::mockupItem($all[$f]);
                        }
                        unset($all[$f]);
                    }
                }
                if ($pending) {
                    $children[] = ['label' => $caption.' (belum dibangun)', 'items' => $pending];
                }
            }
            $modules[] = ['code' => $g['code'], 'short' => $g['short'] ?? null, 'label' => $g['label'], 'icon' => $g['icon'], 'children' => $children];
        }

        if ($all) {
            $modules[] = ['code' => 'ETC', 'short' => 'ETC', 'label' => 'Lainnya', 'icon' => 'fa-solid fa-folder-open',
                'children' => [['label' => 'Mockup belum dibangun', 'items' => array_map(self::mockupItem(...), array_values($all))]]];
        }

        return $modules;
    }

    public static function find(string $file): ?array
    {
        return self::files()[$file] ?? null;
    }

    // Path absolut di dalam folder mockup, null bila di luar (anti traversal).
    public static function path(string $file): ?string
    {
        $real = realpath(config('devlib.mockup_path').DIRECTORY_SEPARATOR.$file);
        $root = realpath(config('devlib.mockup_path'));

        return $real && $root && is_file($real) && str_starts_with($real, $root.DIRECTORY_SEPARATOR) ? $real : null;
    }

    private static function mockupItem(array $m): array
    {
        return [
            'code' => 'M'.substr(md5($m['file']), 0, 10),
            'label' => $m['title'],
            'url' => route('mockup', ['file' => $m['file']]),
            'type' => 'report',
            'icon' => $m['type'] === 'html' ? 'fa-solid fa-file-code' : 'fa-solid fa-image',
            'badge' => 'Belum',
        ];
    }

    /** @return array<string, array{file: string, title: string, type: string}> */
    private static function files(): array
    {
        $items = [];
        $root = config('devlib.mockup_path');
        if (! is_dir($root)) {
            return $items;
        }

        foreach ((new Finder)->files()->in($root)->name(['*.html', '*.jpg', '*.jpeg', '*.png'])->sortByName() as $f) {
            $rel = str_replace('\\', '/', $f->getRelativePathname());
            // Gambar yang punya pasangan .html bernama sama = lampiran, bukan item sendiri.
            if (strtolower($f->getExtension()) !== 'html' && is_file(preg_replace('/\\.(jpe?g|png)$/i', '.html', $f->getPathname()))) {
                continue;
            }
            $base = preg_replace('/^(mockup_|MODULE_)/', '', pathinfo($f->getFilename(), PATHINFO_FILENAME));
            $items[$rel] = [
                'file' => $rel,
                'title' => Str::of($base)->replace(['_', '-'], ' ')->title()->toString(),
                'type' => strtolower($f->getExtension()) === 'html' ? 'html' : 'image',
            ];
        }

        return $items;
    }
}
