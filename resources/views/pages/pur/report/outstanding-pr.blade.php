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
            ->where('stat_cancel', false)
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

    // Componentization grid (2026-08-27): avatarInitials()/avatarGradient()
    // dipindah ke App\Support\Grid\Avatar (dipakai <x-grid.identity-cell>).
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">
        <x-grid.stat-row :count="3">
            <x-grid.stat-box tone="total" :label="t('Outstanding PRs')" :value="$this->statCount" />
            <x-grid.stat-box tone="extra" :label="t('Outstanding Lines')" :value="$this->statLines" />
            <x-grid.stat-box tone="warn" :label="t('Outstanding Value (Est.)')" :value="number_format($this->statValue, 0, ',', '.')" />
        </x-grid.stat-row>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar class="flex-wrap">
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ t('Search PR no. / department…') }}"
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
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('Department / PR No.') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">{{ t('PR Date') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">{{ t('Needed') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[130px]">{{ t('Outstanding Lines') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[140px]">{{ t('Outstanding Value') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">{{ t('Status') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[60px]">{{ t('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->rows as $r)
                            <tr wire:key="opr-{{ $r->pr->pr_id }}" wire:dblclick="openPr({{ $r->pr->pr_id }})" class="hover:bg-[#f7faff] cursor-pointer" title="{{ t('Double-click to open') }}">
                                <x-grid.identity-cell :id="$r->pr->pr_id" :name="$r->pr->department?->department_name ?? '—'" :code="$r->pr->doc_no" />
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $r->pr->i_doc_date?->translatedFormat('d M Y') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $r->pr->i_need_date?->translatedFormat('d M Y') ?? '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right">{{ $r->lineCount }} / {{ $r->pr->lines->count() }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right font-semibold">{{ number_format($r->value, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $r->pr->status === 'PARTIALLY_ORDERED' ? 'bg-[#fff5e0] text-[#8a5a00]' : 'bg-[#eef1f5] text-[#4b5568]' }}">{{ $r->pr->status }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-up-right-from-square" title="{{ t('Open') }}" action="openPr({{ $r->pr->pr_id }})" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="7" message="{{ t('No outstanding PRs in this range/filter.') }}" />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
    </div>
</div>
