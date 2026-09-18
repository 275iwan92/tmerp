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
// griduistandard.md diikuti (2026-08-22, diperluas ke grid transaksi juga
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

    // Componentization grid (2026-08-27): avatarInitials()/avatarGradient()
    // dipindah ke App\Support\Grid\Avatar (dipakai <x-grid.identity-cell>).
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">
        <div class="text-xs px-3.5 py-2.5 rounded-md mb-3.5 leading-relaxed bg-[#fef6e7] border border-[#f5e0b3] text-[#8a4b00]">
            🔒 Layar ini khusus role Costing/Direksi — angka HPP &amp; margin tidak boleh bocor ke Sisi B (Sales) sebelum <b>doc_status = PUBLISHED</b> (approve Direksi).
        </div>

        <x-grid.stat-row :count="4">
            <x-grid.stat-box tone="total" label="Total Perlu Costing" :value="$this->statTotal" />
            <x-grid.stat-box tone="warn" label="Belum Dimulai" :value="$this->statNotStarted" />
            <x-grid.stat-box tone="extra" label="Menunggu Approval Direksi" :value="$this->statPendingApproval" />
            <x-grid.stat-box tone="active" label="Published" :value="$this->statPublished" subtitle="DEKLASIFIKASI KE SALES" />
        </x-grid.stat-row>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari no. inkuiri / nama produk…"
                        class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <x-slot:tools>
                    <div class="flex items-center gap-1 pr-2.5 mr-1 border-r border-[#e2e7ee] shrink-0">
                        <a href="{{ route('inquiry.costing.list') }}" wire:navigate title="Costing yang terhubung ke Inquiry Product"
                            class="px-2.5 py-1.5 rounded-md text-[11px] font-semibold bg-[#2c3e69] text-white">🔗 Integrated</a>
                        <a href="{{ route('inquiry.costing.instant.list') }}" wire:navigate title="Kalkulator HPP standalone, tanpa Inquiry"
                            class="px-2.5 py-1.5 rounded-md text-[11px] font-semibold bg-white border border-[#d7dbe2]">🧮 Instant</a>
                    </div>
                </x-slot:tools>
            </x-grid.toolbar>

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Inkuiri</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[170px]">Produk</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[90px]">Tipe</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[120px]">Status Inquiry</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[140px]">Status Costing</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[60px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->inquiries as $row)
                            <tr wire:key="cost-{{ $row->id_inquiry }}" wire:dblclick="open({{ $row->id_inquiry }})" class="hover:bg-[#f7faff] cursor-pointer" title="Klik 2x untuk buka Costing">
                                @php($custName = $row->is_prospect ? $row->prospect_name : ($row->customer?->customer_name ?? '—'))
                                <x-grid.identity-cell :id="$row->id_inquiry" :name="$custName" :code="$row->inquiry_no ?: '—'" />
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $row->product_draft_name ?: '—' }}</td>
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
                                    <x-grid.action-icon icon="↗" title="Buka Costing" action="open({{ $row->id_inquiry }})" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="6" message="Tidak ada inkuiri yang perlu costing." />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
    </div>
</div>
