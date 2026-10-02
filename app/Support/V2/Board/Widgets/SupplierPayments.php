<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Uang yang dibayarkan ke supplier (alokasi pembayaran ke invoice) pada periode itu, dibanding periode lalu.
final class SupplierPayments extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'supplier_payments', 'title' => 'Supplier Payments Made', 'type' => 'kpi', 'dashboard' => 'AR_AP', 'module' => 'FINACC', 'size' => 3,
            'description' => 'Money paid to suppliers and applied to their invoices in the period.',
            'icon' => 'fa-money-bill-transfer', 'tone' => 'violet', 'phrases' => ['pembayaran supplier', 'bayar supplier', 'pembayaran hutang', 'supplier payments'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        [$prevFrom, $prevTo] = $n->range(1);

        return ['source' => 'tm2acc.fin_payment_alloc + fin_invoice', 'data' => ['value' => $n->paid('SUPPLIER', $n->from(), $n->to()),
            'previous' => $n->paid('SUPPLIER', $prevFrom, $prevTo), 'format' => 'money', 'good_when' => 'down', 'hint' => raw($n->rangeText())]];
    }
}
