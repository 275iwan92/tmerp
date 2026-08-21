<?php

use App\Models\Sdi\IqpInquiry;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

// Worklist "Product Costing" (menu SALES_COST, digate module:COSTING -- beda
// dari layar Sales lain di modul ini, CLAUDE.md #4 Sisi A rahasia). Grid
// inquiry confirmed & punya part, klik 2x (atau ikon Aksi) buka layar costing
// per-inquiry yang SUDAH ADA (inquiry/costing.blade.php, tidak diubah).
// GRID_UI_STANDARD.md diikuti (2026-08-22, diperluas ke grid transaksi juga
// atas instruksi user): tanpa header halaman, kotak statistik pastel, kolom
// identitas avatar+nama+kode, toolbar cuma search, ikon Aksi.
new #[Title('Product Costing — TELASIH MAKMUR ERP')] class extends Component
{
    #[Url]
    public string $search = '';

    public function open(int $id)
    {
        return redirect()->route('inquiry.costing', ['inquiry' => $id]);
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

    public function docStatusBadgeClass(?string $status): string
    {
        return match ($status) {
            'CALCULATED' => 'bg-[#eaf2ff] text-[#1d4ed8]',
            'PENDING_APPROVAL' => 'bg-[#fef6e7] text-[#b45309]',
            'PUBLISHED' => 'bg-[#e9f9ef] text-[#16a34a]',
            'FAILED' => 'bg-[#fdecec] text-[#c0392b]',
            default => 'bg-[#eef0f3] text-[#8a8f98]', // null / DRAFT = belum dimulai
        };
    }

    #[Computed]
    public function inquiries()
    {
        return IqpInquiry::query()
            ->with(['customer', 'costing'])
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
    public function statNotStarted(): int
    {
        return $this->inquiries->whereNull('costing')->count();
    }

    #[Computed]
    public function statPendingApproval(): int
    {
        return $this->inquiries->filter(fn ($i) => $i->costing?->doc_status === 'PENDING_APPROVAL')->count();
    }

    #[Computed]
    public function statPublished(): int
    {
        return $this->inquiries->filter(fn ($i) => $i->costing?->doc_status === 'PUBLISHED')->count();
    }

    // GRID_UI_STANDARD.md #3 -- avatar inisial+warna utk kolom identitas.
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
        <div class="text-xs px-3.5 py-2.5 rounded-md mb-3.5 leading-relaxed bg-[#fef6e7] border border-[#f5e0b3] text-[#8a4b00]">
            🔒 Layar ini khusus role Costing/Direksi — angka HPP &amp; margin tidak boleh bocor ke Sisi B (Sales) sebelum <b>doc_status = PUBLISHED</b> (approve Direksi).
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-3.5">
            <div class="rounded-lg p-3.5 shadow-sm bg-[#eaf2ff] border border-[#c8ddfb]">
                <p class="text-[10.5px] font-bold uppercase text-[#3b5b99]">Total Perlu Costing</p>
                <p class="text-xl font-extrabold text-[#1d4ed8] mt-0.5">{{ $this->statTotal }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#fef6e7] border border-[#f5e0b3]">
                <p class="text-[10.5px] font-bold uppercase text-[#8a6d00]">Belum Dimulai</p>
                <p class="text-xl font-extrabold text-[#b45309] mt-0.5">{{ $this->statNotStarted }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#f1ecfc] border border-[#ddd0f7]">
                <p class="text-[10.5px] font-bold uppercase text-[#6b4fa0]">Menunggu Approval Direksi</p>
                <p class="text-xl font-extrabold text-[#6d28d9] mt-0.5">{{ $this->statPendingApproval }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#e9f9ef] border border-[#c8ecd6]">
                <p class="text-[10.5px] font-bold uppercase text-[#2f7a52]">Published</p>
                <p class="text-xl font-extrabold text-[#16a34a] mt-0.5">{{ $this->statPublished }}</p>
                <p class="text-[10px] text-[#2f7a52]/70">DEKLASIFIKASI KE SALES</p>
            </div>
        </div>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <div class="flex items-center gap-2 bg-white border-b border-[#e2e7ee] px-2.5 py-2 overflow-x-auto">
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari no. inkuiri / nama produk…"
                        class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
            </div>

            <div class="max-h-[65vh] overflow-auto">
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Inkuiri</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[170px]">Customer</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[90px]">Tipe</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[120px]">Status Inquiry</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[140px]">Status Costing</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[60px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->inquiries as $row)
                            <tr wire:key="cost-{{ $row->id_inquiry }}" wire:dblclick="open({{ $row->id_inquiry }})" class="hover:bg-[#f7faff] cursor-pointer" title="Klik 2x untuk buka Costing">
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-md bg-gradient-to-br {{ $this->avatarGradient($row->id_inquiry) }} text-white text-[11px] font-bold flex items-center justify-center shrink-0">{{ $this->avatarInitials($row->product_draft_name ?: $row->inquiry_no) }}</div>
                                        <div>
                                            <div class="font-semibold text-[13px]">{{ $row->product_draft_name ?: '—' }}</div>
                                            <div class="text-[11px] text-muted font-mono">{{ $row->inquiry_no ?: '—' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $row->is_prospect ? $row->prospect_name : ($row->customer?->customer_name ?? '—') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $this->typeBadgeClass($row->inquiry_type) }}">{{ $row->inquiry_type }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $this->subStateBadgeClass($row->sub_state) }}">{{ $row->sub_state }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $this->docStatusBadgeClass($row->costing?->doc_status) }}">{{ $row->costing?->doc_status ?? 'Belum dimulai' }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <button type="button" wire:click.stop="open({{ $row->id_inquiry }})" title="Buka Costing" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">↗</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-8 text-muted text-sm">Tidak ada inkuiri yang perlu costing.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
