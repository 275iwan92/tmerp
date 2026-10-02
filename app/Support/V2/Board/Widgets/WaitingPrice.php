<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Inquiry yang sudah di-Confirm Sales dan masih menunggu Pro-Dev atau Pro-Cost, yang paling lama menunggu di atas.
final class WaitingPrice extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'waiting_price', 'title' => 'Waiting for Price', 'type' => 'list', 'dashboard' => 'SALES', 'module' => 'SALES',
            'description' => 'Inquiries confirmed by Sales that still wait for Pro-Dev or Pro-Cost, longest wait first.',
            'icon' => 'fa-hourglass-half', 'tone' => 'warn', 'phrases' => ['menunggu harga', 'inquiry menunggu', 'antrian prodev', 'antrian procost', 'waiting for price', 'inquiry queue'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $w = $n->waitingPrice();
        $names = $n->customerNames($w['prodev']->concat($w['procost'])->pluck('customer_id')->all());
        $row = fn ($i, string $stage) => ['title' => trim($i->inquiry_no.' · '.($names[$i->customer_id] ?? ''), ' ·'), 'sub' => t($stage), 'days' => $i->days,
            'value' => $this->say(':n days', ['n' => $i->days]), 'tone' => $i->days >= 3 ? 'danger' : 'warn'];
        $rows = $w['prodev']->map(fn ($i) => $row($i, 'Waiting for Pro-Dev'))->concat($w['procost']->map(fn ($i) => $row($i, 'Waiting for Pro-Cost')))
            ->sortByDesc('days')->take(6)->map(fn ($r) => collect($r)->except('days')->all())->values()->all();

        return ['caption' => raw($this->say('Pro-Dev :a · Pro-Cost :b', ['a' => $w['prodev']->count(), 'b' => $w['procost']->count()])), 'source' => 'tm2sdi.iqp_inquiry',
            'data' => ['rows' => $rows, 'empty' => 'No inquiry waits for a price'], 'open' => $this->open('SALES_INQ', 'All Inquiries', 'fa-solid fa-inbox')];
    }
}
