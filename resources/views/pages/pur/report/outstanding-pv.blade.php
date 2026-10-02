<?php

use App\Models\Master\SupSupplier;
use App\Models\Pur\PvReceipt;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// docs/BACKLOG.md §7 "Outstanding PV" -- PV yang SUDAH POSTED (barang sudah
// masuk stok gudang) tapi BELUM ditandai diserahkan ke departemen peminta
// (is_handed_over, migration 2026_09_02_000033). Keputusan desain: flag
// header-level per-PV (opsi paling sederhana dari 3 yang diajukan dulu),
// sama gaya Outstanding PR/PO existing (pur/report/outstanding-pr.blade.php).
new #[Title('Outstanding PV — TELASIH MAKMUR ERP')] class extends Component
{
    public string $search = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    #[Computed]
    public function rows()
    {
        $pvs = PvReceipt::where('status', 'POSTED')
            ->where('is_handed_over', false)
            ->when($this->dateFrom !== '', fn ($q) => $q->whereDate('i_doc_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($q) => $q->whereDate('i_doc_date', '<=', $this->dateTo))
            ->when($this->search !== '', fn ($q) => $q->where('doc_no', 'like', '%'.$this->search.'%'))
            ->orderBy('i_doc_date')
            ->get();

        $suppliers = SupSupplier::whereIn('id_supplier', $pvs->pluck('i_supplier_id')->unique())
            ->pluck('supplier_name', 'id_supplier');

        return $pvs->map(fn ($pv) => (object) [
            'pv' => $pv,
            'supplierName' => $suppliers->get($pv->i_supplier_id) ?? '—',
            'daysWaiting' => (int) $pv->i_doc_date->diffInDays(now(), true),
        ]);
    }

    #[Computed]
    public function statCount(): int
    {
        return $this->rows->count();
    }

    #[Computed]
    public function statOver3Days(): int
    {
        return $this->rows->where('daysWaiting', '>', 3)->count();
    }

    public function resetFilter(): void
    {
        $this->search = '';
        $this->dateFrom = '';
        $this->dateTo = '';
    }

    public function markHandedOver(int $pvId): void
    {
        PvReceipt::where('pv_id', $pvId)->where('status', 'POSTED')->update([
            'is_handed_over' => true,
            'handed_over_at' => now(),
            'handed_over_by' => Auth::user()->login,
        ]);

        unset($this->rows);
    }
};
?>

<div>
    <div class="max-w-[1200px] mx-auto my-5 px-4">
        <x-grid.stat-row :count="2">
            <x-grid.stat-box tone="total" :label="t('Outstanding PVs (not handed over)')" :value="$this->statCount" />
            <x-grid.stat-box tone="warn" :label="t('Waiting > 3 Days')" :value="$this->statOver3Days" />
        </x-grid.stat-row>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar class="flex-wrap">
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ t('Search PV no.…') }}"
                        class="w-56 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <div class="flex items-center gap-1.5 shrink-0">
                    <label class="text-[10.5px] text-muted font-semibold">{{ t('From') }}</label>
                    <x-form.date-picker wire:model.live="dateFrom" class="w-[130px]" />
                    <label class="text-[10.5px] text-muted font-semibold">{{ t('until') }}</label>
                    <x-form.date-picker wire:model.live="dateTo" class="w-[130px]" />
                </div>
                @if ($search !== '' || $dateFrom !== '' || $dateTo !== '')
                    <x-slot:tools>
                        <button type="button" wire:click="resetFilter" class="shrink-0 text-[11px] font-semibold text-muted hover:text-navy underline">{{ t('Reset filter') }}</button>
                    </x-slot:tools>
                @endif
            </x-grid.toolbar>

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('Supplier / PV No.') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">{{ t('Received Date') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('Warehouse') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('PO Ref.') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">{{ t('Waiting') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[160px]">{{ t('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->rows as $r)
                            <tr wire:key="opv-{{ $r->pv->pv_id }}">
                                <x-grid.identity-cell :id="$r->pv->pv_id" :name="$r->supplierName" :code="$r->pv->doc_no" />
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $r->pv->i_doc_date?->translatedFormat('d M Y') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $r->pv->i_warehouse_code }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-muted">{{ $r->pv->c_po_doc_list ?? '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right {{ $r->daysWaiting > 3 ? 'text-[#c0392b] font-bold' : '' }}">{{ str_replace(':n', $r->daysWaiting, t(':n days')) }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right">
                                    <button type="button" wire:click="markHandedOver({{ $r->pv->pv_id }})" wire:confirm="{{ str_replace(':doc', $r->pv->doc_no, t('Mark PV :doc as handed over to the requesting department?')) }}"
                                        class="text-[11px] font-bold text-[#0e8a4f] cursor-pointer">✓ {{ t('Handed Over') }}</button>
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="6" message="{{ t('No outstanding PVs in this range/filter.') }}" />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
    </div>
</div>
