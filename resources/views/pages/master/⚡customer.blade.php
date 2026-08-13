<?php

use App\Models\Master\CustCustomer;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Master Customer — TELASIH MAKMUR ERP')] class extends Component
{
    #[Url]
    public string $q = '';

    public string $panel = 'grid'; // grid | form | detail

    public ?int $selectedId = null;

    public ?int $editingId = null;

    // ---- field form (mengikuti cust_customer asli, docs/tm2mst.sql) ----
    public string $customer_code = '';

    public string $customer_name = '';

    public string $tax_code = '';

    public string $mr_ms = '';

    public string $contact_name = '';

    public string $default_address = '';

    public ?int $default_area_id = null;

    public ?string $default_area_level = null;

    public ?string $address_label = null;

    public string $postal_code = '';

    public string $email = '';

    public string $phone = '';

    public string $website = '';

    public string $customer_type = 'local';

    public string $payment_term = '';

    public string $currency_code = 'IDR';

    public string $credit_limit = '0';

    public bool $is_prospect = false;

    public bool $is_active = true;

    #[Computed]
    public function customers()
    {
        return CustCustomer::query()
            ->when($this->q, function ($query) {
                $query->where(function ($query) {
                    $query->where('customer_name', 'like', "%{$this->q}%")
                        ->orWhere('customer_code', 'like', "%{$this->q}%")
                        ->orWhere('contact_name', 'like', "%{$this->q}%");
                });
            })
            ->orderBy('customer_name')
            ->limit(200)
            ->get();
    }

    #[Computed]
    public function selectedCustomer(): ?CustCustomer
    {
        if (! $this->selectedId) {
            return null;
        }

        return CustCustomer::query()->find($this->selectedId);
    }

    // Diisi AddressPicker lewat event `address-picked` (docs/FIX_MASTER_CUSTOMER_NAV_ADDRESS.md §B.3) —
    // bukan wire:model biasa karena 1x pilih wilayah menghasilkan 3 nilai sekaligus
    // (areaId + areaLevel + postalCode), sedangkan wire:model cuma bind 1 properti.
    #[On('address-picked')]
    public function onAddressPicked(string $fieldName, ?int $areaId, ?string $areaLevel, ?string $label, ?string $postalCode): void
    {
        if ($fieldName !== 'customer-address') {
            return;
        }

        $this->default_area_id = $areaId;
        $this->default_area_level = $areaLevel;
        $this->address_label = $label;
        $this->postal_code = $postalCode ?? '';
    }

    public function updatedQ(): void
    {
        $this->selectedId = null;
    }

    public function selectRow(int $id): void
    {
        $this->selectedId = $id;
    }

    public function showGrid(): void
    {
        $this->panel = 'grid';
    }

    public function showDetail(): void
    {
        if (! $this->selectedId) {
            return;
        }

        $this->panel = 'detail';
    }

    public function newCustomer(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->panel = 'form';
    }

    public function editCustomer(): void
    {
        if (! $this->selectedId) {
            return;
        }

        $customer = CustCustomer::findOrFail($this->selectedId);
        $this->editingId = $customer->id_customer;
        $this->customer_code = (string) $customer->customer_code;
        $this->customer_name = (string) $customer->customer_name;
        $this->tax_code = (string) $customer->tax_code;
        $this->mr_ms = (string) $customer->mr_ms;
        $this->contact_name = (string) $customer->contact_name;
        $this->default_address = (string) $customer->default_address;
        $this->default_area_id = $customer->default_area_id;
        $this->default_area_level = $customer->default_area_level;
        $this->address_label = $customer->addressLabel();
        $this->postal_code = (string) $customer->postal_code;
        $this->email = (string) $customer->email;
        $this->phone = (string) $customer->phone;
        $this->website = (string) $customer->website;
        $this->customer_type = $customer->customer_type ?? 'local';
        $this->payment_term = (string) $customer->payment_term;
        $this->currency_code = $customer->currency_code ?? 'IDR';
        $this->credit_limit = (string) $customer->credit_limit;
        $this->is_prospect = (bool) $customer->is_prospect;
        $this->is_active = (bool) $customer->is_active;
        $this->panel = 'form';
    }

    public function resetForm(): void
    {
        $this->reset([
            'customer_code', 'customer_name', 'tax_code', 'mr_ms', 'contact_name',
            'default_address', 'default_area_id', 'default_area_level', 'address_label',
            'postal_code', 'email', 'phone', 'website', 'payment_term', 'credit_limit',
        ]);
        $this->customer_type = 'local';
        $this->currency_code = 'IDR';
        $this->is_prospect = false;
        $this->is_active = true;
    }

    public function save(): void
    {
        $unique = 'unique:tm2mst.cust_customer,customer_code';
        if ($this->editingId) {
            $unique .= ','.$this->editingId.',id_customer';
        }

        $data = $this->validate([
            'customer_code' => ['required', 'string', 'max:50', $unique],
            'customer_name' => ['required', 'string', 'max:200'],
            'tax_code' => ['nullable', 'string', 'max:50'],
            'mr_ms' => ['nullable', 'string', 'max:5'],
            'contact_name' => ['nullable', 'string', 'max:200'],
            'default_address' => ['nullable', 'string'],
            'default_area_id' => ['nullable', 'integer'],
            'default_area_level' => ['nullable', 'in:CITY,DISTRICT,VILLAGE'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:150'],
            'customer_type' => ['required', 'in:local,import'],
            'payment_term' => ['nullable', 'string', 'max:50'],
            'currency_code' => ['nullable', 'string', 'max:10'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'is_prospect' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $data['updated_by'] = Auth::user()->login;

        if ($this->editingId) {
            CustCustomer::whereKey($this->editingId)->update($data);
            $this->selectedId = $this->editingId;
        } else {
            $data['created_by'] = Auth::user()->login;
            $customer = CustCustomer::create($data);
            $this->selectedId = $customer->id_customer;
        }

        unset($this->customers);
        $this->panel = 'grid';
    }
};
?>

<div>
    <header class="bg-[#2c3e69] text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">📇 TELASIH MAKMUR — Master Customer</b>
        <span class="text-xs text-white/75">tm2mst.cust_customer</span>
        <a href="{{ route('menu') }}" class="ml-auto bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md no-underline hover:brightness-110">← Menu Utama</a>
    </header>

    <div class="max-w-[1180px] mx-auto my-5 px-4">

        {{-- ============ GRID (LIST) ============ --}}
        @if ($panel === 'grid')
            <div class="flex items-center gap-2 bg-white border border-[#d7dbe2] rounded-t-md px-2.5 py-2 flex-wrap">
                <div class="flex items-center gap-1.5 flex-1 max-w-[340px]">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.400ms="q" placeholder="Cari nama / kode / PIC..."
                        class="flex-1 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <button type="button" wire:click="newCustomer"
                    class="flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">＋ New</button>
                <button type="button" wire:click="editCustomer" @disabled(! $selectedId)
                    class="flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">✎ Edit</button>
                <button type="button" wire:click="showDetail" @disabled(! $selectedId)
                    class="flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">☰ Detail</button>
                <div class="flex-1"></div>
                <div class="text-xs text-[#6b7280] px-2">{{ $this->customers->count() }} baris</div>
            </div>

            <div class="bg-white border border-t-0 border-[#d7dbe2] rounded-b-md overflow-hidden">
                <table class="w-full border-collapse">
                    <thead>
                        <tr>
                            <th class="w-8"></th>
                            <th class="text-left bg-[#f4f5f8] text-[#6b7280] text-[10.5px] uppercase tracking-wide px-2.5 py-2 border-b border-[#d7dbe2]">Kode</th>
                            <th class="text-left bg-[#f4f5f8] text-[#6b7280] text-[10.5px] uppercase tracking-wide px-2.5 py-2 border-b border-[#d7dbe2]">Nama Customer</th>
                            <th class="text-left bg-[#f4f5f8] text-[#6b7280] text-[10.5px] uppercase tracking-wide px-2.5 py-2 border-b border-[#d7dbe2]">Tipe</th>
                            <th class="text-left bg-[#f4f5f8] text-[#6b7280] text-[10.5px] uppercase tracking-wide px-2.5 py-2 border-b border-[#d7dbe2]">Wilayah</th>
                            <th class="text-left bg-[#f4f5f8] text-[#6b7280] text-[10.5px] uppercase tracking-wide px-2.5 py-2 border-b border-[#d7dbe2]">Kontak PIC</th>
                            <th class="text-left bg-[#f4f5f8] text-[#6b7280] text-[10.5px] uppercase tracking-wide px-2.5 py-2 border-b border-[#d7dbe2]">Termin</th>
                            <th class="text-left bg-[#f4f5f8] text-[#6b7280] text-[10.5px] uppercase tracking-wide px-2.5 py-2 border-b border-[#d7dbe2]">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->customers as $c)
                            <tr wire:click="selectRow({{ $c->id_customer }})"
                                class="cursor-pointer hover:bg-[#f8f9fb] {{ $selectedId === $c->id_customer ? 'bg-[#e8f0fe]' : '' }}">
                                <td class="text-center px-2.5 py-2 border-b border-[#d7dbe2]">
                                    <input type="radio" @checked($selectedId === $c->id_customer) class="scale-110">
                                </td>
                                <td class="px-2.5 py-2 border-b border-[#d7dbe2]">{{ $c->customer_code }}</td>
                                <td class="px-2.5 py-2 border-b border-[#d7dbe2] font-semibold">{{ $c->customer_name }}</td>
                                <td class="px-2.5 py-2 border-b border-[#d7dbe2]">{{ $c->customer_type === 'import' ? 'Import' : 'Lokal' }}{{ $c->is_prospect ? ' · Prospek' : '' }}</td>
                                <td class="px-2.5 py-2 border-b border-[#d7dbe2] max-w-[220px] truncate" title="{{ $c->addressLabel() }}">{{ $c->addressLabel() ?? '—' }}</td>
                                <td class="px-2.5 py-2 border-b border-[#d7dbe2]">{{ $c->contact_name ?: '—' }}</td>
                                <td class="px-2.5 py-2 border-b border-[#d7dbe2]">{{ $c->payment_term ?: '—' }}</td>
                                <td class="px-2.5 py-2 border-b border-[#d7dbe2]">
                                    <span class="text-[10.5px] font-bold px-2.5 py-0.5 rounded-full inline-block {{ $c->is_active ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#eef0f3] text-[#8a8f98]' }}">
                                        {{ $c->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-8 text-[#8a94a6] text-sm">
                                    Belum ada data customer{{ $q ? ' untuk pencarian ini' : '' }}.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        {{-- ============ FORM (NEW/EDIT) ============ --}}
        @if ($panel === 'form')
            <div class="flex justify-between items-center bg-[#2c3e69] text-white px-4 py-3 rounded-t-md">
                <div class="text-sm font-bold">
                    {{ $editingId ? 'Edit Customer' : 'Customer Baru' }}
                    @if ($editingId)<span class="text-xs font-normal opacity-75 ml-2">{{ $customer_code }}</span>@endif
                </div>
            </div>
            <form wire:submit="save" class="bg-white border border-t-0 border-[#d7dbe2] p-5">
                <div class="mb-4.5">
                    <h3 class="text-xs text-[#2c3e69] uppercase tracking-wide mb-2.5 border-b border-[#d7dbe2] pb-1.5">Identitas</h3>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="flex flex-col gap-1">
                            <label class="text-[10.5px] font-semibold text-[#6b7280] uppercase">Kode Customer</label>
                            <input type="text" wire:model="customer_code" maxlength="50"
                                class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                            @error('customer_code') <span class="text-[10.5px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1 col-span-2">
                            <label class="text-[10.5px] font-semibold text-[#6b7280] uppercase">Nama Customer</label>
                            <input type="text" wire:model="customer_name" maxlength="200"
                                class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                            @error('customer_name') <span class="text-[10.5px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10.5px] font-semibold text-[#6b7280] uppercase">Tipe</label>
                            <select wire:model="customer_type" class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                                <option value="local">Lokal</option>
                                <option value="import">Import</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10.5px] font-semibold text-[#6b7280] uppercase">NPWP</label>
                            <input type="text" wire:model="tax_code" maxlength="50" placeholder="00.000.000.0-000.000"
                                class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10.5px] font-semibold text-[#6b7280] uppercase">Status</label>
                            <label class="flex items-center gap-2 px-2.5 py-1.5 text-xs">
                                <input type="checkbox" wire:model="is_active"> Aktif
                            </label>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10.5px] font-semibold text-[#6b7280] uppercase">Prospek</label>
                            <label class="flex items-center gap-2 px-2.5 py-1.5 text-xs">
                                <input type="checkbox" wire:model="is_prospect"> Calon pelanggan (belum resmi)
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mb-4.5">
                    <h3 class="text-xs text-[#2c3e69] uppercase tracking-wide mb-2.5 border-b border-[#d7dbe2] pb-1.5">Alamat</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1 col-span-2">
                            <label class="text-[10.5px] font-semibold text-[#6b7280] uppercase">Alamat Lengkap</label>
                            <textarea wire:model="default_address" rows="2" placeholder="Jalan, nomor, RT/RW, patokan"
                                class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs"></textarea>
                        </div>
                        <div class="flex flex-col gap-1 col-span-2">
                            <label class="text-[10.5px] font-semibold text-[#6b7280] uppercase">
                                Wilayah <span class="text-[9.5px] font-bold px-1.5 py-0.5 rounded-lg ml-1 bg-[#fff8e1] text-[#b98900] normal-case">◆ ketik kelurahan / kecamatan / kota / provinsi / kode pos</span>
                            </label>
                            <livewire:address-picker
                                field-name="customer-address"
                                :initial-area-id="$default_area_id"
                                :initial-area-level="$default_area_level"
                                :initial-label="$address_label"
                                wire:key="addr-picker-{{ $editingId ?? 'new' }}" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10.5px] font-semibold text-[#6b7280] uppercase">Kode Pos</label>
                            <input type="text" wire:model="postal_code" maxlength="20" placeholder="Auto dari wilayah, bisa diubah"
                                class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        </div>
                    </div>
                </div>

                <div class="mb-4.5">
                    <h3 class="text-xs text-[#2c3e69] uppercase tracking-wide mb-2.5 border-b border-[#d7dbe2] pb-1.5">Kontak PIC</h3>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="flex flex-col gap-1">
                            <label class="text-[10.5px] font-semibold text-[#6b7280] uppercase">Sapaan</label>
                            <select wire:model="mr_ms" class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                                <option value="">—</option>
                                <option value="Bapak">Bapak</option>
                                <option value="Ibu">Ibu</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10.5px] font-semibold text-[#6b7280] uppercase">Nama PIC</label>
                            <input type="text" wire:model="contact_name" maxlength="200" class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10.5px] font-semibold text-[#6b7280] uppercase">No. Telepon</label>
                            <input type="text" wire:model="phone" maxlength="50" class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10.5px] font-semibold text-[#6b7280] uppercase">Email</label>
                            <input type="email" wire:model="email" maxlength="150" class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                            @error('email') <span class="text-[10.5px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10.5px] font-semibold text-[#6b7280] uppercase">Website</label>
                            <input type="text" wire:model="website" maxlength="150" class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        </div>
                    </div>
                </div>

                <div class="mb-4.5">
                    <h3 class="text-xs text-[#2c3e69] uppercase tracking-wide mb-2.5 border-b border-[#d7dbe2] pb-1.5">Termin &amp; Mata Uang</h3>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="flex flex-col gap-1">
                            <label class="text-[10.5px] font-semibold text-[#6b7280] uppercase">Termin Bayar</label>
                            <input type="text" wire:model="payment_term" maxlength="50" placeholder="mis. NET 30"
                                class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10.5px] font-semibold text-[#6b7280] uppercase">Mata Uang</label>
                            <input type="text" wire:model="currency_code" maxlength="10" placeholder="IDR"
                                class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10.5px] font-semibold text-[#6b7280] uppercase">Limit Kredit</label>
                            <input type="number" step="0.01" min="0" wire:model="credit_limit"
                                class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 pt-3.5 -mx-5 -mb-5 px-4 py-3.5 bg-[#fafbfc] border-t border-[#d7dbe2] rounded-b-md">
                    <button type="submit" class="px-4 py-2 rounded text-xs font-semibold bg-[#2f6fed] text-white border border-[#2f6fed] cursor-pointer">Simpan</button>
                    <button type="button" wire:click="showGrid" class="px-4 py-2 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">Batal</button>
                </div>
            </form>
        @endif

        {{-- ============ DETAIL ============ --}}
        @if ($panel === 'detail' && $this->selectedCustomer)
            @php($d = $this->selectedCustomer)
            <div class="flex justify-between items-start bg-[#2c3e69] text-white px-5 py-4.5 rounded-t-md">
                <div>
                    <div class="text-base font-bold">{{ $d->customer_name }}</div>
                    <div class="text-xs opacity-75 mt-1">{{ $d->customer_code }} · {{ $d->customer_type === 'import' ? 'Import' : 'Lokal' }} · {{ $d->addressLabel() ?? 'wilayah belum diisi' }} · {{ $d->payment_term ?: 'termin belum diisi' }}</div>
                </div>
            </div>
            <div class="bg-white border border-t-0 border-[#d7dbe2] rounded-b-md p-4.5">
                <div class="text-xs text-[#8a94a6] bg-[#fafbfc] border border-dashed border-[#d7dbe2] rounded-md px-3.5 py-3">
                    Riwayat Customer Order belum tersedia — modul Sales Order (co_order) belum dibangun. Bagian ini akan menampilkan daftar CO per customer begitu modul tersebut ada.
                </div>
                <div class="grid grid-cols-2 gap-3 mt-4">
                    <div class="border border-[#d7dbe2] rounded-md p-3">
                        <div class="text-[10.5px] text-[#8a94a6] uppercase mb-1">Kontak</div>
                        <div class="text-xs">{{ $d->mr_ms }} {{ $d->contact_name ?: '—' }}</div>
                        <div class="text-xs text-[#6b7280]">{{ $d->phone ?: '—' }} · {{ $d->email ?: '—' }}</div>
                    </div>
                    <div class="border border-[#d7dbe2] rounded-md p-3">
                        <div class="text-[10.5px] text-[#8a94a6] uppercase mb-1">Alamat</div>
                        <div class="text-xs">{{ $d->default_address ?: '—' }}</div>
                        <div class="text-xs text-[#6b7280] mt-1">{{ $d->addressLabel() ?? 'wilayah belum diisi' }}{{ $d->postal_code ? ' · '.$d->postal_code : '' }}</div>
                    </div>
                </div>
            </div>
            <div class="flex gap-2 mt-3">
                <button type="button" wire:click="showGrid" class="px-4 py-2 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">← Kembali ke Daftar</button>
                <button type="button" wire:click="editCustomer" class="px-4 py-2 rounded text-xs font-semibold bg-[#2f6fed] text-white border border-[#2f6fed] cursor-pointer">Edit Customer Ini</button>
            </div>
        @endif

    </div>
</div>
