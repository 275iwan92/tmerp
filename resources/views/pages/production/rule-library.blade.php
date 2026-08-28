<?php

use App\Models\Master\OrgDivision;
use App\Models\Master\ProdMachine;
use App\Models\Prd\PrdSchedulingRule;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Rule Library (Constraint Rule Library) -- production_scheduling_tier1.md §5A.7,
// tier4.md §1 ("Master-like, tapi butuh approval"). Grid griduistandard,
// TAPI toggle-aktif standar diganti Aktifkan/Nonaktifkan yang digate role
// (Tier1 §9.7: Manager Produksi/Operasional/Admin App ke atas) -- siapa saja
// yang can_input PROD boleh bikin DRAFT (Tier2 §9 matrix: PPIC = R/bisa draft).
new #[Title('Rule Library — TELASIH MAKMUR ERP')] class extends Component
{
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $rule_type = 'BATCHING';

    public string $rule_name = '';

    public ?int $applies_to_machine_id = null;

    public string $applies_to_process = '';

    public string $condition_field = '';

    public string $condition_operator = 'EQUALS';

    #[Computed]
    public function rules()
    {
        return PrdSchedulingRule::with('machine')->orderByDesc('rule_id')->get();
    }

    #[Computed]
    public function machines()
    {
        $offsetDivisionId = OrgDivision::where('division_code', 'OFFSET')->value('division_id');

        return ProdMachine::where('division_id', $offsetDivisionId)->where('activate', true)->orderBy('machine_name')->get();
    }

    #[Computed]
    public function statTotal(): int
    {
        return PrdSchedulingRule::count();
    }

    #[Computed]
    public function statActive(): int
    {
        return PrdSchedulingRule::where('rule_status', 'ACTIVE')->count();
    }

    #[Computed]
    public function statDraft(): int
    {
        return PrdSchedulingRule::where('rule_status', 'DRAFT')->count();
    }

    public function openNew(): void
    {
        $this->reset(['editingId', 'rule_name', 'applies_to_machine_id', 'applies_to_process', 'condition_field']);
        $this->rule_type = 'BATCHING';
        $this->condition_operator = 'EQUALS';
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $rule = PrdSchedulingRule::findOrFail($id);
        $this->editingId = $rule->rule_id;
        $this->rule_type = $rule->rule_type;
        $this->rule_name = $rule->rule_name;
        $this->applies_to_machine_id = $rule->applies_to_machine_id;
        $this->applies_to_process = $rule->applies_to_process ?? '';
        $this->condition_field = $rule->condition_field;
        $this->condition_operator = $rule->condition_operator;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function save(): void
    {
        $this->validate([
            'rule_name' => ['required', 'string', 'max:100'],
            'condition_field' => ['required', 'string', 'max:50'],
        ]);

        $data = [
            'rule_type' => $this->rule_type,
            'rule_name' => $this->rule_name,
            'applies_to_machine_id' => $this->applies_to_machine_id ?: null,
            'applies_to_process' => $this->applies_to_process ?: null,
            'condition_field' => $this->condition_field,
            'condition_operator' => $this->condition_operator,
            'updated_by' => Auth::user()->login,
        ];

        if ($this->editingId) {
            PrdSchedulingRule::whereKey($this->editingId)->update($data);
        } else {
            // Selalu lahir DRAFT -- ACTIVATE lewat aksi terpisah yang digate role (Tier1 §9.7).
            PrdSchedulingRule::create($data + ['rule_status' => 'DRAFT', 'created_by' => Auth::user()->login]);
        }

        $this->showModal = false;
    }

    public function activate(int $id): void
    {
        if (! Auth::user()->isProdManagerOrAbove()) {
            $this->dispatch('rule-toast', message: 'Hanya Manager Produksi ke atas yang bisa mengaktifkan rule.');

            return;
        }

        PrdSchedulingRule::findOrFail($id)->activate(Auth::user()->login);
        $this->dispatch('rule-toast', message: 'Rule diaktifkan.');
    }

    public function deactivate(int $id): void
    {
        if (! Auth::user()->isProdManagerOrAbove()) {
            $this->dispatch('rule-toast', message: 'Hanya Manager Produksi ke atas yang bisa menonaktifkan rule.');

            return;
        }

        PrdSchedulingRule::whereKey($id)->update(['rule_status' => 'INACTIVE', 'updated_by' => Auth::user()->login]);
        $this->dispatch('rule-toast', message: 'Rule dinonaktifkan.');
    }
};
?>

<div x-data="{ toastMsg: '', toastShow: false, toastTimer: null }"
    x-on:rule-toast.window="toastMsg = $event.detail.message; toastShow = true; clearTimeout(toastTimer); toastTimer = setTimeout(() => toastShow = false, 2500)">
    <div class="max-w-[1200px] mx-auto my-5 px-4">
        <x-grid.stat-row :count="3">
            <x-grid.stat-box tone="total" label="Total Rule" :value="$this->statTotal" />
            <x-grid.stat-box tone="active" label="Aktif" :value="$this->statActive" />
            <x-grid.stat-box tone="warn" label="Draft" :value="$this->statDraft" />
        </x-grid.stat-row>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <span class="text-[11px] text-muted italic">Batching (gabung task setipe) &amp; Sequencing (penghalus urutan lokal) -- Tier1 §5A.7.</span>
                <x-slot:newRecord>
                    <button type="button" wire:click="openNew" class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
                </x-slot:newRecord>
            </x-grid.toolbar>

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">Nama Rule</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">Tipe</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">Berlaku Untuk</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">Kondisi</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">Status</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->rules as $rule)
                            <tr wire:key="rule-{{ $rule->rule_id }}">
                                <td class="px-3 py-2 border-b border-[#eef1f5] font-semibold">{{ $rule->rule_name }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded {{ $rule->rule_type === 'BATCHING' ? 'bg-[#eef3fb] text-[#2f6fed]' : 'bg-[#f3eefb] text-[#7c3aed]' }}">{{ $rule->rule_type }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $rule->machine?->machine_name ?? $rule->applies_to_process ?? 'Semua' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] font-mono text-[11px]">{{ $rule->condition_field }} {{ $rule->condition_operator }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full
                                        {{ match ($rule->rule_status) {
                                            'DRAFT' => 'bg-[#eef1f5] text-[#4b5568]',
                                            'ACTIVE' => 'bg-[#dcfce7] text-[#15803d]',
                                            'INACTIVE' => 'bg-[#fbeaea] text-[#a3241f]',
                                            default => 'bg-[#eef1f5] text-[#4b5568]',
                                        } }}">{{ $rule->rule_status }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="✎" title="Edit" action="openEdit({{ $rule->rule_id }})" />
                                    @if ($rule->rule_status === 'ACTIVE')
                                        <button type="button" wire:click="deactivate({{ $rule->rule_id }})" class="text-[11px] font-semibold text-[#a3241f] hover:underline cursor-pointer ml-1">Nonaktifkan</button>
                                    @else
                                        <button type="button" wire:click="activate({{ $rule->rule_id }})" class="text-[11px] font-semibold text-[#15803d] hover:underline cursor-pointer ml-1">Aktifkan</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="6" message="Belum ada rule." />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
    </div>

    @if ($showModal)
        <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="closeModal">
            <div class="bg-white rounded-xl w-[480px] max-w-[95vw] shadow-2xl">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold flex items-center justify-between">
                    <span>{{ $editingId ? 'Edit Rule' : 'Rule Baru' }}</span>
                    <button type="button" wire:click="closeModal" class="text-white/70 hover:text-white cursor-pointer">✕</button>
                </div>
                <div class="p-4 space-y-3">
                    <div>
                        <label class="block text-[10px] font-bold text-muted uppercase mb-1.5">Tipe</label>
                        <select wire:model="rule_type" class="w-full px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                            <option value="BATCHING">Batching</option>
                            <option value="SEQUENCING">Sequencing</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-muted uppercase mb-1.5">Nama Rule</label>
                        <input type="text" wire:model="rule_name" placeholder="mis. Gabung dieline sama" class="w-full px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                        @error('rule_name') <span class="text-[10px] text-[#a3241f]">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold text-muted uppercase mb-1.5">Mesin (opsional)</label>
                            <select wire:model="applies_to_machine_id" class="w-full px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                                <option value="">-- semua mesin --</option>
                                @foreach ($this->machines as $m)
                                    <option value="{{ $m->machine_id }}">{{ $m->machine_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-muted uppercase mb-1.5">Proses (opsional)</label>
                            <input type="text" wire:model="applies_to_process" placeholder="mis. Cetak" class="w-full px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold text-muted uppercase mb-1.5">Field Kondisi</label>
                            <input type="text" wire:model="condition_field" placeholder="mis. dieline_ref" class="w-full px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                            @error('condition_field') <span class="text-[10px] text-[#a3241f]">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-muted uppercase mb-1.5">Operator</label>
                            <select wire:model="condition_operator" class="w-full px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                                <option value="EQUALS">Sama Dengan (batching)</option>
                                <option value="SORT_ASC">Urut Naik (sequencing)</option>
                                <option value="SORT_DESC">Urut Turun (sequencing)</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-3 mt-2 border-t border-[#eef1f5]">
                        <button type="button" wire:click="closeModal" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Batal</button>
                        <button type="button" wire:click="save" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#2c3e69] text-white cursor-pointer">Simpan sebagai Draft</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div x-show="toastShow" x-transition x-cloak
        class="fixed bottom-5 left-1/2 -translate-x-1/2 bg-[#1f2328] text-white text-xs font-medium px-4 py-2.5 rounded-md shadow-lg z-[200]"
        x-text="toastMsg"></div>
</div>
