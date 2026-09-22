<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// BACKLOG.md dipecah jadi 10 backlog per domain (permintaan user 2026-09-22)
// supaya tiap bidang bisa dibaca sendiri tanpa menggulung 2.000 baris.
// BACKLOG.md sendiri jadi indeks + bagian lintas-modul. Dokumen ini bukan
// milik 1 menu, jadi terdaftarnya di sys_doc_reference (CLAUDE.md §6 DEC-NAMING).
return new class extends Migration
{
    private const DOCS = [
        ['backlog01_uiux_component.md', 'Backlog 01 - UI/UX, Komponen & Fasilitas Grid/Form', 'Termasuk permintaan menjadikan seluruh komponen object oriented yang bisa diturunkan.'],
        ['backlog02_inquiry_to_production.md', 'Backlog 02 - Inquiry Product s/d Siap Produksi', 'Inquiry, ProDev, ProCost, Quotation, Customer Order, Master Card, Master Mesin & Proses.'],
        ['backlog03_production_to_delivery.md', 'Backlog 03 - Siap Produksi s/d Pengiriman', 'Penjadwalan PPIC, Work Order, eksekusi lantai, sampai Delivery Order.'],
        ['backlog04_procurement.md', 'Backlog 04 - Procurement: PR s/d Barang Masuk', 'PR, PO, PQ, penerimaan barang sampai serah terima ke Inventory.'],
        ['backlog05_inventory_warehouse.md', 'Backlog 05 - Inventory & Warehousing', 'Stok 3 kelas, kartu stok, mutasi, dan tata letak gudang.'],
        ['backlog06_hr_payroll_recruitment.md', 'Backlog 06 - HR, Payroll & Recruitment', 'Master karyawan, presensi/roster, payroll, portal rekrutmen.'],
        ['backlog07_finance_accounting.md', 'Backlog 07 - Finance & Accounting (AR/AP)', 'GL, AR, AP, kas & bank, pajak, Accounting Process Engine.'],
        ['backlog08_dashboard_management.md', 'Backlog 08 - Dashboard, Grafik & Ringkasan Manajemen', 'Dashboard per level jabatan, grafik, ringkasan untuk direksi.'],
        ['backlog09_platform_facility.md', 'Backlog 09 - Fasilitas Platform', 'Chat antar user, AI lokal, keamanan & identitas user, i18n.'],
        ['backlog10_mobile_app.md', 'Backlog 10 - Mobile: Aplikasi Android & iOS', 'Lanjutan dari PWA menuju aplikasi mobile sungguhan.'],
    ];

    public function up(): void
    {
        $sort = 200;

        foreach (self::DOCS as [$file, $title, $desc]) {
            DB::connection('tm2sys')->table('sys_doc_reference')->updateOrInsert(
                ['file_name' => $file],
                [
                    'title' => $title,
                    'category' => 'GLOBAL_RULE',
                    'related_menu_code' => null,
                    'description' => $desc,
                    'sort_order' => $sort,
                    'activate' => 1,
                ]
            );
            $sort += 1;
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_doc_reference')
            ->whereIn('file_name', array_column(self::DOCS, 0))
            ->delete();
    }
};
