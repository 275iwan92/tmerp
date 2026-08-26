<?php

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinAccountTree;
use App\Models\Acc\FinCostCenter;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// financeaccounting_tier4.md — Master Chart of Accounts. Grid dari VIEW v1_fin_account_tree
// (tree_level + default_cc_code sudah dihitung DB, lihat FinAccountTree).
// account_id fin_account MANUAL (bukan AUTO_INCREMENT) — dibangkitkan otomatis
// di nextAccountId() saat tambah baru, user hanya isi account_code (kode GL).
new #[Title('Master Chart of Accounts — TELASIH MAKMUR ERP')] class extends Component
{
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

    public bool $coa_is_active = true;

    #[Computed]
    public function accounts()
    {
        return FinAccountTree::query()
            ->when($this->search !== '', fn ($q) => $q->where(fn ($qq) => $qq
                ->where('account_code', 'like', "%{$this->search}%")
                ->orWhere('account_name', 'like', "%{$this->search}%")))
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
        return FinCostCenter::query()->where('is_active', true)->orderBy('cc_code')->get();
    }

    #[Computed]
    public function metricTotalActive(): int
    {
        return FinAccount::query()->where('is_active', true)->count();
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
            'ASSET' => 'ASET',
            'LIABILITY' => 'KEWAJIBAN',
            'EQUITY' => 'EKUITAS',
            'REVENUE' => 'PENDAPATAN',
            'COGS' => 'HPP (COGS)',
            'EXPENSE' => 'BEBAN OPERASIONAL',
            'OTHER_INCOME' => 'PENDAPATAN LAIN',
            'OTHER_EXPENSE' => 'BEBAN LAIN',
            default => $type,
        };
    }

    public function reconBadge($row): ?array
    {
        if (! $row->is_postable) {
            return ['label' => 'Header', 'class' => 'bg-[#eef1f7] text-[#6b7488] border border-[#d8deeb]'];
        }
        if ($row->is_recon) {
            $label = $row->account_type === 'LIABILITY' ? 'RECON AP' : 'RECON AR';

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
        $a->is_active = ! $a->is_active;
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
            $this->coa_is_active = (bool) $a->is_active;
        } else {
            $this->reset(['parent_id', 'account_code', 'account_name', 'account_type', 'default_cc_id', 'tax_scheme_code']);
            $this->normal_balance = 'DEBIT';
            $this->is_recon = false;
            $this->is_postable = true;
            $this->coa_is_active = true;
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
            'coa_is_active' => ['boolean'],
        ]);

        if ($this->mode === 'edit' && $this->parent_id === $this->editingId) {
            $this->addError('parent_id', 'Akun tidak boleh jadi parent dirinya sendiri.');

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
            'is_active' => $data['coa_is_active'],
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
};
?>

