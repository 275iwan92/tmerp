<?php

namespace App\Support\V2\Grid\Acc;

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinVoucher;
use App\Models\Acc\FinVoucherLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Cashier / Cash & Bank (v1: pages::finance.cash-bank): bukti kas dan bank masuk / keluar (BKM, BKK, BBM, BBK).
// Buka / New = form v1 (simpan draft, posting, batal).
class CashBankGrid extends AccGrid
{
    private const TYPES = ['BKM' => 'Cash Receipt', 'BKK' => 'Cash Payment', 'BBM' => 'Bank Receipt', 'BBK' => 'Bank Payment'];

    private const STATUSES = ['DRAFT' => 'Draft', 'SUBMITTED' => 'Pending approval', 'APPROVED' => 'Approved', 'POSTED' => 'Posted', 'CANCELLED' => 'Cancelled'];

    private const STATUS_TONES = ['Draft' => 'neutral', 'Pending approval' => 'warn', 'Approved' => 'info', 'Posted' => 'success', 'Cancelled' => 'danger'];

    public function slug(): string
    {
        return 'acc-cash-bank';
    }

    public function title(): string
    {
        return 'Cash & Bank';
    }

    public function family(): string
    {
        return self::TRANSACTIONAL;
    }

    public function group(): string
    {
        return 'Transaction Flows';
    }

    public function icon(): string
    {
        return 'fa-solid fa-cash-register';
    }

    protected function v1Route(): ?string
    {
        return 'finance.cash-bank';
    }

    protected function models(): array
    {
        return [FinVoucher::class, FinVoucherLine::class, FinAccount::class];
    }

    public function search(): string
    {
        return 'Search voucher no, paid to / received from...';
    }

    protected function query(): Builder
    {
        return FinVoucher::query()->with(['lines.account', 'mainAccount'])->whereNotNull('voucher_type')->orderByDesc('entry_date')->orderByDesc('voucher_id');
    }

    public function actionUrls(): array
    {
        return ['print' => $this->printUrl('payment-voucher')];
    }

    public function columns(): array
    {
        return [
            ['key' => 'voucher_no', 'label' => 'Voucher No. / Party', 'type' => 'doc', 'party' => 'partner'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'type', 'label' => 'Type'],
            ['key' => 'account', 'label' => 'Cash / Bank Account'],
            ['key' => 'description', 'label' => 'Description'],
            ['key' => 'reference', 'label' => 'Reference', 'visible' => false],
            ['key' => 'amount', 'label' => 'Amount', 'type' => 'money'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => self::STATUS_TONES],
            $this->printColumn(),
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'voucher_no' => $m->voucher_number ?: 'VCH-'.$m->voucher_id, 'partner' => $m->partner_name, 'date' => $m->entry_date?->toDateString(),
            'type' => isset(self::TYPES[$m->voucher_type]) ? t(self::TYPES[$m->voucher_type]) : $m->voucher_type, 'account' => $m->mainAccount ? $m->mainAccount->account_code.' '.$m->mainAccount->account_name : null,
            'description' => $m->description, 'reference' => $m->reference_no, 'amount' => $m->totalAmount(),
            'status' => $m->stat_cancel ? 'Cancelled' : (self::STATUSES[$m->status] ?? $m->status),
            'items' => $m->lines->map(fn (FinVoucherLine $l) => ['code' => $l->account?->account_code, 'account' => $l->account?->account_name,
                'debit' => (float) $l->debit, 'credit' => (float) $l->credit, 'narration' => $l->narration])->all(),
        ];
    }

    public function detail(): array
    {
        return ['key' => 'items', 'title' => 'Journal lines', 'columns' => [
            ['key' => 'code', 'label' => 'Account Code'],
            ['key' => 'account', 'label' => 'Account Name', 'strong' => true],
            ['key' => 'debit', 'label' => 'Debit', 'type' => 'money'],
            ['key' => 'credit', 'label' => 'Credit', 'type' => 'money'],
            ['key' => 'narration', 'label' => 'Narration'],
        ]];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Draft', 'where' => ['status' => 'Draft'], 'tone' => 'neutral'],
            ['label' => 'Posted', 'where' => ['status' => 'Posted'], 'tone' => 'success'],
            ['label' => 'Cancelled', 'where' => ['status' => 'Cancelled'], 'tone' => 'danger'],
        ];
    }

    public function filters(): array
    {
        return ['type' => 'Type', 'account' => 'Cash / Bank Account', 'status' => 'Status'];
    }
}
