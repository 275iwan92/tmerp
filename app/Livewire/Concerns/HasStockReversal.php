<?php

namespace App\Livewire\Concerns;

use App\Services\Inventory\StockReversalService;
use Illuminate\Support\Facades\Auth;

// Tombol Cancel (dialog alasan wajib -> dokumen pembalik) dan Duplicate pada form dokumen stok POSTED. Layar pemakai:
// properti $category, $editingId, $doc_no dan metode openEdit(); tampilannya = partial inventory-reversal.
trait HasStockReversal
{
    public bool $reverseOpen = false;

    public string $reverseReason = '';

    public function reversalState(): array
    {
        return app(StockReversalService::class)->state($this->category, $this->editingId);
    }

    public function openReverse(): void
    {
        $this->reverseReason = app(StockReversalService::class)->defaultReason($this->doc_no);
        $this->reverseOpen = true;
        $this->resetErrorBag('reverseReason');
    }

    public function closeReverse(): void
    {
        $this->reverseOpen = false;
    }

    public function reverseDoc(StockReversalService $svc): void
    {
        $this->resetErrorBag('reverseReason');
        $r = $svc->reverse($this->category, (int) $this->editingId, $this->reverseReason, Auth::user()->login);
        if (! $r['ok']) {
            $this->addError('reverseReason', $r['error']);

            return;
        }
        $this->afterReversalChange();
        session()->flash('flash', tf('Document cancelled. Reversal :no posted.', ['no' => $r['doc_no']]).' '.($r['stock_warning'] ?? ''));
        if ($r['journal_warning']) {
            session()->flash('journal_warning', $r['journal_warning']);
        }
        $this->openEdit((int) $this->editingId);
    }

    public function duplicateDoc(StockReversalService $svc): void
    {
        $id = $svc->duplicate($this->category, (int) $this->editingId, Auth::user()->login);
        if (! $id) {
            return;
        }
        $this->afterReversalChange();
        session()->flash('flash', t('Draft copy created. Correct it, then Confirm.'));
        $this->openEdit($id);
    }

    private function afterReversalChange(): void
    {
        $this->reverseOpen = false;
        foreach (['documents', 'visibleDocuments', 'statTotal', 'statDraft', 'statPosted'] as $computed) {
            unset($this->$computed);
        }
    }
}
