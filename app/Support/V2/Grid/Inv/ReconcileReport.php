<?php

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\InvReconcileLog;

// Daily Reconciliation: hasil php artisan inv:reconcile per hari dan kelas (RM / SFG / FG): saldo akun persediaan di buku besar dibanding nilai kartu stok.
// Selisih di atas toleransi (INV_RECON_TOLERANCE) ditandai merah.
class ReconcileReport extends ValuationReport
{
    public function slug(): string
    {
        return 'inv-reconcile';
    }

    public function title(): string
    {
        return 'Daily Reconciliation';
    }

    public function icon(): string
    {
        return 'fa-solid fa-scale-unbalanced';
    }

    public function guide(): ?string
    {
        return 'The inventory accounts in the general ledger compared with the stock card value, per class and day. Difference = ledger - (stock card + landed cost not yet in the cards). '
            .'A red row is above the tolerance: find the journal or stock document that is missing on one side. The daily run is the command inv:reconcile.';
    }

    protected function sources(): array
    {
        return ['tm2inv.inv_reconcile_log', 'tm2acc.fin_voucher_line', 'tm2inv.rm_stockcard', 'tm2inv.sfg_stockcard', 'tm2inv.fg_stockcard'];
    }

    protected function listRows(): array
    {
        [$from, $to] = $this->period();

        return InvReconcileLog::query()->whereBetween('recon_date', [$from->toDateString(), $to->toDateString()])->orderByDesc('recon_date')->orderBy('stock_class')->get()
            ->map(fn (InvReconcileLog $r) => ['id' => $r->id_reconcile, 'date' => $r->recon_date->toDateString(), 'class' => AllStockReport::CLASSES[$r->stock_class] ?? $r->stock_class,
                'accounts' => (string) $r->account_codes, 'gl' => $r->gl_value, 'cards' => $r->ledger_value, 'landed' => $r->landed_pending, 'difference' => $r->difference,
                'status' => $r->status === 'OK' ? 'Balanced' : 'Difference', 'tone' => $r->status === 'OK' ? null : 'danger'])->all();
    }

    public function rowTone(): ?string
    {
        return 'tone';
    }

    public function columns(): array
    {
        return [
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'class', 'label' => 'Class'],
            ['key' => 'accounts', 'label' => 'Accounts', 'visible' => false],
            ['key' => 'gl', 'label' => 'General Ledger', 'type' => 'money', 'sum' => true],
            ['key' => 'cards', 'label' => 'Stock Card', 'type' => 'money', 'sum' => true],
            ['key' => 'landed', 'label' => 'Landed Cost Not in Cards', 'type' => 'money', 'sum' => true],
            ['key' => 'difference', 'label' => 'Difference', 'type' => 'money', 'sum' => true],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['Balanced' => 'success', 'Difference' => 'danger']],
        ];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'Difference', 'where' => ['status' => 'Difference'], 'tone' => 'danger'], ['label' => 'Balanced', 'where' => ['status' => 'Balanced'], 'tone' => 'success']];
    }

    public function filters(): array
    {
        return ['class' => 'Class', 'status' => 'Status'];
    }
}
