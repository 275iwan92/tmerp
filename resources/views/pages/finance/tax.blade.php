<?php

use App\Livewire\Concerns\FinanceScreen;
use App\Models\Acc\FinAccount;
use App\Models\Acc\FinTaxRegister;
use App\Models\Master\FinTaxScheme;
use App\Services\Acc\TaxEngine;
use App\Support\Finance\Fmt;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Indonesia Tax Center -- mockup blueprint (Tax-to-Accounting Flow):
// Classify -> Calculate -> Post -> Reconcile -> File & Pay.
// PPN dari mutasi GL 212100/114100, PPh 21 dari payroll (tm2pyr) atau GL,
// PPh 23 dari 212200, PPh Badan = provisi laba sebelum pajak x tarif.
// Tarif & jatuh tempo dibaca dari tm2mst.fin_tax_scheme (PMK 131/2024 & 81/2024).
new #[Title('Indonesia Tax Center — TELASIH MAKMUR ERP')] class extends Component
{
    use FinanceScreen;

    public string $period = '';

    public string $search = '';

    public string $typeFilter = '';

    public ?int $regId = null;

    public string $pay_date = '';

    public string $ntpn = '';

    public string $pay_bank = '';

    public string $filing_ref = '';

    public function mount(): void
    {
        $this->period = now()->subMonthNoOverflow()->format('Y-m');
    }

    private function ym(): array
    {
        return [(int) substr($this->period, 0, 4), (int) substr($this->period, 5, 2)];
    }

    #[Computed]
    public function registers()
    {
        return FinTaxRegister::query()
            ->when($this->typeFilter !== '', fn ($q) => $q->where('tax_code', $this->typeFilter))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('register_code', 'like', "%{$this->search}%")->orWhere('description', 'like', "%{$this->search}%")))
            ->orderByDesc('fiscal_year')->orderByDesc('period_no')->limit(200)->get();
    }

    #[Computed]
    public function schemes()
    {
        return FinTaxScheme::where('activate', true)->get()->keyBy('tax_code');
    }

    #[Computed]
    public function current(): array
    {
        [$y, $m] = $this->ym();
        $get = fn ($code) => FinTaxRegister::where('tax_code', $code)->where('fiscal_year', $y)->where('period_no', $m)->first();

        return [
            'ppn' => $get('PPN_OUT'), 'pph21' => $get('PPH21'), 'pph23' => $get('PPH23'),
            'cit' => FinTaxRegister::where('tax_code', 'PPH_BADAN')->where('fiscal_year', $y)->whereNull('period_no')->first(),
        ];
    }

    #[Computed]
    public function calendar()
    {
        return FinTaxRegister::whereNotIn('status', ['FILED'])->whereNotNull('payment_due')
            ->orderBy('payment_due')->limit(8)->get();
    }

    #[Computed]
    public function reconciliation(): array
    {
        [$y, $m] = $this->ym();

        return app(TaxEngine::class)->reconciliation($y, $m);
    }

    #[Computed]
    public function periodOptions(): array
    {
        $out = [];
        for ($i = 0; $i < 18; $i++) {
            $d = now()->startOfMonth()->subMonths($i);
            $out[$d->format('Y-m')] = $d->format('F Y');
        }

        return $out;
    }

    public function generate(TaxEngine $engine): void
    {
        [$y, $m] = $this->ym();
        if ($this->attempt(fn () => $engine->generateMonth($y, $m, $this->login()), 'Workpaper pajak masa '.($this->periodOptions[$this->period] ?? '').' disusun dari GL & payroll.')) {
            unset($this->registers, $this->current, $this->calendar, $this->reconciliation);
        }
    }

    public function provision(TaxEngine $engine): void
    {
        [$y, $m] = $this->ym();
        if ($this->attempt(fn () => $engine->provisionCit($y, date('Y-m-t', strtotime($this->period.'-01')), $this->login()), 'Provisi PPh Badan diperbarui (Dr Beban Pajak / Cr Hutang PPh Badan).')) {
            unset($this->registers, $this->current, $this->reconciliation);
        }
    }

    public function openPay(int $id): void
    {
        $this->regId = $id;
        $this->pay_date = now()->toDateString();
        $this->ntpn = '';
        $this->pay_bank = (string) FinAccount::where('account_code', '111200')->value('account_id');
        $this->modal = 'pay';
    }

    public function pay(TaxEngine $engine): void
    {
        $this->validate(['pay_date' => 'required|date', 'ntpn' => 'required|string|max:30', 'pay_bank' => 'required|exists:tm2acc.fin_account,account_id']);
        if ($this->attempt(fn () => $engine->pay(FinTaxRegister::findOrFail($this->regId), $this->pay_date, $this->ntpn, (int) $this->pay_bank, $this->login()), 'Setoran pajak diposting & register ditandai PAID.')) {
            $this->closeModal();
            unset($this->registers, $this->current, $this->calendar);
        }
    }

    public function openFile(int $id): void
    {
        $this->regId = $id;
        $this->filing_ref = '';
        $this->modal = 'file';
    }

    public function fileReturn(TaxEngine $engine): void
    {
        $this->validate(['filing_ref' => 'required|string|max:50']);
        if ($this->attempt(fn () => $engine->file(FinTaxRegister::findOrFail($this->regId), $this->filing_ref, $this->login()), 'SPT ditandai sudah dilaporkan.')) {
            $this->closeModal();
            unset($this->registers, $this->current, $this->calendar);
        }
    }

    public function review(int $id): void
    {
        $this->attempt(fn () => FinTaxRegister::findOrFail($id)->update(['status' => 'REVIEW', 'updated_by' => $this->login()]), 'Register ditandai sedang direview.');
        unset($this->registers, $this->current);
    }

    public function export()
    {
        return $this->csvDownload('tax-register-'.now()->format('Ymd').'.csv',
            ['Register', 'Tax', 'Year', 'Period', 'Description', 'Base/Output', 'Credit/Input', 'Payable', 'Payment due', 'Filing due', 'Status', 'NTPN', 'Filing ref'],
            $this->registers->map(fn ($r) => [$r->register_code, $r->tax_code, $r->fiscal_year, $r->period_no, $r->description,
                (float) $r->base_amount, (float) $r->credit_amount, (float) $r->payable_amount,
                $r->payment_due?->format('Y-m-d'), $r->filing_due?->format('Y-m-d'), $r->status, $r->ntpn, $r->filing_ref]));
    }

    public function statusTone(string $s): string
    {
        return ['DRAFT' => 'warn', 'REVIEW' => 'warn', 'PAYMENT_PENDING' => 'bad', 'PAID' => 'info', 'FILED' => 'good', 'ACCRUED' => 'good'][$s] ?? 'mute';
    }

    public function statusLabel(string $s): string
    {
        return ['DRAFT' => 'Draft return', 'REVIEW' => 'Review', 'PAYMENT_PENDING' => 'Payment pending', 'PAID' => 'Paid', 'FILED' => 'Filed', 'ACCRUED' => 'Accrued'][$s] ?? $s;
    }

    public function dueTag($date): array
    {
        if (! $date) {
            return ['—', 'mute'];
        }
        $days = (int) today()->diffInDays($date, false);

        return ['Due '.Fmt::date($date), $days < 0 ? 'bad' : ($days <= 7 ? 'warn' : 'info')];
    }
};
?>

