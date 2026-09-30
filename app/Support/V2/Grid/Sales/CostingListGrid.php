<?php

namespace App\Support\V2\Grid\Sales;

use App\Models\Master\CustCustomer;
use App\Models\Sdi\IqpCosting;
use App\Models\Sdi\IqpInquiry;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Pro-Cost worklist (v1: pages::inquiry.costing-list, zona beku — hanya dibaca, buka = Costing Hub v1 apa adanya).
class CostingListGrid extends DbGrid
{
    public function slug(): string
    {
        return 'sales-procost';
    }

    public function title(): string
    {
        return 'Pro-Cost';
    }

    public function module(): string
    {
        return 'COSTING';
    }

    public function moduleLabel(): string
    {
        return 'Sales';
    }

    public function family(): string
    {
        return self::TRANSACTIONAL;
    }

    protected function v1Route(): ?string
    {
        return 'inquiry.costing.list';
    }

    protected function canCreate(): bool
    {
        return false;
    }

    public function openUrl(): ?string
    {
        return $this->routeUrl('inquiry.costing', 'inquiry');
    }

    protected function models(): array
    {
        return [IqpInquiry::class, IqpCosting::class, CustCustomer::class];
    }

    public function search(): string
    {
        return 'Cari no. inkuiri, produk, customer...';
    }

    protected function query(): Builder
    {
        return IqpInquiry::query()->with(['customer', 'costing'])->whereNotIn('sub_state', ['DRAFT', 'CANCELLED', 'LOST'])
            ->whereHas('parts')->orderByDesc('id_inquiry');
    }

    public function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'Inkuiri'],
            ['key' => 'name', 'label' => 'Produk', 'type' => 'identity', 'code' => 'customer', 'avatar' => false],
            ['key' => 'type', 'label' => 'Type', 'type' => 'badge', 'tones' => ['NEW' => 'info', 'REVISE' => 'warn', 'RECALC' => 'accent']],
            ['key' => 'state', 'label' => 'Status Inquiry'],
            ['key' => 'costing', 'label' => 'Status Costing', 'type' => 'badge', 'tones' => ['BELUM' => 'neutral', 'DRAFT' => 'warn', 'PENDING_APPROVAL' => 'info', 'PUBLISHED' => 'success']],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'no' => $m->inquiry_no, 'name' => $m->product_draft_name,
            'customer' => $m->is_prospect ? $m->prospect_name : $m->customer?->customer_name,
            'type' => $m->inquiry_type, 'state' => $m->sub_state, 'costing' => $m->costing?->doc_status ?? 'BELUM',
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Not calculated', 'where' => ['costing' => 'BELUM'], 'tone' => 'neutral'],
            ['label' => 'Waiting for approval', 'where' => ['costing' => 'PENDING_APPROVAL'], 'tone' => 'info'],
            ['label' => 'Published', 'where' => ['costing' => 'PUBLISHED'], 'tone' => 'success'],
        ];
    }

    public function filters(): array
    {
        return ['type' => 'Jenis', 'state' => 'Status Inquiry', 'costing' => 'Status Costing'];
    }
}
