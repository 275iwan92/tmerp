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
        'p5-grid' => '2026-09-26',        // Grid for Pre-Data Entry
        'p5-grid-report' => '2026-09-26', // Grid for Reporting (tombol Filter -> pop-up)
        'p5-grid-special' => '2026-09-26', // Grid Special (spreadsheet)
        // Menu Form & Dokumen disetujui user 2026-09-26
        'p5-form-single' => '2026-09-26',
        'p5-form-md' => '2026-09-26',
        'p5-form-special' => '2026-09-26',
        'form' => '2026-09-26',
        'p5-master' => '2026-09-26',
        'p5-master-detail' => '2026-09-26',
        'p5-approval' => '2026-09-26',
        'p5-wizard' => '2026-09-26',
        'p5-print' => '2026-09-26',
        'p5-report' => '2026-09-26',
        'p5-import' => '2026-09-26',
        // Basics & Themes + Menus, Accounts & Layout disetujui user 2026-09-26
        'basic-colors' => '2026-09-26',
        'basic-typography' => '2026-09-26',
        'basic-shape' => '2026-09-26',
        'basic-themes' => '2026-09-26',
        'basic-density' => '2026-09-26',
        'basic-icons' => '2026-09-26',
        'basic-breakpoints' => '2026-09-26',
        'basic-locale' => '2026-09-26',
        'p3-feedback' => '2026-09-26',
        'p3-loading' => '2026-09-26',
        'p3-notifications' => '2026-09-26',
        'p3-errors' => '2026-09-26',
        'p4-developer' => '2026-09-26',
        'p4-guard' => '2026-09-26',
        'p4-shortcuts' => '2026-09-26',
        'p4-permissions' => '2026-09-26',
        'p4-i18n' => '2026-09-26',
        'p4-metadata' => '2026-09-26',
        'p7-menus' => '2026-09-26',
        'p7-chat' => '2026-09-26',
        'login-1' => '2026-09-26',
        'login-2' => '2026-09-26',
        'login-3' => '2026-09-26',
        'register' => '2026-09-26',
        'forgot-1' => '2026-09-26',
        'forgot-2' => '2026-09-26',
        'p8-lock' => '2026-09-26',
        'p8-security' => '2026-09-26',
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
            ['code' => 'DT', 'short' => 'DT', 'label' => 'Basics & Themes', 'icon' => 'fa-solid fa-palette',
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
            ['code' => 'IN', 'short' => 'IN', 'label' => 'Inputs & Buttons', 'icon' => 'fa-solid fa-i-cursor', 'pages' => self::smallCategories()],
            // Grid, Form, Dokumen = 3 menu terpisah (user 2026-09-26) supaya breadcrumb menunjukkan keluarganya.
            ['code' => 'GR', 'short' => 'GR', 'label' => 'Grid', 'icon' => 'fa-solid fa-table',
                'pages' => [
                    // Tiga keluarga grid (user 2026-09-26): Pre-Data Entry, Reporting, Special (spreadsheet).
                    // Dua keluarga pertama: Pre-Data Entry = pintu ke form (baris lega, centang, kotak Aksi,
                    // + New Record); Reporting = baca data ribuan baris (rapat, tanpa Aksi, fokus cari + filter). x-dl::grid mode=entry|report.
                    'p5-grid' => $page('Grid for Pre-Data Entry (6 variants)', 'fa-solid fa-table', [
                        'grid-master' => ['1 · Grid for Master (photo box + Edit + Active/Inactive)', $c('Grid')],
                        'grid-worklist' => ['2 · Grid for Single Transaction (worklist)', $c('Grid')],
                        'grid-master-detail' => ['3 · Grid for Master-Detail (expand row = items)', $c('Grid')],
                        'grid-status' => ['4 · Grid with Status Bar (stages + progress)', $c('Grid')],
                        'grid-photos' => ['5 · Grid with Photos (table ↔ cards)', $c('Grid')],
                        'grid-mix' => ['6 · Grid with Mix Component', $c('Grid')],
                    ], 'grid_kerapatan_baris.html'),
                    'p5-grid-report' => $page('Grid for Reporting (4 variants)', 'fa-solid fa-table-list', [
                        'grid-report' => ['1 · Grid Report List (compact, thousands of rows, Filter pop-up)', $c('Grid')],
                        'grid-group' => ['2 · Grid Group By (+ subtotal)', $c('Grid')],
                        'grid-drilldown' => ['3 · Grid Summary → Details (drill-down)', $c('Drilldown', 'Grid')],
                        'grid-pivot' => ['4 · Grid Pivot (rows × columns × values)', $c('Pivot')],
                    ]),
                    // Grid Special (user 2026-09-26): grid yang selnya langsung diedit, bukan pintu ke form maupun laporan.
                    'p5-grid-special' => $page('Grid Special (1 variant)', 'fa-solid fa-table-cells', [
                        'grid-sheet' => ['1 · Grid Spreadsheet (inline edit, Excel copy/paste, drag-fill)', $c('Sheet')],
                    ]),
                ]],
            // Tiga keluarga form (user 2026-09-26): Single, Master-Detail, Special.
            ['code' => 'FM', 'short' => 'FM', 'label' => 'Form', 'icon' => 'fa-solid fa-pen-to-square',
                'pages' => [
                    'p5-form-single' => $page('Single Form (4 variants)', 'fa-solid fa-pen-to-square', [
                        'form' => ['1 · Form Standard (all field types)', $c('Form')],
                        'form-tabs' => ['2 · Form with Tabs', $c('Form', 'Tabs')],
                        'form-view' => ['3 · Form Read-only (view mode + Edit)', $c('Form')],
                        'form-popup' => ['4 · Form Pop-up (modal & drawer)', $c('Modal', 'Drawer', 'Form')],
                    ], 'UIUX_components_part1.html'),
                    'p5-form-md' => $page('Form Master-Detail (2 variants)', 'fa-solid fa-file-invoice', [
                        'form-md' => ['1 · Form Master-Detail (header + item lines)', $c('Form', 'Lines')],
                        'form-approval' => ['2 · Form with Approval', $c('Form', 'ApprovalBar')],
                    ], 'UIUX_components_part1.html'),
                    'p5-form-special' => $page('Form Special (3 variants)', 'fa-solid fa-wand-magic-sparkles', [
                        'wizard-customer' => ['1 · Form Wizard (step by step)', $c('Wizard')],
                        'form-filter' => ['2 · Form Filter / Report Criteria', $c('FilterForm')],
                        'form-quickadd' => ['3 · Form Quick-add inside Grid', $c('Grid')],
                    ], 'UIUX_components_part1.html'),
                    'form' => ['label' => 'Form · all fields (full screen)', 'icon' => 'fa-solid fa-pen-to-square', 'full' => true,
                        'classes' => $c('Form', 'Form\Col', 'Form\Row', 'DeviceFrame'), 'mockup' => 'UIUX_components_part1.html'],
                ]],
            ['code' => 'DK', 'short' => 'DK', 'label' => 'Documents', 'icon' => 'fa-solid fa-file-lines',
                'pages' => [
                    'p5-master' => $page('Master', 'fa-solid fa-address-book', ['master-employee' => ['Employee Master (grid + edit form)', $c('Master', 'Grid')]], 'mockup_master_customer.html'),
                    'p5-master-detail' => $page('Master-Detail', 'fa-solid fa-file-invoice', ['master-detail-po' => ['Purchase Order (header + item lines)', $c('Master', 'Lines', 'Grid')]], 'mockup_purchase_requisition.html'),
                    'p5-approval' => $page('Approval', 'fa-solid fa-stamp', ['approval-po' => ['Approval Center PO', $c('Approval')]], 'mockup_po_approval_center.html'),
                    'p5-wizard' => $page('Wizard', 'fa-solid fa-shoe-prints', ['wizard-customer' => ['Customer Registration (3 steps + Review)', $c('Wizard', 'Wizard\\Step')]]),
                    'p5-print' => $page('Print Out', 'fa-solid fa-print', ['print-co' => ['Customer Order Note (prices, VAT, amount in words, multi-page)', $c('PrintOut')], 'print-spb' => ['Goods Receipt Note (multi-level header, no prices)', $c('PrintOut')]], 'nota_co_mockup.html', [...$c('PrintOut'), 'Devlib\\Terbilang']),
                    'p5-report' => $page('Report', 'fa-solid fa-file-lines', ['report-stock-card' => ['Stock Card (running balance, per month)', $c('Report')], 'report-master-stock' => ['Stock Master (KPI, groups + subtotal, highlight < MIN)', $c('Report')]], 'mockup_print_master_stock.html'),
                    'p5-import' => $page('Import / Export', 'fa-solid fa-file-import', ['import-items' => ['Import CSV (column mapping, validation, failed rows)', $c('Import')], 'grid-master' => ['Export CSV from Grid (visible columns)', $c('Grid')]]),
                    'p5-override' => $page('Override Pattern (OO)', 'fa-solid fa-code-branch', ['oo-override' => ['Kanban subclass: properties overridden in PHP, methods overridden in JS (super)', ['App\\View\\Components\\Demo\\KanbanProduksi', ...$c('Kanban')]]], null, $c('Kanban')),
                ]],
            ['code' => 'PJ', 'short' => 'PJ', 'label' => 'Boards, Schedules & Charts', 'icon' => 'fa-solid fa-chart-pie',
                'pages' => [
                    'p5-kanban' => $page('Kanban', 'fa-solid fa-table-columns', ['kanban-production' => ['Production Board (drag, WIP, move rules, card detail)', $c('Kanban')]], 'mockup_UIUX_kanban.html'),
                    'p5-gantt' => $page('Gantt', 'fa-solid fa-bars-staggered', ['gantt-scheduling' => ['Scheduling Board (resource × hours)', $c('Gantt')]], 'mockup_scheduling_board.html'),
                    'p5-calendar' => $page('Calendar', 'fa-solid fa-calendar-days', ['calendar-events' => ['Event Calendar (day / week / month)', $c('Calendar')]], 'mockup_UIUX_calendar.html'),
                    'p5-org' => $page('Org Chart', 'fa-solid fa-sitemap', ['org-chart' => ['Organization Structure (drag to change superior, vacancies, multiple holders)', $c('OrgChart')]], 'Organization-Structure-Mockup.html'),
                    'p6-charts' => $page('Charts', 'fa-solid fa-chart-pie', ['chart-bars' => ['Bars: bar, stacked, horizontal, combo', $c('Chart')], 'chart-lines' => ['Line, area, radar, heatmap', $c('Chart')], 'chart-pies' => ['Pie, donut, funnel', $c('Chart')]], 'mockup_dashboardcolor.html'),
                    'p6-indicators' => $page('Dashboard Indicators', 'fa-solid fa-gauge-high', ['indicators' => ['KPI, gauge, progress ring, bullet, traffic light', $c('Kpi', 'Gauge', 'ProgressRing', 'Bullet', 'TrafficLight')]]),
                    'p6-codes' => $page('QR & Barcode', 'fa-solid fa-qrcode', ['codes' => ['QR & Barcode (Code128, EAN-13)', $c('Qr', 'Barcode')]]),
                ]],
            ['code' => 'MA', 'short' => 'MA', 'label' => 'Menus, Accounts & Layout', 'icon' => 'fa-solid fa-table-columns',
                'pages' => [
                    'shell' => ['label' => 'Shell (main menu)', 'icon' => 'fa-solid fa-table-columns', 'full' => true, 'classes' => $c('Shell', 'LayerShell', 'LayerMenu'), 'mockup' => 'main_menu_v2.html'],
                    'p7-menus' => $page('Menu (Ribbon · Mega · Side Panel)', 'fa-solid fa-bars-staggered', [
                        'ribbon' => ['Excel-style Ribbon (tabs, groups, multi icons, submenu, split, toggle, KeyTips)', $c('Ribbon')],
                        'mega-menu' => ['ScriptCase-style Mega Menu (color bar + 4-column icon panel)', $c('MegaMenu')],
                        'side-menu' => ['Left & right Side Panel (nested accordion, icon rail + flyout, favorites, recently opened)', $c('SideMenu')],
                    ], 'main_menu_v2.html'),
                    'p7-layer' => $page('Layer Menu', 'fa-solid fa-layer-group', ['layer-menu' => ['Layer Menu: layered left panel (module › menu › sub › sub-sub) + gear: Chat (swap / dock right) & Profile sliding up. Also used in Shell: avatar menu › Layer Menu', $c('LayerMenu', 'Chat')]]),
                    'p7-chat' => $page('Chat (WhatsApp style)', 'fa-solid fa-comment-dots', ['chat' => ['Chat: online users, + for offline users, profile photo & send photo', $c('Chat')]], 'main_menu_v2.html'),
                    'login-1' => ['label' => 'Login 1 · same as tmerp', 'icon' => 'fa-solid fa-right-to-bracket', 'full' => true, 'classes' => self::LOGIN_CLASSES, 'mockup' => 'login.html'],
                    'login-2' => ['label' => 'Login 2 · glass pop-up', 'icon' => 'fa-solid fa-right-to-bracket', 'full' => true, 'classes' => self::LOGIN_CLASSES, 'mockup' => 'login.html'],
                    'login-3' => ['label' => 'Login 3 · navy left panel', 'icon' => 'fa-solid fa-right-to-bracket', 'full' => true, 'classes' => self::LOGIN_CLASSES, 'mockup' => 'login.html'],
                    'register' => ['label' => 'Register Account', 'icon' => 'fa-solid fa-user-plus', 'full' => true, 'classes' => $c('RegisterBox', 'Frame'), 'mockup' => 'register.html'],
                    'forgot-1' => ['label' => 'Forgot Password · email', 'icon' => 'fa-solid fa-key', 'full' => true, 'classes' => $c('ForgotBox', 'Frame'), 'mockup' => 'forgot-password.html'],
                    'forgot-2' => ['label' => 'Forgot Password · 4 questions', 'icon' => 'fa-solid fa-key', 'full' => true, 'classes' => $c('ForgotBox', 'Frame'), 'mockup' => 'forgot-password.html'],
                    'p8-lock' => $page('Lock Screen', 'fa-solid fa-lock', ['lock-screen' => ['Lock screen (idle / manual / Ctrl+Shift+L)', $c('LockScreen')]]),
                    'p8-security' => $page('Setup & Security', 'fa-solid fa-user-shield', ['security' => ['Users, Groups, Objects, Group Permissions, Language, Theme (vertical tabs)', $c('Tabs', 'Grid', 'PermissionMatrix', 'ThemePicker')]], 'security.html', $c('PermissionMatrix', 'Tabs')),
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
                'field-masked' => ['Masked (NIK)', $f('Masked')], 'field-phone' => ['Phone (mobile & office, per country)', $f('Phone')], 'field-credit-card' => ['Credit Card', $f('CreditCard')], 'field-signature' => ['Signature', $f('Signature')], 'field-tags' => ['Tags', $f('Tags')],
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
                'field-money' => ['Money', $f('Money')], 'field-number-words' => ['Number + Amount in Words', $f('NumberWords')], 'field-decimal' => ['Decimal', $f('Decimal')], 'field-number' => ['Number (spinner)', $f('Number')],
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
            'p4-permissions' => ['Permissions', 'fa-solid fa-user-lock', ['permissions' => ['can="..." on every component', $c('DevlibComponent')]]],
            'p4-i18n' => ['Multi-language', 'fa-solid fa-language', ['i18n' => ['ID / EN', $c('LangSwitch', 'LangText')]]],
            'p4-metadata' => ['Metadata', 'fa-solid fa-circle-info', ['metadata' => ['Created / updated by', $c('Meta')]]],
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
            $modules[] = ['code' => $g['code'], 'short' => $g['short'], 'label' => $g['label'], 'icon' => $g['icon'], 'children' => [['label' => 'Components', 'items' => $items]]];
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
