<?php

use App\Livewire\Concerns\HasGridStatFilter;
use App\Livewire\Concerns\HasGridPagination;
use App\Models\Master\CustCustomer;
use App\Models\Master\ProdCategory;
use App\Models\Master\ProdProduct;
use App\Models\Master\UomUnit;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

// Master Produk (menu MD_PROD). Grid+form standar header prod_product, paralel
// ke Customer/Supplier/dst -- BUKAN layar "Master Card" (PROD_CARD, modul
// Production, F1-F11 di masterproduct_tier1.md: dieline/material layer/warna
// per pass/sign-off). Kolom prod_product.design_no/card_status sudah ada di DB
// (delta TIER_3A_MASTER_CARD.sql ternyata sudah jalan) tapi sengaja TIDAK
// dipakai di sini -- itu domain Master Card yang belum digarap, biar tidak
// setengah-jadi. cur_stock TIDAK bisa diedit dari sini (ikut pola Warehouse:
// itu posisi stok transaksional dari ledger Inventory, bukan master data).
new #[Title('Master Produk — TELASIH MAKMUR ERP')] class extends Component
{
    use HasGridStatFilter;

    use HasGridPagination;
    use WithPagination;

    public string $search = '';

    public ?int $filterCategory = null;

    public bool $filterWithImage = false;

    public bool $filterUnclassified = false;

    public string $panel = 'grid'; // grid | form

    public string $mode = 'new'; // new | edit

    public ?int $editingId = null;

    public bool $lockedSpec = false; // is_confirmed saat dibuka -- kode & dimensi dikunci

    public string $product_code = '';

    public string $product_name = '';

    public ?int $category_id = null;

    public ?int $customer_id = null;

    public string $customer_label = '';

    public string $custQuery = '';

    public string $description = '';

    public string $uom_stock = '';

    public string $uom_sales = '';

    public string $conversion_to_stock = '1';

    public string $qty_limit_min = '';

    public string $qty_limit_max = '';

    public string $cm_length = '';

    public string $cm_width = '';

    public string $cm_height = '';

    public string $min_stock = '';

    public string $max_stock = '';

    public string $default_selling_price = '';

    public string $minimum_selling_price = '';

    public bool $product_is_confirmed = false;

    public bool $product_activate = true;

    // Read-only di form: berasal dari Master Card legacy & klasifikasi mst_item,
    // bukan diisi user. Ditampilkan supaya asal-usul datanya kelihatan.
    public ?string $image_path = null;

    public ?int $image_source_card_id = null;

    public string $brand_name = '';

    public string $old_item_code = '';

    public string $old_goods_purpose = '';

    // Dulu ->get() polos: 14.010 baris + eager load dikirim ke browser tiap
    // render. Sekarang paginasi server-side (grid_performance, globalrules.yaml).
    #[Computed]
    public function products()
    {
        return ProdProduct::query()
            ->with(['category', 'customer'])
            ->when($this->search !== '', fn ($q) => $q->where(fn ($qq) => $qq
                ->where('product_code', 'like', "%{$this->search}%")
                ->orWhere('product_name', 'like', "%{$this->search}%")
                ->orWhere('old_item_code', 'like', "%{$this->search}%")
                ->orWhere('brand_name', 'like', "%{$this->search}%")))
            ->when($this->filterCategory, fn ($q) => $q->where('category_id', $this->filterCategory))
            ->when($this->filterWithImage, fn ($q) => $q->whereNotNull('image_path'))
            ->when($this->filterUnclassified, fn ($q) => $q->whereNull('category_id')->whereNull('customer_id'))
            ->tap(fn ($q) => $this->applyStatFilter($q))
            ->orderBy('product_name')
            ->paginate($this->perPage);
    }

    // Pindah halaman tanpa reset = "tidak ada hasil" palsu saat filter dipersempit
    // dari halaman 40.
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterCategory(): void
    {
        $this->resetPage();
    }

    public function updatedFilterWithImage(): void
    {
        $this->resetPage();
    }

    public function updatedFilterUnclassified(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'filterCategory', 'filterWithImage', 'filterUnclassified']);
        $this->resetPage();
    }

    #[Computed]
    public function categoryOptions()
    {
        return ProdCategory::query()->where('activate', true)->orderBy('category_name')->get();
    }

    #[Computed]
    public function uomOptions()
    {
        return UomUnit::query()->where('activate', true)->orderBy('uom_code')->get();
    }

    #[Computed]
    public function customerResults()
    {
        if ($this->customer_id || mb_strlen($this->custQuery) < 2) {
            return collect();
        }

        return CustCustomer::query()
            ->where('activate', true)
            ->where(fn ($q) => $q->where('customer_name', 'like', "%{$this->custQuery}%")
                ->orWhere('customer_code', 'like', "%{$this->custQuery}%"))
            ->orderBy('customer_name')
            ->limit(8)
            ->get();
    }

    public function selectCustomer(int $customerId): void
    {
        $c = CustCustomer::find($customerId);
        if (! $c) {
            return;
        }
        $this->customer_id = $c->id_customer;
        $this->customer_label = $c->customer_name.' ('.$c->customer_code.')';
        $this->custQuery = '';
    }

    public function clearCustomer(): void
    {
        $this->customer_id = null;
        $this->customer_label = '';
    }

    #[Computed]
    public function statTotal(): int
    {
        return ProdProduct::count();
    }

    #[Computed]
    public function statActive(): int
    {
        return ProdProduct::where('activate', true)->count();
    }

    #[Computed]
    public function statConfirmed(): int
    {
        return ProdProduct::where('stat_confirm', true)->count();
    }

    #[Computed]
    public function statInactive(): int
    {
        return ProdProduct::where('activate', false)->count();
    }

    #[Computed]
    public function statWithImage(): int
    {
        return ProdProduct::whereNotNull('image_path')->count();
    }

    public function openModal(string $mode, ?int $id = null): void
    {
        $this->resetErrorBag();
        $this->mode = in_array($mode, ['new', 'edit'], true) ? $mode : 'new';
        $this->editingId = $id;

        if ($this->mode === 'edit' && $id) {
            $p = ProdProduct::with('customer')->findOrFail($id);
            $this->product_code = $p->product_code;
            $this->product_name = (string) $p->product_name;
            $this->category_id = $p->category_id;
            $this->customer_id = $p->customer_id;
            $this->customer_label = $p->customer ? $p->customer->customer_name.' ('.$p->customer->customer_code.')' : '';
            $this->custQuery = '';
            $this->description = (string) $p->description;
            $this->uom_stock = (string) $p->uom_stock;
            $this->uom_sales = (string) $p->uom_sales;
            $this->conversion_to_stock = (string) $p->conversion_to_stock;
            $this->qty_limit_min = (string) ($p->qty_limit_min ?? '');
            $this->qty_limit_max = (string) ($p->qty_limit_max ?? '');
            $this->cm_length = (string) ($p->cm_length ?? '');
            $this->cm_width = (string) ($p->cm_width ?? '');
            $this->cm_height = (string) ($p->cm_height ?? '');
            $this->min_stock = (string) ($p->min_stock ?? '');
            $this->max_stock = (string) ($p->max_stock ?? '');
            $this->default_selling_price = (string) ($p->default_selling_price ?? '');
            $this->minimum_selling_price = (string) ($p->minimum_selling_price ?? '');
            $this->product_is_confirmed = (bool) $p->stat_confirm;
            $this->product_activate = (bool) $p->activate;
            $this->lockedSpec = (bool) $p->stat_confirm;
            $this->image_path = $p->image_path;
            $this->image_source_card_id = $p->image_source_card_id;
            $this->brand_name = (string) $p->brand_name;
            $this->old_item_code = (string) $p->old_item_code;
            $this->old_goods_purpose = (string) $p->old_goods_purpose;
        } else {
            $this->reset([
                'product_code', 'product_name', 'category_id', 'customer_id', 'customer_label', 'custQuery',
                'description', 'uom_stock', 'uom_sales', 'qty_limit_min', 'qty_limit_max',
                'cm_length', 'cm_width', 'cm_height', 'min_stock', 'max_stock',
                'default_selling_price', 'minimum_selling_price',
                'image_path', 'image_source_card_id', 'brand_name', 'old_item_code', 'old_goods_purpose',
            ]);
            $this->conversion_to_stock = '1';
            $this->product_is_confirmed = false;
            $this->product_activate = true;
            $this->lockedSpec = false;
        }

        $this->panel = 'form';
    }

    public function showGrid(): void
    {
        $this->panel = 'grid';
    }

    public function save(): void
    {
        $unique = 'unique:tm2mst.prod_product,product_code';
        if ($this->mode === 'edit' && $this->editingId) {
            $unique .= ','.$this->editingId.',id_product';
        }

        $data = $this->validate([
            'product_code' => ['required', 'string', 'max:100', $unique],
            'product_name' => ['required', 'string', 'max:200'],
            'brand_name' => ['nullable', 'string', 'max:50'],
            'category_id' => ['nullable', 'integer', 'exists:tm2mst.prod_category,id_category'],
            'customer_id' => ['nullable', 'integer', 'exists:tm2mst.cust_customer,id_customer'],
            'description' => ['nullable', 'string'],
            'uom_stock' => ['nullable', 'string', 'max:50'],
            'uom_sales' => ['nullable', 'string', 'max:50'],
            'conversion_to_stock' => ['nullable', 'numeric', 'min:0'],
            'qty_limit_min' => ['nullable', 'numeric', 'min:0'],
            'qty_limit_max' => ['nullable', 'numeric', 'min:0'],
            'cm_length' => ['nullable', 'numeric', 'min:0'],
            'cm_width' => ['nullable', 'numeric', 'min:0'],
            'cm_height' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'max_stock' => ['nullable', 'numeric', 'min:0'],
            'default_selling_price' => ['nullable', 'numeric', 'min:0'],
            'minimum_selling_price' => ['nullable', 'numeric', 'min:0'],
            'product_is_confirmed' => ['boolean'],
            'product_activate' => ['boolean'],
        ]);

        $payload = [
            'product_code' => trim($data['product_code']),
            'product_name' => $data['product_name'],
            'brand_name' => $data['brand_name'] ?: null,
            'category_id' => $data['category_id'] ?: null,
            'customer_id' => $data['customer_id'] ?: null,
            'description' => $data['description'] ?: null,
            'uom_stock' => $data['uom_stock'] ?: null,
            'uom_sales' => $data['uom_sales'] ?: null,
            'conversion_to_stock' => $data['conversion_to_stock'] !== '' && $data['conversion_to_stock'] !== null ? $data['conversion_to_stock'] : 1,
            'qty_limit_min' => $data['qty_limit_min'] !== '' && $data['qty_limit_min'] !== null ? $data['qty_limit_min'] : null,
            'qty_limit_max' => $data['qty_limit_max'] !== '' && $data['qty_limit_max'] !== null ? $data['qty_limit_max'] : null,
            'cm_length' => $data['cm_length'] !== '' && $data['cm_length'] !== null ? $data['cm_length'] : null,
            'cm_width' => $data['cm_width'] !== '' && $data['cm_width'] !== null ? $data['cm_width'] : null,
            'cm_height' => $data['cm_height'] !== '' && $data['cm_height'] !== null ? $data['cm_height'] : null,
            'min_stock' => $data['min_stock'] !== '' && $data['min_stock'] !== null ? $data['min_stock'] : null,
            'max_stock' => $data['max_stock'] !== '' && $data['max_stock'] !== null ? $data['max_stock'] : null,
            'default_selling_price' => $data['default_selling_price'] !== '' && $data['default_selling_price'] !== null ? $data['default_selling_price'] : null,
            'minimum_selling_price' => $data['minimum_selling_price'] !== '' && $data['minimum_selling_price'] !== null ? $data['minimum_selling_price'] : null,
            'stat_confirm' => $data['product_is_confirmed'],
            'activate' => $data['product_activate'],
            'updated_by' => Auth::user()->login,
        ];

        if ($this->mode === 'edit' && $this->editingId) {
            ProdProduct::whereKey($this->editingId)->update($payload);
        } else {
            $payload['created_by'] = Auth::user()->login;
            ProdProduct::create($payload);
        }

        unset($this->products, $this->statTotal, $this->statActive, $this->statConfirmed, $this->statInactive, $this->statWithImage);
        $this->panel = 'grid';
    }

    public function toggleActive(int $id): void
    {
        $p = ProdProduct::findOrFail($id);
        $p->activate = ! $p->activate;
        $p->updated_by = Auth::user()->login;
        $p->save();

        unset($this->products, $this->statTotal, $this->statActive, $this->statConfirmed, $this->statInactive, $this->statWithImage);
    }

    // Componentization grid+form (2026-08-27): avatarInitials()/avatarGradient()
    // dipindah ke App\Support\Grid\Avatar (dipakai <x-grid.identity-cell>).

    // Kotak statistik = filter data (griduistandard.md §2b).
    protected function statFilterDefs(): array
    {
        return [
            'total' => ['label' => t('Total Products'), 'value' => $this->statTotal, 'tone' => 'total'],
            'active' => ['label' => t('Active'), 'value' => $this->statActive, 'tone' => 'active',
                'filter' => fn ($q) => $q->where('activate', true)],
            'confirmed' => ['label' => t('Confirmed'), 'value' => $this->statConfirmed, 'tone' => 'extra',
                'subtitle' => t('SPEC LOCKED'),
                'filter' => fn ($q) => $q->where('stat_confirm', true)],
            'with_image' => ['label' => t('With Image'), 'value' => $this->statWithImage, 'tone' => 'extra', 'subtitle' => t('FROM MASTER CARD'),
                'filter' => fn ($q) => $q->whereNotNull('image_path')],
            'inactive' => ['label' => t('Inactive'), 'value' => $this->statInactive, 'tone' => 'critical',
                'filter' => fn ($q) => $q->where('activate', false)],
        ];
    }
};
?>

