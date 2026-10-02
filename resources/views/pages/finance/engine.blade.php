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
        ], [], ['rule_name' => t('Rule name'), 'min_amount' => t('Minimum amount')]);

        if ($this->attempt(fn () => FinApprovalRule::create([
            'rule_name' => $this->rule_name, 'process_code' => $this->process_code,
            'min_amount' => $this->min_amount, 'approver_group' => $this->approver_group,
            'segregation_of_duties' => $this->sod, 'activate' => true, 'created_by' => $this->login(),
        ]), t('Workflow rule added.'))) {
            $this->reset(['rule_name', 'min_amount']);
            $this->closeModal();
            unset($this->rules);
        }
    }

    public function toggleRule(int $id): void
    {
        $r = FinApprovalRule::findOrFail($id);
        $this->attempt(fn () => $r->update(['activate' => ! $r->activate, 'updated_by' => $this->login()]), t('Rule updated.'));
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

<x-ledger.page :title="t('Accounting Process Engine')" :subtitle="t('End-to-end accounting operating model: source transaction → validation → approval → subledger → general ledger → close → reporting.')">
    <x-slot:actions>
        <button type="button" class="lg-btn light" wire:click="export">⇩ {{ t('Export Rules') }}</button>
        <button type="button" class="lg-btn primary" wire:click="$set('modal', 'rule')">＋ {{ t('Create Workflow Rule') }}</button>
    </x-slot:actions>

    <div class="lg-cards">
        <x-ledger.metric :label="t('Source Documents')" icon="▤" :value="number_format($this->m['source_docs'])" :sub="str_replace(':period', now()->format('F Y'), t(':period transactions'))" />
        <x-ledger.metric :label="t('Pending Approval')" icon="◷" :value="$this->m['pending_n']" :pill="$this->m['pending_n'] ? str_replace(':amount', Fmt::short($this->m['pending_value']), t(':amount exposure')) : t('Queue empty')" :tone="$this->m['pending_n'] ? 'warn' : 'good'" />
        <x-ledger.metric :label="t('Posted to GL')" icon="✓" :value="number_format($this->m['posted_n'])" :pill="$this->m['tb_balanced'] ? t('Balanced') : t('Out of balance')" :tone="$this->m['tb_balanced'] ? 'good' : 'bad'" />
        <x-ledger.metric :label="t('Close Readiness')" icon="▣" :value="$this->m['close_pct'].'%'" :pill="$this->m['close_exceptions'] ? str_replace(':n', $this->m['close_exceptions'], t(':n control exceptions')) : t('No exceptions')" :tone="$this->m['close_exceptions'] ? 'warn' : 'good'" :sub="$this->m['close_period']?->period_name" />
    </div>

    <x-ledger.panel class="lg-stack" :title="t('Canonical Accounting Posting Flow')" :subtitle="t('All modules use this controlled lifecycle before entries affect financial statements.')">
        <x-slot:action><button type="button" class="lg-link" wire:click="$set('modal', 'rule')">{{ t('Configure rules') }}</button></x-slot:action>
        <x-ledger.flow :nodes="[
            [t('Source Event'), t('Invoice, bill, receipt, bank transaction, asset purchase, tax, or manual journal.'), $this->m['source_docs'] ?: null],
            [t('Validation'), t('Period open, master data, tax logic, document balance, duplicate and control-account checks.')],
            [t('Approval'), t('Role, amount and segregation-of-duties matrix (fin_approval_rule).'), $this->m['pending_n'] ?: null],
            [t('Subledger'), t('AR, AP, bank, fixed assets, tax and amortization schedules keep the detail.')],
            [t('GL Posting'), t('Balanced debit/credit voucher, audit trail, source reference, posting date.'), $this->m['posted_n'] ?: null],
            [t('Reporting & Close'), t('Trial balance, statements, tax workpapers, reconciliation, period lock.'), $this->m['close_pct'].'%'],
        ]" />
    </x-ledger.panel>

    <div class="lg-two">
        <x-ledger.panel :title="t('Module Integration Map')" :subtitle="t('Where business activity becomes accounting data.')">
            <div class="lg-list">
                <x-ledger.row icon="⇧" tone="bl" :title="t('Order to Cash')" :text="t('Invoice → AR → receipt allocation → bank → revenue and PPN posting')">
                    <a class="lg-link" href="{{ route('v2.screen', 'customer-invoice') }}">{{ t('Open') }}</a>
                </x-ledger.row>
                <x-ledger.row icon="⇩" tone="bl" :title="t('Procure to Pay')" :text="t('Purchase receive → GR/IR → bill → AP → payment, input PPN & PPh 23')">
                    <a class="lg-link" href="{{ route('finance.payables') }}">{{ t('Open') }}</a>
                </x-ledger.row>
                <x-ledger.row icon="▥" tone="gr" :title="t('Bank & Cash')" :text="t('Cashier vouchers → statement import → auto-match → reconciliation')">
                    <a class="lg-link" href="{{ route('finance.bank') }}">{{ t('Open') }}</a>
                </x-ledger.row>
                <x-ledger.row icon="▰" tone="pu" :title="t('Assets & Amortization')" :text="t('Capitalization → schedule → periodic depreciation/amortization journals')">
                    <a class="lg-link" href="{{ route('finance.assets') }}">{{ t('Open') }}</a>
                </x-ledger.row>
                <x-ledger.row icon="%" tone="or" :title="t('Tax Compliance')" :text="t('PPN & PPh derived from taxable events, reconciled to GL, then filed')">
                    <a class="lg-link" href="{{ route('finance.tax') }}">{{ t('Open') }}</a>
                </x-ledger.row>
                <x-ledger.row icon="↔" tone="bl" :title="t('Inventory & Production')" :text="t('Purchase receive and raw-material issue post automatically (PUR & USG journals)')">
                    <a class="lg-link" href="{{ route('finance.ledger') }}">{{ t('Open') }}</a>
                </x-ledger.row>
            </div>
        </x-ledger.panel>

        <x-ledger.panel :title="t('Controls Requiring Attention')" :subtitle="t('Exceptions block or qualify close.')">
            <x-slot:action><a class="lg-link" href="{{ route('finance.closing') }}">{{ t('Closing tasks') }}</a></x-slot:action>
            <div class="lg-list">
                @foreach ($this->attention as $a)
                    <x-ledger.row :icon="$a[0]" :tone="$a[1]" :title="$a[2]" :text="$a[3]">
                        <div class="lg-n">{{ $a[4] }}</div>
                    </x-ledger.row>
                @endforeach
            </div>
        </x-ledger.panel>
    </div>

    <x-ledger.panel :title="t('Workflow Rules')" :subtitle="t('Amount thresholds & approver groups per process; segregation of duties prevents makers from approving their own documents.')">
        <div class="lg-tablewrap">
            <table>
                <thead><tr><th>{{ t('Rule') }}</th><th>{{ t('Process') }}</th><th class="r">{{ t('Minimum amount') }}</th><th>{{ t('Approver group') }}</th><th>{{ t('Segregation of duties') }}</th><th>{{ t('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($this->rules as $r)
                        <tr wire:key="rule-{{ $r->rule_id }}">
                            <td><span class="lg-name">{{ $r->rule_name }}</span></td>
                            <td>{{ t(str_replace('_', ' ', $r->process_code)) }}</td>
                            <td class="r lg-money">{{ Fmt::rp($r->min_amount) }}</td>
                            <td>{{ $r->approver_group }}</td>
                            <td>{{ $r->segregation_of_duties ? t('Enforced') : t('Not enforced') }}</td>
                            <td><x-ledger.tag :tone="$r->activate ? 'good' : 'mute'">{{ $r->activate ? t('Active') : t('Inactive') }}</x-ledger.tag></td>
                            <td class="r"><button type="button" class="lg-link" wire:click="toggleRule({{ $r->rule_id }})">{{ $r->activate ? t('Disable') : t('Enable') }}</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="lg-empty">{{ t('No rules yet — without rules, manual journals can be posted directly without approval.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ledger.panel>

    @if ($modal === 'rule')
        <x-ledger.modal :title="t('Create Workflow Rule')">
            <div class="lg-mb">
                @if ($errorMessage)<div class="lg-alert err lg-field full">{{ $errorMessage }}</div>@endif
                <x-ledger.field :label="t('Rule name')" name="rule_name" full><input wire:model="rule_name" placeholder="{{ t('e.g. Manual journal above Rp 500 million') }}"></x-ledger.field>
                <x-ledger.field :label="t('Process')" name="process_code">
                    <select wire:model="process_code" x-data x-effect="$el.value = @js($process_code)">
                        <option value="JOURNAL">{{ t('Manual journal') }}</option>
                        <option value="CUSTOMER_INVOICE">{{ t('Customer invoice') }}</option>
                        <option value="VENDOR_BILL">{{ t('Vendor bill') }}</option>
                        <option value="BANK_ADJUSTMENT">{{ t('Bank adjustment') }}</option>
                        <option value="ASSET">{{ t('Asset capitalization') }}</option>
                    </select>
                </x-ledger.field>
                <x-ledger.field :label="t('Minimum amount (IDR)')" name="min_amount" :hint="t('0 = applies to all amounts.')"><input type="number" min="0" step="1000" wire:model="min_amount"></x-ledger.field>
                <x-ledger.field :label="t('Approver group')" name="approver_group">
                    <select wire:model="approver_group" x-data x-effect="$el.value = @js($approver_group)">
                        @foreach ($this->groupOptions as $code => $name)<option value="{{ $code }}">{{ $code }} · {{ $name }}</option>@endforeach
                    </select>
                </x-ledger.field>
                <x-ledger.field :label="t('Segregation of duties')">
                    <label style="display:flex;gap:8px;align-items:center;font-weight:600"><input type="checkbox" wire:model="sod"> {{ t('Maker may not approve') }}</label>
                </x-ledger.field>
            </div>
            <x-slot:footer>
                <button type="button" class="lg-btn light" wire:click="closeModal">{{ t('Cancel') }}</button>
                <button type="button" class="lg-btn primary" wire:click="saveRule">{{ t('Save') }}</button>
            </x-slot:footer>
        </x-ledger.modal>
    @endif
</x-ledger.page>
