<?php

use App\Models\Pur\PrRequisition;
use App\Services\Flow\FlowEngineService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// griduistandard.md diikuti (2026-08-22). Approve/Reject/Hold TIDAK LAGI di
// layar ini -- dipindah ke form PR yang sama (pur/pr/entry.blade.php), persis
// pola ME29N di SAP / action pane Dynamics 365 / approval di form Odoo:
// approver melihat & memutuskan langsung di dokumen aslinya, bukan ringkasan
// terpisah. Layar ini murni ANTRIAN -- grid, klik 2x buka dokumen aslinya.
new #[Title('PR Approval Center — TELASIH MAKMUR ERP')] class extends Component
{
    public string $search = '';

    #[Computed]
    public function pendingQueue()
    {
        return app(FlowEngineService::class)->pendingForUser('PR', Auth::id());
    }

    // FlowLog (tm2mst) tak bisa di-join fisik ke pr_requisition (tm2pur) --
    // beda DB (CLAUDE.md §2). Ambil dokumennya lewat query terpisah, cocokkan
    // di PHP (pola sama dgn seluruh relasi lintas-modul di codebase ini).
    #[Computed]
    public function requisitions(): array
    {
        $ids = $this->pendingQueue->pluck('doc_id')->unique();

        return PrRequisition::with('department')->whereIn('pr_id', $ids)->get()->keyBy('pr_id')->all();
    }

    #[Computed]
    public function rows()
    {
        $reqs = $this->requisitions;

        return $this->pendingQueue
            ->map(fn ($p) => (object) ['pending' => $p, 'pr' => $reqs[$p->doc_id] ?? null])
            ->filter(fn ($r) => $r->pr !== null)
            ->when($this->search !== '', function ($rows) {
                $term = mb_strtolower($this->search);

                return $rows->filter(fn ($r) => str_contains(mb_strtolower($r->pr->doc_no ?? ''), $term)
                    || str_contains(mb_strtolower($r->pr->department?->department_name ?? ''), $term));
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

    public function openPr(int $id)
    {
        return $this->redirect(route('pur.pr.entry', ['pr' => $id]));
    }

    // Componentization grid (2026-08-27): avatarInitials()/avatarGradient()
    // dipindah ke App\Support\Grid\Avatar (dipakai <x-grid.identity-cell>).
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">
        <x-grid.stat-row :count="3">
            <x-grid.stat-box tone="total" label="Total di Antrian Saya" :value="$this->statTotal" />
            <x-grid.stat-box tone="warn" label="Menunggu Keputusan" :value="$this->statWaiting" />
            <x-grid.stat-box tone="extra" label="Ditahan (Hold)" :value="$this->statHold" />
        </x-grid.stat-row>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari no. PR / departemen…"
                        class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
            </x-grid.toolbar>

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Departemen / No. PR</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[130px]">Diajukan</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[150px]">Posisi Approval</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[140px]">Nilai</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">Status</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[60px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->rows as $r)
                            <tr wire:key="prq-{{ $r->pr->pr_id }}" wire:dblclick="openPr({{ $r->pr->pr_id }})" class="hover:bg-[#f7faff] cursor-pointer" title="Klik 2x untuk buka">
                                <x-grid.identity-cell :id="$r->pr->pr_id" :name="$r->pr->department?->department_name ?? '—'" :code="$r->pr->doc_no" />
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $r->pending->acted_at?->translatedFormat('d M Y') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-[10.5px]">{{ $r->pending->pending_label ?? '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right font-semibold">{{ number_format((float) $r->pending->amount_snapshot, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $r->pending->action === 'HOLD' ? 'bg-[#f1ecfc] text-[#6d28d9]' : 'bg-[#fff5e0] text-[#8a5a00]' }}">{{ $r->pending->action === 'HOLD' ? 'DITAHAN' : 'MENUNGGU' }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-up-right-from-square" title="Buka" action="openPr({{ $r->pr->pr_id }})" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="6" message="Tidak ada PR menunggu approval Anda saat ini." />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
    </div>
</div>
