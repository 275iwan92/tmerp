<?php

use App\Livewire\Concerns\HasGridStatFilter;
use App\Models\Acc\FinAccount;
use App\Models\Acc\FinAccountTree;
use App\Models\Acc\FinCostCenter;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use App\Livewire\Concerns\OpensFromQuery;
use Livewire\Component;

// financeaccounting_tier4.md — Master Chart of Accounts. Grid dari VIEW v1_fin_account_tree
// (tree_level + default_cc_code sudah dihitung DB, lihat FinAccountTree).
// account_id fin_account MANUAL (bukan AUTO_INCREMENT) — dibangkitkan otomatis
// di nextAccountId() saat tambah baru, user hanya isi account_code (kode GL).
new #[Title('Master Chart of Accounts — TELASIH MAKMUR ERP')] class extends Component
{
    use OpensFromQuery;

    protected function openFromQuery(int $id): void
    {
        $this->openModal('edit', $id);
    }

    protected function newFromQuery(): void
    {
        $this->openModal('new');
    }

    use HasGridStatFilter;

    public array $accountTypes = ['ASSET', 'LIABILITY', 'EQUITY', 'REVENUE', 'COGS', 'EXPENSE', 'OTHER_INCOME', 'OTHER_EXPENSE'];

    public string $search = '';

    public bool $showDetail = true; // false = ciutkan baris lvl-3 (postable), header tetap tampil

    public bool $showModal = false;

    public string $mode = 'new'; // new | edit

    public ?int $editingId = null;

    public ?int $parent_id = null;

    public string $account_code = '';

    public string $account_name = '';

    public string $account_type = '';

    public string $normal_balance = 'DEBIT';

    public bool $is_recon = false;

    public bool $is_postable = true;

    public ?int $default_cc_id = null;

    public string $tax_scheme_code = '';

    public bool $coa_activate = true;

    #[Computed]
    public function accounts()
    {
        return FinAccountTree::query()
            ->when($this->search !== '', fn ($q) => $q->where(fn ($qq) => $qq
                ->where('account_code', 'like', "%{$this->search}%")
                ->orWhere('account_name', 'like', "%{$this->search}%")))
            ->tap(fn ($q) => $this->applyStatFilter($q))
            ->orderBy('account_code')
            ->get();
    }

    #[Computed]
    public function parentOptions()
    {
        return FinAccount::query()->orderBy('account_code')->get(['account_id', 'account_code', 'account_name']);
    }

    #[Computed]
    public function costCenterOptions()
    {
        return FinCostCenter::query()->where('activate', true)->orderBy('cc_code')->get();
    }

    #[Computed]
    public function metricTotalActive(): int
    {
        return FinAccount::query()->where('activate', true)->count();
    }

    #[Computed]
    public function metricTotalRecon(): int
    {
        return FinAccount::query()->where('is_recon', true)->count();
    }

    #[Computed]
    public function metricTotalCogs(): int
    {
        return FinAccount::query()->where('account_type', 'COGS')->where('is_postable', true)->count();
    }

    #[Computed]
    public function metricTotalPersediaan(): int
    {
        return FinAccount::query()->where(fn ($q) => $q
            ->where('account_code', 'like', '1131%')
            ->orWhere('account_code', 'like', '1132%')
            ->orWhere('account_code', 'like', '1133%')
            ->orWhere('account_code', 'like', '1134%')
            ->orWhere('account_code', 'like', '1135%'))
            ->count();
    }

    public function accountTypeLabel(string $type): string
    {
        return match ($type) {
            'ASSET' => t('ASSET'),
            'LIABILITY' => t('LIABILITY'),
            'EQUITY' => t('EQUITY'),
            'REVENUE' => t('REVENUE'),
            'COGS' => t('COGS'),
            'EXPENSE' => t('OPERATING EXPENSE'),
            'OTHER_INCOME' => t('OTHER INCOME'),
            'OTHER_EXPENSE' => t('OTHER EXPENSE'),
            default => $type,
        };
    }

    public function reconBadge($row): ?array
    {
        if (! $row->is_postable) {
            return ['label' => t('Header'), 'class' => 'bg-[#eef1f7] text-[#6b7488] border border-[#d8deeb]'];
        }
        if ($row->is_recon) {
            $label = $row->account_type === 'LIABILITY' ? t('RECON AP') : t('RECON AR');

            return ['label' => $label, 'class' => 'bg-[#e5edff] text-navy border border-[#c7d8fb]'];
        }
        if ($row->tax_scheme_code) {
            return ['label' => $row->tax_scheme_code, 'class' => 'bg-[#eef1f7] text-[#475069] border border-[#d8deeb]'];
        }

        return null;
    }

    public function toggleDetail(): void
    {
        $this->showDetail = ! $this->showDetail;
    }

    public function toggleActive(int $accountId): void
    {
        $a = FinAccount::findOrFail($accountId);
        $a->activate = ! $a->activate;
        $a->updated_by = Auth::user()->login;
        $a->save();

        unset($this->accounts, $this->metricTotalActive, $this->metricTotalRecon, $this->metricTotalCogs, $this->metricTotalPersediaan);
    }

    public function openModal(string $mode, ?int $id = null): void
    {
        $this->resetErrorBag();
        $this->mode = in_array($mode, ['new', 'edit'], true) ? $mode : 'new';
        $this->editingId = $id;

        if ($this->mode === 'edit' && $id) {
            $a = FinAccount::findOrFail($id);

            // Header (is_postable=0) tak boleh diedit lewat form — financeaccounting_tier4.md §3.
            if (! $a->is_postable) {
                return;
            }

            $this->parent_id = $a->parent_id;
            $this->account_code = $a->account_code;
            $this->account_name = $a->account_name;
            $this->account_type = $a->account_type;
            $this->normal_balance = $a->normal_balance;
            $this->is_recon = (bool) $a->is_recon;
            $this->is_postable = (bool) $a->is_postable;
            $this->default_cc_id = $a->default_cc_id;
            $this->tax_scheme_code = (string) $a->tax_scheme_code;
            $this->coa_activate = (bool) $a->activate;
        } else {
            $this->reset(['parent_id', 'account_code', 'account_name', 'account_type', 'default_cc_id', 'tax_scheme_code']);
            $this->normal_balance = 'DEBIT';
            $this->is_recon = false;
            $this->is_postable = true;
            $this->coa_activate = true;
        }

        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    private function nextAccountId(): int
    {
        return (int) (FinAccount::query()->max('account_id') ?? 0) + 1;
    }

    public function save(): void
    {
        $unique = 'unique:tm2acc.fin_account,account_code';
        if ($this->mode === 'edit' && $this->editingId) {
            $unique .= ','.$this->editingId.',account_id';
        }

        $data = $this->validate([
            'parent_id' => ['nullable', 'integer', 'exists:tm2acc.fin_account,account_id'],
            'account_code' => ['required', 'string', 'max:20', $unique],
            'account_name' => ['required', 'string', 'max:150'],
            'account_type' => ['required', 'string', 'in:'.implode(',', $this->accountTypes)],
            'normal_balance' => ['required', 'string', 'in:DEBIT,CREDIT'],
            'is_recon' => ['boolean'],
            'is_postable' => ['boolean'],
            'default_cc_id' => ['nullable', 'integer', 'exists:tm2acc.fin_cost_center,cc_id'],
            'tax_scheme_code' => ['nullable', 'string', 'max:20'],
            'coa_activate' => ['boolean'],
        ]);

        if ($this->mode === 'edit' && $this->parent_id === $this->editingId) {
            $this->addError('parent_id', t('An account cannot be its own parent.'));

            return;
        }

        $payload = [
            'parent_id' => $data['parent_id'] ?: null,
            'account_code' => $data['account_code'],
            'account_name' => $data['account_name'],
            'account_type' => $data['account_type'],
            'normal_balance' => $data['normal_balance'],
            'is_recon' => $data['is_recon'],
            'is_postable' => $data['is_postable'],
            'default_cc_id' => $data['default_cc_id'] ?: null,
            'tax_scheme_code' => $data['tax_scheme_code'] ?: null,
            'activate' => $data['coa_activate'],
            'updated_by' => Auth::user()->login,
        ];

        if ($this->mode === 'edit' && $this->editingId) {
            FinAccount::whereKey($this->editingId)->update($payload);
        } else {
            $payload['account_id'] = $this->nextAccountId();
            $payload['created_by'] = Auth::user()->login;
            FinAccount::create($payload);
        }

        unset($this->accounts, $this->parentOptions, $this->metricTotalActive, $this->metricTotalRecon, $this->metricTotalCogs, $this->metricTotalPersediaan);
        $this->showModal = false;
    }

    // Kotak statistik = filter data (griduistandard.md §2b). Semua kotak di
    // layar ini kategori baris akun, jadi keempatnya bisa diklik. Filter
    // "Persediaan & Tooling" memakai pola kode akun yang sama dgn angkanya.
    protected function statFilterDefs(): array
    {
        return [
            'active' => ['label' => t('Total Active Accounts'), 'value' => $this->metricTotalActive, 'tone' => 'total',
                'filter' => fn ($q) => $q->where('activate', true)],
            'recon' => ['label' => t('Reconciliation Accounts (AR/AP)'), 'value' => $this->metricTotalRecon, 'tone' => 'extra',
                'subtitle' => t('LOCKED'),
                'filter' => fn ($q) => $q->where('is_recon', true)],
            'cogs' => ['label' => t('Production COGS Accounts'), 'value' => $this->metricTotalCogs, 'tone' => 'warn',
                'filter' => fn ($q) => $q->where('account_type', 'COGS')->where('is_postable', true)],
            'inventory' => ['label' => t('Inventory & Tooling'), 'value' => $this->metricTotalPersediaan, 'tone' => 'active',
                'filter' => fn ($q) => $q->where(fn ($qq) => $qq
                    ->where('account_code', 'like', '1131%')
                    ->orWhere('account_code', 'like', '1132%')
                    ->orWhere('account_code', 'like', '1133%')
                    ->orWhere('account_code', 'like', '1134%')
                    ->orWhere('account_code', 'like', '1135%'))],
        ];
    }
};
?>

<div>
    <div class="w-full px-5 py-4.5">

        <x-grid.stat-filter-row :boxes="$this->statBoxes()" :active="$statFilter" />

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ t('Search account code / name…') }}"
                        class="w-56 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <x-slot:newRecord>
                    <button type="button" wire:click="openModal('new')"
                        class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
                </x-slot:newRecord>
            </x-grid.toolbar>

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3.5 py-2.5 bg-[#f7f9fe] text-navy text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[150px]">
                                <button type="button" wire:click="toggleDetail" title="{{ $showDetail ? t('Collapse postable account rows') : t('Show all rows') }}"
                                    class="inline-flex items-center gap-1 cursor-pointer bg-transparent border-none text-navy text-[11px] uppercase tracking-wide font-bold p-0">
                                    <span>{{ $showDetail ? '▾' : '▸' }}</span> {{ t('Code') }}
                                </button>
                            </th>
                            <th class="text-left px-3.5 py-2.5 bg-[#f7f9fe] text-navy text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('Account Name') }}</th>
                            <th class="text-left px-3.5 py-2.5 bg-[#f7f9fe] text-navy text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">{{ t('Type') }}</th>
                            <th class="text-left px-3.5 py-2.5 bg-[#f7f9fe] text-navy text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[95px]">{{ t('Balance') }}</th>
                            <th class="text-left px-3.5 py-2.5 bg-[#f7f9fe] text-navy text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[160px]">{{ t('Recon & Tagging') }}</th>
                            <th class="text-left px-3.5 py-2.5 bg-[#f7f9fe] text-navy text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[150px]">{{ t('Default Cost Center') }}</th>
                            <th class="text-right px-3.5 py-2.5 bg-[#f7f9fe] text-navy text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[80px]">{{ t('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->accounts as $a)
                            @continue(! $showDetail && $a->tree_level == 3)
                            @php $recon = $this->reconBadge($a); @endphp
                            <tr wire:key="acc-{{ $a->account_id }}"
                                class="{{ match ((int) $a->tree_level) {
                                    1 => 'bg-[#eef2fb] font-extrabold text-navy uppercase',
                                    2 => 'font-bold text-[#2a3352]',
                                    default => 'hover:bg-[#f7faff]',
                                } }}">
                                <td class="px-3.5 py-2 border-b border-[#eef1f5] font-mono {{ $a->tree_level == 3 ? 'pl-[42px]' : ($a->tree_level == 2 ? 'pl-6' : '') }}">{{ $a->account_code }}</td>
                                <td class="px-3.5 py-2 border-b border-[#eef1f5]">
                                    {{ $a->account_name }}
                                    @if (! $a->activate)
                                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-[#eef0f3] text-[#8a8f98] ml-1 align-middle">{{ t('INACTIVE') }}</span>
                                    @endif
                                </td>
                                <td class="px-3.5 py-2 border-b border-[#eef1f5]">{{ $a->account_type }}</td>
                                <td class="px-3.5 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded uppercase {{ $a->normal_balance === 'DEBIT' ? 'bg-[#dff0ff] text-[#1d5fa8]' : 'bg-[#fdeecf] text-[#8a5a12]' }}">{{ $a->normal_balance === 'DEBIT' ? t('DEBIT') : t('CREDIT') }}</span>
                                </td>
                                <td class="px-3.5 py-2 border-b border-[#eef1f5]">
                                    @if ($recon)
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded uppercase {{ $recon['class'] }}">{{ $recon['label'] }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3.5 py-2 border-b border-[#eef1f5]">
                                    @if ($a->default_cc_code)
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-[#e8f7ee] text-[#166534] border border-[#bfe6cd]">{{ $a->default_cc_code }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3.5 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    @if ($a->is_postable)
                                        <x-grid.action-icon icon="fa-pen" title="{{ t('Edit') }}" action="openModal('edit', {{ $a->account_id }})" />
                                        <x-grid.action-icon :active="$a->activate" :title="$a->activate ? t('Deactivate') : t('Activate')" action="toggleActive({{ $a->account_id }})" confirm="{{ $a->activate ? t('Deactivate') : t('Activate') }} {{ t('account') }} {{ $a->account_code }}?" />
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="7" message="{{ t('No accounts.') }}" />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
    </div>

    {{-- ══ MODAL: TAMBAH/EDIT AKUN ══ --}}
    @if ($showModal)
        <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="closeModal">
            <div class="bg-white rounded-xl w-[520px] max-w-[92vw] max-h-[92vh] overflow-y-auto shadow-2xl">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold flex items-center gap-2 sticky top-0">
                    <span>{{ $mode === 'edit' ? t('Edit Account ·').' '.$account_code : t('Add New GL Account') }}</span>
                    <span class="ml-auto cursor-pointer opacity-85 hover:opacity-100" wire:click="closeModal">✕</span>
                </div>
                <form wire:submit="save" class="p-4 flex flex-col gap-3">
                    <x-form.select label="{{ t('Parent Account') }}" wire:model="parent_id" placeholder="{{ t('— no parent (root) —') }}" name="parent_id"
                        :options="$this->parentOptions->filter(fn ($p) => ! ($mode === 'edit' && $p->account_id === $editingId))->mapWithKeys(fn ($p) => [$p->account_id => $p->account_code.' — '.$p->account_name])->all()" />

                    <div class="flex gap-2.5">
                        <div class="flex-1">
                            <x-form.input label="{{ t('Account Code *') }}" wire:model="account_code" maxlength="20" placeholder="{{ t('e.g. 115100') }}" class="font-mono" name="account_code" />
                        </div>
                        <div class="flex-[2]">
                            <x-form.input label="{{ t('Account Name *') }}" wire:model="account_name" placeholder="{{ t('e.g. Warehouse Petty Cash') }}" name="account_name" />
                        </div>
                    </div>

                    <div class="flex gap-2.5">
                        <div class="flex-1">
                            <x-form.select label="{{ t('Type *') }}" wire:model="account_type" name="account_type"
                                :options="collect($accountTypes)->mapWithKeys(fn ($t) => [$t => $this->accountTypeLabel($t)])->all()" />
                        </div>
                        <div class="flex-1">
                            <x-form.select label="{{ t('Normal Balance *') }}" wire:model="normal_balance" :placeholder="false" :options="['DEBIT' => 'DEBIT', 'CREDIT' => 'CREDIT']" />
                        </div>
                    </div>

                    <div class="flex gap-2.5">
                        <div class="flex-1">
                            <x-form.select label="{{ t('Default Cost Center') }}" wire:model="default_cc_id" placeholder="{{ t('— no default —') }}" name="default_cc_id"
                                :options="$this->costCenterOptions->mapWithKeys(fn ($cc) => [$cc->cc_id => $cc->cc_code.' — '.$cc->cc_name])->all()" />
                        </div>
                        <div class="flex-1">
                            <x-form.input label="{{ t('Tax Scheme') }}" wire:model="tax_scheme_code" maxlength="20" placeholder="PPN_IN / PPN_OUT / PPH22 / PPH23" class="uppercase" name="tax_scheme_code" />
                        </div>
                    </div>

                    <x-form.checkbox wire:model="is_recon" label-class="text-[#334]">
                        {{ t('Reconciliation account (requires a partner on every journal line — AR/AP)') }}
                    </x-form.checkbox>
                    <x-form.checkbox wire:model="is_postable" label-class="text-[#334]">
                        {{ t('Can be journaled directly') }} <span class="text-muted">{{ t('(disable for header/group accounts)') }}</span>
                    </x-form.checkbox>
                    <x-form.checkbox wire:model="coa_activate" label="{{ t('Active') }}" label-class="text-[#334]" />

                    <div class="text-[11px] text-muted leading-relaxed">{{ t('The VAT/WHT rate is not stored here —') }} <code>{{ t('Tax Scheme') }}</code> {{ t('only a reference code (e.g. PPN_OUT); the % value is taken from') }} <code>tm2mst.fin_tax_scheme</code> {{ t('when posting the voucher.') }}</div>

                    <div class="flex gap-2 justify-end pt-2 border-t border-[#eef1f5] mt-1">
                        <button type="button" wire:click="closeModal" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">{{ t('Cancel') }}</button>
                        <button type="submit" class="px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">{{ t('Save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
