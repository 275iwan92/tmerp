<?php

namespace App\Support\V2\Grid\Inv;

use App\Services\Inventory\Valuation\GrIrAgingService;

// GR/IR Aging: barang sudah diterima tetapi belum ditagih, dan tagihan yang barangnya belum diterima, dengan umur 0-30 / 31-60 / 61-90 / 90+ hari.
class GrIrAgingReport extends ValuationReport
{
    public function slug(): string
    {
        return 'inv-grir-aging';
    }

    public function title(): string
    {
        return 'GR/IR Aging';
    }

    public function icon(): string
    {
        return 'fa-solid fa-file-circle-question';
    }

    public function guide(): ?string
    {
        return 'Goods received but not yet invoiced (the GR/IR clearing balance) and invoices received before the goods, aged from the receipt or invoice date to the To date. '
            .'An invoice closes the oldest receipts of its PO first.';
    }

    protected function sources(): array
    {
        return ['tm2pur.pv_receipt', 'tm2pur.pv_line', 'tm2acc.fin_invoice_receipt', 'tm2acc.fin_invoice_receipt_line', 'tm2mst.sup_supplier'];
    }

    protected function listRows(): array
    {
        return (new GrIrAgingService)->rows($this->asOf());
    }

    public function columns(): array
    {
        $buckets = [];
        foreach (GrIrAgingService::BUCKETS as $key => $label) {
            $buckets[] = ['key' => $key, 'label' => raw($label), 'type' => 'money', 'sum' => true];
        }

        return array_merge([
            ['key' => 'kind', 'label' => 'Type', 'type' => 'badge', 'tones' => [GrIrAgingService::RECEIVED => 'warn', GrIrAgingService::INVOICED => 'info']],
            ['key' => 'supplier', 'label' => 'Supplier'],
            ['key' => 'po_no', 'label' => 'PO No.', 'width' => '130px'],
            ['key' => 'doc_no', 'label' => 'Receipt No.', 'width' => '130px'],
            ['key' => 'doc_date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'days', 'label' => 'Age (days)', 'type' => 'integer'],
            ['key' => 'bucket', 'label' => 'Age', 'visible' => false],
            ['key' => 'amount', 'label' => 'Amount', 'type' => 'money', 'sum' => true],
        ], $buckets);
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'Received not invoiced', 'where' => ['kind' => GrIrAgingService::RECEIVED], 'tone' => 'warn'],
            ['label' => 'Invoiced not received', 'where' => ['kind' => GrIrAgingService::INVOICED], 'tone' => 'info'], ['label' => raw('90+'), 'where' => ['bucket' => '90+'], 'tone' => 'danger']];
    }

    public function filters(): array
    {
        return ['kind' => 'Type', 'supplier' => 'Supplier', 'bucket' => 'Age'];
    }

    public function groups(): array
    {
        return ['supplier' => 'Supplier', 'kind' => 'Type', 'bucket' => 'Age'];
    }

    public function groupBy(): ?string
    {
        return 'supplier';
    }
}
