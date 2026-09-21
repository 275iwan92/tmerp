<?php

use App\Models\Sdi\IqpInquiry;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

// Worklist "Imposition Engine" (menu SALES_IMPO). Grid inquiry yang sudah
// confirmed (sub_state di luar DRAFT/CANCELLED/LOST) dan punya part -- klik 2x
// (atau ikon Aksi) buka layar imposition per-inquiry yang SUDAH ADA
// (inquiry/imposition.blade.php, tidak diubah di sini, cuma dituju).
// griduistandard.md diikuti (2026-08-22, diperluas ke grid transaksi juga
// atas instruksi user): tanpa header halaman, kotak statistik pastel, kolom
// identitas avatar+nama+kode, toolbar cuma search, ikon Aksi.
new #[Title('Imposition Engine — TELASIH MAKMUR ERP')] class extends Component
{
    #[Url]
    public string $search = '';

    public function open(int $id)
    {
        return redirect()->route('inquiry.imposition', ['inquiry' => $id]);
    }

    public function impositionProgress(IqpInquiry $inquiry): string
    {
        $total = $inquiry->parts->count();
        $done = $inquiry->parts->filter(fn ($p) => (bool) $p->layout?->stat_confirm)->count();

        return "{$done}/{$total}";
    }

    public function isImpositionDone(IqpInquiry $inquiry): bool
    {
        return $inquiry->parts->isNotEmpty() && $inquiry->parts->every(fn ($p) => (bool) $p->layout?->stat_confirm);
    }

    public function subStateBadgeClass(string $state): string
    {
        return match ($state) {
            'DRAFT' => 'bg-[#eef0f3] text-[#8a8f98]',
            'WAITING_COST', 'SUBMITTED', 'PRICE_CALCULATED' => 'bg-[#eaf2ff] text-[#1d4ed8]',
            'PRICE_APPROVED', 'CONVERTED' => 'bg-[#e9f9ef] text-[#16a34a]',
            'QUOTED' => 'bg-[#f1ecfc] text-[#6d28d9]',
            'PROVING', 'PROVE_APPROVED' => 'bg-[#fef6e7] text-[#b45309]',
            'CANCELLED', 'LOST' => 'bg-[#fdecec] text-[#c0392b]',
            default => 'bg-[#eef0f3] text-[#8a8f98]',
        };
    }

    public function typeBadgeClass(string $type): string
    {
        return match ($type) {
            'RECALC' => 'bg-[#f1ecfc] text-[#6d28d9]',
            'REVISE' => 'bg-[#fef6e7] text-[#b45309]',
            default => 'bg-[#eaf2ff] text-[#1d4ed8]', // NEW
        };
    }

    #[Computed]
    public function inquiries()
    {
        return IqpInquiry::query()
            ->with(['customer', 'parts.layout'])
            ->whereNotIn('sub_state', ['DRAFT', 'CANCELLED', 'LOST'])
            ->whereHas('parts')
            ->when($this->search !== '', function ($q) {
                $q->where(function ($qq) {
                    $qq->where('inquiry_no', 'like', "%{$this->search}%")
                        ->orWhere('product_draft_name', 'like', "%{$this->search}%");
                });
            })
            ->orderByDesc('id_inquiry')
            ->limit(200)
            ->get();
    }

    #[Computed]
    public function statTotal(): int
    {
        return $this->inquiries->count();
    }

    #[Computed]
    public function statDone(): int
    {
        return $this->inquiries->filter(fn ($i) => $this->isImpositionDone($i))->count();
    }

    #[Computed]
    public function statPending(): int
    {
        return $this->statTotal - $this->statDone;
    }

    // Componentization grid (2026-08-27): avatarInitials()/avatarGradient()
    // dipindah ke App\Support\Grid\Avatar (dipakai <x-grid.identity-cell>).
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">
        <x-grid.stat-row :count="3">
            <x-grid.stat-box tone="total" label="Total Perlu Imposisi" :value="$this->statTotal" />
            <x-grid.stat-box tone="active" label="Selesai" :value="$this->statDone" subtitle="SEMUA PART TERKONFIRMASI" />
            <x-grid.stat-box tone="warn" label="Belum Selesai" :value="$this->statPending" />
        </x-grid.stat-row>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari no. inkuiri / nama produk…"
                        class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
            </x-grid.toolbar>

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Inkuiri</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[170px]">Produk</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[90px]">Tipe</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[120px]">Status</th>
                            <th class="text-center px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">Progres Part</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[60px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->inquiries as $row)
                            <tr wire:key="imp-{{ $row->id_inquiry }}" wire:dblclick="open({{ $row->id_inquiry }})" class="hover:bg-[#f7faff] cursor-pointer" title="Klik 2x untuk buka Imposition">
                                @php($custName = $row->is_prospect ? $row->prospect_name : ($row->customer?->customer_name ?? '—'))
                                <x-grid.identity-cell :id="$row->id_inquiry" :name="$custName" :code="$row->inquiry_no ?: '—'" />
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $row->product_draft_name ?: '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $this->typeBadgeClass($row->inquiry_type) }}">{{ $row->inquiry_type }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $this->subStateBadgeClass($row->sub_state) }}">{{ $row->sub_state }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-center">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $this->isImpositionDone($row) ? 'bg-[#e9f9ef] text-[#16a34a]' : 'bg-[#eaf2ff] text-[#1d4ed8]' }}">{{ $this->impositionProgress($row) }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-up-right-from-square" title="Buka Imposition" action="open({{ $row->id_inquiry }})" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="6" message="Tidak ada inkuiri yang perlu imposisi." />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
    </div>
</div>
