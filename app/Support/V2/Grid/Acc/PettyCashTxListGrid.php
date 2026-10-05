<?php

namespace App\Support\V2\Grid\Acc;

use App\Models\Acc\FinPettyCash;
use App\Support\V2\Data\PettyCash;
use App\Support\V2\Grid\DocListGrid;

// Daftar Petty Cash Transactions (layar v2 petty-cash): turunan DocListGrid, kolom dan saringannya sama dengan sebelumnya.
class PettyCashTxListGrid extends DocListGrid
{
    public function __construct(private FinPettyCash $fund) {}

    public function slug(): string
    {
        return 'petty-cash';
    }

    public function title(): string
    {
        return 'Petty Cash Transactions';
    }

    public function module(): string
    {
        return 'FINACC';
    }

    protected function listId(): string
    {
        return 'petty-tx';
    }

    protected function listRows(): array
    {
        return PettyCash::rows($this->fund);
    }

    protected function sources(): array
    {
        return ['tm2acc.fin_petty_cash_tx', 'tm2acc.fin_voucher'];
    }

    public function search(): string
    {
        return 'Search transaction, PO, receiver...';
    }

    public function openUrl(): ?string
    {
        return $this->url().'?tx={id}';
    }

    public function newUrl(): ?string
    {
        return null;
    }

    public function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'Transaction No. / Receiver', 'type' => 'doc', 'party' => 'who'],
            ['key' => 'type', 'label' => 'Transaction'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'po', 'label' => 'Cash PO'],
            ['key' => 'in', 'label' => 'In', 'type' => 'money', 'sum' => true],
            ['key' => 'out', 'label' => 'Out', 'type' => 'money', 'sum' => true],
            ['key' => 'voucher', 'label' => 'Journal'],
            ['key' => 'note', 'label' => 'Notes', 'visible' => false],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['Posted' => 'success', 'Cancelled' => 'danger']],
        ];
    }

    public function detail(): array
    {
        return ['key' => 'items', 'title' => 'Item (Cash PO)', 'columns' => [
            ['key' => 'code', 'label' => 'Code'],
            ['key' => 'name', 'label' => 'Item Name', 'strong' => true],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
        ]];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'Cash Advance', 'where' => ['type' => t('Cash Advance')], 'tone' => 'warn'], ['label' => 'Settlement / Reimburse', 'where' => ['type' => [t('Settlement'), t('Reimburse')]], 'tone' => 'info'], ['label' => 'Top Up', 'where' => ['type' => t('Top Up')], 'tone' => 'success'], ['label' => 'Cancelled', 'where' => ['status' => 'Cancelled'], 'tone' => 'danger']];
    }

    public function filters(): array
    {
        return ['type' => 'Transaction'];
    }
}
