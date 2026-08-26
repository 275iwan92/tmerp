<?php

use App\Models\Pur\PrRequisition;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Outstanding PR = PR yang sudah APPROVED tapi baris-nya belum (sepenuhnya)
// jadi PO -- i_qty > c_qty_ordered (docs/procurement_tier3.md view v1_pr_outstanding).
// pr_requisition.status HEADER tidak pernah otomatis naik ke FULLY_ORDERED/
// CLOSED saat semua baris FULLY_ORDERED (gap lama, lihat PoBuilderService::
// addAllocation -- cuma pr_line.c_line_status yg di-update) -- makanya
// "outstanding" DIHITUNG dari qty baris, bukan cuma dipercaya dari status
// header, supaya PR yang sebenarnya sudah tuntas tidak ikut nyangkut di sini.
new #[Title('Outstanding PR — TELASIH MAKMUR ERP')] class extends Component
{
    public string $search = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    #[Computed]
    public function rows()
    {
        $prs = PrRequisition::with(['department', 'lines'])
            ->whereIn('status', ['APPROVED', 'PARTIALLY_ORDERED'])
            ->where('is_cancelled', false)
            ->when($this->dateFrom !== '', fn ($q) => $q->whereDate('i_doc_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($q) => $q->whereDate('i_doc_date', '<=', $this->dateTo))
            ->orderBy('i_doc_date')
            ->get();

        return $prs
            ->map(function ($pr) {
                $outstandingLines = $pr->lines->filter(fn ($l) => bcsub((string) $l->i_qty, (string) ($l->c_qty_ordered ?? 0), 4) > 0);

                return (object) [
                    'pr' => $pr,
                    'lineCount' => $outstandingLines->count(),
                    'value' => $outstandingLines->sum(fn ($l) => (float) bcsub((string) $l->i_qty, (string) ($l->c_qty_ordered ?? 0), 4) * (float) ($l->i_est_price ?? 0)),
                ];
            })
            ->filter(fn ($r) => $r->lineCount > 0)
            ->when($this->search !== '', function ($rows) {
                $term = mb_strtolower($this->search);

                return $rows->filter(fn ($r) => str_contains(mb_strtolower($r->pr->doc_no ?? ''), $term)
                    || str_contains(mb_strtolower($r->pr->department?->department_name ?? ''), $term));
            })
            ->sortBy(fn ($r) => $r->pr->i_doc_date)
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
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 mb-3.5">
            <div class="rounded-lg p-3.5 shadow-sm bg-[#eaf2ff] border border-[#c8ddfb]">
                <p class="text-[10.5px] font-bold uppercase text-[#3b5b99]">PR Outstanding</p>
                <p class="text-xl font-extrabold text-[#1d4ed8] mt-0.5">{{ $this->statCount }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#f1ecfc] border border-[#ddd0f7]">
                <p class="text-[10.5px] font-bold uppercase text-[#6b4fa0]">Baris Outstanding</p>
                <p class="text-xl font-extrabold text-[#6d28d9] mt-0.5">{{ $this->statLines }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#fef6e7] border border-[#f5e0b3]">
                <p class="text-[10.5px] font-bold uppercase text-[#8a6d00]">Nilai Outstanding (Est.)</p>
                <p class="text-xl font-extrabold text-[#b45309] mt-0.5">{{ number_format($this->statValue, 0, ',', '.') }}</p>
            </div>
        </div>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <div class="flex items-center gap-2 bg-white border-b border-[#e2e7ee] px-2.5 py-2 overflow-x-auto flex-wrap">
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari no. PR / departemen…"
                        class="w-56 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <div class="flex items-center gap-1.5 shrink-0">
                    <label class="text-[10.5px] text-muted font-semibold">Dari</label>
                    <input type="date" wire:model.live="dateFrom" class="px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                    <label class="text-[10.5px] text-muted font-semibold">s/d</label>
                    <input type="date" wire:model.live="dateTo" class="px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                @if ($search !== '' || $dateFrom !== '' || $dateTo !== '')
                    <button type="button" wire:click="resetFilter" class="shrink-0 text-[11px] font-semibold text-muted hover:text-navy underline">Reset filter</button>
                @endif
            </div>

            <div class="max-h-[65vh] overflow-auto">
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Departemen / No. PR</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">Tanggal PR</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">Dibutuhkan</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[130px]">Baris Outstanding</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[140px]">Nilai Outstanding</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">Status</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[60px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->rows as $r)
                            <tr wire:key="opr-{{ $r->pr->pr_id }}" wire:dblclick="openPr({{ $r->pr->pr_id }})" class="hover:bg-[#f7faff] cursor-pointer" title="Klik 2x untuk buka">
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-md bg-gradient-to-br {{ $this->avatarGradient($r->pr->pr_id) }} text-white text-[11px] font-bold flex items-center justify-center shrink-0">{{ $this->avatarInitials($r->pr->department?->department_name ?: $r->pr->doc_no ?: 'PR') }}</div>
                                        <div>
                                            <div class="font-semibold text-[13px]">{{ $r->pr->department?->department_name ?? '—' }}</div>
                                            <div class="text-[11px] text-muted font-mono">{{ $r->pr->doc_no }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $r->pr->i_doc_date?->translatedFormat('d M Y') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $r->pr->i_need_date?->translatedFormat('d M Y') ?? '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right">{{ $r->lineCount }} / {{ $r->pr->lines->count() }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right font-semibold">{{ number_format($r->value, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $r->pr->status === 'PARTIALLY_ORDERED' ? 'bg-[#fff5e0] text-[#8a5a00]' : 'bg-[#eef1f5] text-[#4b5568]' }}">{{ $r->pr->status }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <button type="button" wire:click.stop="openPr({{ $r->pr->pr_id }})" title="Buka" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">↗</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center py-8 text-muted text-sm">Tidak ada PR outstanding pada rentang/filter ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
