<?php

use App\Livewire\Concerns\HasGridStatFilter;
use App\Models\Master\FinPaymentTerm;
use App\Models\Master\SupCategory;
use App\Models\Master\SupSupplier;
use App\Models\Master\SupSupplierBranch;
use App\Models\Master\SupSupplierContact;
use App\Services\Master\SupplierGuardService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use App\Livewire\Concerns\OpensFromQuery;
use Livewire\Component;

// Pola sama persis dengan pages::master.customer (grid/form/detail) — lihat
// file itu untuk komentar arsitektur lebih lengkap. sup_supplier: lihat
// migration 2026_08_18_000005_create_sup_supplier_table.php.
new #[Title('Master Supplier — TELASIH MAKMUR ERP')] class extends Component
{
    use OpensFromQuery;

    protected function openFromQuery(int $id): void
    {
        $this->editRow($id);
    }

    protected function newFromQuery(): void
    {
        $this->newSupplier();
    }

    use HasGridStatFilter;

    #[Url]
    public string $q = '';

    public string $panel = 'grid'; // grid | form | detail

    public ?int $selectedId = null;

    public ?int $editingId = null;

    // Form dipecah jadi tab (bukan 1 panel panjang) supaya muat 1 layar tanpa
    // scroll saat editing. identitas | alamat | kontak | termin.
    public string $formTab = 'identitas';

    // ---- field form (mengikuti sup_supplier, migration 2026_08_18_000005) ----
    public string $supplier_code = '';

    public string $supplier_name = '';

    public ?string $category = null;

    public bool $is_subcon = false;

    public string $contact_name = '';

    public string $default_address = '';

    public ?int $default_area_id = null;

    public ?string $default_area_level = null;

    public ?string $address_label = null;

    public string $postal_code = '';

    public string $email = '';

    public string $phone = '';

    public string $phone_2 = '';

    public string $fax = '';

    public string $website = '';

    public ?string $payment_term = null;

    public string $tax_code = '';

    public string $tax_purchase_value = '0';

    public string $tax_service_value = '0';

    public bool $activate = true;

    public bool $is_blacklisted = false;

    public string $note = '';

    public ?string $actionMessage = null;

    // ---- Cabang tambahan (sup_supplier_branch) — default_address/dst di atas
    // tetap jadi alamat utama, ini cuma lokasi TAMBAHAN. ----
    public bool $showBranchModal = false;

    public string $branchMode = 'new'; // new | edit

    public ?int $editingBranchId = null;

    public string $branch_name = '';

    public string $branch_address = '';

    public ?int $branch_area_id = null;

    public ?string $branch_area_level = null;

    public ?string $branch_address_label = null;

    public string $branch_postal_code = '';

    public string $branch_phone = '';

    public bool $branch_is_default = false;

    // ---- Kontak tambahan (sup_supplier_contact) — contact_name/dst di atas
    // tetap jadi PIC utama, ini cuma kontak TAMBAHAN. ----
    public bool $showContactModal = false;

    public string $contactMode = 'new'; // new | edit

    public ?int $editingContactId = null;

    public string $c_contact_name = '';

    public string $c_position = '';

    public string $c_phone = '';

    public string $c_email = '';

    public bool $c_is_default = false;

    public string $c_note = '';

    #[Computed]
    public function suppliers()
    {
        return SupSupplier::query()
            ->with('paymentTerm')
            ->when($this->q, function ($query) {
                $query->where(function ($query) {
                    $query->where('supplier_name', 'like', "%{$this->q}%")
                        ->orWhere('supplier_code', 'like', "%{$this->q}%")
                        ->orWhere('contact_name', 'like', "%{$this->q}%");
                });
            })
            ->tap(fn ($q) => $this->applyStatFilter($q))
            ->orderBy('supplier_name')
            ->limit(200)
            ->get();
    }

    #[Computed]
    public function selectedSupplier(): ?SupSupplier
    {
        if (! $this->selectedId) {
            return null;
        }

        return SupSupplier::query()->with(['branches', 'contacts'])->find($this->selectedId);
    }

    #[Computed]
    public function paymentTerms()
    {
        return FinPaymentTerm::query()->where('activate', true)->orderBy('sort_order')->get();
    }

    #[Computed]
    public function categories()
    {
        return SupCategory::query()->where('activate', true)->orderBy('category_code')->get();
    }

    // Sama pola dengan Master Customer — lihat komentar di customer.blade.php.
    #[On('address-picked')]
    public function onAddressPicked(string $fieldName, ?int $areaId, ?string $areaLevel, ?string $label, ?string $postalCode): void
    {
        if ($fieldName === 'supplier-branch-address') {
            $this->branch_area_id = $areaId;
            $this->branch_area_level = $areaLevel;
            $this->branch_address_label = $label;
            $this->branch_postal_code = $postalCode ?? '';

            return;
        }

        if ($fieldName !== 'supplier-address') {
            return;
        }

        $this->default_area_id = $areaId;
        $this->default_area_level = $areaLevel;
        $this->address_label = $label;
        $this->postal_code = $postalCode ?? '';
    }

    // ============== CABANG (sup_supplier_branch) ==============
    public function openBranchModal(string $mode, ?int $id = null): void
    {
        $this->resetErrorBag();
        $this->branchMode = in_array($mode, ['new', 'edit'], true) ? $mode : 'new';
        $this->editingBranchId = $id;

        if ($this->branchMode === 'edit' && $id) {
            $b = SupSupplierBranch::findOrFail($id);
            $this->branch_name = (string) $b->branch_name;
            $this->branch_address = (string) $b->address;
            $this->branch_area_id = $b->area_id;
            $this->branch_area_level = $b->area_level;
            $this->branch_address_label = null;
            $this->branch_postal_code = (string) $b->postal_code;
            $this->branch_phone = (string) $b->phone;
            $this->branch_is_default = (bool) $b->is_default;
        } else {
            $this->reset(['branch_name', 'branch_address', 'branch_area_id', 'branch_area_level', 'branch_address_label', 'branch_postal_code', 'branch_phone']);
            $this->branch_is_default = false;
        }

        $this->showBranchModal = true;
    }

    public function closeBranchModal(): void
    {
        $this->showBranchModal = false;
    }

    public function saveBranch(): void
    {
        if (! $this->selectedId) {
            return;
        }

        $this->validate([
            'branch_name' => ['nullable', 'string', 'max:100'],
            'branch_address' => ['nullable', 'string'],
            'branch_area_id' => ['nullable', 'integer'],
            'branch_area_level' => ['nullable', 'in:CITY,DISTRICT,VILLAGE'],
            'branch_postal_code' => ['nullable', 'string', 'max:20'],
            'branch_phone' => ['nullable', 'string', 'max:50'],
            'branch_is_default' => ['boolean'],
        ]);

        $payload = [
            'supplier_id' => $this->selectedId,
            'branch_name' => $this->branch_name,
            'address' => $this->branch_address,
            'area_id' => $this->branch_area_id,
            'area_level' => $this->branch_area_level,
            'postal_code' => $this->branch_postal_code,
            'phone' => $this->branch_phone,
            'is_default' => $this->branch_is_default,
            'updated_by' => Auth::user()->login,
        ];

        if ($this->branchMode === 'edit' && $this->editingBranchId) {
            SupSupplierBranch::whereKey($this->editingBranchId)->update($payload);
            $savedBranchId = $this->editingBranchId;
        } else {
            $payload['created_by'] = Auth::user()->login;
            $savedBranchId = SupSupplierBranch::create($payload)->id_supplier_branch;
        }

        if ($this->branch_is_default) {
            SupSupplierBranch::where('supplier_id', $this->selectedId)
                ->whereKeyNot($savedBranchId)
                ->update(['is_default' => false]);
        }

        unset($this->selectedSupplier);
        $this->showBranchModal = false;
    }

    public function deleteBranch(int $id): void
    {
        SupSupplierBranch::whereKey($id)->delete();
        unset($this->selectedSupplier);
    }

    public function setDefaultBranch(int $id): void
    {
        if (! $this->selectedId) {
            return;
        }

        SupSupplierBranch::where('supplier_id', $this->selectedId)->update(['is_default' => false]);
        SupSupplierBranch::whereKey($id)->update(['is_default' => true]);
        unset($this->selectedSupplier);
    }

    // ============== KONTAK TAMBAHAN (sup_supplier_contact) ==============
    public function openContactModal(string $mode, ?int $id = null): void
    {
        $this->resetErrorBag();
        $this->contactMode = in_array($mode, ['new', 'edit'], true) ? $mode : 'new';
        $this->editingContactId = $id;

        if ($this->contactMode === 'edit' && $id) {
            $c = SupSupplierContact::findOrFail($id);
            $this->c_contact_name = (string) $c->contact_name;
            $this->c_position = (string) $c->position;
            $this->c_phone = (string) $c->phone;
            $this->c_email = (string) $c->email;
            $this->c_is_default = (bool) $c->is_default;
            $this->c_note = (string) $c->note;
        } else {
            $this->reset(['c_contact_name', 'c_position', 'c_phone', 'c_email', 'c_note']);
            $this->c_is_default = false;
        }

        $this->showContactModal = true;
    }

    public function closeContactModal(): void
    {
        $this->showContactModal = false;
    }

    public function saveContact(): void
    {
        if (! $this->selectedId) {
            return;
        }

        $this->validate([
            'c_contact_name' => ['required', 'string', 'max:200'],
            'c_position' => ['nullable', 'string', 'max:100'],
            'c_phone' => ['nullable', 'string', 'max:50'],
            'c_email' => ['nullable', 'email', 'max:150'],
            'c_is_default' => ['boolean'],
            'c_note' => ['nullable', 'string'],
        ]);

        $payload = [
            'supplier_id' => $this->selectedId,
            'contact_name' => $this->c_contact_name,
            'position' => $this->c_position,
            'phone' => $this->c_phone,
            'email' => $this->c_email,
            'is_default' => $this->c_is_default,
            'note' => $this->c_note,
            'updated_by' => Auth::user()->login,
        ];

        if ($this->contactMode === 'edit' && $this->editingContactId) {
            SupSupplierContact::whereKey($this->editingContactId)->update($payload);
            $savedContactId = $this->editingContactId;
        } else {
            $payload['created_by'] = Auth::user()->login;
            $savedContactId = SupSupplierContact::create($payload)->id_supplier_contact;
        }

        if ($this->c_is_default) {
            SupSupplierContact::where('supplier_id', $this->selectedId)
                ->whereKeyNot($savedContactId)
                ->update(['is_default' => false]);
        }

        unset($this->selectedSupplier);
        $this->showContactModal = false;
    }

    public function deleteContact(int $id): void
    {
        SupSupplierContact::whereKey($id)->delete();
        unset($this->selectedSupplier);
    }

    public function setDefaultContact(int $id): void
    {
        if (! $this->selectedId) {
            return;
        }

        SupSupplierContact::where('supplier_id', $this->selectedId)->update(['is_default' => false]);
        SupSupplierContact::whereKey($id)->update(['is_default' => true]);
        unset($this->selectedSupplier);
    }

    public function updatedQ(): void
    {
        $this->selectedId = null;
    }

    public function selectRow(int $id): void
    {
        $this->selectedId = $id;
        $this->actionMessage = null;
    }

    // Quick-edit: icon pensil per baris / double-click baris -> langsung buka
    // form edit baris itu, tanpa perlu select-lalu-scroll-ke-toolbar dulu.
    public function editRow(int $id): void
    {
        $this->selectedId = $id;
        $this->editSupplier();
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

    public function newSupplier(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->formTab = 'identitas';
        $this->panel = 'form';
    }

    public function editSupplier(): void
    {
        if (! $this->selectedId) {
            return;
        }

        $supplier = SupSupplier::findOrFail($this->selectedId);
        $this->editingId = $supplier->id_supplier;
        $this->formTab = 'identitas';
        $this->supplier_code = (string) $supplier->supplier_code;
        $this->supplier_name = (string) $supplier->supplier_name;
        $this->category = $supplier->category;
        $this->is_subcon = (bool) $supplier->is_subcon;
        $this->contact_name = (string) $supplier->contact_name;
        $this->default_address = (string) $supplier->default_address;
        $this->default_area_id = $supplier->default_area_id;
        $this->default_area_level = $supplier->default_area_level;
        $this->address_label = null;
        $this->postal_code = (string) $supplier->postal_code;
        $this->email = (string) $supplier->email;
        $this->phone = (string) $supplier->phone;
        $this->phone_2 = (string) $supplier->phone_2;
        $this->fax = (string) $supplier->fax;
        $this->website = (string) $supplier->website;
        $this->payment_term = $supplier->payment_term;
        $this->tax_code = (string) $supplier->tax_code;
        $this->tax_purchase_value = (string) $supplier->tax_purchase_value;
        $this->tax_service_value = (string) $supplier->tax_service_value;
        $this->activate = (bool) $supplier->activate;
        $this->is_blacklisted = (bool) $supplier->is_blacklisted;
        $this->note = (string) $supplier->note;
        $this->panel = 'form';
    }

    public function setFormTab(string $tab): void
    {
        if (in_array($tab, ['identitas', 'alamat', 'kontak', 'termin'], true)) {
            $this->formTab = $tab;
        }
    }

    // Tandai tab yang punya field error tapi sedang tidak aktif dilihat,
    // supaya user tahu ada yang perlu diperbaiki di tab lain.
    public function tabHasError(string $tab): bool
    {
        $fields = match ($tab) {
            'identitas' => ['supplier_code', 'supplier_name', 'category', 'tax_code'],
            'alamat' => ['default_address', 'default_area_id', 'default_area_level', 'postal_code'],
            'kontak' => ['contact_name', 'phone', 'phone_2', 'fax', 'email', 'website'],
            'termin' => ['payment_term', 'tax_purchase_value', 'tax_service_value'],
            default => [],
        };

        return collect($fields)->contains(fn ($f) => $this->getErrorBag()->has($f));
    }

    public function resetForm(): void
    {
        $this->reset([
            'supplier_code', 'supplier_name', 'category', 'contact_name',
            'default_address', 'default_area_id', 'default_area_level', 'address_label',
            'postal_code', 'email', 'phone', 'phone_2', 'fax', 'website', 'payment_term',
            'tax_code', 'note',
        ]);
        $this->tax_purchase_value = '0';
        $this->tax_service_value = '0';
        $this->is_subcon = false;
        $this->activate = true;
        $this->is_blacklisted = false;
    }

    public function save(): void
    {
        $unique = 'unique:tm2mst.sup_supplier,supplier_code';
        if ($this->editingId) {
            $unique .= ','.$this->editingId.',id_supplier';
        }

        try {
            $data = $this->validate([
                'supplier_code' => ['required', 'string', 'max:50', $unique],
                'supplier_name' => ['required', 'string', 'max:200'],
                'category' => ['nullable', 'string', 'exists:tm2mst.sup_category,category_code'],
                'is_subcon' => ['boolean'],
                'contact_name' => ['nullable', 'string', 'max:200'],
                'default_address' => ['nullable', 'string'],
                'default_area_id' => ['nullable', 'integer'],
                'default_area_level' => ['nullable', 'in:CITY,DISTRICT,VILLAGE'],
                'postal_code' => ['nullable', 'string', 'max:20'],
                'email' => ['nullable', 'email', 'max:150'],
                'phone' => ['nullable', 'string', 'max:50'],
                'phone_2' => ['nullable', 'string', 'max:50'],
                'fax' => ['nullable', 'string', 'max:20'],
                'website' => ['nullable', 'string', 'max:150'],
                'payment_term' => ['nullable', 'string', 'exists:tm2mst.fin_payment_term,payment_term_code'],
                'tax_code' => ['nullable', 'string', 'max:25'],
                'tax_purchase_value' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'tax_service_value' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'activate' => ['boolean'],
                'is_blacklisted' => ['boolean'],
                'note' => ['nullable', 'string'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
            foreach (['identitas', 'alamat', 'kontak', 'termin'] as $t) {
                if ($this->tabHasError($t)) {
                    $this->formTab = $t;
                    break;
                }
            }
            throw $e;
        }

        $data['updated_by'] = Auth::user()->login;

        if ($this->editingId) {
            SupSupplier::whereKey($this->editingId)->update($data);
            $this->selectedId = $this->editingId;
        } else {
            $data['created_by'] = Auth::user()->login;
            $supplier = SupSupplier::create($data);
            $this->selectedId = $supplier->id_supplier;
        }

        unset($this->suppliers);
        $this->panel = 'grid';
    }

    public function toggleActive(int $id): void
    {
        $s = SupSupplier::findOrFail($id);
        $s->activate = ! $s->activate;
        $s->updated_by = Auth::user()->login;
        $s->save();

        unset($this->suppliers, $this->metricActive);
    }

    #[Computed]
    public function metricTotal(): int
    {
        return SupSupplier::count();
    }

    #[Computed]
    public function metricActive(): int
    {
        return SupSupplier::where('activate', true)->count();
    }

    #[Computed]
    public function metricBlocked(): int
    {
        return SupSupplier::where('is_blacklisted', true)->count();
    }

    #[Computed]
    public function metricSubcon(): int
    {
        return SupSupplier::where('is_subcon', true)->count();
    }

    // Componentization grid+form (2026-08-27): avatarInitials()/avatarGradient()
    // dipindah ke App\Support\Grid\Avatar (dipakai <x-grid.identity-cell>).

    // Hapus permanen HANYA kalau tidak ada transaksi (SupplierGuardService —
    // saat ini selalu boleh, modul Purchasing belum ada; lihat komentar di
    // service-nya untuk kapan ini akan mulai memblokir).
    public function deleteSupplier(): void
    {
        if (! $this->selectedId) {
            return;
        }

        $supplier = SupSupplier::findOrFail($this->selectedId);
        $reasons = app(SupplierGuardService::class)->blockingReasons($this->selectedId);

        if ($reasons !== []) {
            $supplier->update(['activate' => false, 'updated_by' => Auth::user()->login]);
            $this->actionMessage = str_replace(':refs', implode(' & ', $reasons), t('Cannot be deleted — still used by :refs. The supplier has been deactivated instead.'));
        } else {
            $supplier->delete();
            $this->selectedId = null;
            $this->panel = 'grid';
            $this->actionMessage = t('Supplier permanently deleted.');
        }

        unset($this->suppliers);
    }

    public function toggleBlacklist(): void
    {
        if (! $this->selectedId) {
            return;
        }

        $supplier = SupSupplier::findOrFail($this->selectedId);
        $supplier->update(['is_blacklisted' => ! $supplier->is_blacklisted, 'updated_by' => Auth::user()->login]);

        $this->actionMessage = $supplier->is_blacklisted ? t('Supplier blocked.') : t('Supplier unblocked.');
        unset($this->suppliers);
    }

    // Kotak statistik = filter data (griduistandard.md §2b).
    protected function statFilterDefs(): array
    {
        return [
            'total' => ['label' => t('Total Suppliers'), 'value' => $this->metricTotal, 'tone' => 'total'],
            'active' => ['label' => t('Active'), 'value' => $this->metricActive, 'tone' => 'active',
                'filter' => fn ($q) => $q->where('activate', true)],
            'subcon' => ['label' => t('Subcontractor'), 'value' => $this->metricSubcon, 'tone' => 'extra',
                'filter' => fn ($q) => $q->where('is_subcon', true)],
            'blocked' => ['label' => t('Blocked'), 'value' => $this->metricBlocked, 'tone' => 'critical',
                'filter' => fn ($q) => $q->where('is_blacklisted', true)],
        ];
    }
};
?>

<div>

    <div class="max-w-[1180px] mx-auto my-5 px-4">

        {{-- ============ GRID (LIST) ============ --}}
        @if ($panel === 'grid')
            <x-grid.stat-filter-row :boxes="$this->statBoxes()" :active="$statFilter" />

            <x-grid.toolbar class="!border !border-[#d7dbe2] !border-b-0 rounded-t-md">
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.400ms="q" placeholder="{{ t('Search name / code / PIC...') }}"
                        class="w-56 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <x-slot:newRecord>
                    <button type="button" wire:click="newSupplier"
                        class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
                </x-slot:newRecord>
            </x-grid.toolbar>

            @if ($actionMessage)
                <div class="text-xs px-3 py-2 border border-t-0 border-[#d7dbe2] bg-[#fff8e1] text-[#8a6d00]">{{ $actionMessage }}</div>
            @endif

            <div class="bg-white border border-t-0 border-[#d7dbe2] rounded-b-md overflow-hidden">
                <x-grid.table-shell>
                <table class="w-full border-collapse">
                    <thead>
                        <tr>
                            <th class="text-left bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide px-2.5 py-2 border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('Supplier') }}</th>
                            <th class="text-left bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide px-2.5 py-2 border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('Category') }}</th>
                            <th class="text-left bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide px-2.5 py-2 border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('PIC Contact') }}</th>
                            <th class="text-left bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide px-2.5 py-2 border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('Payment Terms') }}</th>
                            <th class="text-left bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide px-2.5 py-2 border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('Status') }}</th>
                            <th class="w-10 bg-[#fafbfd] border-b border-[#eef1f5] sticky top-0 z-[1]"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->suppliers as $s)
                            <tr wire:click="selectRow({{ $s->id_supplier }})" wire:dblclick="editRow({{ $s->id_supplier }})"
                                class="cursor-pointer hover:bg-[#f8f9fb] {{ $selectedId === $s->id_supplier ? 'bg-[#e8f0fe]' : '' }}"
                                title="{{ t('Double-click to open detail') }}">
                                <x-grid.identity-cell :id="$s->id_supplier" :name="$s->supplier_name" :code="$s->supplier_code" class="!border-b !border-[#d7dbe2]" />
                                <td class="px-2.5 py-2 border-b border-[#d7dbe2]">{{ $s->category ?: '—' }}</td>
                                <td class="px-2.5 py-2 border-b border-[#d7dbe2]">{{ $s->contact_name ?: '—' }}</td>
                                <td class="px-2.5 py-2 border-b border-[#d7dbe2]">{{ $s->paymentTerm?->term_label ?? '—' }}</td>
                                <td class="px-2.5 py-2 border-b border-[#d7dbe2]">
                                    <span class="text-[10.5px] font-bold px-2.5 py-0.5 rounded-full inline-block {{ $s->activate ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#eef0f3] text-[#8a8f98]' }}">
                                        {{ $s->activate ? t('Active') : t('Inactive') }}
                                    </span>
                                    @if ($s->is_blacklisted)
                                        <span class="text-[10.5px] font-bold px-2.5 py-0.5 rounded-full inline-block bg-[#fdeceb] text-[#c0392b] ml-1">🚫 {{ t('Blocked') }}</span>
                                    @endif
                                </td>
                                <td class="text-right px-1 py-2 border-b border-[#d7dbe2] whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-pen" title="{{ t('Edit') }}" action="editRow({{ $s->id_supplier }})" />
                                    <x-grid.action-icon :active="$s->activate" :title="$s->activate ? t('Deactivate') : t('Activate')" action="toggleActive({{ $s->id_supplier }})"
                                        :confirm="str_replace(':name', $s->supplier_name, $s->activate ? t('Deactivate supplier :name?') : t('Activate supplier :name?'))"
                                        :variant="$s->activate ? 'danger' : 'default'" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="6" :message="t('No supplier data yet').($q ? t(' for this search') : '').'.'" />
                        @endforelse
                    </tbody>
                </table>
                </x-grid.table-shell>
            </div>
        @endif

        {{-- ============ FORM (NEW/EDIT) ============ --}}
        @if ($panel === 'form')
            <div class="flex justify-between items-center bg-[#2c3e69] text-white px-4 py-3 rounded-t-md">
                <div class="text-sm font-bold">
                    {{ $editingId ? t('Edit Supplier') : t('New Supplier') }}
                    @if ($editingId)<span class="text-xs font-normal opacity-75 ml-2">{{ $supplier_code }}</span>@endif
                </div>
            </div>
            <div class="flex gap-1 bg-[#fafbfc] border-x border-b border-[#d7dbe2] px-3 pt-2">
                @foreach (['identitas' => t('Identity'), 'alamat' => t('Address'), 'kontak' => t('PIC Contact'), 'termin' => t('Terms & Tax')] as $tabKey => $tabLabel)
                    <button type="button" wire:click="setFormTab('{{ $tabKey }}')"
                        class="px-3.5 py-2 text-xs font-semibold rounded-t-md border border-b-0 cursor-pointer relative -mb-px
                            {{ $formTab === $tabKey ? 'bg-white border-[#d7dbe2] text-[#2c3e69]' : 'bg-transparent border-transparent text-[#6b7280] hover:text-[#2c3e69]' }}">
                        {{ $tabLabel }}
                        @if ($this->tabHasError($tabKey))
                            <span class="inline-block w-1.5 h-1.5 rounded-full bg-red-600 ml-1 align-middle"></span>
                        @endif
                    </button>
                @endforeach
            </div>
            <form wire:submit="save" novalidate class="bg-white border border-t-0 border-[#d7dbe2] p-5">
                <div class="{{ $formTab === 'identitas' ? '' : 'hidden' }}">
                    <h3 class="text-xs text-[#2c3e69] uppercase tracking-wide mb-2.5 border-b border-[#d7dbe2] pb-1.5">{{ t('Identity') }}</h3>
                    <div class="[display:grid] grid-cols-3 gap-3">
                        <x-form.input :label="t('Supplier Code')" name="supplier_code" wire:model="supplier_code" maxlength="50" />
                        <div class="col-span-2">
                            <x-form.input :label="t('Supplier Name')" name="supplier_name" wire:model="supplier_name" maxlength="200" />
                        </div>
                        <x-form.select :label="t('Category')" name="category" wire:model="category" :options="$this->categories->pluck('category_code', 'category_code')" :selected="$category" />
                        <x-form.input :label="t('NPWP')" wire:model="tax_code" maxlength="25" placeholder="00.000.000.0-000.000" />
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ t('Status') }}</label>
                            <x-form.checkbox :label="t('Active')" wire:model="activate" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ t('Block') }}</label>
                            <x-form.checkbox :label="'🚫 '.t('Block this supplier (problematic)')" wire:model="is_blacklisted" label-class="text-[#c0392b]" />
                        </div>
                        <div class="col-span-3">
                            <x-form.textarea :label="t('Notes')" wire:model="note" rows="2" :placeholder="t('e.g. reason for blocking, history of quality/delivery issues, etc.')" />
                        </div>
                    </div>
                </div>

                <div class="{{ $formTab === 'alamat' ? '' : 'hidden' }}">
                    <h3 class="text-xs text-[#2c3e69] uppercase tracking-wide mb-2.5 border-b border-[#d7dbe2] pb-1.5">{{ t('Address') }}</h3>
                    <div class="[display:grid] grid-cols-2 gap-3">
                        <div class="col-span-2">
                            <x-form.textarea :label="t('Full Address')" wire:model="default_address" rows="2" :placeholder="t('Street, number, RT/RW, landmark')" />
                        </div>
                        <div class="flex flex-col gap-1 col-span-2">
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">
                                {{ t('Region') }} <span class="text-[9.5px] font-bold px-1.5 py-0.5 rounded-lg ml-1 bg-[#fff8e1] text-[#b98900] normal-case">◆ {{ t('type village / district / city / province / postal code') }}</span>
                            </label>
                            <livewire:address-picker
                                field-name="supplier-address"
                                :initial-area-id="$default_area_id"
                                :initial-area-level="$default_area_level"
                                :initial-label="$address_label"
                                wire:key="addr-picker-{{ $editingId ?? 'new' }}" />
                        </div>
                        <x-form.input :label="t('Postal Code')" wire:model="postal_code" maxlength="20" :placeholder="t('Auto from region, can be changed')" />
                    </div>
                </div>

                <div class="{{ $formTab === 'kontak' ? '' : 'hidden' }}">
                    <h3 class="text-xs text-[#2c3e69] uppercase tracking-wide mb-2.5 border-b border-[#d7dbe2] pb-1.5">{{ t('PIC Contact') }}</h3>
                    <div class="[display:grid] grid-cols-3 gap-3">
                        <x-form.input :label="t('PIC Name')" wire:model="contact_name" maxlength="200" />
                        <x-form.input :label="t('Phone No.', 'master')" wire:model="phone" maxlength="50" />
                        <x-form.input :label="t('Phone 2')" wire:model="phone_2" maxlength="50" />
                        <x-form.input :label="t('Fax')" wire:model="fax" maxlength="20" />
                        <x-form.input :label="t('Email')" name="email" type="email" wire:model="email" maxlength="150" />
                        <x-form.input :label="t('Website')" wire:model="website" maxlength="150" />
                    </div>
                </div>

                <div class="{{ $formTab === 'termin' ? '' : 'hidden' }}">
                    <h3 class="text-xs text-[#2c3e69] uppercase tracking-wide mb-2.5 border-b border-[#d7dbe2] pb-1.5">{{ t('Terms & Tax') }}</h3>
                    <div class="[display:grid] grid-cols-3 gap-3">
                        <x-form.select :label="t('Payment Terms')" name="payment_term" wire:model="payment_term"
                            :options="$this->paymentTerms->pluck('term_label', 'payment_term_code')" :selected="$payment_term" />
                        <x-form.input :label="t('Purchase Tax Rate (%)')" type="number" step="0.01" min="0" max="100" wire:model="tax_purchase_value" />
                        <x-form.input :label="t('Service Tax Rate (%)')" type="number" step="0.01" min="0" max="100" wire:model="tax_service_value" />
                    </div>
                </div>

                <div class="flex gap-2 pt-3.5 -mx-5 -mb-5 px-4 py-3.5 bg-[#fafbfc] border-t border-[#d7dbe2] rounded-b-md">
                    <button type="submit" class="px-4 py-2 rounded text-xs font-semibold bg-[#2f6fed] text-white border border-[#2f6fed] cursor-pointer">{{ t('Save') }}</button>
                    <button type="button" wire:click="showGrid" data-dirty-guard class="px-4 py-2 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">{{ t('Cancel') }}</button>
                </div>
            </form>
        @endif

        {{-- ============ DETAIL ============ --}}
        @if ($panel === 'detail' && $this->selectedSupplier)
            @php($d = $this->selectedSupplier)
            <div class="flex justify-between items-start bg-[#2c3e69] text-white px-5 py-4.5 rounded-t-md">
                <div>
                    <div class="text-base font-bold">{{ $d->supplier_name }} @if ($d->is_blacklisted)<span class="text-[10.5px] font-bold px-2.5 py-0.5 rounded-full inline-block bg-[#fdeceb] text-[#c0392b] align-middle ml-2">🚫 {{ t('Blocked', 'master') }}</span>@endif</div>
                    <div class="text-xs opacity-75 mt-1">{{ $d->supplier_code }} · {{ $d->category ?: t('category not filled in') }} · {{ $d->paymentTerm?->term_label ?? t('terms not filled in') }}</div>
                </div>
            </div>
            @if ($actionMessage)
                <div class="text-xs px-3.5 py-2 border-x border-[#d7dbe2] bg-[#fff8e1] text-[#8a6d00]">{{ $actionMessage }}</div>
            @endif
            <div class="bg-white border border-t-0 border-[#d7dbe2] rounded-b-md p-4.5">
                <div class="text-xs text-[#8a94a6] bg-[#fafbfc] border border-dashed border-[#d7dbe2] rounded-md px-3.5 py-3">
                    {{ t('Purchase Order history is not available yet — the Purchasing module (tm2pur) has not been built.') }} {{ t('This section will show the PO list per supplier once that module exists.') }}
                </div>
                @if ($d->note)
                    <div class="text-xs text-[#8a6d00] bg-[#fff8e1] border border-dashed border-[#e6d38a] rounded-md px-3.5 py-3 mt-3">
                        <div class="text-[10.5px] uppercase font-bold mb-1">{{ t('Notes') }}</div>{{ $d->note }}
                    </div>
                @endif
                <div class="[display:grid] grid-cols-2 gap-3 mt-4">
                    <div class="border border-[#d7dbe2] rounded-md p-3">
                        <div class="flex items-center mb-2">
                            <div class="text-[10.5px] text-[#8a94a6] uppercase">{{ t('Contact') }}</div>
                            <span class="flex-1"></span>
                            <button type="button" wire:click="openContactModal('new')" class="text-[11px] font-semibold text-[#2f6fed] cursor-pointer">+ {{ t('Add') }}</button>
                        </div>
                        <div class="flex items-start justify-between gap-2 py-1.5 border-b border-[#f0f2f5]">
                            <div>
                                <div class="text-xs font-semibold">{{ $d->contact_name ?: '—' }} <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-[#e8f0fe] text-[#2f6fed] ml-1">{{ t('PRIMARY') }}</span></div>
                                <div class="text-xs text-[#6b7280]">{{ $d->phone ?: '—' }} · {{ $d->email ?: '—' }}</div>
                            </div>
                        </div>
                        @foreach ($d->contacts as $ct)
                            <div class="flex items-start justify-between gap-2 py-1.5 border-b border-[#f0f2f5] last:border-b-0">
                                <div>
                                    <div class="text-xs font-semibold">{{ $ct->contact_name }}{{ $ct->position ? ' · '.$ct->position : '' }}
                                        @if ($ct->is_default)<span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-[#eaf7ee] text-[#1f8a4c] ml-1">{{ t('DEFAULT') }}</span>@endif
                                    </div>
                                    <div class="text-xs text-[#6b7280]">{{ $ct->phone ?: '—' }} · {{ $ct->email ?: '—' }}</div>
                                </div>
                                <div class="flex gap-1 shrink-0">
                                    @unless ($ct->is_default)
                                        <button type="button" wire:click="setDefaultContact({{ $ct->id_supplier_contact }})" title="{{ t('Set as default') }}" class="w-6 h-6 rounded border border-[#d7dbe2] text-[10px] cursor-pointer">★</button>
                                    @endunless
                                    <button type="button" wire:click="openContactModal('edit', {{ $ct->id_supplier_contact }})" title="{{ t('Edit') }}" class="w-6 h-6 rounded border border-[#d7dbe2] text-[10px] cursor-pointer">✎</button>
                                    <button type="button" wire:click="deleteContact({{ $ct->id_supplier_contact }})" wire:confirm="{{ str_replace(':name', $ct->contact_name, t('Delete contact :name?')) }}" title="{{ t('Delete') }}" class="w-6 h-6 rounded border border-[#f0c4c4] text-[#c0392b] text-[10px] cursor-pointer">🗑</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="border border-[#d7dbe2] rounded-md p-3">
                        <div class="flex items-center mb-2">
                            <div class="text-[10.5px] text-[#8a94a6] uppercase">{{ t('Address') }}</div>
                            <span class="flex-1"></span>
                            <button type="button" wire:click="openBranchModal('new')" class="text-[11px] font-semibold text-[#2f6fed] cursor-pointer">+ {{ t('Add Branch') }}</button>
                        </div>
                        <div class="py-1.5 border-b border-[#f0f2f5]">
                            <div class="text-xs font-semibold">{{ t('Head Office') }} <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-[#e8f0fe] text-[#2f6fed] ml-1">{{ t('PRIMARY') }}</span></div>
                            <div class="text-xs">{{ $d->default_address ?: '—' }}</div>
                            <div class="text-xs text-[#6b7280] mt-0.5">{{ $d->postal_code ?: '—' }}</div>
                        </div>
                        @foreach ($d->branches as $br)
                            <div class="flex items-start justify-between gap-2 py-1.5 border-b border-[#f0f2f5] last:border-b-0">
                                <div>
                                    <div class="text-xs font-semibold">{{ $br->branch_name ?: t('Branch') }}
                                        @if ($br->is_default)<span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-[#eaf7ee] text-[#1f8a4c] ml-1">{{ t('DEFAULT') }}</span>@endif
                                    </div>
                                    <div class="text-xs">{{ $br->address ?: '—' }}</div>
                                    <div class="text-xs text-[#6b7280] mt-0.5">{{ $br->phone ?: '—' }}{{ $br->postal_code ? ' · '.$br->postal_code : '' }}</div>
                                </div>
                                <div class="flex gap-1 shrink-0">
                                    @unless ($br->is_default)
                                        <button type="button" wire:click="setDefaultBranch({{ $br->id_supplier_branch }})" title="{{ t('Set as default') }}" class="w-6 h-6 rounded border border-[#d7dbe2] text-[10px] cursor-pointer">★</button>
                                    @endunless
                                    <button type="button" wire:click="openBranchModal('edit', {{ $br->id_supplier_branch }})" title="{{ t('Edit') }}" class="w-6 h-6 rounded border border-[#d7dbe2] text-[10px] cursor-pointer">✎</button>
                                    <button type="button" wire:click="deleteBranch({{ $br->id_supplier_branch }})" wire:confirm="{{ $br->branch_name ? str_replace(':name', $br->branch_name, t('Delete branch :name?')) : t('Delete this branch?') }}" title="{{ t('Delete') }}" class="w-6 h-6 rounded border border-[#f0c4c4] text-[#c0392b] text-[10px] cursor-pointer">🗑</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="flex gap-2 mt-3">
                <button type="button" wire:click="showGrid" data-dirty-guard class="px-4 py-2 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">← {{ t('Back to List') }}</button>
                <button type="button" wire:click="editSupplier" class="px-4 py-2 rounded text-xs font-semibold bg-[#2f6fed] text-white border border-[#2f6fed] cursor-pointer">{{ t('Edit This Supplier') }}</button>
                <button type="button" wire:click="toggleBlacklist" wire:confirm="{{ t('Change the block status of this supplier?') }}" class="px-4 py-2 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">{{ $d->is_blacklisted ? '✅ '.t('Unblock') : '🚫 '.t('Block') }}</button>
                <button type="button" wire:click="deleteSupplier" wire:confirm="{{ t('Delete this supplier? If it is still used by transactions, it will only be deactivated.') }}" class="px-4 py-2 rounded text-xs font-semibold bg-white border border-[#f0c4c4] text-[#c0392b] cursor-pointer">🗑 {{ t('Delete') }}</button>
            </div>
        @endif

        {{-- ══ MODAL: KONTAK TAMBAHAN ══ --}}
        @if ($showContactModal)
            <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="closeContactModal">
                <div class="bg-white rounded-xl w-[420px] max-w-[92vw] shadow-2xl">
                    <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold flex items-center gap-2">
                        <span>{{ $contactMode === 'edit' ? t('Edit Contact') : t('New Contact') }}</span>
                        <span class="ml-auto cursor-pointer opacity-85 hover:opacity-100" wire:click="closeContactModal">✕</span>
                    </div>
                    <form wire:submit="saveContact" novalidate class="p-4 flex flex-col gap-3">
                        <x-form.input :label="t('Contact Name *')" name="c_contact_name" wire:model="c_contact_name" />
                        <x-form.input :label="t('Role')" wire:model="c_position" />
                        <div class="flex gap-2.5">
                            <div class="flex-1">
                                <x-form.input :label="t('Phone')" wire:model="c_phone" />
                            </div>
                            <div class="flex-1">
                                <x-form.input :label="t('Email')" name="c_email" type="email" wire:model="c_email" />
                            </div>
                        </div>
                        <x-form.textarea :label="t('Notes')" wire:model="c_note" rows="2" />
                        <x-form.checkbox :label="t('Set as default contact')" wire:model="c_is_default" />
                        <div class="flex gap-2 justify-end pt-2 border-t border-[#eef1f5] mt-1">
                            <button type="button" wire:click="closeContactModal" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">{{ t('Cancel') }}</button>
                            <button type="submit" class="px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">{{ t('Save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- ══ MODAL: CABANG TAMBAHAN ══ --}}
        @if ($showBranchModal)
            <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="closeBranchModal">
                <div class="bg-white rounded-xl w-[480px] max-w-[92vw] shadow-2xl">
                    <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold flex items-center gap-2">
                        <span>{{ $branchMode === 'edit' ? t('Edit Branch') : t('New Branch') }}</span>
                        <span class="ml-auto cursor-pointer opacity-85 hover:opacity-100" wire:click="closeBranchModal">✕</span>
                    </div>
                    <form wire:submit="saveBranch" novalidate class="p-4 flex flex-col gap-3">
                        <x-form.input :label="t('Branch Name')" wire:model="branch_name" :placeholder="t('e.g. Surabaya Warehouse')" />
                        <x-form.textarea :label="t('Address')" wire:model="branch_address" rows="2" />
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ t('Region') }}</label>
                            <livewire:address-picker
                                field-name="supplier-branch-address"
                                :initial-area-id="$branch_area_id"
                                :initial-area-level="$branch_area_level"
                                :initial-label="$branch_address_label"
                                wire:key="branch-addr-picker-{{ $editingBranchId ?? 'new' }}" />
                        </div>
                        <div class="flex gap-2.5">
                            <div class="flex-1">
                                <x-form.input :label="t('Postal Code')" wire:model="branch_postal_code" />
                            </div>
                            <div class="flex-1">
                                <x-form.input :label="t('Phone')" wire:model="branch_phone" />
                            </div>
                        </div>
                        <x-form.checkbox :label="t('Set as default branch')" wire:model="branch_is_default" />
                        <div class="flex gap-2 justify-end pt-2 border-t border-[#eef1f5] mt-1">
                            <button type="button" wire:click="closeBranchModal" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">{{ t('Cancel') }}</button>
                            <button type="submit" class="px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">{{ t('Save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

    </div>
</div>
