<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;
use Devlib\Locale;

// Penerimaan barang yang menunggu QC, atau sudah diposting tetapi belum diserahkan ke pemakai.
final class ReceiptsWaiting extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'receipts_waiting', 'title' => 'Goods Receipts in Progress', 'type' => 'list', 'dashboard' => 'INVENTORY', 'module' => 'INV', 'size' => 6,
            'description' => 'Goods receipts that wait for the quality check, or are posted but not handed over yet.',
            'icon' => 'fa-truck-ramp-box', 'tone' => 'warn', 'phrases' => ['penerimaan barang', 'barang datang', 'penerimaan menunggu', 'goods receipts', 'receipts waiting'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $all = $n->receiptsWaiting();
        $names = $n->supplierNames($all->pluck('i_supplier_id')->all());
        $rows = $all->take(6)->map(fn ($r) => ['title' => trim(($r->doc_no ?: '#'.$r->pv_id).' · '.($names[$r->i_supplier_id] ?? ''), ' ·'), 'sub' => (string) Locale::d($r->i_doc_date),
            'value' => $r->status === 'QC_PENDING' ? t('Waiting for QC') : t('Not handed over'), 'tone' => $r->status === 'QC_PENDING' ? 'warn' : 'neutral'])->values()->all();

        return ['caption' => $all->count() ? raw($this->say(':n receipts', ['n' => $all->count()])) : null, 'source' => 'tm2pur.pv_receipt',
            'data' => ['rows' => $rows, 'empty' => 'No goods receipt is in progress']];
    }
}
