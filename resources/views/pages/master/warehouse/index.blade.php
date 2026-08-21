<?php

use App\Models\Master\MWarehouse;
use Illuminate\Support\Facades\Auth;
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
            'raw' => MWarehouse::where('warehouse_type', 'RM')->count(),
            'fg' => MWarehouse::where('warehouse_type', 'FG')->count(),
            'locations' => \App\Models\Master\MWhLocation::count(),
        ];
    }

    public function toggleActive(int $warehouseId): void
    {
        $w = MWarehouse::findOrFail($warehouseId);
        $w->is_active = ! $w->is_active;
        $w->updated_by = Auth::user()->login;
        $w->save();

        unset($this->warehouses, $this->stats);
    }
};
?>
<div>
    <div class="max-w-[1280px] mx-auto my-5 px-4">

        <div class="bg-amber-50 border border-amber-300 text-amber-800 text-[11px] px-3.5 py-2 rounded-md mb-4">
            ⓘ Stok on-hand tidak ditampilkan di sini — itu posisi transaksional dari modul Inventory, bukan master data. Layar ini murni struktur gudang & lokasi/bin.
        </div>

        {{-- STATS --}}
        <div class="grid grid-cols-4 gap-3 mb-4">
            <div class="rounded-lg p-3.5 text-white shadow-sm bg-gradient-to-br from-[#2f6fed] to-[#1d4ed8]">
                <p class="text-[10.5px] font-bold uppercase opacity-85">Total Gudang</p>
                <p class="text-xl font-extrabold mt-0.5">{{ number_format($this->stats['total']) }}</p>
            </div>
            <div class="rounded-lg p-3.5 text-white shadow-sm bg-gradient-to-br from-[#22c55e] to-[#16a34a]">
                <p class="text-[10.5px] font-bold uppercase opacity-85">Gudang Bahan Baku</p>
                <p class="text-xl font-extrabold mt-0.5">{{ number_format($this->stats['raw']) }}</p>
                <p class="text-[10px] opacity-75">RM</p>
            </div>
            <div class="rounded-lg p-3.5 text-white shadow-sm bg-gradient-to-br from-[#8b5cf6] to-[#6d28d9]">
                <p class="text-[10.5px] font-bold uppercase opacity-85">Gudang Barang Jadi</p>
                <p class="text-xl font-extrabold mt-0.5">{{ number_format($this->stats['fg']) }}</p>
                <p class="text-[10px] opacity-75">FG</p>
            </div>
            <div class="rounded-lg p-3.5 text-white shadow-sm bg-gradient-to-br from-[#f59e0b] to-[#d97706]">
                <p class="text-[10.5px] font-bold uppercase opacity-85">Total Lokasi / Bin</p>
                <p class="text-xl font-extrabold mt-0.5">{{ number_format($this->stats['locations']) }}</p>
            </div>
        </div>

        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 overflow-hidden">
            <div class="flex items-center gap-2 px-4 py-2.5 bg-[#f4f6fa] border-b border-[#d7dbe2] flex-wrap">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="🔍 Cari kode / nama gudang..." class="w-72 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                <select wire:model.live="typeFilter" class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                    <option value="">Semua Tipe</option>
                    <option value="RM">Bahan Baku</option>
                    <option value="SFG">Semi Finished Goods</option>
                    <option value="FG">Barang Jadi</option>
                    <option value="MIX">Mix</option>
                    <option value="WASTE">Afkir / Waste</option>
                    <option value="TRANSIT">Transit</option>
                </select>
                <span class="flex-1"></span>
                <a href="{{ route('master.warehouse.create') }}" class="bg-accent text-white text-xs px-3.5 py-1.5 rounded-md no-underline hover:brightness-110 font-semibold">＋ Gudang Baru</a>
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
                        <tr class="border-b border-[#eef1f5] hover:bg-[#fbfcfe]" wire:key="wh-{{ $wh->id_warehouse }}">
                            <td class="px-3 py-2 font-mono font-bold text-accent">{{ $wh->warehouse_code }}</td>
                            <td class="px-3 py-2">{{ $wh->warehouse_name }}</td>
                            <td class="px-3 py-2">
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded
                                    {{ match ($wh->warehouse_type) {
                                        'RM' => 'bg-[#e3f2fd] text-[#0d47a1]',
                                        'SFG' => 'bg-[#ede7f6] text-[#4527a0]',
                                        'FG' => 'bg-[#e8f5e9] text-[#1b5e20]',
                                        'MIX' => 'bg-[#fff8e1] text-[#f57f17]',
                                        'WASTE' => 'bg-[#ffebee] text-[#b71c1c]',
                                        'TRANSIT' => 'bg-[#e0f2f1] text-[#00695c]',
                                        default => 'bg-[#eef1f5] text-[#4b5568]',
                                    } }}">{{ match($wh->warehouse_type) { 'RM' => 'Bahan Baku', 'SFG' => 'Semi Finished Goods', 'FG' => 'Barang Jadi', 'MIX' => 'Mix', 'WASTE' => 'Afkir/Waste', 'TRANSIT' => 'Transit', default => $wh->warehouse_type } }}</span>
                            </td>
                            <td class="px-3 py-2 text-right font-semibold">{{ $wh->locations_count }}</td>
                            <td class="px-3 py-2">
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $wh->is_active ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#fbeaea] text-[#a3241f]' }}">{{ $wh->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            </td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                <a href="{{ route('master.warehouse.edit', ['warehouse' => $wh->id_warehouse]) }}" title="Edit" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer no-underline">✎</a>
                                <button type="button" wire:click="toggleActive({{ $wh->id_warehouse }})" wire:confirm="{{ $wh->is_active ? 'Nonaktifkan' : 'Aktifkan' }} gudang {{ $wh->warehouse_name }}?"
                                    title="{{ $wh->is_active ? 'Nonaktifkan' : 'Aktifkan' }}" class="inline-flex items-center justify-center w-7 h-7 rounded-md cursor-pointer {{ $wh->is_active ? 'text-[#c0392b] hover:bg-[#fdeceb]' : 'text-accent hover:bg-[#eef3fb]' }}">⏻</button>
                            </td>
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
