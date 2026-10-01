<?php

namespace App\Support\V2\Grid\Planning;

use App\Models\Prd\PrdDemandForecast;
use App\Support\V2\Data\MaterialPlanning;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

// Demand Forecast (MPS baru): perkiraan order per customer x product x bulan. CO customer + product di bulan yang sama
// memakan forecast; sisanya ikut dihitung Material Planning sebagai kebutuhan rencana (belum memesan stok).
class DemandForecastLookup extends DbLookup
{
    public function slug(): string
    {
        return 'demand-forecast';
    }

    public function title(): string
    {
        return 'Demand Forecast (MPS)';
    }

    public function module(): string
    {
        return 'PROD';
    }

    public function moduleLabel(): string
    {
        return 'Production';
    }

    public function group(): string
    {
        return 'Planning';
    }

    public function icon(): string
    {
        return 'fa-solid fa-calendar-week';
    }

    public function guide(): ?string
    {
        // Maksimal 255 huruf supaya bisa diterjemahkan lewat kamus.
        return 'Expected orders per customer, product and month (first day of the month). A Customer Order for the same customer, product and month uses up the forecast; '
            .'only the remainder is planned. Forecast never reserves stock: it shows what to buy in time.';
    }

    protected function query(): Builder
    {
        return PrdDemandForecast::query()->orderBy('period_start')->orderBy('customer_id');
    }

    public function columns(): array
    {
        // Product yang pernah dipesan (CO) + yang sudah ada di forecast: daftar pilih tetap pendek.
        $ids = DB::connection('tm2sdi')->table('co_order_item')->whereNotNull('product_id')->distinct()->pluck('product_id')
            ->merge(PrdDemandForecast::query()->pluck('product_id'))->unique();
        $products = DB::connection('tm2mst')->table('prod_product')->whereIn('id_product', $ids)->orderBy('product_name')
            ->get(['id_product', 'product_code', 'product_name'])->mapWithKeys(fn ($p) => [$p->id_product => $p->product_name.' · '.$p->product_code])->all();
        $customers = DB::connection('tm2mst')->table('cust_customer')->where('activate', 1)->orderBy('customer_name')->pluck('customer_name', 'id_customer')->all();

        return [
            ['key' => 'customer_id', 'label' => 'Customer', 'type' => 'select', 'options' => $customers, 'required' => true, 'width' => '260px'],
            ['key' => 'product_id', 'label' => 'Product', 'type' => 'select', 'options' => $products, 'required' => true, 'width' => '320px'],
            ['key' => 'period_start', 'label' => 'Month (first day)', 'type' => 'date', 'required' => true, 'width' => '150px'],
            ['key' => 'qty_forecast', 'label' => 'Forecast Qty', 'type' => 'number', 'required' => true, 'decimals' => 0, 'width' => '130px'],
            ['key' => 'notes', 'label' => 'Notes', 'width' => '240px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '90px'],
        ];
    }

    protected function row(Model $m): array
    {
        return ['period_start' => $m->period_start?->toDateString()] + parent::row($m);
    }

    protected function rules(): array
    {
        return ['qty_forecast' => ['gt:0'], 'period_start' => ['date']];
    }

    public function save(array $changed): array
    {
        // Forecast selalu disimpan di hari pertama bulan (satu baris per customer x product x bulan).
        $changed = array_map(fn ($r) => ! empty($r['period_start']) && strtotime((string) $r['period_start'])
            ? ['period_start' => date('Y-m-01', strtotime((string) $r['period_start']))] + $r : $r, $changed);
        $rows = parent::save($changed);
        MaterialPlanning::changed();

        return $rows;
    }
}
