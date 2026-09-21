<?php

use App\Livewire\Concerns\HasGridStatFilter;
use App\Livewire\Concerns\HasGridPagination;
use App\Models\Master\WhWarehouse;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Master Gudang — TELASIH MAKMUR ERP')] class extends Component
{
    use HasGridStatFilter;

    use HasGridPagination;
    use WithPagination;

    public string $search = '';

    // grid_performance (globalrules.yaml): server-side pagination + WHERE.
    #[Computed]
    public function warehouses()
    {
        return WhWarehouse::withCount('locations')
            ->when($this->search !== '', function ($q) {
                $term = $this->search;
                $q->where(function ($qq) use ($term) {
                    $qq->where('warehouse_code', 'like', "%{$term}%")
                        ->orWhere('warehouse_name', 'like', "%{$term}%");
                });
            })
            ->tap(fn ($q) => $this->applyStatFilter($q))
            ->orderBy('warehouse_code')
            ->paginate($this->perPage);
    }

    // KPI struktural saja (jumlah gudang/lokasi) -- BUKAN stok, karena
    // modul Inventory (sumber ledger stok asli) belum dibangun. Lihat
    // catatan deviasi di migration 2026_08_21_000006.
    #[Computed]
    public function stats(): array
    {
        return [
            'total' => WhWarehouse::count(),
            'raw' => WhWarehouse::where('warehouse_type', 'RM')->count(),
            'fg' => WhWarehouse::where('warehouse_type', 'FG')->count(),
            'locations' => \App\Models\Master\WhLoc::count(),
        ];
    }

    public function toggleActive(int $warehouseId): void
    {
        $w = WhWarehouse::findOrFail($warehouseId);
        $w->activate = ! $w->activate;
        $w->updated_by = Auth::user()->login;
        $w->save();

        unset($this->warehouses, $this->stats);
    }

    // Componentization grid (2026-08-27): buka detail via wire:dblclick+redirect
    // (dulu ondblclick="window.location=...'"), avatarInitials()/avatarGradient()
    // dipindah ke App\Support\Grid\Avatar (dipakai <x-grid.identity-cell>).
    public function openWarehouse(int $id)
    {
        return $this->redirect(route('master.warehouse.edit', ['warehouse' => $id]));
    }

    // Kotak statistik = filter data (griduistandard.md §2b). "Total Gudang"
    // sama dengan tanpa filter; "Total Lokasi / Bin" menghitung tabel LAIN
    // (wh_loc), bukan kategori baris gudang -- keduanya sengaja tak diklik.
    protected function statFilterDefs(): array
    {
        $s = $this->stats;

        return [
            'total' => ['label' => 'Total Gudang', 'value' => number_format($s['total']), 'tone' => 'total'],
            'raw' => ['label' => 'Gudang Bahan Baku', 'value' => number_format($s['raw']), 'tone' => 'active', 'subtitle' => 'RM',
                'filter' => fn ($q) => $q->where('warehouse_type', 'RM')],
            'fg' => ['label' => 'Gudang Barang Jadi', 'value' => number_format($s['fg']), 'tone' => 'extra', 'subtitle' => 'FG',
                'filter' => fn ($q) => $q->where('warehouse_type', 'FG')],
            'locations' => ['label' => 'Total Lokasi / Bin', 'value' => number_format($s['locations']), 'tone' => 'warn'],
        ];
    }
};
?>
<div>
    <div class="max-w-[1280px] mx-auto my-5 px-4">

        <div class="bg-amber-50 border border-amber-300 text-amber-800 text-[11px] px-3.5 py-2 rounded-md mb-4">
            ⓘ Stok on-hand tidak ditampilkan di sini — itu posisi transaksional dari modul Inventory, bukan master data. Layar ini murni struktur gudang & lokasi/bin.
        </div>

        <x-grid.stat-filter-row :boxes="$this->statBoxes()" :active="$statFilter" />

        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari kode / nama gudang..."
                        class="w-56 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <x-slot:newRecord>
                    <a href="{{ route('master.warehouse.create') }}"
                        class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] no-underline cursor-pointer">+ New Record</a>
                </x-slot:newRecord>
            </x-grid.toolbar>

            <x-grid.table-shell>
            <table class="w-full text-xs border-collapse">
                <thead>
                    <tr>
                        <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Gudang</th>
                        <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Tipe</th>
                        <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Jumlah Lokasi</th>
                        <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Status</th>
                        <th class="px-3 py-2 bg-[#fafbfd] border-b border-[#eef1f5] sticky top-0 z-[1]"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->warehouses as $wh)
                        <tr class="border-b border-[#eef1f5] hover:bg-[#fbfcfe] cursor-pointer" wire:key="wh-{{ $wh->id_warehouse }}" wire:dblclick="openWarehouse({{ $wh->id_warehouse }})" title="Klik 2x untuk buka detail">
                            <x-grid.identity-cell :id="$wh->id_warehouse" :name="$wh->warehouse_name" :code="$wh->warehouse_code" />
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
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $wh->activate ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#fbeaea] text-[#a3241f]' }}">{{ $wh->activate ? 'Aktif' : 'Nonaktif' }}</span>
                            </td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                <x-grid.action-icon icon="fa-pen" title="Edit" action="openWarehouse({{ $wh->id_warehouse }})" />
                                <x-grid.action-icon :active="$wh->activate" :title="$wh->activate ? 'Nonaktifkan' : 'Aktifkan'" action="toggleActive({{ $wh->id_warehouse }})"
                                    :confirm="($wh->activate ? 'Nonaktifkan' : 'Aktifkan').' gudang '.$wh->warehouse_name.'?'"
                                    :variant="$wh->activate ? 'danger' : 'default'" />
                            </td>
                        </tr>
                    @empty
                        <x-grid.empty-row :colspan="5" message="Belum ada gudang." />
                    @endforelse
                </tbody>
            </table>
            </x-grid.table-shell>

            @include('components.grid.pagination-footer', ['paginator' => $this->warehouses])
        </div>
    </div>
</div>
