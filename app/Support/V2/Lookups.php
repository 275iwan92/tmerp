<?php

namespace App\Support\V2;

use App\Support\V2\Data\Sales;
use Devlib\Lookup;

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
    }
}
