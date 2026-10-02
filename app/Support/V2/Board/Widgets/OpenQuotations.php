<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Quotation yang sudah terbit dan belum menjadi Customer Order, per hari ini. Referensi: open quotes (Business Central, NetSuite, PrintVis).
final class OpenQuotations extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'open_quotations', 'title' => 'Open Quotations', 'type' => 'kpi', 'dashboard' => 'SALES', 'module' => 'SALES', 'size' => 3, 'price' => true,
            'description' => 'Value of quotations issued to customers that have not become a Customer Order yet, as of today.',
            'icon' => 'fa-file-signature', 'tone' => 'violet', 'phrases' => ['quotation terbuka', 'penawaran terbuka', 'quotation belum order', 'open quotations', 'open quotes'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $open = $n->openQuotations();
        $expired = $open->filter(fn ($q) => $q->valid_until && $q->valid_until < $n->now->toDateString())->count();

        return ['source' => 'tm2sdi.quo_quotation + quo_quotation_item', 'data' => ['value' => (float) $open->sum('value'), 'format' => 'money',
            'hint' => raw($this->say(':n quotations wait for the customer', ['n' => $open->count()])),
            'note' => $expired ? raw($this->say(':n past the valid-until date', ['n' => $expired])) : null]];
    }
}
