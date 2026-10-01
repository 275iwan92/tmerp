<?php

namespace App\Support\V2\Grid\Acc;

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinVoucher;
use App\Models\Acc\FinVoucherLine;
use App\Models\Sys\SecUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Journal Entries (v1: pages::finance.journals): jurnal umum yang dibuat bagian Accounting (manual, accrual, recurring,
// adjustment, reversing, opening, closing). Jurnal otomatis dari modul lain dibaca di General Ledger. Buka / New = layar v1.
class JournalGrid extends AccGrid
{
    private const KINDS = ['MANUAL' => 'Manual Journal', 'ACCRUAL' => 'Accrual Journal', 'RECURRING' => 'Recurring Journal', 'ADJUSTMENT' => 'Adjustment Journal',
        'REVERSAL' => 'Reversing Journal', 'OPENING' => 'Opening Balance', 'CLOSING' => 'Year-end Closing'];

    private const STATUSES = ['DRAFT' => 'Draft', 'SUBMITTED' => 'Pending approval', 'APPROVED' => 'Approved', 'POSTED' => 'Posted', 'CANCELLED' => 'Cancelled'];

    private const STATUS_TONES = ['Draft' => 'neutral', 'Pending approval' => 'warn', 'Approved' => 'info', 'Posted' => 'success', 'Reversed' => 'neutral', 'Cancelled' => 'danger'];

    private array $people = [];

    public function slug(): string
    {
        return 'acc-journal';
    }

    public function title(): string
    {
        return 'Journal Entries';
    }

    public function family(): string
    {
        return self::TRANSACTIONAL;
    }

    public function group(): string
    {
        return 'Accounting';
    }

    public function icon(): string
    {
        return 'fa-solid fa-book';
    }

    protected function v1Route(): ?string
    {
        return 'finance.journals';
    }

    protected function models(): array
    {
        return [FinVoucher::class, FinVoucherLine::class, FinAccount::class];
    }

    public function search(): string
    {
        return 'Search journal no, description, reference...';
    }

    protected function query(): Builder
    {
        return FinVoucher::query()->with('lines.account')->whereIn('journal_kind', array_keys(self::KINDS))->orderByDesc('entry_date')->orderByDesc('voucher_id');
    }

    protected function prefetch(Collection $items): void
    {
        $this->people = SecUser::query()->whereIn('login', $items->pluck('created_by')->filter()->unique()->all())->pluck('name', 'login')->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'voucher_no', 'label' => 'Journal No. / Kind', 'type' => 'doc', 'party' => 'kind'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'description', 'label' => 'Description'],
            ['key' => 'reference', 'label' => 'Reference'],
            ['key' => 'amount', 'label' => 'Amount', 'type' => 'money'],
            ['key' => 'created_by', 'label' => 'Prepared By'],
            ['key' => 'auto_reverse', 'label' => 'Auto Reverse', 'type' => 'date', 'visible' => false],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => self::STATUS_TONES],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            // Nomor terbit saat jurnal disimpan; baris lama tanpa nomor ditandai id-nya supaya persegi dokumen tidak kosong.
            'voucher_no' => $m->voucher_number ?: 'JV-'.$m->voucher_id, 'kind' => self::KINDS[$m->journal_kind] ?? 'Journal',
            'date' => $m->entry_date?->toDateString(), 'description' => $m->description, 'reference' => $m->reference_no, 'amount' => $m->totalAmount(),
            'created_by' => $this->people[$m->created_by] ?? $m->created_by, 'auto_reverse' => $m->auto_reverse_date?->toDateString(),
            'status' => $m->status === 'POSTED' && $m->reversed_by_id ? 'Reversed' : (self::STATUSES[$m->status] ?? $m->status),
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
            ['label' => 'Pending approval', 'where' => ['status' => 'Pending approval'], 'tone' => 'warn'],
            ['label' => 'Approved', 'where' => ['status' => 'Approved'], 'tone' => 'info'],
            ['label' => 'Posted', 'where' => ['status' => 'Posted'], 'tone' => 'success'],
        ];
    }

    public function filters(): array
    {
        return ['kind' => 'Kind', 'created_by' => 'Prepared By', 'status' => 'Status'];
    }
}
