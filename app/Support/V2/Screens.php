<?php

namespace App\Support\V2;

// Menu mockup.test: satu modul = lengkap sendiri (transaksi + master penyertanya), supaya hak akses per modul
// cukup membuka satu menu. Master yang dipakai beberapa modul = SATU layar, tampil di menu tiap modul.
// Layar: 'ref' = acuan tmerp sekarang (alur & field), 'mockup' = mockup HTML asli (tampilan), 'status' todo|review.
final class Screens
{
    // Layar bersama (dipakai beberapa modul).
    private static function shared(): array
    {
        return [
            'master-product' => ['Master Product', 'fa-solid fa-box', 'docs/flow_product_master.md (baru, v2)', null],
            'product-acc' => ['ACC Digital / Proof Digital', 'fa-solid fa-stamp', 'docs/flow_product_master.md §5', null],
            'master-raw' => ['Master Bahan Baku', 'fa-solid fa-layer-group', 'master/raw-material/index.blade.php', 'mockup_master_rawmaterial.html'],
            'master-customer' => ['Master Customer', 'fa-solid fa-building', 'master/customer.blade.php', 'mockup_master_customer.html'],
            'master-matcat' => ['Kategori Bahan Baku', 'fa-solid fa-sitemap', 'master/mat-category.blade.php', null],
            'master-uom' => ['Satuan (UoM)', 'fa-solid fa-ruler', 'master/uom.blade.php', 'mockup_master_uom_v4.html'],
            'master-currency' => ['Mata Uang & Kurs', 'fa-solid fa-coins', 'master/currency.blade.php', null],
            'master-card' => ['Master Card', 'fa-solid fa-id-card', 'production/prodev.blade.php (Master Card)', 'mockup_mastercard_ver2.jpg'],
            'prodev' => ['Pro-Dev (Product Development)', 'fa-solid fa-table-cells', 'production/prodev.blade.php + prodev-list.blade.php', 'mockup_prodev_imposition.html'],
        ];
    }

