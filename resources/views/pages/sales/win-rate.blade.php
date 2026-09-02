<?php

use App\Models\Sdi\IqpInquiry;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// docs/BACKLOG.md §3 "Win-rate Sales" -- iqp_inquiry.sub_state TERNYATA sudah
// punya value CONVERTED/LOST sejak skema awal, cuma tak ada 1 pun titik kode
// yang menuliskannya (lihat CoOrderBuilder::findOrCreateFromQuotation() utk
// CONVERTED, inquiry/form.blade.php::markLost() utk LOST -- keduanya baru
// ditambahkan 2026-09-02). Layar ini murni REPORT (read-only), agregasi dari
// dua titik tulis itu.
new #[Title('Win-Rate Sales — TELASIH MAKMUR ERP')] class extends Component
{
    public string $dateFrom = '';

    public string $dateTo = '';

    #[Computed]
    public function terminalInquiries()
    {
        return IqpInquiry::whereIn('sub_state', ['CONVERTED', 'LOST'])
            ->when($this->dateFrom !== '', fn ($q) => $q->whereDate('inquiry_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($q) => $q->whereDate('inquiry_date', '<=', $this->dateTo))
            ->orderByDesc('inquiry_date')
            ->get();
    }

    #[Computed]
    public function wonCount(): int
    {
        return $this->terminalInquiries->where('sub_state', 'CONVERTED')->count();
    }

    #[Computed]
    public function lostCount(): int
    {
        return $this->terminalInquiries->where('sub_state', 'LOST')->count();
    }

    #[Computed]
    public function winRatePct(): float
    {
        $total = $this->wonCount + $this->lostCount;

        return $total > 0 ? round($this->wonCount / $total * 100, 1) : 0.0;
    }

    #[Computed]
    public function byPic()
    {
        return $this->terminalInquiries
            ->groupBy(fn ($i) => $i->pic_sales ?: '(tanpa PIC)')
            ->map(function ($rows, $pic) {
                $won = $rows->where('sub_state', 'CONVERTED')->count();
                $lost = $rows->where('sub_state', 'LOST')->count();
                $total = $won + $lost;

                return (object) [
                    'pic' => $pic,
                    'won' => $won,
                    'lost' => $lost,
                    'total' => $total,
                    'rate' => $total > 0 ? round($won / $total * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    #[Computed]
    public function lostRows()
    {
        return $this->terminalInquiries->where('sub_state', 'LOST')->sortByDesc('inquiry_date')->values();
    }

    public function resetFilter(): void
    {
        $this->dateFrom = '';
        $this->dateTo = '';
    }

    // inquiry.hub belum dukung query-param pre-select -- redirect ke Hub polos,
    // officer cari manual (inquiry_no sudah tampil di kolom identitas di sini).
    public function openInquiry(int $id)
    {
        return $this->redirect(route('inquiry.hub'));
    }
};
?>

<div>
    <div class="max-w-[1200px] mx-auto my-5 px-4">
        <x-grid.stat-row :count="3">
            <x-grid.stat-box tone="total" label="Deal Menang (CONVERTED)" :value="$this->wonCount" />
            <x-grid.stat-box tone="warn" label="Deal Kalah (LOST)" :value="$this->lostCount" />
            <x-grid.stat-box tone="extra" label="Win-Rate" :value="number_format($this->winRatePct, 1).'%'" />
        </x-grid.stat-row>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden mb-4">
            <x-grid.toolbar class="flex-wrap">
                <div class="flex items-center gap-1.5 shrink-0">
                    <label class="text-[10.5px] text-muted font-semibold">Dari</label>
                    <x-form.date-picker wire:model.live="dateFrom" class="w-[130px]" />
                    <label class="text-[10.5px] text-muted font-semibold">s/d</label>
                    <x-form.date-picker wire:model.live="dateTo" class="w-[130px]" />
                </div>
                @if ($dateFrom !== '' || $dateTo !== '')
                    <x-slot:tools>
                        <button type="button" wire:click="resetFilter" class="shrink-0 text-[11px] font-semibold text-muted hover:text-navy underline">Reset filter</button>
                    </x-slot:tools>
                @endif
            </x-grid.toolbar>

            <div class="p-4">
                <h3 class="text-xs text-navy uppercase font-bold mb-2.5">Win-Rate per PIC Sales</h3>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">PIC Sales</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] w-[100px]">Menang</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] w-[100px]">Kalah</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] w-[100px]">Total</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] w-[100px]">Win-Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->byPic as $row)
                            <tr wire:key="pic-{{ $row->pic }}">
                                <td class="px-3 py-2 border-b border-[#eef1f5] font-semibold">{{ $row->pic }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right text-[#0e8a4f] font-semibold">{{ $row->won }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right text-[#c0392b] font-semibold">{{ $row->lost }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right">{{ $row->total }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right font-bold text-navy">{{ number_format($row->rate, 1) }}%</td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="5" message="Belum ada inquiry CONVERTED/LOST pada rentang ini." />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <h3 class="text-xs text-navy uppercase font-bold px-4 py-2.5 bg-[#f4f6fa] border-b border-[#d7dbe2]">Detail Deal Kalah (LOST)</h3>
            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Inquiry</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">Tanggal</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[120px]">PIC Sales</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Alasan Kalah</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[60px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->lostRows as $inq)
                            <tr wire:key="lost-{{ $inq->id_inquiry }}" wire:dblclick="openInquiry({{ $inq->id_inquiry }})" class="hover:bg-[#f7faff] cursor-pointer" title="Klik 2x untuk buka">
                                <x-grid.identity-cell :id="$inq->id_inquiry" :name="$inq->product_draft_name" :code="$inq->inquiry_no" />
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $inq->inquiry_date?->translatedFormat('d M Y') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $inq->pic_sales ?? '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-muted">{{ $inq->cancel_reason ?? '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="↗" title="Buka" action="openInquiry({{ $inq->id_inquiry }})" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="5" message="Belum ada deal kalah pada rentang ini." />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
    </div>
</div>
