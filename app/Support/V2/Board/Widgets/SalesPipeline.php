<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Tahap penjualan periode berjalan dalam jumlah dokumen: Inquiry masuk -> harga terbit (Pro-Cost) -> Quotation terbit -> Customer Order.
// Batang mendatar, bukan corong: Customer Order boleh masuk langsung tanpa Quotation (INSTANT), jadi jumlahnya tidak selalu mengecil.
final class SalesPipeline extends Widget
{
    public function key(): string
    {
        return 'sales_pipeline';
    }

    public function title(): string
    {
        return 'Sales Pipeline';
    }

    public function description(): string
    {
        return 'Documents of the period at each sales step: Inquiry, price published, Quotation, Customer Order.';
    }

    public function type(): string
    {
        return 'chart';
    }

    public function group(): string
    {
        return 'Sales';
    }

    public function icon(): string
    {
        return 'fa-filter';
    }

    public function size(): int
    {
        return 6;
    }

    public function module(): ?string
    {
        return 'SALES';
    }

    public function phrases(): array
    {
        return ['pipeline penjualan', 'corong penjualan', 'funnel penjualan', 'sales pipeline', 'sales funnel'];
    }

    public function dashboard(): ?string
    {
        return 'SALES';
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $p = $n->pipeline($n->from(), $n->to());

        return ['caption' => raw(t('Documents dated').' '.$n->rangeText()), 'source' => 'tm2sdi.iqp_inquiry · iqp_costing (published) · quo_quotation · co_order',
            'data' => ['chart' => 'hbar', 'format' => 'number', 'height' => '260px', 'legend' => false,
                'labels' => [t('Inquiries'), t('Price published (Pro-Cost)'), t('Quotations issued'), t('Customer Orders')],
                'series' => [['name' => t('Documents'), 'data' => [$p['inquiries'], $p['priced'], $p['quoted'], $p['ordered']], 'tone' => 'accent']]]];
    }
}
