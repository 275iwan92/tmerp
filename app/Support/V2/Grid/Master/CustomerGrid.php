<?php

namespace App\Support\V2\Grid\Master;

use App\Models\Master\CustCustomer;
use App\Models\Master\FinPaymentTerm;
use App\Models\Sdi\CoOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/*
 * REMARK
 * Tujuan       : Grid Master Customer: daftar customer dengan grup, tipe, industri, batas kredit, termin bayar, status persetujuan dan jumlah Customer Order; Open dan + New Record membuka form v2 md-customer-form.
 * Sumber hulu  : tm2mst.cust_customer + fin_payment_term; jumlah Customer Order dari tm2sdi.co_order (query terpisah).
 * Input        : Pencarian, statistik, filter Type / Industry / Payment Term / Approval.
 * Output       : Baris grid; tombol aktif / nonaktif membalik cust_customer.activate.
 * Dipakai oleh : Menu MD_CUST dan PRE_MD_CUST (v2:md-customer), layar md-customer-form (kembali ke daftar).
 * Status       : Aktif. Halaman v1 pages::master.customer sudah dipindah ke tmerp_unused (aturan D.25).
 */
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

    protected function canCreate(): bool
    {
        return true;
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'md-customer-form').'?id={id}';
    }

    public function newUrl(): ?string
    {
        return route('v2.screen', 'md-customer-form').'?new=1';
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
