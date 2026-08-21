<?php

use App\Models\Master\MRawMaterial;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Master Item Bahan Baku — TELASIH MAKMUR ERP')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $typeFilter = '';

    public string $categoryFilter = '';

    // grid_performance (GLOBAL_RULES.yaml): server-side pagination + WHERE.
    #[Computed]
    public function items()
    {
        return MRawMaterial::when($this->search !== '', function ($q) {
                $term = $this->search;
                $q->where(function ($qq) use ($term) {
                    $qq->where('material_code', 'like', "%{$term}%")
                        ->orWhere('material_name', 'like', "%{$term}%")
                        ->orWhere('hs_code', 'like', "%{$term}%");
                });
            })
            ->when($this->typeFilter !== '', fn ($q) => $q->where('item_type', $this->typeFilter))
            ->when($this->categoryFilter !== '', fn ($q) => $q->where('category1', $this->categoryFilter))
            ->orderBy('material_name')
            ->paginate(25);
    }

    #[Computed]
    public function categories()
    {
        return MRawMaterial::query()->distinct()->orderBy('category1')->pluck('category1')->filter();
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => MRawMaterial::count(),
            'material' => MRawMaterial::where('item_type', 'MATERIAL')->count(),
            'non_material' => MRawMaterial::whereIn('item_type', ['SERVICE', 'FREE_SAMPLE'])->count(),
            'low_stock' => MRawMaterial::whereNotNull('reorder_point')->whereColumn('cur_stock', '<=', 'reorder_point')->count(),
        ];
    }

    public function toggleActive(int $materialId): void
    {
        $m = MRawMaterial::findOrFail($materialId);
        $m->is_active = ! $m->is_active;
        $m->update_by = Auth::user()->login;
        $m->save();

        unset($this->items, $this->stats);
    }
};
?>

<div>
    <div class="max-w-[1500px] mx-auto my-5 px-4">

        {{-- STATS --}}
        <div class="grid grid-cols-4 gap-3 mb-4">
            <div class="rounded-lg p-3.5 shadow-sm bg-[#eaf2ff] border border-[#c8ddfb]">
                <p class="text-[10.5px] font-bold uppercase text-[#3b5b99]">Total Master Item</p>
                <p class="text-xl font-extrabold text-[#1d4ed8] mt-0.5">{{ number_format($this->stats['total']) }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#e9f9ef] border border-[#c8ecd6]">
                <p class="text-[10.5px] font-bold uppercase text-[#2f7a52]">Bahan Baku (Stok)</p>
                <p class="text-xl font-extrabold text-[#16a34a] mt-0.5">{{ number_format($this->stats['material']) }}</p>
                <p class="text-[10px] text-[#2f7a52]/70">MATERIAL</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#f1ecfc] border border-[#ddd0f7]">
                <p class="text-[10.5px] font-bold uppercase text-[#6b4fa0]">Jasa / Sample</p>
                <p class="text-xl font-extrabold text-[#6d28d9] mt-0.5">{{ number_format($this->stats['non_material']) }}</p>
                <p class="text-[10px] text-[#6b4fa0]/70">SERVICE / FREE_SAMPLE</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#fdecec] border border-[#f5c6c6]">
                <p class="text-[10.5px] font-bold uppercase text-[#a3453f]">Stok Kritis</p>
                <p class="text-xl font-extrabold text-[#c0392b] mt-0.5">{{ number_format($this->stats['low_stock']) }}</p>
                <p class="text-[10px] text-[#a3453f]/70">≤ Reorder Point</p>
            </div>
        </div>

        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 overflow-hidden">
            <div class="flex items-center gap-2 px-4 py-2.5 bg-[#f4f6fa] border-b border-[#d7dbe2] flex-wrap">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="🔍 Cari kode, nama, HS code..." class="w-72 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                <select wire:model.live="typeFilter" class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                    <option value="">Semua Tipe</option>
                    <option value="MATERIAL">MATERIAL</option>
                    <option value="SERVICE">SERVICE</option>
                    <option value="FREE_SAMPLE">FREE_SAMPLE</option>
                </select>
                <select wire:model.live="categoryFilter" class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                    <option value="">Semua Kategori</option>
                    @foreach ($this->categories as $c)
                        <option value="{{ $c }}">{{ $c }}</option>
                    @endforeach
                </select>
                <span class="flex-1"></span>
                <a href="{{ route('master.raw-material.create') }}" class="bg-accent text-white text-xs px-3.5 py-1.5 rounded-md no-underline hover:brightness-110 font-semibold">＋ Item Baru</a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs border-collapse">
                    <thead>
                        <tr class="bg-[#f4f5f8]">
                            <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Kode</th>
                            <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Nama Item</th>
                            <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Tipe</th>
                            <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Kategori</th>
                            <th class="text-right px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Gramatur</th>
                            <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">UoM</th>
                            <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Metode</th>
                            <th class="text-right px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Stok</th>
                            <th class="text-right px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Harga Rata²</th>
                            <th class="px-3 py-2 border-b border-[#d7dbe2]"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->items as $m)
                            <tr class="border-b border-[#eef1f5] hover:bg-[#fbfcfe]" wire:key="mat-{{ $m->material_id }}">
                                <td class="px-3 py-2 font-mono font-bold text-accent">{{ $m->material_code }}</td>
                                <td class="px-3 py-2">{{ $m->material_name }}</td>
                                <td class="px-3 py-2"><span class="text-[10px] font-bold px-2 py-0.5 rounded bg-[#eef1f5] text-[#4b5568]">{{ $m->item_type }}</span></td>
                                <td class="px-3 py-2 text-[10.5px]">{{ $m->category1 }} @if($m->category2) » {{ $m->category2 }} @endif</td>
                                <td class="px-3 py-2 text-right">{{ $m->gram_weight ? $m->gram_weight.' gsm' : '—' }}</td>
                                <td class="px-3 py-2 font-bold">{{ $m->uom_stock }}</td>
                                <td class="px-3 py-2 font-mono text-[10.5px]">{{ $m->costing_method }}</td>
                                <td class="px-3 py-2 text-right {{ $m->reorder_point && $m->cur_stock <= $m->reorder_point ? 'text-[#a3241f] font-bold' : '' }}">{{ number_format((float) $m->cur_stock, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $m->avg_unitprice, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    <a href="{{ route('master.raw-material.edit', ['material' => $m->material_id]) }}" title="Edit" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer no-underline">✎</a>
                                    <button type="button" wire:click="toggleActive({{ $m->material_id }})" wire:confirm="{{ $m->is_active ? 'Nonaktifkan' : 'Aktifkan' }} item {{ $m->material_name }}?"
                                        title="{{ $m->is_active ? 'Nonaktifkan' : 'Aktifkan' }}" class="inline-flex items-center justify-center w-7 h-7 rounded-md cursor-pointer {{ $m->is_active ? 'text-[#c0392b] hover:bg-[#fdeceb]' : 'text-accent hover:bg-[#eef3fb]' }}">⏻</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center py-8 text-muted">Tidak ada item cocok.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t border-[#eef1f5]">
                {{ $this->items->links() }}
            </div>
        </div>
    </div>
</div>
