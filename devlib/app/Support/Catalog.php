<?php

namespace App\Support;

use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

// Menu situs DevLib: 5 grup komponen library (demo = kode pemakaian yang tampil di ikon </>).
// status komponen: review = menunggu penilaian user; hanya user yang boleh menaikkan ke approved.
final class Catalog
{
    // Halaman yang sudah DISETUJUI user (slug => tanggal). Hanya ditambah atas persetujuan eksplisit user.
    public const APPROVED = [
        'p7-layer' => '2026-09-24',   // Layer Menu
        'shell' => '2026-09-24',      // menu utama = Layer Shell (tanpa top bar)
    ];

    public const LOGIN_CLASSES = ['Devlib\Components\Frame', 'Devlib\Components\LoginBox', 'Devlib\Components\Clock', 'Devlib\Components\Quote',
        'Devlib\Components\Greeting', 'Devlib\Components\Weather', 'Devlib\Components\Announce', 'Devlib\Components\UatAccounts'];

    public static function groups(): array
    {
        $basic = fn ($label, $icon, $classes = [], $mockup = 'UIUX_components_part1.html') => ['label' => $label, 'icon' => $icon, 'classes' => $classes, 'mockup' => $mockup];
        $c = fn (...$cls) => array_map(fn ($x) => 'Devlib\\Components\\'.$x, $cls);
        $page = fn ($label, $icon, $members, $mockup = null, $classes = null) => ['label' => $label, 'icon' => $icon, 'mockup' => $mockup, 'members' => $members,
            'classes' => $classes ?? array_values(array_unique(array_merge([], ...array_column($members, 1))))];

        // DevLib = komponen dasar & layar standar saja (keputusan user 2026-09-24). Layar khusus -> mockup.test (packages/mockup-parts).
        $groups = [
            ['code' => 'DT', 'short' => 'DT', 'label' => 'Dasar & Tema', 'icon' => 'fa-solid fa-palette',
                'pages' => [
                    'basic-colors' => $basic('Colors', 'fa-solid fa-droplet', $c('TokenTable'), 'mockup_dashboardcolor.html'),
                    'basic-typography' => $basic('Typography', 'fa-solid fa-font', $c('TokenTable')),
                    'basic-shape' => $basic('Spacing · Radius · Shadow', 'fa-solid fa-vector-square', $c('TokenTable')),
                    'basic-themes' => $basic('Themes', 'fa-solid fa-swatchbook', $c('ThemePicker', 'ThemeBoot'), 'bukti_komponen_tema_navy.html'),
                    'basic-density' => $basic('Density', 'fa-solid fa-table-cells', $c('ThemePicker'), 'grid_kerapatan_baris.html'),
                    'basic-icons' => $basic('Icons', 'fa-solid fa-icons', $c('Icon'), 'pilihan_ikon_aksi_grid.html'),
                    'basic-breakpoints' => $basic('Breakpoints', 'fa-solid fa-mobile-screen', $c('TokenTable')),
                    'basic-locale' => $basic('Locale & Formats', 'fa-solid fa-earth-asia'),
                ] + self::feedbackCategories() + self::behaviorCategories()],
            ['code' => 'IN', 'short' => 'IN', 'label' => 'Input & Tombol', 'icon' => 'fa-solid fa-i-cursor', 'pages' => self::smallCategories()],
            ['code' => 'GF', 'short' => 'GF', 'label' => 'Grid, Form & Dokumen', 'icon' => 'fa-solid fa-table-list',
                'pages' => [
                    // Dua keluarga grid (user 2026-09-26): Pre-Data Entry = pintu ke form (baris lega, centang, kotak Aksi,
                    // + New Record); Reporting = baca data ribuan baris (rapat, tanpa Aksi, fokus cari + filter). x-dl::grid mode=entry|report.
                    'p5-grid' => $page('Grid for Pre-Data Entry (7 varian)', 'fa-solid fa-table', [
                        'grid-master' => ['1 · Grid for Master (kotak Preview + Edit + Aktif/Nonaktif)', $c('Grid')],
                        'grid-worklist' => ['2 · Grid for Single Transaction (worklist)', $c('Grid')],
                        'grid-master-detail' => ['3 · Grid for Master-Detail (baris dibuka = item)', $c('Grid')],
                        'grid-status' => ['4 · Grid with Status Bar (tahapan + progres)', $c('Grid')],
                        'grid-photos' => ['5 · Grid with Photos (tabel ↔ kartu)', $c('Grid')],
                        'grid-mix' => ['6 · Grid with Mix Component', $c('Grid')],
                        'grid-sheet' => ['7 · Grid Spreadsheet (edit langsung, Excel copy/paste, drag-fill)', $c('Sheet')],
                    ], 'grid_kerapatan_baris.html'),
                    'p5-grid-report' => $page('Grid for Reporting (4 varian)', 'fa-solid fa-table-list', [
                        'grid-report' => ['1 · Grid Report List (rapat, ribuan baris, saring per kolom)', $c('Grid')],
                        'grid-group' => ['2 · Grid Group By (+ subtotal)', $c('Grid')],
                        'grid-drilldown' => ['3 · Grid Summary → Details (drill-down)', $c('Drilldown', 'Grid')],
                        'grid-pivot' => ['4 · Grid Pivot (baris × kolom × nilai)', $c('Pivot')],
                    ]),
                    'p5-forms' => $page('Form (9 varian)', 'fa-solid fa-pen-to-square', [
                        'form' => ['1 · Form Standard (semua jenis field)', $c('Form')],
                        'form-tabs' => ['2 · Form with Tabs (tabular)', $c('Form', 'Tabs')],
                        'form-md' => ['3 · Form Master-Detail (header + baris item)', $c('Form', 'Lines')],
                        'form-approval' => ['4 · Form with Approval', $c('Form', 'ApprovalBar')],
                        'form-view' => ['5 · Form Read-only (mode lihat + Edit)', $c('Form')],
                        'form-popup' => ['6 · Form Pop-up (modal & drawer)', $c('Modal', 'Drawer', 'Form')],
                        'wizard-customer' => ['7 · Form Wizard (bertahap)', $c('Wizard')],
                        'form-filter' => ['8 · Form Filter / Kriteria Laporan', $c('FilterForm')],
                        'form-quickadd' => ['9 · Form Quick-add di dalam Grid', $c('Grid')],
                    ], 'UIUX_components_part1.html'),
                    'form' => ['label' => 'Form · semua field (layar penuh)', 'icon' => 'fa-solid fa-pen-to-square', 'full' => true,
                        'classes' => $c('Form', 'Form\\Col', 'Form\\Row', 'DeviceFrame'), 'mockup' => 'UIUX_components_part1.html'],
                    'p5-master' => $page('Master', 'fa-solid fa-address-book', ['master-employee' => ['Master Pegawai (grid + form edit)', $c('Master', 'Grid')]], 'mockup_master_customer.html'),
                    'p5-master-detail' => $page('Master-Detail', 'fa-solid fa-file-invoice', ['master-detail-po' => ['Purchase Order (header + baris item)', $c('Master', 'Lines', 'Grid')]], 'mockup_purchase_requisition.html'),
                    'p5-approval' => $page('Approval', 'fa-solid fa-stamp', ['approval-po' => ['Approval Center PO', $c('Approval')]], 'mockup_po_approval_center.html'),
                    'p5-wizard' => $page('Wizard', 'fa-solid fa-shoe-prints', ['wizard-customer' => ['Registrasi Customer (3 langkah + Review)', $c('Wizard', 'Wizard\\Step')]]),
                    'p5-print' => $page('Print Out', 'fa-solid fa-print', ['print-co' => ['Nota Customer Order (harga, PPN, terbilang, multi-halaman)', $c('PrintOut')], 'print-spb' => ['Surat Penerimaan Barang (header bertingkat, tanpa harga)', $c('PrintOut')]], 'nota_co_mockup.html', [...$c('PrintOut'), 'Devlib\\Terbilang']),
                    'p5-report' => $page('Report', 'fa-solid fa-file-lines', ['report-stock-card' => ['Kartu Stok (saldo berjalan, per bulan)', $c('Report')], 'report-master-stock' => ['Master Stok (KPI, kelompok + subtotal, sorot < MIN)', $c('Report')]], 'mockup_print_master_stock.html'),
                    'p5-import' => $page('Import / Export', 'fa-solid fa-file-import', ['import-items' => ['Import CSV (cocokkan kolom, validasi, baris gagal)', $c('Import')], 'grid-master' => ['Export CSV dari Grid (kolom yang tampil)', $c('Grid')]]),
                    'p5-override' => $page('Pola Override (OO)', 'fa-solid fa-code-branch', ['oo-override' => ['Turunan Kanban: properti ditimpa di PHP, method ditimpa di JS (super)', ['App\\View\\Components\\Demo\\KanbanProduksi', ...$c('Kanban')]]], null, $c('Kanban')),
                ]],
            ['code' => 'PJ', 'short' => 'PJ', 'label' => 'Papan, Jadwal & Grafik', 'icon' => 'fa-solid fa-chart-pie',
                'pages' => [
                    'p5-kanban' => $page('Kanban', 'fa-solid fa-table-columns', ['kanban-production' => ['Papan Produksi (drag, WIP, aturan pindah, detail kartu)', $c('Kanban')]], 'mockup_UIUX_kanban.html'),
                    'p5-gantt' => $page('Gantt', 'fa-solid fa-bars-staggered', ['gantt-scheduling' => ['Scheduling Board (resource x jam)', $c('Gantt')]], 'mockup_scheduling_board.html'),
                    'p5-calendar' => $page('Calendar', 'fa-solid fa-calendar-days', ['calendar-events' => ['Kalender Acara (hari / minggu / bulan)', $c('Calendar')]], 'mockup_UIUX_calendar.html'),
                    'p5-org' => $page('Org Chart', 'fa-solid fa-sitemap', ['org-chart' => ['Struktur Organisasi (drag pindah atasan, lowong, multi pemegang)', $c('OrgChart')]], 'Organization-Structure-Mockup.html'),
                    'p6-charts' => $page('Charts', 'fa-solid fa-chart-pie', ['chart-bars' => ['Batang: bar, stacked, horizontal, combo', $c('Chart')], 'chart-lines' => ['Garis, area, radar, heatmap', $c('Chart')], 'chart-pies' => ['Pie, donut, funnel', $c('Chart')]], 'mockup_dashboardcolor.html'),
                    'p6-indicators' => $page('Dashboard Indicators', 'fa-solid fa-gauge-high', ['indicators' => ['KPI, gauge, progress ring, bullet, traffic light', $c('Kpi', 'Gauge', 'ProgressRing', 'Bullet', 'TrafficLight')]]),
                    'p6-codes' => $page('QR & Barcode', 'fa-solid fa-qrcode', ['codes' => ['QR & Barcode (Code128, EAN-13)', $c('Qr', 'Barcode')]]),
                ]],
            ['code' => 'MA', 'short' => 'MA', 'label' => 'Menu, Akun & Layout', 'icon' => 'fa-solid fa-table-columns',
                'pages' => [
                    'shell' => ['label' => 'Shell (menu utama)', 'icon' => 'fa-solid fa-table-columns', 'full' => true, 'classes' => $c('Shell', 'LayerShell', 'LayerMenu'), 'mockup' => 'main_menu_v2.html'],
                    'p7-menus' => $page('Menu (Ribbon · Mega · Side Panel)', 'fa-solid fa-bars-staggered', [
                        'ribbon' => ['Ribbon ala Microsoft Excel (tab, grup, multi ikon, submenu, split, toggle, KeyTips)', $c('Ribbon')],
                        'mega-menu' => ['Mega Menu ala ScriptCase (bilah warna + panel 4 kolom ber-ikon)', $c('MegaMenu')],
                        'side-menu' => ['Side Panel kiri & kanan (akordeon bertingkat, rail ikon + flyout, favorit, terakhir dibuka)', $c('SideMenu')],
                    ], 'main_menu_v2.html'),
                    'p7-layer' => $page('Layer Menu', 'fa-solid fa-layer-group', ['layer-menu' => ['Layer Menu: panel kiri berlapis (modul › menu › sub › sub-sub) + roda gigi: Chat (tukar / dok kanan) & Profile naik dari bawah. Dipakai juga di Shell: menu avatar › Layer Menu', $c('LayerMenu', 'Chat')]]),
                    'p7-chat' => $page('Chat (gaya WhatsApp)', 'fa-solid fa-comment-dots', ['chat' => ['Chat: yang sedang login, + untuk yang tidak login, foto profil & kirim foto', $c('Chat')]], 'main_menu_v2.html'),
                    'login-1' => ['label' => 'Login 1 · sama dengan tmerp', 'icon' => 'fa-solid fa-right-to-bracket', 'full' => true, 'classes' => self::LOGIN_CLASSES, 'mockup' => 'login.html'],
                    'login-2' => ['label' => 'Login 2 · popup kaca', 'icon' => 'fa-solid fa-right-to-bracket', 'full' => true, 'classes' => self::LOGIN_CLASSES, 'mockup' => 'login.html'],
                    'login-3' => ['label' => 'Login 3 · panel kiri navy', 'icon' => 'fa-solid fa-right-to-bracket', 'full' => true, 'classes' => self::LOGIN_CLASSES, 'mockup' => 'login.html'],
                    'register' => ['label' => 'Daftar Akun', 'icon' => 'fa-solid fa-user-plus', 'full' => true, 'classes' => $c('RegisterBox', 'Frame'), 'mockup' => 'register.html'],
                    'forgot-1' => ['label' => 'Lupa Password · email', 'icon' => 'fa-solid fa-key', 'full' => true, 'classes' => $c('ForgotBox', 'Frame'), 'mockup' => 'forgot-password.html'],
                    'forgot-2' => ['label' => 'Lupa Password · 4 pertanyaan', 'icon' => 'fa-solid fa-key', 'full' => true, 'classes' => $c('ForgotBox', 'Frame'), 'mockup' => 'forgot-password.html'],
                    'p8-lock' => $page('Lock Screen', 'fa-solid fa-lock', ['lock-screen' => ['Layar kunci (idle / manual / Ctrl+Shift+L)', $c('LockScreen')]]),
                    'p8-security' => $page('Setup & Security', 'fa-solid fa-user-shield', ['security' => ['Pengguna, Grup, Objek, Hak Akses Grup, Bahasa, Tema (tab vertikal)', $c('Tabs', 'Grid', 'PermissionMatrix', 'ThemePicker')]], 'security.html', $c('PermissionMatrix', 'Tabs')),
                ]],
        ];
        // Label breadcrumb halaman kategori = nama grupnya.
        foreach ($groups as &$g) {
            foreach ($g['pages'] as &$p) {
                $p['part'] = $g['label'];
            }
        }

        return $groups;
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

    // Bentuk siap-pakai untuk <x-dl::shell :modules>. Menu DevLib = komponen saja; mockup (termasuk yang
    // belum dibangun) pindah ke mockup.test (Fase 2), tetap bisa dibuka dari tautan mockup di ikon </>.
    public static function modules(): array
    {
        $modules = [];
        foreach (self::groups() as $g) {
            $items = [];
            foreach ($g['pages'] as $slug => $p) {
                $items[] = ['code' => 'P-'.$slug, 'label' => $p['label'], 'icon' => $p['icon'], 'url' => route('lib', ['slug' => $slug]),
                    'type' => 'form'] + (isset(self::APPROVED[$slug]) ? ['badge' => 'Approved', 'badgeTone' => 'done'] : ['badge' => 'Review', 'badgeTone' => 'partial']);
            }
            $modules[] = ['code' => $g['code'], 'short' => $g['short'], 'label' => $g['label'], 'icon' => $g['icon'], 'children' => [['label' => 'Komponen', 'items' => $items]]];
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
