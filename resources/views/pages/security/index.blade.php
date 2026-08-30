<?php

use App\Models\Sys\SecGroup;
use App\Models\Sys\SecGroupPermission;
use App\Models\Sys\SecModule;
use App\Models\Sys\SecUser;
use App\Services\Migration\EmployeeLoginProvisionService;
use App\Services\Migration\EmployeeMigrationService;
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
    #[Url(as: 'tab')]
    public string $activePanel = 'users';

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
            $user->update(['name' => $this->u_name, 'email' => $this->u_email ?: null]);
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

    public bool $migrationRunning = false;

    public function runVendorMigration(): void
    {
        $this->migrationRunning = true;
        $this->migrationReport = app(VendorMigrationService::class)->run(Auth::user()->login);
        $this->migrationRunning = false;
    }

    public ?array $employeeMigrationReport = null;

    public bool $employeeMigrationRunning = false;

    public function runEmployeeMigration(): void
    {
        $this->employeeMigrationRunning = true;
        $this->employeeMigrationReport = app(EmployeeMigrationService::class)->run(Auth::user()->login);
        $this->employeeMigrationRunning = false;
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
                <h1>Pengguna</h1><p class="desc">Kelola akun yang bisa masuk ke aplikasi.</p>

                @if ($userView === 'grid')
                    <x-grid.stat-row :count="4">
                        <x-grid.stat-box tone="total" label="Total Pengguna" :value="$this->userStatTotal" />
                        <x-grid.stat-box tone="active" label="Aktif" :value="$this->userStatActive" />
                        <x-grid.stat-box tone="warn" label="Wajib Ganti Password" :value="$this->userStatMustChange" />
                        <x-grid.stat-box tone="critical" label="Nonaktif" :value="$this->userStatInactive" />
                    </x-grid.stat-row>

                    @if ($userMessage)
                        <div class="text-xs px-3 py-2 border border-[#c8ecd6] bg-[#e9f9ef] text-[#2f7a52] rounded-md mb-3">✅ {{ $userMessage }}</div>
                    @endif

                    <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
                        <x-grid.toolbar>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <span class="text-sm">🔍</span>
                                <input type="text" wire:model.live.debounce.300ms="userSearch" placeholder="Cari username / nama / email…"
                                    class="w-56 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
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
                                        <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Pengguna</th>
                                        <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Grup</th>
                                        <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[90px]">Status</th>
                                        <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[150px]">Password</th>
                                        <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[160px]">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($this->users as $u)
                                        <tr wire:key="user-{{ $u->id_user }}" wire:dblclick="openUserForm('edit', {{ $u->id_user }})" class="hover:bg-[#f7faff] cursor-pointer" title="Klik 2x untuk buka detail">
                                            <x-grid.identity-cell :id="$u->id_user" :name="$u->name" :code="$u->login" />
                                            <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $u->groups->pluck('group_name')->implode(', ') ?: '—' }}{{ $u->is_admin ? ' (Admin)' : '' }}</td>
                                            <td class="px-3 py-2 border-b border-[#eef1f5]">
                                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block {{ $u->activate ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#eef0f3] text-[#8a8f98]' }}">{{ $u->activate ? 'AKTIF' : 'NONAKTIF' }}</span>
                                            </td>
                                            <td class="px-3 py-2 border-b border-[#eef1f5]">
                                                @if (session()->has('resetPlain_'.$u->id_user))
                                                    <span class="text-[11px] font-bold text-[#1f8a4c]">Baru: <code>{{ session('resetPlain_'.$u->id_user) }}</code></span>
                                                @elseif ($u->must_change_password)
                                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block bg-[#fef6e7] text-[#8a6d00]">Wajib ganti</span>
                                                @else
                                                    <span class="text-[11px] text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                                <x-grid.action-icon icon="✎" title="Edit" action="openUserForm('edit', {{ $u->id_user }})" />
                                                <x-grid.action-icon icon="🔑" title="Reset Password" action="resetPassword({{ $u->id_user }})"
                                                    confirm="Reset password {{ $u->login }} ke password baru?" />
                                                @if ($u->default_password)
                                                    <x-grid.action-icon icon="👁" title="Lihat Password" action="revealPassword({{ $u->id_user }})" />
                                                @endif
                                                @if ($u->id_user !== Auth::id())
                                                    <x-grid.action-icon icon="⏻" :title="$u->activate ? 'Nonaktifkan' : 'Aktifkan'" action="toggleUserActive({{ $u->id_user }})"
                                                        :confirm="($u->activate ? 'Nonaktifkan' : 'Aktifkan').' pengguna '.$u->login.'?'"
                                                        :variant="$u->activate ? 'danger' : 'default'" />
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <x-grid.empty-row :colspan="5" :message="'Tidak ada pengguna'.($userSearch ? ' untuk pencarian ini' : '').'.'" />
                                    @endforelse
                                </tbody>
                            </table>
                        </x-grid.table-shell>
                    </div>
                @else
                    {{-- FORM Pengguna (New/Edit) --}}
                    <div class="flex justify-between items-center bg-navy text-white px-4 py-3 rounded-t-md">
                        <div class="text-sm font-bold">
                            {{ $userMode === 'edit' ? 'Edit Pengguna' : 'Pengguna Baru' }}
                            @if ($userMode === 'edit')<span class="text-xs font-normal opacity-75 ml-2">{{ $u_login }}</span>@endif
                        </div>
                    </div>
                    <form wire:submit="saveUser" class="bg-white border border-t-0 border-[#d7dbe2] p-5 rounded-b-md">
                        <div class="form-grid">
                            <x-form.input legacy="fld" label="Username" wire:model="u_login" placeholder="mis. andi.pratama" name="u_login" @readonly($userMode === 'edit') />
                            <x-form.input legacy="fld" label="Nama lengkap" wire:model="u_name" placeholder="Andi Pratama" name="u_name" />
                            <x-form.input legacy="fld" label="Email" wire:model="u_email" placeholder="andi@telasih.co.id" name="u_email" />
                            @if ($userMode === 'new')
                                <x-form.input legacy="fld" label="Password awal" type="password" wire:model="u_pswd" placeholder="Minimal 8 karakter" name="u_pswd" />
                            @endif
                            <x-form.select legacy="fld" label="Grup" wire:model="u_group_id" name="u_group_id"
                                placeholder="— pilih grup —" :options="$this->groupOptions->pluck('group_name', 'id_group')->all()" :selected="$u_group_id" />
                        </div>
                        @if ($userMode === 'edit')
                            <p class="note">Ganti password lewat aksi 🔑 "Reset Password" di grid, bukan di sini.</p>
                        @endif
                        <div class="actions">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <button type="button" wire:click="showUserGrid" class="btn btn-ghost">Batal</button>
                        </div>
                    </form>
                @endif
            </section>

            {{-- 2. Grup -- Grid Master standar, sama pola dgn Pengguna di atas --}}
            <section class="panel {{ $activePanel === 'groups' ? 'active' : '' }}">
                <h1>Grup</h1><p class="desc">Grup menampung sekumpulan pengguna yang berbagi hak akses sama.</p>

                @if ($groupView === 'grid')
                    <x-grid.stat-row :count="3">
                        <x-grid.stat-box tone="total" label="Total Grup" :value="$this->groupStatTotal" />
                        <x-grid.stat-box tone="active" label="Terisi (≥1 Pengguna)" :value="$this->groupStatFilled" />
                        <x-grid.stat-box tone="warn" label="Kosong" :value="$this->groupStatEmpty" />
                    </x-grid.stat-row>

                    @if ($groupMessage)
                        <div class="text-xs px-3 py-2 border border-[#c8ecd6] bg-[#e9f9ef] text-[#2f7a52] rounded-md mb-3">✅ {{ $groupMessage }}</div>
                    @endif

                    <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
                        <x-grid.toolbar>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <span class="text-sm">🔍</span>
                                <input type="text" wire:model.live.debounce.300ms="groupSearch" placeholder="Cari kode / nama grup…"
                                    class="w-56 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
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
                                        <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Grup</th>
                                        <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">Level</th>
                                        <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">Jumlah User</th>
                                        <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[90px]">Status</th>
                                        <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($this->groups as $g)
                                        <tr wire:key="group-{{ $g->id_group }}" wire:dblclick="openGroupForm('edit', {{ $g->id_group }})" class="hover:bg-[#f7faff] cursor-pointer" title="Klik 2x untuk buka detail">
                                            <x-grid.identity-cell :id="$g->id_group" :name="$g->group_name" :code="$g->group_code" />
                                            <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $g->mgmt_level }}</td>
                                            <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $g->users_count }}</td>
                                            <td class="px-3 py-2 border-b border-[#eef1f5]">
                                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block {{ $g->activate ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#eef0f3] text-[#8a8f98]' }}">{{ $g->activate ? 'AKTIF' : 'NONAKTIF' }}</span>
                                            </td>
                                            <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                                <x-grid.action-icon icon="✎" title="Edit" action="openGroupForm('edit', {{ $g->id_group }})" />
                                                <x-grid.action-icon icon="⏻" :title="$g->activate ? 'Nonaktifkan' : 'Aktifkan'" action="toggleGroupActive({{ $g->id_group }})"
                                                    :confirm="($g->activate ? 'Nonaktifkan' : 'Aktifkan').' grup '.$g->group_name.'?'"
                                                    :variant="$g->activate ? 'danger' : 'default'" />
                                            </td>
                                        </tr>
                                    @empty
                                        <x-grid.empty-row :colspan="5" :message="'Tidak ada grup'.($groupSearch ? ' untuk pencarian ini' : '').'.'" />
                                    @endforelse
                                </tbody>
                            </table>
                        </x-grid.table-shell>
                    </div>
                @else
                    {{-- FORM Grup (New/Edit) --}}
                    <div class="flex justify-between items-center bg-navy text-white px-4 py-3 rounded-t-md">
                        <div class="text-sm font-bold">
                            {{ $groupMode === 'edit' ? 'Edit Grup' : 'Grup Baru' }}
                            @if ($groupMode === 'edit')<span class="text-xs font-normal opacity-75 ml-2">{{ $g_code }}</span>@endif
                        </div>
                    </div>
                    <form wire:submit="saveGroup" class="bg-white border border-t-0 border-[#d7dbe2] p-5 rounded-b-md">
                        <div class="form-grid">
                            <x-form.input legacy="fld" label="Kode grup" wire:model="g_code" placeholder="mis. PRODUKSI_SPV" name="g_code" @readonly($groupMode === 'edit') />
                            <x-form.input legacy="fld" label="Nama grup" wire:model="g_name" placeholder="mis. Supervisor Produksi" name="g_name" />
                            <x-form.select legacy="fld" label="Level manajemen (1=Staf .. 5=Direksi)" wire:model="g_level"
                                :placeholder="false" :options="[1 => '1 - Staf', 2 => '2 - Supervisor', 3 => '3 - Manager', 4 => '4 - GM', 5 => '5 - Direksi']" :selected="$g_level" />
                        </div>
                        <div class="actions">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <button type="button" wire:click="showGroupGrid" class="btn btn-ghost">Batal</button>
                        </div>
                    </form>
                @endif
            </section>

            {{-- 3. Hak Akses Grup --}}
            <section class="panel {{ $activePanel === 'perms' ? 'active' : '' }}">
                <h1>Hak Akses Grup</h1><p class="desc">Pilih grup, lalu tentukan akses per modul (hierarki induk → sub).</p>

                <div class="box">
                    <div style="max-width:320px">
                        <x-form.select legacy="fld" label="Grup" wire:model.live="permGroupId"
                            :placeholder="false" :options="$this->groupOptions->pluck('group_name', 'id_group')->all()" :selected="$permGroupId" />
                    </div>
                </div>

                <div class="box">
                    <h3>Izin per modul</h3>
                    <table>
                        <thead><tr><th>Modul</th><th style="width:90px">Akses</th><th style="width:90px">Input</th><th style="width:90px">Approve</th><th style="width:90px">Export</th></tr></thead>
                        <tbody>
                            @foreach ($this->modulesTree as $m)
                                @php($p = $this->currentPermissions->get($m->id_module))
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
                                    @php($sp = $this->currentPermissions->get($sub->id_module))
                                    <tr wire:key="perm-mod-{{ $sub->id_module }}">
                                        <td class="sub-indent">↳ {{ $sub->icon }} {{ $sub->module_name }} <span class="pill sub">min lvl {{ $sub->min_mgmt_level }}</span></td>
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
                    <p class="note">Readonly = Akses menyala, Input mati. Mematikan Akses otomatis mematikan Input/Approve/Export (BRIEF §4).</p>
                </div>
            </section>

            {{-- 4. Migrasi Data Legacy --}}
            <section class="panel {{ $activePanel === 'migration' ? 'active' : '' }}">
                <h1>Migrasi Data Legacy</h1>
                <p class="desc">Tarik data lama (ScriptCase V1) dari staging <code>tm2old</code> ke Master Customer &amp; Master Supplier. Aman dijalankan berulang kali — data yang sudah ada akan diperbarui, bukan diduplikasi.</p>

                <div class="box">
                    <h3>tm2old.mst_vendor → cust_customer &amp; sup_supplier</h3>
                    <p class="note">Sumber: <code>mst_vendor</code> (1.160 baris), <code>mst_vendor_bank</code>, <code>mst_vendor_customer_category</code>, <code>mst_vendor_supplier_category</code>. Baris dengan <code>as_customer=1</code> masuk Master Customer, <code>as_supplier=1</code> masuk Master Supplier — vendor bisa masuk keduanya.</p>
                    <div class="actions">
                        <button type="button" class="btn btn-primary" wire:click="runVendorMigration" wire:loading.attr="disabled"
                            wire:confirm="Jalankan migrasi data dari tm2old.mst_vendor sekarang?">
                            <span wire:loading.remove wire:target="runVendorMigration">▶ Jalankan Migrasi</span>
                            <span wire:loading wire:target="runVendorMigration">Memproses…</span>
                        </button>
                    </div>

                    @if ($migrationReport)
                        <div style="margin-top:16px">
                            <p class="note" style="color:var(--sp-ok)">✅ Migrasi selesai.</p>
                            <table>
                                <thead><tr><th>Bagian</th><th>Hasil</th></tr></thead>
                                <tbody>
                                    <tr><td>Kategori customer / supplier</td><td>{{ $migrationReport['categories']['cust'] }} / {{ $migrationReport['categories']['sup'] }} kode</td></tr>
                                    <tr><td>Master Customer</td><td>{{ $migrationReport['customers']['created'] }} baru, {{ $migrationReport['customers']['updated'] }} diperbarui</td></tr>
                                    <tr><td>Master Supplier</td><td>{{ $migrationReport['suppliers']['created'] }} baru, {{ $migrationReport['suppliers']['updated'] }} diperbarui</td></tr>
                                    <tr><td>Rekening bank</td><td>{{ $migrationReport['banks']['cust'] }} customer, {{ $migrationReport['banks']['sup'] }} supplier{{ $migrationReport['banks']['skipped'] ? ', '.$migrationReport['banks']['skipped'].' dilewati (vendor tak ketemu)' : '' }}</td></tr>
                                    <tr><td>Kota cocok otomatis</td><td>{{ $migrationReport['city']['matched'] }} baris</td></tr>
                                </tbody>
                            </table>

                            @if (! empty($migrationReport['city']['unmatched']))
                                <div style="margin-top:12px">
                                    <p class="note" style="color:var(--sp-danger)">⚠ {{ count($migrationReport['city']['unmatched']) }} nama kota tidak cocok ke <code>geo_city</code> (dibiarkan kosong, isi manual di Master Customer/Supplier):</p>
                                    <p class="note">{{ implode(', ', array_map(fn ($name, $cnt) => "{$name} ({$cnt})", array_keys($migrationReport['city']['unmatched']), $migrationReport['city']['unmatched'])) }}</p>
                                </div>
                            @endif

                            @if (! empty($migrationReport['errors']))
                                <div style="margin-top:12px">
                                    <p class="note" style="color:var(--sp-danger)">⚠ {{ count($migrationReport['errors']) }} baris gagal diproses:</p>
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
                    <h3>tm2old.emp → emp_employee (+ org_department / org_position)</h3>
                    <p class="note">Sumber: <code>emp</code> (485 baris, roster lengkap — <code>emp_history</code> 347 baris SENGAJA dilewati karena ternyata arsip duplikat penuh dari <code>emp</code>, bukan 347 orang lain), <code>emp_position</code> (49 jabatan), <code>emp_identities</code> (KTP), <code>org_department</code> legacy (20 departemen). Data personal (nama/gender/tanggal lahir/dsb) sangat tidak lengkap di sumber — TIDAK dikarang, dibiarkan kosong apa adanya.</p>
                    <p class="note" style="color:var(--sp-danger)">⚠ Departemen/jabatan asli per karyawan nyaris tidak tercatat di legacy (cuma 1 dari 485 baris). Semua karyawan hasil migrasi ini masuk ke satu bucket sementara "Migrasi Legacy - Belum Dipetakan" — wajib di-assign ulang manual ke department/position yang benar.</p>
                    <div class="actions">
                        <button type="button" class="btn btn-primary" wire:click="runEmployeeMigration" wire:loading.attr="disabled"
                            wire:confirm="Jalankan migrasi data karyawan dari tm2old.emp sekarang?">
                            <span wire:loading.remove wire:target="runEmployeeMigration">▶ Jalankan Migrasi Karyawan</span>
                            <span wire:loading wire:target="runEmployeeMigration">Memproses…</span>
                        </button>
                    </div>

                    @if ($employeeMigrationReport)
                        <div style="margin-top:16px">
                            <p class="note" style="color:var(--sp-ok)">✅ Migrasi karyawan selesai.</p>
                            <table>
                                <thead><tr><th>Bagian</th><th>Hasil</th></tr></thead>
                                <tbody>
                                    <tr><td>Departemen (referensi)</td><td>{{ $employeeMigrationReport['departments']['created'] }} baru, {{ $employeeMigrationReport['departments']['updated'] }} diperbarui</td></tr>
                                    <tr><td>Jabatan (referensi)</td><td>{{ $employeeMigrationReport['positions']['created'] }} baru, {{ $employeeMigrationReport['positions']['updated'] }} diperbarui</td></tr>
                                    <tr><td>Karyawan</td><td>{{ $employeeMigrationReport['employees']['created'] }} baru, {{ $employeeMigrationReport['employees']['updated'] }} diperbarui</td></tr>
                                    <tr><td>Rekening bank</td><td>{{ $employeeMigrationReport['bankAccounts']['created'] }} baru</td></tr>
                                    <tr><td>Dokumen KTP</td><td>{{ $employeeMigrationReport['documents']['created'] }} baru</td></tr>
                                    <tr><td>Gender tidak diketahui</td><td>{{ $employeeMigrationReport['genderUnknown'] }} karyawan (dibiarkan kosong)</td></tr>
                                    <tr><td>Tanggal masuk tidak diketahui</td><td>{{ $employeeMigrationReport['joinDateUnknown'] }} karyawan (dibiarkan kosong)</td></tr>
                                </tbody>
                            </table>

                            @if (! empty($employeeMigrationReport['employees']['skipped']))
                                <div style="margin-top:12px">
                                    <p class="note" style="color:var(--sp-danger)">⚠ {{ count($employeeMigrationReport['employees']['skipped']) }} baris dilewati:</p>
                                    <ul style="font-size:12px;color:var(--sp-danger);margin:4px 0 0;padding-left:18px">
                                        @foreach ($employeeMigrationReport['employees']['skipped'] as $s)
                                            <li>{{ $s }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if (! empty($employeeMigrationReport['errors']))
                                <div style="margin-top:12px">
                                    <p class="note" style="color:var(--sp-danger)">⚠ {{ count($employeeMigrationReport['errors']) }} baris gagal diproses:</p>
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
                    <h3>emp_employee (aktif) → login sec_user</h3>
                    <p class="note">Buat akun login untuk karyawan hasil migrasi di atas yang <code>activate=1</code> (karyawan yang sudah keluar TIDAK dibuatkan akun — realistis). Username = nama depan (tabrakan ditambah angka), password acak dibuat lewat <code>PasswordResetService</code>, wajib ganti password di login pertama. Aman dijalankan berulang — karyawan yang sudah punya akun dilewati.</p>
                    <div class="actions">
                        <button type="button" class="btn btn-primary" wire:click="runEmployeeLoginProvision" wire:loading.attr="disabled"
                            wire:confirm="Buat akun login untuk semua karyawan aktif hasil migrasi sekarang?">
                            <span wire:loading.remove wire:target="runEmployeeLoginProvision">▶ Buat Login Karyawan</span>
                            <span wire:loading wire:target="runEmployeeLoginProvision">Memproses…</span>
                        </button>
                    </div>

                    @if ($loginProvisionReport)
                        <div style="margin-top:16px">
                            <p class="note" style="color:var(--sp-ok)">✅ {{ $loginProvisionReport['created'] }} akun baru dibuat, {{ $loginProvisionReport['skippedExisting'] }} dilewati (sudah punya akun).</p>

                            @if (! empty($loginProvisionReport['sample']))
                                <p class="note">Contoh {{ count($loginProvisionReport['sample']) }} akun pertama (sisanya bisa dicek lewat tombol "Lihat password" di Daftar Pengguna di atas):</p>
                                <table>
                                    <thead><tr><th>Username</th><th>Nama</th><th>Password</th></tr></thead>
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
