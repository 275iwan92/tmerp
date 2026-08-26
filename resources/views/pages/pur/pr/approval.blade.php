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

    // griduistandard.md #3 -- avatar inisial+warna utk kolom identitas.
    public function avatarInitials(string $name): string
    {
        $words = array_values(array_filter(explode(' ', trim($name))));
        if (count($words) >= 2) {
            return mb_strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1));
        }

        return mb_strtoupper(mb_substr($name, 0, 2));
    }

    private const AVATAR_PALETTE = [
        'from-[#2563eb] to-[#1d4ed8]', 'from-[#10b981] to-[#059669]', 'from-[#ef4444] to-[#dc2626]',
        'from-[#8b5cf6] to-[#6d28d9]', 'from-[#f59e0b] to-[#d97706]', 'from-[#0ea5e9] to-[#0284c7]',
    ];

    public function avatarGradient(int $id): string
    {
        return self::AVATAR_PALETTE[$id % count(self::AVATAR_PALETTE)];
    }
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">
        <div class="grid three mb-3.5">
            <div class="rounded-lg p-3.5 shadow-sm bg-[#eaf2ff] border border-[#c8ddfb]">
                <p class="text-[10.5px] font-bold uppercase text-[#3b5b99]">Total di Antrian Saya</p>
                <p class="text-xl font-extrabold text-[#1d4ed8] mt-0.5">{{ $this->statTotal }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#fef6e7] border border-[#f5e0b3]">
                <p class="text-[10.5px] font-bold uppercase text-[#8a6d00]">Menunggu Keputusan</p>
                <p class="text-xl font-extrabold text-[#b45309] mt-0.5">{{ $this->statWaiting }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#f1ecfc] border border-[#ddd0f7]">
                <p class="text-[10.5px] font-bold uppercase text-[#6b4fa0]">Ditahan (Hold)</p>
                <p class="text-xl font-extrabold text-[#6d28d9] mt-0.5">{{ $this->statHold }}</p>
            </div>
        </div>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <div class="flex items-center gap-2 bg-white border-b border-[#e2e7ee] px-2.5 py-2 overflow-x-auto">
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari no. PR / departemen…"
                        class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
            </div>

            <div class="max-h-[65vh] overflow-auto">
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
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-md bg-gradient-to-br {{ $this->avatarGradient($r->pr->pr_id) }} text-white text-[11px] font-bold flex items-center justify-center shrink-0">{{ $this->avatarInitials($r->pr->department?->department_name ?: $r->pr->doc_no ?: 'PR') }}</div>
                                        <div>
                                            <div class="font-semibold text-[13px]">{{ $r->pr->department?->department_name ?? '—' }}</div>
                                            <div class="text-[11px] text-muted font-mono">{{ $r->pr->doc_no }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $r->pending->acted_at?->translatedFormat('d M Y') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-[10.5px]">{{ $r->pending->pending_label ?? '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right font-semibold">{{ number_format((float) $r->pending->amount_snapshot, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $r->pending->action === 'HOLD' ? 'bg-[#f1ecfc] text-[#6d28d9]' : 'bg-[#fff5e0] text-[#8a5a00]' }}">{{ $r->pending->action === 'HOLD' ? 'DITAHAN' : 'MENUNGGU' }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <button type="button" wire:click.stop="openPr({{ $r->pr->pr_id }})" title="Buka" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">↗</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-8 text-muted text-sm">Tidak ada PR menunggu approval Anda saat ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
