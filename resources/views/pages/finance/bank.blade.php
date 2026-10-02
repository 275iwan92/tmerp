<?php

use App\Livewire\Concerns\FinanceScreen;
use App\Models\Acc\FinAccount;
use App\Models\Acc\FinBankRecon;
use App\Models\Acc\FinBankStatement;
use App\Services\Acc\BankReconService;
use App\Services\Acc\LedgerService;
use App\Support\Finance\Fmt;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

// Bank & Cash Process -- mockup blueprint: Import Statement -> Normalize ->
// Auto-Match -> Investigate (jurnal penyesuaian lewat approval) -> Reconcile.
// Voucher kas/bank tetap dibuat di layar Kasir (finance.cash-bank).
new #[Title('Bank & Cash — TELASIH MAKMUR ERP')] class extends Component
{
    use FinanceScreen;
    use WithFileUploads;

    public string $accountId = '';

    public string $search = '';

    public string $typeFilter = '';

    public $csv;

    public ?int $stmtId = null;

    public string $matchVoucher = '';

    public string $adjAccount = '';

    public function mount(): void
    {
        $this->accountId = (string) FinAccount::where('account_code', '111200')->value('account_id');
    }

    #[Computed]
    public function account(): ?FinAccount
    {
        return FinAccount::find($this->accountId);
    }

    #[Computed]
    public function movements()
    {
        return app(BankReconService::class)->bookMovements((int) $this->accountId)
            ->when($this->typeFilter === 'IN', fn ($c) => $c->filter(fn ($l) => (float) $l->debit > 0))
            ->when($this->typeFilter === 'OUT', fn ($c) => $c->filter(fn ($l) => (float) $l->credit > 0))
            ->when($this->search !== '', fn ($c) => $c->filter(fn ($l) => str_contains(strtolower($l->voucher->voucher_number.' '.$l->voucher->description.' '.$l->voucher->reference_no.' '.$l->voucher->partner_name), strtolower($this->search))))
            ->take(150)->values();
    }

    #[Computed]
    public function matchedVoucherIds(): array
    {
        return FinBankStatement::whereNotNull('matched_voucher_id')->pluck('match_status', 'matched_voucher_id')->all();
    }

    #[Computed]
    public function statement()
    {
        return FinBankStatement::where('account_id', $this->accountId)->orderByDesc('txn_date')->orderByDesc('stmt_line_id')->limit(150)->get();
    }

    #[Computed]
    public function stats(): array
    {
        $svc = app(BankReconService::class);
        $unmatched = FinBankStatement::where('account_id', $this->accountId)->where('match_status', 'UNMATCHED')->get();
        $lastStmt = FinBankStatement::where('account_id', $this->accountId)->max('txn_date');
        $book = $this->account ? app(LedgerService::class)->accountBalance($this->account->account_code, $lastStmt ?: now()->toDateString()) : 0;
        $stmt = $svc->statementBalance((int) $this->accountId);
        $last = FinBankRecon::where('account_id', $this->accountId)->orderByDesc('recon_id')->first();

        return [
            'stmt' => $stmt, 'book' => $book, 'diff' => $stmt === null ? null : round($stmt - $book, 2),
            'unmatched_n' => $unmatched->count(), 'unmatched_sum' => $unmatched->sum(fn ($s) => (float) $s->amount_in + (float) $s->amount_out),
            'last' => $last,
        ];
    }

    #[Computed]
    public function candidates()
    {
        $s = $this->stmtId ? FinBankStatement::find($this->stmtId) : null;
        if (! $s) {
            return collect();
        }
        $isIn = (float) $s->amount_in > 0;
        $used = array_keys($this->matchedVoucherIds);

        return app(BankReconService::class)->bookMovements((int) $this->accountId, $s->txn_date->copy()->subDays(30)->toDateString(), $s->txn_date->copy()->addDays(30)->toDateString())
            ->filter(fn ($l) => ($isIn ? (float) $l->debit : (float) $l->credit) > 0 && ! in_array($l->voucher_id, $used))
            ->sortBy(fn ($l) => abs(($isIn ? (float) $l->debit : (float) $l->credit) - ($isIn ? (float) $s->amount_in : (float) $s->amount_out)))
            ->take(15)->values();
    }

    public function updatedAccountId(): void
    {
        unset($this->movements, $this->statement, $this->stats, $this->account);
    }

    public function import(BankReconService $svc): void
    {
        $this->validate(['csv' => 'required|file|mimes:csv,txt|max:4096']);
        $n = 0;
        if ($this->attempt(function () use ($svc, &$n) {
            $n = $svc->importCsv((int) $this->accountId, $this->csv->getRealPath(), $this->login());
        })) {
            session()->flash('flash', str_replace(':n', $n, t(':n bank statement lines imported. Run Auto-Match to pair them with the books.')));
            $this->reset('csv');
            $this->closeModal();
            unset($this->statement, $this->stats);
        }
    }

    public function autoMatch(BankReconService $svc): void
    {
        $n = 0;
        $this->attempt(function () use ($svc, &$n) {
            $n = $svc->autoMatch((int) $this->accountId, $this->login());
        });
        session()->flash('flash', str_replace(':n', $n, t('Auto-match: :n lines matched successfully.')));
        unset($this->statement, $this->stats, $this->matchedVoucherIds);
    }

    public function openInvestigate(int $id): void
    {
        $this->stmtId = $id;
        $this->matchVoucher = '';
        $this->adjAccount = (string) FinAccount::where('account_code', '812000')->value('account_id');
        unset($this->candidates);
        $this->modal = 'investigate';
    }

    public function manualMatch(BankReconService $svc): void
    {
        $this->validate(['matchVoucher' => 'required|integer']);
        if ($this->attempt(fn () => $svc->match(FinBankStatement::findOrFail($this->stmtId), (int) $this->matchVoucher, $this->login()), t('Statement line matched to the book voucher.'))) {
            $this->closeModal();
            unset($this->statement, $this->stats, $this->matchedVoucherIds);
        }
    }

    public function adjust(BankReconService $svc): void
    {
        $this->validate(['adjAccount' => 'required|exists:tm2acc.fin_account,account_id']);
        if ($this->attempt(fn () => $svc->createAdjustment(FinBankStatement::findOrFail($this->stmtId), (int) $this->adjAccount, $this->login()), t('Bank adjustment journal created as DRAFT — submit & approve it in Journal Entries.'))) {
            $this->closeModal();
            unset($this->statement, $this->stats);
        }
    }

    public function ignore(int $id): void
    {
        $this->attempt(fn () => FinBankStatement::findOrFail($id)->update(['match_status' => 'IGNORED', 'updated_by' => $this->login()]), t('Statement line marked as ignored (duplicate/information).'));
        unset($this->statement, $this->stats);
    }

    public function reconcile(BankReconService $svc): void
    {
        $this->attempt(function () use ($svc) {
            $r = $svc->reconcile((int) $this->accountId, $this->login());
            session()->flash($r->status === 'RECONCILED' ? 'flash' : 'error', $r->status === 'RECONCILED'
                ? str_replace(':date', Fmt::date($r->statement_date), t('Reconciliation complete: statement balance = book balance as at :date.'))
                : str_replace([':amount', ':n'], [Fmt::rp($r->difference), $r->unmatched_count], t('Reconciliation recorded WITH a difference of :amount and :n unmatched lines.')));
        });
        unset($this->stats);
    }

    public function matchTone(?string $s): string
    {
        return ['MATCHED' => 'good', 'ADJUSTED' => 'info', 'IGNORED' => 'mute'][$s] ?? 'warn';
    }
};
?>