<x-ledger.page title="Indonesia Tax Center" subtitle="PPN, PPh withholding, PPh Badan provision, tax reconciliation, and filing workpapers.">
    <x-slot:actions>
        <select class="lg-period" wire:model.live="period" x-data x-effect="$el.value = @js($period)">
            @foreach ($this->periodOptions as $k => $label)<option value="{{ $k }}">▣ Masa {{ $label }}</option>@endforeach
        </select>
        <button type="button" class="lg-btn light" wire:click="export">⇩ Export Summary</button>
        <button type="button" class="lg-btn light" wire:click="provision">▤ Update CIT Provision</button>
        <button type="button" class="lg-btn primary" wire:click="generate">＋ New Workpaper</button>
    </x-slot:actions>

    <div class="lg-cards">
        <x-ledger.metric label="PPN Net Payable" icon="%" :value="Fmt::short($this->current['ppn']?->payable_amount ?? 0)" :pill="'Masa '.($this->periodOptions[$period] ?? '')" :tone="$this->current['ppn'] ? 'warn' : 'mute'" :sub="$this->current['ppn'] ? null : 'Workpaper belum dibuat'" />
        <x-ledger.metric label="PPh 21 Payable" icon="%" :value="Fmt::short($this->current['pph21']?->payable_amount ?? 0)" :pill="$this->current['pph21'] ? $this->statusLabel($this->current['pph21']->status) : null" :tone="($this->current['pph21']?->status === 'FILED') ? 'good' : 'warn'" />
        <x-ledger.metric label="PPh 23 Withheld" icon="%" :value="Fmt::short($this->current['pph23']?->payable_amount ?? 0)" :sub="$this->periodOptions[$period] ?? ''" />
        <x-ledger.metric label="PPh Badan Provision" icon="▤" :value="Fmt::short($this->current['cit']?->payable_amount ?? 0)" :sub="'FY '.substr($period, 0, 4).' estimate'" />
    </div>

    <x-ledger.panel class="lg-stack" title="Tax-to-Accounting Flow" subtitle="Tax calculation must remain traceable from document to filing workpaper and GL.">
        <x-ledger.flow :nodes="[
            ['Classify Event', 'Tax profile, counterparty, tax code, invoice data and supporting documents.'],
            ['Calculate Tax', 'PPN output/input & PPh withholding from fin_tax_scheme (rate configurable).'],
            ['Post Accounting', 'Tax payable/receivable accounts with source transaction references.'],
            ['Reconcile', 'Tax register vs invoice subledger vs GL control accounts.'],
            ['File & Pay', 'Approved workpaper, NTPN payment evidence, filing tracking and archive.'],
        ]" />
    </x-ledger.panel>

    <div class="lg-two wide">
        <x-ledger.panel title="Tax Compliance Calendar" subtitle="Upcoming filing and payment obligations.">
            <div class="lg-list">
                @forelse ($this->calendar as $r)
                    <x-ledger.row :icon="'%'" :tone="$this->dueTag($r->payment_due)[1] === 'bad' ? 're' : 'or'" :title="$r->description" :text="'Payment '.Fmt::date($r->payment_due).' · Filing '.Fmt::date($r->filing_due)">
                        <x-ledger.tag :tone="$this->dueTag($r->payment_due)[1]">{{ $this->dueTag($r->payment_due)[0] }}</x-ledger.tag>
                    </x-ledger.row>
                @empty
                    <div class="lg-empty">Tidak ada kewajiban pajak terbuka.</div>
                @endforelse
            </div>
        </x-ledger.panel>

        <x-ledger.panel title="Tax Reconciliation Status" :subtitle="($this->periodOptions[$period] ?? '').' book-to-tax controls.'">
            <div class="lg-list">
                @foreach ($this->reconciliation as $r)
                    <x-ledger.row :icon="$r[2] === 'Matched' ? '✓' : '!'" :tone="$r[2] === 'Matched' ? 'bl' : 'or'" :title="$r[0]" :text="$r[1]">
                        <x-ledger.tag :tone="$r[2] === 'Matched' ? 'good' : 'warn'">{{ $r[2] }}</x-ledger.tag>
                    </x-ledger.row>
                @endforeach
            </div>
        </x-ledger.panel>
    </div>

    <x-ledger.panel title="Tax Register" subtitle="Tax obligations, accounting provisions, and filing workflow status.">
        <div class="lg-toolbar">
            <input class="lg-search" wire:model.live.debounce.300ms="search" placeholder="Search tax period, type, or reference...">
            <select class="lg-select" wire:model.live="typeFilter" x-data x-effect="$el.value = @js($typeFilter)">
                <option value="">All tax types</option>
                @foreach ($this->schemes as $code => $s)<option value="{{ $code }}">{{ $s->tax_name }}</option>@endforeach
            </select>
            <div class="lg-grow"></div>
            <span class="lg-sub">Tarif aktif: PPN {{ rtrim(rtrim(number_format((float) ($this->schemes['PPN_OUT']->rate ?? 0), 2), '0'), '.') }}% × DPP {{ round((float) ($this->schemes['PPN_OUT']->dpp_factor ?? 1), 4) }} · PPh 23 {{ rtrim(rtrim(number_format((float) ($this->schemes['PPH23']->rate ?? 0), 2), '0'), '.') }}% · PPh Badan {{ rtrim(rtrim(number_format((float) ($this->schemes['PPH_BADAN']->rate ?? 0), 2), '0'), '.') }}%</span>
        </div>
        <div class="lg-tablewrap">
            <table class="lg-min">
                <thead><tr><th>Tax Type / Period</th><th>Description</th><th class="r">Output / Base</th><th class="r">Input / Credit</th><th class="r">Payable</th><th>Payment Due</th><th>Filing Due</th><th>Workflow Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($this->registers as $r)
                        <tr wire:key="tx-{{ $r->tax_reg_id }}">
                            <td><span class="lg-name">{{ $this->schemes[$r->tax_code]->tax_name ?? $r->tax_code }} — {{ $r->period_no ? \Carbon\Carbon::create($r->fiscal_year, $r->period_no, 1)->format('M Y') : 'FY '.$r->fiscal_year }}</span><span class="lg-code">{{ $r->register_code }}</span></td>
                            <td>{{ \Illuminate\Support\Str::after($r->description, '· ') }}</td>
                            <td class="r lg-money">{{ Fmt::rp($r->base_amount, true) }}</td>
                            <td class="r lg-money">{{ Fmt::rp($r->credit_amount, true) }}</td>
                            <td class="r lg-money">{{ Fmt::rp($r->payable_amount) }}</td>
                            <td>{{ Fmt::date($r->payment_due) }}</td>
                            <td>{{ $r->filing_due ? Fmt::date($r->filing_due) : 'Annual' }}</td>
                            <td><x-ledger.tag :tone="$this->statusTone($r->status)">{{ $this->statusLabel($r->status) }}</x-ledger.tag></td>
                            <td class="r">
                                <div class="lg-rowmenu" x-data="{ o: false }" @click.outside="o = false">
                                    <button type="button" class="lg-dots" @click="o = !o">⋮</button>
                                    <div class="m" x-show="o" x-cloak>
                                        @if (in_array($r->status, ['DRAFT', 'REVIEW', 'PAYMENT_PENDING', 'ACCRUED'], true))
                                            <button type="button" wire:click="openPay({{ $r->tax_reg_id }})">Record payment (NTPN)</button>
                                            <button type="button" wire:click="review({{ $r->tax_reg_id }})">Mark under review</button>
                                        @endif
                                        @if (in_array($r->status, ['PAID', 'DRAFT', 'REVIEW'], true))
                                            <button type="button" wire:click="openFile({{ $r->tax_reg_id }})">Mark SPT filed</button>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="lg-empty">Belum ada workpaper — pilih masa pajak lalu klik <b>＋ New Workpaper</b>.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="lg-foot"><span>{{ $this->registers->count() }} register rows</span><span>Nilai diambil dari mutasi GL periode terkait — bukan input manual</span></div>
    </x-ledger.panel>

    @if ($modal === 'pay')
        @foreach ([\App\Models\Acc\FinTaxRegister::find($regId)] as $r)
            <x-ledger.modal title="Record Tax Payment">
                <div class="lg-mb">
                    @if ($errorMessage)<div class="lg-alert err lg-field full">{{ $errorMessage }}</div>@endif
                    <x-ledger.field label="Obligation" full><b>{{ $r?->description }}</b> — {{ Fmt::rp($r?->payable_amount) }}</x-ledger.field>
                    <x-ledger.field label="Payment date" name="pay_date"><input type="date" wire:model="pay_date"></x-ledger.field>
                    <x-ledger.field label="NTPN" name="ntpn"><input wire:model="ntpn" placeholder="16 karakter dari bukti setor"></x-ledger.field>
                    <x-ledger.field label="Pay from" name="pay_bank" full>
                        <select wire:model="pay_bank" x-data x-effect="$el.value = @js($pay_bank)">@foreach ($this->cashBankOptions() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select>
                    </x-ledger.field>
                    <p class="lg-sub lg-field full">PPN: Dr PPN Keluaran / Cr PPN Masukan / Cr Bank sebesar kurang bayar. PPh: Dr hutang pajak / Cr Bank.</p>
                </div>
                <x-slot:footer>
                    <button type="button" class="lg-btn light" wire:click="closeModal">{{ \App\Support\Localization\Translator::t('shared_btn_cancel', 'Batal') }}</button>
                    <button type="button" class="lg-btn primary" wire:click="pay">Post Payment</button>
                </x-slot:footer>
            </x-ledger.modal>
        @endforeach
    @endif

    @if ($modal === 'file')
        <x-ledger.modal title="Mark SPT Filed">
            <div class="lg-mb one">
                @if ($errorMessage)<div class="lg-alert err">{{ $errorMessage }}</div>@endif
                <x-ledger.field label="Filing reference (BPE / e-Bupot)" name="filing_ref"><input wire:model="filing_ref" placeholder="e.g. BPE-1234567890"></x-ledger.field>
            </div>
            <x-slot:footer>
                <button type="button" class="lg-btn light" wire:click="closeModal">{{ \App\Support\Localization\Translator::t('shared_btn_cancel', 'Batal') }}</button>
                <button type="button" class="lg-btn primary" wire:click="fileReturn">Save</button>
            </x-slot:footer>
        </x-ledger.modal>
    @endif
</x-ledger.page>
