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

    // grid_performance (globalrules.yaml): server-side pagination + WHERE.
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
    <div class="max-w-[1280px] mx-auto my-5 px-4">

        <div class="bg-amber-50 border border-amber-300 text-amber-800 text-[11px] px-3.5 py-2 rounded-md mb-4">
            ⓘ Stok on-hand tidak ditampilkan di sini — itu posisi transaksional dari modul Inventory, bukan master data. Layar ini murni struktur gudang & lokasi/bin.
        </div>

        {{-- STATS --}}
        <div class="grid grid-cols-4 gap-3 mb-4">
            <div class="rounded-lg p-3.5 shadow-sm bg-[#eaf2ff] border border-[#c8ddfb]">
                <p class="text-[10.5px] font-bold uppercase text-[#3b5b99]">Total Gudang</p>
                <p class="text-xl font-extrabold text-[#1d4ed8] mt-0.5">{{ number_format($this->stats['total']) }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#e9f9ef] border border-[#c8ecd6]">
                <p class="text-[10.5px] font-bold uppercase text-[#2f7a52]">Gudang Bahan Baku</p>
                <p class="text-xl font-extrabold text-[#16a34a] mt-0.5">{{ number_format($this->stats['raw']) }}</p>
                <p class="text-[10px] text-[#2f7a52]/70">RM</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#f1ecfc] border border-[#ddd0f7]">
                <p class="text-[10.5px] font-bold uppercase text-[#6b4fa0]">Gudang Barang Jadi</p>
                <p class="text-xl font-extrabold text-[#6d28d9] mt-0.5">{{ number_format($this->stats['fg']) }}</p>
                <p class="text-[10px] text-[#6b4fa0]/70">FG</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#fef6e7] border border-[#f5e0b3]">
                <p class="text-[10.5px] font-bold uppercase text-[#8a6d00]">Total Lokasi / Bin</p>
                <p class="text-xl font-extrabold text-[#b45309] mt-0.5">{{ number_format($this->stats['locations']) }}</p>
            </div>
        </div>

        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 overflow-hidden">
            <div class="flex items-center gap-2 bg-white border-b border-[#d7dbe2] px-2.5 py-2 overflow-x-auto">
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari kode / nama gudang..."
                        class="w-56 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <span class="flex-1"></span>
                <a href="{{ route('master.warehouse.create') }}"
                    class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] no-underline cursor-pointer">+ New Record</a>
            </div>

            <table class="w-full text-xs border-collapse">
                <thead>
                    <tr class="bg-[#f4f5f8]">
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Gudang</th>
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Tipe</th>
                        <th class="text-right px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Jumlah Lokasi</th>
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Status</th>
                        <th class="px-3 py-2 border-b border-[#d7dbe2]"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->warehouses as $wh)
                        <tr class="border-b border-[#eef1f5] hover:bg-[#fbfcfe] cursor-pointer" wire:key="wh-{{ $wh->id_warehouse }}" ondblclick="window.location='{{ route('master.warehouse.edit', ['warehouse' => $wh->id_warehouse]) }}'" title="Klik 2x untuk buka detail">
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-md bg-gradient-to-br {{ $this->avatarGradient($wh->id_warehouse) }} text-white text-[11px] font-bold flex items-center justify-center shrink-0">{{ $this->avatarInitials($wh->warehouse_name) }}</div>
                                    <div>
                                        <div class="font-semibold text-[13px]">{{ $wh->warehouse_name }}</div>
                                        <div class="text-[11px] text-muted font-mono">{{ $wh->warehouse_code }}</div>
                                    </div>
                                </div>
                            </td>
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
                        <tr><td colspan="5" class="text-center py-8 text-muted">Belum ada gudang.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="px-4 py-3 border-t border-[#eef1f5]">
                {{ $this->warehouses->links() }}
            </div>
        </div>
    </div>
</div>
