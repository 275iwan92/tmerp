<?php

use App\Livewire\Concerns\OpensFromQuery;
use App\Models\Sys\SecGroup;
use App\Models\Sys\SecGroupPermission;
use App\Models\Sys\SecModule;
use App\Models\Sys\SecUser;
use App\Services\Migration\CustomerOrderMigrationService;
use App\Services\Migration\EmployeeLoginProvisionService;
use App\Services\Migration\EmployeeMigrationService;
use App\Services\Migration\PurchaseOrderMigrationService;
use App\Services\Migration\VendorMigrationService;
use App\Services\Security\PasswordResetService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Setup & Security — TELASIH MAKMUR ERP')] class extends Component
{
    use OpensFromQuery;

    #[Url(as: 'tab')]
    public string $activePanel = 'users';

    // Dibuka dari grid v2 Account / Group (?tab=users|groups&open={id} atau &new=1): langsung ke form baris itu.
    protected function openFromQuery(int $id): void
    {
        request()->query('tab') === 'groups' ? $this->openGroupForm('edit', $id) : $this->openUserForm('edit', $id);
    }

    protected function newFromQuery(): void
    {
        request()->query('tab') === 'groups' ? $this->openGroupForm('new') : $this->openUserForm('new');
    }

    // ================= PENGGUNA =================
    // REVISI 2026-08-30 (instruksi user): "Daftar pengguna" tadinya <table>
    // polos + form "Tambah pengguna" selalu-tampil -- diganti pola Grid Master
    // standar (docs/griduistandard.md): kotak statistik pastel, kolom identitas
    // avatar+nama+kode, toolbar search+"+ New Record", kolom Aksi ikon.
    // Panel grid<->form pakai swap $userView (BUKAN modal float) -- pola PERSIS
    // sama dgn resources/views/pages/master/shift.blade.php, komponen
    // <x-grid.*> dipakai apa adanya (bukan reinvent).
    public string $userSearch = '';

    public string $userView = 'grid'; // grid | form

    public string $userMode = 'new'; // new | edit

    public ?int $editingUserId = null;

    public string $u_login = '';

    public string $u_name = '';

    public string $u_email = '';

    public string $u_pswd = '';

    public ?int $u_group_id = null;

    public ?string $userMessage = null;

    #[Computed]
    public function users()
    {
        return SecUser::query()
            ->with('groups')
            ->when($this->userSearch !== '', fn ($q) => $q->where(fn ($qq) => $qq
                ->where('login', 'like', "%{$this->userSearch}%")
                ->orWhere('name', 'like', "%{$this->userSearch}%")
                ->orWhere('email', 'like', "%{$this->userSearch}%")))
            ->orderBy('login')
            ->get();
    }

    #[Computed]
    public function userStatTotal(): int
    {
        return SecUser::count();
    }

    #[Computed]
    public function userStatActive(): int
    {
        return SecUser::where('activate', true)->count();
    }

    #[Computed]
    public function userStatMustChange(): int
    {
        return SecUser::where('must_change_password', true)->count();
    }

    #[Computed]
    public function userStatInactive(): int
    {
        return SecUser::where('activate', false)->count();
    }

    public function openUserForm(string $mode, ?int $id = null): void
    {
        $this->resetErrorBag();
        $this->userMessage = null;
        $this->userMode = in_array($mode, ['new', 'edit'], true) ? $mode : 'new';
        $this->editingUserId = $id;

        if ($this->userMode === 'edit' && $id) {
            $user = SecUser::with('groups')->findOrFail($id);
            $this->u_login = $user->login;
            $this->u_name = $user->name;
            $this->u_email = (string) $user->email;
            $this->u_pswd = '';
            $this->u_group_id = $user->groups->first()->id_group ?? null;
        } else {
            $this->reset(['u_login', 'u_name', 'u_email', 'u_pswd', 'u_group_id']);
        }

        $this->userView = 'form';
    }

    public function showUserGrid(): void
    {
        $this->userView = 'grid';
    }

    public function saveUser(): void
    {
        if ($this->userMode === 'edit' && $this->editingUserId) {
            $this->validate([
                'u_name' => ['required', 'string', 'max:255'],
                'u_email' => ['nullable', 'email', 'max:255'],
                'u_group_id' => ['required', 'integer', 'exists:tm2sys.sec_group,id_group'],
            ]);

            $user = SecUser::findOrFail($this->editingUserId);
            $email = $this->u_email ?: null;
            // Alamat berubah = verifikasi lama gugur (user harus verifikasi ulang, lihat v2 Profil > E-mail).
            $reverify = strcasecmp((string) $user->email, (string) $email) !== 0 ? ['email_verified' => false, 'email_verified_at' => null] : [];
            $user->update(['name' => $this->u_name, 'email' => $email] + $reverify);
            $user->groups()->sync([$this->u_group_id]);
        } else {
            $this->validate([
                'u_login' => ['required', 'string', 'max:190', 'unique:tm2sys.sec_user,login'],
                'u_name' => ['required', 'string', 'max:255'],
                'u_email' => ['nullable', 'email', 'max:255'],
                'u_pswd' => ['required', 'string', 'min:8'],
                'u_group_id' => ['required', 'integer', 'exists:tm2sys.sec_group,id_group'],
            ]);

            $user = SecUser::create([
                'login' => $this->u_login,
                'name' => $this->u_name,
                'email' => $this->u_email ?: null,
                'pswd' => Hash::make($this->u_pswd),
                'active' => true,
                'activate' => true,
            ]);

            $user->groups()->attach($this->u_group_id);
        }

        unset($this->users, $this->userStatTotal, $this->userStatActive, $this->userStatMustChange, $this->userStatInactive);
        $this->userView = 'grid';
    }

    public function toggleUserActive(int $userId): void
    {
        // Admin tak boleh menonaktifkan akun sendiri — kunci diri sendiri keluar.
        if ($userId === Auth::id()) {
            return;
        }

        $user = SecUser::findOrFail($userId);
        $user->update(['activate' => ! $user->activate]);
        unset($this->users, $this->userStatActive, $this->userStatInactive);
    }

    // Password default yang baru saja di-reset — ditampilkan SEKALI di layar
    // (session, bukan disimpan di properti Livewire supaya tak ikut di-dehydrate
    // ke request berikutnya). security_defaultpassword_spec.md §0/§4.
    public ?int $revealUserId = null;

    public function resetPassword(int $userId): void
    {
        $user = SecUser::findOrFail($userId);
        $plain = app(PasswordResetService::class)->reset($user);

        session()->flash('resetPlain_'.$userId, $plain);
        $this->revealUserId = $userId;
        unset($this->users, $this->userStatMustChange);
    }

    // ================= GRUP =================
    // REVISI 2026-08-30: pola sama persis dgn PENGGUNA di atas (Grid Master
    // standar, swap grid<->form) -- instruksi user "begitu pula dengan menu
    // lainnya, pertimbangkan dengan UI yang sama". groupOptions() (TANPA
    // filter search) dipakai khusus utk dropdown pemilih grup (form Pengguna
    // & panel Hak Akses Grup) -- TERPISAH dari groups() yg search-filtered,
    // supaya dropdown itu tak ikut kosong saat user lagi ngetik di search grid.
    public string $groupSearch = '';

    public string $groupView = 'grid'; // grid | form

    public string $groupMode = 'new'; // new | edit

    public ?int $editingGroupId = null;

    public string $g_code = '';

    public string $g_name = '';

    public int $g_level = 1;

    public ?string $groupMessage = null;

    #[Computed]
    public function groups()
    {
        return SecGroup::withCount('users')
            ->when($this->groupSearch !== '', fn ($q) => $q->where(fn ($qq) => $qq
                ->where('group_code', 'like', "%{$this->groupSearch}%")
                ->orWhere('group_name', 'like', "%{$this->groupSearch}%")))
            ->orderBy('group_name')
            ->get();
    }

    #[Computed]
    public function groupOptions()
    {
        return SecGroup::where('activate', true)->orderBy('group_name')->get();
    }

    #[Computed]
    public function groupStatTotal(): int
    {
        return SecGroup::count();
    }

    #[Computed]
    public function groupStatFilled(): int
    {
        return SecGroup::has('users')->count();
    }

    #[Computed]
    public function groupStatEmpty(): int
    {
        return SecGroup::doesntHave('users')->count();
    }

    public function openGroupForm(string $mode, ?int $id = null): void
    {
        $this->resetErrorBag();
        $this->groupMessage = null;
        $this->groupMode = in_array($mode, ['new', 'edit'], true) ? $mode : 'new';
        $this->editingGroupId = $id;

        if ($this->groupMode === 'edit' && $id) {
            $g = SecGroup::findOrFail($id);
            $this->g_code = $g->group_code;
            $this->g_name = $g->group_name;
            $this->g_level = $g->mgmt_level;
        } else {
            $this->reset(['g_code', 'g_name']);
            $this->g_level = 1;
        }

        $this->groupView = 'form';
    }

    public function showGroupGrid(): void
    {
        $this->groupView = 'grid';
    }

    public function saveGroup(): void
    {
        if ($this->groupMode === 'edit' && $this->editingGroupId) {
            $this->validate([
                'g_name' => ['required', 'string', 'max:100'],
                'g_level' => ['required', 'integer', 'min:1', 'max:5'],
            ]);

            SecGroup::findOrFail($this->editingGroupId)->update([
                'group_name' => $this->g_name,
                'mgmt_level' => $this->g_level,
            ]);
        } else {
            $this->validate([
                'g_code' => ['required', 'string', 'max:40', 'unique:tm2sys.sec_group,group_code'],
                'g_name' => ['required', 'string', 'max:100'],
                'g_level' => ['required', 'integer', 'min:1', 'max:5'],
            ]);

            SecGroup::create([
                'group_code' => strtoupper(str_replace(' ', '_', $this->g_code)),
                'group_name' => $this->g_name,
                'mgmt_level' => $this->g_level,
                'activate' => true,
            ]);
        }

        unset($this->groups, $this->groupOptions, $this->groupStatTotal, $this->groupStatFilled, $this->groupStatEmpty);
        $this->groupView = 'grid';
    }

    public function toggleGroupActive(int $groupId): void
    {
        $g = SecGroup::findOrFail($groupId);
        $g->update(['activate' => ! $g->activate]);
        unset($this->groups, $this->groupOptions, $this->groupStatFilled, $this->groupStatEmpty);
    }

    // ================= HAK AKSES GRUP =================
    public ?int $permGroupId = null;

    public function mount(): void
    {
        $this->permGroupId = SecGroup::query()->orderBy('group_name')->value('id_group');

        if (! in_array($this->activePanel, ['users', 'groups', 'perms', 'migration'], true)) {
            $this->activePanel = 'users';
        }
    }

    #[Computed]
    public function modulesTree()
    {
        return SecModule::query()
            ->whereNull('parent_id')
            ->where('activate', true)
            ->orderBy('sort_order')
            ->with(['children' => fn ($q) => $q->where('activate', true)->orderBy('sort_order')])
            ->get();
    }

    #[Computed]
    public function currentPermissions()
    {
        if (! $this->permGroupId) {
            return collect();
        }

        return SecGroupPermission::where('group_id', $this->permGroupId)->get()->keyBy('module_id');
    }

    // BRIEF §4: readonly = can_access=1 & can_input=0 (bukan flag terpisah) —
    // makanya toggle can_access mati otomatis mematikan 3 lainnya juga.
    public function togglePermission(int $moduleId, string $field): void
    {
        if (! in_array($field, ['can_access', 'can_input', 'can_approve', 'can_export'], true)) {
            return;
        }

        $perm = SecGroupPermission::firstOrNew([
            'group_id' => $this->permGroupId,
            'module_id' => $moduleId,
        ]);

        $perm->$field = ! $perm->$field;

        if ($field === 'can_access' && ! $perm->can_access) {
            $perm->can_input = false;
            $perm->can_approve = false;
            $perm->can_export = false;
        }

        $perm->save();
        unset($this->currentPermissions);
    }

    public function setPanel(string $panel): void
    {
        if (in_array($panel, ['users', 'groups', 'perms', 'migration'], true)) {
            $this->activePanel = $panel;
        }
    }

    // ================= MIGRASI DATA LEGACY =================
    // Engine baca tm2old.mst_vendor* (dump ScriptCase V1, diimpor manual oleh
    // Jo) -> tulis cust_customer/sup_supplier tm2mst. Idempotent, aman diklik
    // ulang. Laporan hasil TIDAK disimpan ke session — cukup properti Livewire
    // biasa (hilang saat reload halaman, itu memang yang diharapkan).
    public ?array $migrationReport = null;

    public function runVendorMigration(): void
    {
        $this->migrationReport = app(VendorMigrationService::class)->run(Auth::user()->login);
    }

    public ?array $employeeMigrationReport = null;

    public function runEmployeeMigration(): void
    {
        $this->employeeMigrationReport = app(EmployeeMigrationService::class)->run(Auth::user()->login);
    }

    public ?array $rawMaterialMigrationReport = null;

    public function runRawMaterialMigration(): void
    {
        $this->rawMaterialMigrationReport = app(\App\Services\Migration\RawMaterialMigrationService::class)->run(Auth::user()->login);
    }

    public ?array $productMigrationReport = null;

    public function runProductMigration(): void
    {
        $this->productMigrationReport = app(\App\Services\Migration\ProductMigrationService::class)->run(Auth::user()->login);
    }

    public ?array $coOrderMigrationReport = null;

    public function runCoOrderMigration(): void
    {
        $this->coOrderMigrationReport = app(CustomerOrderMigrationService::class)->run(Auth::user()->login);
    }

    public ?array $poOrderMigrationReport = null;

    public function runPoOrderMigration(): void
    {
        $this->poOrderMigrationReport = app(PurchaseOrderMigrationService::class)->run(Auth::user()->login);
    }

    public ?array $prMigrationReport = null;

    public function runPrMigration(): void
    {
        $this->prMigrationReport = app(\App\Services\Migration\PurchaseRequisitionMigrationService::class)->run(Auth::user()->login);
    }

    public ?array $receiveMigrationReport = null;

    public function runReceiveMigration(): void
    {
        $this->receiveMigrationReport = app(\App\Services\Migration\PurchaseReceiveMigrationService::class)->run(Auth::user()->login);
    }

    public ?array $paymentMigrationReport = null;

    public function runPaymentMigration(): void
    {
        $this->paymentMigrationReport = app(\App\Services\Migration\PurchasePaymentMigrationService::class)->run(Auth::user()->login);
    }

    public ?array $dnoteMigrationReport = null;

    public function runDnoteMigration(): void
    {
        $this->dnoteMigrationReport = app(\App\Services\Migration\DeliveryNoteMigrationService::class)->run(Auth::user()->login);
    }

    public ?array $cinvMigrationReport = null;

    public function runCinvMigration(): void
    {
        $this->cinvMigrationReport = app(\App\Services\Migration\CustomerInvoiceMigrationService::class)->run(Auth::user()->login);
    }

    public ?array $uomMigrationReport = null;

    public function runUomMigration(): void
    {
        $this->uomMigrationReport = app(\App\Services\Migration\UomMigrationService::class)->run(Auth::user()->login);
    }

    public ?array $lookupMigrationReport = null;

    public function runLookupMigration(): void
    {
        $this->lookupMigrationReport = app(\App\Services\Migration\SupportingLookupMigrationService::class)->run(Auth::user()->login);
    }

    public ?array $masterCardMigrationReport = null;

    public function runMasterCardMigration(): void
    {
        $this->masterCardMigrationReport = app(\App\Services\Migration\MasterCardMigrationService::class)->run(Auth::user()->login);
    }

    public ?array $productImageReport = null;

    public function runProductImageIngest(): void
    {
        $this->productImageReport = app(\App\Services\Migration\ProductImageIngestService::class)->run(Auth::user()->login);
    }

    public ?array $productEnrichmentReport = null;

    public function runProductEnrichment(): void
    {
        $this->productEnrichmentReport = app(\App\Services\Migration\ProductEnrichmentService::class)->run(Auth::user()->login);
    }

    public ?array $loginProvisionReport = null;

    public bool $loginProvisionRunning = false;

    public function runEmployeeLoginProvision(): void
    {
        $this->loginProvisionRunning = true;
        $this->loginProvisionReport = app(EmployeeLoginProvisionService::class)->run(Auth::user()->login);
        $this->loginProvisionRunning = false;
        unset($this->users);
    }

    // Lihat ULANG default_password user yang sudah ada (BUKAN reset/timpa) --
    // dipakai buat spot-check akun karyawan hasil provisioning massal, tanpa
    // mengubah passwordnya. Reuse bucket tampilan yang sama dgn resetPassword().
    public function revealPassword(int $userId): void
    {
        $user = SecUser::findOrFail($userId);

        if ($user->default_password) {
            session()->flash('resetPlain_'.$userId, $user->default_password);
            $this->revealUserId = $userId;
        }
    }

    public function logout()
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }
};
?>

