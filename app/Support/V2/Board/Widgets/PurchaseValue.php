<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Nilai Purchase Order periode berjalan. Angka besar = PO yang sudah disetujui; baris bawah = yang menunggu approval dan Draft.
// Referensi: purchased value / spend (Odoo, SAP, Business Central).
final class PurchaseValue extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'purchase_value', 'title' => 'Purchase Value', 'type' => 'kpi', 'dashboard' => 'PURCHASING', 'module' => 'PROC',
            'description' => 'Approved Purchase Orders of the period against the period before, with the amount still waiting for approval.',
            'icon' => 'fa-cart-shopping', 'phrases' => ['nilai pembelian', 'pembelian bulan ini', 'total po', 'purchase value', 'purchase spend'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        [$prevFrom, $prevTo] = $n->range(1);
        $orders = $n->purchaseOrders($n->from(), $n->to());
        $sum = fn ($set, array $status) => (float) $set->whereIn('status', $status)->sum('value');

        return ['source' => 'tm2pur.po_order', 'data' => ['value' => $sum($orders, Numbers::PO_APPROVED), 'previous' => $sum($n->purchaseOrders($prevFrom, $prevTo), Numbers::PO_APPROVED),
            'format' => 'money', 'hint' => raw($this->say(':n purchase orders', ['n' => $orders->count()])),
            'note' => raw($this->say('Waiting for approval :a · Draft :b', ['a' => $this->money($sum($orders, ['SUBMITTED'])), 'b' => $this->money($sum($orders, ['DRAFT']))]))]];
    }
}