<div>
    <div class="max-w-[1500px] mx-auto my-5 px-4">

        {{-- ============ GRID (LIST) ============ --}}
        @if ($panel === 'grid')
        <x-grid.stat-filter-row :boxes="$this->statBoxes()" :active="$statFilter" />

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ t('Search code / name / old code / brand…') }}"
                        class="w-56 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <select wire:model.live="filterCategory" class="shrink-0 px-2 py-1.5 border border-[#d7dbe2] rounded text-xs bg-white">
                    <option value="">{{ t('All categories') }}</option>
                    @foreach ($this->categoryOptions as $c)
                        <option value="{{ $c->id_category }}">{{ $c->category_name }}</option>
                    @endforeach
                </select>
                <label class="shrink-0 flex items-center gap-1.5 text-xs cursor-pointer" title="{{ t('Only products that have a Master Card image') }}">
                    <input type="checkbox" wire:model.live="filterWithImage"> 🖼 {{ t('With Image') }}
                </label>
                <label class="shrink-0 flex items-center gap-1.5 text-xs cursor-pointer" title="{{ t('Has neither a category nor a customer — data cleanup queue') }}">
                    <input type="checkbox" wire:model.live="filterUnclassified"> ⚠ {{ t('Unclassified') }}
                </label>
                @if ($search !== '' || $filterCategory || $filterWithImage || $filterUnclassified)
                    <button type="button" wire:click="clearFilters" class="shrink-0 px-2 py-1.5 rounded text-xs border border-[#d7dbe2] bg-white cursor-pointer">{{ t('Reset') }}</button>
                @endif
                <x-slot:newRecord>
                    <button type="button" wire:click="openModal('new')"
                        class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
                </x-slot:newRecord>
            </x-grid.toolbar>

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('Product') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[140px]">{{ t('Category') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[170px]">{{ t('Customer') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[150px]">{{ t('Brand / Purpose') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">{{ t('Dimension (cm)') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[150px]">{{ t('Status') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[80px]">{{ t('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->products as $p)
                            <tr wire:key="prod-{{ $p->id_product }}" wire:dblclick="openModal('edit', {{ $p->id_product }})" class="hover:bg-[#f7faff] cursor-pointer" title="{{ t('Double-click to open detail') }}">
                                {{-- Ikut pola foto Master Employee: thumbnail menggantikan avatar
                                     inisial kalau produk punya gambar Master Card, klik = perbesar. --}}
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <div class="flex items-center gap-2.5">
                                        @if ($p->image_thumb_path)
                                            <img src="{{ asset('storage/'.$p->image_thumb_path) }}" alt="{{ $p->product_name }}" loading="lazy"
                                                @click.stop="$dispatch('show-product-image', { src: '{{ asset('storage/'.$p->image_path) }}', name: @js($p->product_name) })"
                                                class="w-9 h-9 rounded-md object-cover shrink-0 border border-[#d9dfe8] cursor-zoom-in bg-white"
                                                title="{{ t('Click to enlarge') }}">
                                        @else
                                            <div class="w-9 h-9 rounded-md bg-gradient-to-br {{ \App\Support\Grid\Avatar::gradient((int) $p->id_product) }} text-white text-[11px] font-bold flex items-center justify-center shrink-0">{{ \App\Support\Grid\Avatar::initials($p->product_name ?: '?') }}</div>
                                        @endif
                                        <div class="min-w-0">
                                            <div class="font-semibold text-[13px] truncate">{{ $p->product_name ?: '—' }}</div>
                                            <div class="text-[11px] text-muted font-mono truncate">{{ $p->product_code }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $p->category->category_name ?? '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $p->customer->customer_name ?? '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    @if ($p->brand_name || $p->old_goods_purpose)
                                        <div class="truncate">{{ $p->brand_name ?: '—' }}</div>
                                        @if ($p->old_goods_purpose)
                                            <div class="text-[11px] text-muted truncate">{{ $p->old_goods_purpose }}</div>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] font-mono">
                                    @if ($p->cm_length || $p->cm_width || $p->cm_height)
                                        {{ rtrim(rtrim(number_format($p->cm_length, 1), '0'), '.') }}×{{ rtrim(rtrim(number_format($p->cm_width, 1), '0'), '.') }}×{{ rtrim(rtrim(number_format($p->cm_height, 1), '0'), '.') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <div class="flex flex-wrap gap-1">
                                        @if ($p->stat_confirm)
                                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#f1ecfc] text-[#6d28d9]">{{ t('LOCKED') }}</span>
                                        @endif
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block {{ $p->activate ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#eef0f3] text-[#8a8f98]' }}">{{ $p->activate ? t('ACTIVE') : t('INACTIVE') }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-pen" title="{{ t('Edit') }}" action="openModal('edit', {{ $p->id_product }})" />
                                    <x-grid.action-icon :active="$p->activate" :title="$p->activate ? t('Deactivate') : t('Activate')" action="toggleActive({{ $p->id_product }})"
                                        :confirm="str_replace(':name', $p->product_name, $p->activate ? t('Deactivate product :name?') : t('Activate product :name?'))"
                                        :variant="$p->activate ? 'danger' : 'default'" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="7" message="{{ t('No products.') }}" />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>

            @include('components.grid.pagination-footer', ['paginator' => $this->products])
        </div>

        {{-- Lightbox gambar Master Card. wire:ignore supaya morph Livewire tidak
             mereset state Alpine-nya saat grid re-render (ganti halaman/filter). --}}
        <div wire:ignore x-data="{ open: false, src: '', name: '' }"
             @show-product-image.window="src = $event.detail.src; name = $event.detail.name; open = true"
             @keydown.escape.window="open = false">
            <div x-show="open" x-cloak @click="open = false"
                 class="fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-8 cursor-zoom-out">
                <div class="max-w-[90vw] max-h-[90vh] flex flex-col gap-2" @click.stop>
                    <img :src="src" :alt="name" class="max-w-full max-h-[80vh] object-contain bg-white rounded-md">
                    <div class="text-white text-sm text-center" x-text="name"></div>
                </div>
            </div>
        </div>
        @endif

        {{-- ============ FORM (NEW/EDIT) ============ --}}
        @if ($panel === 'form')
            <div class="flex justify-between items-center bg-navy text-white px-4 py-3 rounded-t-md">
                <div class="text-sm font-bold">
                    {{ $mode === 'edit' ? t('Edit Product') : t('New Product') }}
                    @if ($mode === 'edit')<span class="text-xs font-normal opacity-75 ml-2">{{ $product_code }}</span>@endif
                </div>
            </div>
            <form wire:submit="save" class="bg-white border border-t-0 border-[#d7dbe2] p-5 flex flex-col gap-3">
                    @if ($lockedSpec)
                        <div class="text-xs px-3 py-2.5 rounded-md leading-relaxed bg-[#f1ecfc] border border-[#ddd0f7] text-[#6b4fa0]">
                            🔒 {!! t('This product is already <b>confirmed</b> — code & dimensions are locked. Revise the core spec via Inquiry REVISE, not by editing directly here.') !!}
                        </div>
                    @endif

                    <div class="text-xs font-bold text-navy uppercase tracking-wide">{{ t('Identity') }}</div>
                    <div class="flex gap-4">
                        {{-- Gambar tidak bisa diunggah dari sini: sumbernya Master Card
                             legacy (1 kartu dipakai banyak produk), jadi menggantinya
                             per-produk akan memutus jejak ke kartunya. --}}
                        <div class="shrink-0 w-[150px] flex flex-col gap-1.5">
                            <label class="text-xs font-semibold text-[#556]">{{ t('Master Card Image') }}</label>
                            @if ($image_path)
                                <a href="{{ asset('storage/'.$image_path) }}" target="_blank" rel="noopener" title="{{ t('Open full size') }}">
                                    <img src="{{ asset('storage/'.$image_path) }}" alt="{{ $product_name }}"
                                        class="w-[150px] h-[150px] object-contain rounded-md border border-[#d9dfe8] bg-white">
                                </a>
                                <div class="text-[10.5px] text-muted leading-snug">{{ t('Legacy Master Card') }} #{{ $image_source_card_id }}</div>
                            @else
                                <div class="w-[150px] h-[150px] rounded-md border border-dashed border-[#d9dfe8] bg-[#fafbfc] flex items-center justify-center text-[11px] text-muted text-center px-2">{{ t('No Master Card image') }}</div>
                            @endif
                        </div>

                        <div class="flex-1 flex flex-col gap-3">
                            <div class="flex gap-2.5">
                                <div class="flex-1">
                                    <x-form.input :label="t('Product Code *')" name="product_code" wire:model="product_code" :placeholder="t('e.g. FB-DUPLEX-001')" :disabled="$lockedSpec" class="font-mono" />
                                </div>
                                <div class="flex-[2]">
                                    <x-form.input :label="t('Product Name *')" name="product_name" wire:model="product_name" :placeholder="t('e.g. Cosmetic Duplex Folding Box')" />
                                </div>
                            </div>
                            <div class="flex gap-2.5">
                                <div class="flex-1">
                                    <x-form.input :label="t('Brand')" name="brand_name" wire:model="brand_name" :placeholder="t('e.g. KAYABA')" />
                                </div>
                                <div class="flex-1 flex flex-col gap-1">
                                    <label class="text-xs font-semibold text-[#556]">{{ t('Old Item Code') }}</label>
                                    <div class="px-2.5 py-2 border border-[#e3e7ec] rounded-md text-[13px] bg-[#f7f9fc] font-mono text-muted truncate">{{ $old_item_code ?: '—' }}</div>
                                </div>
                                <div class="flex-1 flex flex-col gap-1">
                                    <label class="text-xs font-semibold text-[#556]">{{ t('Purpose (legacy)') }}</label>
                                    <div class="px-2.5 py-2 border border-[#e3e7ec] rounded-md text-[13px] bg-[#f7f9fc] text-muted truncate">{{ $old_goods_purpose ?: '—' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2.5">
                        <div class="flex-1">
                            <x-form.select :label="t('Category')" name="category_id" wire:model="category_id"
                                :options="$this->categoryOptions->pluck('category_name', 'id_category')" :selected="$category_id" />
                        </div>
                        <div class="flex-1 flex flex-col gap-1 relative">
                            <label class="text-xs font-semibold text-[#556]">{{ t('Customer') }} <span class="text-muted font-normal">{{ t('(leave empty for a general stock item)') }}</span></label>
                            @if ($customer_id)
                                <div class="flex items-center gap-1.5 px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px] bg-[#f7f9fc]">
                                    <span class="flex-1 truncate">{{ $customer_label }}</span>
                                    <button type="button" wire:click="clearCustomer" class="text-muted hover:text-red-600 cursor-pointer" title="{{ t('Remove') }}">✕</button>
                                </div>
                            @else
                                <x-form.input :case-toggle="false" wire:model.live.debounce.300ms="custQuery" :placeholder="t('Search customer name / code…')" />
                                @if ($custQuery && mb_strlen($custQuery) >= 2)
                                    <div class="absolute top-full left-0 right-0 z-10 bg-white border border-[#c7cfda] rounded-md shadow-lg mt-1 max-h-[180px] overflow-auto">
                                        @forelse ($this->customerResults as $c)
                                            <div wire:click="selectCustomer({{ $c->id_customer }})" class="px-2.5 py-1.5 text-[12.5px] cursor-pointer hover:bg-[#eef3fb]">
                                                <div class="font-semibold">{{ $c->customer_name }}</div>
                                                <div class="text-[11px] text-muted font-mono">{{ $c->customer_code }}</div>
                                            </div>
                                        @empty
                                            <div class="px-2.5 py-1.5 text-[12.5px] text-muted">{{ t('Not found.') }}</div>
                                        @endforelse
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                    <x-form.textarea :label="t('Description')" wire:model="description" rows="2" :placeholder="t('Product notes/description')" />

                    <div class="text-xs font-bold text-navy uppercase tracking-wide mt-1">{{ t('Units & Dimensions') }}</div>
                    <div class="flex gap-2.5">
                        <div class="flex-1">
                            <x-form.select :label="t('Stock UoM')" wire:model="uom_stock" :options="$this->uomOptions->mapWithKeys(fn ($u) => [$u->uom_code => $u->uom_code.' — '.$u->uom_name])" />
                        </div>
                        <div class="flex-1">
                            <x-form.select :label="t('Sales UoM')" wire:model="uom_sales" :options="$this->uomOptions->mapWithKeys(fn ($u) => [$u->uom_code => $u->uom_code.' — '.$u->uom_name])" />
                        </div>
                        <div class="flex-1">
                            <x-form.input :label="t('Conversion to Stock')" name="conversion_to_stock" wire:model="conversion_to_stock" placeholder="1" />
                        </div>
                    </div>
                    <div class="text-[11px] text-muted -mt-1">{!! t('Dimensions are deliberately <b>not</b> taken from the old data: in <code>mst_item</code> only 10% of rows are filled and the units mix mm/cm without a marker, so it is safer to re-enter them when the product is used.') !!}</div>
                    <div class="flex gap-2.5">
                        <div class="flex-1">
                            <x-form.input :label="t('Length (cm)')" name="cm_length" wire:model="cm_length" :disabled="$lockedSpec" placeholder="0" />
                        </div>
                        <div class="flex-1">
                            <x-form.input :label="t('Width (cm)')" name="cm_width" wire:model="cm_width" :disabled="$lockedSpec" placeholder="0" />
                        </div>
                        <div class="flex-1">
                            <x-form.input :label="t('Height (cm)', 'master')" name="cm_height" wire:model="cm_height" :disabled="$lockedSpec" placeholder="0" />
                        </div>
                    </div>

                    <div class="text-xs font-bold text-navy uppercase tracking-wide mt-1">{{ t('Stock & Price') }}</div>
                    <div class="text-[11px] text-muted -mt-1">{{ t('Current stock') }} (<code>cur_stock</code>) {{ t('follows the Inventory ledger, not entered by hand here — only the min/max limits are set.') }}</div>
                    <div class="flex gap-2.5">
                        <div class="flex-1">
                            <x-form.input :label="t('Minimum Stock')" name="min_stock" wire:model="min_stock" placeholder="0" />
                        </div>
                        <div class="flex-1">
                            <x-form.input :label="t('Maximum Stock')" name="max_stock" wire:model="max_stock" placeholder="0" />
                        </div>
                    </div>
                    <div class="flex gap-2.5">
                        <div class="flex-1">
                            <x-form.input :label="t('Default Selling Price')" name="default_selling_price" wire:model="default_selling_price" placeholder="0" />
                        </div>
                        <div class="flex-1">
                            <x-form.input :label="t('Minimum Selling Price')" name="minimum_selling_price" wire:model="minimum_selling_price" placeholder="0" />
                        </div>
                    </div>

                    <x-form.checkbox :label="t('Confirmed (lock code & dimensions)')" wire:model="product_is_confirmed" />
                    <x-form.checkbox :label="t('Active')" wire:model="product_activate" />

                    <div class="flex gap-2 pt-3.5 -mx-5 -mb-5 px-4 py-3.5 bg-[#fafbfc] border-t border-[#d7dbe2] rounded-b-md">
                        <button type="submit" class="px-4 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer">{{ t('Save') }}</button>
                        <button type="button" wire:click="showGrid" class="px-4 py-2 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">{{ t('Cancel') }}</button>
                    </div>
            </form>
        @endif
    </div>
</div>
