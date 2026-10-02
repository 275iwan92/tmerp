<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Lima customer dengan order value (Confirmed / Closed) terbesar pada periode berjalan.
final class TopCustomers extends Widget
{
    private const TOP = 5;

    public function key(): string
    {
        return 'top_customers';
    }

    public function title(): string
    {
        return 'Top Customers';
    }

    public function description(): string
    {
        return 'The five customers with the highest confirmed order value in the period.';
    }

    public function type(): string
    {
        return 'chart';
    }

    public function group(): string
    {
        return 'Sales';
    }

    public function icon(): string
    {
        return 'fa-building-user';
    }

    public function module(): ?string
    {
        return 'SALES';
    }

    public function needsSalesPrice(): bool
    {
        return true;
    }

    public function phrases(): array
    {
        return ['customer terbesar', 'customer teratas', 'pelanggan terbesar', 'top customer', 'top customers', 'biggest customers'];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $by = $n->confirmed($n->from(), $n->to())->groupBy(fn ($o) => (int) $o->customer_id)
            ->map(fn ($set) => (float) $set->sum('value'))->sortDesc()->take(self::TOP);
        $names = $n->customerNames($by->keys()->all());

        return ['caption' => raw($n->rangeText().' · '.t('confirmed order value')), 'source' => 'tm2sdi.co_order + co_order_item · tm2mst.cust_customer',
            'data' => ['chart' => 'hbar', 'format' => 'money', 'height' => '240px', 'legend' => false,
                'labels' => $by->keys()->map(fn ($id) => $names[$id] ?? '#'.$id)->all(), 'series' => [['name' => t('Order value'), 'data' => $by->values()->all(), 'tone' => 'accent']]]];
    }
}