    public static function modules(): array
    {
        $s = self::shared();
        $it = fn (string $slug, ?array $def = null) => self::item($slug, $def ?? $s[$slug]);

        return [
            ['code' => 'SALES', 'short' => 'SLS', 'label' => 'Sales', 'icon' => 'fa-solid fa-cart-shopping', 'groups' => [
                ['label' => 'Inquiry & Quotation', 'items' => [
                    $it('inquiry', ['Inquiry Product (worklist)', 'fa-solid fa-inbox', 'inquiry/hub.blade.php', 'inquiry_main_hub.html']),
                    $it('inquiry-new', ['Inquiry Baru', 'fa-solid fa-file-circle-plus', 'inquiry/form.blade.php', 'inquiry_new.html']),
                    $it('inquiry-revise', ['Inquiry Revisi', 'fa-solid fa-file-pen', 'inquiry/revise.blade.php', 'inquiry_revise_v2.html']),
                    $it('inquiry-recalc', ['Inquiry Rekalkulasi', 'fa-solid fa-calculator', 'inquiry/recalc.blade.php', 'inquiry_recalc_v2.html']),
                    $it('prodev'),
                    $it('master-card-collection', ['Master Card Collection', 'fa-solid fa-id-card', 'v2 baru: upload master card lama (Excel/foto) -> Master Product / Inquiry', 'master_card_collection_v2.jpg']),
                    $it('sales-price', ['Harga dari Pro-Cost (tier & harga)', 'fa-solid fa-tags', 'inquiry/costing.blade.php (hanya tier & harga terpublish)', null]),
                    $it('quotation', ['Quotation', 'fa-solid fa-file-signature', 'quotation/entry.blade.php + list', 'mockup_sales_quotation_entry.html']),
                    $it('nota-inquiry', ['Nota Inquiry', 'fa-solid fa-receipt', 'inquiry/nota.blade.php', 'nota_inquiry_mockup.html']),
                ]],
                ['label' => 'Sales Order', 'items' => [
                    $it('customer-order', ['Customer Order', 'fa-solid fa-bag-shopping', 'co/entry.blade.php + list', 'mockup_co_entry.html']),
                    $it('cpdel', ['Customer Plan Delivery', 'fa-solid fa-truck-ramp-box', 'co/delivery-plan.blade.php', 'co_delivery_plan_mockup.html']),
                    $it('approval-co', ['CO Approval Center', 'fa-solid fa-stamp', 'v2 baru: DevLib Form Approval + flow engine CO (F&A SPV -> Operational Manager)', null]),
                ]],
                ['label' => 'Delivery', 'items' => [
                    $it('delivery-order', ['Delivery Order', 'fa-solid fa-truck', 'dispatch/do/entry.blade.php + list', null]),
                    $it('delivery-notes', ['Delivery Notes (Surat Jalan)', 'fa-solid fa-file-lines', 'dispatch/do/print-surat-jalan.blade.php', null]),
                ]],
                ['label' => 'Master', 'items' => [$it('master-product'), $it('product-acc'), $it('master-raw'), $it('master-customer'), $it('master-card')]],
                ['label' => 'Lookup', 'items' => [$it('master-matcat'), $it('master-uom'), $it('master-currency')]],
                ['label' => 'Laporan', 'items' => [
                    $it('win-rate', ['Win-Rate Sales', 'fa-solid fa-trophy', 'sales/win-rate.blade.php', null]),
                    $it('quotation-outstanding', ['Quotation Terbuka', 'fa-solid fa-hourglass-half', 'v2 baru: quo_quotation PUBLISHED belum WIN/LOST', null]),
                    $it('quotation-result', ['Hasil Quotation (WIN/LOST)', 'fa-solid fa-chart-column', 'v2 baru: quo_quotation ditutup', null]),
                    $it('quotation-followup', ['Follow-up Quotation', 'fa-solid fa-phone', 'v2 baru: quo_activity', null]),
                ]],
                ['label' => 'Alur', 'items' => [self::flowItem('SALES', 'Alur Sales: Inquiry → CPDEL')]],
            ]],
            ['code' => 'PROC', 'short' => 'PRC', 'label' => 'Procurement', 'icon' => 'fa-solid fa-cart-flatbed', 'groups' => [
                ['label' => 'Requisition', 'items' => [
                    $it('purchase-requisition', ['Purchase Requisition (PR)', 'fa-solid fa-file-circle-plus', 'pur/pr/index + entry.blade.php', 'mockup_purchase_requisition.html']),
                    $it('approval-pr', ['PR Approval Center', 'fa-solid fa-stamp', 'pur/pr/approval.blade.php', 'mockup_pr_approval_center.html']),
                ]],
                ['label' => 'Order', 'items' => [
                    $it('purchase-quotation', ['Purchase Quotation (PQ)', 'fa-solid fa-file-import', 'pur/pq/index + entry.blade.php (v2: upload file penawaran)', null]),
                    $it('purchase-order', ['Purchase Order (PO)', 'fa-solid fa-file-invoice-dollar', 'pur/po/index + entry.blade.php', null]),
                    $it('approval-po', ['PO Approval Center', 'fa-solid fa-stamp', 'pur/po/approval.blade.php', 'mockup_po_approval_center.html']),
                ]],
                ['label' => 'Receive', 'items' => [
                    $it('purchase-receive', ['Penerimaan Barang (SPB)', 'fa-solid fa-truck-ramp-box', 'pur/pv/index + entry.blade.php', null]),
                ]],
                ['label' => 'Return', 'items' => [
                    $it('approval-prt', ['Purchase Return Approval Center', 'fa-solid fa-stamp', 'pur/prt/approval.blade.php', null]),
                ]],
                ['label' => 'Master', 'items' => [$it('master-raw')]],
            ]],
            ['code' => 'COSTING', 'short' => 'PCO', 'label' => 'Product Costing', 'icon' => 'fa-solid fa-scale-balanced', 'groups' => [
                ['label' => 'Costing', 'items' => [
                    $it('procost', ['Pro-Cost (Costing Hub)', 'fa-solid fa-scale-balanced', 'inquiry/costing.blade.php + costing-list', 'inquiry_costing_hub.html']),
                    $it('procost-instant', ['Pro-Cost Instant (manual)', 'fa-solid fa-bolt', 'inquiry/costing-instant.blade.php + list', 'inquiry_costing_manual.html']),
                ]],
                ['label' => 'Master', 'items' => [$it('master-product'), $it('master-raw'), $it('prodev')]],
                ['label' => 'Alur', 'items' => [self::flowItem('COSTING', 'Alur Costing: ProDev → ProCost → Publish')]],
            ]],
            ['code' => 'INV', 'short' => 'INV', 'label' => 'Inventory', 'icon' => 'fa-solid fa-warehouse', 'groups' => [
                ['label' => 'Stock Adjustment', 'items' => [
                    $it('approval-stock', ['Adjustment & Opname Approval Center', 'fa-solid fa-stamp', 'inventory/stock-adjustment-approval.blade.php', null]),
                ]],
            ]],
            ['code' => 'SETUP', 'short' => 'SET', 'label' => 'Setup', 'icon' => 'fa-solid fa-gear', 'groups' => [
                ['label' => 'Settings', 'items' => [
                    $it('approval-setting', ['Setting Approval', 'fa-solid fa-list-ol', 'v2 baru: tm2mst.flow_config + flow_config_step (pengganti m_authorize)', null]),
                    $it('approval-acting', ['Officer Pengganti (PLT)', 'fa-solid fa-user-clock', 'v2 baru: tm2mst.flow_position_acting', null]),
                ]],
            ]],
            // Khusus developer: tidak tampil untuk akun biasa.
            ...(auth()->user()?->is_admin ? [['code' => 'DEV', 'short' => 'DEV', 'label' => 'Developer', 'icon' => 'fa-solid fa-code', 'groups' => [
                ['label' => 'Database', 'items' => [
                    ['code' => 'S-dictionary', 'label' => 'Data Dictionary', 'icon' => 'fa-solid fa-database', 'url' => route('v2.dictionary'), 'type' => 'report'],
                ]],
            ]]] : []),
        ];
    }

