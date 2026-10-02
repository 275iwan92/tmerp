<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Jumlah Customer Order periode berjalan per status (istilah status selalu English). Cancelled tidak ikut.
final class OrdersByStatus extends Widget
{
    private const STATUSES = ['DRAFT' => 'Draft', 'SUBMITTED' => 'Waiting for approval', 'CONFIRMED' => 'Confirmed', 'CLOSED' => 'Closed'];

    public function key(): string
    {
        return 'orders_by_status';
    }

    public function title(): string
    {
        return 'Customer Orders by Status';
    }

    public function description(): string
    {
        return 'Customer Orders of the period split by status: Draft, Waiting for approval, Confirmed, Closed.';
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
        return 'fa-chart-pie';
    }

    public function module(): ?string
    {
        return 'SALES';
    }

    public function phrases(): array
    {
        return ['status order', 'order per status', 'customer order per status', 'orders by status', 'order status'];
    }

    public function dashboard(): ?string
    {
        return 'SALES';
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $by = $n->orders($n->from(), $n->to())->countBy('doc_status');

        return ['caption' => raw($n->rangeText().' · '.t('documents')), 'source' => 'tm2sdi.co_order', 'open' => $this->open('SALES_SO', 'Customer Order', 'fa-solid fa-file-signature'),
            'data' => ['chart' => 'donut', 'format' => 'number', 'height' => '240px', 'labels' => array_values(self::STATUSES),
                'series' => [['name' => t('Documents'), 'data' => array_map(fn (string $s) => (int) ($by[$s] ?? 0), array_keys(self::STATUSES))]]]];
    }
}
