<?php

use App\Livewire\Concerns\FinanceScreen;
use App\Livewire\Concerns\OpensFromQuery;
use App\Models\Acc\FinAccount;
use App\Models\Acc\FinCostCenter;
use App\Models\Acc\FinJournal;
use App\Models\Acc\FinVoucher;
use App\Services\Acc\JournalPostingService;
use App\Support\Finance\Fmt;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Journal Entries -- mockup blueprint (Manual Journal Flow): Prepare ->
// Validate -> Approve -> Post -> Reverse/Adjust. Posted tidak pernah diedit;
// koreksi = jurnal pembalik. Aturan approval dari fin_approval_rule.
new #[Title('Journal Entries — TELASIH MAKMUR ERP')] class extends Component
{
    use FinanceScreen;
    use OpensFromQuery;

    private const KINDS = ['MANUAL' => 'Manual Journal', 'ACCRUAL' => 'Accrual Journal', 'RECURRING' => 'Recurring Journal', 'ADJUSTMENT' => 'Adjustment Journal', 'REVERSAL' => 'Reversing Journal', 'OPENING' => 'Opening Balance', 'CLOSING' => 'Year-end Closing'];

    public string $search = '';

    public string $workflow = '';

    public ?int $editingId = null;

    public ?int $viewId = null;

    public string $jv_date = '';

    public string $jv_kind = 'MANUAL';

    public string $jv_desc = '';

    public string $jv_ref = '';

    public string $jv_autorev = '';

    public array $lines = [];

    public string $rev_date = '';

    public string $rev_reason = '';

    #[Computed]
    public function journals()
    {
        return FinVoucher::with('lines')
            ->whereIn('journal_kind', array_keys(self::KINDS))
            ->when($this->workflow !== '', fn ($q) => $q->where('status', $this->workflow))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('voucher_number', 'like', "%{$this->search}%")->orWhere('description', 'like', "%{$this->search}%")))
            ->orderByDesc('entry_date')->orderByDesc('voucher_id')->limit(200)->get();
    }

    #[Computed]
    public function stats(): array
    {
        $base = FinVoucher::with('lines')->whereIn('journal_kind', array_keys(self::KINDS));
        $pending = (clone $base)->where('status', 'SUBMITTED')->get();
        $auto = FinVoucher::where('status', 'POSTED')->whereNotNull('auto_reverse_date')->whereNull('reversed_by_id');

        return [
            'draft' => (clone $base)->where('status', 'DRAFT')->count(),
            'pending' => $pending->count(),
            'pending_value' => $pending->sum(fn ($v) => $v->totalAmount()),
            'posted_month' => (clone $base)->where('status', 'POSTED')->whereBetween('entry_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->count(),
            'auto' => (clone $auto)->count(),
            'auto_next' => (clone $auto)->min('auto_reverse_date'),
        ];
    }

    #[Computed]
    public function names(): array
    {
        return $this->userNames($this->journals->pluck('created_by'));
    }

    #[Computed]
    public function accountOptions(): array
    {
        return $this->postableAccountOptions();
    }

    #[Computed]
    public function ccOptions(): array
    {
        return FinCostCenter::where('activate', true)->orderBy('cc_code')->pluck('cc_name', 'cc_id')->all();
    }

    #[Computed]
    public function templates()
    {
        return FinVoucher::with('lines')->where('journal_kind', 'RECURRING')->where('status', 'POSTED')->orderByDesc('entry_date')->limit(20)->get()->unique('description');
    }

    #[Computed]
    public function viewing(): ?FinVoucher
    {
        return $this->viewId ? FinVoucher::with('lines.account', 'journal')->find($this->viewId) : null;
    }

    public function totals(): array
    {
        $d = array_sum(array_map(fn ($l) => (float) ($l['debit'] ?: 0), $this->lines));
        $c = array_sum(array_map(fn ($l) => (float) ($l['credit'] ?: 0), $this->lines));

        return [round($d, 2), round($c, 2)];
    }

    public function openNew(): void
    {
        $this->reset(['editingId', 'jv_desc', 'jv_ref', 'jv_autorev']);
        $this->jv_date = now()->toDateString();
        $this->jv_kind = 'MANUAL';
        $this->lines = [$this->blankLine(), $this->blankLine()];
        $this->modal = 'form';
    }

    private function blankLine(): array
    {
        return ['account_id' => '', 'debit' => '', 'credit' => '', 'cc_id' => '', 'narration' => ''];
    }

    public function addLine(): void
    {
        $this->lines[] = $this->blankLine();
    }

    public function removeLine(int $i): void
    {
        unset($this->lines[$i]);
        $this->lines = array_values($this->lines);
    }

    public function edit(int $id): void
    {
        $v = FinVoucher::with('lines')->findOrFail($id);
        if ($v->status !== 'DRAFT') {
            $this->openView($id);

            return;
        }
        $this->editingId = $v->voucher_id;
        $this->jv_date = $v->entry_date->toDateString();
        $this->jv_kind = $v->journal_kind ?: 'MANUAL';
        $this->jv_desc = (string) $v->description;
        $this->jv_ref = (string) $v->reference_no;
        $this->jv_autorev = (string) $v->auto_reverse_date?->toDateString();
        $this->lines = $v->lines->map(fn ($l) => ['account_id' => $l->account_id, 'debit' => (float) $l->debit ?: '', 'credit' => (float) $l->credit ?: '', 'cc_id' => $l->cc_id ?: '', 'narration' => (string) $l->narration])->all();
        $this->modal = 'form';
    }

    public function useTemplate(int $id): void
    {
        $v = FinVoucher::with('lines')->findOrFail($id);
        $this->openNew();
        $this->jv_kind = 'RECURRING';
        $this->jv_desc = (string) $v->description;
        $this->lines = $v->lines->map(fn ($l) => ['account_id' => $l->account_id, 'debit' => (float) $l->debit ?: '', 'credit' => (float) $l->credit ?: '', 'cc_id' => $l->cc_id ?: '', 'narration' => (string) $l->narration])->all();
    }

    // Dibuka dari grid v2 (?open={id}): langsung ke tampilan jurnal itu; ?new=1 = jurnal baru (openNew).
    protected function openFromQuery(int $id): void
    {
        $this->openView($id);
    }

    public function openView(int $id): void
    {
        $this->viewId = $id;
        $this->modal = 'view';
    }

    public function save(bool $submit, JournalPostingService $svc): void
    {
        $this->validate([
            'jv_date' => 'required|date',
            'jv_desc' => 'required|string|max:255',
            'jv_kind' => 'required|in:MANUAL,ACCRUAL,RECURRING,ADJUSTMENT,OPENING',
            'jv_autorev' => 'nullable|date|after:jv_date',
            'lines.*.account_id' => 'nullable|exists:tm2acc.fin_account,account_id',
        ], [], ['jv_desc' => t('Description'), 'jv_date' => t('Date'), 'jv_autorev' => t('Auto-reverse date')]);

        [$d, $c] = $this->totals();
        if (abs($d - $c) > 0.009 || $d <= 0) {
            $this->errorMessage = str_replace([':debit', ':credit'], [Fmt::rp($d), Fmt::rp($c)], t('Debit (:debit) must equal credit (:credit) and must not be zero.'));

            return;
        }
        $recon = FinAccount::whereIn('account_id', collect($this->lines)->pluck('account_id')->filter())->where('is_recon', true)->pluck('account_code');
        if ($recon->isNotEmpty()) {
            $this->errorMessage = str_replace(':accounts', $recon->implode(', '), t('Control accounts :accounts (receivable/payable/advance) only via Order to Cash / Procure to Pay so the subledger stays in sync.'));

            return;
        }

        $ok = $this->attempt(function () use ($svc, $submit) {
            $existing = $this->editingId ? FinVoucher::find($this->editingId) : null;
            $voucher = $svc->saveDraft($existing, [
                'voucher_number' => $existing?->voucher_number ?? $svc->nextJournalNumber($this->jv_date),
                'journal_id' => FinJournal::where('journal_code', 'GEN')->value('journal_id'),
                'journal_kind' => $this->jv_kind, 'entry_date' => $this->jv_date, 'posting_date' => $this->jv_date,
                'description' => $this->jv_desc, 'reference_no' => $this->jv_ref ?: null,
                'auto_reverse_date' => $this->jv_kind === 'ACCRUAL' && $this->jv_autorev ? $this->jv_autorev : null,
                'source_type' => 'MANUAL',
            ], array_map(fn ($l) => [
                'account_id' => (int) $l['account_id'], 'debit' => (float) ($l['debit'] ?: 0), 'credit' => (float) ($l['credit'] ?: 0),
                'cc_id' => $l['cc_id'] ?: null, 'narration' => $l['narration'] ?: null,
            ], $this->lines), $this->login());
            if ($submit) {
                $svc->submit($voucher, $this->login());
            }
        }, $submit ? t('Journal saved & submitted for approval.') : t('Journal draft saved.'));

        if ($ok) {
            $this->closeModal();
            unset($this->journals, $this->stats);
        }
    }

    public function act(string $action, int $id, JournalPostingService $svc): void
    {
        $v = FinVoucher::findOrFail($id);
        $user = Auth::user();
        $this->attempt(match ($action) {
            'submit' => fn () => $svc->submit($v, $user->login),
            'approve' => fn () => $svc->approve($v, $user),
            'return' => fn () => $svc->reject($v, $user->login),
            'post' => fn () => $svc->postDraft($v, $user->login, true),
            'delete' => function () use ($v) {
                abort_unless($v->status === 'DRAFT', 422, t('Only DRAFT can be deleted.'));
                $v->lines()->delete();
                $v->delete();
            },
        }, ['submit' => t('Journal submitted.'), 'approve' => t('Journal approved.'), 'return' => t('Journal returned to Draft.'), 'post' => t('Journal posted to the General Ledger.'), 'delete' => t('Draft deleted.')][$action]);
        unset($this->journals, $this->stats, $this->viewing);
    }

    public function openReverse(int $id): void
    {
        $this->viewId = $id;
        $this->rev_date = now()->toDateString();
        $this->rev_reason = '';
        $this->modal = 'reverse';
    }

    public function reverse(JournalPostingService $svc): void
    {
        $this->validate(['rev_date' => 'required|date', 'rev_reason' => 'required|string|max:120']);
        if ($this->attempt(fn () => $svc->reverse(FinVoucher::findOrFail($this->viewId), $this->rev_date, $this->login(), $this->rev_reason), t('Reversing journal posted.'))) {
            $this->closeModal();
            unset($this->journals, $this->stats);
        }
    }

    public function statusTone(string $s): string
    {
        return ['DRAFT' => 'warn', 'SUBMITTED' => 'warn', 'APPROVED' => 'info', 'POSTED' => 'good', 'CANCELLED' => 'bad'][$s] ?? 'mute';
    }

    public function statusLabel(FinVoucher $v): string
    {
        if ($v->status === 'POSTED' && $v->reversed_by_id) {
            return t('Reversed');
        }

        return t(['DRAFT' => 'Draft', 'SUBMITTED' => 'Pending approval', 'APPROVED' => 'Approved', 'POSTED' => 'Posted', 'CANCELLED' => 'Cancelled'][$v->status] ?? $v->status);
    }

    public function kindLabel(?string $k): string
    {
        return t(self::KINDS[$k] ?? 'Journal');
    }
};
?>

