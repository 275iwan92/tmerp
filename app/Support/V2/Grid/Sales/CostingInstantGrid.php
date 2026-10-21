<?php

namespace App\Support\V2\Grid\Sales;

use App\Models\Master\CustCustomer;
use App\Models\Sdi\IqpCosting;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Pro-Cost > Instant (daftar v1 pages::inquiry.costing-instant-list diarsipkan T10; buka = form Instant v2 procost-instant).
// Kalkulator HPP standalone: iqp_costing mode INSTANT tanpa Inquiry Product. + New Record membuat draft kosong lalu membuka formnya
// (rute v2.procost-instant.new). Reverse (jadi mode Integrated) tetap di dalam form v1.
class CostingInstantGrid extends DbGrid
{
    public function slug(): string
    {
        return 'sales-procost-instant';
    }

    public function title(): string
    {
        return 'Pro-Cost Instant';
    }

    public function module(): string
    {
        return 'COSTING';
    }

    public function moduleLabel(): string
    {
        return 'Sales';
    }

    public function group(): string
    {
        return 'Pro-Cost';
    }

    public function icon(): string
    {
        return 'fa-solid fa-bolt';
    }

    public function family(): string
    {
        return self::TRANSACTIONAL;
    }

    public function guide(): ?string
    {
        return 'Standalone cost calculator, without an Inquiry Product. Sure of the numbers? Use Reverse inside the form to create the Inquiry + Pro-Dev automatically and move to Integrated mode.';
    }

    public function search(): string
    {
        return 'Search document no., product, customer...';
    }

    protected function query(): Builder
    {
        return IqpCosting::query()->with('customer')->where('mode', IqpCosting::MODE_INSTANT)->orderByDesc('id_costing');
    }

    protected function models(): array
    {
        return [IqpCosting::class, CustCustomer::class];
    }

    protected function row(Model $m): array
    {
        return ['doc_no' => $m->doc_no ?: 'Draft #'.$m->id_costing, 'customer' => $m->customer?->customer_name ?? '—', 'product' => $m->product_name_draft ?: '—',
            'status' => $m->doc_status, 'reversed' => $m->reversed_at ? 'Reversed' : '—'];
    }

    public function columns(): array
    {
        return [
            ['key' => 'doc_no', 'label' => 'Document / Customer', 'type' => 'doc', 'party' => 'customer'],
            ['key' => 'product', 'label' => 'Product'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['DRAFT' => 'neutral', 'CALCULATED' => 'info', 'PENDING_APPROVAL' => 'warn', 'PUBLISHED' => 'success', 'FAILED' => 'danger']],
            ['key' => 'reversed', 'label' => 'Reverse', 'type' => 'badge', 'tones' => ['Reversed' => 'violet', '—' => 'neutral']],
        ];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'Draft', 'where' => ['status' => 'DRAFT'], 'tone' => 'warn'],
            ['label' => 'Calculated', 'where' => ['status' => 'CALCULATED'], 'tone' => 'info'], ['label' => 'Reversed', 'where' => ['reversed' => 'Reversed'], 'tone' => 'success']];
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'procost-instant').'?id={id}';
    }

    public function newUrl(): ?string
    {
        return route('v2.procost-instant.new');
    }
}