<div class="secpage">
    {{-- class "sp-shell"/"sp-content" (BUKAN "shell"/"content" polos) --
         nama polos itu SUDAH dipakai class global outer app shell
         (main-menu.blade.php, lihat .shell{h-screen overflow-hidden} dan
         .shell .content{overflow-hidden} di app.css) -- kalau dipakai lagi
         di sini, div ini ikut kena overflow-hidden+h-screen yang TIDAK
         dimaksud (padahal beda dokumen/iframe), memotong konten yang lebih
         tinggi dari layar (mis. "Daftar pengguna") tanpa bisa discroll sama
         sekali. Ditemukan 2026-08-30 dari laporan user (tabel kepotong,
         "tidak ada tombol scrolling") -- collision SIS class polos, bukan
         .modal seperti dicatat di komentar §Security/Setup di atas. --}}
    <div class="sp-shell">
        <div class="sp-content">
            {{-- 1. Pengguna -- Grid Master standar (docs/griduistandard.md), revisi 2026-08-30 --}}
            <section class="panel {{ $activePanel === 'users' ? 'active' : '' }}">
                <h1>{{ t('User') }}</h1><p class="desc">{{ t('Manage the accounts that can sign in to the application.') }}</p>

                @if ($userView === 'grid')
                    <x-grid.stat-row :count="4">
                        <x-grid.stat-box tone="total" :label="t('Total Users')" :value="$this->userStatTotal" />
                        <x-grid.stat-box tone="active" :label="t('Active')" :value="$this->userStatActive" />
                        <x-grid.stat-box tone="warn" :label="t('Must Change Password')" :value="$this->userStatMustChange" />
                        <x-grid.stat-box tone="critical" :label="t('Inactive')" :value="$this->userStatInactive" />
                    </x-grid.stat-row>

                    @if ($userMessage)
                        <div class="text-xs px-3 py-2 border border-[#c8ecd6] bg-[#e9f9ef] text-[#2f7a52] rounded-md mb-3">✅ {{ $userMessage }}</div>
                    @endif

                    <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
                        <x-grid.toolbar>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <span class="text-sm">🔍</span>
                                <div class="w-56 shrink-0"><x-form.input :case-toggle="false" wire:model.live.debounce.300ms="userSearch" :placeholder="t('Search username / name / email…')" /></div>
                            </div>
                            <x-slot:newRecord>
                                <button type="button" wire:click="openUserForm('new')"
                                    class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
                            </x-slot:newRecord>
                        </x-grid.toolbar>

                        <x-grid.table-shell>
                            <table class="w-full border-collapse text-[12.5px]">
                                <thead>
                                    <tr>
                                        <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('User') }}</th>
                                        <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('Group') }}</th>
                                        <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[90px]">{{ t('Status') }}</th>
                                        <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[150px]">{{ t('Password') }}</th>
                                        <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[160px]">{{ t('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($this->users as $u)
                                        <tr wire:key="user-{{ $u->id_user }}" wire:dblclick="openUserForm('edit', {{ $u->id_user }})" class="hover:bg-[#f7faff] cursor-pointer" title="{{ t('Double-click to open details') }}">
                                            <x-grid.identity-cell :id="$u->id_user" :name="$u->name" :code="$u->login" />
                                            <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $u->groups->pluck('group_name')->implode(', ') ?: '—' }}{{ $u->is_admin ? ' ('.t('Admin').')' : '' }}</td>
                                            <td class="px-3 py-2 border-b border-[#eef1f5]">
                                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block {{ $u->activate ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#eef0f3] text-[#8a8f98]' }}">{{ $u->activate ? t('ACTIVE') : t('INACTIVE') }}</span>
                                            </td>
                                            <td class="px-3 py-2 border-b border-[#eef1f5]">
                                                @if (session()->has('resetPlain_'.$u->id_user))
                                                    <span class="text-[11px] font-bold text-[#1f8a4c]">{{ t('New:') }} <code>{{ session('resetPlain_'.$u->id_user) }}</code></span>
                                                @elseif ($u->must_change_password)
                                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block bg-[#fef6e7] text-[#8a6d00]">{{ t('Must change') }}</span>
                                                @else
                                                    <span class="text-[11px] text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                                <x-grid.action-icon icon="fa-pen" :title="t('Edit')" action="openUserForm('edit', {{ $u->id_user }})" />
                                                <x-grid.action-icon icon="fa-key" :title="t('Reset Password')" action="resetPassword({{ $u->id_user }})"
                                                    :confirm="str_replace(':user', $u->login, t('Reset the password of :user to a new password?'))" />
                                                @if ($u->default_password)
                                                    <x-grid.action-icon icon="fa-eye" :title="t('View Password')" action="revealPassword({{ $u->id_user }})" />
                                                @endif
                                                @if ($u->id_user !== Auth::id())
                                                    <x-grid.action-icon :active="$u->activate" :title="$u->activate ? t('Deactivate') : t('Activate')" action="toggleUserActive({{ $u->id_user }})"
                                                        :confirm="str_replace(':user', $u->login, $u->activate ? t('Deactivate user :user?') : t('Activate user :user?'))"
                                                        :variant="$u->activate ? 'danger' : 'default'" />
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <x-grid.empty-row :colspan="5" :message="$userSearch ? t('No users for this search.') : t('No users.')" />
                                    @endforelse
                                </tbody>
                            </table>
                        </x-grid.table-shell>
                    </div>
                @else
                    {{-- FORM Pengguna (New/Edit) --}}
                    <div class="flex justify-between items-center bg-navy text-white px-4 py-3 rounded-t-md">
                        <div class="text-sm font-bold">
                            {{ $userMode === 'edit' ? t('Edit User') : t('New User') }}
                            @if ($userMode === 'edit')<span class="text-xs font-normal opacity-75 ml-2">{{ $u_login }}</span>@endif
                        </div>
                    </div>
                    <form wire:submit="saveUser" class="bg-white border border-t-0 border-[#d7dbe2] p-5 rounded-b-md">
                        <div class="form-grid">
                            <x-form.input legacy="fld" :label="t('Username')" wire:model="u_login" :placeholder="t('e.g.').' andi.pratama'" name="u_login" @readonly($userMode === 'edit') />
                            <x-form.input legacy="fld" :label="t('Full name')" wire:model="u_name" placeholder="Andi Pratama" name="u_name" />
                            <x-form.input legacy="fld" :label="t('Email')" wire:model="u_email" placeholder="andi@telasih.co.id" name="u_email" />
                            @if ($userMode === 'new')
                                <x-form.input legacy="fld" :label="t('Initial password')" type="password" wire:model="u_pswd" :placeholder="t('Minimum 8 characters')" name="u_pswd" />
                            @endif
                            <x-form.select legacy="fld" :label="t('Group')" wire:model="u_group_id" name="u_group_id"
                                :placeholder="t('— select group —')" :options="$this->groupOptions->pluck('group_name', 'id_group')->all()" :selected="$u_group_id" />
                        </div>
                        @if ($userMode === 'edit')
                            <p class="note">{{ t('Change the password with the 🔑 "Reset Password" action in the grid, not here.') }}</p>
                        @endif
                        <div class="actions">
                            <button type="submit" class="btn btn-primary">{{ t('Save') }}</button>
                            <button type="button" wire:click="showUserGrid" class="btn btn-ghost">{{ t('Cancel') }}</button>
                        </div>
                    </form>
                @endif
            </section>

            {{-- 2. Grup -- Grid Master standar, sama pola dgn Pengguna di atas --}}
            <section class="panel {{ $activePanel === 'groups' ? 'active' : '' }}">
                <h1>{{ t('Group') }}</h1><p class="desc">{{ t('A group holds a set of users who share the same access rights.') }}</p>

                @if ($groupView === 'grid')
                    <x-grid.stat-row :count="3">
                        <x-grid.stat-box tone="total" :label="t('Total Groups')" :value="$this->groupStatTotal" />
                        <x-grid.stat-box tone="active" :label="t('Filled (≥1 User)')" :value="$this->groupStatFilled" />
                        <x-grid.stat-box tone="warn" :label="t('Empty')" :value="$this->groupStatEmpty" />
                    </x-grid.stat-row>

                    @if ($groupMessage)
                        <div class="text-xs px-3 py-2 border border-[#c8ecd6] bg-[#e9f9ef] text-[#2f7a52] rounded-md mb-3">✅ {{ $groupMessage }}</div>
                    @endif

                    <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
                        <x-grid.toolbar>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <span class="text-sm">🔍</span>
                                <div class="w-56 shrink-0"><x-form.input :case-toggle="false" wire:model.live.debounce.300ms="groupSearch" :placeholder="t('Search group code / name…')" /></div>
                            </div>
                            <x-slot:newRecord>
                                <button type="button" wire:click="openGroupForm('new')"
                                    class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
                            </x-slot:newRecord>
                        </x-grid.toolbar>

                        <x-grid.table-shell>
                            <table class="w-full border-collapse text-[12.5px]">
                                <thead>
                                    <tr>
                                        <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('Group') }}</th>
                                        <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">{{ t('Level') }}</th>
                                        <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">{{ t('User Count') }}</th>
                                        <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[90px]">{{ t('Status') }}</th>
                                        <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">{{ t('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($this->groups as $g)
                                        <tr wire:key="group-{{ $g->id_group }}" wire:dblclick="openGroupForm('edit', {{ $g->id_group }})" class="hover:bg-[#f7faff] cursor-pointer" title="{{ t('Double-click to open details') }}">
                                            <x-grid.identity-cell :id="$g->id_group" :name="$g->group_name" :code="$g->group_code" />
                                            <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $g->mgmt_level }}</td>
                                            <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $g->users_count }}</td>
                                            <td class="px-3 py-2 border-b border-[#eef1f5]">
                                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block {{ $g->activate ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#eef0f3] text-[#8a8f98]' }}">{{ $g->activate ? t('ACTIVE') : t('INACTIVE') }}</span>
                                            </td>
                                            <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                                <x-grid.action-icon icon="fa-pen" :title="t('Edit')" action="openGroupForm('edit', {{ $g->id_group }})" />
                                                <x-grid.action-icon :active="$g->activate" :title="$g->activate ? t('Deactivate') : t('Activate')" action="toggleGroupActive({{ $g->id_group }})"
                                                    :confirm="str_replace(':group', $g->group_name, $g->activate ? t('Deactivate group :group?') : t('Activate group :group?'))"
                                                    :variant="$g->activate ? 'danger' : 'default'" />
                                            </td>
                                        </tr>
                                    @empty
                                        <x-grid.empty-row :colspan="5" :message="$groupSearch ? t('No groups for this search.') : t('No groups.')" />
                                    @endforelse
                                </tbody>
                            </table>
                        </x-grid.table-shell>
                    </div>
                @else
                    {{-- FORM Grup (New/Edit) --}}
                    <div class="flex justify-between items-center bg-navy text-white px-4 py-3 rounded-t-md">
                        <div class="text-sm font-bold">
                            {{ $groupMode === 'edit' ? t('Edit Group') : t('New Group') }}
                            @if ($groupMode === 'edit')<span class="text-xs font-normal opacity-75 ml-2">{{ $g_code }}</span>@endif
                        </div>
                    </div>
                    <form wire:submit="saveGroup" class="bg-white border border-t-0 border-[#d7dbe2] p-5 rounded-b-md">
                        <div class="form-grid">
                            <x-form.input legacy="fld" :label="t('Group code')" wire:model="g_code" :placeholder="t('e.g.').' PRODUKSI_SPV'" name="g_code" @readonly($groupMode === 'edit') />
                            <x-form.input legacy="fld" :label="t('Group name')" wire:model="g_name" :placeholder="t('e.g. Production Supervisor')" name="g_name" />
                            <x-form.select legacy="fld" :label="t('Management level (1=Staff .. 5=Directors)')" wire:model="g_level"
                                :placeholder="false" :options="[1 => '1 - '.t('Staff'), 2 => '2 - '.t('Supervisor'), 3 => '3 - '.t('Manager'), 4 => '4 - '.t('GM'), 5 => '5 - '.t('Directors')]" :selected="$g_level" />
                        </div>
                        <div class="actions">
                            <button type="submit" class="btn btn-primary">{{ t('Save') }}</button>
                            <button type="button" wire:click="showGroupGrid" class="btn btn-ghost">{{ t('Cancel') }}</button>
                        </div>
                    </form>
                @endif
            </section>

            {{-- 3. Hak Akses Grup --}}
            <section class="panel {{ $activePanel === 'perms' ? 'active' : '' }}">
                <h1>{{ t('Group Access Rights') }}</h1><p class="desc">{{ t('Select a group, then set access per module (parent → sub hierarchy).') }}</p>

                <div class="box">
                    <div style="max-width:320px">
                        <x-form.select legacy="fld" :label="t('Group')" wire:model.live="permGroupId"
                            :placeholder="false" :options="$this->groupOptions->pluck('group_name', 'id_group')->all()" :selected="$permGroupId" />
                    </div>
                </div>

                <div class="box">
                    <h3>{{ t('Permissions per module') }}</h3>
                    <table>
                        <thead><tr><th>{{ t('Module') }}</th><th style="width:90px">{{ t('Access') }}</th><th style="width:90px">{{ t('Input') }}</th><th style="width:90px">{{ t('Approve') }}</th><th style="width:90px">{{ t('Export') }}</th></tr></thead>
                        <tbody>
                            @foreach ($this->modulesTree as $m)
                                @php $p = $this->currentPermissions->get($m->id_module); @endphp
                                <tr wire:key="perm-mod-{{ $m->id_module }}">
                                    <td>{{ $m->icon }} {{ $m->module_name }}</td>
                                    @foreach (['can_access', 'can_input', 'can_approve', 'can_export'] as $field)
                                        <td>
                                            <label class="switch">
                                                <input type="checkbox" {{ $p && $p->$field ? 'checked' : '' }} wire:click="togglePermission({{ $m->id_module }}, '{{ $field }}')">
                                                <span class="slider"></span>
                                            </label>
                                        </td>
                                    @endforeach
                                </tr>
                                @foreach ($m->children as $sub)
                                    @php $sp = $this->currentPermissions->get($sub->id_module); @endphp
                                    <tr wire:key="perm-mod-{{ $sub->id_module }}">
                                        <td class="sub-indent">↳ {{ $sub->icon }} {{ $sub->module_name }} <span class="pill sub">{{ t('min lvl') }} {{ $sub->min_mgmt_level }}</span></td>
                                        @foreach (['can_access', 'can_input', 'can_approve', 'can_export'] as $field)
                                            <td>
                                                <label class="switch">
                                                    <input type="checkbox" {{ $sp && $sp->$field ? 'checked' : '' }} wire:click="togglePermission({{ $sub->id_module }}, '{{ $field }}')">
                                                    <span class="slider"></span>
                                                </label>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                    <p class="note">{{ t('Readonly = Access on, Input off. Turning Access off automatically turns off Input/Approve/Export') }} (BRIEF §4).</p>
                </div>
            </section>

            {{-- 4. Migrasi Data Legacy --}}
            <section class="panel {{ $activePanel === 'migration' ? 'active' : '' }}">
                <h1>{{ t('Legacy Data Migration') }}</h1>
                <p class="desc">{{ t('Pull old data (ScriptCase V1) from staging') }} <code>tm2old</code> {{ t('into the Customer Master & Supplier Master.') }} {{ t('Safe to run repeatedly — existing data is updated, not duplicated.') }}</p>

                <div class="box">
                    <h3>tm2old.mst_vendor → cust_customer &amp; sup_supplier</h3>
                    <p class="note">{{ t('Source:') }} <code>mst_vendor</code> (1.160 {{ t('rows') }}), <code>mst_vendor_bank</code>, <code>mst_vendor_customer_category</code>, <code>mst_vendor_supplier_category</code>. {{ t('Rows with') }} <code>as_customer=1</code> {{ t('go to the Customer Master,') }} <code>as_supplier=1</code> {{ t('go to the Supplier Master — a vendor can go to both.') }}</p>
                    <div class="actions">
                        <button type="button" class="btn btn-primary" wire:click="runVendorMigration" wire:loading.attr="disabled"
                            wire:confirm="{{ t('Run the data migration from tm2old.mst_vendor now?') }}">
                            <span wire:loading.remove wire:target="runVendorMigration">▶ {{ t('Run Migration') }}</span>
                            <span wire:loading wire:target="runVendorMigration">{{ t('Processing…') }}</span>
                        </button>
                    </div>

                    @if ($migrationReport)
                        <div style="margin-top:16px">
                            <p class="note" style="color:var(--sp-ok)">✅ {{ t('Migration finished.') }}</p>
                            <table>
                                <thead><tr><th>{{ t('Section') }}</th><th>{{ t('Result') }}</th></tr></thead>
                                <tbody>
                                    <tr><td>{{ t('Customer / supplier category') }}</td><td>{{ $migrationReport['categories']['cust'] }} / {{ $migrationReport['categories']['sup'] }} {{ t('codes') }}</td></tr>
                                    <tr><td>{{ t('Customer Master') }}</td><td>{{ $migrationReport['customers']['created'] }} {{ t('new,') }} {{ $migrationReport['customers']['updated'] }} {{ t('updated') }}</td></tr>
                                    <tr><td>{{ t('Supplier Master') }}</td><td>{{ $migrationReport['suppliers']['created'] }} {{ t('new,') }} {{ $migrationReport['suppliers']['updated'] }} {{ t('updated') }}</td></tr>
                                    <tr><td>{{ t('Bank account') }}</td><td>{{ $migrationReport['banks']['cust'] }} {{ t('customer') }}, {{ $migrationReport['banks']['sup'] }} {{ t('supplier') }}{{ $migrationReport['banks']['skipped'] ? ', '.$migrationReport['banks']['skipped'].' '.t('skipped (vendor not found)') : '' }}</td></tr>
                                    <tr><td>{{ t('City matched automatically') }}</td><td>{{ $migrationReport['city']['matched'] }} {{ t('rows') }}</td></tr>
                                </tbody>
                            </table>

                            @if (! empty($migrationReport['city']['unmatched']))
                                <div style="margin-top:12px">
                                    <p class="note" style="color:var(--sp-danger)">⚠ {{ count($migrationReport['city']['unmatched']) }} {{ t('city names do not match') }} <code>geo_city</code> {{ t('(left empty, fill in manually in the Customer/Supplier Master):') }}</p>
                                    <p class="note">{{ implode(', ', array_map(fn ($name, $cnt) => "{$name} ({$cnt})", array_keys($migrationReport['city']['unmatched']), $migrationReport['city']['unmatched'])) }}</p>
                                </div>
                            @endif

                            @if (! empty($migrationReport['errors']))
                                <div style="margin-top:12px">
                                    <p class="note" style="color:var(--sp-danger)">⚠ {{ count($migrationReport['errors']) }} {{ t('rows failed to process:') }}</p>
                                    <ul style="font-size:12px;color:var(--sp-danger);margin:4px 0 0;padding-left:18px">
                                        @foreach ($migrationReport['errors'] as $err)
                                            <li>{{ $err }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="box" style="margin-top:16px">
                    <h3>tm2old.mst_item ({{ t('raw material') }}) → mat_raw</h3>
                    <p class="note">{{ t('Criteria:') }} <code>material_class='RAW MATERIAL'</code> {{ t('or') }} <code>as_mat=1</code>. {{ t('Item codes >60 characters are truncated + given a numeric suffix for') }} <code>material_code</code> ({{ t('the original text stays intact in') }} <code>old_item_code</code>).</p>
                    <div class="actions">
                        <button type="button" class="btn btn-primary" wire:click="runRawMaterialMigration" wire:loading.attr="disabled"
                            wire:confirm="{{ t('Run the raw material migration from tm2old.mst_item now?') }}">
                            <span wire:loading.remove wire:target="runRawMaterialMigration">▶ {{ t('Run Migration') }}</span>
                            <span wire:loading wire:target="runRawMaterialMigration">{{ t('Processing…') }}</span>
                        </button>
                    </div>

                    @if ($rawMaterialMigrationReport)
                        <div style="margin-top:16px">
                            <p class="note" style="color:var(--sp-ok)">✅ {{ t('Migration finished.') }}</p>
                            <table>
                                <thead><tr><th>{{ t('Section') }}</th><th>{{ t('Result') }}</th></tr></thead>
                                <tbody>
                                    <tr><td>{{ t('Raw Material Master') }}</td><td>{{ $rawMaterialMigrationReport['materials']['created'] }} {{ t('new,') }} {{ $rawMaterialMigrationReport['materials']['updated'] }} {{ t('updated,') }} {{ $rawMaterialMigrationReport['materials']['unchanged'] }} {{ t('unchanged') }}</td></tr>
                                </tbody>
                            </table>

                            @if (! empty($rawMaterialMigrationReport['skipped_too_long']))
                                <div style="margin-top:12px">
                                    <p class="note" style="color:var(--sp-danger)">⚠ {{ count($rawMaterialMigrationReport['skipped_too_long']) }} {{ t('item codes >60 characters, regenerated (manual check recommended):') }}</p>
                                    <p class="note">{{ implode(', ', array_slice($rawMaterialMigrationReport['skipped_too_long'], 0, 10)) }}{{ count($rawMaterialMigrationReport['skipped_too_long']) > 10 ? '…' : '' }}</p>
                                </div>
                            @endif

                            @if (! empty($rawMaterialMigrationReport['errors']))
                                <div style="margin-top:12px">
                                    <p class="note" style="color:var(--sp-danger)">⚠ {{ count($rawMaterialMigrationReport['errors']) }} {{ t('rows failed to process:') }}</p>
                                    <ul style="font-size:12px;color:var(--sp-danger);margin:4px 0 0;padding-left:18px">
                                        @foreach ($rawMaterialMigrationReport['errors'] as $err)
                                            <li>{{ $err }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="box" style="margin-top:16px">
                    <h3>tm2old.mst_item ({{ t('finished goods') }}) → prod_product</h3>
                    <p class="note">{{ t('Criteria:') }} <code>as_fgs=1</code>. {{ t('Physical dimensions & customer/category are DELIBERATELY not migrated (source units unclear/inconsistent) — fill in manually through ProDev when the product is used.') }}</p>
                    <div class="actions">
                        <button type="button" class="btn btn-primary" wire:click="runProductMigration" wire:loading.attr="disabled"
                            wire:confirm="{{ t('Run the product migration from tm2old.mst_item now?') }}">
                            <span wire:loading.remove wire:target="runProductMigration">▶ {{ t('Run Migration') }}</span>
                            <span wire:loading wire:target="runProductMigration">{{ t('Processing…') }}</span>
                        </button>
                    </div>

                    @if ($productMigrationReport)
                        <div style="margin-top:16px">
                            <p class="note" style="color:var(--sp-ok)">✅ {{ t('Migration finished.') }}</p>
                            <table>
                                <thead><tr><th>{{ t('Section') }}</th><th>{{ t('Result') }}</th></tr></thead>
                                <tbody>
                                    <tr><td>{{ t('Product Master') }}</td><td>{{ $productMigrationReport['products']['created'] }} {{ t('new,') }} {{ $productMigrationReport['products']['updated'] }} {{ t('updated,') }} {{ $productMigrationReport['products']['unchanged'] }} {{ t('unchanged') }}</td></tr>
                                </tbody>
                            </table>

                            @if (! empty($productMigrationReport['errors']))
                                <div style="margin-top:12px">
                                    <p class="note" style="color:var(--sp-danger)">⚠ {{ count($productMigrationReport['errors']) }} {{ t('rows failed to process:') }}</p>
                                    <ul style="font-size:12px;color:var(--sp-danger);margin:4px 0 0;padding-left:18px">
                                        @foreach ($productMigrationReport['errors'] as $err)
                                            <li>{{ $err }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="box" style="margin-top:16px">
                    <h3>tm2old.sdi_co(_detail) → co_order_legacy ({{ t('history archive, NOT') }} co_order)</h3>
                    <p class="note">{{ t('co_order requires a Quotation lineage (1 CO = 1 Quotation) for the new flow — the 20.669 old COs never had one.') }} {{ t('Archived to a separate, read-only table, only for dashboards/reports (e.g. total spend per customer).') }} {{ t('Idempotent: full refresh on every run.') }}</p>
                    <div class="actions">
                        <button type="button" class="btn btn-primary" wire:click="runCoOrderMigration" wire:loading.attr="disabled"
                            wire:confirm="{{ t('Run the Customer Order archive migration from tm2old.sdi_co now?') }}">
                            <span wire:loading.remove wire:target="runCoOrderMigration">▶ {{ t('Run Migration') }}</span>
                            <span wire:loading wire:target="runCoOrderMigration">{{ t('Processing…') }}</span>
                        </button>
                    </div>

                    @if ($coOrderMigrationReport)
                        <div style="margin-top:16px">
                            <p class="note" style="color:var(--sp-ok)">✅ {{ t('Migration finished.') }}</p>
                            <table>
                                <thead><tr><th>{{ t('Section') }}</th><th>{{ t('Result') }}</th></tr></thead>
                                <tbody>
                                    <tr><td>{{ t('CO header') }}</td><td>{{ $coOrderMigrationReport['headers']['inserted'] }} {{ t('rows') }}</td></tr>
                                    <tr><td>{{ t('CO item lines') }}</td><td>{{ $coOrderMigrationReport['details']['inserted'] }} {{ t('rows') }} ({{ $coOrderMigrationReport['details']['no_product_match'] }} {{ t('without a Product Master match, falling back to the old code') }})</td></tr>
                                </tbody>
                            </table>

                            @if (! empty($coOrderMigrationReport['customer_unmatched']))
                                <div style="margin-top:12px">
                                    <p class="note" style="color:var(--sp-danger)">⚠ {{ count($coOrderMigrationReport['customer_unmatched']) }} {{ t('customer codes without a Customer Master match:') }}</p>
                                    <p class="note">{{ implode(', ', array_slice(array_keys($coOrderMigrationReport['customer_unmatched']), 0, 15)) }}{{ count($coOrderMigrationReport['customer_unmatched']) > 15 ? '…' : '' }}</p>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="box" style="margin-top:16px">
                    <h3>tm2old.pur_po(_v2)+pur_po_detail → po_order_legacy ({{ t('history archive, NOT') }} po_order)</h3>
                    <p class="note">{{ t('Header from pur_po_v2 (more complete tax), lines from pur_po_detail (v1) — joined by PO number.') }} {{ t('Archived to a separate, read-only table, only for dashboards/reports (e.g. total AP per supplier).') }} {{ t('Idempotent: full refresh on every run.') }}</p>
                    <div class="actions">
                        <button type="button" class="btn btn-primary" wire:click="runPoOrderMigration" wire:loading.attr="disabled"
                            wire:confirm="{{ t('Run the Purchase Order archive migration from tm2old.pur_po now?') }}">
                            <span wire:loading.remove wire:target="runPoOrderMigration">▶ {{ t('Run Migration') }}</span>
                            <span wire:loading wire:target="runPoOrderMigration">{{ t('Processing…') }}</span>
                        </button>
                    </div>

                    @if ($poOrderMigrationReport)
                        <div style="margin-top:16px">
                            <p class="note" style="color:var(--sp-ok)">✅ {{ t('Migration finished.') }}</p>
                            <table>
                                <thead><tr><th>{{ t('Section') }}</th><th>{{ t('Result') }}</th></tr></thead>
                                <tbody>
                                    <tr><td>{{ t('PO header') }}</td><td>{{ $poOrderMigrationReport['headers']['inserted'] }} {{ t('rows') }}</td></tr>
                                    <tr><td>{{ t('PO item lines') }}</td><td>{{ $poOrderMigrationReport['details']['inserted'] }} {{ t('rows') }} ({{ $poOrderMigrationReport['details']['no_material_match'] }} {{ t('without a Raw Material Master match, falling back to the old code') }})</td></tr>
                                </tbody>
                            </table>

                            @if (! empty($poOrderMigrationReport['supplier_unmatched']))
                                <div style="margin-top:12px">
                                    <p class="note" style="color:var(--sp-danger)">⚠ {{ count($poOrderMigrationReport['supplier_unmatched']) }} {{ t('supplier codes without a Supplier Master match:') }}</p>
                                    <p class="note">{{ implode(', ', array_slice(array_keys($poOrderMigrationReport['supplier_unmatched']), 0, 15)) }}{{ count($poOrderMigrationReport['supplier_unmatched']) > 15 ? '…' : '' }}</p>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                @php
                    $legacyArchiveBoxes = [
                        ['title' => 'tm2old.pur_pr(_detail) → pr_requisition_legacy', 'action' => 'runPrMigration', 'report' => 'prMigrationReport', 'unmatchedKey' => null],
                        ['title' => 'tm2old.pur_prcv(_detail) → po_receive_legacy', 'action' => 'runReceiveMigration', 'report' => 'receiveMigrationReport', 'unmatchedKey' => 'supplier_unmatched'],
                        ['title' => 'tm2old.pur_ppym(_detail) → po_payment_legacy', 'action' => 'runPaymentMigration', 'report' => 'paymentMigrationReport', 'unmatchedKey' => 'supplier_unmatched'],
                        ['title' => 'tm2old.sdi_dnote(_detail) → co_delivery_legacy ('.t('Delivery Note').')', 'action' => 'runDnoteMigration', 'report' => 'dnoteMigrationReport', 'unmatchedKey' => 'customer_unmatched'],
                        ['title' => 'tm2old.sdi_cinv(_detail) → co_invoice_legacy (Customer Invoice, basis AR)', 'action' => 'runCinvMigration', 'report' => 'cinvMigrationReport', 'unmatchedKey' => 'customer_unmatched'],
                    ];
                @endphp

                @php
                    // Master pendukung: bentuk laporannya beda-beda (bukan header/detail
                    // seperti arsip dokumen), jadi dirender generik key -> nilai.
                    $legacySupportBoxes = [
                        ['title' => 'tm2old.mst_uom(_conversion) → uom_unit / uom_conversion', 'note' => t('The existing V2 Master WINS — legacy only fills in unit codes that do not exist yet.'), 'action' => 'runUomMigration', 'report' => 'uomMigrationReport'],
                        ['title' => 'tm2old.mst_* ('.t('supporting lookups').') → ref_legacy_lookup', 'note' => t('All small lookups are merged into one table (lookup_group column), not dozens of thin tables.').' '.t('Tables >300 rows are reported as "needs a separate design", not crammed in here.'), 'action' => 'runLookupMigration', 'report' => 'lookupMigrationReport'],
                        ['title' => 'tm2old Master Card → prod_card_legacy '.t('etc. (product engineering)'), 'note' => t('Material, process sequence + machine, and die per product. Bridged to items via mst_product_mc.'), 'action' => 'runMasterCardMigration', 'report' => 'masterCardMigrationReport'],
                        ['title' => t('Master Card Images → Product Master').' (prod_product.image_path)', 'note' => t('Must run AFTER Master Card.').' '.t('435 cards have a file name & all exist in the ScriptCase archive; through the mst_product_mc bridge they reach 483 products (389 files shared).').' '.t('Files are copied once per card + a 480px thumbnail to storage/app/public/product/card.'), 'action' => 'runProductImageIngest', 'report' => 'productImageReport'],
                        ['title' => t('Classification').' mst_item → customer_id / category_id / brand_name', 'note' => t('Completes the columns skipped by ProductMigrationService.').' '.t('goods_belongto is matched to cust_customer.nickname (exact, duplicate nicknames discarded), goods_purpose is mapped to a category by keyword.').' '.t('cm_* dimensions are STILL not filled — source units mix mm/cm without a marker.'), 'action' => 'runProductEnrichment', 'report' => 'productEnrichmentReport'],
                    ];
                @endphp

                @foreach ($legacySupportBoxes as $box)
                    <div class="box" style="margin-top:16px">
                        <h3>{{ $box['title'] }}</h3>
                        <p class="note">{{ $box['note'] }}</p>
                        <div class="actions">
                            <button type="button" class="btn btn-primary" wire:click="{{ $box['action'] }}" wire:loading.attr="disabled"
                                wire:confirm="{{ t('Run this supporting master migration now?') }}">
                                <span wire:loading.remove wire:target="{{ $box['action'] }}">▶ {{ t('Run Migration') }}</span>
                                <span wire:loading wire:target="{{ $box['action'] }}">{{ t('Processing…') }}</span>
                            </button>
                        </div>

                        @if ($this->{$box['report']})
                            <div style="margin-top:16px">
                                <p class="note" style="color:var(--sp-ok)">✅ {{ t('Migration finished.') }}</p>
                                <table>
                                    <thead><tr><th>{{ t('Section') }}</th><th>{{ t('Result') }}</th></tr></thead>
                                    <tbody>
                                        @foreach ($this->{$box['report']} as $key => $value)
                                            <tr>
                                                <td>{{ $key }}</td>
                                                <td>
                                                    @if (is_array($value))
                                                        @if (empty($value))
                                                            —
                                                        @elseif (array_is_list($value))
                                                            {{ implode(', ', array_slice($value, 0, 20)) }}{{ count($value) > 20 ? '…' : '' }}
                                                        @else
                                                            @foreach ($value as $k => $v)
                                                                {{ $k }}: {{ is_scalar($v) ? $v : json_encode($v) }}@if (! $loop->last), @endif
                                                            @endforeach
                                                        @endif
                                                    @else
                                                        {{ $value }}
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endforeach

                @foreach ($legacyArchiveBoxes as $box)
                    <div class="box" style="margin-top:16px">
                        <h3>{{ $box['title'] }}</h3>
                        <p class="note">{{ t('Read-only history archive for dashboards/reports, separate from the live transaction tables.') }} {{ t('Idempotent: full refresh on every run.') }}</p>
                        <div class="actions">
                            <button type="button" class="btn btn-primary" wire:click="{{ $box['action'] }}" wire:loading.attr="disabled"
                                wire:confirm="{{ t('Run this archive migration now?') }}">
                                <span wire:loading.remove wire:target="{{ $box['action'] }}">▶ {{ t('Run Migration') }}</span>
                                <span wire:loading wire:target="{{ $box['action'] }}">{{ t('Processing…') }}</span>
                            </button>
                        </div>

                        @if ($this->{$box['report']})
                            @php $r = $this->{$box['report']}; @endphp
                            <div style="margin-top:16px">
                                <p class="note" style="color:var(--sp-ok)">✅ {{ t('Migration finished.') }}</p>
                                <table>
                                    <thead><tr><th>{{ t('Section') }}</th><th>{{ t('Result') }}</th></tr></thead>
                                    <tbody>
                                        <tr><td>{{ t('Header') }}</td><td>{{ $r['headers']['inserted'] }} {{ t('rows') }}</td></tr>
                                        <tr><td>{{ t('Detail') }}</td><td>{{ $r['details']['inserted'] }} {{ t('rows') }}</td></tr>
                                    </tbody>
                                </table>

                                @if ($box['unmatchedKey'] && ! empty($r[$box['unmatchedKey']]))
                                    <div style="margin-top:12px">
                                        <p class="note" style="color:var(--sp-danger)">⚠ {{ count($r[$box['unmatchedKey']]) }} {{ t('codes without a master match:') }}</p>
                                        <p class="note">{{ implode(', ', array_slice(array_keys($r[$box['unmatchedKey']]), 0, 15)) }}{{ count($r[$box['unmatchedKey']]) > 15 ? '…' : '' }}</p>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach

                <div class="box" style="margin-top:16px">
                    <h3>tm2old.emp → emp_employee (+ org_department / org_position)</h3>
                    <p class="note">{{ t('Source:') }} <code>emp</code> ({{ t('485 rows, full roster —') }} <code>emp_history</code> {{ t('347 rows DELIBERATELY skipped because they turned out to be a full duplicate archive of') }} <code>emp</code>, {{ t('not 347 other people') }}), <code>emp_position</code> ({{ t('49 positions') }}), <code>emp_identities</code> ({{ t('ID card') }}), <code>org_department</code> {{ t('legacy (20 departments).') }} {{ t('Personal data (name/gender/date of birth/etc.) is very incomplete in the source — NOT made up, left empty as is.') }}</p>
                    <p class="note" style="color:var(--sp-danger)">⚠ {{ t('The original department/position per employee is barely recorded in legacy (only 1 of 485 rows).') }} {{ t('All employees from this migration go into one temporary bucket') }} "Migrasi Legacy - Belum Dipetakan" — {{ t('they must be manually reassigned to the correct department/position.') }}</p>
                    <div class="actions">
                        <button type="button" class="btn btn-primary" wire:click="runEmployeeMigration" wire:loading.attr="disabled"
                            wire:confirm="{{ t('Run the employee data migration from tm2old.emp now?') }}">
                            <span wire:loading.remove wire:target="runEmployeeMigration">▶ {{ t('Run Employee Migration') }}</span>
                            <span wire:loading wire:target="runEmployeeMigration">{{ t('Processing…') }}</span>
                        </button>
                    </div>

                    @if ($employeeMigrationReport)
                        <div style="margin-top:16px">
                            <p class="note" style="color:var(--sp-ok)">✅ {{ t('Employee migration finished.') }}</p>
                            <table>
                                <thead><tr><th>{{ t('Section') }}</th><th>{{ t('Result') }}</th></tr></thead>
                                <tbody>
                                    <tr><td>{{ t('Department (reference)') }}</td><td>{{ $employeeMigrationReport['departments']['created'] }} {{ t('new,') }} {{ $employeeMigrationReport['departments']['updated'] }} {{ t('updated') }}</td></tr>
                                    <tr><td>{{ t('Position (reference)') }}</td><td>{{ $employeeMigrationReport['positions']['created'] }} {{ t('new,') }} {{ $employeeMigrationReport['positions']['updated'] }} {{ t('updated') }}</td></tr>
                                    <tr><td>{{ t('Employee') }}</td><td>{{ $employeeMigrationReport['employees']['created'] }} {{ t('new,') }} {{ $employeeMigrationReport['employees']['updated'] }} {{ t('updated') }}</td></tr>
                                    <tr><td>{{ t('Bank account') }}</td><td>{{ $employeeMigrationReport['bankAccounts']['created'] }} {{ t('new') }}</td></tr>
                                    <tr><td>{{ t('ID card documents') }}</td><td>{{ $employeeMigrationReport['documents']['created'] }} {{ t('new') }}</td></tr>
                                    <tr><td>{{ t('Gender unknown') }}</td><td>{{ $employeeMigrationReport['genderUnknown'] }} {{ t('employees (left empty)') }}</td></tr>
                                    <tr><td>{{ t('Join date unknown') }}</td><td>{{ $employeeMigrationReport['joinDateUnknown'] }} {{ t('employees (left empty)') }}</td></tr>
                                </tbody>
                            </table>

                            @if (! empty($employeeMigrationReport['employees']['skipped']))
                                <div style="margin-top:12px">
                                    <p class="note" style="color:var(--sp-danger)">⚠ {{ count($employeeMigrationReport['employees']['skipped']) }} {{ t('rows skipped:') }}</p>
                                    <ul style="font-size:12px;color:var(--sp-danger);margin:4px 0 0;padding-left:18px">
                                        @foreach ($employeeMigrationReport['employees']['skipped'] as $s)
                                            <li>{{ $s }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if (! empty($employeeMigrationReport['errors']))
                                <div style="margin-top:12px">
                                    <p class="note" style="color:var(--sp-danger)">⚠ {{ count($employeeMigrationReport['errors']) }} {{ t('rows failed to process:') }}</p>
                                    <ul style="font-size:12px;color:var(--sp-danger);margin:4px 0 0;padding-left:18px">
                                        @foreach ($employeeMigrationReport['errors'] as $err)
                                            <li>{{ $err }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="box" style="margin-top:16px">
                    <h3>emp_employee ({{ t('active') }}) → login sec_user</h3>
                    <p class="note">{{ t('Create login accounts for the migrated employees above that have') }} <code>activate=1</code> {{ t('(employees who have left do NOT get an account — realistic).') }} {{ t('Username = first name (collisions get a number), a random password is made through') }} <code>PasswordResetService</code>, {{ t('the password must be changed at first login.') }} {{ t('Safe to run repeatedly — employees who already have an account are skipped.') }}</p>
                    <div class="actions">
                        <button type="button" class="btn btn-primary" wire:click="runEmployeeLoginProvision" wire:loading.attr="disabled"
                            wire:confirm="{{ t('Create login accounts for all active migrated employees now?') }}">
                            <span wire:loading.remove wire:target="runEmployeeLoginProvision">▶ {{ t('Create Employee Logins') }}</span>
                            <span wire:loading wire:target="runEmployeeLoginProvision">{{ t('Processing…') }}</span>
                        </button>
                    </div>

                    @if ($loginProvisionReport)
                        <div style="margin-top:16px">
                            <p class="note" style="color:var(--sp-ok)">✅ {{ str_replace([':created', ':skipped'], [$loginProvisionReport['created'], $loginProvisionReport['skippedExisting']], t(':created new accounts created, :skipped skipped (already have an account).')) }}</p>

                            @if (! empty($loginProvisionReport['sample']))
                                <p class="note">{{ str_replace(':n', count($loginProvisionReport['sample']), t('Sample of the first :n accounts (the rest can be checked with the "View password" button in the User List above):')) }}</p>
                                <table>
                                    <thead><tr><th>{{ t('Username') }}</th><th>{{ t('Name') }}</th><th>{{ t('Password') }}</th></tr></thead>
                                    <tbody>
                                        @foreach ($loginProvisionReport['sample'] as $s)
                                            <tr><td><code>{{ $s['login'] }}</code></td><td>{{ $s['name'] }}</td><td><code>{{ $s['password'] }}</code></td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </div>
</div>
