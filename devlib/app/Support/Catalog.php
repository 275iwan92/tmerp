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
        'text' => ['Teks', 'Text'], 'password' => ['Password', 'Password'], 'select' => ['Pilihan + cari', 'Select'],
        'color' => ['Warna', 'Color'], 'radio' => ['Radio', 'Radio'], 'toggle' => ['Saklar', 'Toggle'],
        'checkboxes' => ['Checkbox group', 'Checkboxes'], 'slider' => ['Slider', 'Slider'], 'date-range' => ['Rentang tanggal', 'DateRange'],
        'multi-select' => ['Dropdown centang', 'MultiSelect'], 'otp' => ['OTP', 'Otp'], 'rating' => ['Rating bintang', 'Rating'],
        'masked' => ['Mask (NIK)', 'Masked'], 'phone' => ['No. HP', 'Phone'], 'money' => ['Uang', 'Money'], 'date' => ['Tanggal', 'Date'],
        'time' => ['Jam', 'Time'], 'decimal' => ['Desimal', 'Decimal'], 'number' => ['Angka spin', 'Number'], 'upload' => ['Upload', 'Upload'],
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
            ['code' => 'P1', 'label' => '1 · Basic & Themes', 'icon' => 'fa-solid fa-palette',
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
            ['code' => 'P2', 'label' => '2 · Small Components', 'icon' => 'fa-solid fa-shapes',
                'pages' => $fieldPages + [
                    'field-lookup' => ['label' => 'Lookup (pilih dari master)', 'icon' => 'fa-solid fa-magnifying-glass', 'classes' => ['Devlib\\Components\\Field\\Lookup'], 'mockup' => 'UIUX_components_part1.html'],
                    'input-theme' => ['label' => 'Input · Themes', 'icon' => 'fa-solid fa-swatchbook', 'classes' => ['Devlib\\Components\\Field\\Text', 'Devlib\\Components\\Field\\Select'], 'mockup' => 'bukti_komponen_input_tema.html'],
                    'badge' => ['label' => 'Badge (status dokumen)', 'icon' => 'fa-solid fa-tag', 'classes' => ['Devlib\\Components\\Badge']],
                    'tooltip' => ['label' => 'Tooltip', 'icon' => 'fa-solid fa-comment', 'classes' => ['Devlib\\Components\\Tooltip']],
                    'tabs-accordion' => ['label' => 'Tabs · Accordion', 'icon' => 'fa-solid fa-folder-open', 'classes' => ['Devlib\\Components\\Tabs', 'Devlib\\Components\\Accordion']],
                    'pagination' => ['label' => 'Pagination', 'icon' => 'fa-solid fa-ellipsis', 'classes' => ['Devlib\\Components\\Pagination']],
                    'avatar-progress' => ['label' => 'Avatar · Progress', 'icon' => 'fa-solid fa-user', 'classes' => ['Devlib\\Components\\Avatar', 'Devlib\\Components\\Progress']],
                    'stat-box' => ['label' => 'Stat Box (filter)', 'icon' => 'fa-solid fa-chart-simple', 'classes' => ['Devlib\\Components\\StatBox']],
                    'breadcrumb' => ['label' => 'Breadcrumb', 'icon' => 'fa-solid fa-signs-post', 'classes' => ['Devlib\\Components\\Breadcrumb']],
                    'button' => ['label' => 'Button', 'icon' => 'fa-solid fa-hand-pointer', 'classes' => ['Devlib\\Components\\Button', 'Devlib\\Components\\SplitButton'], 'mockup' => 'UIUX_components_part1.html'],
                    'misc' => ['label' => 'Alert · Divider · Brand · Language', 'icon' => 'fa-solid fa-shapes', 'classes' => ['Devlib\\Components\\Alert', 'Devlib\\Components\\Divider', 'Devlib\\Components\\Brand', 'Devlib\\Components\\LangSwitch'], 'mockup' => 'login.html'],
                ],
                'files' => ['Mockup' => ['bukti_komponen_input_tema.html', 'penjelasan_standar_input_teks.html']]],
            ['code' => 'P3', 'label' => '3 · Feedback & States', 'icon' => 'fa-solid fa-bell', 'files' => ['Mockup' => ['mockup_notification.jpg']]],
            ['code' => 'P4', 'label' => '4 · Global Behaviors', 'icon' => 'fa-solid fa-gears', 'files' => []],
            ['code' => 'P5', 'label' => '5 · Big Components', 'icon' => 'fa-solid fa-table-list',
                'pages' => ['form' => ['label' => 'Form', 'icon' => 'fa-solid fa-pen-to-square', 'full' => true,
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
            ['code' => 'P6', 'label' => '6 · Special Components', 'icon' => 'fa-solid fa-draw-polygon',
                'files' => ['Mockup' => ['inquiry_new.html', 'inquiry_revise_v2.html', 'inquiry_recalc_v2.html', 'mockup_inquiry_imposition_grid_form.html', 'mockup_prodev_imposition.html',
                    'tata layout layar imposistion.jpg', 'Organization-Structure-Mockup.html', 'mockup_ID_card.png', 'mockup_mastercard.jpg', 'mockup_mastercard_ver2.jpg']]],
            ['code' => 'P7', 'label' => '7 · Menu & Dashboard', 'icon' => 'fa-solid fa-table-columns',
                'pages' => ['shell' => ['label' => 'Shell (menu utama)', 'icon' => 'fa-solid fa-table-columns', 'full' => true, 'classes' => ['Devlib\\Components\\Shell'], 'mockup' => 'main_menu_v2.html']],
                'files' => ['Mockup' => ['main_menu_v2.html']]],
            ['code' => 'P8', 'label' => '8 · Login & Account', 'icon' => 'fa-solid fa-right-to-bracket',
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
        return ['login.html', 'main_menu_v2.html', 'UIUX_components_part1.html', 'mockup_dashboardcolor.html', 'bukti_komponen_tema_navy.html',
            'bukti_komponen_tema_emerald.html', 'bukti_komponen_tema_cerah_red.html', 'bukti_komponen_input_tema.html', 'penjelasan_standar_input_teks.html'];
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
            $modules[] = ['code' => $g['code'], 'label' => $g['label'], 'icon' => $g['icon'], 'children' => $children];
        }

        if ($all) {
            $modules[] = ['code' => 'ETC', 'label' => 'Lainnya', 'icon' => 'fa-solid fa-folder-open',
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
