<?php

namespace App\Support\Mockup;

use Symfony\Component\Finder\Finder;

// Katalog situs tinjau mockuperp.test: mockup user (docs/mockups) dipasangkan
// dengan komponen library DevLib yang menirunya. File yang belum dikelompokkan
// tetap muncul di "Lainnya" -- tak ada mockup yang boleh hilang dari situs.
// status: none = komponen belum dibangun, partial, done (sama dgn mockup).
final class MockupCatalog
{
    public const GROUPS = [
        'Login & Akun' => ['login.html', 'register.html', 'forgot-password.html', 'security.html'],
        'Shell & Menu' => ['main_menu_v2.html', 'mockup_notification.jpg', 'mockup_dashboardcolor.html'],
        'Form' => ['UIUX_components_part1.html', 'penjelasan_standar_input_teks.html', 'penjelasan_standar_input_teks.jpg',
            'bukti_komponen_input_tema.html', 'bukti_komponen_input_tema.jpg', 'bukti_komponen_tema_navy.html',
            'bukti_komponen_tema_emerald.html', 'bukti_komponen_tema_cerah_red.html'],
        'Grid' => ['mockup_master_employee.html', 'filament_table_demo.html', 'powergrid_demo.html',
            'grid_kerapatan_baris.html', 'grid_kerapatan_baris.jpg', 'pilihan_ikon_aksi_grid.html', 'pilihan_ikon_aksi_grid.jpg',
            'pilihan_ikon_aksi_grid_konteks.html', 'pilihan_ikon_aksi_grid_konteks.jpg', 'mockup_master_uom_v4.html'],
        'Master Data' => ['mockup_master_customer.html', 'mockup_master_rawmaterial.html', 'mockup_master_warehouse.html',
            'mockup_master_produksi.html', 'master_mesin_update_2026.html', 'master_mesin_update_2026.jpg', 'master_coa_mockup.html',
            'mockup_mastercard.jpg', 'mockup_mastercard_ver2.jpg'],
        'Dokumen & Approval' => ['mockup_purchase_requisition.html', 'mockup_pr_approval_center.html', 'mockup_po_approval_center.html',
            'mockup_purchase_quotation.html', 'mockup_purchase_receive.html', 'mockup_purchase_return.html', 'mockup_flow_engine.html',
            'mockup_sales_quotation_entry.html', 'mockup_co_entry.html', 'co_delivery_plan_mockup.html', 'mockup_down_payment.html',
            'mockup_stock_in.html', 'mockup_stock_out.html', 'mockup_stock_return.html', 'mockup_stock_adjustment.html'],
        'Kanban · Kalender · Gantt' => ['mockup_UIUX_kanban.html', 'mockup_UIUX_calendar.html', 'mockup_scheduling_board.html',
            'mockup_roster_shift.html', 'mockup_roster_formasi_harian.html', 'mockup_roster_formasi_harian.jpg',
            'real_roster_formasi_editor.jpg', 'real_roster_formasi_ringkas.jpg', 'real_roster_formasi_harian.jpg'],
        'Bagan & Kartu' => ['Organization-Structure-Mockup.html', 'mockup_ID_card.png', 'mockup_personal_data_self_service.html'],
        'Cetak / Nota' => ['nota_co_mockup.html', 'nota_pv_spb.html', 'mockup_print_stock_card.html', 'mockup_print_master_stock.html',
            'mockup_sales_quotation_letter_print.html', 'payroll_payslip_list.html', 'payroll_payslip_list.jpg'],
        'Inquiry (zona beku)' => ['inquiry_main_hub.html', 'inquiry_costing_hub.html', 'inquiry_costing_manual.html',
            'inquiry_proof.html', 'inquiry_quotation.html', 'inquiry_nota_new.html', 'inquiry_nota_revise.html',
            'inquiry_nota_recalc.html', 'inquiry_nota_quotation.html', 'nota_inquiry_mockup.html', 'nota_inquiry_mockup_bw.html'],
        'SVG Packaging & Optimasi (zona beku)' => ['inquiry_new.html', 'inquiry_revise_v2.html', 'inquiry_recalc_v2.html',
            'mockup_inquiry_imposition_grid_form.html', 'mockup_prodev_imposition.html', 'tata layout layar imposistion.jpg'],
        'ProCost & Costing (zona beku)' => ['ERP_Product_Costing_Mockup.html', 'Kalkulasi_HPP_UAT.html', 'product_costing.html',
            'mockup_legacy_product_costing.png', 'inquiry_product_costing/mockup_product_costing_v1_scriptcase.png',
            'procost_instant_hasil_build.png', 'procost_integrasi_hasil_build.png', 'procost_instant_deviasi.jpg',
            'procost_instant_deviasi_v2.jpg', 'procost_integrasi_deviasi.jpg', 'procost_integrasi_deviasi_v2.jpg'],
        'Accounting' => ['MODULE_accounting-mockup-blueprint.html', 'MODULE_accounting-process-engine-blueprint.html'],
    ];

    // Status komponen library per mockup; semua 'none' sampai GO BUILD LIBRARY.
    public const STATUS = [];

    public static function root(): string
    {
        return base_path('docs/mockups');
    }

    /** @return array<string, array<int, array{file: string, title: string, type: string, date: string, status: string}>> */
    public static function groups(): array
    {
        $all = self::files();
        $out = [];

        foreach (self::GROUPS as $group => $files) {
            foreach ($files as $file) {
                if (isset($all[$file])) {
                    $out[$group][] = $all[$file];
                    unset($all[$file]);
                }
            }
        }

        if ($all) {
            $out['Lainnya'] = array_values($all);
        }

        return $out;
    }

    public static function find(string $file): ?array
    {
        return self::files()[$file] ?? null;
    }

    // Path absolut di dalam docs/mockups, null bila di luar folder (anti traversal).
    public static function path(string $file): ?string
    {
        $real = realpath(self::root().DIRECTORY_SEPARATOR.$file);
        $root = realpath(self::root());

        return $real && is_file($real) && str_starts_with($real, $root.DIRECTORY_SEPARATOR) ? $real : null;
    }

    /** @return array<string, array{file: string, title: string, type: string, date: string, status: string}> */
    private static function files(): array
    {
        $items = [];
        $finder = (new Finder)->files()->in(self::root())->name(['*.html', '*.jpg', '*.jpeg', '*.png'])->sortByName();

        foreach ($finder as $f) {
            $rel = str_replace('\\', '/', $f->getRelativePathname());
            $items[$rel] = [
                'file' => $rel,
                'title' => self::title($f->getFilename()),
                'type' => strtolower($f->getExtension()) === 'html' ? 'html' : 'image',
                'date' => date('d M Y', $f->getMTime()),
                'status' => self::STATUS[$rel] ?? 'none',
            ];
        }

        return $items;
    }

    private static function title(string $name): string
    {
        $base = preg_replace('/^(mockup_|MODULE_)/', '', pathinfo($name, PATHINFO_FILENAME));

        return ucwords(str_replace(['_', '-'], ' ', $base));
    }
}
