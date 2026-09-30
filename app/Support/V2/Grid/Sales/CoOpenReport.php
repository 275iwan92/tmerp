<?php

namespace App\Support\V2\Grid\Sales;

use Illuminate\Database\Eloquent\Builder;

// CO belum ditutup & tidak dibatalkan (v1: Order Qty Only - Status Open and Not Cancel).
class CoOpenReport extends CoReportGrid
{
    public function slug(): string
    {
        return 'co-rpt-open';
    }

    public function title(): string
    {
        return 'Order Qty Only - Status Open and Not Cancel';
    }

    protected function scope(Builder $q): Builder
    {
        return $q->whereHas('order', fn ($o) => $o->where('stat_cancel', false)->whereNotIn('doc_status', ['CLOSED', 'CANCELLED']));
    }
}
