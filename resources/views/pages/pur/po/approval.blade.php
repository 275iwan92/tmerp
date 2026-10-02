<?php

use App\Models\Pur\PoOrder;
use App\Services\Flow\FlowEngineService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// griduistandard.md diikuti (2026-08-22). Approve/Reject/Hold TIDAK LAGI di
// layar ini -- dipindah ke form PO yang sama (pur/po/entry.blade.php), persis
// pola ME29N di SAP / action pane Dynamics 365 / approval di form Odoo:
// approver melihat & memutuskan langsung di dokumen aslinya, bukan ringkasan
// terpisah. Layar ini murni ANTRIAN -- grid, klik 2x buka dokumen aslinya.
new #[Title('PO Approval Center — TELASIH MAKMUR ERP')] class extends Component
{
    public string $search = '';

    #[Computed]
    public function pendingQueue()
    {
        return app(FlowEngineService::class)->pendingForUser('PO', Auth::id());
    }

    // FlowLog (tm2mst) tak bisa di-join fisik ke po_order (tm2pur) -- beda DB
    // (CLAUDE.md §2). Ambil dokumennya lewat query terpisah, cocokkan di PHP.
    #[Computed]
    public function orders(): array
    {
        $ids = $this->pendingQueue->pluck('doc_id')->unique();

        return PoOrder::whereIn('po_id', $ids)->get()->keyBy('po_id')->all();
    }

    #[Computed]
    public function supplierNames(): array
    {
        $ids = collect($this->orders)->pluck('i_supplier_id')->filter()->unique();

        return DB::connection('tm2mst')->table('sup_supplier')->whereIn('id_supplier', $ids)->pluck('supplier_name', 'id_supplier')->all();
    }

    #[Computed]
    public function rows()
    {
        $orders = $this->orders;
        $suppliers = $this->supplierNames;

        return $this->pendingQueue
            ->map(fn ($p) => (object) ['pending' => $p, 'po' => $orders[$p->doc_id] ?? null])
            ->filter(fn ($r) => $r->po !== null)
            ->when($this->search !== '', function ($rows) use ($suppliers) {
                $term = mb_strtolower($this->search);

                return $rows->filter(fn ($r) => str_contains(mb_strtolower($r->po->doc_no ?? ''), $term)
                    || str_contains(mb_strtolower($suppliers[$r->po->i_supplier_id] ?? ''), $term));
            })
            ->values();
    }

    #[Computed]
    public function statTotal(): int
    {
        return $this->pendingQueue->count();
    }

    #[Computed]
    public function statWaiting(): int
    {
        return $this->pendingQueue->where('action', 'SUBMITTED')->count();
    }

    #[Computed]
    public function statHold(): int
    {
        return $this->pendingQueue->where('action', 'HOLD')->count();
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
            <x-grid.stat-box tone="total" :label="t('Total in My Queue')" :value="$this->statTotal" />
            <x-grid.stat-box tone="warn" :label="t('Awaiting Decision')" :value="$this->statWaiting" />
            <x-grid.stat-box tone="extra" :label="t('On Hold (Hold)')" :value="$this->statHold" />
        </x-grid.stat-row>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ t('Search PO no. / supplier…') }}"
                        class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
            </x-grid.toolbar>

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('Supplier / PO No.') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[130px]">{{ t('Submitted') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[150px]">{{ t('Approval Position') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[140px]">{{ t('Value') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">{{ t('Status') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[60px]">{{ t('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->rows as $r)
                            <tr wire:key="poq-{{ $r->po->po_id }}" wire:dblclick="openPo({{ $r->po->po_id }})" class="hover:bg-[#f7faff] cursor-pointer" title="{{ t('Double-click to open') }}">
                                <x-grid.identity-cell :id="$r->po->po_id" :name="$this->supplierNames[$r->po->i_supplier_id] ?? '—'" :code="$r->po->doc_no" />
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $r->pending->acted_at?->translatedFormat('d M Y') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-[10.5px]">{{ $r->pending->pending_label ?? '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right font-semibold">{{ number_format((float) $r->pending->amount_snapshot, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $r->pending->action === 'HOLD' ? 'bg-[#f1ecfc] text-[#6d28d9]' : 'bg-[#fff5e0] text-[#8a5a00]' }}">{{ $r->pending->action === 'HOLD' ? t('ON HOLD') : mb_strtoupper(t('Waiting')) }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-up-right-from-square" title="{{ t('Open') }}" action="openPo({{ $r->po->po_id }})" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="6" message="{{ t('No POs awaiting your approval right now.') }}" />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
    </div>
</div>
