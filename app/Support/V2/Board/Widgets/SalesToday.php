<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;
use Devlib\Components\Dashboard;

// Nilai Customer Order yang masuk hari ini, semua status kecuali Cancelled (definisi sama dengan jawaban asisten "sales today").
final class SalesToday extends Widget
{
    public function key(): string
    {
        return 'sales_today';
    }

    public function title(): string
    {
        return 'Sales Today';
    }

    public function description(): string
    {
        return 'Value of the Customer Orders entered today: Draft and Confirmed together.';
    }

    public function type(): string
    {
        return 'kpi';
    }

    public function group(): string
    {
        return 'Sales';
    }

    public function icon(): string
    {
        return 'fa-calendar-day';
    }

    public function tone(): string
    {
        return 'violet';
    }

    public function size(): int
    {
        return 3;
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
        return ['sales hari ini', 'penjualan hari ini', 'omzet hari ini', 'order hari ini', 'sales today', 'orders today'];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $today = $n->orders($n->now->startOfDay(), $n->now->startOfDay());
        $yesterday = $n->orders($n->now->subDay()->startOfDay(), $n->now->subDay()->startOfDay());
        $hint = str_replace([':n', ':v'], [(string) $today->count(), Dashboard::fmt($today->whereIn('doc_status', Numbers::CONFIRMED)->sum('value'), 'money')],
            t(':n documents · confirmed :v'));

        return ['source' => 'tm2sdi.co_order + co_order_item', 'data' => ['value' => (float) $today->sum('value'), 'previous' => (float) $yesterday->sum('value'),
            'format' => 'money', 'hint' => raw($hint)]];
    }
}
