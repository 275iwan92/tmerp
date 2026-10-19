<?php

namespace App\Support\V2;

use App\Support\V2\Data\Sales;
use Devlib\Lookup;
use Illuminate\Support\Facades\DB;

/*
 * REMARK
 * Tujuan       : Mendaftarkan semua Devlib\Lookup yang dipakai layar v2 (customer, sales person, bahan, product, item stok, bin, batch, pegawai, wilayah).
 * Sumber hulu  : Tabel master tm2mst / tm2inv; daftar kecil lewat App\Support\V2\Data\Sales, data besar dicari ke server (search / find).
 * Input        : Nama lookup, kata cari, parameter ctx dari field.
 * Output       : Baris lookup untuk x-dl::field.lookup dan kolom lookup x-dl::lines.
 * Dipakai oleh : Semua layar v2 dengan field atau baris lookup; Devlib route devlib.lookup.
 * Status       : Aktif. Lookup stock_item_rm / _fg / _sfg, stock_bin, stock_batch, stock_return_bin ditambah 2026-10-19 untuk dokumen stok manual (Stock In, Stock Out, Return); prod_process dan prod_machine untuk langkah routing (Master T6b).
 */

// Lookup = data master yang dipilih di layar v2 (bentuk sama dengan tabel tujuannya, lihat `source`).
// Semua dibaca dari database situs (ddt* / tm2*): daftar kecil lewat App\Support\V2\Data\Sales, data besar dicari ke server.
final class Lookups
{
    public static function register(): void
    {
        Lookup::define('customer', ['rows' => fn () => Sales::customerRows(), 'key' => 'id_customer', 'label' => 'customer_name',
            'columns' => ['customer_name' => 'Customer Name', 'customer_code' => 'Code'], 'source' => 'tm2mst.cust_customer']);
        Lookup::define('sales_person', ['rows' => fn () => Sales::salesPersons(), 'key' => 'sales_name', 'label' => 'sales_name',
            'columns' => ['sales_name' => 'Sales Name', 'sales_type' => 'Type'], 'source' => 'tm2mst.sales_person']);
        Lookup::define('paper', ['rows' => fn () => Sales::masters()['materials'], 'key' => 'material_id', 'label' => 'material_name',
            'columns' => ['material_name' => 'Material Name', 'material_code' => 'Code', 'brand' => 'Brand'], 'fill' => ['brand' => 'brand', 'category' => 'category'],
            'source' => 'tm2mst.mat_raw (activate = 1, stat_confirm = 1)']);

        // Data besar -> Lookup remote (dicari ke server). Master Product: milik customer dokumen didahulukan (ctx customer_id).
        Lookup::define('product', ['key' => 'id_product', 'label' => 'label', 'source' => 'tm2mst.prod_product (activate = 1)',
            'columns' => ['product_name' => 'Product', 'product_code' => 'Code', 'design_no' => 'Design No.', 'owner' => 'Customer'],
            'search' => fn (string $q, array $ctx) => self::products()
                ->where(fn ($w) => $w->where('p.product_name', 'like', "%{$q}%")->orWhere('p.product_code', 'like', "%{$q}%")->orWhere('p.design_no', 'like', "%{$q}%"))
                ->orderByRaw('CASE WHEN p.customer_id = ? THEN 0 ELSE 1 END', [(int) ($ctx['customer_id'] ?? 0)])->orderBy('p.product_name')->limit(50)->get(),
            'find' => fn ($v) => self::products()->where('p.id_product', (int) $v)->first()]);

        // Bahan baku / item beli (PR, PO): dicari ke server -- mat_raw aktif & confirmed (sama dgn filter v1 pur.pr.entry).
        Lookup::define('material', ['key' => 'material_id', 'label' => 'label', 'source' => 'tm2mst.mat_raw (activate = 1, stat_confirm = 1)',
            'columns' => ['material_name' => 'Item Name', 'material_code' => 'Code', 'uom_purchase' => 'Unit'],
            'search' => fn (string $q) => self::materials()->where(fn ($w) => $w->where('material_name', 'like', "%{$q}%")->orWhere('material_code', 'like', "%{$q}%"))
                ->orderBy('material_name')->limit(50)->get(),
            'find' => fn ($v) => self::materials()->where('material_id', (int) $v)->first()]);

        // Item stok semua kelas (form Batch): memilih item juga mengisi Class (RM / SFG / FG) lewat fill.
        Lookup::define('stock_item', ['key' => 'item_code', 'label' => 'label', 'source' => 'tm2mst.mat_raw + tm2mst.prod_part + tm2mst.prod_product (activate = 1)',
            'columns' => ['item_name' => 'Item', 'item_code' => 'Code', 'class_label' => 'Class'], 'fill' => ['stock_class' => 'class'],
            'search' => fn (string $q) => Data\Batches::itemSearch($q),
            'find' => fn ($v) => Data\Batches::itemFind((string) $v)]);

        // Pegawai aktif (teknisi Maintenance, pelapor): dicari ke server.
        Lookup::define('employee', ['key' => 'employee_id', 'label' => 'label', 'source' => 'tm2mst.emp_employee (activate = 1)',
            'columns' => ['full_name' => 'Name', 'employee_code' => 'Employee No', 'position_name' => 'Position'],
            'search' => fn (string $q) => self::employees()->where(fn ($w) => $w->where('e.full_name', 'like', "%{$q}%")->orWhere('e.employee_code', 'like', "%{$q}%")->orWhere('p.position_name', 'like', "%{$q}%"))
                ->orderBy('e.full_name')->limit(50)->get(),
            'find' => fn ($v) => self::employees()->where('e.employee_id', (int) $v)->first()]);

        // Dokumen stok manual (Stock In dst.): item per kelas (RM bahan, FG product, SFG part; memilih item mengisi satuan stok), bin dan batch.
        foreach (['RM' => 'tm2mst.mat_raw', 'FG' => 'tm2mst.prod_product', 'SFG' => 'tm2mst.prod_part'] as $class => $table) {
            Lookup::define('stock_item_'.strtolower($class), ['key' => 'id', 'label' => 'label', 'source' => $table.' (activate = 1)', 'fill' => ['uom_code' => 'uom'],
                'columns' => ['name' => 'Item Name', 'code' => 'Code', 'uom' => 'Unit'],
                'search' => fn (string $q) => Data\StockEntry::itemSearch($class, $q), 'find' => fn ($v) => Data\StockEntry::itemFind($class, $v)]);
        }
        Lookup::define('stock_bin', ['key' => 'id', 'label' => 'label', 'source' => 'tm2mst.wh_loc (activate = 1, is_usable = 1)', 'columns' => ['code' => 'Bin', 'warehouse' => 'Warehouse'],
            'search' => fn (string $q) => Data\StockEntry::binSearch($q), 'find' => fn ($v) => Data\StockEntry::binFind($v)]);
        Lookup::define('stock_return_bin', ['key' => 'id', 'label' => 'label', 'source' => 'tm2mst.wh_loc (activate = 1; usable untuk GOOD, non-usable untuk SCRAP)', 'columns' => ['code' => 'Bin', 'warehouse' => 'Warehouse', 'type' => 'Type'],
            'search' => fn (string $q) => Data\StockEntry::binSearch($q, 40, false), 'find' => fn ($v) => Data\StockEntry::binFind($v)]);
        // Master Production (langkah routing): proses aktif dan mesin aktif; mesin yang tidak mampu proses ditolak server saat Save.
        Lookup::define('prod_process', ['key' => 'id', 'label' => 'label', 'source' => 'tm2mst.prod_process (activate = 1)', 'columns' => ['name' => 'Process', 'code' => 'Code', 'stage' => 'Stage'],
            'search' => fn (string $q) => Data\ProductionRef::processSearch($q), 'find' => fn ($v) => Data\ProductionRef::processFind($v)]);
        Lookup::define('prod_machine', ['key' => 'id', 'label' => 'label', 'source' => 'tm2mst.prod_machine (activate = 1)', 'columns' => ['code' => 'Machine', 'name' => 'Name'],
            'search' => fn (string $q) => Data\ProductionRef::machineSearch($q), 'find' => fn ($v) => Data\ProductionRef::machineFind($v)]);
        Lookup::define('rcr_requisition_approved', ['key' => 'id', 'label' => 'label', 'source' => 'tm2hrr.rcr_requisition (status = APPROVED)', 'columns' => ['no' => 'Requisition', 'position' => 'Position', 'headcount' => 'Headcount'],
            'fill' => ['title' => 'title'], 'search' => fn (string $q) => \App\Services\Recruitment\RecruitmentVacancyService::requisitionSearch($q), 'find' => fn ($v) => \App\Services\Recruitment\RecruitmentVacancyService::requisitionFind($v)]);
        Lookup::define('prod_process_machine_side', ['key' => 'id', 'label' => 'label', 'source' => 'tm2mst.prod_process (activate = 1, is_manual = 0)', 'columns' => ['code' => 'Code', 'name' => 'Process', 'stage' => 'Stage'],
            'search' => fn (string $q) => Data\ProductionRef::machineProcessSearch($q), 'find' => fn ($v) => Data\ProductionRef::processFind($v)]);
        Lookup::define('prod_machine_model', ['key' => 'id', 'label' => 'label', 'source' => 'tm2mst.prod_machine_model (activate = 1)', 'columns' => ['code' => 'Model', 'size' => 'Sheet size', 'drive' => 'Drive'],
            'fill' => ['max_sheet_length_mm' => 'work_length_mm', 'max_sheet_width_mm' => 'work_width_mm', 'max_speed_per_hour' => 'speed_per_hour', 'power_kw' => 'motor_kw'],
            'search' => fn (string $q) => Data\ProductionRef::modelSearch($q), 'find' => fn ($v) => Data\ProductionRef::modelFind($v)]);
        Lookup::define('prod_machine_proc', ['key' => 'id', 'label' => 'label', 'source' => 'tm2mst.prod_machine (activate = 1, is_process_machine = 1)', 'columns' => ['code' => 'Machine', 'name' => 'Name'],
            'search' => fn (string $q) => Data\ProductionRef::processMachineSearch($q), 'find' => fn ($v) => Data\ProductionRef::machineFind($v)]);
        Lookup::define('stock_batch', ['key' => 'id', 'label' => 'label', 'source' => 'tm2inv.inv_batch (activate = 1)', 'columns' => ['batch_no' => 'Batch', 'lot' => 'Supplier lot', 'item' => 'Item', 'expiry' => 'Expiry'],
            'search' => fn (string $q) => Data\StockEntry::batchSearch($q), 'find' => fn ($v) => Data\StockEntry::batchFind($v)]);

        // Wilayah kirim = v_geo_full_address (5 level, sama dengan AddressPicker v1). Nilai = "LEVEL:id".
        Lookup::define('geo_area', ['key' => 'area', 'label' => 'full_label', 'source' => 'tm2mst.v_geo_full_address',
            'columns' => ['full_label' => 'Region', 'postal_code' => 'Postcode'], 'fill' => ['ship_postal_code' => 'postal_code'],
            'search' => fn (string $q) => DB::connection('tm2mst')->table('v_geo_full_address')
                ->select('id_village', 'id_district', 'id_city', 'full_label', 'postal_code')
                ->where(fn ($w) => $w->where('full_label', 'like', "%{$q}%")->orWhere('postal_code', 'like', "{$q}%"))
                ->orderBy('full_label')->limit(30)->get()->map(fn ($r) => (array) $r + ['area' => self::geoKey($r)]),
            'find' => fn ($v) => self::geoFind((string) $v)]);
    }

