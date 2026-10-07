<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Services\Migration\CustomerInvoiceMigrationService;
use App\Services\Migration\CustomerOrderMigrationService;
use App\Services\Migration\DeliveryNoteMigrationService;
use App\Services\Migration\EmployeeLoginProvisionService;
use App\Services\Migration\EmployeeMigrationService;
use App\Services\Migration\MasterCardMigrationService;
use App\Services\Migration\ProductEnrichmentService;
use App\Services\Migration\ProductImageIngestService;
use App\Services\Migration\ProductMigrationService;
use App\Services\Migration\PurchaseOrderMigrationService;
use App\Services\Migration\PurchasePaymentMigrationService;
use App\Services\Migration\PurchaseReceiveMigrationService;
use App\Services\Migration\PurchaseRequisitionMigrationService;
use App\Services\Migration\RawMaterialMigrationService;
use App\Services\Migration\SupportingLookupMigrationService;
use App\Services\Migration\UomMigrationService;
use App\Services\Migration\VendorMigrationService;

// Setup > Migration (v2): tarik data lama (ScriptCase V1, staging tm2old) ke master / arsip V2. Setiap runner = satu service
// App\Services\Migration\* dengan run(string $login): array; aman diulang (idempoten). Daftar runner dan urutannya sama dengan layar v1.
// Laporan hasil tidak disimpan: dikembalikan sebagai baris "Bagian / Hasil" untuk satu kali tampil (sesi).
final class MigrationDesk
{
    private const ARCHIVE_NOTE = 'Read-only history archive for dashboards and reports, separate from the live transaction tables. Idempotent: fully refreshed on every run.';

