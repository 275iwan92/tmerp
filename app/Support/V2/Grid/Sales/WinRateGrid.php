<?php

namespace App\Support\V2\Grid\Sales;

use App\Models\Sdi\IqpInquiry;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Laporan Win-Rate Sales (v1: pages::sales.win-rate): inquiry yang sudah selesai (CONVERTED = menang, LOST = kalah).
class WinRateGrid extends DbGrid
{
    public function slug(): string
    {
        return 'sales-win-rate';
    }

    public function title(): string
    {
        return 'Win-Rate Sales';
    }

    public function module(): string
    {
        return 'SALES';
    }

    public function moduleLabel(): string
    {
        return 'Sales';
    }

    public function group(): string
    {
        return 'Inquiry & Quotation › Report';
    }

    public function family(): string
    {
        return self::REPORT;
    }

    public function search(): string
    {
        return 'Cari inquiry, PIC, alasan...';
    }

    protected function query(): Builder
    {
        return IqpInquiry::query()->whereIn('sub_state', ['CONVERTED', 'LOST'])->orderByDesc('inquiry_date');
    }

    public function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'Inquiry'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'product', 'label' => 'Produk'],
            ['key' => 'pic', 'label' => 'PIC Sales'],
            ['key' => 'result', 'label' => 'Hasil', 'type' => 'badge', 'tones' => ['Menang' => 'success', 'Kalah' => 'danger']],
            ['key' => 'reason', 'label' => 'Alasan Kalah'],
        ];
    }

    protected function row(Model $m): array
    {
        return ['no' => $m->inquiry_no, 'date' => $m->inquiry_date?->toDateString(), 'product' => $m->product_draft_name,
            'pic' => $m->pic_sales ?: '(tanpa PIC)', 'result' => $m->sub_state === 'CONVERTED' ? 'Menang' : 'Kalah',
            'reason' => $m->sub_state === 'LOST' ? $m->cancel_reason : null];
    }

    public function filters(): array
    {
        return ['pic' => 'PIC Sales', 'result' => 'Hasil'];
    }
}
