<?php

use App\Livewire\Concerns\FinanceScreen;
use App\Models\Acc\FinApprovalRule;
use App\Services\Acc\FinanceMetrics;
use App\Support\Finance\Fmt;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Accounting Process Engine -- halaman kendali modul (mockup
// MODULE_accounting-process-engine-blueprint.html): volume dokumen, antrian
// approval, kesiapan tutup buku, peta integrasi modul, dan aturan workflow
// (fin_approval_rule) yang menentukan kapan dokumen wajib disetujui.
new #[Title('Accounting Process Engine — TELASIH MAKMUR ERP')] class extends Component
{
    use FinanceScreen;

    public string $rule_name = '';

    public string $process_code = 'JOURNAL';

    public string $min_amount = '0';

    public string $approver_group = 'DIREKSI';

    public bool $sod = true;

    #[Computed]
    public function m(): array
    {
        return app(FinanceMetrics::class)->engine();
    }

    #[Computed]
    public function attention(): array
    {
        return app(FinanceMetrics::class)->attention();
    }

    #[Computed]
    public function rules()
    {
        return FinApprovalRule::orderBy('process_code')->orderBy('min_amount')->get();
    }

    #[Computed]
    public function groupOptions(): array
    {
        return \Illuminate\Support\Facades\DB::connection('tm2sys')->table('sec_group')->where('activate', true)->orderBy('group_code')->pluck('group_name', 'group_code')->all();
    }

    public function saveRule(): void
    {
        $this->validate([
            'rule_name' => 'required|string|max:120',
            'process_code' => 'required|in:JOURNAL,CUSTOMER_INVOICE,VENDOR_BILL,BANK_ADJUSTMENT,ASSET',
            'min_amount' => 'required|numeric|min:0',
            'approver_group' => 'required|string|max:30',
        ], [], ['rule_name' => 'Rule name', 'min_amount' => 'Minimum amount']);

        if ($this->attempt(fn () => FinApprovalRule::create([
            'rule_name' => $this->rule_name, 'process_code' => $this->process_code,
            'min_amount' => $this->min_amount, 'approver_group' => $this->approver_group,
            'segregation_of_duties' => $this->sod, 'activate' => true, 'created_by' => $this->login(),
        ]), 'Aturan workflow ditambahkan.')) {
            $this->reset(['rule_name', 'min_amount']);
            $this->closeModal();
            unset($this->rules);
        }
    }

    public function toggleRule(int $id): void
    {
        $r = FinApprovalRule::findOrFail($id);
        $this->attempt(fn () => $r->update(['activate' => ! $r->activate, 'updated_by' => $this->login()]), 'Aturan diperbarui.');
        unset($this->rules);
    }

    public function export()
    {
        return $this->csvDownload('accounting-workflow-rules.csv',
            ['Rule', 'Process', 'Minimum amount', 'Approver group', 'Segregation of duties', 'Active'],
            $this->rules->map(fn ($r) => [$r->rule_name, $r->process_code, (float) $r->min_amount, $r->approver_group, $r->segregation_of_duties ? 'yes' : 'no', $r->activate ? 'yes' : 'no']));
    }
};
?>

<x-ledger.page title="Accounting Process Engine" subtitle="End-to-end accounting operating model: source transaction → validation → approval → subledger → general ledger → close → reporting.">
    <x-slot:actions>
        <button type="button" class="lg-btn light" wire:click="export">⇩ Export Rules</button>
        <button type="button" class="lg-btn primary" wire:click="$set('modal', 'rule')">＋ Create Workflow Rule</button>
    </x-slot:actions>

    <div class="lg-cards">
        <x-ledger.metric label="Source Documents" icon="▤" :value="number_format($this->m['source_docs'])" :sub="now()->format('F Y').' transactions'" />
        <x-ledger.metric label="Pending Approval" icon="◷" :value="$this->m['pending_n']" :pill="$this->m['pending_n'] ? Fmt::short($this->m['pending_value']).' exposure' : 'Queue empty'" :tone="$this->m['pending_n'] ? 'warn' : 'good'" />
        <x-ledger.metric label="Posted to GL" icon="✓" :value="number_format($this->m['posted_n'])" :pill="$this->m['tb_balanced'] ? 'Balanced' : 'Out of balance'" :tone="$this->m['tb_balanced'] ? 'good' : 'bad'" />
        <x-ledger.metric label="Close Readiness" icon="▣" :value="$this->m['close_pct'].'%'" :pill="$this->m['close_exceptions'] ? $this->m['close_exceptions'].' control exceptions' : 'No exceptions'" :tone="$this->m['close_exceptions'] ? 'warn' : 'good'" :sub="$this->m['close_period']?->period_name" />
    </div>

    <x-ledger.panel class="lg-stack" title="Canonical Accounting Posting Flow" subtitle="All modules use this controlled lifecycle before entries affect financial statements.">
        <x-slot:action><button type="button" class="lg-link" wire:click="$set('modal', 'rule')">Configure rules</button></x-slot:action>
        <x-ledger.flow :nodes="[
            ['Source Event', 'Invoice, bill, receipt, bank transaction, asset purchase, tax, or manual journal.', $this->m['source_docs'] ?: null],
            ['Validation', 'Period open, master data, tax logic, document balance, duplicate and control-account checks.'],
            ['Approval', 'Role, amount and segregation-of-duties matrix (fin_approval_rule).', $this->m['pending_n'] ?: null],
            ['Subledger', 'AR, AP, bank, fixed assets, tax and amortization schedules keep the detail.'],
            ['GL Posting', 'Balanced debit/credit voucher, audit trail, source reference, posting date.', $this->m['posted_n'] ?: null],
            ['Reporting & Close', 'Trial balance, statements, tax workpapers, reconciliation, period lock.', $this->m['close_pct'].'%'],
        ]" />
    </x-ledger.panel>

    <div class="lg-two">
        <x-ledger.panel title="Module Integration Map" subtitle="Where business activity becomes accounting data.">
            <div class="lg-list">
                <x-ledger.row icon="⇧" tone="bl" title="Order to Cash" text="Invoice → AR → receipt allocation → bank → revenue and PPN posting">
                    <a class="lg-link" href="{{ route('v2.screen', 'customer-invoice') }}">Open</a>
                </x-ledger.row>
                <x-ledger.row icon="⇩" tone="bl" title="Procure to Pay" text="Purchase receive → GR/IR → bill → AP → payment, input PPN & PPh 23">
                    <a class="lg-link" href="{{ route('finance.payables') }}">Open</a>
                </x-ledger.row>
                <x-ledger.row icon="▥" tone="gr" title="Bank & Cash" text="Cashier vouchers → statement import → auto-match → reconciliation">
                    <a class="lg-link" href="{{ route('finance.bank') }}">Open</a>
                </x-ledger.row>
                <x-ledger.row icon="▰" tone="pu" title="Assets & Amortization" text="Capitalization → schedule → periodic depreciation/amortization journals">
                    <a class="lg-link" href="{{ route('finance.assets') }}">Open</a>
                </x-ledger.row>
                <x-ledger.row icon="%" tone="or" title="Tax Compliance" text="PPN & PPh derived from taxable events, reconciled to GL, then filed">
                    <a class="lg-link" href="{{ route('finance.tax') }}">Open</a>
                </x-ledger.row>
                <x-ledger.row icon="↔" tone="bl" title="Inventory & Production" text="Purchase receive and raw-material issue post automatically (PUR & USG journals)">
                    <a class="lg-link" href="{{ route('finance.ledger') }}">Open</a>
                </x-ledger.row>
            </div>
        </x-ledger.panel>

        <x-ledger.panel title="Controls Requiring Attention" subtitle="Exceptions block or qualify close.">
            <x-slot:action><a class="lg-link" href="{{ route('finance.closing') }}">Closing tasks</a></x-slot:action>
            <div class="lg-list">
                @foreach ($this->attention as $a)
                    <x-ledger.row :icon="$a[0]" :tone="$a[1]" :title="$a[2]" :text="$a[3]">
                        <div class="lg-n">{{ $a[4] }}</div>
                    </x-ledger.row>
                @endforeach
            </div>
        </x-ledger.panel>
    </div>

    <x-ledger.panel title="Workflow Rules" subtitle="Ambang nominal & grup penyetuju per proses; pemisahan tugas mencegah pembuat menyetujui dokumennya sendiri.">
        <div class="lg-tablewrap">
            <table>
                <thead><tr><th>Rule</th><th>Process</th><th class="r">Minimum amount</th><th>Approver group</th><th>Segregation of duties</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($this->rules as $r)
                        <tr wire:key="rule-{{ $r->rule_id }}">
                            <td><span class="lg-name">{{ $r->rule_name }}</span></td>
                            <td>{{ str_replace('_', ' ', $r->process_code) }}</td>
                            <td class="r lg-money">{{ Fmt::rp($r->min_amount) }}</td>
                            <td>{{ $r->approver_group }}</td>
                            <td>{{ $r->segregation_of_duties ? 'Enforced' : 'Not enforced' }}</td>
                            <td><x-ledger.tag :tone="$r->activate ? 'good' : 'mute'">{{ $r->activate ? 'Active' : 'Inactive' }}</x-ledger.tag></td>
                            <td class="r"><button type="button" class="lg-link" wire:click="toggleRule({{ $r->rule_id }})">{{ $r->activate ? 'Disable' : 'Enable' }}</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="lg-empty">Belum ada aturan — tanpa aturan, jurnal manual bisa langsung diposting tanpa approval.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ledger.panel>

    @if ($modal === 'rule')
        <x-ledger.modal title="Create Workflow Rule">
            <div class="lg-mb">
                @if ($errorMessage)<div class="lg-alert err lg-field full">{{ $errorMessage }}</div>@endif
                <x-ledger.field label="Rule name" name="rule_name" full><input wire:model="rule_name" placeholder="e.g. Jurnal manual di atas Rp 500 jt"></x-ledger.field>
                <x-ledger.field label="Process" name="process_code">
                    <select wire:model="process_code" x-data x-effect="$el.value = @js($process_code)">
                        <option value="JOURNAL">Manual journal</option>
                        <option value="CUSTOMER_INVOICE">Customer invoice</option>
                        <option value="VENDOR_BILL">Vendor bill</option>
                        <option value="BANK_ADJUSTMENT">Bank adjustment</option>
                        <option value="ASSET">Asset capitalization</option>
                    </select>
                </x-ledger.field>
                <x-ledger.field label="Minimum amount (IDR)" name="min_amount" hint="0 = berlaku untuk semua nominal."><input type="number" min="0" step="1000" wire:model="min_amount"></x-ledger.field>
                <x-ledger.field label="Approver group" name="approver_group">
                    <select wire:model="approver_group" x-data x-effect="$el.value = @js($approver_group)">
                        @foreach ($this->groupOptions as $code => $name)<option value="{{ $code }}">{{ $code }} · {{ $name }}</option>@endforeach
                    </select>
                </x-ledger.field>
                <x-ledger.field label="Segregation of duties">
                    <label style="display:flex;gap:8px;align-items:center;font-weight:600"><input type="checkbox" wire:model="sod"> Pembuat tidak boleh menyetujui</label>
                </x-ledger.field>
            </div>
            <x-slot:footer>
                <button type="button" class="lg-btn light" wire:click="closeModal">{{ \App\Support\Localization\Translator::t('shared_btn_cancel', 'Batal') }}</button>
                <button type="button" class="lg-btn primary" wire:click="saveRule">{{ \App\Support\Localization\Translator::t('shell_btn_save', 'Simpan') }}</button>
            </x-slot:footer>
        </x-ledger.modal>
    @endif
</x-ledger.page>
