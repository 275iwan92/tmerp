<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;
use Devlib\Locale;

// Baris CPDEL (Customer Order Confirmed) yang belum berangkat: lewat janji, jatuh hari ini, dan 7 hari ke depan.
// Referensi: backorder / overdue order items (SAP), jobs late / due today (print MIS).
final class DeliveriesDue extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'deliveries_due', 'title' => 'Deliveries Due & Late', 'type' => 'list', 'dashboard' => 'SALES', 'module' => 'SALES',
            'description' => 'Planned deliveries that are past the promised date, due today, or due in the next 7 days.',
            'icon' => 'fa-truck-ramp-box', 'tone' => 'warn', 'phrases' => ['pengiriman jatuh tempo', 'pengiriman terlambat', 'kirim hari ini', 'deliveries due', 'late deliveries', 'order backlog'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $open = $n->deliveriesOpen();
        $late = $open->where('days', '<', 0);
        $today = $open->where('days', 0);
        $soon = $open->filter(fn ($d) => $d->days >= 1 && $d->days <= 7);
        $names = $n->customerNames($open->pluck('customer_id')->all());
        $due = fn (int $days) => $days < 0 ? [$this->say('Late :n days', ['n' => abs($days)]), 'danger'] : ($days === 0 ? [t('Today'), 'warn'] : [$this->say(':n days left', ['n' => $days]), 'neutral']);
        $rows = $late->concat($today)->concat($soon)->take(6)->map(fn ($d) => ['title' => trim($d->co_no.' · '.($names[$d->customer_id] ?? ''), ' ·'),
            'sub' => Locale::d($d->ship_date).' · '.Locale::number($d->qty, 0), 'value' => $due($d->days)[0], 'tone' => $due($d->days)[1]])->values()->all();

        return ['caption' => raw($this->say(':a late · :b today · :c in 7 days', ['a' => $late->count(), 'b' => $today->count(), 'c' => $soon->count()])),
            'source' => 'tm2sdi.co_order_delivery + co_order', 'data' => ['rows' => $rows, 'empty' => 'No delivery is due'],
            'open' => $this->open('SALES_CPDEL', 'Customer Plan Delivery', 'fa-solid fa-truck-fast')];
    }
}
