<?php

use App\Livewire\Concerns\HasGridPagination;
use App\Livewire\Concerns\HasGridStatFilter;
use App\Models\Master\ProdMarket;
use App\Support\Localization\Translator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

// Lookup Product Market (officer ProDev, user 2026-09-26): daftar "Kategori Produk" yang dipilih sales di
// Inquiry New. Pola Grid for Master DevLib: kotak foto di kolom pertama, Edit + Aktif/Nonaktif.
new #[Title('Lookup Product Market — TELASIH MAKMUR ERP')] class extends Component
{
    use HasGridPagination;
    use HasGridStatFilter;
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function items()
    {
        $term = trim($this->search);

        return $this->applyStatFilter(ProdMarket::query()->with(['type', 'paperCategory.parent.parent.parent.parent', 'sheetCategory.parent.parent.parent.parent'])->withCount('sizes'))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('market_label', 'like', "%{$term}%")
                ->orWhere('market_code', 'like', "%{$term}%")->orWhere('type_code', 'like', "%{$term}%")))
            ->orderBy('sort_order')->orderBy('market_label')
            ->paginate($this->perPage);
    }

    protected function statFilterDefs(): array
    {
        return [
            'total' => ['label' => 'Total Product Market', 'value' => ProdMarket::count(), 'tone' => 'total'],
            'active' => ['label' => 'Aktif', 'value' => ProdMarket::where('activate', true)->count(), 'tone' => 'active',
                'filter' => fn ($q) => $q->where('activate', true)],
            'inactive' => ['label' => 'Nonaktif', 'value' => ProdMarket::where('activate', false)->count(), 'tone' => 'critical',
                'filter' => fn ($q) => $q->where('activate', false)],
            'nosize' => ['label' => 'Belum ada ukuran', 'value' => ProdMarket::doesntHave('sizes')->count(), 'tone' => 'warning',
                'filter' => fn ($q) => $q->doesntHave('sizes')],
        ];
    }

    public function openItem(int $id)
    {
        return $this->redirect(route('lookup.product-market.edit', ['market' => $id]));
    }

    public function toggleActive(int $id): void
    {
        $r = ProdMarket::findOrFail($id);
        $r->update(['activate' => ! $r->activate, 'updated_by' => Auth::user()->login]);
        unset($this->items);
    }
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">
        <x-grid.stat-filter-row :boxes="$this->statBoxes()" :active="$statFilter" />

        <div class="bg-white border border-field-border rounded-md mb-4 overflow-hidden">
            <x-grid.toolbar>
                <x-form.input :case-toggle="false" wire:model.live.debounce.300ms="search" placeholder="Cari nama, kode, tipe produk…" class="w-64" />
                <x-slot:newRecord>
                    <x-ui.button preset="new" :href="route('lookup.product-market.create')" />
                </x-slot:newRecord>
            </x-grid.toolbar>

            <x-ui.table hover>
                <thead>
                    <tr>
                        <th class="w-[72px]">Foto</th>
                        <th>Product Market</th>
                        <th>Tipe Produk</th>
                        <th class="text-right">Ukuran Pasaran</th>
                        <th>Bahan Awal</th>
                        <th>Warna</th>
                        <th>Jenjang Qty</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->items as $row)
                        <tr wire:key="pm-{{ $row->id_market }}" wire:dblclick="openItem({{ $row->id_market }})" @class(['cursor-pointer', 'opacity-55' => ! $row->activate])
                            title="{{ Translator::t('hrm_emp_dblclick_detail_hint', 'Klik 2x untuk buka detail') }}">
                            <td>
                                @if ($row->photoUrl())
                                    <img src="{{ $row->photoUrl() }}" alt="" class="w-12 h-12 object-cover rounded border border-field-border" loading="lazy">
                                @else
                                    <span class="w-12 h-12 rounded border border-field-border bg-accent-soft text-accent font-bold flex items-center justify-center">{{ $row->initials() }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="font-semibold text-navy">{{ $row->market_label }}</div>
                                <div class="text-[10.5px] text-muted font-mono">{{ $row->market_code }}</div>
                            </td>
                            <td>{{ $row->type?->type_name ?? $row->type_code }}</td>
                            <td class="text-right">{{ $row->sizes_count ?: '—' }}</td>
                            <td class="text-[11px] leading-snug">
                                <div><span class="text-muted">#1</span> {{ ProdMarket::chainLabel($row->paperCategory) ?? '—' }}</div>
                                <div><span class="text-muted">#2</span> {{ ProdMarket::chainLabel($row->sheetCategory) ?? '—' }}</div>
                            </td>
                            <td>{{ $row->default_color ?: '—' }}</td>
                            <td>{{ $row->tierList() ? collect($row->tierList())->map(fn ($q) => number_format($q, 0, ',', '.'))->implode(' / ') : '—' }}</td>
                            <td class="text-right whitespace-nowrap">
                                <x-grid.action-icon icon="fa-pen" title="Edit (klik 2x baris)" action="openItem({{ $row->id_market }})" />
                                <x-grid.action-icon title="{{ $row->activate ? 'Nonaktifkan' : 'Aktifkan' }}" action="toggleActive({{ $row->id_market }})" :active="$row->activate"
                                    confirm="{{ $row->activate ? 'Nonaktifkan' : 'Aktifkan' }} {{ $row->market_label }}?" />
                            </td>
                        </tr>
                    @empty
                        <x-grid.empty-row :colspan="8" />
                    @endforelse
                </tbody>
            </x-ui.table>

            @include('components.grid.pagination-footer', ['paginator' => $this->items])
        </div>
    </div>
</div>
