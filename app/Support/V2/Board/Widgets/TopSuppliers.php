<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Lima supplier dengan nilai PO disetujui terbesar pada periode itu. Referensi: top vendors by amount (Odoo, Business Central, SAP).
final class TopSuppliers extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'top_suppliers', 'title' => 'Top Suppliers', 'type' => 'chart', 'dashboard' => 'PURCHASING', 'module' => 'PROC', 'size' => 6,
            'description' => 'The five suppliers with the highest approved Purchase Order value in the period.',
            'icon' => 'fa-industry', 'phrases' => ['supplier terbesar', 'supplier teratas', 'pemasok terbesar', 'top suppliers', 'top vendors'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $by = $n->purchaseOrders($n->from(), $n->to())->whereIn('status', Numbers::PO_APPROVED)->groupBy(fn ($o) => (int) $o->supplier_id)
            ->map(fn ($set) => (float) $set->sum('value'))->sortDesc()->take(5);
        $names = $n->supplierNames($by->keys()->all());

        return ['caption' => raw($n->rangeText().' · '.t('approved purchase orders')), 'source' => 'tm2pur.po_order · tm2mst.sup_supplier',
            'data' => ['chart' => 'hbar', 'format' => 'money', 'height' => '240px', 'legend' => false,
                'labels' => $by->keys()->map(fn ($id) => $names[$id] ?? t('No supplier'))->all(), 'series' => [['name' => t('Purchase value'), 'data' => $by->values()->all(), 'tone' => 'accent']]],
            'open' => $this->open('PROC_PO', 'Purchase Order', 'fa-solid fa-cart-shopping')];
    }
}
