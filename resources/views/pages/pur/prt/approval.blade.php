<?php

use App\Models\Pur\PrtReturn;
use App\Services\Flow\FlowEngineService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// griduistandard.md diikuti. Approve/Reject/Minta Info (Hold) TIDAK di sini
// -- dipindah ke form PRT yang sama (pur/prt/entry.blade.php), pola sama PR/PO/
// Adjustment. Layar ini murni ANTRIAN -- grid, klik 2x buka dokumen aslinya.
new #[Title('Purchase Return Approval — TELASIH MAKMUR ERP')] class extends Component
{
    public string $search = '';

    #[Computed]
    public function pendingQueue()
    {
        return app(FlowEngineService::class)->pendingForUser('PRT', Auth::id());
    }

    #[Computed]
    public function returns(): array
    {
        $ids = $this->pendingQueue->pluck('doc_id')->unique();

        return PrtReturn::whereIn('prt_id', $ids)->get()->keyBy('prt_id')->all();
    }

    #[Computed]
    public function supplierNames(): array
    {
        $ids = collect($this->returns)->pluck('i_supplier_id')->filter()->unique();

        return DB::connection('tm2mst')->table('sup_supplier')->whereIn('id_supplier', $ids)->pluck('supplier_name', 'id_supplier')->all();
    }

    #[Computed]
    public function rows()
    {
        $returns = $this->returns;
        $suppliers = $this->supplierNames;

        return $this->pendingQueue
            ->map(fn ($p) => (object) ['pending' => $p, 'prt' => $returns[$p->doc_id] ?? null])
            ->filter(fn ($r) => $r->prt !== null)
            ->when($this->search !== '', function ($rows) use ($suppliers) {
                $term = mb_strtolower($this->search);

                return $rows->filter(fn ($r) => str_contains(mb_strtolower($r->prt->doc_no ?? ''), $term)
                    || str_contains(mb_strtolower($suppliers[$r->prt->i_supplier_id] ?? ''), $term));
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

    public function openReturn(int $id)
    {
        return $this->redirect(route('pur.prt.entry', ['prt' => $id]));
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
            <x-grid.stat-box tone="extra" :label="t('Request Info (Hold)')" :value="$this->statHold" />
        </x-grid.stat-row>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ t('Search return no. / supplier…') }}"
                        class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
            </x-grid.toolbar>

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('Supplier / Return No.') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[130px]">{{ t('Submitted') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[150px]">{{ t('Approval Position') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[140px]">{{ t('Value') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">{{ t('Status') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[60px]">{{ t('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->rows as $r)
                            <tr wire:key="prtq-{{ $r->prt->prt_id }}" wire:dblclick="openReturn({{ $r->prt->prt_id }})" class="hover:bg-[#f7faff] cursor-pointer" title="{{ t('Double-click to open') }}">
                                <x-grid.identity-cell :id="$r->prt->prt_id" :name="$this->supplierNames[$r->prt->i_supplier_id] ?? '—'" :code="$r->prt->doc_no" />
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $r->pending->acted_at?->translatedFormat('d M Y') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-[10.5px]">{{ $r->pending->pending_label ?? '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right font-semibold">{{ number_format((float) $r->pending->amount_snapshot, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $r->pending->action === 'HOLD' ? 'bg-[#f1ecfc] text-[#6d28d9]' : 'bg-[#fff5e0] text-[#8a5a00]' }}">{{ $r->pending->action === 'HOLD' ? mb_strtoupper(t('Request Info')) : mb_strtoupper(t('Waiting')) }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-up-right-from-square" title="{{ t('Open') }}" action="openReturn({{ $r->prt->prt_id }})" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="6" message="{{ t('No returns awaiting your approval right now.') }}" />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
    </div>
</div>