<x-ledger.page :title="t('Journal Entries')" :subtitle="t('Manual, recurring, accrual, adjustment, and reversing journal processing.')">
    <x-slot:actions>
        <button type="button" class="lg-btn light" wire:click="$set('modal', 'templates')">▤ {{ t('Templates') }}</button>
        <button type="button" class="lg-btn primary" wire:click="openNew">＋ {{ t('Create Journal') }}</button>
    </x-slot:actions>

    <div class="lg-cards">
        <x-ledger.metric :label="t('Draft')" icon="✎" :value="$this->stats['draft']" :pill="t('Awaiting review')" tone="warn" />
        <x-ledger.metric :label="t('Pending Approval')" icon="◷" :value="$this->stats['pending']" :sub="str_replace(':amount', Fmt::short($this->stats['pending_value']), t(':amount value'))" />
        <x-ledger.metric :label="t('Posted This Month')" icon="✓" :value="$this->stats['posted_month']" :sub="now()->format('F Y')" />
        <x-ledger.metric :label="t('Auto-Reversing')" icon="↔" :value="$this->stats['auto']" :sub="$this->stats['auto_next'] ? t('Next reversal').': '.Fmt::date($this->stats['auto_next']) : t('No reversal scheduled')" />
    </div>

    <x-ledger.panel class="lg-stack" :title="t('Manual Journal Flow')" :subtitle="t('Controlled exception path into the general ledger.')">
        <x-ledger.flow :nodes="[
            [t('Prepare'), t('Header, date, source, supporting document, debit and credit lines.'), $this->stats['draft'] ? str_replace(':n', $this->stats['draft'], t(':n draft')) : null],
            [t('Validate'), t('Debit equals credit, active COA, control-account and period checks.')],
            [t('Approve'), t('Segregation of duties and materiality approval matrix.'), $this->stats['pending'] ? str_replace(':n', $this->stats['pending'], t(':n pending')) : null],
            [t('Post'), t('Create immutable journal batch with audit metadata.')],
            [t('Reverse / Adjust'), t('Never edit posted records; reverse and post a corrected entry.')],
        ]" />
    </x-ledger.panel>

    <x-ledger.panel :title="t('Journal Workbench')" :subtitle="t('Recent manual and recurring journal entries.')">
        <div class="lg-toolbar">
            <input class="lg-search" wire:model.live.debounce.300ms="search" placeholder="{{ t('Search journal or description...') }}">
            <select class="lg-select" wire:model.live="workflow" x-data x-effect="$el.value = @js($workflow)">
                <option value="">{{ t('All workflows') }}</option>
                <option value="DRAFT">{{ t('Draft') }}</option>
                <option value="SUBMITTED">{{ t('Pending approval') }}</option>
                <option value="APPROVED">{{ t('Approved') }}</option>
                <option value="POSTED">{{ t('Posted') }}</option>
            </select>
        </div>
        <div class="lg-tablewrap">
            <table class="lg-min">
                <thead><tr><th>{{ t('Journal No.') }}</th><th>{{ t('Date') }}</th><th>{{ t('Description') }}</th><th>{{ t('Prepared By') }}</th><th class="r">{{ t('Amount') }}</th><th>{{ t('Workflow Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($this->journals as $v)
                        <tr wire:key="jv-{{ $v->voucher_id }}" wire:dblclick="edit({{ $v->voucher_id }})" style="cursor:pointer">
                            <td><span class="lg-name">{{ $v->voucher_number }}</span><span class="lg-code">{{ $this->kindLabel($v->journal_kind) }}</span></td>
                            <td>{{ Fmt::date($v->entry_date) }}</td>
                            <td>{{ $v->description }}</td>
                            <td>{{ $this->names[$v->created_by] ?? ($v->created_by === '_system' ? t('System') : $v->created_by) }}</td>
                            <td class="r lg-money">{{ Fmt::rp($v->totalAmount()) }}</td>
                            <td><x-ledger.tag :tone="$v->reversed_by_id ? 'purple' : $this->statusTone($v->status)">{{ $this->statusLabel($v) }}</x-ledger.tag></td>
                            <td class="r">
                                <div class="lg-rowmenu" x-data="{ o: false }" @click.outside="o = false">
                                    <button type="button" class="lg-dots" @click="o = !o">⋮</button>
                                    <div class="m" x-show="o" x-cloak>
                                        <button type="button" wire:click="openView({{ $v->voucher_id }})">{{ t('Open') }}</button>
                                        @if ($v->status === 'DRAFT')
                                            <button type="button" wire:click="edit({{ $v->voucher_id }})">{{ t('Edit') }}</button>
                                            <button type="button" wire:click="act('submit', {{ $v->voucher_id }})">{{ t('Submit for approval') }}</button>
                                            <button type="button" wire:click="act('delete', {{ $v->voucher_id }})" wire:confirm="{{ t('Delete this draft?') }}">{{ t('Delete draft') }}</button>
                                        @elseif ($v->status === 'SUBMITTED')
                                            <button type="button" wire:click="act('approve', {{ $v->voucher_id }})">{{ t('Approve') }}</button>
                                            <button type="button" wire:click="act('return', {{ $v->voucher_id }})">{{ t('Return to draft') }}</button>
                                        @elseif ($v->status === 'APPROVED')
                                            <button type="button" wire:click="act('post', {{ $v->voucher_id }})">{{ t('Post to GL') }}</button>
                                            <button type="button" wire:click="act('return', {{ $v->voucher_id }})">{{ t('Return to draft') }}</button>
                                        @elseif ($v->status === 'POSTED' && ! $v->reversed_by_id && $v->journal_kind !== 'REVERSAL')
                                            <button type="button" wire:click="openReverse({{ $v->voucher_id }})">{{ t('Reverse') }}</button>
                                        @endif
                                        @if ($v->journal_kind === 'RECURRING')
                                            <button type="button" wire:click="useTemplate({{ $v->voucher_id }})">{{ t('Use as template') }}</button>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="lg-empty">{{ t('No journal entries yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="lg-foot"><span>{{ str_replace(':n', $this->journals->count(), t('Showing :n journals')) }}</span><span>{{ t('Double-click a row to open') }}</span></div>
    </x-ledger.panel>

    @if ($modal === 'form')
        <x-ledger.modal :title="$editingId ? t('Edit Journal Entry') : t('Create Journal Entry')" size="xl">
            <div class="lg-mb">
                @if ($errorMessage)<div class="lg-alert err lg-field full">{{ $errorMessage }}</div>@endif
                <x-ledger.field :label="t('Description')" name="jv_desc" full><input wire:model="jv_desc" placeholder="{{ t('e.g. Office utilities accrual') }}"></x-ledger.field>
                <x-ledger.field :label="t('Journal type')" name="jv_kind">
                    <select wire:model.live="jv_kind" x-data x-effect="$el.value = @js($jv_kind)">
                        <option value="MANUAL">{{ t('Manual Journal') }}</option>
                        <option value="ACCRUAL">{{ t('Accrual Journal') }}</option>
                        <option value="RECURRING">{{ t('Recurring Journal') }}</option>
                        <option value="ADJUSTMENT">{{ t('Adjustment Journal') }}</option>
                        <option value="OPENING">{{ t('Opening Balance') }}</option>
                    </select>
                </x-ledger.field>
                <x-ledger.field :label="t('Transaction date')" name="jv_date"><input type="date" wire:model="jv_date"></x-ledger.field>
                <x-ledger.field :label="t('Reference number')" name="jv_ref"><input wire:model="jv_ref" placeholder="{{ t('e.g., REF-2026-0001') }}"></x-ledger.field>
                @if ($jv_kind === 'ACCRUAL')
                    <x-ledger.field :label="t('Auto-reverse date')" name="jv_autorev" :hint="t('The reversing journal is posted automatically on this date.')"><input type="date" wire:model="jv_autorev"></x-ledger.field>
                @endif
                <div class="lg-field full">
                    <table class="lg-lines">
                        <thead><tr><th style="width:36%">{{ t('Account') }}</th><th style="width:15%">{{ t('Debit') }}</th><th style="width:15%">{{ t('Credit') }}</th><th style="width:15%">{{ t('Cost center') }}</th><th>{{ t('Narration') }}</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($lines as $i => $l)
                                <tr wire:key="ln-{{ $i }}">
                                    <td><select wire:model="lines.{{ $i }}.account_id" x-data x-effect="$el.value = @js((string) $l['account_id'])"><option value="">{{ t('— account —') }}</option>@foreach ($this->accountOptions as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select></td>
                                    <td><input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="lines.{{ $i }}.debit" style="text-align:right"></td>
                                    <td><input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="lines.{{ $i }}.credit" style="text-align:right"></td>
                                    <td><select wire:model="lines.{{ $i }}.cc_id" x-data x-effect="$el.value = @js((string) $l['cc_id'])"><option value="">—</option>@foreach ($this->ccOptions as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select></td>
                                    <td><input wire:model="lines.{{ $i }}.narration"></td>
                                    <td><button type="button" class="lg-x" wire:click="removeLine({{ $i }})">×</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="lg-foot" style="padding:10px 4px">
                        <button type="button" class="lg-link" wire:click="addLine">＋ {{ t('Add Line') }}</button>
                        <span>{{ t('Debit') }} <b class="lg-money">{{ Fmt::rp($this->totals()[0]) }}</b> · {{ t('Credit') }} <b class="lg-money">{{ Fmt::rp($this->totals()[1]) }}</b>
                            @if (abs($this->totals()[0] - $this->totals()[1]) < 0.01 && $this->totals()[0] > 0)<x-ledger.tag tone="good">{{ t('Balanced') }}</x-ledger.tag>@else<x-ledger.tag tone="bad">{{ str_replace(':amount', Fmt::rp($this->totals()[0] - $this->totals()[1]), t('Difference :amount')) }}</x-ledger.tag>@endif
                        </span>
                    </div>
                </div>
            </div>
            <x-slot:footer>
                <button type="button" class="lg-btn light" wire:click="closeModal">{{ t('Cancel') }}</button>
                <button type="button" class="lg-btn light" wire:click="save(false)">{{ t('Save') }}</button>
                <button type="button" class="lg-btn primary" wire:click="save(true)">{{ t('Submit for Approval') }}</button>
            </x-slot:footer>
        </x-ledger.modal>
    @endif

    @if ($modal === 'view' && $this->viewing)
        @foreach ([$this->viewing] as $v)
        <x-ledger.modal :title="$v->voucher_number" size="xl">
            @if ($errorMessage)<div class="lg-alert err" style="margin:14px 20px 0">{{ $errorMessage }}</div>@endif
            <dl class="lg-kv" style="padding-top:16px">
                <dt>{{ t('Journal') }}</dt><dd>{{ $v->journal?->journal_name }} · {{ $this->kindLabel($v->journal_kind) }}</dd>
                <dt>{{ t('Date') }}</dt><dd>{{ Fmt::date($v->entry_date) }}</dd>
                <dt>{{ t('Description') }}</dt><dd>{{ $v->description }}</dd>
                <dt>{{ t('Status') }}</dt><dd><x-ledger.tag :tone="$this->statusTone($v->status)">{{ $this->statusLabel($v) }}</x-ledger.tag></dd>
                <dt>{{ t('Audit trail') }}</dt><dd>{{ t('Prepared') }} {{ $v->created_by }} · {{ t('Submitted') }} {{ $v->submitted_by ?? '—' }} · {{ t('Approved') }} {{ $v->approved_by ?? '—' }} · {{ t('Posted') }} {{ $v->posted_by ?? '—' }} {{ $v->posted_at?->format('d M Y H:i') }}</dd>
                @if ($v->reversal_of_id)<dt>{{ t('Reverses') }}</dt><dd>{{ $v->reference_no }}</dd>@endif
                @if ($v->auto_reverse_date)<dt>{{ t('Auto-reverse') }}</dt><dd>{{ Fmt::date($v->auto_reverse_date) }}</dd>@endif
            </dl>
            <div class="lg-tablewrap" style="padding:0 20px 16px">
                <table>
                    <thead><tr><th>{{ t('Account') }}</th><th>{{ t('Narration') }}</th><th class="r">{{ t('Debit') }}</th><th class="r">{{ t('Credit') }}</th></tr></thead>
                    <tbody>
                        @foreach ($v->lines as $l)
                            <tr><td><span class="lg-name">{{ $l->account?->account_code }}</span><span class="lg-code">{{ $l->account?->account_name }}</span></td><td>{{ $l->narration }}</td><td class="r lg-money">{{ Fmt::rp($l->debit, true) }}</td><td class="r lg-money">{{ Fmt::rp($l->credit, true) }}</td></tr>
                        @endforeach
                        <tr><td colspan="2"><b>{{ t('Total') }}</b></td><td class="r lg-money">{{ Fmt::rp($v->lines->sum('debit')) }}</td><td class="r lg-money">{{ Fmt::rp($v->lines->sum('credit')) }}</td></tr>
                    </tbody>
                </table>
            </div>
            <x-slot:footer>
                @if ($v->status === 'SUBMITTED')
                    <button type="button" class="lg-btn light" wire:click="act('return', {{ $v->voucher_id }})">{{ t('Return to Draft') }}</button>
                    <button type="button" class="lg-btn primary" wire:click="act('approve', {{ $v->voucher_id }})">{{ t('Approve') }}</button>
                @elseif ($v->status === 'APPROVED')
                    <button type="button" class="lg-btn primary" wire:click="act('post', {{ $v->voucher_id }})">{{ t('Post to GL') }}</button>
                @elseif ($v->status === 'DRAFT')
                    <button type="button" class="lg-btn primary" wire:click="edit({{ $v->voucher_id }})">{{ t('Edit') }}</button>
                @elseif ($v->status === 'POSTED' && ! $v->reversed_by_id && $v->journal_kind !== 'REVERSAL')
                    <button type="button" class="lg-btn light" wire:click="openReverse({{ $v->voucher_id }})">{{ t('Reverse') }}</button>
                @endif
                <button type="button" class="lg-btn light" wire:click="closeModal">{{ t('Close') }}</button>
            </x-slot:footer>
        </x-ledger.modal>
        @endforeach
    @endif

    @if ($modal === 'reverse')
        <x-ledger.modal :title="t('Reverse Journal')">
            <div class="lg-mb">
                @if ($errorMessage)<div class="lg-alert err lg-field full">{{ $errorMessage }}</div>@endif
                <x-ledger.field :label="t('Reversal date')" name="rev_date"><input type="date" wire:model="rev_date"></x-ledger.field>
                <x-ledger.field :label="t('Reason')" name="rev_reason"><input wire:model="rev_reason" placeholder="{{ t('e.g. wrong cost center') }}"></x-ledger.field>
                <p class="lg-sub lg-field full">{{ t('Debit and credit are fully reversed into a new POSTED voucher. The original voucher stays intact as the audit trail.') }}</p>
            </div>
            <x-slot:footer>
                <button type="button" class="lg-btn light" wire:click="closeModal">{{ t('Cancel') }}</button>
                <button type="button" class="lg-btn primary" wire:click="reverse">{{ t('Post Reversal') }}</button>
            </x-slot:footer>
        </x-ledger.modal>
    @endif

    @if ($modal === 'templates')
        <x-ledger.modal :title="t('Journal Templates')">
            <div class="lg-list" style="padding-top:10px">
                @forelse ($this->templates as $t)
                    <x-ledger.row icon="↻" tone="bl" :title="$t->description" :text="t('Last posted').' '.Fmt::date($t->entry_date).' · '.Fmt::rp($t->totalAmount())">
                        <button type="button" class="lg-link" wire:click="useTemplate({{ $t->voucher_id }})">{{ t('Use') }}</button>
                    </x-ledger.row>
                @empty
                    <div class="lg-empty">{{ t('No posted Recurring Journal yet to use as a template.') }}</div>
                @endforelse
            </div>
        </x-ledger.modal>
    @endif
</x-ledger.page>