<div>
    <div class="w-full px-5 py-4.5">

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-3.5">
            <div class="rounded-lg p-3.5 shadow-sm bg-[#eaf2ff] border border-[#c8ddfb]">
                <p class="text-[10.5px] font-bold uppercase text-[#3b5b99]">Total Akun Aktif</p>
                <p class="text-xl font-extrabold text-[#1d4ed8] mt-0.5">{{ $this->metricTotalActive }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#f1ecfc] border border-[#ddd0f7]">
                <p class="text-[10.5px] font-bold uppercase text-[#6b4fa0]">Akun Rekonsiliasi (AR/AP)</p>
                <p class="text-xl font-extrabold text-[#6d28d9] mt-0.5">{{ $this->metricTotalRecon }}</p>
                <p class="text-[10px] text-[#6b4fa0]/70">TERKUNCI</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#fef6e7] border border-[#f5e0b3]">
                <p class="text-[10.5px] font-bold uppercase text-[#8a6d00]">Akun HPP Produksi (COGS)</p>
                <p class="text-xl font-extrabold text-[#b45309] mt-0.5">{{ $this->metricTotalCogs }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#e9f9ef] border border-[#c8ecd6]">
                <p class="text-[10.5px] font-bold uppercase text-[#2f7a52]">Persediaan &amp; Tooling</p>
                <p class="text-xl font-extrabold text-[#16a34a] mt-0.5">{{ $this->metricTotalPersediaan }}</p>
            </div>
        </div>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <div class="flex items-center gap-2 bg-white border-b border-[#e2e7ee] px-2.5 py-2 overflow-x-auto">
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari kode / nama akun…"
                        class="w-56 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <span class="flex-1"></span>
                <button type="button" wire:click="openModal('new')"
                    class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
            </div>

            <div class="max-h-[70vh] overflow-auto">
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3.5 py-2.5 bg-[#f7f9fe] text-navy text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[150px]">
                                <button type="button" wire:click="toggleDetail" title="{{ $showDetail ? 'Ciutkan baris akun postable' : 'Tampilkan semua baris' }}"
                                    class="inline-flex items-center gap-1 cursor-pointer bg-transparent border-none text-navy text-[11px] uppercase tracking-wide font-bold p-0">
                                    <span>{{ $showDetail ? '▾' : '▸' }}</span> Kode
                                </button>
                            </th>
                            <th class="text-left px-3.5 py-2.5 bg-[#f7f9fe] text-navy text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Nama Akun</th>
                            <th class="text-left px-3.5 py-2.5 bg-[#f7f9fe] text-navy text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">Tipe</th>
                            <th class="text-left px-3.5 py-2.5 bg-[#f7f9fe] text-navy text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[95px]">Saldo</th>
                            <th class="text-left px-3.5 py-2.5 bg-[#f7f9fe] text-navy text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[160px]">Recon &amp; Tagging</th>
                            <th class="text-left px-3.5 py-2.5 bg-[#f7f9fe] text-navy text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[150px]">Default Cost Center</th>
                            <th class="text-right px-3.5 py-2.5 bg-[#f7f9fe] text-navy text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[80px]">Aksi</th>
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
                                    @if (! $a->is_active)
                                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-[#eef0f3] text-[#8a8f98] ml-1 align-middle">NONAKTIF</span>
                                    @endif
                                </td>
                                <td class="px-3.5 py-2 border-b border-[#eef1f5]">{{ $a->account_type }}</td>
                                <td class="px-3.5 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded uppercase {{ $a->normal_balance === 'DEBIT' ? 'bg-[#dff0ff] text-[#1d5fa8]' : 'bg-[#fdeecf] text-[#8a5a12]' }}">{{ $a->normal_balance === 'DEBIT' ? 'DEBIT' : 'KREDIT' }}</span>
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
                                        <button type="button" wire:click="openModal('edit', {{ $a->account_id }})" title="Edit" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">✎</button>
                                        <button type="button" wire:click="toggleActive({{ $a->account_id }})" wire:confirm="{{ $a->is_active ? 'Nonaktifkan' : 'Aktifkan' }} akun {{ $a->account_code }}?"
                                            title="{{ $a->is_active ? 'Nonaktifkan' : 'Aktifkan' }}" class="inline-flex items-center justify-center w-7 h-7 rounded-md cursor-pointer {{ $a->is_active ? 'text-[#c0392b] hover:bg-[#fdeceb]' : 'text-accent hover:bg-[#eef3fb]' }}">⏻</button>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center py-8 text-muted text-sm">Tidak ada akun.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ══ MODAL: TAMBAH/EDIT AKUN ══ --}}
    @if ($showModal)
        <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="closeModal">
            <div class="bg-white rounded-xl w-[520px] max-w-[92vw] max-h-[92vh] overflow-y-auto shadow-2xl">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold flex items-center gap-2 sticky top-0">
                    <span>{{ $mode === 'edit' ? 'Edit Akun · '.$account_code : 'Tambah Akun GL Baru' }}</span>
                    <span class="ml-auto cursor-pointer opacity-85 hover:opacity-100" wire:click="closeModal">✕</span>
                </div>
                <form wire:submit="save" class="p-4 flex flex-col gap-3">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-[#556]">Parent Akun</label>
                        <select wire:model="parent_id" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                            <option value="">— tanpa parent (root) —</option>
                            @foreach ($this->parentOptions as $p)
                                @if (! ($mode === 'edit' && $p->account_id === $editingId))
                                    <option value="{{ $p->account_id }}">{{ $p->account_code }} — {{ $p->account_name }}</option>
                                @endif
                            @endforeach
                        </select>
                        @error('parent_id') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex gap-2.5">
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Kode Akun *</label>
                            <input type="text" wire:model="account_code" maxlength="20" placeholder="mis. 115100" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px] font-mono">
                            @error('account_code') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex-[2] flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Nama Akun *</label>
                            <input type="text" wire:model="account_name" placeholder="mis. Kas Kecil Gudang" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                            @error('account_name') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex gap-2.5">
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Tipe *</label>
                            <select wire:model="account_type" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                                <option value="">— pilih —</option>
                                @foreach ($accountTypes as $t)
                                    <option value="{{ $t }}">{{ $this->accountTypeLabel($t) }}</option>
                                @endforeach
                            </select>
                            @error('account_type') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Saldo Normal *</label>
                            <select wire:model="normal_balance" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                                <option value="DEBIT">DEBIT</option>
                                <option value="CREDIT">CREDIT</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex gap-2.5">
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Default Cost Center</label>
                            <select wire:model="default_cc_id" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                                <option value="">— tanpa default —</option>
                                @foreach ($this->costCenterOptions as $cc)
                                    <option value="{{ $cc->cc_id }}">{{ $cc->cc_code }} — {{ $cc->cc_name }}</option>
                                @endforeach
                            </select>
                            @error('default_cc_id') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Skema Pajak</label>
                            <input type="text" wire:model="tax_scheme_code" maxlength="20" placeholder="PPN_IN / PPN_OUT / PPH22 / PPH23" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px] uppercase">
                            @error('tax_scheme_code') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-[13px] text-[#334]">
                        <input type="checkbox" wire:model="is_recon"> Akun rekonsiliasi (wajib partner di tiap baris jurnal — AR/AP)
                    </label>
                    <label class="flex items-center gap-2 text-[13px] text-[#334]">
                        <input type="checkbox" wire:model="is_postable"> Bisa dijurnal langsung <span class="text-muted">(nonaktifkan untuk akun header/kelompok)</span>
                    </label>
                    <label class="flex items-center gap-2 text-[13px] text-[#334]">
                        <input type="checkbox" wire:model="coa_is_active"> Aktif
                    </label>

                    <div class="text-[11px] text-muted leading-relaxed">Rate PPN/PPh tidak disimpan di sini — <code>Skema Pajak</code> hanya kode referensi (mis. PPN_OUT), nilai % diambil dari <code>tm2mst.fin_tax_scheme</code> saat posting voucher.</div>

                    <div class="flex gap-2 justify-end pt-2 border-t border-[#eef1f5] mt-1">
                        <button type="button" wire:click="closeModal" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
