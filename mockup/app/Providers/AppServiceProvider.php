<?php

namespace App\Providers;

use App\Support\Data\Sales;
use Devlib\Lookup;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \MockupParts\Src::$resolver = \App\Support\Src::of(...);

        // Lookup = data master yang dipilih di layar (bentuk sama dengan tabel tujuannya, lihat source).
        Lookup::define('customer', ['rows' => fn () => Sales::customerRows(), 'key' => 'id_customer', 'label' => 'customer_name',
            'columns' => ['customer_name' => 'Nama Customer', 'customer_code' => 'Kode'], 'source' => 'tm2mst.cust_customer']);
        Lookup::define('sales_person', ['rows' => fn () => Sales::salesPersons(), 'key' => 'sales_name', 'label' => 'sales_name',
            'columns' => ['sales_name' => 'Nama Sales', 'sales_type' => 'Jenis'], 'source' => 'tm2mst.sales_person']);
        Lookup::define('paper', ['rows' => fn () => Sales::masters()['materials'], 'key' => 'material_id', 'label' => 'material_name',
            'columns' => ['material_name' => 'Nama Bahan', 'material_code' => 'Kode', 'brand' => 'Brand'], 'fill' => ['brand' => 'brand', 'category' => 'category'],
            'source' => 'tm2mst.mat_raw (activate = 1, stat_confirm = 1)']);
    }
}
