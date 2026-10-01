<?php

namespace App\Support\V2\Grid\Master;

use App\Models\Master\CustCustomer;
use App\Models\Master\FinPaymentTerm;
use App\Models\Sdi\CoOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Master Customer (v1: pages::master.customer).
class CustomerGrid extends MasterDataGrid
{
    private array $orders = [];

    public function slug(): string
    {
        return 'md-customer';
    }

    public function title(): string
    {
        return 'Master Customer';
    }

    protected function v1Route(): ?string
    {
        return 'master.customer';
    }

    protected function v1Component(): ?string
    {
        return 'pages::master.customer';
    }

    protected function models(): array
    {
        return [CustCustomer::class, FinPaymentTerm::class, CoOrder::class];
    }

    public function search(): string
    {
        return 'Search name, code, group, industry...';
    }

    protected function query(): Builder
    {
        return CustCustomer::query()->with('paymentTerm')->orderBy('customer_name');
    }

    protected function prefetch(Collection $items): void
    {
        $this->orders = CoOrder::query()->whereIn('customer_id', $items->modelKeys())->groupBy('customer_id')
            ->selectRaw('customer_id, COUNT(*) n')->pluck('n', 'customer_id')->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'photo', 'label' => '', 'type' => 'image', 'fallback' => 'name', 'sortable' => false],
            ['key' => 'name', 'label' => 'Customer', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'group', 'label' => 'Group'],
            ['key' => 'type', 'label' => 'Type', 'type' => 'badge', 'tones' => [t('Local') => 'info', 'Import' => 'accent', t('Prospect') => 'warn']],
            ['key' => 'industry', 'label' => 'Industry'],
            ['key' => 'credit', 'label' => 'Credit Limit', 'type' => 'money'],
            ['key' => 'term', 'label' => 'Payment Term'],
            ['key' => 'approval', 'label' => 'Approval', 'type' => 'badge', 'tones' => ['APPROVED' => 'success', 'DRAFT' => 'neutral', 'BLOCKED' => 'danger']],
            ['key' => 'orders', 'label' => 'Customer Orders', 'type' => 'link', 'format' => 'number'],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'photo' => null, 'name' => $m->customer_name, 'code' => $m->customer_code, 'group' => $m->parent_group,
            'type' => $m->is_prospect ? t('Prospect') : ($m->customer_type === 'import' ? 'Import' : t('Local')),
            'industry' => $m->industry_category, 'credit' => (float) $m->credit_limit, 'term' => $m->paymentTerm?->term_label,
            'approval' => $m->is_blacklisted ? 'BLOCKED' : ($m->approval_status ?? 'DRAFT'), 'orders' => (int) ($this->orders[$m->getKey()] ?? 0),
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Active', 'where' => ['active' => true], 'tone' => 'success'],
            ['label' => 'Draft', 'where' => ['approval' => 'DRAFT'], 'tone' => 'warn'],
            ['label' => 'Blocked', 'where' => ['approval' => 'BLOCKED'], 'tone' => 'danger'],
            ['label' => 'Inactive', 'where' => ['active' => false], 'tone' => 'neutral'],
        ];
    }

    public function filters(): array
    {
        return ['type' => 'Type', 'industry' => 'Industry', 'term' => 'Payment Term', 'approval' => 'Approval'];
    }
}