<x-ledger.page :title="t('Bank & Cash Process')" :subtitle="t('Bank journals, cash movements, statement imports, matching, and reconciliation.')">
    <x-slot:actions>
        <select class="lg-period" wire:model.live="accountId" x-data x-effect="$el.value = @js($accountId)">
            @foreach ($this->cashBankOptions() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
        </select>
        <button type="button" class="lg-btn light" wire:click="$set('modal', 'import')">⇧ {{ t('Import Statement') }}</button>
        <a class="lg-btn primary" href="{{ route('finance.cash-bank') }}">＋ {{ t('Bank Transaction') }}</a>
    </x-slot:actions>

    <div class="lg-cards">
        <x-ledger.metric :label="t('Statement Balance')" icon="▥" :value="$this->stats['stmt'] === null ? '—' : Fmt::short($this->stats['stmt'])" :sub="$this->account?->account_name" />
        <x-ledger.metric :label="t('GL Book Balance')" icon="▤" :value="Fmt::short($this->stats['book'])"
            :pill="$this->stats['diff'] === null ? t('No statement imported') : (abs($this->stats['diff']) < 0.01 ? t('Agrees with statement') : str_replace(':amount', Fmt::short($this->stats['diff']), t('Difference :amount')))"
            :tone="$this->stats['diff'] !== null && abs($this->stats['diff']) < 0.01 ? 'good' : 'warn'" />
        <x-ledger.metric :label="t('Unmatched Items')" icon="◷" :value="$this->stats['unmatched_n']" :sub="str_replace(':amount', Fmt::rp($this->stats['unmatched_sum']), t(':amount total'))" />
        <x-ledger.metric :label="t('Last Reconciled')" icon="✓" :value="$this->stats['last'] ? $this->stats['last']->statement_date->format('d M') : '—'"
            :pill="$this->stats['last'] ? ($this->stats['last']->status === 'RECONCILED' ? t('Completed') : t('With difference')) : t('Never')"
            :tone="$this->stats['last']?->status === 'RECONCILED' ? 'good' : 'warn'" />
    </div>

    <x-ledger.panel class="lg-stack" :title="t('Bank Reconciliation Flow')" :subtitle="t('Statement-to-ledger matching and adjustment control.')">
        <x-ledger.flow :nodes="[
            [t('Import Statement'), t('CSV (BCA/Mandiri export), or manual bank journal entry.')],
            [t('Normalize'), t('Bank date, value date, amount, reference, counterparty.')],
            [t('Auto-Match'), t('Match receipts, payments, transfers using amount and reference rules.'), $this->stats['unmatched_n'] ? str_replace(':n', $this->stats['unmatched_n'], t(':n unmatched')) : null],
            [t('Investigate'), t('Create bank charge, interest, timing, or suspense journal only with approval.')],
            [t('Reconcile'), t('Certified statement balance equals reconciled book balance.')],
        ]" />
    </x-ledger.panel>

    <div class="lg-two even">
        <x-ledger.panel :title="t('Bank Journal')" :subtitle="t('Recent cash and bank movements (posted GL).')">
            <x-slot:action><button type="button" class="lg-link" wire:click="reconcile">{{ t('Reconcile now') }}</button></x-slot:action>
            <div class="lg-toolbar">
                <input class="lg-search" style="width:220px" wire:model.live.debounce.300ms="search" placeholder="{{ t('Search reference or description...') }}">
                <select class="lg-select" wire:model.live="typeFilter" x-data x-effect="$el.value = @js($typeFilter)"><option value="">{{ t('All transaction types') }}</option><option value="IN">{{ t('Receipt') }}</option><option value="OUT">{{ t('Payment') }}</option></select>
            </div>
            <div class="lg-tablewrap">
                <table>
                    <thead><tr><th>{{ t('Date') }}</th><th>{{ t('Reference') }}</th><th>{{ t('Description') }}</th><th class="r">{{ t('Money In') }}</th><th class="r">{{ t('Money Out') }}</th><th>{{ t('Match Status') }}</th></tr></thead>
                    <tbody>
                        @forelse ($this->movements as $l)
                            <tr wire:key="bm-{{ $l->line_id }}">
                                <td>{{ Fmt::date($l->voucher->entry_date) }}</td>
                                <td><span class="lg-name">{{ $l->voucher->voucher_number }}</span><span class="lg-code">{{ $l->voucher->reference_no }}</span></td>
                                <td>{{ $l->voucher->description }}</td>
                                <td class="r lg-money">{{ Fmt::rp($l->debit, true) }}</td>
                                <td class="r lg-money">{{ Fmt::rp($l->credit, true) }}</td>
                                <td><x-ledger.tag :tone="$this->matchTone($this->matchedVoucherIds[$l->voucher_id] ?? null)">{{ isset($this->matchedVoucherIds[$l->voucher_id]) ? t('Matched') : t('Unmatched') }}</x-ledger.tag></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="lg-empty">{{ t('No posted movements on this account.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ledger.panel>

        <x-ledger.panel :title="t('Bank Statement')" :subtitle="t('Imported statement lines to reconcile.')">
            <x-slot:action><button type="button" class="lg-btn light sm" wire:click="autoMatch">⚡ {{ t('Auto-Match') }}</button></x-slot:action>
            <div class="lg-tablewrap">
                <table>
                    <thead><tr><th>{{ t('Date') }}</th><th>{{ t('Reference / Description') }}</th><th class="r">{{ t('In') }}</th><th class="r">{{ t('Out') }}</th><th>{{ t('Status') }}</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($this->statement as $s)
                            <tr wire:key="st-{{ $s->stmt_line_id }}">
                                <td>{{ Fmt::date($s->txn_date) }}</td>
                                <td><span class="lg-name">{{ $s->reference ?: '—' }}</span><span class="lg-code">{{ $s->description }}</span></td>
                                <td class="r lg-money">{{ Fmt::rp($s->amount_in, true) }}</td>
                                <td class="r lg-money">{{ Fmt::rp($s->amount_out, true) }}</td>
                                <td><x-ledger.tag :tone="$this->matchTone($s->match_status)">{{ t(ucfirst(strtolower($s->match_status))) }}</x-ledger.tag></td>
                                <td class="r">
                                    @if ($s->match_status === 'UNMATCHED')
                                        <div class="lg-rowmenu" x-data="{ o: false }" @click.outside="o = false">
                                            <button type="button" class="lg-dots" @click="o = !o">⋮</button>
                                            <div class="m" x-show="o" x-cloak>
                                                <button type="button" wire:click="openInvestigate({{ $s->stmt_line_id }})">{{ t('Investigate / match') }}</button>
                                                <button type="button" wire:click="ignore({{ $s->stmt_line_id }})">{{ t('Ignore') }}</button>
                                            </div>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="lg-empty">{{ t('No bank statement yet — click Import Statement.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ledger.panel>
    </div>

    @if ($modal === 'import')
        <x-ledger.modal :title="t('Import Bank Statement')">
            <div class="lg-mb one">
                @if ($errorMessage)<div class="lg-alert err">{{ $errorMessage }}</div>@endif
                <x-ledger.field :label="t('Bank account')"><b>{{ $this->account?->account_code }} · {{ $this->account?->account_name }}</b></x-ledger.field>
                <x-ledger.field :label="t('CSV file')" name="csv" :hint="t('Required headers: date/tanggal, description/keterangan, reference, debit/keluar, credit/masuk, balance/saldo.').' '.t('Separator , or ; — numbers 1.234.567,50 and 1,234,567.50 are both recognized.')">
                    <input type="file" accept=".csv,.txt" wire:model="csv" style="padding-top:8px">
                </x-ledger.field>
            </div>
            <x-slot:footer>
                <button type="button" class="lg-btn light" wire:click="closeModal">{{ t('Cancel') }}</button>
                <button type="button" class="lg-btn primary" wire:click="import" wire:loading.attr="disabled">⇧ {{ t('Import') }}</button>
            </x-slot:footer>
        </x-ledger.modal>
    @endif

    @if ($modal === 'investigate')
        @foreach ([\App\Models\Acc\FinBankStatement::find($stmtId)] as $s)
            <x-ledger.modal :title="t('Investigate Statement Line')" size="xl">
                <dl class="lg-kv" style="padding-top:16px">
                    <dt>{{ t('Date') }}</dt><dd>{{ Fmt::date($s?->txn_date) }}</dd>
                    <dt>{{ t('Reference') }}</dt><dd>{{ $s?->reference }} — {{ $s?->description }}</dd>
                    <dt>{{ t('Amount') }}</dt><dd>{{ (float) $s?->amount_in > 0 ? t('In').' '.Fmt::rp($s?->amount_in) : t('Out').' '.Fmt::rp($s?->amount_out) }}</dd>
                </dl>
                <div class="lg-mb">
                    @if ($errorMessage)<div class="lg-alert err lg-field full">{{ $errorMessage }}</div>@endif
                    <x-ledger.field :label="t('Match to book entry (±30 days, closest amount first)')" name="matchVoucher" full>
                        <select wire:model="matchVoucher" x-data x-effect="$el.value = @js($matchVoucher)">
                            <option value="">— {{ t('choose voucher') }} —</option>
                            @foreach ($this->candidates as $l)<option value="{{ $l->voucher_id }}">{{ Fmt::date($l->voucher->entry_date) }} · {{ $l->voucher->voucher_number }} · {{ Fmt::rp((float) $l->debit ?: $l->credit) }} · {{ \Illuminate\Support\Str::limit($l->voucher->description, 40) }}</option>@endforeach
                        </select>
                    </x-ledger.field>
                    <div class="lg-field full"><button type="button" class="lg-btn light" wire:click="manualMatch">{{ t('Match') }}</button></div>
                    <x-ledger.field :label="t('…or create adjustment journal against')" name="adjAccount" full :hint="t('Admin fees, interest, interest tax, etc. Created as DRAFT and must be approved in Journal Entries.')">
                        <select wire:model="adjAccount" x-data x-effect="$el.value = @js($adjAccount)">@foreach ($this->postableAccountOptions() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select>
                    </x-ledger.field>
                </div>
                <x-slot:footer>
                    <button type="button" class="lg-btn light" wire:click="closeModal">{{ t('Close') }}</button>
                    <button type="button" class="lg-btn primary" wire:click="adjust">{{ t('Create Adjustment Journal') }}</button>
                </x-slot:footer>
            </x-ledger.modal>
        @endforeach
    @endif
</x-ledger.page>
