<?php

use App\Models\Pur\PoOrder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Outstanding PO = PO yang sudah SENT/PARTIALLY_RECEIVED tapi baris-nya belum
// (sepenuhnya) diterima -- i_qty > c_qty_received, hanya baris is_receivable
// (baris SERVICE tak pernah lewat PV -- PROC PV-09). Sejalan dgn
// docs/procurement_tier3.md view v1_po_outstanding. Beda dgn Outstanding PR, header
// po_order.status DI-MAINTAIN dgn benar oleh PvPostingService::recalcPo(),
// jadi filter status cukup dipercaya -- qty dihitung ulang cuma utk nilai
// tampilan, bukan penjaga utama spt di Outstanding PR.
new #[Title('Outstanding PO — TELASIH MAKMUR ERP')] class extends Component
{
    public string $search = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    #[Computed]
    public function orders()
    {
        return PoOrder::with('lines')
            ->whereIn('status', ['SENT', 'PARTIALLY_RECEIVED'])
            ->where('stat_cancel', false)
            ->when($this->dateFrom !== '', fn ($q) => $q->whereDate('i_doc_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($q) => $q->whereDate('i_doc_date', '<=', $this->dateTo))
            ->orderBy('i_doc_date')
            ->get();
    }

    #[Computed]
    public function supplierNames(): array
    {
        $ids = $this->orders->pluck('i_supplier_id')->filter()->unique();

        return DB::connection('tm2mst')->table('sup_supplier')->whereIn('id_supplier', $ids)->pluck('supplier_name', 'id_supplier')->all();
    }

    #[Computed]
    public function rows()
    {
        return $this->orders
            ->map(function ($po) {
                $outstandingLines = $po->lines->filter(fn ($l) => $l->is_receivable && bcsub((string) $l->i_qty, (string) ($l->c_qty_received ?? 0), 4) > 0);

                return (object) [
                    'po' => $po,
                    'lineCount' => $outstandingLines->count(),
                    'value' => $outstandingLines->sum(function ($l) {
                        $outstandingQty = (float) bcsub((string) $l->i_qty, (string) ($l->c_qty_received ?? 0), 4);
                        $qty = (float) $l->i_qty;

                        return $qty > 0 ? $outstandingQty / $qty * (float) ($l->c_line_net ?? 0) : 0;
                    }),
                ];
            })
            ->filter(fn ($r) => $r->lineCount > 0)
            ->when($this->search !== '', function ($rows) {
                $term = mb_strtolower($this->search);
                $suppliers = $this->supplierNames;

                return $rows->filter(fn ($r) => str_contains(mb_strtolower($r->po->doc_no ?? ''), $term)
                    || str_contains(mb_strtolower($suppliers[$r->po->i_supplier_id] ?? ''), $term));
            })
            ->sortBy(fn ($r) => $r->po->i_doc_date)
            ->values();
    }

    #[Computed]
    public function statCount(): int
    {
        return $this->rows->count();
    }

    #[Computed]
    public function statLines(): int
    {
        return $this->rows->sum('lineCount');
    }

    #[Computed]
    public function statValue(): float
    {
        return $this->rows->sum('value');
    }

    public function resetFilter(): void
    {
        $this->search = '';
        $this->dateFrom = '';
        $this->dateTo = '';
    }

    public function openPo(int $id)
    {
        return $this->redirect(route('pur.po.entry', ['po' => $id]));
    }

    // Componentization grid (2026-08-27): avatarInitials()/avatarGradient()
    // dipindah ke App\Support\Grid\Avatar (dipakai <x-grid.identity-cell>).
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">
        <x-grid.stat-row :count="3">
            <x-grid.stat-box tone="total" :label="t('Outstanding POs')" :value="$this->statCount" />
            <x-grid.stat-box tone="extra" :label="t('Outstanding Lines')" :value="$this->statLines" />
            <x-grid.stat-box tone="warn" :label="t('Outstanding Value')" :value="number_format($this->statValue, 0, ',', '.')" />
        </x-grid.stat-row>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar class="flex-wrap">
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ t('Search PO no. / supplier…') }}"
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
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('Supplier / PO No.') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">{{ t('PO Date') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">{{ t('Delivery Date') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[130px]">{{ t('Outstanding Lines') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[140px]">{{ t('Outstanding Value') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[130px]">{{ t('Status') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[60px]">{{ t('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->rows as $r)
                            <tr wire:key="opo-{{ $r->po->po_id }}" wire:dblclick="openPo({{ $r->po->po_id }})" class="hover:bg-[#f7faff] cursor-pointer" title="{{ t('Double-click to open') }}">
                                <x-grid.identity-cell :id="$r->po->po_id" :name="$this->supplierNames[$r->po->i_supplier_id] ?? '—'" :code="$r->po->doc_no" />
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $r->po->i_doc_date?->translatedFormat('d M Y') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $r->po->i_delivery_date?->translatedFormat('d M Y') ?? '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right">{{ $r->lineCount }} / {{ $r->po->lines->where('is_receivable', true)->count() }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right font-semibold">{{ number_format($r->value, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $r->po->status === 'PARTIALLY_RECEIVED' ? 'bg-[#fff5e0] text-[#8a5a00]' : 'bg-[#eef1f5] text-[#4b5568]' }}">{{ $r->po->status }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-up-right-from-square" title="{{ t('Open') }}" action="openPo({{ $r->po->po_id }})" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="7" message="{{ t('No outstanding POs in this range/filter.') }}" />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
    </div>
</div>
