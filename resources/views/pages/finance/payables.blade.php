<?php

use App\Livewire\Concerns\OpensFromQuery;
use App\Livewire\Concerns\FinanceScreen;
use App\Models\Acc\FinAccount;
use App\Models\Acc\FinInvoice;
use App\Models\Master\FinPaymentTerm;
use App\Models\Master\SupSupplier;
use App\Models\Pur\PvReceipt;
use App\Services\Acc\ArApService;
use App\Services\Acc\JournalPostingService;
use App\Support\Finance\Fmt;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Procure to Pay / Accounts Payable -- mockup blueprint: Bill Capture ->
// Validate -> Approve Bill -> Payment Run -> Pay & Reconcile.
// Tagihan dari PV mendebit GR/IR Clearing (211900) yang dikredit saat barang
// diterima (PvPostingService) -- 3-way match PO -> PV -> Bill.
// Approval mengikuti fin_approval_rule proses VENDOR_BILL.
new #[Title('Procure to Pay — TELASIH MAKMUR ERP')] class extends Component
{
    use OpensFromQuery;

    // Dibuka dari grid v2 (?open={id}).
    protected function openFromQuery(int $id): void
    {
        $this->openView($id);
    }

    // Dibuka dari grid v2 (+ New Record -> ?new=1).
    protected function newFromQuery(): void
    {
        $this->openCreate();
    }

    use FinanceScreen;

    public string $search = '';

    public string $statusFilter = '';

    public string $supQuery = '';

    public ?int $supplier_id = null;

    public string $supplier_label = '';

    public string $partner_ref = '';

    public string $pv_id = '';

    public string $bill_date = '';

    public string $due_date = '';

    public string $description = '';

    public string $expense_account_id = '';

    public string $subtotal = '';

    public bool $with_ppn = true;

    public string $pph_code = '';

    public string $tax_invoice_no = '';

    public ?int $paySupplierId = null;

    public string $pay_date = '';

    public string $pay_bank = '';

    public string $pay_note = '';

    public array $alloc = [];

    public ?int $viewId = null;

    #[Computed]
    public function bills()
    {
        $q = FinInvoice::where('invoice_type', 'SUPPLIER_BILL')
            ->when($this->statusFilter === 'OPEN', fn ($q) => $q->where('doc_status', 'POSTED')->where('payment_status', '<>', 'PAID'))
            ->when($this->statusFilter === 'DUE', fn ($q) => $q->where('doc_status', 'POSTED')->where('payment_status', '<>', 'PAID')->where('due_date', '<=', now()->addDays(7)->toDateString()))
            ->when($this->statusFilter === 'PAID', fn ($q) => $q->where('payment_status', 'PAID'))
            ->when($this->statusFilter === 'APPROVAL', fn ($q) => $q->whereIn('doc_status', ['DRAFT', 'SUBMITTED']));
        if ($this->search !== '') {
            $ids = SupSupplier::where('supplier_name', 'like', "%{$this->search}%")->limit(200)->pluck('id_supplier');
            $q->where(fn ($w) => $w->where('invoice_number', 'like', "%{$this->search}%")->orWhere('partner_ref', 'like', "%{$this->search}%")->orWhere('description', 'like', "%{$this->search}%")->orWhereIn('partner_id', $ids));
        }

        return $q->orderByDesc('invoice_date')->orderByDesc('invoice_id')->limit(300)->get();
    }

    #[Computed]
    public function suppliers(): array
    {
        return SupSupplier::withoutGlobalScopes()->whereIn('id_supplier', $this->bills->pluck('partner_id')->merge([$this->paySupplierId])->filter()->unique())
            ->pluck('supplier_name', 'id_supplier')->all();
    }

    #[Computed]
    public function stats(): array
    {
        $open = FinInvoice::where('invoice_type', 'SUPPLIER_BILL')->where('doc_status', 'POSTED')->where('payment_status', '<>', 'PAID')->get();
        $due = $open->filter(fn ($i) => $i->due_date->lte(today()->addDays(7)));
        $from = now()->startOfMonth()->toDateString();
        $to = now()->endOfMonth()->toDateString();
        $month = FinInvoice::where('invoice_type', 'SUPPLIER_BILL')->where('doc_status', 'POSTED')->whereBetween('invoice_date', [$from, $to]);

        return [
            'open' => $open->sum(fn ($i) => $i->outstanding()), 'open_n' => $open->count(),
            'due' => $due->sum(fn ($i) => $i->outstanding()), 'due_n' => $due->count(),
            'ppn' => (float) (clone $month)->sum('tax_amount'),
            'unvalidated' => (clone $month)->where('tax_amount', '>', 0)->where('tax_validated', false)->count(),
            'pph' => (float) (clone $month)->sum('pph_amount'),
            'pending' => FinInvoice::where('invoice_type', 'SUPPLIER_BILL')->whereIn('doc_status', ['DRAFT', 'SUBMITTED'])->count(),
        ];
    }

    #[Computed]
    public function supResults()
    {
        if (mb_strlen($this->supQuery) < 2) {
            return collect();
        }

        return SupSupplier::where('activate', true)->where(fn ($q) => $q->where('supplier_name', 'like', "%{$this->supQuery}%")->orWhere('supplier_code', 'like', "%{$this->supQuery}%"))
            ->orderBy('supplier_name')->limit(8)->get(['id_supplier', 'supplier_code', 'supplier_name', 'payment_term']);
    }

    #[Computed]
    public function pvOptions(): array
    {
        return $this->supplier_id ? PvReceipt::where('i_supplier_id', $this->supplier_id)->orderByDesc('i_doc_date')->limit(30)->get()
            ->mapWithKeys(fn ($p) => [$p->pv_id => $p->doc_no.' · '.$p->i_doc_date?->format('d M Y')])->all() : [];
    }

    #[Computed]
    public function debitOptions(): array
    {
        return FinAccount::where('is_postable', true)->where('activate', true)
            ->where(fn ($q) => $q->where('account_code', '211900')->orWhere('account_code', 'like', '113%')->orWhere('account_code', 'like', '115%')
                ->orWhere('account_code', 'like', '12%')->orWhere('account_code', 'like', '13%')->orWhere('account_code', 'like', '5%')->orWhere('account_code', 'like', '6%'))
            ->where('account_code', 'not like', '1219%')->where('account_code', 'not like', '1319%')
            ->orderBy('account_code')->get()->mapWithKeys(fn ($a) => [$a->account_id => $a->account_code.' · '.$a->account_name])->all();
    }

    #[Computed]
    public function openForPay()
    {
        return $this->paySupplierId ? app(ArApService::class)->openInvoices('SUPPLIER_BILL', $this->paySupplierId) : collect();
    }

    #[Computed]
    public function viewing(): ?FinInvoice
    {
        return $this->viewId ? FinInvoice::with('allocations.voucher', 'voucher.lines.account')->find($this->viewId) : null;
    }

    public function openCreate(): void
    {
        $this->reset(['supQuery', 'supplier_id', 'supplier_label', 'partner_ref', 'pv_id', 'description', 'subtotal', 'pph_code', 'tax_invoice_no']);
        $this->bill_date = now()->toDateString();
        $this->due_date = now()->addDays(30)->toDateString();
        $this->with_ppn = true;
        $this->expense_account_id = (string) FinAccount::where('account_code', '211900')->value('account_id');
        $this->modal = 'create';
    }

    public function pickSupplier(int $id): void
    {
        $s = SupSupplier::findOrFail($id);
        $this->supplier_id = $s->id_supplier;
        $this->supplier_label = $s->supplier_name;
        $this->supQuery = '';
        $this->pph_code = $s->is_subcon ? 'PPH23' : '';
        $days = FinPaymentTerm::firstWhere('payment_term_code', $s->payment_term)?->days ?? (preg_match('/(\d+)/', (string) $s->payment_term, $m) ? (int) $m[1] : 30);
        $this->due_date = \Carbon\Carbon::parse($this->bill_date ?: now())->addDays($days)->toDateString();
    }

    public function saveBill(bool $post, ArApService $svc, JournalPostingService $jps): void
    {
        $this->validate([
            'supplier_id' => 'required|integer',
            'partner_ref' => 'required|string|max:50',
            'bill_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:bill_date',
            'description' => 'required|string|max:255',
            'expense_account_id' => 'required|exists:tm2acc.fin_account,account_id',
            'subtotal' => 'required|numeric|min:1',
            'pph_code' => 'nullable|in:PPH23,PPH22',
        ], [], ['supplier_id' => t('Vendor'), 'partner_ref' => t('Vendor bill no.'), 'subtotal' => t('Net amount')]);

        $msg = t('Bill draft saved.');
        $ok = $this->attempt(function () use ($svc, $jps, $post, &$msg) {
            $bill = $svc->createVendorBill([
                'supplier_id' => $this->supplier_id, 'partner_ref' => $this->partner_ref, 'invoice_date' => $this->bill_date,
                'due_date' => $this->due_date, 'subtotal' => $this->subtotal, 'ppn' => $this->with_ppn, 'pph_code' => $this->pph_code ?: null,
                'expense_account_id' => (int) $this->expense_account_id, 'description' => $this->description,
                'source_type' => $this->pv_id ? 'PV' : 'MANUAL', 'source_id' => $this->pv_id ?: null,
                'tax_invoice_no' => $this->tax_invoice_no ?: null,
            ], $this->login(), false);
            if (! $post) {
                return;
            }
            if ($jps->requiresApproval('VENDOR_BILL', (float) $bill->total_amount)) {
                $bill->update(['doc_status' => 'SUBMITTED']);
                $msg = t('Bill >= approval limit — submitted, awaiting approval before posting.');

                return;
            }
            $svc->postVendorBill($bill, $this->login());
            $msg = t('Bill posted: Dr Expense/GR-IR + Input VAT / Cr Payable.');
        });
        if ($ok) {
            session()->flash('flash', $msg);
            $this->closeModal();
            unset($this->bills, $this->stats);
        }
    }

    public function approveBill(int $id, ArApService $svc, JournalPostingService $jps): void
    {
        $this->attempt(function () use ($id, $svc, $jps) {
            $bill = FinInvoice::findOrFail($id);
            $rule = $jps->ruleFor('VENDOR_BILL', (float) $bill->total_amount);
            if ($rule) {
                $user = Auth::user();
                abort_unless($jps->canApprove($user, $rule->approver_group), 422, str_replace(':group', $rule->approver_group, t('You are not authorized to approve this bill (approver: :group).')));
                abort_if($rule->segregation_of_duties && ! $user->is_admin && $bill->created_by === $user->login, 422, t('Segregation of duties: the person who recorded the bill may not approve it.'));
            }
            $svc->postVendorBill($bill, $this->login());
        }, t('Bill approved & posted to the GL.'));
        unset($this->bills, $this->stats, $this->viewing);
    }

    public function validateTax(int $id): void
    {
        $this->attempt(fn () => FinInvoice::findOrFail($id)->update(['tax_validated' => true, 'updated_by' => $this->login()]), t('Input tax invoice marked as validated.'));
        unset($this->bills, $this->stats);
    }

    public function cancelDraft(int $id): void
    {
        $this->attempt(function () use ($id) {
            $i = FinInvoice::findOrFail($id);
            abort_if($i->doc_status === 'POSTED', 422, t('A posted bill cannot be cancelled here — use a reversing journal.'));
            $i->update(['doc_status' => 'CANCELLED', 'updated_by' => $this->login()]);
        }, t('Bill draft cancelled.'));
        unset($this->bills, $this->stats);
    }

    public function openPay(int $billId): void
    {
        $bill = FinInvoice::findOrFail($billId);
        $this->paySupplierId = $bill->partner_id;
        $this->pay_date = now()->toDateString();
        $this->pay_bank = (string) FinAccount::where('account_code', '111200')->value('account_id');
        $this->pay_note = '';
        unset($this->openForPay, $this->suppliers);
        $this->alloc = [];
        foreach ($this->openForPay as $o) {
            $this->alloc[$o->invoice_id] = $o->invoice_id === $billId ? (string) $o->outstanding() : '';
        }
        $this->modal = 'pay';
    }

    public function pay(ArApService $svc): void
    {
        $this->validate(['pay_date' => 'required|date', 'pay_bank' => 'required|exists:tm2acc.fin_account,account_id']);
        $alloc = array_filter($this->alloc, fn ($v) => (float) $v > 0);
        if ($this->attempt(fn () => $svc->settle('PAYMENT', (int) $this->pay_bank, $this->pay_date, $alloc, $this->login(), $this->pay_note), t('Payment posted: Dr Payable / Cr Bank.'))) {
            $this->closeModal();
            unset($this->bills, $this->stats);
        }
    }

    public function openView(int $id): void
    {
        $this->viewId = $id;
        $this->modal = 'view';
    }

    public function statusOf(FinInvoice $i): array
    {
        return match (true) {
            $i->doc_status === 'CANCELLED' => ['Cancelled', 'mute'],
            $i->doc_status === 'SUBMITTED' => ['Pending approval', 'warn'],
            $i->doc_status === 'DRAFT' => ['Draft', 'warn'],
            $i->payment_status === 'PAID' => ['Paid', 'good'],
            $i->due_date->lt(today()) => ['Overdue', 'bad'],
            $i->due_date->lte(today()->addDays(7)) => ['Due this week', 'warn'],
            default => [$i->payment_status === 'PARTIAL' ? 'Partially paid' : 'Open', 'info'],
        };
    }
};
?>

