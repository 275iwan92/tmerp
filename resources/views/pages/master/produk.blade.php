<?php

use App\Models\Master\CustCustomer;
use App\Models\Master\ProdCategory;
use App\Models\Master\ProdProduct;
use App\Models\Master\UomUnit;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

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
    public string $search = '';

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

    public bool $product_is_active = true;

    #[Computed]
    public function products()
    {
        return ProdProduct::query()
            ->with(['category', 'customer'])
            ->when($this->search !== '', fn ($q) => $q->where(fn ($qq) => $qq
                ->where('product_code', 'like', "%{$this->search}%")
                ->orWhere('product_name', 'like', "%{$this->search}%")))
            ->orderBy('product_name')
            ->get();
    }

    #[Computed]
    public function categoryOptions()
    {
        return ProdCategory::query()->where('is_active', true)->orderBy('category_name')->get();
    }

    #[Computed]
    public function uomOptions()
    {
        return UomUnit::query()->where('is_active', true)->orderBy('uom_code')->get();
    }

    #[Computed]
    public function customerResults()
    {
        if ($this->customer_id || mb_strlen($this->custQuery) < 2) {
            return collect();
        }

        return CustCustomer::query()
            ->where('is_active', true)
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
        return ProdProduct::where('is_active', true)->count();
    }

    #[Computed]
    public function statConfirmed(): int
    {
        return ProdProduct::where('is_confirmed', true)->count();
    }

    #[Computed]
    public function statInactive(): int
    {
        return ProdProduct::where('is_active', false)->count();
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
            $this->product_is_confirmed = (bool) $p->is_confirmed;
            $this->product_is_active = (bool) $p->is_active;
            $this->lockedSpec = (bool) $p->is_confirmed;
        } else {
            $this->reset([
                'product_code', 'product_name', 'category_id', 'customer_id', 'customer_label', 'custQuery',
                'description', 'uom_stock', 'uom_sales', 'qty_limit_min', 'qty_limit_max',
                'cm_length', 'cm_width', 'cm_height', 'min_stock', 'max_stock',
                'default_selling_price', 'minimum_selling_price',
            ]);
            $this->conversion_to_stock = '1';
            $this->product_is_confirmed = false;
            $this->product_is_active = true;
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
            'product_is_active' => ['boolean'],
        ]);

        $payload = [
            'product_code' => trim($data['product_code']),
            'product_name' => $data['product_name'],
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
            'is_confirmed' => $data['product_is_confirmed'],
            'is_active' => $data['product_is_active'],
            'updated_by' => Auth::user()->login,
        ];

        if ($this->mode === 'edit' && $this->editingId) {
            ProdProduct::whereKey($this->editingId)->update($payload);
        } else {
            $payload['created_by'] = Auth::user()->login;
            ProdProduct::create($payload);
        }

        unset($this->products, $this->statTotal, $this->statActive, $this->statConfirmed, $this->statInactive);
        $this->panel = 'grid';
    }

    public function toggleActive(int $id): void
    {
        $p = ProdProduct::findOrFail($id);
        $p->is_active = ! $p->is_active;
        $p->updated_by = Auth::user()->login;
        $p->save();

        unset($this->products, $this->statTotal, $this->statActive, $this->statConfirmed, $this->statInactive);
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
    <div class="max-w-[1500px] mx-auto my-5 px-4">

        {{-- ============ GRID (LIST) ============ --}}
        @if ($panel === 'grid')
        <div class="grid gap-3 mb-3.5">
            <div class="rounded-lg p-3.5 shadow-sm bg-[#eaf2ff] border border-[#c8ddfb]">
                <p class="text-[10.5px] font-bold uppercase text-[#3b5b99]">Total Produk</p>
                <p class="text-xl font-extrabold text-[#1d4ed8] mt-0.5">{{ $this->statTotal }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#e9f9ef] border border-[#c8ecd6]">
                <p class="text-[10.5px] font-bold uppercase text-[#2f7a52]">Aktif</p>
                <p class="text-xl font-extrabold text-[#16a34a] mt-0.5">{{ $this->statActive }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#f1ecfc] border border-[#ddd0f7]">
                <p class="text-[10.5px] font-bold uppercase text-[#6b4fa0]">Terkonfirmasi</p>
                <p class="text-xl font-extrabold text-[#6d28d9] mt-0.5">{{ $this->statConfirmed }}</p>
                <p class="text-[10px] text-[#6b4fa0]/70">SPEC TERKUNCI</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#fdecec] border border-[#f5c6c6]">
                <p class="text-[10.5px] font-bold uppercase text-[#a3453f]">Nonaktif</p>
                <p class="text-xl font-extrabold text-[#c0392b] mt-0.5">{{ $this->statInactive }}</p>
            </div>
        </div>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <div class="flex items-center gap-2 bg-white border-b border-[#e2e7ee] px-2.5 py-2 overflow-x-auto">
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari kode / nama produk…"
                        class="w-56 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <span class="flex-1"></span>
                <button type="button" wire:click="openModal('new')"
                    class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
            </div>

            <div class="max-h-[65vh] overflow-auto">
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Produk</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[140px]">Kategori</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[170px]">Customer</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[130px]">Dimensi (cm)</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[150px]">Status</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[80px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->products as $p)
                            <tr wire:key="prod-{{ $p->id_product }}" wire:dblclick="openModal('edit', {{ $p->id_product }})" class="hover:bg-[#f7faff] cursor-pointer" title="Klik 2x untuk buka detail">
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-md bg-gradient-to-br {{ $this->avatarGradient($p->id_product) }} text-white text-[11px] font-bold flex items-center justify-center shrink-0">{{ $this->avatarInitials($p->product_name ?: $p->product_code) }}</div>
                                        <div>
                                            <div class="font-semibold text-[13px]">{{ $p->product_name ?: '—' }}</div>
                                            <div class="text-[11px] text-muted font-mono">{{ $p->product_code }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $p->category->category_name ?? '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $p->customer->customer_name ?? '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] font-mono">
                                    @if ($p->cm_length || $p->cm_width || $p->cm_height)
                                        {{ rtrim(rtrim(number_format($p->cm_length, 1), '0'), '.') }}×{{ rtrim(rtrim(number_format($p->cm_width, 1), '0'), '.') }}×{{ rtrim(rtrim(number_format($p->cm_height, 1), '0'), '.') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <div class="flex flex-wrap gap-1">
                                        @if ($p->is_confirmed)
                                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#f1ecfc] text-[#6d28d9]">TERKUNCI</span>
                                        @endif
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block {{ $p->is_active ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#eef0f3] text-[#8a8f98]' }}">{{ $p->is_active ? 'AKTIF' : 'NONAKTIF' }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <button type="button" wire:click.stop="openModal('edit', {{ $p->id_product }})" title="Edit" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">✎</button>
                                    <button type="button" wire:click.stop="toggleActive({{ $p->id_product }})" wire:confirm="{{ $p->is_active ? 'Nonaktifkan' : 'Aktifkan' }} produk {{ $p->product_name }}?"
                                        title="{{ $p->is_active ? 'Nonaktifkan' : 'Aktifkan' }}" class="inline-flex items-center justify-center w-7 h-7 rounded-md cursor-pointer {{ $p->is_active ? 'text-[#c0392b] hover:bg-[#fdeceb]' : 'text-accent hover:bg-[#eef3fb]' }}">⏻</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-8 text-muted text-sm">Tidak ada produk.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- ============ FORM (NEW/EDIT) ============ --}}
        @if ($panel === 'form')
            <div class="flex justify-between items-center bg-navy text-white px-4 py-3 rounded-t-md">
                <div class="text-sm font-bold">
                    {{ $mode === 'edit' ? 'Edit Produk' : 'Produk Baru' }}
                    @if ($mode === 'edit')<span class="text-xs font-normal opacity-75 ml-2">{{ $product_code }}</span>@endif
                </div>
            </div>
            <form wire:submit="save" class="bg-white border border-t-0 border-[#d7dbe2] p-5 flex flex-col gap-3">
                    @if ($lockedSpec)
                        <div class="text-xs px-3 py-2.5 rounded-md leading-relaxed bg-[#f1ecfc] border border-[#ddd0f7] text-[#6b4fa0]">
                            🔒 Produk ini sudah <b>terkonfirmasi</b> — kode &amp; dimensi terkunci. Revisi spec inti lewat Inquiry REVISE, bukan edit langsung di sini.
                        </div>
                    @endif

                    <div class="text-xs font-bold text-navy uppercase tracking-wide">Identitas</div>
                    <div class="flex gap-2.5">
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Kode Produk *</label>
                            <input type="text" wire:model="product_code" placeholder="mis. FB-DUPLEX-001" @disabled($lockedSpec) class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px] font-mono">
                            @error('product_code') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex-[2] flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Nama Produk *</label>
                            <input type="text" wire:model="product_name" placeholder="mis. Folding Box Duplex Kosmetik" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                            @error('product_name') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="flex gap-2.5">
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Kategori</label>
                            <select wire:model="category_id" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                                <option value="">— pilih —</option>
                                @foreach ($this->categoryOptions as $c)
                                    <option value="{{ $c->id_category }}">{{ $c->category_name }}</option>
                                @endforeach
                            </select>
                            @error('category_id') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex-1 flex flex-col gap-1 relative">
                            <label class="text-xs font-semibold text-[#556]">Customer <span class="text-muted font-normal">(kosongkan jika stock item umum)</span></label>
                            @if ($customer_id)
                                <div class="flex items-center gap-1.5 px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px] bg-[#f7f9fc]">
                                    <span class="flex-1 truncate">{{ $customer_label }}</span>
                                    <button type="button" wire:click="clearCustomer" class="text-muted hover:text-red-600 cursor-pointer" title="Hapus">✕</button>
                                </div>
                            @else
                                <input type="text" wire:model.live.debounce.300ms="custQuery" placeholder="Cari nama / kode customer…" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                                @if ($custQuery && mb_strlen($custQuery) >= 2)
                                    <div class="absolute top-full left-0 right-0 z-10 bg-white border border-[#c7cfda] rounded-md shadow-lg mt-1 max-h-[180px] overflow-auto">
                                        @forelse ($this->customerResults as $c)
                                            <div wire:click="selectCustomer({{ $c->id_customer }})" class="px-2.5 py-1.5 text-[12.5px] cursor-pointer hover:bg-[#eef3fb]">
                                                <div class="font-semibold">{{ $c->customer_name }}</div>
                                                <div class="text-[11px] text-muted font-mono">{{ $c->customer_code }}</div>
                                            </div>
                                        @empty
                                            <div class="px-2.5 py-1.5 text-[12.5px] text-muted">Tidak ditemukan.</div>
                                        @endforelse
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-[#556]">Deskripsi</label>
                        <textarea wire:model="description" rows="2" placeholder="Catatan/deskripsi produk" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]"></textarea>
                    </div>

                    <div class="text-xs font-bold text-navy uppercase tracking-wide mt-1">Satuan &amp; Dimensi</div>
                    <div class="flex gap-2.5">
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">UoM Stok</label>
                            <select wire:model="uom_stock" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                                <option value="">— pilih —</option>
                                @foreach ($this->uomOptions as $u)
                                    <option value="{{ $u->uom_code }}">{{ $u->uom_code }} — {{ $u->uom_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">UoM Jual</label>
                            <select wire:model="uom_sales" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                                <option value="">— pilih —</option>
                                @foreach ($this->uomOptions as $u)
                                    <option value="{{ $u->uom_code }}">{{ $u->uom_code }} — {{ $u->uom_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Konversi ke Stok</label>
                            <input type="text" wire:model="conversion_to_stock" placeholder="1" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                            @error('conversion_to_stock') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="flex gap-2.5">
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Panjang (cm)</label>
                            <input type="text" wire:model="cm_length" @disabled($lockedSpec) placeholder="0" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                            @error('cm_length') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Lebar (cm)</label>
                            <input type="text" wire:model="cm_width" @disabled($lockedSpec) placeholder="0" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                            @error('cm_width') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Tinggi (cm)</label>
                            <input type="text" wire:model="cm_height" @disabled($lockedSpec) placeholder="0" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                            @error('cm_height') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="text-xs font-bold text-navy uppercase tracking-wide mt-1">Stok &amp; Harga</div>
                    <div class="text-[11px] text-muted -mt-1">Stok saat ini (<code>cur_stock</code>) mengikuti ledger Inventory, bukan diisi manual di sini — hanya batas min/max yang diatur.</div>
                    <div class="flex gap-2.5">
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Stok Minimum</label>
                            <input type="text" wire:model="min_stock" placeholder="0" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                            @error('min_stock') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Stok Maksimum</label>
                            <input type="text" wire:model="max_stock" placeholder="0" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                            @error('max_stock') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="flex gap-2.5">
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Harga Jual Default</label>
                            <input type="text" wire:model="default_selling_price" placeholder="0" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                            @error('default_selling_price') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Harga Jual Minimum</label>
                            <input type="text" wire:model="minimum_selling_price" placeholder="0" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                            @error('minimum_selling_price') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-[13px] text-[#334]">
                        <input type="checkbox" wire:model="product_is_confirmed"> Sudah dikonfirmasi (kunci kode &amp; dimensi)
                    </label>
                    <label class="flex items-center gap-2 text-[13px] text-[#334]">
                        <input type="checkbox" wire:model="product_is_active"> Aktif
                    </label>

                    <div class="flex gap-2 pt-3.5 -mx-5 -mb-5 px-4 py-3.5 bg-[#fafbfc] border-t border-[#d7dbe2] rounded-b-md">
                        <button type="submit" class="px-4 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer">Simpan</button>
                        <button type="button" wire:click="showGrid" class="px-4 py-2 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">Batal</button>
                    </div>
            </form>
        @endif
    </div>
</div>
