<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Uang yang diterima dari customer (alokasi pembayaran ke invoice) pada periode itu, dibanding periode lalu.
// Referensi: cash collection tracker (SAP).
final class Collections extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'collections', 'title' => 'Customer Payments Received', 'type' => 'kpi', 'dashboard' => 'AR_AP', 'module' => 'FINACC', 'size' => 3,
            'description' => 'Money received from customers and applied to their invoices in the period.',
            'icon' => 'fa-hand-holding-dollar', 'tone' => 'success', 'phrases' => ['penerimaan customer', 'pembayaran customer', 'penagihan', 'collections', 'customer payments'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        [$prevFrom, $prevTo] = $n->range(1);

        return ['source' => 'tm2acc.fin_payment_alloc + fin_invoice', 'data' => ['value' => $n->paid('CUSTOMER', $n->from(), $n->to()),
            'previous' => $n->paid('CUSTOMER', $prevFrom, $prevTo), 'format' => 'money', 'hint' => raw($n->rangeText())]];
    }
}