    private static function item(string $slug, array $def): array
    {
        [$label, $icon, $ref, $mockup] = $def;
        $status = is_file(resource_path("views/v2/screens/{$slug}.blade.php")) ? 'review' : 'todo';

        return ['code' => 'S-'.$slug, 'slug' => $slug, 'label' => $label, 'icon' => $icon, 'ref' => $ref, 'mockup' => $mockup, 'status' => $status,
            'url' => route('v2.screen', $slug), 'type' => 'form', 'badge' => $status === 'review' ? 'Review' : 'Belum', 'badgeTone' => $status === 'review' ? 'partial' : 'todo'];
    }

    private static function flowItem(string $module, string $label): array
    {
        return ['code' => 'F-'.$module, 'label' => $label, 'icon' => 'fa-solid fa-diagram-project', 'url' => route('v2.flow', $module), 'type' => 'report'];
    }

    // Bentuk untuk <x-dl::shell :modules>.
    public static function shell(): array
    {
        return array_map(fn ($m) => ['code' => $m['code'], 'short' => $m['short'], 'label' => $m['label'], 'icon' => $m['icon'],
            'children' => array_map(fn ($g) => ['label' => $g['label'], 'items' => $g['items']], $m['groups'])], self::modules());
    }

    public static function screen(string $slug): ?array
    {
        foreach (self::modules() as $m) {
            foreach ($m['groups'] as $g) {
                foreach ($g['items'] as $i) {
                    if (($i['slug'] ?? null) === $slug) {
                        return $i + ['module' => $m['label'], 'module_code' => $m['code'], 'group' => $g['label']];
                    }
                }
            }
        }

        return null;
    }
}
