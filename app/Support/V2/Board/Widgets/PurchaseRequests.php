<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Purchase Requisition yang menunggu approval, per hari ini; baris bawah = yang sudah disetujui dan yang masih Draft.
// Referensi: open purchase requisitions (SAP).
final class PurchaseRequests extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'purchase_requests', 'title' => 'Purchase Requisitions Waiting', 'type' => 'kpi', 'dashboard' => 'PURCHASING', 'module' => 'PROC',
            'description' => 'Purchase Requisitions that wait for approval, as of today.',
            'icon' => 'fa-clipboard-list', 'tone' => 'teal', 'phrases' => ['pr menunggu', 'permintaan pembelian', 'pr belum disetujui', 'purchase requisitions', 'pr waiting'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $by = $n->requisitions();

        return ['source' => 'tm2pur.pr_requisition', 'data' => ['value' => (int) ($by['SUBMITTED'] ?? 0), 'format' => 'number', 'hint' => 'Waiting for approval',
            'note' => raw($this->say('Approved :a · Draft :b', ['a' => (int) ($by['APPROVED'] ?? 0), 'b' => (int) ($by['DRAFT'] ?? 0)]))]];
    }
}
