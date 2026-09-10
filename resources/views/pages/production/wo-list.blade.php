<?php

use App\Livewire\Concerns\HasGridPagination;
use App\Models\Prd\PrdWo;
use App\Support\Localization\Translator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

// Worklist WO -- production_scheduling_tier4.md §2. Pola grid griduistandard
// diikuti dari resources/views/pages/pur/pr/index.blade.php (stat-row 4 kotak,
// identity-cell avatar+kode, badge status via match(), action-icon "Buka").
// WO tidak lahir dari grid ini (otomatis dari CO confirmed / cycle MTS) --
// Direvisi 2026-08-28: TERNYATA WO generation dari CO belum pernah beneran
// diimplementasikan (Tier1 F1) -- "+ New Record" di sini bukan bikin WO
// kosong manual, tapi ↗ ke production.wo.new (WO Detail dgn $wo=null,
// modal picklist Plan Delivery kebuka otomatis -> auto-generate). Screen #1
// yang dulu berdiri sendiri (wo-generate.blade.php) DIHAPUS 2026-08-28
// (permintaan user: gabung jadi 1 alur dgn WO Detail, bukan layar terpisah).
// Lihat docs/production_scheduling_wo_generation_delta.md.
new #[Title('Worklist Work Order — TELASIH MAKMUR ERP')] class extends Component
{
    use HasGridPagination;
    use WithPagination;

    public string $search = '';

    #[Computed]
    public function workOrders()
    {
        return PrdWo::query()
            ->with('coOrder.quotation.customer')
            ->when($this->search !== '', function ($q) {
                $term = $this->search;
                $q->where('wo_no', 'like', "%{$term}%");
            })
            ->orderByDesc('wo_id')
            ->paginate($this->perPage);
    }

    #[Computed]
    public function statTotal(): int
    {
        return PrdWo::count();
    }

    #[Computed]
    public function statInProgress(): int
    {
        return PrdWo::where('wo_status', 'IN_PROGRESS')->count();
    }

    #[Computed]
    public function statAtRisk(): int
    {
        return PrdWo::whereHas('operations', fn ($q) => $q->where('critical_ratio', '<', 1))->count();
    }

    #[Computed]
    public function statAwaitingSimulation(): int
    {
        return PrdWo::where('wo_status', 'DRAFT')->count();
    }

    public function openWo(int $id)
    {
        return $this->redirect(route('production.wo.entry', ['wo' => $id]));
    }
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">
        <x-grid.stat-row :count="4">
            <x-grid.stat-box tone="total" label="{{ Translator::t('prod_wo_stat_total', 'Total WO') }}" :value="$this->statTotal" />
            <x-grid.stat-box tone="active" label="{{ Translator::t('prod_wo_stat_in_progress', 'Sedang Berjalan') }}" :value="$this->statInProgress" />
            <x-grid.stat-box tone="critical" label="{{ Translator::t('prod_wo_stat_at_risk', 'Berisiko Telat') }}" :value="$this->statAtRisk" />
            <x-grid.stat-box tone="warn" label="{{ Translator::t('prod_wo_stat_awaiting_sim', 'Menunggu Simulasi') }}" :value="$this->statAwaitingSimulation" />
        </x-grid.stat-row>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ Translator::t('prod_wo_search_placeholder', 'Cari no. WO…') }}"
                        class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <span class="text-[11px] text-muted italic">{{ Translator::t('prod_wo_auto_note', 'Otomatis dari CO Confirmed (MTO) / cycle MTS Forecast — bukan dibuat manual dari sini.') }}</span>
                <x-slot:newRecord>
                    <a href="{{ route('production.wo.new') }}" class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#16a34a] text-white no-underline">+ {{ Translator::t('prod_wo_btn_generate', 'Generate dari Plan Delivery') }}</a>
                </x-slot:newRecord>
            </x-grid.toolbar>

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ Translator::t('prod_wo_col_wo_customer', 'WO / Customer') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[90px]">{{ Translator::t('prod_col_type', 'Tipe') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">{{ Translator::t('prod_col_qty', 'Qty') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">{{ Translator::t('prod_wo_col_due_date', 'Due Date') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[140px]">{{ Translator::t('prod_col_status', 'Status') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[60px]">{{ Translator::t('prod_col_action', 'Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->workOrders as $wo)
                            <tr wire:key="wo-{{ $wo->wo_id }}" wire:dblclick="openWo({{ $wo->wo_id }})" class="hover:bg-[#f7faff] cursor-pointer" title="{{ Translator::t('shared_grid_dblclick_hint', 'Klik 2x untuk buka') }}">
                                <x-grid.identity-cell :id="$wo->wo_id" :name="$wo->coOrder?->quotation?->customer?->customer_name ?? 'GENERAL'" :code="$wo->wo_no" />
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded {{ $wo->wo_type === 'MTS' ? 'bg-[#eef3fb] text-[#2f6fed]' : 'bg-[#eef1f5] text-[#4b5568]' }}">{{ $wo->wo_type }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right font-semibold">{{ number_format((float) $wo->qty, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $wo->due_date?->translatedFormat('d M Y') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <div class="flex items-center gap-1.5">
                                        @if ($wo->is_hot_job)
                                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#dc2626] text-white">🔥 HOT</span>
                                        @endif
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full
                                            {{ match ($wo->wo_status) {
                                                'DRAFT' => 'bg-[#eef1f5] text-[#4b5568]',
                                                'SIMULATED' => 'bg-[#eff6ff] text-[#1d4ed8]',
                                                'SCHEDULED' => 'bg-[#fffbeb] text-[#92400e]',
                                                'IN_PROGRESS' => 'bg-[#eaf7ee] text-[#1f8a4c]',
                                                'ON_HOLD' => 'bg-[#fff5e0] text-[#8a5a00]',
                                                'COMPLETED' => 'bg-[#dcfce7] text-[#15803d]',
                                                'CANCELLED' => 'bg-[#fbeaea] text-[#a3241f]',
                                                default => 'bg-[#eef1f5] text-[#4b5568]',
                                            } }}">{{ $wo->wo_status }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="↗" title="{{ Translator::t('shared_grid_action_open', 'Buka') }}" action="openWo({{ $wo->wo_id }})" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="6" message="{{ Translator::t('prod_wo_empty', 'Belum ada Work Order.') }}" />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>

            @include('components.grid.pagination-footer', ['paginator' => $this->workOrders])
        </div>
    </div>
</div>
