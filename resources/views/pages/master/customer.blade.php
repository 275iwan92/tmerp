<?php

use App\Models\Master\CustCategory;
use App\Models\Master\CustCustomer;
use App\Models\Master\CustCustomerBranch;
use App\Models\Master\CustCustomerContact;
use App\Models\Master\FinPaymentTerm;
use App\Models\Master\OrgDivision;
use App\Support\Localization\Translator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

// mockup_master_customer.html (v2, 2026-08-19). Redesign dari versi tab
// identitas/alamat/kontak/accounting -> 1 layar bersatu (grid <-> form, tanpa
// panel "detail" terpisah lagi — form SEKARANG merangkap tampil+edit, sesuai
// struktur mockup v2 & rekomendasi tab yang sudah disetujui Jo sebelumnya,
// lihat memory project_pending_ui_feedback_20260818). Tab baru: Printing
// Specs, Sales & Finance (delta dari Accounting lama), Sites & Contacts
// (gabungan Alamat+Kontak+branches/contacts lama), Governance, History
// (timeline ringkas dari created_at/updated_at, BUKAN tabel audit generik —
// tak ada tabelnya). ~22 kolom baru di cust_customer, lihat migration
// 2026_08_19_000019. "Sales Organization"/"Distribution Channel" mockup
// SENGAJA tak dibawa (customer_spec.md §1.5 "SAP-isme tak relevan
// percetakan" — satu-satunya bagian dokumen lama itu yang masih dipakai).
new #[Title('Master Customer — TELASIH MAKMUR ERP')] class extends Component
{
    #[Url]
    public string $q = '';

    public string $panel = 'grid'; // grid | form

    public ?int $selectedId = null;

    public ?int $editingId = null;

    public string $formTab = 'overview';

    // ---- field form: Overview ----
    public string $customer_code = '';

    public string $customer_name = '';

    public string $trade_name = '';

    public string $parent_group = '';

    public string $industry_category = '';

    public string $tax_code = '';

    public string $customer_type = 'local';

    public ?string $category = null;

    public bool $is_prospect = false;

    public bool $activate = true;

    public bool $is_blacklisted = false;

    public string $approval_status = 'DRAFT';

    public string $note = '';

    // ---- field form: Printing Specs ----
    public string $primary_product = '';

    public string $secondary_product = '';

    public string $default_substrate = '';

    public string $color_standard = '';

    public string $proofing_method = '';

    public string $artwork_approval = '';

    public string $retained_sample = '';

    public string $barcode_verification = '';

    public string $finishing_preference = '';

    public string $die_owner = '';

    public string $packing_instruction = '';

    public string $quality_notes = '';

    // ---- field form: Sales & Finance ----
    public ?int $sales_division_id = null;

    public string $price_list_code = '';

    public string $tax_profile = 'PPN_11';

    public ?string $payment_term = null;

    public string $currency_code = 'IDR';

    public string $credit_limit = '0';

    public bool $credit_check_enabled = true;

    public string $credit_rating = 'B';

    // ---- field form: Sites & Contacts (PIC + alamat utama) ----
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

    // ---- field form: Governance ----
    public bool $tax_validated = false;

    public string $kyc_status = 'PENDING';

    public ?string $actionMessage = null;

    // ---- unblock (ganti PIN palsu mockup dgn permission + alasan wajib) ----
    public bool $showUnblockModal = false;

    public string $unblock_reason = '';

    // ---- Cabang tambahan (cust_customer_branch) ----
    public bool $showBranchModal = false;

    public string $branchMode = 'new';

    public ?int $editingBranchId = null;

    public string $branch_name = '';

    public string $branch_address = '';

    public ?int $branch_area_id = null;

    public ?string $branch_area_level = null;

    public ?string $branch_address_label = null;

    public string $branch_postal_code = '';

    public string $branch_phone = '';

    public bool $branch_is_default = false;

    // ---- Kontak tambahan (cust_customer_contact) ----
    public bool $showContactModal = false;

    public string $contactMode = 'new';

    public ?int $editingContactId = null;

    public string $c_contact_name = '';

    public string $c_position = '';

    public string $c_phone = '';

    public string $c_email = '';

    public bool $c_is_default = false;

    public string $c_note = '';

    #[Computed]
    public function customers()
    {
        return CustCustomer::query()
            ->with('paymentTerm')
            ->when($this->q, fn ($query) => $query->where(fn ($qq) => $qq
                ->where('customer_name', 'like', "%{$this->q}%")
                ->orWhere('customer_code', 'like', "%{$this->q}%")
                ->orWhere('contact_name', 'like', "%{$this->q}%")))
            ->orderBy('customer_name')
            ->limit(200)
            ->get();
    }

    #[Computed]
    public function metricTotal(): int
    {
        return CustCustomer::query()->count();
    }

    #[Computed]
    public function metricActive(): int
    {
        return CustCustomer::query()->where('approval_status', 'APPROVED')->where('activate', true)->count();
    }

    #[Computed]
    public function metricBlocked(): int
    {
        return CustCustomer::query()->where(fn ($q) => $q->where('is_blacklisted', true)->orWhere('approval_status', 'BLOCKED'))->count();
    }

    #[Computed]
    public function metricDraft(): int
    {
        return CustCustomer::query()->where('approval_status', 'DRAFT')->count();
    }

    #[Computed]
    public function selectedCustomer(): ?CustCustomer
    {
        if (! $this->selectedId) {
            return null;
        }

        return CustCustomer::query()->with(['branches', 'contacts'])->find($this->selectedId);
    }

    #[Computed]
    public function paymentTerms()
    {
        return FinPaymentTerm::query()->where('activate', true)->orderBy('sort_order')->get();
    }

    #[Computed]
    public function categories()
    {
        return CustCategory::query()->where('activate', true)->orderBy('category_code')->get();
    }

    #[Computed]
    public function divisions()
    {
        return OrgDivision::query()->where('activate', true)->orderBy('division_name')->get();
    }

    public function avatarInitials(?string $name): string
    {
        $clean = trim((string) preg_replace('/^(PT|CV|UD|TBK)\b/i', '', (string) $name));
        $words = array_values(array_filter(explode(' ', $clean)));

        if (count($words) >= 2) {
            return strtoupper($words[0][0].$words[1][0]);
        }
        if (count($words) === 1) {
            return strtoupper(substr($words[0], 0, 2));
        }

        return '??';
    }

    // avatarGradient() dipindah ke App\Support\Grid\Avatar::gradient() (static) --
    // avatarInitials() TETAP di sini (bukan duplikat): strip prefix PT/CV/UD/TBK
    // sebelum ambil inisial, beda dari helper generik yang dipakai grid lain.

    #[On('address-picked')]
    public function onAddressPicked(string $fieldName, ?int $areaId, ?string $areaLevel, ?string $label, ?string $postalCode): void
    {
        if ($fieldName === 'customer-branch-address') {
            $this->branch_area_id = $areaId;
            $this->branch_area_level = $areaLevel;
            $this->branch_address_label = $label;
            $this->branch_postal_code = $postalCode ?? '';

            return;
        }

        if ($fieldName !== 'customer-address') {
            return;
        }

        $this->default_area_id = $areaId;
        $this->default_area_level = $areaLevel;
        $this->address_label = $label;
        $this->postal_code = $postalCode ?? '';
    }

    // ============== CABANG (cust_customer_branch) ==============
    public function openBranchModal(string $mode, ?int $id = null): void
    {
        $this->resetErrorBag();
        $this->branchMode = in_array($mode, ['new', 'edit'], true) ? $mode : 'new';
        $this->editingBranchId = $id;

        if ($this->branchMode === 'edit' && $id) {
            $b = CustCustomerBranch::findOrFail($id);
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
            'customer_id' => $this->selectedId,
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
            CustCustomerBranch::whereKey($this->editingBranchId)->update($payload);
            $savedBranchId = $this->editingBranchId;
        } else {
            $payload['created_by'] = Auth::user()->login;
            $savedBranchId = CustCustomerBranch::create($payload)->id_customer_branch;
        }

        if ($this->branch_is_default) {
            CustCustomerBranch::where('customer_id', $this->selectedId)
                ->whereKeyNot($savedBranchId)
                ->update(['is_default' => false]);
        }

        unset($this->selectedCustomer);
        $this->showBranchModal = false;
    }

    public function deleteBranch(int $id): void
    {
        CustCustomerBranch::whereKey($id)->delete();
        unset($this->selectedCustomer);
    }

    public function setDefaultBranch(int $id): void
    {
        if (! $this->selectedId) {
            return;
        }

        CustCustomerBranch::where('customer_id', $this->selectedId)->update(['is_default' => false]);
        CustCustomerBranch::whereKey($id)->update(['is_default' => true]);
        unset($this->selectedCustomer);
    }

    // ============== KONTAK TAMBAHAN (cust_customer_contact) ==============
    public function openContactModal(string $mode, ?int $id = null): void
    {
        $this->resetErrorBag();
        $this->contactMode = in_array($mode, ['new', 'edit'], true) ? $mode : 'new';
        $this->editingContactId = $id;

        if ($this->contactMode === 'edit' && $id) {
            $c = CustCustomerContact::findOrFail($id);
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
            'customer_id' => $this->selectedId,
            'contact_name' => $this->c_contact_name,
            'position' => $this->c_position,
            'phone' => $this->c_phone,
            'email' => $this->c_email,
            'is_default' => $this->c_is_default,
            'note' => $this->c_note,
            'updated_by' => Auth::user()->login,
        ];

        if ($this->contactMode === 'edit' && $this->editingContactId) {
            CustCustomerContact::whereKey($this->editingContactId)->update($payload);
            $savedContactId = $this->editingContactId;
        } else {
            $payload['created_by'] = Auth::user()->login;
            $savedContactId = CustCustomerContact::create($payload)->id_customer_contact;
        }

        if ($this->c_is_default) {
            CustCustomerContact::where('customer_id', $this->selectedId)
                ->whereKeyNot($savedContactId)
                ->update(['is_default' => false]);
        }

        unset($this->selectedCustomer);
        $this->showContactModal = false;
    }

    public function deleteContact(int $id): void
    {
        CustCustomerContact::whereKey($id)->delete();
        unset($this->selectedCustomer);
    }

    public function setDefaultContact(int $id): void
    {
        if (! $this->selectedId) {
            return;
        }

        CustCustomerContact::where('customer_id', $this->selectedId)->update(['is_default' => false]);
        CustCustomerContact::whereKey($id)->update(['is_default' => true]);
        unset($this->selectedCustomer);
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

    public function editRow(int $id): void
    {
        $this->selectedId = $id;
        $this->editCustomer();
    }

    public function showGrid(): void
    {
        $this->panel = 'grid';
    }

    public function newCustomer(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->selectedId = null;
        $this->formTab = 'overview';
        $this->panel = 'form';
    }

    public function editCustomer(): void
    {
        if (! $this->selectedId) {
            return;
        }

        $customer = CustCustomer::findOrFail($this->selectedId);
        $this->editingId = $customer->id_customer;
        $this->formTab = 'overview';
        $this->customer_code = (string) $customer->customer_code;
        $this->customer_name = (string) $customer->customer_name;
        $this->trade_name = (string) $customer->trade_name;
        $this->parent_group = (string) $customer->parent_group;
        $this->industry_category = (string) $customer->industry_category;
        $this->tax_code = (string) $customer->tax_code;
        $this->customer_type = $customer->customer_type ?? 'local';
        $this->category = $customer->category;
        $this->is_prospect = (bool) $customer->is_prospect;
        $this->activate = (bool) $customer->activate;
        $this->is_blacklisted = (bool) $customer->is_blacklisted;
        $this->approval_status = $customer->approval_status ?? 'DRAFT';
        $this->note = (string) $customer->note;

        $this->primary_product = (string) $customer->primary_product;
        $this->secondary_product = (string) $customer->secondary_product;
        $this->default_substrate = (string) $customer->default_substrate;
        $this->color_standard = (string) $customer->color_standard;
        $this->proofing_method = (string) $customer->proofing_method;
        $this->artwork_approval = (string) $customer->artwork_approval;
        $this->retained_sample = (string) $customer->retained_sample;
        $this->barcode_verification = (string) $customer->barcode_verification;
        $this->finishing_preference = (string) $customer->finishing_preference;
        $this->die_owner = (string) $customer->die_owner;
        $this->packing_instruction = (string) $customer->packing_instruction;
        $this->quality_notes = (string) $customer->quality_notes;

        $this->sales_division_id = $customer->sales_division_id;
        $this->price_list_code = (string) $customer->price_list_code;
        $this->tax_profile = $customer->tax_profile ?? 'PPN_11';
        $this->payment_term = $customer->payment_term;
        $this->currency_code = $customer->currency_code ?? 'IDR';
        $this->credit_limit = (string) $customer->credit_limit;
        $this->credit_check_enabled = (bool) $customer->credit_check_enabled;
        $this->credit_rating = $customer->credit_rating ?? 'B';

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

        $this->tax_validated = (bool) $customer->tax_validated;
        $this->kyc_status = $customer->kyc_status ?? 'PENDING';

        $this->panel = 'form';
    }

    public function setFormTab(string $tab): void
    {
        if (in_array($tab, ['overview', 'printing', 'sales', 'sites', 'governance', 'history'], true)) {
            $this->formTab = $tab;
        }
    }

    public function tabHasError(string $tab): bool
    {
        $fields = match ($tab) {
            'overview' => ['customer_code', 'customer_name', 'customer_type', 'tax_code', 'category'],
            'sales' => ['sales_division_id', 'payment_term', 'currency_code', 'credit_limit', 'tax_profile', 'credit_rating'],
            'sites' => ['default_address', 'default_area_id', 'default_area_level', 'postal_code', 'mr_ms', 'contact_name', 'phone', 'email', 'website'],
            'governance' => ['kyc_status'],
            default => [],
        };

        return collect($fields)->contains(fn ($f) => $this->getErrorBag()->has($f));
    }

    public function resetForm(): void
    {
        $this->reset([
            'customer_code', 'customer_name', 'trade_name', 'parent_group', 'industry_category',
            'tax_code', 'category', 'note',
            'primary_product', 'secondary_product', 'default_substrate', 'color_standard',
            'proofing_method', 'artwork_approval', 'retained_sample', 'barcode_verification',
            'finishing_preference', 'die_owner', 'packing_instruction', 'quality_notes',
            'sales_division_id', 'price_list_code', 'payment_term', 'credit_limit',
            'mr_ms', 'contact_name', 'default_address', 'default_area_id', 'default_area_level',
            'address_label', 'postal_code', 'email', 'phone', 'website',
        ]);
        $this->customer_type = 'local';
        $this->currency_code = 'IDR';
        $this->tax_profile = 'PPN_11';
        $this->credit_rating = 'B';
        $this->credit_check_enabled = true;
        $this->kyc_status = 'PENDING';
        $this->tax_validated = false;
        $this->is_prospect = false;
        $this->activate = true;
        $this->is_blacklisted = false;
        $this->approval_status = 'DRAFT';
    }

    public function setApprovalStatus(string $status): void
    {
        if (! array_key_exists($status, CustCustomer::APPROVAL_STATUSES)) {
            return;
        }

        $this->approval_status = $status;

        $targetId = $this->editingId ?: $this->selectedId;

        if ($targetId) {
            CustCustomer::whereKey($targetId)->update([
                'approval_status' => $status,
                'updated_by' => Auth::user()->login,
            ]);
            $this->actionMessage = 'Status diubah ke '.CustCustomer::APPROVAL_STATUSES[$status].'.';
            unset($this->customers, $this->selectedCustomer);
        }
    }

    public function save(): void
    {
        $unique = 'unique:tm2mst.cust_customer,customer_code';
        if ($this->editingId) {
            $unique .= ','.$this->editingId.',id_customer';
        }

        try {
            $data = $this->validate([
                'customer_code' => ['required', 'string', 'max:50', $unique],
                'customer_name' => ['required', 'string', 'max:200'],
                'trade_name' => ['nullable', 'string', 'max:200'],
                'parent_group' => ['nullable', 'string', 'max:200'],
                'industry_category' => ['nullable', 'string', 'max:150'],
                'tax_code' => ['nullable', 'string', 'max:50'],
                'customer_type' => ['required', 'in:local,import'],
                'category' => ['nullable', 'string', 'exists:tm2mst.cust_category,category_code'],
                'is_prospect' => ['boolean'],
                'activate' => ['boolean'],
                'is_blacklisted' => ['boolean'],
                'approval_status' => ['required', 'in:'.implode(',', array_keys(CustCustomer::APPROVAL_STATUSES))],
                'note' => ['nullable', 'string'],

                'primary_product' => ['nullable', 'string', 'max:150'],
                'secondary_product' => ['nullable', 'string', 'max:150'],
                'default_substrate' => ['nullable', 'string', 'max:150'],
                'color_standard' => ['nullable', 'string', 'max:150'],
                'proofing_method' => ['nullable', 'string', 'max:150'],
                'artwork_approval' => ['nullable', 'string', 'max:150'],
                'retained_sample' => ['nullable', 'string', 'max:150'],
                'barcode_verification' => ['nullable', 'string', 'max:150'],
                'finishing_preference' => ['nullable', 'string', 'max:200'],
                'die_owner' => ['nullable', 'string', 'max:150'],
                'packing_instruction' => ['nullable', 'string', 'max:200'],
                'quality_notes' => ['nullable', 'string'],

                'sales_division_id' => ['nullable', 'integer', 'exists:tm2mst.org_division,division_id'],
                'price_list_code' => ['nullable', 'string', 'max:50'],
                'tax_profile' => ['required', 'in:PPN_11,EXEMPT'],
                'payment_term' => ['nullable', 'string', 'exists:tm2mst.fin_payment_term,payment_term_code'],
                'currency_code' => ['nullable', 'string', 'max:10'],
                'credit_limit' => ['nullable', 'numeric', 'min:0'],
                'credit_check_enabled' => ['boolean'],
                'credit_rating' => ['required', 'in:A,B,C'],

                'default_address' => ['nullable', 'string'],
                'default_area_id' => ['nullable', 'integer'],
                'default_area_level' => ['nullable', 'in:CITY,DISTRICT,VILLAGE'],
                'postal_code' => ['nullable', 'string', 'max:20'],
                'mr_ms' => ['nullable', 'string', 'max:5'],
                'contact_name' => ['nullable', 'string', 'max:200'],
                'email' => ['nullable', 'email', 'max:150'],
                'phone' => ['nullable', 'string', 'max:50'],
                'website' => ['nullable', 'string', 'max:150'],

                'tax_validated' => ['boolean'],
                'kyc_status' => ['required', 'in:PENDING,APPROVED,REJECTED'],
            ]);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
            foreach (['overview', 'sales', 'sites', 'governance'] as $t) {
                if ($this->tabHasError($t)) {
                    $this->formTab = $t;
                    break;
                }
            }
            throw $e;
        }

        $data['updated_by'] = Auth::user()->login;

        if ($this->editingId) {
            CustCustomer::whereKey($this->editingId)->update($data);
            $this->selectedId = $this->editingId;
        } else {
            $data['created_by'] = Auth::user()->login;
            $customer = CustCustomer::create($data);
            $this->selectedId = $customer->id_customer;
            $this->editingId = $customer->id_customer;
        }

        unset($this->customers, $this->metricTotal, $this->metricActive, $this->metricBlocked, $this->metricDraft);
        $this->panel = 'grid';
    }

    public function toggleActive(int $id): void
    {
        $c = CustCustomer::findOrFail($id);
        $c->activate = ! $c->activate;
        $c->updated_by = Auth::user()->login;
        $c->save();

        unset($this->customers, $this->metricActive);
    }

    // Blokir = aksi langsung (siapa saja yang boleh edit customer). Buka
    // blokir WAJIB lewat modal + alasan + akses modul Finance/Admin — ganti
    // dari modal PIN statis "8888" mockup yang cuma ilusi keamanan.
    public function blockCustomer(): void
    {
        $id = $this->editingId ?: $this->selectedId;
        if (! $id) {
            return;
        }

        CustCustomer::whereKey($id)->update([
            'is_blacklisted' => true,
            'approval_status' => 'BLOCKED',
            'updated_by' => Auth::user()->login,
        ]);

        if ($this->editingId) {
            $this->is_blacklisted = true;
            $this->approval_status = 'BLOCKED';
        }

        $this->actionMessage = 'Customer diblokir.';
        unset($this->customers, $this->selectedCustomer);
    }

    public function openUnblockModal(): void
    {
        $this->reset(['unblock_reason']);
        $this->resetErrorBag();
        $this->showUnblockModal = true;
    }

    public function confirmUnblock(): void
    {
        if (! (Auth::user()->is_admin || Auth::user()->canAccessModule('FINACC'))) {
            $this->addError('unblock_reason', 'Anda tidak punya wewenang membuka blokir — butuh akses modul Finance & Accounting atau Admin.');

            return;
        }

        $data = $this->validate(['unblock_reason' => ['required', 'string', 'max:255']]);

        $id = $this->editingId ?: $this->selectedId;
        if (! $id) {
            return;
        }

        $customer = CustCustomer::findOrFail($id);
        $stamp = '[Unblock '.now()->format('d/m/Y H:i').' oleh '.Auth::user()->login.'] '.$data['unblock_reason'];

        $customer->update([
            'is_blacklisted' => false,
            'approval_status' => 'APPROVED',
            'note' => trim(($customer->note ? $customer->note."\n" : '').$stamp),
            'updated_by' => Auth::user()->login,
        ]);

        if ($this->editingId) {
            $this->is_blacklisted = false;
            $this->approval_status = 'APPROVED';
            $this->note = $customer->note;
        }

        $this->actionMessage = 'Blokir customer dibuka.';
        unset($this->customers, $this->selectedCustomer);
        $this->showUnblockModal = false;
    }
};
?>

<div>

    <div class="max-w-[1180px] mx-auto my-5 px-4">

        {{-- ============ GRID (LIST) ============ --}}
        @if ($panel === 'grid')
            <x-grid.stat-row :count="4">
                <x-grid.stat-box tone="total" label="{{ Translator::t('mdata_cust_stat_total', 'Total Customer') }}" :value="$this->metricTotal" />
                <x-grid.stat-box tone="active" label="{{ Translator::t('mdata_active', 'Aktif') }}" :value="$this->metricActive" />
                <x-grid.stat-box tone="critical" label="{{ Translator::t('mdata_sup_stat_blocked', 'Terblokir') }}" :value="$this->metricBlocked" />
                <x-grid.stat-box tone="warn" label="{{ Translator::t('mdata_cust_stat_draft', 'Draft / Onboarding') }}" :value="$this->metricDraft" />
            </x-grid.stat-row>

            <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
                <x-grid.toolbar>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <span class="text-sm">🔍</span>
                        <input type="text" wire:model.live.debounce.400ms="q" placeholder="{{ Translator::t('mdata_sup_search_placeholder', 'Cari nama / kode / PIC...') }}"
                            class="w-56 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                    </div>
                    <x-slot:newRecord>
                        <button type="button" wire:click="newCustomer"
                            class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
                    </x-slot:newRecord>
                </x-grid.toolbar>

                @if ($actionMessage)
                    <div class="text-xs px-3 py-2 border-b border-[#eef1f5] bg-[#fff8e1] text-[#8a6d00]">{{ $actionMessage }}</div>
                @endif

                <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Customer</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ Translator::t('proc_po_col_type', 'Tipe') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ Translator::t('mdata_cust_col_industry', 'Industri') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ Translator::t('mdata_cust_col_credit_limit', 'Limit Kredit') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ Translator::t('mdata_sup_col_terms', 'Termin') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ Translator::t('proc_col_status', 'Status') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[80px]">{{ Translator::t('proc_col_action', 'Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->customers as $c)
                            <tr wire:click="selectRow({{ $c->id_customer }})" wire:dblclick="editRow({{ $c->id_customer }})"
                                class="cursor-pointer hover:bg-[#f7faff] {{ $selectedId === $c->id_customer ? 'bg-[#e8f0fe]' : '' }}"
                                title="{{ Translator::t('hrm_emp_dblclick_detail_hint', 'Klik 2x untuk buka detail') }}">
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-md bg-gradient-to-br {{ \App\Support\Grid\Avatar::gradient($c->id_customer) }} text-white text-[11px] font-bold flex items-center justify-center shrink-0">{{ $this->avatarInitials($c->customer_name) }}</div>
                                        <div>
                                            <div class="font-semibold text-[13px]">{{ $c->customer_name }}</div>
                                            <div class="text-[11px] text-muted">{{ $c->customer_code }}{{ $c->parent_group ? ' · '.$c->parent_group : '' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $c->customer_type === 'import' ? Translator::t('mdata_cust_type_import', 'Import') : Translator::t('mdata_cust_type_local', 'Lokal') }}{{ $c->is_prospect ? ' · '.Translator::t('mdata_cust_prospect', 'Prospek') : '' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] max-w-[160px] truncate">{{ $c->industry_category ?: '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">Rp {{ number_format((float) $c->credit_limit, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $c->paymentTerm?->term_label ?? '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10.5px] font-bold px-2.5 py-0.5 rounded-full inline-block {{ $c->activate ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#eef0f3] text-[#8a8f98]' }}">
                                        {{ $c->activate ? Translator::t('mdata_active', 'Aktif') : Translator::t('mdata_inactive', 'Nonaktif') }}
                                    </span>
                                    <span class="text-[10.5px] font-bold px-2.5 py-0.5 rounded-full inline-block ml-1 {{ match($c->approval_status ?? 'DRAFT') { 'APPROVED' => 'bg-[#eaf7ee] text-[#1f8a4c]', 'BLOCKED' => 'bg-[#fdeceb] text-[#c0392b]', 'CREDIT_CHECK' => 'bg-[#fff4e0] text-[#8a6d00]', default => 'bg-[#eef0f3] text-[#6b7280]' } }}">
                                        {{ \App\Models\Master\CustCustomer::APPROVAL_STATUSES[$c->approval_status ?? 'DRAFT'] }}
                                    </span>
                                    @if ($c->is_blacklisted)
                                        <span class="text-[10.5px] font-bold px-2.5 py-0.5 rounded-full inline-block bg-[#fdeceb] text-[#c0392b] ml-1">🚫</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="✎" title="{{ Translator::t('fin_coa_action_edit', 'Edit') }}" action="editRow({{ $c->id_customer }})" />
                                    <x-grid.action-icon icon="⏻" :title="$c->activate ? Translator::t('fin_coa_deactivate', 'Nonaktifkan') : Translator::t('fin_coa_activate', 'Aktifkan')" action="toggleActive({{ $c->id_customer }})" :variant="$c->activate ? 'danger' : 'default'" confirm="{{ $c->activate ? Translator::t('fin_coa_deactivate', 'Nonaktifkan') : Translator::t('fin_coa_activate', 'Aktifkan') }} customer {{ $c->customer_name }}?" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="7" message="{{ Translator::t('mdata_cust_empty', 'Belum ada data customer') }}{{ $q ? Translator::t('mdata_sup_empty_search_suffix', ' untuk pencarian ini') : '' }}." />
                        @endforelse
                    </tbody>
                </table>
                </x-grid.table-shell>
            </div>
        @endif

        {{-- ============ FORM (VIEW + EDIT BERSATU) ============ --}}
        @if ($panel === 'form')
            <div class="flex justify-between items-center flex-wrap gap-2.5 bg-[#2c3e69] text-white px-4 py-3 rounded-t-md">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br {{ \App\Support\Grid\Avatar::gradient($editingId ?? 0) }} text-white text-xs font-bold flex items-center justify-center shrink-0 uppercase">{{ $this->avatarInitials($customer_name) }}</div>
                    <div>
                        <div class="text-sm font-bold">{{ $customer_name ?: ($editingId ? 'Edit Customer' : 'Customer Baru') }}</div>
                        @if ($editingId)<div class="text-xs font-normal opacity-75">{{ $customer_code }}</div>@endif
                    </div>
                </div>
                <div class="flex border border-white/25 rounded-md overflow-hidden text-[11px]">
                    @foreach (CustCustomer::APPROVAL_STATUSES as $stKey => $stLabel)
                        <button type="button" wire:click="setApprovalStatus('{{ $stKey }}')"
                            class="px-3 py-1.5 font-semibold cursor-pointer border-r border-white/25 last:border-r-0
                                {{ $approval_status === $stKey ? 'bg-white text-[#2c3e69]' : 'bg-transparent text-white/70 hover:text-white' }}">
                            {{ $stLabel }}
                        </button>
                    @endforeach
                </div>
            </div>

            @if ($actionMessage)
                <div class="text-xs px-3.5 py-2 border-x border-[#d7dbe2] bg-[#fff8e1] text-[#8a6d00]">{{ $actionMessage }}</div>
            @endif

            @if ($editingId)
                <div class="flex justify-between items-center gap-2.5 px-4 py-2.5 bg-white border-x border-[#d7dbe2] flex-wrap">
                    <div class="flex gap-2">
                        @if ($is_blacklisted)
                            <button type="button" wire:click="openUnblockModal" class="px-3 py-1.5 rounded text-[11px] font-semibold bg-[#eaf7ee] border border-[#bfe6cd] text-[#1f8a4c] cursor-pointer">🔓 Buka Blokir</button>
                        @else
                            <button type="button" wire:click="blockCustomer" wire:confirm="Blokir customer ini?" class="px-3 py-1.5 rounded text-[11px] font-semibold bg-[#fdeceb] border border-[#f0c4c4] text-[#c0392b] cursor-pointer">🚫 Blokir</button>
                        @endif
                    </div>
                    <div class="flex gap-2.5 flex-wrap">
                        <div class="border border-[#d7dbe2] rounded-md px-3.5 py-1.5 min-w-[135px] text-right bg-[#fafbfc]">
                            <div class="text-[10px] text-[#8a94a6] uppercase tracking-wide">Limit Kredit</div>
                            <div class="text-[13px] font-bold">Rp {{ number_format((float) $credit_limit, 0, ',', '.') }}</div>
                        </div>
                        <div class="border border-[#d7dbe2] rounded-md px-3.5 py-1.5 min-w-[135px] text-right bg-[#fafbfc]">
                            <div class="text-[10px] text-[#8a94a6] uppercase tracking-wide">Saldo Piutang</div>
                            <div class="text-[13px] font-bold">Rp {{ number_format((float) ($this->selectedCustomer?->current_balance ?? 0), 0, ',', '.') }}</div>
                        </div>
                        <div class="border border-[#d7dbe2] rounded-md px-3.5 py-1.5 min-w-[110px] text-right bg-[#fafbfc]">
                            <div class="text-[10px] text-[#8a94a6] uppercase tracking-wide">Cabang</div>
                            <div class="text-[13px] font-bold">{{ 1 + ($this->selectedCustomer?->branches->count() ?? 0) }}</div>
                        </div>
                        <div class="border border-[#d7dbe2] rounded-md px-3.5 py-1.5 min-w-[110px] text-right bg-[#fafbfc]">
                            <div class="text-[10px] text-[#8a94a6] uppercase tracking-wide">Kontak</div>
                            <div class="text-[13px] font-bold">{{ 1 + ($this->selectedCustomer?->contacts->count() ?? 0) }}</div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="flex gap-1 bg-[#fafbfc] border-x border-b border-[#d7dbe2] px-3 pt-2 flex-wrap">
                @foreach (['overview' => 'Overview', 'printing' => 'Printing Specs', 'sales' => 'Sales & Finance', 'sites' => 'Sites & Contacts', 'governance' => 'Governance', 'history' => 'History'] as $tabKey => $tabLabel)
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

                {{-- TAB: OVERVIEW --}}
                <div class="{{ $formTab === 'overview' ? '' : 'hidden' }}">
                    <h3 class="text-xs text-[#2c3e69] uppercase tracking-wide mb-2.5 border-b border-[#d7dbe2] pb-1.5">Identitas Umum</h3>
                    <div class="[display:grid] grid-cols-3 gap-3">
                        <x-form.input label="Kode Customer" wire:model="customer_code" maxlength="50" name="customer_code" />
                        <div class="col-span-2">
                            <x-form.input label="Nama Customer" wire:model="customer_name" maxlength="200" name="customer_name" />
                        </div>
                        <x-form.input label="Trade Name / Brand" wire:model="trade_name" maxlength="200" />
                        <x-form.input label="Parent / Customer Group" wire:model="parent_group" maxlength="200" />
                        <x-form.input label="Kategori Industri" wire:model="industry_category" maxlength="150" placeholder="mis. FMCG - Food & Beverage Packaging" />
                        <x-form.select label="Tipe" wire:model="customer_type" :placeholder="false" :options="['local' => 'Lokal', 'import' => 'Import']" />
                        <x-form.input label="NPWP" wire:model="tax_code" maxlength="50" placeholder="00.000.000.0-000.000" />
                        <x-form.select label="Kategori (Tracking Bayar)" wire:model="category" name="category"
                            :options="$this->categories->pluck('category_code', 'category_code')->all()" />
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Status</label>
                            <x-form.checkbox wire:model="activate" label="Aktif" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Prospek</label>
                            <x-form.checkbox wire:model="is_prospect" label="Calon pelanggan (belum resmi)" />
                        </div>
                        <div class="col-span-3">
                            <x-form.textarea label="Catatan Internal" wire:model="note" rows="2" placeholder="mis. preferensi operasional, riwayat masalah" />
                        </div>
                    </div>
                </div>

                {{-- TAB: PRINTING SPECS --}}
                <div class="{{ $formTab === 'printing' ? '' : 'hidden' }}">
                    <div class="text-[11px] text-[#8a6d00] bg-[#fff8e1] border border-dashed border-[#e6d38a] rounded-md px-3 py-2 mb-3 leading-relaxed">
                        Field cetak-spesifik ini mempengaruhi asumsi quotation, proofing artwork, routing produksi, label pengiriman, dan penerimaan kualitas.
                    </div>
                    <h3 class="text-xs text-[#2c3e69] uppercase tracking-wide mb-2.5 border-b border-[#d7dbe2] pb-1.5">Produk &amp; Material</h3>
                    <div class="[display:grid] grid-cols-4 gap-3 mb-3">
                        <x-form.input label="Produk Utama" wire:model="primary_product" />
                        <x-form.input label="Produk Sekunder" wire:model="secondary_product" />
                        <x-form.input label="Substrate Default" wire:model="default_substrate" />
                        <x-form.input label="Standar Warna" wire:model="color_standard" />
                    </div>
                    <h3 class="text-xs text-[#2c3e69] uppercase tracking-wide mb-2.5 border-b border-[#d7dbe2] pb-1.5">Proofing &amp; Quality Control</h3>
                    <div class="[display:grid] grid-cols-4 gap-3 mb-3">
                        <x-form.input label="Metode Proofing" wire:model="proofing_method" />
                        <x-form.input label="Approval Artwork" wire:model="artwork_approval" />
                        <x-form.input label="Retained Sample" wire:model="retained_sample" />
                        <x-form.input label="Verifikasi Barcode" wire:model="barcode_verification" />
                    </div>
                    <h3 class="text-xs text-[#2c3e69] uppercase tracking-wide mb-2.5 border-b border-[#d7dbe2] pb-1.5">Finishing &amp; Pengiriman</h3>
                    <div class="[display:grid] grid-cols-3 gap-3 mb-3">
                        <x-form.input label="Preferensi Finishing" wire:model="finishing_preference" />
                        <x-form.input label="Pemilik Die-Cut" wire:model="die_owner" />
                        <x-form.input label="Instruksi Packing" wire:model="packing_instruction" />
                    </div>
                    <x-form.textarea label="Catatan Kualitas & Kriteria Reject" wire:model="quality_notes" rows="3" />
                </div>

                {{-- TAB: SALES & FINANCE --}}
                <div class="{{ $formTab === 'sales' ? '' : 'hidden' }}">
                    <h3 class="text-xs text-[#2c3e69] uppercase tracking-wide mb-2.5 border-b border-[#d7dbe2] pb-1.5">Wilayah Penjualan &amp; Harga</h3>
                    <div class="[display:grid] grid-cols-3 gap-3 mb-3">
                        <x-form.select label="Divisi (SBU)" wire:model="sales_division_id" name="sales_division_id"
                            :options="$this->divisions->pluck('division_name', 'division_id')->all()" />
                        <x-form.input label="Price List" wire:model="price_list_code" maxlength="50" />
                        <x-form.select label="Profil Pajak" wire:model="tax_profile" :placeholder="false"
                            :options="['PPN_11' => 'PPN 11% - Domestic Taxable', 'EXEMPT' => 'Bebas PPN (Kawasan Berikat)']" />
                    </div>
                    <h3 class="text-xs text-[#2c3e69] uppercase tracking-wide mb-2.5 border-b border-[#d7dbe2] pb-1.5">Termin &amp; Kredit</h3>
                    <div class="[display:grid] grid-cols-3 gap-3 mb-3">
                        <x-form.select label="Termin Bayar" wire:model="payment_term" name="payment_term"
                            :options="$this->paymentTerms->pluck('term_label', 'payment_term_code')->all()" />
                        <x-form.input label="Mata Uang" wire:model="currency_code" maxlength="10" placeholder="IDR" />
                        <x-form.input label="Limit Kredit" type="number" step="0.01" min="0" wire:model="credit_limit" />
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Credit Checking</label>
                            <x-form.checkbox wire:model="credit_check_enabled" label="Aktifkan pengecekan kredit" />
                        </div>
                        <x-form.select label="Credit Rating" wire:model="credit_rating" :placeholder="false" :options="CustCustomer::CREDIT_RATINGS" />
                        <x-form.input label="Saldo Piutang Berjalan" readonly value="Rp {{ number_format((float) ($this->selectedCustomer?->current_balance ?? 0), 0, ',', '.') }}" />
                    </div>

                    {{--
                        PLACEHOLDER BELUM FUNGSIONAL — menunggu pembahasan skema COA
                        dgn Jo, lihat catatan asli di git history file ini. Jangan
                        aktifkan sebelum tabel akun & FK-nya didesain.
                    --}}
                    <div class="mt-4 border border-dashed border-[#e6d38a] bg-[#fff8e1] rounded-md p-3.5">
                        <div class="flex items-center gap-1.5 mb-2">
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#8a6d00] text-white">MENYUSUL</span>
                            <span class="text-[11px] font-bold text-[#8a6d00] uppercase tracking-wide">Chart of Accounts — belum aktif</span>
                        </div>
                        <div class="flex flex-col gap-1 max-w-[360px]">
                            <label class="text-[10.5px] font-semibold text-[#6b7280] uppercase">Akun Rekonsiliasi Piutang</label>
                            <input type="text" disabled placeholder="Menunggu desain skema COA" class="px-2.5 py-1.5 border border-[#e6d38a] rounded text-xs bg-white text-[#b8a45c] cursor-not-allowed">
                        </div>
                        <div class="text-[11px] text-[#8a6d00] mt-2 leading-relaxed">Belum tersambung ke data apa pun — tampilan saja, menunggu pembahasan COA terpisah.</div>
                    </div>
                </div>

                {{-- TAB: SITES & CONTACTS --}}
                <div class="{{ $formTab === 'sites' ? '' : 'hidden' }}">
                    <h3 class="text-xs text-[#2c3e69] uppercase tracking-wide mb-2.5 border-b border-[#d7dbe2] pb-1.5">Kantor Pusat (Alamat Utama)</h3>
                    <div class="[display:grid] grid-cols-2 gap-3 mb-4">
                        <div class="col-span-2">
                            <x-form.textarea label="Alamat Lengkap" wire:model="default_address" rows="2" placeholder="Jalan, nomor, RT/RW, patokan" />
                        </div>
                        <div class="flex flex-col gap-1 col-span-2">
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">
                                Wilayah <span class="text-[9.5px] font-bold px-1.5 py-0.5 rounded-lg ml-1 bg-[#fff8e1] text-[#b98900] normal-case">◆ ketik kelurahan / kecamatan / kota / provinsi / kode pos</span>
                            </label>
                            <livewire:address-picker
                                field-name="customer-address"
                                :initial-area-id="$default_area_id"
                                :initial-area-level="$default_area_level"
                                :initial-label="$address_label"
                                wire:key="addr-picker-{{ $editingId ?? 'new' }}" />
                        </div>
                        <x-form.input label="Kode Pos" wire:model="postal_code" maxlength="20" placeholder="Auto dari wilayah, bisa diubah" />
                    </div>

                    <h3 class="text-xs text-[#2c3e69] uppercase tracking-wide mb-2.5 border-b border-[#d7dbe2] pb-1.5">Kontak PIC Utama</h3>
                    <div class="[display:grid] grid-cols-3 gap-3 mb-4">
                        <x-form.select label="Sapaan" wire:model="mr_ms" placeholder="—" :options="['Bapak' => 'Bapak', 'Ibu' => 'Ibu']" />
                        <x-form.input label="Nama PIC" wire:model="contact_name" maxlength="200" />
                        <x-form.input label="No. Telepon" wire:model="phone" maxlength="50" />
                        <x-form.input label="Email" type="email" wire:model="email" maxlength="150" name="email" />
                        <x-form.input label="Website" wire:model="website" maxlength="150" />
                    </div>

                    @if ($editingId)
                        <div class="[display:grid] grid-cols-2 gap-3">
                            <div class="border border-[#d7dbe2] rounded-md p-3">
                                <div class="flex items-center mb-2">
                                    <div class="text-[10.5px] text-[#8a94a6] uppercase">Kontak Tambahan</div>
                                    <span class="flex-1"></span>
                                    <button type="button" wire:click="openContactModal('new')" class="text-[11px] font-semibold text-[#2f6fed] cursor-pointer">+ Tambah</button>
                                </div>
                                @forelse ($this->selectedCustomer?->contacts ?? [] as $ct)
                                    <div class="flex items-start justify-between gap-2 py-1.5 border-b border-[#f0f2f5] last:border-b-0">
                                        <div>
                                            <div class="text-xs font-semibold">{{ $ct->contact_name }}{{ $ct->position ? ' · '.$ct->position : '' }}
                                                @if ($ct->is_default)<span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-[#eaf7ee] text-[#1f8a4c] ml-1">DEFAULT</span>@endif
                                            </div>
                                            <div class="text-xs text-[#6b7280]">{{ $ct->phone ?: '—' }} · {{ $ct->email ?: '—' }}</div>
                                        </div>
                                        <div class="flex gap-1 shrink-0">
                                            @unless ($ct->is_default)
                                                <button type="button" wire:click="setDefaultContact({{ $ct->id_customer_contact }})" title="Jadikan default" class="w-6 h-6 rounded border border-[#d7dbe2] text-[10px] cursor-pointer">★</button>
                                            @endunless
                                            <button type="button" wire:click="openContactModal('edit', {{ $ct->id_customer_contact }})" title="Edit" class="w-6 h-6 rounded border border-[#d7dbe2] text-[10px] cursor-pointer">✎</button>
                                            <button type="button" wire:click="deleteContact({{ $ct->id_customer_contact }})" wire:confirm="Hapus kontak {{ $ct->contact_name }}?" title="Hapus" class="w-6 h-6 rounded border border-[#f0c4c4] text-[#c0392b] text-[10px] cursor-pointer">🗑</button>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-xs text-[#8a94a6] py-2">Belum ada kontak tambahan.</div>
                                @endforelse
                            </div>
                            <div class="border border-[#d7dbe2] rounded-md p-3">
                                <div class="flex items-center mb-2">
                                    <div class="text-[10.5px] text-[#8a94a6] uppercase">Cabang / Site Tambahan</div>
                                    <span class="flex-1"></span>
                                    <button type="button" wire:click="openBranchModal('new')" class="text-[11px] font-semibold text-[#2f6fed] cursor-pointer">+ Tambah</button>
                                </div>
                                @forelse ($this->selectedCustomer?->branches ?? [] as $br)
                                    <div class="flex items-start justify-between gap-2 py-1.5 border-b border-[#f0f2f5] last:border-b-0">
                                        <div>
                                            <div class="text-xs font-semibold">{{ $br->branch_name ?: 'Cabang' }}
                                                @if ($br->is_default)<span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-[#eaf7ee] text-[#1f8a4c] ml-1">DEFAULT</span>@endif
                                            </div>
                                            <div class="text-xs">{{ $br->address ?: '—' }}</div>
                                            <div class="text-xs text-[#6b7280] mt-0.5">{{ $br->phone ?: '—' }}{{ $br->postal_code ? ' · '.$br->postal_code : '' }}</div>
                                        </div>
                                        <div class="flex gap-1 shrink-0">
                                            @unless ($br->is_default)
                                                <button type="button" wire:click="setDefaultBranch({{ $br->id_customer_branch }})" title="Jadikan default" class="w-6 h-6 rounded border border-[#d7dbe2] text-[10px] cursor-pointer">★</button>
                                            @endunless
                                            <button type="button" wire:click="openBranchModal('edit', {{ $br->id_customer_branch }})" title="Edit" class="w-6 h-6 rounded border border-[#d7dbe2] text-[10px] cursor-pointer">✎</button>
                                            <button type="button" wire:click="deleteBranch({{ $br->id_customer_branch }})" wire:confirm="Hapus cabang {{ $br->branch_name ?: 'ini' }}?" title="Hapus" class="w-6 h-6 rounded border border-[#f0c4c4] text-[#c0392b] text-[10px] cursor-pointer">🗑</button>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-xs text-[#8a94a6] py-2">Belum ada cabang tambahan.</div>
                                @endforelse
                            </div>
                        </div>
                    @else
                        <div class="text-xs text-[#8a94a6] bg-[#fafbfc] border border-dashed border-[#d7dbe2] rounded-md px-3.5 py-3">Simpan customer dahulu untuk menambah kontak &amp; cabang tambahan.</div>
                    @endif
                </div>

                {{-- TAB: GOVERNANCE --}}
                <div class="{{ $formTab === 'governance' ? '' : 'hidden' }}">
                    <h3 class="text-xs text-[#2c3e69] uppercase tracking-wide mb-2.5 border-b border-[#d7dbe2] pb-1.5">Governance &amp; Kepatuhan</h3>
                    <div class="[display:grid] grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Validasi NPWP</label>
                            <x-form.checkbox wire:model="tax_validated" label="NPWP sudah diverifikasi Finance" />
                        </div>
                        <x-form.select label="Status KYC" wire:model="kyc_status" :placeholder="false" :options="CustCustomer::KYC_STATUSES" />
                    </div>
                    <div class="text-[11px] text-[#8a94a6] mt-3 leading-relaxed">COA/GL &amp; laporan finansial umumnya internal (Sisi A) — data yang dibagikan ke Sales harus melalui publish, sesuai CLAUDE.md §4.</div>
                </div>

                {{-- TAB: HISTORY --}}
                <div class="{{ $formTab === 'history' ? '' : 'hidden' }}">
                    <h3 class="text-xs text-[#2c3e69] uppercase tracking-wide mb-2.5 border-b border-[#d7dbe2] pb-1.5">Riwayat Ringkas</h3>
                    @if ($editingId && $this->selectedCustomer)
                        <div class="relative pl-4 border-l-2 border-[#e2e7ee]">
                            <div class="relative mb-4 pl-3">
                                <span class="absolute -left-[21px] top-1 w-2 h-2 rounded-full bg-accent border-2 border-white"></span>
                                <div class="text-[10px] text-[#8a94a6] font-semibold">{{ $this->selectedCustomer->updated_at?->format('d M Y · H:i') }} WIB</div>
                                <div class="text-xs font-bold text-[#2c3e69] mt-0.5">Terakhir Diubah</div>
                                <div class="text-xs text-[#6b7280]">oleh {{ $this->selectedCustomer->updated_by ?: '—' }}</div>
                            </div>
                            <div class="relative pl-3">
                                <span class="absolute -left-[21px] top-1 w-2 h-2 rounded-full bg-[#d7dbe2] border-2 border-white"></span>
                                <div class="text-[10px] text-[#8a94a6] font-semibold">{{ $this->selectedCustomer->created_at?->format('d M Y · H:i') }} WIB</div>
                                <div class="text-xs font-bold text-[#2c3e69] mt-0.5">Customer Dibuat</div>
                                <div class="text-xs text-[#6b7280]">oleh {{ $this->selectedCustomer->created_by ?: '—' }}</div>
                            </div>
                        </div>
                        <div class="text-[11px] text-[#8a94a6] mt-3">Riwayat detail per-field (audit trail lengkap) belum tersedia — baru mencatat jejak dibuat/diubah terakhir.</div>
                    @else
                        <div class="text-xs text-[#8a94a6] bg-[#fafbfc] border border-dashed border-[#d7dbe2] rounded-md px-3.5 py-3">Simpan customer dahulu untuk melihat riwayat.</div>
                    @endif
                </div>

                <div class="flex gap-2 pt-3.5 -mx-5 -mb-5 px-4 py-3.5 bg-[#fafbfc] border-t border-[#d7dbe2] rounded-b-md">
                    <button type="submit" class="px-4 py-2 rounded text-xs font-semibold bg-[#2f6fed] text-white border border-[#2f6fed] cursor-pointer">Simpan</button>
                    <button type="button" wire:click="showGrid" class="px-4 py-2 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">Batal</button>
                </div>
            </form>
        @endif

        {{-- ══ MODAL: KONTAK TAMBAHAN ══ --}}
        @if ($showContactModal)
            <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="closeContactModal">
                <div class="bg-white rounded-xl w-[420px] max-w-[92vw] shadow-2xl">
                    <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold flex items-center gap-2">
                        <span>{{ $contactMode === 'edit' ? 'Edit Kontak' : 'Kontak Baru' }}</span>
                        <span class="ml-auto cursor-pointer opacity-85 hover:opacity-100" wire:click="closeContactModal">✕</span>
                    </div>
                    <form wire:submit="saveContact" novalidate class="p-4 flex flex-col gap-3">
                        <x-form.input label="Nama Kontak *" wire:model="c_contact_name" name="c_contact_name" />
                        <x-form.input label="Jabatan" wire:model="c_position" />
                        <div class="flex gap-2.5">
                            <div class="flex-1">
                                <x-form.input label="Telepon" wire:model="c_phone" />
                            </div>
                            <div class="flex-1">
                                <x-form.input label="Email" type="email" wire:model="c_email" name="c_email" />
                            </div>
                        </div>
                        <x-form.textarea label="Catatan" wire:model="c_note" rows="2" />
                        <x-form.checkbox wire:model="c_is_default" label="Jadikan kontak default" label-class="text-[#334]" />
                        <div class="flex gap-2 justify-end pt-2 border-t border-[#eef1f5] mt-1">
                            <button type="button" wire:click="closeContactModal" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Batal</button>
                            <button type="submit" class="px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">Simpan</button>
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
                        <span>{{ $branchMode === 'edit' ? 'Edit Cabang' : 'Cabang Baru' }}</span>
                        <span class="ml-auto cursor-pointer opacity-85 hover:opacity-100" wire:click="closeBranchModal">✕</span>
                    </div>
                    <form wire:submit="saveBranch" novalidate class="p-4 flex flex-col gap-3">
                        <x-form.input label="Nama Cabang" wire:model="branch_name" placeholder="mis. Gudang Surabaya" />
                        <x-form.textarea label="Alamat" wire:model="branch_address" rows="2" />
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Wilayah</label>
                            <livewire:address-picker
                                field-name="customer-branch-address"
                                :initial-area-id="$branch_area_id"
                                :initial-area-level="$branch_area_level"
                                :initial-label="$branch_address_label"
                                wire:key="branch-addr-picker-{{ $editingBranchId ?? 'new' }}" />
                        </div>
                        <div class="flex gap-2.5">
                            <div class="flex-1">
                                <x-form.input label="Kode Pos" wire:model="branch_postal_code" />
                            </div>
                            <div class="flex-1">
                                <x-form.input label="Telepon" wire:model="branch_phone" />
                            </div>
                        </div>
                        <x-form.checkbox wire:model="branch_is_default" label="Jadikan cabang default" label-class="text-[#334]" />
                        <div class="flex gap-2 justify-end pt-2 border-t border-[#eef1f5] mt-1">
                            <button type="button" wire:click="closeBranchModal" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Batal</button>
                            <button type="submit" class="px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- ══ MODAL: OTORISASI UNBLOCK ══ --}}
        @if ($showUnblockModal)
            <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('showUnblockModal', false)">
                <div class="bg-white rounded-xl w-[420px] max-w-[92vw] shadow-2xl">
                    <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold flex items-center gap-2">
                        <span>🔒 Otorisasi Unblock Customer</span>
                        <span class="ml-auto cursor-pointer opacity-85 hover:opacity-100" wire:click="$set('showUnblockModal', false)">✕</span>
                    </div>
                    <form wire:submit="confirmUnblock" novalidate class="p-4 flex flex-col gap-3">
                        <div class="text-[11px] text-[#6b7280] leading-relaxed">Butuh akses modul Finance &amp; Accounting atau Admin App. Alasan wajib diisi — akan tercatat di catatan internal customer sebagai jejak audit.</div>
                        <x-form.textarea label="Alasan Unblock *" wire:model="unblock_reason" rows="3" placeholder="Alasan pembukaan blokir..." name="unblock_reason" />
                        <div class="flex gap-2 justify-end pt-2 border-t border-[#eef1f5] mt-1">
                            <button type="button" wire:click="$set('showUnblockModal', false)" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Batal</button>
                            <button type="submit" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#1f8a4c] text-white cursor-pointer">Verifikasi &amp; Release Block</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

    </div>
</div>