    private static function materials()
    {
        return DB::connection('tm2mst')->table('mat_raw')->where('activate', 1)->where('stat_confirm', 1)
            ->select('material_id', 'material_code', 'material_name', 'uom_purchase', DB::raw("CONCAT(material_code, ' · ', material_name) as label"));
    }

    private static function employees()
    {
        return DB::connection('tm2mst')->table('emp_employee as e')->where('e.activate', 1)
            ->leftJoin('org_position as p', 'p.position_id', '=', 'e.position_id')
            ->select('e.employee_id', 'e.full_name', 'e.employee_code', 'p.position_name', DB::raw("CONCAT(e.full_name, ' · ', COALESCE(e.employee_code, '')) as label"));
    }

    private static function products()
    {
        return DB::connection('tm2mst')->table('prod_product as p')->where('p.activate', 1)
            ->leftJoin('cust_customer as c', 'c.id_customer', '=', 'p.customer_id')
            ->select('p.id_product', 'p.product_name', 'p.product_code', 'p.design_no', 'c.customer_name as owner',
                DB::raw("CONCAT(p.product_name, ' (', COALESCE(p.product_code, ''), ')') as label"));
    }

    public static function geoKey(object $r): string
    {
        return match (true) {
            $r->id_village !== null => 'VILLAGE:'.$r->id_village,
            $r->id_district !== null => 'DISTRICT:'.$r->id_district,
            default => 'CITY:'.$r->id_city,
        };
    }

    // "VILLAGE:123" -> baris wilayah (label + kode pos) untuk disimpan / ditampilkan.
    public static function geoFind(string $v): ?array
    {
        if (! preg_match('/^(VILLAGE|DISTRICT|CITY):(\d+)$/', $v, $m)) {
            return null;
        }
        $col = ['VILLAGE' => 'id_village', 'DISTRICT' => 'id_district', 'CITY' => 'id_city'][$m[1]];
        $q = DB::connection('tm2mst')->table('v_geo_full_address')->where($col, (int) $m[2]);
        if ($m[1] !== 'VILLAGE') {
            $q->whereNull('id_village');
        }
        if ($m[1] === 'CITY') {
            $q->whereNull('id_district');
        }
        $r = $q->first(['id_village', 'id_district', 'id_city', 'full_label', 'postal_code']);

        return $r ? (array) $r + ['area' => $v, 'level' => $m[1], 'id' => (int) $m[2]] : null;
    }
}
