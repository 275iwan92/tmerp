<?php

use App\Livewire\Concerns\FinanceScreen;
use App\Models\Acc\FinInvoice;
use App\Models\Master\CustCustomer;
use App\Models\Master\FinPaymentTerm;
use App\Models\Sdi\CoOrder;
use App\Services\Acc\ArApService;
use App\Support\Finance\Fmt;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Order to Cash / Accounts Receivable -- gabungan 2 mockup blueprint:
// alur O2C (Invoice Draft -> Approval -> Post -> Receive -> Allocate) +
// Customer Invoice Register (subledger AR, roll-up ke akun 112x00).
// Posting: Dr Piutang / Cr Pendapatan / Cr PPN Keluaran. Penerimaan:
// Dr Bank / Cr Piutang, alokasi tak boleh melebihi sisa faktur.
new #[Title('Order to Cash — TELASIH MAKMUR ERP')] class extends Component
{
    use FinanceScreen;

    public string $search = '';

    public string $statusFilter = '';

    // form faktur
    public string $custQuery = '';

    public ?int $customer_id = null;

    public string $customer_label = '';

    public string $co_id = '';

    public string $inv_date = '';

    public string $due_date = '';

    public string $description = '';

    public string $revenue_account_id = '';

    public string $subtotal = '';

    public bool $with_ppn = true;

    public string $tax_invoice_no = '';

    // form penerimaan
    public ?int $payCustomerId = null;

    public string $pay_date = '';

    public string $pay_bank = '';

    public string $pay_note = '';

    public array $alloc = [];

    public ?int $viewId = null;

    #[Computed]
    public function invoices()
    {
        $q = FinInvoice::where('invoice_type', 'CUSTOMER_INVOICE')
            ->when($this->statusFilter === 'OPEN', fn ($q) => $q->where('doc_status', 'POSTED')->where('payment_status', '<>', 'PAID'))
            ->when($this->statusFilter === 'OVERDUE', fn ($q) => $q->where('doc_status', 'POSTED')->where('payment_status', '<>', 'PAID')->where('due_date', '<', now()->toDateString()))
            ->when($this->statusFilter === 'PAID', fn ($q) => $q->where('payment_status', 'PAID'))
            ->when($this->statusFilter === 'DRAFT', fn ($q) => $q->where('doc_status', '<>', 'POSTED'));
        if ($this->search !== '') {
            $ids = CustCustomer::where('customer_name', 'like', "%{$this->search}%")->limit(200)->pluck('id_customer');
            $q->where(fn ($w) => $w->where('invoice_number', 'like', "%{$this->search}%")->orWhere('description', 'like', "%{$this->search}%")->orWhereIn('partner_id', $ids));
        }

        return $q->orderByDesc('invoice_date')->orderByDesc('invoice_id')->limit(300)->get();
    }

    #[Computed]
    public function customers(): array
    {
        return CustCustomer::withoutGlobalScopes()->whereIn('id_customer', $this->invoices->pluck('partner_id')->merge([$this->payCustomerId])->filter()->unique())
            ->pluck('customer_name', 'id_customer')->all();
    }

    #[Computed]
    public function stats(): array
    {
        $open = FinInvoice::where('invoice_type', 'CUSTOMER_INVOICE')->where('doc_status', 'POSTED')->where('payment_status', '<>', 'PAID')->get();
        $overdue = $open->filter(fn ($i) => $i->due_date->lt(today()));
        $mtdFrom = now()->startOfMonth()->toDateString();
        $prevFrom = now()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $prevTo = now()->subMonthNoOverflow()->day(min(now()->day, now()->subMonthNoOverflow()->daysInMonth))->toDateString();
        $coll = fn ($a, $b) => (float) \App\Models\Acc\FinPaymentAlloc::whereBetween('alloc_date', [$a, $b])
            ->whereIn('invoice_id', FinInvoice::where('invoice_type', 'CUSTOMER_INVOICE')->select('invoice_id'))->sum('amount');
        $mtd = $coll($mtdFrom, now()->toDateString());
        $prev = $coll($prevFrom, $prevTo);
        $ppn = (float) FinInvoice::whereIn('invoice_type', ['CUSTOMER_INVOICE', 'CUSTOMER_DP'])->where('doc_status', 'POSTED')->whereBetween('invoice_date', [$mtdFrom, now()->endOfMonth()->toDateString()])->sum('tax_amount');

        return [
            'open' => $open->sum(fn ($i) => $i->outstanding()), 'open_n' => $open->count(),
            'overdue' => $overdue->sum(fn ($i) => $i->outstanding()), 'overdue_n' => $overdue->count(),
            'mtd' => $mtd, 'mtd_change' => $prev > 0 ? ($mtd - $prev) / $prev * 100 : null,
            'ppn' => $ppn, 'draft_n' => FinInvoice::where('invoice_type', 'CUSTOMER_INVOICE')->where('doc_status', 'DRAFT')->count(),
        ];
    }

    #[Computed]
    public function aging(): array
    {
        $b = ['Current' => 0, '1–30 days' => 0, '31–60 days' => 0, '61–90 days' => 0, '> 90 days' => 0];
        foreach (FinInvoice::where('invoice_type', 'CUSTOMER_INVOICE')->where('doc_status', 'POSTED')->where('payment_status', '<>', 'PAID')->get() as $i) {
            $d = (int) $i->due_date->diffInDays(today(), false);
            $k = $d <= 0 ? 'Current' : ($d <= 30 ? '1–30 days' : ($d <= 60 ? '31–60 days' : ($d <= 90 ? '61–90 days' : '> 90 days')));
            $b[$k] += $i->outstanding();
        }

        return $b;
    }

    #[Computed]
    public function custResults()
    {
        if (mb_strlen($this->custQuery) < 2) {
            return collect();
        }

        return CustCustomer::where('activate', true)->where(fn ($q) => $q->where('customer_name', 'like', "%{$this->custQuery}%")->orWhere('customer_code', 'like', "%{$this->custQuery}%"))
            ->orderBy('customer_name')->limit(8)->get(['id_customer', 'customer_code', 'customer_name', 'payment_term']);
    }

    #[Computed]
    public function revenueOptions(): array
    {
        return $this->postableAccountOptions('4');
    }

    #[Computed]
    public function coOptions(): array
    {
        return CoOrder::orderByDesc('co_date')->limit(50)->pluck('co_no', 'id_order')->all();
    }

    #[Computed]
    public function openForPay()
    {
        return $this->payCustomerId ? app(ArApService::class)->openInvoices('CUSTOMER_INVOICE', $this->payCustomerId) : collect();
    }

    #[Computed]
    public function viewing(): ?FinInvoice
    {
        return $this->viewId ? FinInvoice::with('allocations.voucher', 'voucher.lines.account')->find($this->viewId) : null;
    }

    public function openCreate(): void
    {
        $this->reset(['custQuery', 'customer_id', 'customer_label', 'co_id', 'description', 'subtotal', 'tax_invoice_no']);
        $this->inv_date = now()->toDateString();
        $this->due_date = now()->addDays(30)->toDateString();
        $this->with_ppn = true;
        $this->revenue_account_id = (string) array_key_first($this->revenueOptions);
        $this->modal = 'create';
    }

    public function pickCustomer(int $id): void
    {
        $c = CustCustomer::findOrFail($id);
        $this->customer_id = $c->id_customer;
        $this->customer_label = $c->customer_name;
        $this->custQuery = '';
        $days = FinPaymentTerm::find($c->payment_term)?->days ?? (preg_match('/(\d+)/', (string) $c->payment_term, $m) ? (int) $m[1] : 30);
        $this->due_date = \Carbon\Carbon::parse($this->inv_date ?: now())->addDays($days)->toDateString();
    }

    public function saveInvoice(bool $post, ArApService $svc): void
    {
        $this->validate([
            'customer_id' => 'required|integer',
            'inv_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:inv_date',
            'description' => 'required|string|max:255',
            'revenue_account_id' => 'required|exists:tm2acc.fin_account,account_id',
            'subtotal' => 'required|numeric|min:1',
        ], [], ['customer_id' => 'Customer', 'subtotal' => 'Net amount']);

        if ($this->attempt(fn () => $svc->createCustomerInvoice([
            'customer_id' => $this->customer_id, 'invoice_date' => $this->inv_date, 'due_date' => $this->due_date,
            'subtotal' => $this->subtotal, 'ppn' => $this->with_ppn, 'description' => $this->description,
            'co_id' => $this->co_id ?: null, 'revenue_account_id' => (int) $this->revenue_account_id,
            'tax_invoice_no' => $this->tax_invoice_no ?: null,
        ], $this->login(), $post), $post ? 'Faktur diposting: Dr Piutang / Cr Pendapatan / Cr PPN Keluaran.' : 'Draft faktur disimpan.')) {
            $this->closeModal();
            unset($this->invoices, $this->stats, $this->aging);
        }
    }

    public function postInvoice(int $id, ArApService $svc): void
    {
        $this->attempt(fn () => $svc->postCustomerInvoice(FinInvoice::findOrFail($id), $this->login()), 'Faktur diposting ke GL.');
        unset($this->invoices, $this->stats, $this->aging, $this->viewing);
    }

    public function cancelDraft(int $id): void
    {
        $this->attempt(function () use ($id) {
            $i = FinInvoice::findOrFail($id);
            abort_if($i->doc_status === 'POSTED', 422, 'Faktur posted tidak bisa dibatalkan di sini — buat nota kredit/jurnal pembalik.');
            $i->update(['doc_status' => 'CANCELLED', 'updated_by' => $this->login()]);
        }, 'Draft faktur dibatalkan.');
        unset($this->invoices, $this->stats);
    }

    public function openReceive(int $invoiceId): void
    {
        $inv = FinInvoice::findOrFail($invoiceId);
        $this->payCustomerId = $inv->partner_id;
        $this->pay_date = now()->toDateString();
        $this->pay_bank = (string) (\App\Models\Acc\FinAccount::where('account_code', '111200')->value('account_id'));
        $this->pay_note = '';
        unset($this->openForPay, $this->customers);
        $this->alloc = [];
        foreach ($this->openForPay as $o) {
            $this->alloc[$o->invoice_id] = $o->invoice_id === $invoiceId ? (string) $o->outstanding() : '';
        }
        $this->modal = 'receive';
    }

    public function receive(ArApService $svc): void
    {
        $this->validate(['pay_date' => 'required|date', 'pay_bank' => 'required|exists:tm2acc.fin_account,account_id']);
        $alloc = array_filter($this->alloc, fn ($v) => (float) $v > 0);
        if ($this->attempt(fn () => $svc->settle('RECEIPT', (int) $this->pay_bank, $this->pay_date, $alloc, $this->login(), $this->pay_note), 'Penerimaan dicatat & dialokasikan ke faktur.')) {
            $this->closeModal();
            unset($this->invoices, $this->stats, $this->aging);
        }
    }

    public function openView(int $id): void
    {
        $this->viewId = $id;
        $this->modal = 'view';
    }

    public function statusOf(FinInvoice $i): array
    {
        if ($i->doc_status === 'CANCELLED') {
            return ['Cancelled', 'mute'];
        }
        if ($i->doc_status !== 'POSTED') {
            return ['Draft', 'warn'];
        }
        if ($i->payment_status === 'PAID') {
            return ['Paid', 'good'];
        }
        $d = (int) $i->due_date->diffInDays(today(), false);
        if ($d > 0) {
            return [$d.' days overdue', 'bad'];
        }
        if ($d >= -7) {
            return ['Due this week', 'warn'];
        }

        return [$i->payment_status === 'PARTIAL' ? 'Partially paid' : 'Open', 'info'];
    }
};
?>