    /** @return array<string, array{title: string, note: string, service: class-string, group: string}> */
    public static function runners(): array
    {
        return [
            'vendor' => ['group' => 'Master', 'service' => VendorMigrationService::class, 'title' => 'tm2old.mst_vendor → cust_customer & sup_supplier',
                'note' => 'Source: mst_vendor, mst_vendor_bank and the vendor category tables. Rows with as_customer=1 go to Master Customer, as_supplier=1 to Master Supplier (a vendor can be both). Safe to repeat: existing data is updated, not duplicated.'],
            'raw' => ['group' => 'Master', 'service' => RawMaterialMigrationService::class, 'title' => 'tm2old.mst_item (raw material) → mat_raw',
                'note' => "Criteria: material_class='RAW MATERIAL' or as_mat=1. Item codes longer than 60 characters are cut and given a number suffix for material_code (the original text stays intact in old_item_code)."],
            'product' => ['group' => 'Master', 'service' => ProductMigrationService::class, 'title' => 'tm2old.mst_item (finished goods) → prod_product',
                'note' => 'Criteria: as_fgs=1. Physical dimensions and customer / category are deliberately not migrated (the source unit is unclear) — fill them through ProDev when the product is used.'],
            'co' => ['group' => 'History archive', 'service' => CustomerOrderMigrationService::class, 'title' => 'tm2old.sdi_co(_detail) → co_order_legacy (archive, NOT co_order)',
                'note' => 'co_order requires Quotation lineage (1 CO = 1 Quotation) for the new flow — the old COs never had it. They are archived to a separate read-only table for dashboards / reports (e.g. total purchases per customer). Idempotent: fully refreshed on every run.'],
            'po' => ['group' => 'History archive', 'service' => PurchaseOrderMigrationService::class, 'title' => 'tm2old.pur_po(_v2)+pur_po_detail → po_order_legacy (archive, NOT po_order)',
                'note' => 'Header from pur_po_v2 (fuller tax data), lines from pur_po_detail (v1), joined by PO number. Archived to a separate read-only table for dashboards / reports (e.g. total AP per supplier). Idempotent: fully refreshed on every run.'],
            'pr' => ['group' => 'History archive', 'service' => PurchaseRequisitionMigrationService::class, 'title' => 'tm2old.pur_pr(_detail) → pr_requisition_legacy', 'note' => self::ARCHIVE_NOTE],
            'receive' => ['group' => 'History archive', 'service' => PurchaseReceiveMigrationService::class, 'title' => 'tm2old.pur_prcv(_detail) → po_receive_legacy', 'note' => self::ARCHIVE_NOTE],
            'payment' => ['group' => 'History archive', 'service' => PurchasePaymentMigrationService::class, 'title' => 'tm2old.pur_ppym(_detail) → po_payment_legacy', 'note' => self::ARCHIVE_NOTE],
            'dnote' => ['group' => 'History archive', 'service' => DeliveryNoteMigrationService::class, 'title' => 'tm2old.sdi_dnote(_detail) → co_delivery_legacy (delivery notes)', 'note' => self::ARCHIVE_NOTE],
            'cinv' => ['group' => 'History archive', 'service' => CustomerInvoiceMigrationService::class, 'title' => 'tm2old.sdi_cinv(_detail) → co_invoice_legacy (customer invoices, AR basis)', 'note' => self::ARCHIVE_NOTE],
            'uom' => ['group' => 'Supporting master', 'service' => UomMigrationService::class, 'title' => 'tm2old.mst_uom(_conversion) → uom_unit / uom_conversion',
                'note' => 'The existing V2 master WINS — legacy only fills unit codes that do not exist yet.'],
            'lookup' => ['group' => 'Supporting master', 'service' => SupportingLookupMigrationService::class, 'title' => 'tm2old.mst_* (supporting lookups) → ref_legacy_lookup',
                'note' => 'All small lookups are merged into one table (column lookup_group) instead of dozens of thin tables. Tables over 300 rows are reported as "needs a separate design".'],
            'card' => ['group' => 'Supporting master', 'service' => MasterCardMigrationService::class, 'title' => 'tm2old Master Card → prod_card_legacy etc. (product engineering)',
                'note' => 'Materials, process sequence + machines and knives per product. The bridge to the item is mst_product_mc.'],
            'image' => ['group' => 'Supporting master', 'service' => ProductImageIngestService::class, 'title' => 'Master Card images → Master Product (prod_product.image_path)',
                'note' => 'Must run AFTER Master Card. Every card with a file name has it in the ScriptCase archive; through the mst_product_mc bridge it reaches the products. The file is copied once per card + a 480px thumbnail to storage/app/public/product/card.'],
            'enrich' => ['group' => 'Supporting master', 'service' => ProductEnrichmentService::class, 'title' => 'mst_item classification → customer_id / category_id / brand_name',
                'note' => 'Completes the columns ProductMigrationService skipped. goods_belongto is matched to cust_customer.nickname (exact; duplicate nicknames dropped), goods_purpose is mapped to a category by keyword. The cm_* dimensions are still NOT filled — the source mixes mm / cm without a marker.'],
            'employee' => ['group' => 'Employee', 'service' => EmployeeMigrationService::class, 'title' => 'tm2old.emp → emp_employee (+ org_department / org_position)',
                'note' => 'Source: emp (full roster; emp_history is a full duplicate archive and is skipped on purpose), emp_position, emp_identities (ID card) and the legacy org_department. Personal data is very incomplete in the source — NOT invented, left empty. The real department / position per employee is almost never recorded, so every migrated employee lands in one temporary bucket "Migrasi Legacy - Belum Dipetakan" that must be reassigned manually.'],
            'login' => ['group' => 'Employee', 'service' => EmployeeLoginProvisionService::class, 'title' => 'emp_employee (active) → sec_user login',
                'note' => 'Creates a login for migrated employees with activate=1 (people who already left get none). Username = first name (a clash gets a number), a random password is made through PasswordResetService and must be changed at first login. Safe to repeat — employees who already have an account are skipped.'],
        ];
    }

    public static function find(string $key): array
    {
        return self::runners()[$key] ?? abort(404);
    }

    /** @return array<string, mixed> laporan mentah service */
    public static function run(string $key, string $by): array
    {
        set_time_limit(0);

        return app(self::find($key)['service'])->run($by);
    }

    /** @return list<array{part: string, result: string}> laporan -> baris tabel; daftar pendek ditampilkan 20 isi pertama */
    public static function rows(array $report): array
    {
        $rows = [];
        foreach ($report as $key => $value) {
            $rows[] = ['part' => (string) $key, 'result' => self::text($value)];
        }

        return $rows;
    }

    private static function text(mixed $value): string
    {
        if (! is_array($value)) {
            return (string) $value;
        }
        if ($value === []) {
            return '—';
        }
        if (array_is_list($value)) {
            return implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), array_slice($value, 0, 20))).(count($value) > 20 ? '…' : '');
        }

        return implode(', ', array_map(fn ($k, $v) => $k.': '.(is_scalar($v) ? $v : json_encode($v)), array_keys($value), $value));
    }
}
