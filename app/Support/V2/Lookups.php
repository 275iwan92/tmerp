<?php

namespace App\Support\V2;

use App\Support\V2\Data\Sales;
use Devlib\Lookup;
use Illuminate\Support\Facades\DB;

// Lookup = data master yang dipilih di layar v2 (bentuk sama dengan tabel tujuannya, lihat `source`).
// Masih memakai data contoh App\Support\V2\Data\Sales; tahap berikutnya dibaca dari database situs (ddt* / tm2*).
final class Lookups
{
    public static function register(): void
    {
        Lookup::define('customer', ['rows' => fn () => Sales::customerRows(), 'key' => 'id_customer', 'label' => 'customer_name',
            'columns' => ['customer_name' => 'Nama Customer', 'customer_code' => 'Kode'], 'source' => 'tm2mst.cust_customer']);
        Lookup::define('sales_person', ['rows' => fn () => Sales::salesPersons(), 'key' => 'sales_name', 'label' => 'sales_name',
            'columns' => ['sales_name' => 'Nama Sales', 'sales_type' => 'Jenis'], 'source' => 'tm2mst.sales_person']);
        Lookup::define('paper', ['rows' => fn () => Sales::masters()['materials'], 'key' => 'material_id', 'label' => 'material_name',
            'columns' => ['material_name' => 'Nama Bahan', 'material_code' => 'Kode', 'brand' => 'Brand'], 'fill' => ['brand' => 'brand', 'category' => 'category'],
            'source' => 'tm2mst.mat_raw (activate = 1, stat_confirm = 1)']);

        // Data besar -> Lookup remote (dicari ke server). Master Product: milik customer dokumen didahulukan (ctx customer_id).
        Lookup::define('product', ['key' => 'id_product', 'label' => 'label', 'source' => 'tm2mst.prod_product (activate = 1)',
            'columns' => ['product_name' => 'Product', 'product_code' => 'Kode', 'design_no' => 'No. Desain', 'owner' => 'Customer'],
            'search' => fn (string $q, array $ctx) => self::products()
                ->where(fn ($w) => $w->where('p.product_name', 'like', "%{$q}%")->orWhere('p.product_code', 'like', "%{$q}%")->orWhere('p.design_no', 'like', "%{$q}%"))
                ->orderByRaw('CASE WHEN p.customer_id = ? THEN 0 ELSE 1 END', [(int) ($ctx['customer_id'] ?? 0)])->orderBy('p.product_name')->limit(50)->get(),
            'find' => fn ($v) => self::products()->where('p.id_product', (int) $v)->first()]);

        // Bahan baku / item beli (PR, PO): dicari ke server -- mat_raw aktif & confirmed (sama dgn filter v1 pur.pr.entry).
        Lookup::define('material', ['key' => 'material_id', 'label' => 'label', 'source' => 'tm2mst.mat_raw (activate = 1, stat_confirm = 1)',
            'columns' => ['material_name' => 'Nama Item', 'material_code' => 'Kode', 'uom_purchase' => 'Satuan'],
            'search' => fn (string $q) => self::materials()->where(fn ($w) => $w->where('material_name', 'like', "%{$q}%")->orWhere('material_code', 'like', "%{$q}%"))
                ->orderBy('material_name')->limit(50)->get(),
            'find' => fn ($v) => self::materials()->where('material_id', (int) $v)->first()]);

        // Wilayah kirim = v_geo_full_address (5 level, sama dengan AddressPicker v1). Nilai = "LEVEL:id".
        Lookup::define('geo_area', ['key' => 'area', 'label' => 'full_label', 'source' => 'tm2mst.v_geo_full_address',
            'columns' => ['full_label' => 'Wilayah', 'postal_code' => 'Kode Pos'], 'fill' => ['ship_postal_code' => 'postal_code'],
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
