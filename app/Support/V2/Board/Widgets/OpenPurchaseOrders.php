<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// PO yang sudah disetujui / dikirim ke supplier dan belum diterima penuh, per hari ini; berapa yang lewat tanggal kirimnya.
// Referensi: outstanding purchase orders (Business Central, SAP).
final class OpenPurchaseOrders extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'open_purchase_orders', 'title' => 'Open Purchase Orders', 'type' => 'kpi', 'dashboard' => 'PURCHASING', 'module' => 'PROC',
            'description' => 'Purchase Orders approved or sent to the supplier that are not fully received yet, and how many are past the delivery date.',
            'icon' => 'fa-dolly', 'tone' => 'warn', 'phrases' => ['po terbuka', 'po belum diterima', 'po outstanding', 'open purchase orders', 'outstanding po'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $open = $n->purchaseOrdersOpen();
        $late = $open->filter(fn ($o) => $o->i_delivery_date && substr((string) $o->i_delivery_date, 0, 10) < $n->now->toDateString())->count();

        return ['source' => 'tm2pur.po_order', 'data' => ['value' => $open->count(), 'format' => 'number', 'hint' => raw($this->say('Value :v', ['v' => $this->money((float) $open->sum('c_grand_total'))])),
            'note' => raw($this->say(':n past the delivery date', ['n' => $late]))]];
    }
}
