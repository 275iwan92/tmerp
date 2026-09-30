<?php

namespace App\Support\V2\Grid\Prod;

use App\Models\Master\ProdProduct;
use App\Models\Prd\PrdWo;
use App\Models\Sdi\CoOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Work Order (v1: pages::production.wo-list -> wo-entry). Buka = WO Detail v1, New = Generate dari Plan Delivery v1.
class WorkOrderGrid extends ProdGrid
{
    private const STATUS_TONES = ['DRAFT' => 'neutral', 'SIMULATED' => 'info', 'SCHEDULED' => 'info', 'IN_PROGRESS' => 'warn',
        'ON_HOLD' => 'danger', 'COMPLETED' => 'success', 'CANCELLED' => 'danger'];

    private array $products = [];

    private array $orders = [];

    public function slug(): string
    {
        return 'prod-work-order';
    }

    public function title(): string
    {
        return 'Work Order';
    }

    public function family(): string
    {
        return self::TRANSACTIONAL;
    }

    public function group(): string
    {
        return 'Planning';
    }

    public function icon(): string
    {
        return 'fa-solid fa-clipboard-list';
    }

    public function openUrl(): ?string
    {
        return $this->routeUrl('production.wo.entry', 'wo');
    }

    public function newUrl(): ?string
    {
        return route('production.wo.new');
    }

    protected function models(): array
    {
        return [PrdWo::class, ProdProduct::class, CoOrder::class];
    }

    public function search(): string
    {
        return 'Search WO no, product, CO...';
    }

    protected function query(): Builder
    {
        return PrdWo::query()->orderByDesc('wo_id');
    }

    protected function prefetch(Collection $items): void
    {
        $this->products = ProdProduct::query()->whereKey($items->pluck('product_id')->filter()->unique()->all())
            ->get(['id_product', 'product_code', 'product_name'])->keyBy('id_product')->all();
        $this->orders = CoOrder::query()->whereKey($items->pluck('source_co_id')->filter()->unique()->all())
            ->pluck('co_no', 'id_order')->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'wo_no', 'label' => 'WO No'],
            ['key' => 'product', 'label' => 'Product', 'type' => 'identity', 'code' => 'product_code', 'avatar' => false],
            ['key' => 'part', 'label' => 'Part'],
            ['key' => 'co_no', 'label' => 'Customer Order'],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
            ['key' => 'due', 'label' => 'Due Date', 'type' => 'date'],
            ['key' => 'hot', 'label' => 'Hot Job', 'type' => 'badge', 'tones' => ['Hot' => 'danger']],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => self::STATUS_TONES],
        ];
    }

    protected function row(Model $m): array
    {
        $p = $this->products[$m->product_id] ?? null;

        return [
            'wo_no' => $m->wo_no, 'product' => $p?->product_name, 'product_code' => $p?->product_code, 'part' => $m->part_code,
            'co_no' => $this->orders[$m->source_co_id] ?? null, 'qty' => (float) $m->qty, 'due' => $m->due_date?->toDateString(),
            'hot' => $m->is_hot_job ? 'Hot' : null, 'status' => $m->wo_status,
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Planning', 'where' => ['status' => ['DRAFT', 'SIMULATED', 'SCHEDULED']], 'tone' => 'info'],
            ['label' => 'In Progress', 'where' => ['status' => 'IN_PROGRESS'], 'tone' => 'warn'],
            ['label' => 'On Hold', 'where' => ['status' => 'ON_HOLD'], 'tone' => 'danger'],
            ['label' => 'Completed', 'where' => ['status' => 'COMPLETED'], 'tone' => 'success'],
        ];
    }

    public function filters(): array
    {
        return ['status' => 'Status', 'hot' => 'Hot Job'];
    }
}
