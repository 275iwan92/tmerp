<?php

namespace App\Support;

use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

// Menu situs DevLib = kelompok komponen library. Tiap item = satu mockup user,
// dipasangkan dengan komponen library yang menirunya (COMPONENTS).
// Mockup yang belum dikelompokkan tetap muncul di "Lainnya".
final class Catalog
{
    public const GROUPS = [
        ['code' => 'LOGIN', 'label' => 'Login & Akun', 'icon' => 'fa-solid fa-right-to-bracket', 'files' => ['login.html', 'register.html', 'forgot-password.html', 'security.html']],
        ['code' => 'SHELL', 'label' => 'Shell & Menu', 'icon' => 'fa-solid fa-table-columns', 'files' => ['main_menu_v2.html', 'mockup_notification.jpg', 'mockup_dashboardcolor.html']],
        ['code' => 'FORM', 'label' => 'Form', 'icon' => 'fa-solid fa-pen-to-square', 'files' => ['UIUX_components_part1.html', 'penjelasan_standar_input_teks.html', 'penjelasan_standar_input_teks.jpg',
            'bukti_komponen_input_tema.html', 'bukti_komponen_input_tema.jpg', 'bukti_komponen_tema_navy.html', 'bukti_komponen_tema_emerald.html', 'bukti_komponen_tema_cerah_red.html']],
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

    // Mockup yang sudah punya komponen library. status: review = menunggu
    // penilaian user (hanya user yang boleh menaikkan ke 'approved').
    public const COMPONENTS = [
        'login.html' => ['tag' => 'x-dl::login', 'preview' => 'preview.login', 'status' => 'review'],
        'main_menu_v2.html' => ['tag' => 'x-dl::shell', 'preview' => 'preview.shell', 'status' => 'review'],
    ];

    public const STATUS_LABEL = ['none' => 'Belum', 'review' => 'Review', 'approved' => '✓'];

    public static function root(): string
    {
        return config('devlib.mockup_path');
    }

    // Bentuk siap-pakai untuk <x-dl::shell :modules>.
    public static function modules(): array
    {
        $all = self::files();
        $modules = [];

        foreach (self::GROUPS as $g) {
            $items = [];
            foreach ($g['files'] as $f) {
                if (isset($all[$f])) {
                    $items[] = self::item($all[$f]);
                    unset($all[$f]);
                }
            }
            $modules[] = ['code' => $g['code'], 'label' => $g['label'], 'icon' => $g['icon'], 'children' => $items];
        }

        if ($all) {
            $modules[] = ['code' => 'ETC', 'label' => 'Lainnya', 'icon' => 'fa-solid fa-folder-open', 'children' => array_map(self::item(...), array_values($all))];
        }

        return $modules;
    }

    public static function find(string $file): ?array
    {
        return self::files()[$file] ?? null;
    }

    public static function component(string $file): ?array
    {
        return self::COMPONENTS[$file] ?? null;
    }

    // Path absolut di dalam folder mockup, null bila di luar (anti traversal).
    public static function path(string $file): ?string
    {
        $real = realpath(self::root().DIRECTORY_SEPARATOR.$file);
        $root = realpath(self::root());

        return $real && $root && is_file($real) && str_starts_with($real, $root.DIRECTORY_SEPARATOR) ? $real : null;
    }

    private static function item(array $m): array
    {
        $status = self::COMPONENTS[$m['file']]['status'] ?? 'none';

        return [
            'code' => 'M'.substr(md5($m['file']), 0, 10),
            'label' => $m['title'],
            'url' => route('compare', ['file' => $m['file']]),
            'type' => $m['type'] === 'html' ? 'form' : 'report',
            'icon' => $m['type'] === 'html' ? 'fa-solid fa-file-code' : 'fa-solid fa-image',
            'badge' => self::STATUS_LABEL[$status],
            'badgeTone' => $status === 'approved' ? 'done' : ($status === 'review' ? 'partial' : ''),
        ];
    }

    /** @return array<string, array{file: string, title: string, type: string}> */
    private static function files(): array
    {
        $items = [];
        $root = self::root();
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
