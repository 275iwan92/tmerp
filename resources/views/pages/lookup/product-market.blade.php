<?php

use App\Livewire\Concerns\HasGridPagination;
use App\Livewire\Concerns\HasGridStatFilter;
use App\Models\Master\ProdMarket;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

// Lookup Product Market (officer ProDev, user 2026-09-26): daftar "Kategori Produk" yang dipilih sales di
// Inquiry New. Pola Grid for Master DevLib: kotak foto di kolom pertama, Edit + Aktif/Nonaktif.
new #[Title('Master Product Market — TELASIH MAKMUR ERP')] class extends Component
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

        // Urut per Kategori: baris Kategori lalu Detail-nya (parent_id null = Kategori).
        return $this->applyStatFilter(ProdMarket::query()->with(['parent', 'type', 'paperCategory.parent.parent.parent.parent', 'sheetCategory.parent.parent.parent.parent'])->withCount(['sizes', 'children'])
            ->leftJoin('prod_market as g', 'g.id_market', '=', 'prod_market.parent_id')->select('prod_market.*'))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('prod_market.market_label', 'like', "%{$term}%")
                ->orWhere('prod_market.market_code', 'like', "%{$term}%")->orWhere('prod_market.type_code', 'like', "%{$term}%")->orWhere('g.market_label', 'like', "%{$term}%")))
            ->orderByRaw('COALESCE(g.sort_order, prod_market.sort_order), COALESCE(g.id_market, prod_market.id_market), prod_market.parent_id IS NOT NULL, prod_market.sort_order, prod_market.market_label')
            ->paginate($this->perPage);
    }

    protected function statFilterDefs(): array
    {
        return [
            'total' => ['label' => t('Total Product Market'), 'value' => ProdMarket::count(), 'tone' => 'total'],
            'group' => ['label' => t('Category'), 'value' => ProdMarket::whereNull('parent_id')->count(), 'tone' => 'extra',
                'filter' => fn ($q) => $q->whereNull('prod_market.parent_id')],
            'active' => ['label' => t('Active'), 'value' => ProdMarket::where('activate', true)->count(), 'tone' => 'active',
                'filter' => fn ($q) => $q->where('prod_market.activate', true)],
            'inactive' => ['label' => t('Inactive'), 'value' => ProdMarket::where('activate', false)->count(), 'tone' => 'critical',
                'filter' => fn ($q) => $q->where('prod_market.activate', false)],
            'nosize' => ['label' => t('Details without size'), 'value' => ProdMarket::whereNotNull('parent_id')->doesntHave('sizes')->count(), 'tone' => 'warning',
                'filter' => fn ($q) => $q->whereNotNull('prod_market.parent_id')->doesntHave('sizes')],
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
                <x-form.input :case-toggle="false" wire:model.live.debounce.300ms="search" :placeholder="t('Search name, code, product type…')" class="w-64" />
                <x-slot:newRecord>
                    <x-ui.button preset="new" :href="route('lookup.product-market.create')" />
                </x-slot:newRecord>
            </x-grid.toolbar>

            <x-ui.table hover>
                <thead>
                    <tr>
                        <th class="w-[72px]">{{ t('Photo') }}</th>
                        <th>{{ t('Product Market') }}</th>
                        <th>{{ t('Product Type') }}</th>
                        <th class="text-right">{{ t('Market Sizes') }}</th>
                        <th>{{ t('Initial Material') }}</th>
                        <th>{{ t('Colour') }}</th>
                        <th>{{ t('Qty Tiers') }}</th>
                        <th class="text-right">{{ t('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->items as $row)
                        <tr wire:key="pm-{{ $row->id_market }}" wire:dblclick="openItem({{ $row->id_market }})" @class(['cursor-pointer', 'opacity-55' => ! $row->activate])
                            title="{{ t('Double-click to open detail') }}">
                            <td>
                                @if ($row->photoUrl())
                                    <img src="{{ $row->photoUrl() }}" alt="" class="w-12 h-12 object-cover rounded border border-field-border" loading="lazy">
                                @else
                                    <span class="w-12 h-12 rounded border border-field-border bg-accent-soft text-accent font-bold flex items-center justify-center">{{ $row->initials() }}</span>
                                @endif
                            </td>
                            <td @class(['pl-7' => ! $row->isGroup()])>
                                <div class="font-semibold text-navy">
                                    @if ($row->isGroup())
                                        <x-ui.badge tone="accent" size="xs" pill>{{ t('Category') }}</x-ui.badge>
                                    @else
                                        <span class="text-muted">{{ $row->parent?->market_label }} ›</span>
                                    @endif
                                    {{ $row->market_label }}
                                </div>
                                <div class="text-[10.5px] text-muted font-mono">{{ $row->market_code }}{{ $row->isGroup() ? ' · '.$row->children_count.' '.t('detail') : '' }}</div>
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
                                <x-grid.action-icon icon="fa-pen" :title="t('Edit (double-click row)')" action="openItem({{ $row->id_market }})" />
                                <x-grid.action-icon title="{{ $row->activate ? t('Deactivate') : t('Activate') }}" action="toggleActive({{ $row->id_market }})" :active="$row->activate"
                                    confirm="{{ $row->activate ? t('Deactivate') : t('Activate') }} {{ $row->market_label }}?" />
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