<x-ledger.page :title="t('Procure to Pay')" :subtitle="t('Vendor bills, approval, payment runs, payables, input VAT, and withholding tax.')">
    <x-slot:actions>
        <button type="button" class="lg-btn light" wire:click="$set('statusFilter', 'DUE')">⇩ {{ t('AP Aging') }}</button>
        <button type="button" class="lg-btn primary" wire:click="openCreate">＋ {{ t('Record Bill') }}</button>
    </x-slot:actions>

    <div class="lg-cards">
        <x-ledger.metric :label="t('Open Payables')" icon="⇩" :value="Fmt::short($this->stats['open'])" :sub="str_replace(':n', $this->stats['open_n'], t(':n vendor bills'))" />
        <x-ledger.metric :label="t('Due This Week')" icon="◷" :value="Fmt::short($this->stats['due'])" :pill="str_replace(':n', $this->stats['due_n'], t(':n bills'))" tone="warn" />
        <x-ledger.metric :label="t('Input PPN')" icon="%" :value="Fmt::short($this->stats['ppn'])" :pill="$this->stats['unvalidated'] ? str_replace(':n', $this->stats['unvalidated'], t(':n invoices to validate')) : t('All validated')" :tone="$this->stats['unvalidated'] ? 'warn' : 'good'" />
        <x-ledger.metric :label="t('PPh Withheld')" icon="%" :value="Fmt::short($this->stats['pph'])" :sub="t('PPh 23').', '.now()->format('F Y')" />
    </div>

    <x-ledger.panel class="lg-stack" :title="t('Procure-to-Pay Flow')" :subtitle="t('Three-way approval and accounting events.')">
        <x-ledger.flow :nodes="[
            [t('Bill Capture'), t('Supplier invoice, PO/receipt references, tax invoices and payment terms.'), $this->stats['pending'] ? str_replace(':n', $this->stats['pending'], t(':n in approval')) : null],
            [t('Validate'), t('Duplicate, vendor, PO, invoice total, PPN and PPh tax-code controls.'), $this->stats['unvalidated'] ? str_replace(':n', $this->stats['unvalidated'], t(':n tax inv.')) : null],
            [t('Approve Bill'), t('Dr expense/asset + PPN Masukan; Cr AP; recognize PPh payable where needed.')],
            [t('Payment Run'), t('Select due bills, approval limits, payment file creation.'), $this->stats['due_n'] ? str_replace(':n', $this->stats['due_n'], t(':n due')) : null],
            [t('Pay & Reconcile'), t('Dr AP; Cr Bank; match bank statement transaction.')],
        ]" />
    </x-ledger.panel>

    <x-ledger.panel :title="t('Vendor Bill Register')" :subtitle="t('AP subledger with invoice-level tax and payment status.')">
        <div class="lg-toolbar">
            <input class="lg-search" wire:model.live.debounce.300ms="search" placeholder="{{ t('Search vendor or bill number...') }}">
            <select class="lg-select" wire:model.live="statusFilter" x-data x-effect="$el.value = @js($statusFilter)">
                <option value="">{{ t('All statuses') }}</option><option value="OPEN">{{ t('Open') }}</option><option value="DUE">{{ t('Due') }}</option><option value="PAID">{{ t('Paid') }}</option><option value="APPROVAL">{{ t('Draft / approval') }}</option>
            </select>
        </div>
        <div class="lg-tablewrap">
            <table class="lg-min">
                <thead><tr><th>{{ t('Vendor / Bill') }}</th><th>{{ t('Bill Date') }}</th><th>{{ t('Due Date') }}</th><th>{{ t('Description') }}</th><th class="r">{{ t('Net Amount') }}</th><th class="r">{{ t('Input PPN') }}</th><th class="r">{{ t('Amount Due') }}</th><th>{{ t('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($this->bills as $i)
                        <tr wire:key="ap-{{ $i->invoice_id }}" wire:dblclick="openView({{ $i->invoice_id }})" style="cursor:pointer">
                            <td><span class="lg-name">{{ $this->suppliers[$i->partner_id] ?? '#'.$i->partner_id }}</span><span class="lg-code">{{ $i->partner_ref ?: $i->invoice_number }}</span></td>
                            <td>{{ Fmt::date($i->invoice_date) }}</td>
                            <td>{{ Fmt::date($i->due_date) }}</td>
                            <td>{{ $i->description }}</td>
                            <td class="r lg-money">{{ Fmt::rp($i->subtotal) }}</td>
                            <td class="r lg-money">{{ Fmt::rp($i->tax_amount, true) }} @if ((float) $i->tax_amount > 0 && ! $i->tax_validated)<x-ledger.tag tone="warn">!</x-ledger.tag>@endif</td>
                            <td class="r lg-money">{{ Fmt::rp($i->doc_status === 'POSTED' ? $i->outstanding() : $i->total_amount - $i->pph_amount) }}</td>
                            <td><x-ledger.tag :tone="$this->statusOf($i)[1]">{{ t($this->statusOf($i)[0]) }}</x-ledger.tag></td>
                            <td class="r">
                                <div class="lg-rowmenu" x-data="{ o: false }" @click.outside="o = false">
                                    <button type="button" class="lg-dots" @click="o = !o">⋮</button>
                                    <div class="m" x-show="o" x-cloak>
                                        <button type="button" wire:click="openView({{ $i->invoice_id }})">{{ t('Open') }}</button>
                                        @if (in_array($i->doc_status, ['DRAFT', 'SUBMITTED'], true))
                                            <button type="button" wire:click="approveBill({{ $i->invoice_id }})">{{ t('Approve & post') }}</button>
                                            <button type="button" wire:click="cancelDraft({{ $i->invoice_id }})" wire:confirm="{{ t('Cancel this bill?') }}">{{ t('Cancel') }}</button>
                                        @endif
                                        @if ((float) $i->tax_amount > 0 && ! $i->tax_validated)
                                            <button type="button" wire:click="validateTax({{ $i->invoice_id }})">{{ t('Validate tax invoice') }}</button>
                                        @endif
                                        @if ($i->doc_status === 'POSTED' && $i->payment_status !== 'PAID')
                                            <button type="button" wire:click="openPay({{ $i->invoice_id }})">{{ t('Pay bill') }}</button>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="lg-empty">{{ t('No vendor bills.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="lg-foot"><span>{{ str_replace(':n', $this->bills->count(), t('Showing :n bills')) }}</span><span>{{ t('Double-click a row to open') }}</span></div>
    </x-ledger.panel>

    @if ($modal === 'create')
        <x-ledger.modal :title="t('Record Vendor Bill')">
            <div class="lg-mb">
                @if ($errorMessage)<div class="lg-alert err lg-field full">{{ $errorMessage }}</div>@endif
                <x-ledger.field :label="t('Vendor')" name="supplier_id" full>
                    @if ($supplier_id)
                        <div class="lg-actions"><b>{{ $supplier_label }}</b><button type="button" class="lg-link" wire:click="$set('supplier_id', null)">{{ t('change') }}</button></div>
                    @else
                        <input wire:model.live.debounce.300ms="supQuery" placeholder="{{ t('Type vendor name or code...') }}">
                        @foreach ($this->supResults as $s)
                            <button type="button" class="lg-btn light sm" style="justify-content:flex-start" wire:click="pickSupplier({{ $s->id_supplier }})">{{ $s->supplier_code }} · {{ $s->supplier_name }}</button>
                        @endforeach
                    @endif
                </x-ledger.field>
                <x-ledger.field :label="t('Vendor bill number')" name="partner_ref"><input wire:model="partner_ref" placeholder="{{ t('e.g. INV-ST-0973') }}"></x-ledger.field>
                <x-ledger.field :label="t('Goods receipt (PV)')" name="pv_id" :hint="t('3-way match: a bill from a PV debits GR/IR Clearing.')">
                    <select wire:model="pv_id" x-data x-effect="$el.value = @js($pv_id)"><option value="">{{ t('— no PV (service/expense) —') }}</option>@foreach ($this->pvOptions as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select>
                </x-ledger.field>
                <x-ledger.field :label="t('Description')" name="description" full><input wire:model="description" placeholder="{{ t('e.g. Ivory Paper 310 gsm — Sept') }}"></x-ledger.field>
                <x-ledger.field :label="t('Bill date')" name="bill_date"><input type="date" wire:model="bill_date"></x-ledger.field>
                <x-ledger.field :label="t('Due date')" name="due_date"><input type="date" wire:model="due_date"></x-ledger.field>
                <x-ledger.field :label="t('Debit account')" name="expense_account_id" full>
                    <select wire:model="expense_account_id" x-data x-effect="$el.value = @js($expense_account_id)">@foreach ($this->debitOptions as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select>
                </x-ledger.field>
                <x-ledger.field :label="t('Net amount (IDR)')" name="subtotal"><input type="number" min="0" step="0.01" wire:model.live.debounce.400ms="subtotal"></x-ledger.field>
                <x-ledger.field :label="t('Withholding tax')" name="pph_code">
                    <select wire:model.live="pph_code" x-data x-effect="$el.value = @js($pph_code)"><option value="">{{ t('None') }}</option><option value="PPH23">{{ t('PPh 23 (services, 2%)') }}</option><option value="PPH22">{{ t('PPh 22 (1.5%)') }}</option></select>
                </x-ledger.field>
                <x-ledger.field :label="t('Tax code')" :hint="$with_ppn && $subtotal ? t('Input VAT').' '.Fmt::rp((float) $subtotal * app(\App\Services\Acc\ArApService::class)->taxRate('PPN_IN')) : null">
                    <label style="display:flex;gap:8px;align-items:center;font-weight:600"><input type="checkbox" wire:model.live="with_ppn"> {{ t('Input VAT 12% × DPP 11/12') }}</label>
                </x-ledger.field>
                <x-ledger.field :label="t('Tax Invoice No. (e-Faktur)')" name="tax_invoice_no"><input wire:model="tax_invoice_no" placeholder="{{ t('leave blank if not yet received') }}"></x-ledger.field>
            </div>
            <x-slot:footer>
                <button type="button" class="lg-btn light" wire:click="closeModal">{{ t('Cancel') }}</button>
                <button type="button" class="lg-btn light" wire:click="saveBill(false)">{{ t('Save') }}</button>
                <button type="button" class="lg-btn primary" wire:click="saveBill(true)">{{ t('Approve & Post') }}</button>
            </x-slot:footer>
        </x-ledger.modal>
    @endif

    @if ($modal === 'pay')
        <x-ledger.modal :title="t('Payment Run').' — '.($this->suppliers[$paySupplierId] ?? '')" size="xl">
            <div class="lg-mb">
                @if ($errorMessage)<div class="lg-alert err lg-field full">{{ $errorMessage }}</div>@endif
                <x-ledger.field :label="t('Payment date')" name="pay_date"><input type="date" wire:model="pay_date"></x-ledger.field>
                <x-ledger.field :label="t('Pay from')" name="pay_bank">
                    <select wire:model="pay_bank" x-data x-effect="$el.value = @js($pay_bank)">@foreach ($this->cashBankOptions() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select>
                </x-ledger.field>
                <x-ledger.field :label="t('Note / transfer reference')" full><input wire:model="pay_note" placeholder="{{ t('e.g. TRF-009821') }}"></x-ledger.field>
                <div class="lg-field full">
                    <table class="lg-lines">
                        <thead><tr><th>{{ t('Bill') }}</th><th>{{ t('Due') }}</th><th class="r">{{ t('Outstanding') }}</th><th style="width:200px">{{ t('Pay') }}</th></tr></thead>
                        <tbody>
                            @foreach ($this->openForPay as $o)
                                <tr wire:key="pal-{{ $o->invoice_id }}"><td>{{ $o->partner_ref ?: $o->invoice_number }} <span class="lg-code">{{ $o->description }}</span></td><td>{{ Fmt::date($o->due_date) }}</td><td class="r lg-money">{{ Fmt::rp($o->outstanding()) }}</td><td><input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="alloc.{{ $o->invoice_id }}" style="text-align:right"></td></tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="lg-foot" style="padding:10px 4px"><span>{{ t('Posting: Dr Payable / Cr Bank') }}</span><b class="lg-money">{{ t('Total') }} {{ Fmt::rp(array_sum(array_map('floatval', $alloc))) }}</b></div>
                </div>
            </div>
            <x-slot:footer>
                <button type="button" class="lg-btn light" wire:click="closeModal">{{ t('Cancel') }}</button>
                <button type="button" class="lg-btn primary" wire:click="pay">{{ t('Post Payment') }}</button>
            </x-slot:footer>
        </x-ledger.modal>
    @endif

    @if ($modal === 'view' && $this->viewing)
        @foreach ([$this->viewing] as $i)
            <x-ledger.modal :title="($i->partner_ref ?: $i->invoice_number)" size="xl">
                <dl class="lg-kv" style="padding-top:16px">
                    <dt>{{ t('Vendor') }}</dt><dd>{{ $this->suppliers[$i->partner_id] ?? $i->partner_id }}</dd>
                    <dt>{{ t('Internal no.') }}</dt><dd>{{ $i->invoice_number }} · {{ t('source') }} {{ $i->source_type }} {{ $i->source_id }}</dd>
                    <dt>{{ t('Description') }}</dt><dd>{{ $i->description }}</dd>
                    <dt>{{ t('Amounts') }}</dt><dd>{{ t('Net') }} {{ Fmt::rp($i->subtotal) }} · {{ t('PPN') }} {{ Fmt::rp($i->tax_amount) }} · {{ $i->pph_code ?: t('PPh') }} {{ Fmt::rp($i->pph_amount) }} · {{ t('Paid') }} {{ Fmt::rp($i->paid_amount) }}</dd>
                    <dt>{{ t('e-Faktur') }}</dt><dd>{{ $i->tax_invoice_no ?: '—' }} {{ $i->tax_validated ? '('.t('validated').')' : '' }}</dd>
                    <dt>{{ t('GL voucher') }}</dt><dd>{{ $i->voucher?->voucher_number ?? t('Not posted') }}</dd>
                </dl>
                @if ($i->voucher)
                    <div class="lg-tablewrap" style="padding:0 20px 10px"><table><thead><tr><th>{{ t('Account') }}</th><th class="r">{{ t('Debit') }}</th><th class="r">{{ t('Credit') }}</th></tr></thead><tbody>
                        @foreach ($i->voucher->lines as $l)<tr><td>{{ $l->account?->account_code }} · {{ $l->account?->account_name }}</td><td class="r lg-money">{{ Fmt::rp($l->debit, true) }}</td><td class="r lg-money">{{ Fmt::rp($l->credit, true) }}</td></tr>@endforeach
                    </tbody></table></div>
                @endif
                @if ($i->allocations->isNotEmpty())
                    <div class="lg-list"><b style="font-size:12px">{{ t('Payments') }}</b>
                        @foreach ($i->allocations as $a)<x-ledger.row icon="✓" tone="gr" :title="$a->voucher?->voucher_number" :text="Fmt::date($a->alloc_date)"><span class="lg-money">{{ Fmt::rp($a->amount) }}</span></x-ledger.row>@endforeach
                    </div>
                @endif
                <x-slot:footer>
                    @if (in_array($i->doc_status, ['DRAFT', 'SUBMITTED'], true))<button type="button" class="lg-btn primary" wire:click="approveBill({{ $i->invoice_id }})">{{ t('Approve & Post') }}</button>@endif
                    @if ($i->doc_status === 'POSTED' && $i->payment_status !== 'PAID')<button type="button" class="lg-btn primary" wire:click="openPay({{ $i->invoice_id }})">{{ t('Pay Bill') }}</button>@endif
                    <button type="button" class="lg-btn light" wire:click="closeModal">{{ t('Close') }}</button>
                </x-slot:footer>
            </x-ledger.modal>
        @endforeach
    @endif
</x-ledger.page>
