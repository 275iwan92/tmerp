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

        return [
            ['code' => 'LOGIN', 'label' => 'Login & Akun', 'icon' => 'fa-solid fa-right-to-bracket',
                'pages' => [
                    'login-1' => ['label' => 'Login 1 · sama dengan tmerp', 'icon' => 'fa-solid fa-right-to-bracket', 'full' => true, 'classes' => self::LOGIN_CLASSES, 'mockup' => 'login.html'],
                    'login-2' => ['label' => 'Login 2 · popup kaca', 'icon' => 'fa-solid fa-right-to-bracket', 'full' => true, 'classes' => self::LOGIN_CLASSES, 'mockup' => 'login.html'],
                    'login-3' => ['label' => 'Login 3 · panel kiri navy', 'icon' => 'fa-solid fa-right-to-bracket', 'full' => true, 'classes' => self::LOGIN_CLASSES, 'mockup' => 'login.html'],
                ],
                'files' => ['login.html', 'register.html', 'forgot-password.html', 'security.html']],
            ['code' => 'SHELL', 'label' => 'Shell & Menu', 'icon' => 'fa-solid fa-table-columns',
                'pages' => ['shell' => ['label' => 'Shell (menu utama)', 'icon' => 'fa-solid fa-table-columns', 'full' => true, 'classes' => ['Devlib\\Components\\Shell'], 'mockup' => 'main_menu_v2.html']],
                'files' => ['main_menu_v2.html', 'mockup_notification.jpg', 'mockup_dashboardcolor.html']],
            ['code' => 'FORM', 'label' => 'Form', 'icon' => 'fa-solid fa-pen-to-square',
                'pages' => ['form' => ['label' => 'Form lengkap', 'icon' => 'fa-solid fa-pen-to-square', 'full' => true,
                    'classes' => ['Devlib\\Components\\Form', 'Devlib\\Components\\Form\\Col', 'Devlib\\Components\\Form\\Row', 'Devlib\\Components\\DeviceFrame'], 'mockup' => 'UIUX_components_part1.html']] + $fieldPages,
                'files' => ['UIUX_components_part1.html', 'penjelasan_standar_input_teks.html', 'penjelasan_standar_input_teks.jpg',
                    'bukti_komponen_input_tema.html', 'bukti_komponen_input_tema.jpg', 'bukti_komponen_tema_navy.html', 'bukti_komponen_tema_emerald.html', 'bukti_komponen_tema_cerah_red.html']],
            ['code' => 'BASE', 'label' => 'Tombol & Dasar', 'icon' => 'fa-solid fa-shapes',
                'pages' => [
                    'button' => ['label' => 'Tombol', 'icon' => 'fa-solid fa-hand-pointer', 'classes' => ['Devlib\\Components\\Button', 'Devlib\\Components\\SplitButton'], 'mockup' => 'UIUX_components_part1.html'],
                    'misc' => ['label' => 'Pesan · Pemisah · Brand · Bahasa', 'icon' => 'fa-solid fa-shapes', 'classes' => ['Devlib\\Components\\Alert', 'Devlib\\Components\\Divider', 'Devlib\\Components\\Brand', 'Devlib\\Components\\LangSwitch'], 'mockup' => 'login.html'],
                ], 'files' => []],
            ['code' => 'GRID', 'label' => 'Grid', 'icon' => 'fa-solid fa-table-list', 'files' => ['mockup_master_employee.html', 'filament_table_demo.html', 'powergrid_demo.html',
                'grid_kerapatan_baris.html', 'grid_kerapatan_baris.jpg', 'pilihan_ikon_aksi_grid.html', 'pilihan_ikon_aksi_grid.jpg',
                'pilihan_ikon_aksi_grid_konteks.html', 'pilihan_ikon_aksi_grid_konteks.jpg', 'mockup_master_uom_v4.html']],
            ['code' => 'MASTER', 'label' => 'Master Data', 'icon' => 'fa-solid fa-database', 'files' => ['mockup_master_customer.html', 'mockup_master_rawmaterial.html', 'mockup_master_warehouse.html',
                'mockup_master_produksi.html', 'master_mesin_update_2026.html', 'master_mesin_update_2026.jpg', 'master_coa_mockup.html', 'mockup_mastercard.jpg', 'mockup_mastercard_ver2.jpg']],
            ['code' => 'DOC', 'label' => 'Dokumen & Approval', 'icon' => 'fa-solid fa-file-signature', 'files' => ['mockup_purchase_requisition.html', 'mockup_pr_approval_center.html', 'mockup_po_approval_center.html',
                'mockup_purchase_quotation.html', 'mockup_purchase_receive.html', 'mockup_purchase_return.html', 'mockup_flow_engine.html',
                'mockup_sales_quotation_entry.html', 'mockup_co_entry.html', 'co_delivery_plan_mockup.html', 'mockup_down_payment.html',
                'mockup_stock_in.html', 'mockup_stock_out.html', 'mockup_stock_return.html', 'mockup_stock_adjustment.html']],
            ['code' => 'BOARD', 'label' => 'Kanban · Kalender · Gantt', 'icon' => 'fa-solid fa-chart-gantt', 'files' => ['mockup_UIUX_kanban.html', 'mockup_UIUX_calendar.html', 'mockup_scheduling_board.html',
                'mockup_roster_shift.html', 'mockup_roster_formasi_harian.html', 'mockup_roster_formasi_harian.jpg', 'real_roster_formasi_editor.jpg', 'real_roster_formasi_ringkas.jpg', 'real_roster_formasi_harian.jpg']],
            ['code' => 'CHART', 'label' => 'Bagan & Kartu', 'icon' => 'fa-solid fa-sitemap', 'files' => ['Organization-Structure-Mockup.html', 'mockup_ID_card.png', 'mockup_personal_data_self_service.html']],
            ['code' => 'PRINT', 'label' => 'Cetak / Nota', 'icon' => 'fa-solid fa-print', 'files' => ['nota_co_mockup.html', 'nota_pv_spb.html', 'mockup_print_stock_card.html', 'mockup_print_master_stock.html',
                'mockup_sales_quotation_letter_print.html', 'payroll_payslip_list.html', 'payroll_payslip_list.jpg']],
            ['code' => 'SVG', 'label' => 'SVG Packaging & Optimasi', 'icon' => 'fa-solid fa-draw-polygon', 'files' => ['inquiry_new.html', 'inquiry_revise_v2.html', 'inquiry_recalc_v2.html',
                'mockup_inquiry_imposition_grid_form.html', 'mockup_prodev_imposition.html', 'tata layout layar imposistion.jpg']],
            ['code' => 'INQ', 'label' => 'Inquiry', 'icon' => 'fa-solid fa-clipboard-question', 'files' => ['inquiry_main_hub.html', 'inquiry_costing_hub.html', 'inquiry_costing_manual.html', 'inquiry_proof.html',
                'inquiry_quotation.html', 'inquiry_nota_new.html', 'inquiry_nota_revise.html', 'inquiry_nota_recalc.html', 'inquiry_nota_quotation.html', 'nota_inquiry_mockup.html', 'nota_inquiry_mockup_bw.html']],
            ['code' => 'COST', 'label' => 'ProCost & Costing', 'icon' => 'fa-solid fa-calculator', 'files' => ['ERP_Product_Costing_Mockup.html', 'Kalkulasi_HPP_UAT.html', 'product_costing.html', 'mockup_legacy_product_costing.png',
                'inquiry_product_costing/mockup_product_costing_v1_scriptcase.png', 'procost_instant_hasil_build.png', 'procost_integrasi_hasil_build.png',
                'procost_instant_deviasi.jpg', 'procost_instant_deviasi_v2.jpg', 'procost_integrasi_deviasi.jpg', 'procost_integrasi_deviasi_v2.jpg']],
            ['code' => 'ACC', 'label' => 'Accounting', 'icon' => 'fa-solid fa-sack-dollar', 'files' => ['MODULE_accounting-mockup-blueprint.html', 'MODULE_accounting-process-engine-blueprint.html']],
        ];
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

    // Mockup yang sudah punya komponen -> tidak diulang di daftar "belum dibangun".
    public static function builtMockups(): array
    {
        return ['login.html', 'main_menu_v2.html', 'UIUX_components_part1.html'];
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
            $pending = [];
            foreach ($g['files'] as $f) {
                if (isset($all[$f])) {
                    if (! in_array($f, $built, true)) {
                        $pending[] = self::mockupItem($all[$f]);
                    }
                    unset($all[$f]);
                }
            }
            if ($pending) {
                $children[] = ['label' => 'Mockup belum dibangun', 'items' => $pending];
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
