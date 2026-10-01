<?php

namespace App\Support\V2\Grid\Sales;

use App\Models\Master\CustCustomer;
use App\Models\Sdi\IqpCosting;
use App\Models\Sdi\IqpInquiry;

// Pro-Cost > New (v1: pages::inquiry.costing-list, zona beku — buka = Costing Hub v1 apa adanya).
// Isi = inquiry NEW yang sudah Confirm oleh ProDev (stat_confirm_prodev = 1). To Do = costing belum published.
class CostingListGrid extends InquiryDeskGrid
{
    public function slug(): string
    {
        return $this->type() === 'NEW' ? 'sales-procost' : 'sales-procost-'.strtolower($this->type());
    }

    public function title(): string
    {
        return 'Pro-Cost '.self::TYPE_LABELS[$this->type()];
    }

    public function module(): string
    {
        return 'COSTING';
    }

    public function icon(): string
    {
        return ['NEW' => 'fa-solid fa-calculator', 'REVISE' => 'fa-solid fa-square-root-variable', 'RECALC' => 'fa-solid fa-arrows-rotate'][$this->type()];
    }

    protected function type(): string
    {
        return 'NEW';
    }

    protected function desk(): string
    {
        return 'PROCOST';
    }

    protected function v1Route(): ?string
    {
        return 'inquiry.costing.list';
    }

    protected function models(): array
    {
        return [IqpInquiry::class, IqpCosting::class, CustCustomer::class];
    }

    protected function rowUrl(array $r): string
    {
        return route('inquiry.costing', ['inquiry' => $r['id']]);
    }

    public function filters(): array
    {
        return ['costing' => 'Costing', 'customer' => 'Customer'];
    }
}
