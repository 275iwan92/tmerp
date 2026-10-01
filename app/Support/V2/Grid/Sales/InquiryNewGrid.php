<?php

namespace App\Support\V2\Grid\Sales;

// Inquiry > New: meja Sales untuk inquiry jenis NEW. + New Record langsung membuka form Inquiry New (tanpa pop-up pilih jenis).
class InquiryNewGrid extends InquiryDeskGrid
{
    public function slug(): string
    {
        return 'inquiry-list-new';
    }

    public function title(): string
    {
        return 'Inquiry New';
    }

    public function module(): string
    {
        return 'SALES';
    }

    public function icon(): string
    {
        return 'fa-solid fa-file-circle-plus';
    }

    protected function type(): string
    {
        return 'NEW';
    }

    protected function desk(): string
    {
        return 'SALES';
    }

    protected function rowUrl(array $r): string
    {
        return route('v2.screen', 'inquiry-new').'?id='.$r['id'].($r['sub_state'] === 'DRAFT' ? '' : '&view=1');
    }

    public function newUrl(): ?string
    {
        return route('v2.screen', 'inquiry-new');
    }
}