<x-ledger.page title="Order to Cash" subtitle="Customer billing, receivables, collections, receipts, and revenue recognition.">
    <x-slot:actions>
        <button type="button" class="lg-btn light" wire:click="$set('modal', 'aging')">⇩ AR Aging</button>
        <button type="button" class="lg-btn primary" wire:click="openCreate">＋ Create Invoice</button>
    </x-slot:actions>

    <div class="lg-cards">
        <x-ledger.metric label="Open Receivables" icon="⇧" :value="Fmt::short($this->stats['open'])" :sub="$this->stats['open_n'].' customer invoices'" />
        <x-ledger.metric label="Overdue" icon="!" :value="Fmt::short($this->stats['overdue'])" :pill="$this->stats['overdue_n'].' invoices'" :tone="$this->stats['overdue_n'] ? 'bad' : 'good'" />
        <x-ledger.metric label="Collections MTD" icon="✓" :value="Fmt::short($this->stats['mtd'])" :pill="$this->stats['mtd_change'] !== null ? Fmt::pct($this->stats['mtd_change']) : null" :tone="($this->stats['mtd_change'] ?? 0) >= 0 ? 'good' : 'bad'" :sub="$this->stats['mtd_change'] === null ? 'No prior-month collections' : null" />
        <x-ledger.metric label="Output PPN" icon="%" :value="Fmt::short($this->stats['ppn'])" :sub="now()->format('F').' taxable sales'" />
    </div>

    <x-ledger.panel class="lg-stack" title="Order-to-Cash Flow" subtitle="System posting rule and control points.">
        <x-ledger.flow :nodes="[
            ['Invoice Draft', 'Customer, item/service, price, tax code, due date.', $this->stats['draft_n'] ? $this->stats['draft_n'].' draft' : null],
            ['Approval', 'Credit limit, price, tax and duplicate-invoice checks.'],
            ['Post Invoice', 'Dr AR; Cr Revenue; Cr PPN Keluaran where applicable.'],
            ['Receive Cash', 'Import bank receipt or record cash collection.', $this->stats['open_n'] ? $this->stats['open_n'].' open' : null],
            ['Allocate & Close', 'Dr Bank; Cr AR; allocation never exceeds the open balance.'],
        ]" />
    </x-ledger.panel>

    <x-ledger.panel title="Customer Invoice Register" subtitle="Operational AR subledger; balances roll up to GL account 112100 / 112200.">
        <div class="lg-toolbar">
            <input class="lg-search" wire:model.live.debounce.300ms="search" placeholder="Search customer or invoice number...">
            <select class="lg-select" wire:model.live="statusFilter" x-data x-effect="$el.value = @js($statusFilter)">
                <option value="">All statuses</option><option value="OPEN">Open</option><option value="OVERDUE">Overdue</option><option value="PAID">Paid</option><option value="DRAFT">Draft</option>
            </select>
        </div>
        <div class="lg-tablewrap">
            <table class="lg-min">
                <thead><tr><th>Customer / Invoice</th><th>Invoice Date</th><th>Due Date</th><th>Description</th><th class="r">Net Amount</th><th class="r">PPN</th><th class="r">Amount Due</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($this->invoices as $i)
                        <tr wire:key="ar-{{ $i->invoice_id }}" wire:dblclick="openView({{ $i->invoice_id }})" style="cursor:pointer">
                            <td><span class="lg-name">{{ $this->customers[$i->partner_id] ?? '#'.$i->partner_id }}</span><span class="lg-code">{{ $i->invoice_number }}</span></td>
                            <td>{{ Fmt::date($i->invoice_date) }}</td>
                            <td>{{ Fmt::date($i->due_date) }}</td>
                            <td>{{ $i->description }}</td>
                            <td class="r lg-money">{{ Fmt::rp($i->subtotal) }}</td>
                            <td class="r lg-money">{{ Fmt::rp($i->tax_amount, true) }}</td>
                            <td class="r lg-money">{{ Fmt::rp($i->doc_status === 'POSTED' ? $i->outstanding() : $i->total_amount) }}</td>
                            <td><x-ledger.tag :tone="$this->statusOf($i)[1]">{{ $this->statusOf($i)[0] }}</x-ledger.tag></td>
                            <td class="r">
                                <div class="lg-rowmenu" x-data="{ o: false }" @click.outside="o = false">
                                    <button type="button" class="lg-dots" @click="o = !o">⋮</button>
                                    <div class="m" x-show="o" x-cloak>
                                        <button type="button" wire:click="openView({{ $i->invoice_id }})">Open</button>
                                        @if ($i->doc_status === 'DRAFT')
                                            <button type="button" wire:click="postInvoice({{ $i->invoice_id }})">Approve & post</button>
                                            <button type="button" wire:click="cancelDraft({{ $i->invoice_id }})" wire:confirm="Batalkan draft faktur ini?">Cancel draft</button>
                                        @elseif ($i->doc_status === 'POSTED' && $i->payment_status !== 'PAID')
                                            <button type="button" wire:click="openReceive({{ $i->invoice_id }})">Receive payment</button>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="lg-empty">No customer invoices.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="lg-foot"><span>Showing {{ $this->invoices->count() }} invoices</span><span>Double-click a row to open</span></div>
    </x-ledger.panel>

    @if ($modal === 'create')
        <x-ledger.modal title="Create Customer Invoice">
            <div class="lg-mb">
                @if ($errorMessage)<div class="lg-alert err lg-field full">{{ $errorMessage }}</div>@endif
                <x-ledger.field label="Customer" name="customer_id" full>
                    @if ($customer_id)
                        <div class="lg-actions"><b>{{ $customer_label }}</b><button type="button" class="lg-link" wire:click="$set('customer_id', null)">change</button></div>
                    @else
                        <input wire:model.live.debounce.300ms="custQuery" placeholder="Type customer name or code...">
                        @foreach ($this->custResults as $c)
                            <button type="button" class="lg-btn light sm" style="justify-content:flex-start" wire:click="pickCustomer({{ $c->id_customer }})">{{ $c->customer_code }} · {{ $c->customer_name }}</button>
                        @endforeach
                    @endif
                </x-ledger.field>
                <x-ledger.field label="Description" name="description" full><input wire:model="description" placeholder="e.g. Folding box 5,000 pcs — Sept delivery"></x-ledger.field>
                <x-ledger.field label="Invoice date" name="inv_date"><input type="date" wire:model="inv_date"></x-ledger.field>
                <x-ledger.field label="Due date" name="due_date"><input type="date" wire:model="due_date"></x-ledger.field>
                <x-ledger.field label="Revenue account" name="revenue_account_id">
                    <select wire:model="revenue_account_id" x-data x-effect="$el.value = @js($revenue_account_id)">@foreach ($this->revenueOptions as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select>
                </x-ledger.field>
                <x-ledger.field label="Customer order (optional)" name="co_id">
                    <select wire:model="co_id" x-data x-effect="$el.value = @js($co_id)"><option value="">—</option>@foreach ($this->coOptions as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select>
                </x-ledger.field>
                <x-ledger.field label="Net amount (IDR)" name="subtotal"><input type="number" min="0" step="0.01" wire:model.live.debounce.400ms="subtotal" placeholder="0"></x-ledger.field>
                <x-ledger.field label="Tax code" :hint="$with_ppn && $subtotal ? 'PPN '.Fmt::rp((float) $subtotal * app(\App\Services\Acc\ArApService::class)->taxRate('PPN_OUT')).' · Total '.Fmt::rp((float) $subtotal * (1 + app(\App\Services\Acc\ArApService::class)->taxRate('PPN_OUT'))) : null">
                    <label style="display:flex;gap:8px;align-items:center;font-weight:600"><input type="checkbox" wire:model.live="with_ppn"> PPN Keluaran 12% × DPP 11/12</label>
                </x-ledger.field>
                <x-ledger.field label="No. Faktur Pajak (e-Faktur)" name="tax_invoice_no"><input wire:model="tax_invoice_no" placeholder="010.000-26.00000001"></x-ledger.field>
            </div>
            <x-slot:footer>
                <button type="button" class="lg-btn light" wire:click="closeModal">{{ \App\Support\Localization\Translator::t('shared_btn_cancel', 'Batal') }}</button>
                <button type="button" class="lg-btn light" wire:click="saveInvoice(false)">{{ \App\Support\Localization\Translator::t('shell_btn_save', 'Simpan') }}</button>
                <button type="button" class="lg-btn primary" wire:click="saveInvoice(true)">Approve & Post</button>
            </x-slot:footer>
        </x-ledger.modal>
    @endif

    @if ($modal === 'receive')
        <x-ledger.modal title="Receive Customer Payment" size="xl">
            <div class="lg-mb">
                @if ($errorMessage)<div class="lg-alert err lg-field full">{{ $errorMessage }}</div>@endif
                <x-ledger.field label="Customer" full><b>{{ $this->customers[$payCustomerId] ?? '' }}</b></x-ledger.field>
                <x-ledger.field label="Receipt date" name="pay_date"><input type="date" wire:model="pay_date"></x-ledger.field>
                <x-ledger.field label="Deposit to" name="pay_bank">
                    <select wire:model="pay_bank" x-data x-effect="$el.value = @js($pay_bank)">@foreach ($this->cashBankOptions() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select>
                </x-ledger.field>
                <x-ledger.field label="Note / bank reference" full><input wire:model="pay_note" placeholder="e.g. RCV-009818"></x-ledger.field>
                <div class="lg-field full">
                    <table class="lg-lines">
                        <thead><tr><th>Invoice</th><th>Due</th><th class="r">Outstanding</th><th style="width:200px">Allocate</th></tr></thead>
                        <tbody>
                            @foreach ($this->openForPay as $o)
                                <tr wire:key="al-{{ $o->invoice_id }}"><td>{{ $o->invoice_number }} <span class="lg-code">{{ $o->description }}</span></td><td>{{ Fmt::date($o->due_date) }}</td><td class="r lg-money">{{ Fmt::rp($o->outstanding()) }}</td><td><input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="alloc.{{ $o->invoice_id }}" style="text-align:right"></td></tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="lg-foot" style="padding:10px 4px"><span>Posting: Dr Bank / Cr Piutang</span><b class="lg-money">Total {{ Fmt::rp(array_sum(array_map('floatval', $alloc))) }}</b></div>
                </div>
            </div>
            <x-slot:footer>
                <button type="button" class="lg-btn light" wire:click="closeModal">{{ \App\Support\Localization\Translator::t('shared_btn_cancel', 'Batal') }}</button>
                <button type="button" class="lg-btn primary" wire:click="receive">Post Receipt</button>
            </x-slot:footer>
        </x-ledger.modal>
    @endif

    @if ($modal === 'aging')
        <x-ledger.modal title="AR Aging — as at {{ Fmt::date(now()) }}">
            <div class="lg-aging" style="padding-top:12px">
                @foreach ($this->aging as $bucket => $amt)
                    <div class="lg-age">
                        <div><span>{{ $bucket }}</span><b class="lg-money">{{ Fmt::rp($amt) }}</b></div>
                        <div class="lg-bar"><i style="width: {{ array_sum($this->aging) > 0 ? round($amt / array_sum($this->aging) * 100, 1) : 0 }}%; {{ $loop->index >= 2 ? 'background:#c23b4e' : '' }}"></i></div>
                    </div>
                @endforeach
                <div class="lg-foot" style="padding:12px 0 0"><b>Total open</b><b class="lg-money">{{ Fmt::rp(array_sum($this->aging)) }}</b></div>
            </div>
        </x-ledger.modal>
    @endif

    @if ($modal === 'view' && $this->viewing)
        @foreach ([$this->viewing] as $i)
            <x-ledger.modal :title="$i->invoice_number" size="xl">
                <dl class="lg-kv" style="padding-top:16px">
                    <dt>Customer</dt><dd>{{ $this->customers[$i->partner_id] ?? $i->partner_id }}</dd>
                    <dt>Description</dt><dd>{{ $i->description }}</dd>
                    <dt>Dates</dt><dd>Invoice {{ Fmt::date($i->invoice_date) }} · Due {{ Fmt::date($i->due_date) }}</dd>
                    <dt>Amounts</dt><dd>Net {{ Fmt::rp($i->subtotal) }} · PPN {{ Fmt::rp($i->tax_amount) }} · Total {{ Fmt::rp($i->total_amount) }} · Paid {{ Fmt::rp($i->paid_amount) }}</dd>
                    <dt>e-Faktur</dt><dd>{{ $i->tax_invoice_no ?: '—' }}</dd>
                    <dt>GL voucher</dt><dd>{{ $i->voucher?->voucher_number ?? 'Not posted' }}</dd>
                </dl>
                @if ($i->voucher)
                    <div class="lg-tablewrap" style="padding:0 20px 10px"><table><thead><tr><th>Account</th><th class="r">Debit</th><th class="r">Credit</th></tr></thead><tbody>
                        @foreach ($i->voucher->lines as $l)<tr><td>{{ $l->account?->account_code }} · {{ $l->account?->account_name }}</td><td class="r lg-money">{{ Fmt::rp($l->debit, true) }}</td><td class="r lg-money">{{ Fmt::rp($l->credit, true) }}</td></tr>@endforeach
                    </tbody></table></div>
                @endif
                @if ($i->allocations->isNotEmpty())
                    <div class="lg-list"><b style="font-size:12px">Receipts allocated</b>
                        @foreach ($i->allocations as $a)<x-ledger.row icon="✓" tone="gr" :title="$a->voucher?->voucher_number" :text="Fmt::date($a->alloc_date)"><span class="lg-money">{{ Fmt::rp($a->amount) }}</span></x-ledger.row>@endforeach
                    </div>
                @endif
                <x-slot:footer>
                    @if ($i->doc_status === 'DRAFT')<button type="button" class="lg-btn primary" wire:click="postInvoice({{ $i->invoice_id }})">Approve & Post</button>@endif
                    @if ($i->doc_status === 'POSTED' && $i->payment_status !== 'PAID')<button type="button" class="lg-btn primary" wire:click="openReceive({{ $i->invoice_id }})">Receive Payment</button>@endif
                    <button type="button" class="lg-btn light" wire:click="closeModal">{{ \App\Support\Localization\Translator::t('shell_tab_close_title', 'Tutup') }}</button>
                </x-slot:footer>
            </x-ledger.modal>
        @endforeach
    @endif
</x-ledger.page>
