<?php

namespace App\Support\V2\Grid\Prod;

use App\Models\Master\CustCustomer;
use App\Models\Prd\PrdWo;
use App\Models\Sdi\DoLine;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

// Aturan A.3 (user 2026-10-01): barang boleh dikirim lewat jalan pintas sesudah Post to Production, tapi hasil produksi
// tetap WAJIB di-input karena menjadi acuan KPI semua departemen. Laporan ini = baris DO (Confirmed / Shipped) dari jadwal
// kirim yang sudah di-Post, yang Work Order-nya belum punya satu pun catatan hasil produksi.
class NoOutputReport extends DbGrid
{
    private array $customers = [];

    private array $wos = [];

    public function slug(): string
    {
        return 'prod-no-output';
    }

    public function title(): string
    {
        return 'Shipped Without Production Output';
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
        return 'Report';
    }

    public function family(): string
    {
        return self::REPORT;
    }

    public function icon(): string
    {
        return 'fa-solid fa-triangle-exclamation';
    }

    public function guide(): ?string
    {
        return 'Goods already on a Delivery Order (quick-job shortcut after Post to Production) whose Work Order has no production output yet. Enter the output: it is the KPI basis for all departments.';
    }

    public function search(): string
    {
        return 'Search DO, CO, customer, product, WO...';
    }

    protected function query(): Builder
    {
        $withOutput = DB::connection('tm2prd')->table('prd_wo as w')
            ->join('prd_wo_operation as o', 'o.wo_id', '=', 'w.wo_id')
            ->join('prd_wo_operation_output as x', 'x.operation_id', '=', 'o.operation_id')
            ->whereNotNull('w.source_delivery_id')->distinct()->pluck('w.source_delivery_id');

        return DoLine::query()->with(['delivery', 'order', 'coDelivery'])
            ->whereHas('delivery', fn ($q) => $q->whereIn('status', ['CONFIRMED', 'SHIPPED']))
            ->whereHas('coDelivery', fn ($q) => $q->whereNotNull('posted_at'))
            ->whereNotIn('co_delivery_id', $withOutput)
            ->orderByDesc('id_line');
    }

    protected function prefetch(Collection $items): void
    {
        $this->customers = CustCustomer::whereIn('id_customer', $items->pluck('delivery.customer_id')->unique())->pluck('customer_name', 'id_customer')->all();
        $this->wos = PrdWo::whereIn('source_delivery_id', $items->pluck('co_delivery_id')->unique())->get(['source_delivery_id', 'wo_no'])
            ->groupBy('source_delivery_id')->map(fn ($g) => $g->pluck('wo_no')->join(', '))->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'do', 'label' => 'DO', 'type' => 'identity', 'code' => 'co'],
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'product', 'label' => 'Product'],
            ['key' => 'qty', 'label' => 'Qty shipped', 'type' => 'number', 'sum' => true],
            ['key' => 'do_date', 'label' => 'DO date', 'type' => 'date'],
            ['key' => 'do_status', 'label' => 'DO status', 'type' => 'badge', 'tones' => ['CONFIRMED' => 'info', 'SHIPPED' => 'success']],
            ['key' => 'wo', 'label' => 'Work Order'],
            ['key' => 'posted', 'label' => 'Posted to Production', 'type' => 'date'],
            ['key' => 'co', 'label' => 'CO', 'visible' => false],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'do' => $m->delivery?->do_no, 'co' => $m->order?->co_no, 'customer' => $this->customers[$m->delivery?->customer_id] ?? '—',
            'product' => $m->item_description, 'qty' => (float) $m->qty_do, 'do_date' => $m->delivery?->do_date?->toDateString(),
            'do_status' => $m->delivery?->status, 'wo' => $this->wos[$m->co_delivery_id] ?? '—',
            'posted' => $m->coDelivery?->posted_at?->toDateString(),
        ];
    }

    public function filters(): array
    {
        return ['customer' => 'Customer', 'do_status' => 'DO status'];
    }
}
