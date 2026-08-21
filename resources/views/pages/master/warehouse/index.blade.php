<?php

use App\Models\Master\MWarehouse;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Master Gudang — TELASIH MAKMUR ERP')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $typeFilter = '';

    // grid_performance (GLOBAL_RULES.yaml): server-side pagination + WHERE.
    #[Computed]
    public function warehouses()
    {
        return MWarehouse::withCount('locations')
            ->when($this->search !== '', function ($q) {
                $term = $this->search;
                $q->where(function ($qq) use ($term) {
                    $qq->where('warehouse_code', 'like', "%{$term}%")
                        ->orWhere('warehouse_name', 'like', "%{$term}%");
                });
            })
            ->when($this->typeFilter !== '', fn ($q) => $q->where('warehouse_type', $this->typeFilter))
            ->orderBy('warehouse_code')
            ->paginate(20);
    }

    // KPI struktural saja (jumlah gudang/lokasi) -- BUKAN stok, karena
    // modul Inventory (sumber ledger stok asli) belum dibangun. Lihat
    // catatan deviasi di migration 2026_08_21_000006.
    #[Computed]
    public function stats(): array
    {
        return [
            'total' => MWarehouse::count(),
            'raw' => MWarehouse::where('warehouse_type', 'RAW')->count(),
            'fg' => MWarehouse::where('warehouse_type', 'FG')->count(),
            'locations' => \App\Models\Master\MWhLocation::count(),
        ];
    }
};
?>
<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">🏬 Master Gudang</b>
        <span class="text-xs text-white/75">Lookup Data » Master » m_warehouse</span>
        <a href="{{ route('master.warehouse.create') }}" class="ml-auto bg-accent text-white text-xs px-3.5 py-1.5 rounded-md no-underline hover:brightness-110 font-semibold">＋ Gudang Baru</a>
        <a href="{{ route('menu') }}" class="bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md no-underline hover:brightness-110">← Menu</a>
    </header>

    <div class="max-w-[1280px] mx-auto my-5 px-4">

        <div class="bg-amber-50 border border-amber-300 text-amber-800 text-[11px] px-3.5 py-2 rounded-md mb-4">
            ⓘ Stok on-hand tidak ditampilkan di sini — itu posisi transaksional dari modul Inventory (belum dibangun), bukan master data. Layar ini murni struktur gudang & lokasi/bin.
        </div>

        {{-- STATS --}}
        <div class="grid grid-cols-4 gap-3 mb-4">
            <div class="bg-white rounded-lg p-3 border border-[#d7dbe2] border-l-4 border-l-accent">
                <p class="text-[10px] font-bold text-muted uppercase">Total Gudang</p>
                <p class="text-xl font-extrabold text-navy">{{ number_format($this->stats['total']) }}</p>
            </div>
            <div class="bg-white rounded-lg p-3 border border-[#d7dbe2] border-l-4 border-l-[#0d47a1]">
                <p class="text-[10px] font-bold text-muted uppercase">Gudang Bahan Baku</p>
                <p class="text-xl font-extrabold text-navy">{{ number_format($this->stats['raw']) }}</p>
                <p class="text-[10px] text-muted">RAW</p>
            </div>
            <div class="bg-white rounded-lg p-3 border border-[#d7dbe2] border-l-4 border-l-[#1b5e20]">
                <p class="text-[10px] font-bold text-muted uppercase">Gudang Barang Jadi</p>
                <p class="text-xl font-extrabold text-navy">{{ number_format($this->stats['fg']) }}</p>
                <p class="text-[10px] text-muted">FG</p>
            </div>
            <div class="bg-white rounded-lg p-3 border border-[#d7dbe2] border-l-4 border-l-[#f57f17]">
                <p class="text-[10px] font-bold text-muted uppercase">Total Lokasi / Bin</p>
                <p class="text-xl font-extrabold text-navy">{{ number_format($this->stats['locations']) }}</p>
            </div>
        </div>

        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 overflow-hidden">
            <div class="flex items-center gap-2 px-4 py-2.5 bg-[#f4f6fa] border-b border-[#d7dbe2] flex-wrap">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="🔍 Cari kode / nama gudang..." class="w-72 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                <select wire:model.live="typeFilter" class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                    <option value="">Semua Tipe</option>
                    <option value="RAW">Bahan Baku</option>
                    <option value="FG">Barang Jadi</option>
                    <option value="MIX">Mix</option>
                    <option value="SCRAP">Scrap</option>
                </select>
            </div>

            <table class="w-full text-xs border-collapse">
                <thead>
                    <tr class="bg-[#f4f5f8]">
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Kode Gudang</th>
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Nama Gudang</th>
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Tipe</th>
                        <th class="text-right px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Jumlah Lokasi</th>
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Status</th>
                        <th class="px-3 py-2 border-b border-[#d7dbe2]"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->warehouses as $wh)
                        <tr class="border-b border-[#eef1f5] hover:bg-[#fbfcfe] cursor-pointer" wire:key="wh-{{ $wh->id_warehouse }}" onclick="window.location='{{ route('master.warehouse.edit', ['warehouse' => $wh->id_warehouse]) }}'">
                            <td class="px-3 py-2 font-mono font-bold text-accent">{{ $wh->warehouse_code }}</td>
                            <td class="px-3 py-2">{{ $wh->warehouse_name }}</td>
                            <td class="px-3 py-2">
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded
                                    {{ match ($wh->warehouse_type) {
                                        'RAW' => 'bg-[#e3f2fd] text-[#0d47a1]',
                                        'FG' => 'bg-[#e8f5e9] text-[#1b5e20]',
                                        'MIX' => 'bg-[#fff8e1] text-[#f57f17]',
                                        'SCRAP' => 'bg-[#ffebee] text-[#b71c1c]',
                                        default => 'bg-[#eef1f5] text-[#4b5568]',
                                    } }}">{{ match($wh->warehouse_type) { 'RAW' => 'Bahan Baku', 'FG' => 'Barang Jadi', 'MIX' => 'Mix', 'SCRAP' => 'Scrap', default => $wh->warehouse_type } }}</span>
                            </td>
                            <td class="px-3 py-2 text-right font-semibold">{{ $wh->locations_count }}</td>
                            <td class="px-3 py-2">
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $wh->is_active ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#fbeaea] text-[#a3241f]' }}">{{ $wh->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            </td>
                            <td class="px-3 py-2 text-right text-accent font-semibold">Buka →</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-8 text-muted">Belum ada gudang.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="px-4 py-3 border-t border-[#eef1f5]">
                {{ $this->warehouses->links() }}
            </div>
        </div>
    </div>
</div>
