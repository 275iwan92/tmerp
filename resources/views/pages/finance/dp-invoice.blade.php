<?php

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinDpAllocation;
use App\Models\Acc\FinDpOutstanding;
use App\Models\Acc\FinInvoice;
use App\Models\Acc\FinJournal;
use App\Models\Acc\FinVoucher;
use App\Models\Acc\FinVoucherLine;
use App\Models\Master\CustCustomer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// mockup_down_payment.html + docs/downpayment_tier1-4.md. Uang muka
// (DP) dua arah di atas struktur GL yang sudah ada — fokus SALES DP (kasus
// utama pabrik), PURCHASE DP menyusul (§2B-5, simetris). 3 event dibangun di
// sini: Terbitkan DP (1), Catat Pembayaran (2), Alokasikan ke invoice final
// (4) — event 3 (invoice final per shipment) milik modul Delivery Order yang
// belum ada, jadi Alokasi diuji dengan invoice final yang di-insert manual.
// Referensi SO/CO mockup ("tm2sdi.sales_orders") DISESUAIKAN jadi input
// manual (customer + nilai kontrak + no. referensi teks co_reference) --
// tm2sdi.co_order SEKARANG SUDAH ADA (2026-08-20, lihat sales_state.yaml)
// tapi field ini BELUM di-rewiring ke co_id asli (masih co_reference bebas
// teks); kolom co_id tetap disimpan logis-opsional utk rewiring sesi lain.
new #[Title('Tagihan Uang Muka (DP) — TELASIH MAKMUR ERP')] class extends Component
{
    public bool $showEntryModal = false;

    public bool $showAllocModal = false;

    public ?int $allocatingDpInvoiceId = null;

    // ---- form: terbitkan DP ----
    public ?int $customer_id = null;

    public string $co_reference = '';

    public string $contract_value = '0';

    public string $dp_method = 'percent'; // percent | fixed

    public string $dp_percent = '50';

    public string $dp_fixed_amount = '0';

    public string $entry_date = '';

    public string $due_date = '';

    public string $print_note = '';

    // ---- form: alokasi ----
    public ?int $alloc_final_invoice_id = null;

    public string $alloc_base = '0';

    public string $alloc_tax = '0';

    public function mount(): void
    {
        $this->entry_date = now()->toDateString();
        $this->due_date = now()->addDays(7)->toDateString();
    }

    #[Computed]
    public function customers()
    {
        return CustCustomer::query()->where('activate', true)->orderBy('customer_name')->get();
    }

    #[Computed]
    public function selectedCustomer(): ?CustCustomer
    {
        return $this->customer_id ? CustCustomer::find($this->customer_id) : null;
    }

    #[Computed]
    public function dpInvoices()
    {
        $outstanding = FinDpOutstanding::query()->get()->keyBy('dp_invoice_id');

        return FinInvoice::query()
            ->where('invoice_type', 'CUSTOMER_DP')
            ->orderByDesc('invoice_id')
            ->get()
            ->map(function (FinInvoice $inv) use ($outstanding) {
                $inv->setAttribute('dp_remaining', $outstanding->get($inv->invoice_id)?->dp_remaining ?? $inv->total_amount);
                $inv->setAttribute('customer', CustCustomer::find($inv->partner_id));

                return $inv;
            });
    }

    #[Computed]
    public function metricWaitingApproval(): int
    {
        return FinInvoice::query()->where('invoice_type', 'CUSTOMER_DP')->where('payment_status', 'UNPAID')->count();
    }

    #[Computed]
    public function metricPaid(): int
    {
        return FinInvoice::query()->where('invoice_type', 'CUSTOMER_DP')->where('payment_status', 'PAID')->count();
    }

    #[Computed]
    public function metricOutstandingTotal(): string
    {
        $total = FinDpOutstanding::query()->sum('dp_remaining');

        return number_format((float) $total, 0, ',', '.');
    }

    // Perhitungan real-time ditampilkan di modal (subtotal/PPN/total).
    #[Computed]
    public function calc(): array
    {
        $contract = (float) $this->contract_value;
        $subtotal = $this->dp_method === 'percent'
            ? round($contract * ((float) $this->dp_percent) / 100, 4)
            : (float) $this->dp_fixed_amount;

        $exempt = $this->selectedCustomer?->tax_profile === 'EXEMPT';
        $tax = $exempt ? 0 : round($subtotal * 0.11, 4);

        return [
            'contract' => $contract,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $subtotal + $tax,
            'exempt' => $exempt,
        ];
    }

    public function openEntryModal(): void
    {
        $this->reset(['customer_id', 'co_reference', 'contract_value', 'dp_fixed_amount', 'print_note']);
        $this->dp_method = 'percent';
        $this->dp_percent = '50';
        $this->entry_date = now()->toDateString();
        $this->due_date = now()->addDays(7)->toDateString();
        $this->resetErrorBag();
        $this->showEntryModal = true;
    }

    private function accountId(string $code): int
    {
        return (int) FinAccount::where('account_code', $code)->value('account_id');
    }

    private function journalId(string $code): int
    {
        return (int) FinJournal::where('journal_code', $code)->value('journal_id');
    }

    private function nextInvoiceNumber(): string
    {
        $prefix = 'INV-DP-'.now()->format('Y').'-'.now()->format('m').'-';
        $seq = FinInvoice::where('invoice_number', 'like', $prefix.'%')->count() + 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function nextVoucherNumber(string $journalCode): string
    {
        $prefix = 'VCH-'.$journalCode.'-'.now()->format('Ym').'-';
        $seq = FinVoucher::where('voucher_number', 'like', $prefix.'%')->count() + 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    // ============== EVENT 1 — Terbitkan DP Invoice ==============
    public function issueDpInvoice(): void
    {
        $data = $this->validate([
            'customer_id' => ['required', 'integer', 'exists:tm2mst.cust_customer,id_customer'],
            'co_reference' => ['nullable', 'string', 'max:100'],
            'contract_value' => ['required', 'numeric', 'min:0.01'],
            'dp_method' => ['required', 'in:percent,fixed'],
            'dp_percent' => $this->dp_method === 'percent' ? ['required', 'numeric', 'min:0.01', 'max:100'] : ['nullable'],
            'dp_fixed_amount' => $this->dp_method === 'fixed' ? ['required', 'numeric', 'min:0.01'] : ['nullable'],
            'entry_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:entry_date'],
            'print_note' => ['nullable', 'string', 'max:255'],
        ]);

        $calc = $this->calc;
        if ($calc['subtotal'] <= 0) {
            $this->addError('contract_value', 'Nilai DP harus lebih besar dari 0.');

            return;
        }

        $customer = CustCustomer::findOrFail($data['customer_id']);
        $arAccount = $this->accountId($customer->customer_type === 'import' ? '112200' : '112100');
        $dpAccount = $this->accountId('213200');
        $ppnAccount = $this->accountId('212100');

        DB::connection('tm2acc')->transaction(function () use ($data, $calc, $customer, $arAccount, $dpAccount, $ppnAccount) {
            $invoiceNumber = $this->nextInvoiceNumber();

            $invoice = FinInvoice::create([
                'invoice_number' => $invoiceNumber,
                'invoice_type' => 'CUSTOMER_DP',
                'partner_type' => 'CUSTOMER',
                'partner_id' => $customer->id_customer,
                'source_type' => 'MANUAL',
                'invoice_date' => $data['entry_date'],
                'due_date' => $data['due_date'],
                'subtotal' => $calc['subtotal'],
                'tax_amount' => $calc['tax'],
                'total_amount' => $calc['total'],
                'paid_amount' => 0,
                'payment_status' => 'UNPAID',
                'created_by' => Auth::user()->login,
                'updated_by' => Auth::user()->login,
            ]);

            $voucher = FinVoucher::create([
                'voucher_number' => $this->nextVoucherNumber('SAL'),
                'journal_id' => $this->journalId('SAL'),
                'entry_date' => $data['entry_date'],
                'posting_date' => $data['entry_date'],
                'doc_type' => 'NEW',
                'source_type' => 'CUSTOMER_DP',
                'source_id' => $invoice->invoice_id,
                'reference_no' => $data['co_reference'] ?: null,
                'description' => "DP Invoice {$invoiceNumber} — {$customer->customer_name}. {$data['print_note']}",
                'status' => 'POSTED',
                'posted_by' => Auth::user()->login,
                'posted_at' => now(),
                'created_by' => Auth::user()->login,
                'updated_by' => Auth::user()->login,
            ]);

            FinVoucherLine::create([
                'voucher_id' => $voucher->voucher_id,
                'account_id' => $arAccount,
                'partner_type' => 'CUSTOMER',
                'partner_id' => $customer->id_customer,
                'debit' => $calc['total'],
                'credit' => 0,
                'narration' => "Piutang DP {$invoiceNumber}",
            ]);
            FinVoucherLine::create([
                'voucher_id' => $voucher->voucher_id,
                'account_id' => $dpAccount,
                'partner_type' => 'CUSTOMER',
                'partner_id' => $customer->id_customer,
                'debit' => 0,
                'credit' => $calc['subtotal'],
                'narration' => "Uang Muka {$invoiceNumber}",
            ]);
            if ($calc['tax'] > 0) {
                FinVoucherLine::create([
                    'voucher_id' => $voucher->voucher_id,
                    'account_id' => $ppnAccount,
                    'debit' => 0,
                    'credit' => $calc['tax'],
                    'narration' => "PPN Keluaran DP {$invoiceNumber}",
                ]);
            }

            $invoice->update(['voucher_id' => $voucher->voucher_id]);
        });

        unset($this->dpInvoices, $this->metricWaitingApproval, $this->metricPaid, $this->metricOutstandingTotal);
        $this->showEntryModal = false;
    }

    // ============== EVENT 2 — Catat Pembayaran DP ==============
    public function recordPayment(int $invoiceId): void
    {
        $invoice = FinInvoice::findOrFail($invoiceId);
        if ($invoice->payment_status === 'PAID') {
            return;
        }

        $customer = CustCustomer::findOrFail($invoice->partner_id);
        $bankAccount = $this->accountId('111200');
        $arAccount = $this->accountId($customer->customer_type === 'import' ? '112200' : '112100');

        DB::connection('tm2acc')->transaction(function () use ($invoice, $customer, $bankAccount, $arAccount) {
            $voucher = FinVoucher::create([
                'voucher_number' => $this->nextVoucherNumber('BNK'),
                'journal_id' => $this->journalId('BNK'),
                'entry_date' => now()->toDateString(),
                'posting_date' => now()->toDateString(),
                'doc_type' => 'NEW',
                'source_type' => 'CUSTOMER_DP_PAYMENT',
                'source_id' => $invoice->invoice_id,
                'description' => "Pembayaran DP {$invoice->invoice_number} — {$customer->customer_name}",
                'status' => 'POSTED',
                'posted_by' => Auth::user()->login,
                'posted_at' => now(),
                'created_by' => Auth::user()->login,
                'updated_by' => Auth::user()->login,
            ]);

            FinVoucherLine::create([
                'voucher_id' => $voucher->voucher_id,
                'account_id' => $bankAccount,
                'debit' => $invoice->total_amount,
                'credit' => 0,
                'narration' => "Terima pembayaran DP {$invoice->invoice_number}",
            ]);
            FinVoucherLine::create([
                'voucher_id' => $voucher->voucher_id,
                'account_id' => $arAccount,
                'partner_type' => 'CUSTOMER',
                'partner_id' => $customer->id_customer,
                'debit' => 0,
                'credit' => $invoice->total_amount,
                'narration' => "Tutup piutang DP {$invoice->invoice_number}",
            ]);

            $invoice->update(['paid_amount' => $invoice->total_amount, 'payment_status' => 'PAID', 'updated_by' => Auth::user()->login]);
        });

        unset($this->dpInvoices, $this->metricWaitingApproval, $this->metricPaid, $this->metricOutstandingTotal);
        $this->actionMessage('WO Gate terbuka — DP '.$invoice->invoice_number.' lunas.');
    }

    public ?string $actionMsg = null;

    private function actionMessage(string $msg): void
    {
        $this->actionMsg = $msg;
    }

    // ============== EVENT 4 — Alokasi DP ke Invoice Final ==============
    public function openAllocModal(int $dpInvoiceId): void
    {
        $this->allocatingDpInvoiceId = $dpInvoiceId;
        $this->reset(['alloc_final_invoice_id', 'alloc_base', 'alloc_tax']);
        $this->resetErrorBag();
        $this->showAllocModal = true;
    }

    #[Computed]
    public function allocatingDp(): ?FinInvoice
    {
        return $this->allocatingDpInvoiceId ? FinInvoice::find($this->allocatingDpInvoiceId) : null;
    }

    #[Computed]
    public function allocatingDpRemaining(): float
    {
        if (! $this->allocatingDpInvoiceId) {
            return 0;
        }

        return (float) (FinDpOutstanding::find($this->allocatingDpInvoiceId)?->dp_remaining ?? 0);
    }

    #[Computed]
    public function openFinalInvoices()
    {
        if (! $this->allocatingDp) {
            return collect();
        }

        return FinInvoice::query()
            ->where('invoice_type', 'CUSTOMER_INVOICE')
            ->where('partner_id', $this->allocatingDp->partner_id)
            ->where('payment_status', '!=', 'PAID')
            ->orderByDesc('invoice_id')
            ->get();
    }

    public function allocateDp(): void
    {
        $data = $this->validate([
            'alloc_final_invoice_id' => ['required', 'integer', 'exists:tm2acc.fin_invoice,invoice_id', 'different:allocatingDpInvoiceId'],
            'alloc_base' => ['required', 'numeric', 'min:0.01'],
            'alloc_tax' => ['required', 'numeric', 'min:0'],
        ]);

        $dp = FinInvoice::findOrFail($this->allocatingDpInvoiceId);
        $final = FinInvoice::findOrFail($data['alloc_final_invoice_id']);
        $requested = (float) $data['alloc_base'] + (float) $data['alloc_tax'];

        if ($requested > $this->allocatingDpRemaining + 0.0001) {
            $this->addError('alloc_base', 'Total alokasi melebihi sisa DP yang tersedia.');

            return;
        }

        $finalOutstanding = (float) $final->total_amount - (float) $final->paid_amount;
        if ($requested > $finalOutstanding + 0.0001) {
            $this->addError('alloc_base', 'Total alokasi melebihi sisa tagihan invoice final tersebut.');

            return;
        }

        $customer = CustCustomer::findOrFail($dp->partner_id);
        $dpAccount = $this->accountId('213200');
        $ppnAccount = $this->accountId('212100');
        $arAccount = $this->accountId($customer->customer_type === 'import' ? '112200' : '112100');

        DB::connection('tm2acc')->transaction(function () use ($data, $dp, $final, $customer, $dpAccount, $ppnAccount, $arAccount, $requested) {
            FinDpAllocation::create([
                'dp_invoice_id' => $dp->invoice_id,
                'final_invoice_id' => $final->invoice_id,
                'allocated_base' => $data['alloc_base'],
                'allocated_tax' => $data['alloc_tax'],
                'allocation_date' => now()->toDateString(),
                'created_by' => Auth::user()->login,
                'updated_by' => Auth::user()->login,
            ]);

            $voucher = FinVoucher::create([
                'voucher_number' => $this->nextVoucherNumber('GEN'),
                'journal_id' => $this->journalId('GEN'),
                'entry_date' => now()->toDateString(),
                'posting_date' => now()->toDateString(),
                'doc_type' => 'NEW',
                'source_type' => 'FIN_DP_ALLOCATION',
                'source_id' => $dp->invoice_id,
                'description' => "Reklas DP {$dp->invoice_number} -> {$final->invoice_number}",
                'status' => 'POSTED',
                'posted_by' => Auth::user()->login,
                'posted_at' => now(),
                'created_by' => Auth::user()->login,
                'updated_by' => Auth::user()->login,
            ]);

            FinVoucherLine::create([
                'voucher_id' => $voucher->voucher_id,
                'account_id' => $dpAccount,
                'partner_type' => 'CUSTOMER',
                'partner_id' => $customer->id_customer,
                'debit' => $data['alloc_base'],
                'credit' => 0,
                'narration' => "Reklas UM ke {$final->invoice_number}",
            ]);
            if ((float) $data['alloc_tax'] > 0) {
                FinVoucherLine::create([
                    'voucher_id' => $voucher->voucher_id,
                    'account_id' => $ppnAccount,
                    'debit' => $data['alloc_tax'],
                    'credit' => 0,
                    'narration' => "Reklas PPN DP ke {$final->invoice_number}",
                ]);
            }
            FinVoucherLine::create([
                'voucher_id' => $voucher->voucher_id,
                'account_id' => $arAccount,
                'partner_type' => 'CUSTOMER',
                'partner_id' => $customer->id_customer,
                'co_id' => $final->co_id,
                'debit' => 0,
                'credit' => $requested,
                'narration' => "Tutup sebagian piutang {$final->invoice_number}",
            ]);

            $newPaid = (float) $final->paid_amount + $requested;
            $final->update([
                'paid_amount' => $newPaid,
                'payment_status' => $newPaid >= (float) $final->total_amount ? 'PAID' : 'PARTIAL',
                'updated_by' => Auth::user()->login,
            ]);
        });

        unset($this->dpInvoices, $this->metricOutstandingTotal);
        $this->showAllocModal = false;
        $this->actionMessage("Alokasi berhasil — {$final->invoice_number} terpotong Rp".number_format($requested, 0, ',', '.'));
    }
};
?>

<div>
    <div class="max-w-[1300px] mx-auto my-5 px-4">
        <div class="flex items-center justify-between flex-wrap gap-2.5 mb-3.5">
            <div>
                <h1 class="text-lg font-bold text-navy">Tagihan Uang Muka (Down Payment)</h1>
                <div class="text-xs text-muted mt-0.5">Modul Penjualan &amp; Keuangan · <code>tm2acc.fin_invoice</code> tipe <code>CUSTOMER_DP</code></div>
            </div>
            <button type="button" wire:click="openEntryModal" class="bg-accent text-white text-xs px-3.5 py-2 rounded-md font-semibold cursor-pointer hover:brightness-110">+ Terbitkan DP Baru</button>
        </div>

        @if ($actionMsg)
            <div class="text-xs px-3.5 py-2.5 rounded-md mb-3.5 bg-[#eaf7ee] border border-[#bfe6cd] text-[#1f8a4c]">✓ {{ $actionMsg }}</div>
        @endif

        <x-grid.stat-row :count="3">
            <x-grid.stat-box tone="warn" label="Menunggu Dibayar" :value="$this->metricWaitingApproval" />
            <x-grid.stat-box tone="active" label="Lunas" :value="$this->metricPaid" />
            <x-grid.stat-box tone="total" label="Sisa DP Belum Teralokasi" value="Rp {{ $this->metricOutstandingTotal }}" />
        </x-grid.stat-row>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.table-shell>
            <table class="w-full border-collapse text-[12.5px]">
                <thead>
                    <tr>
                        <th class="text-left px-3.5 py-2.5 bg-[#f7f9fe] text-navy text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">No. Invoice DP</th>
                        <th class="text-left px-3.5 py-2.5 bg-[#f7f9fe] text-navy text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Customer</th>
                        <th class="text-left px-3.5 py-2.5 bg-[#f7f9fe] text-navy text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Tanggal</th>
                        <th class="text-right px-3.5 py-2.5 bg-[#f7f9fe] text-navy text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Total DP</th>
                        <th class="text-right px-3.5 py-2.5 bg-[#f7f9fe] text-navy text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Sisa Belum Teralokasi</th>
                        <th class="text-left px-3.5 py-2.5 bg-[#f7f9fe] text-navy text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Status</th>
                        <th class="text-center px-3.5 py-2.5 bg-[#f7f9fe] text-navy text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->dpInvoices as $inv)
                        <tr wire:key="dp-{{ $inv->invoice_id }}" class="hover:bg-[#f7faff]">
                            <td class="px-3.5 py-2 border-b border-[#eef1f5] font-mono font-bold text-navy">{{ $inv->invoice_number }}</td>
                            <td class="px-3.5 py-2 border-b border-[#eef1f5]">{{ $inv->customer?->customer_name }}</td>
                            <td class="px-3.5 py-2 border-b border-[#eef1f5]">{{ $inv->invoice_date->format('d M Y') }}</td>
                            <td class="px-3.5 py-2 border-b border-[#eef1f5] text-right font-semibold">Rp {{ number_format((float) $inv->total_amount, 0, ',', '.') }}</td>
                            <td class="px-3.5 py-2 border-b border-[#eef1f5] text-right {{ (float) $inv->dp_remaining <= 0 ? 'text-muted' : 'text-[#b45309] font-semibold' }}">Rp {{ number_format((float) $inv->dp_remaining, 0, ',', '.') }}</td>
                            <td class="px-3.5 py-2 border-b border-[#eef1f5]">
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded uppercase {{ match ($inv->payment_status) { 'PAID' => 'bg-[#eaf7ee] text-[#1f8a4c]', 'PARTIAL' => 'bg-[#fff4e0] text-[#8a6d00]', default => 'bg-[#eef0f3] text-[#6b7280]' } }}">{{ $inv->payment_status }}</span>
                            </td>
                            <td class="px-3.5 py-2 border-b border-[#eef1f5] text-center">
                                @if ($inv->payment_status !== 'PAID')
                                    <button type="button" wire:click="recordPayment({{ $inv->invoice_id }})" wire:confirm="Catat pembayaran DP {{ $inv->invoice_number }} sebesar Rp {{ number_format((float) $inv->total_amount, 0, ',', '.') }}?" class="text-accent font-semibold cursor-pointer hover:underline">Catat Pembayaran</button>
                                @elseif ((float) $inv->dp_remaining > 0)
                                    <button type="button" wire:click="openAllocModal({{ $inv->invoice_id }})" class="text-accent font-semibold cursor-pointer hover:underline">Alokasikan</button>
                                @else
                                    <span class="text-muted">Habis Terpakai</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-grid.empty-row :colspan="7" message="Belum ada tagihan uang muka." />
                    @endforelse
                </tbody>
            </table>
            </x-grid.table-shell>
        </div>
    </div>

    {{-- ══ MODAL: TERBITKAN DP ══ --}}
    @if ($showEntryModal)
        <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('showEntryModal', false)">
            <div class="bg-white rounded-xl w-[620px] max-w-[92vw] max-h-[92vh] overflow-y-auto shadow-2xl">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold flex items-center gap-2 sticky top-0">
                    <span>Pembuatan Tagihan Uang Muka (DP Invoice)</span>
                    <span class="ml-auto cursor-pointer opacity-85 hover:opacity-100" wire:click="$set('showEntryModal', false)">✕</span>
                </div>
                <form wire:submit="issueDpInvoice" class="p-4 flex flex-col gap-3">
                    <div class="flex gap-2.5">
                        <div class="flex-1">
                            <x-form.select wire:model.live="customer_id" placeholder="— pilih customer —" name="customer_id"
                                :options="$this->customers->mapWithKeys(fn ($c) => [$c->id_customer => $c->customer_name.' ('.$c->customer_code.')'])->all()">
                                <x-slot:label>Customer *</x-slot:label>
                            </x-form.select>
                            @if ($this->selectedCustomer?->tax_profile === 'EXEMPT')
                                <span class="text-[11px] text-[#1f8a4c]">Bebas PPN (kawasan berikat) — tanpa PPN 11%.</span>
                            @endif
                        </div>
                        <div class="flex-1">
                            <x-form.input label="No. Referensi CO/SO" wire:model="co_reference" placeholder="mis. SO-2026-0899" />
                        </div>
                    </div>

                    <div class="flex gap-2.5">
                        <div class="flex-1">
                            <x-form.input label="Nilai Kontrak (Nilai SO) *" type="number" step="0.01" min="0" wire:model.live="contract_value" name="contract_value" />
                        </div>
                        <div class="flex-1">
                            <x-form.select label="Metode Penagihan DP" wire:model.live="dp_method" :placeholder="false"
                                :options="['percent' => 'Persentase dari Nilai Kontrak', 'fixed' => 'Nominal Tetap']" />
                        </div>
                    </div>

                    <div class="flex gap-2.5">
                        @if ($dp_method === 'percent')
                            <div class="flex-1">
                                <x-form.input label="Persentase DP (%) *" type="number" step="0.01" min="0.01" max="100" wire:model.live="dp_percent" name="dp_percent" />
                            </div>
                        @else
                            <div class="flex-1">
                                <x-form.input label="Nominal DP (Rp) *" type="number" step="0.01" min="0.01" wire:model.live="dp_fixed_amount" name="dp_fixed_amount" />
                            </div>
                        @endif
                        <div class="flex-1">
                            <x-form.input label="Akun Kewajiban UM" readonly value="213200 — Uang Muka Penjualan dari Pelanggan" class="bg-[#f3f4f6]" />
                        </div>
                    </div>

                    <div class="flex gap-2.5">
                        <div class="flex-1">
                            <x-form.date-picker label="Tanggal Faktur DP" wire:model="entry_date" />
                        </div>
                        <div class="flex-1">
                            <x-form.date-picker label="Tanggal Jatuh Tempo DP" wire:model="due_date" name="due_date" />
                        </div>
                    </div>

                    <x-form.input label="Catatan / Instruksi Cetak Pada Faktur" wire:model="print_note" />

                    <div class="bg-[#f8fafc] border border-[#e2e7ee] rounded-md p-3.5 flex flex-col gap-1.5 text-[13px]">
                        <div class="flex justify-between"><span class="text-muted">Nilai Kontrak:</span><span>Rp {{ number_format($this->calc['contract'], 0, ',', '.') }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Subtotal DP:</span><span class="font-semibold">Rp {{ number_format($this->calc['subtotal'], 0, ',', '.') }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">PPN Keluaran (11%):</span><span>Rp {{ number_format($this->calc['tax'], 0, ',', '.') }}</span></div>
                        <div class="flex justify-between text-[15px] font-extrabold text-accent pt-1.5 border-t border-[#e2e7ee]"><span>TOTAL TAGIHAN DP:</span><span>Rp {{ number_format($this->calc['total'], 0, ',', '.') }}</span></div>
                    </div>

                    <div class="text-[11px] text-[#c0392b] font-semibold">🔒 Work Order tidak bisa dibuka sampai DP ini lunas.</div>

                    <div class="flex gap-2 justify-end pt-2 border-t border-[#eef1f5] mt-1">
                        <button type="button" wire:click="$set('showEntryModal', false)" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">Terbitkan &amp; Kirim DP Invoice</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ══ MODAL: ALOKASI DP ══ --}}
    @if ($showAllocModal)
        <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('showAllocModal', false)">
            <div class="bg-white rounded-xl w-[480px] max-w-[92vw] shadow-2xl">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold flex items-center gap-2">
                    <span>Alokasikan DP {{ $this->allocatingDp?->invoice_number }}</span>
                    <span class="ml-auto cursor-pointer opacity-85 hover:opacity-100" wire:click="$set('showAllocModal', false)">✕</span>
                </div>
                <form wire:submit="allocateDp" class="p-4 flex flex-col gap-3">
                    <div class="text-xs text-muted">Sisa DP tersedia: <b class="text-navy">Rp {{ number_format($this->allocatingDpRemaining, 0, ',', '.') }}</b></div>

                    <div>
                        <x-form.select label="Invoice Final Tujuan *" wire:model="alloc_final_invoice_id" placeholder="— pilih invoice final —" name="alloc_final_invoice_id"
                            :options="$this->openFinalInvoices->mapWithKeys(fn ($fi) => [$fi->invoice_id => $fi->invoice_number.' — sisa Rp '.number_format((float) $fi->total_amount - (float) $fi->paid_amount, 0, ',', '.')])->all()" />
                        @if ($this->openFinalInvoices->isEmpty())
                            <span class="text-[11px] text-muted">Belum ada invoice final terbuka untuk customer ini.</span>
                        @endif
                    </div>

                    <div class="flex gap-2.5">
                        <div class="flex-1">
                            <x-form.input label="Potong Pokok (Rp) *" type="number" step="0.01" min="0.01" wire:model="alloc_base" name="alloc_base" />
                        </div>
                        <div class="flex-1">
                            <x-form.input label="Potong PPN (Rp)" type="number" step="0.01" min="0" wire:model="alloc_tax" />
                        </div>
                    </div>

                    <div class="flex gap-2 justify-end pt-2 border-t border-[#eef1f5] mt-1">
                        <button type="button" wire:click="$set('showAllocModal', false)" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">Alokasikan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
