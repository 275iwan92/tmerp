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
    public string $u_login = '';

    public string $u_name = '';

    public string $u_email = '';

    public string $u_pswd = '';

    public ?int $u_group_id = null;

    public ?string $userMessage = null;

    #[Computed]
    public function users()
    {
        return SecUser::query()->with('groups')->orderBy('login')->get();
    }

    public function addUser(): void
    {
        $this->userMessage = null;

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

        $this->reset(['u_login', 'u_name', 'u_email', 'u_pswd', 'u_group_id']);
        $this->userMessage = 'Pengguna ditambahkan.';
        unset($this->users);
    }

    public function toggleUserActive(int $userId): void
    {
        // Admin tak boleh menonaktifkan akun sendiri — kunci diri sendiri keluar.
        if ($userId === Auth::id()) {
            return;
        }

        $user = SecUser::findOrFail($userId);
        $user->update(['activate' => ! $user->activate]);
        unset($this->users);
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
        unset($this->users);
    }

    // ================= GRUP =================
    public string $g_code = '';

    public string $g_name = '';

    public int $g_level = 1;

    public ?string $groupMessage = null;

    #[Computed]
    public function groups()
    {
        return SecGroup::withCount('users')->orderBy('group_name')->get();
    }

    public function addGroup(): void
    {
        $this->groupMessage = null;

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

        $this->reset(['g_code', 'g_name']);
        $this->g_level = 1;
        $this->groupMessage = 'Grup ditambahkan.';
        unset($this->groups);
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
    <div class="shell">
        <div class="content">
            {{-- 1. Pengguna --}}
            <section class="panel {{ $activePanel === 'users' ? 'active' : '' }}">
                <h1>Pengguna</h1><p class="desc">Kelola akun yang bisa masuk ke aplikasi.</p>

                <div class="box">
                    <h3>Tambah pengguna</h3>
                    @if ($userMessage)
                        <p class="note" style="color:var(--sp-ok)">✅ {{ $userMessage }}</p>
                    @endif
                    <div class="form-grid">
                        <x-form.input legacy="fld" label="Username" wire:model="u_login" placeholder="mis. andi.pratama" name="u_login" />
                        <x-form.input legacy="fld" label="Nama lengkap" wire:model="u_name" placeholder="Andi Pratama" name="u_name" />
                        <x-form.input legacy="fld" label="Email" wire:model="u_email" placeholder="andi@telasih.co.id" name="u_email" />
                        <x-form.input legacy="fld" label="Password awal" type="password" wire:model="u_pswd" placeholder="Minimal 8 karakter" name="u_pswd" />
                        <x-form.select legacy="fld" label="Grup" wire:model="u_group_id" x-data x-effect="$el.value = @js($u_group_id)"
                            placeholder="— pilih grup —" name="u_group_id" :options="$this->groups->pluck('group_name', 'id_group')->all()" />
                    </div>
                    <div class="actions"><button type="button" class="btn btn-primary" wire:click="addUser">Tambah pengguna</button></div>
                </div>

                <div class="box">
                    <h3>Daftar pengguna</h3>
                    <table>
                        <thead><tr><th>Username</th><th>Nama</th><th>Grup</th><th>Status</th><th>Password</th><th>Aksi</th></tr></thead>
                        <tbody>
                            @foreach ($this->users as $u)
                                <tr wire:key="user-{{ $u->id_user }}">
                                    <td>{{ $u->login }}</td>
                                    <td>{{ $u->name }}</td>
                                    <td>{{ $u->groups->pluck('group_name')->implode(', ') ?: '—' }}{{ $u->is_admin ? ' (Admin)' : '' }}</td>
                                    <td><span class="pill {{ $u->activate ? 'on' : 'off' }}">{{ $u->activate ? 'Aktif' : 'Nonaktif' }}</span></td>
                                    <td>
                                        @if (session()->has('resetPlain_'.$u->id_user))
                                            <span class="note" style="color:var(--sp-ok)">Baru: <code>{{ session('resetPlain_'.$u->id_user) }}</code></span>
                                        @elseif ($u->must_change_password)
                                            <span class="pill off">Wajib ganti</span>
                                        @else
                                            <span class="note">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <button type="button" class="link-act" wire:click="resetPassword({{ $u->id_user }})" wire:confirm="Reset password {{ $u->login }} ke password baru?">Reset password</button>
                                        @if ($u->default_password)
                                            <button type="button" class="link-act" wire:click="revealPassword({{ $u->id_user }})">Lihat password</button>
                                        @endif
                                        @if ($u->id_user !== Auth::id())
                                            <button type="button" class="link-act" wire:click="toggleUserActive({{ $u->id_user }})">{{ $u->activate ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                        @else
                                            <span class="note">(akun Anda)</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- 2. Grup --}}
            <section class="panel {{ $activePanel === 'groups' ? 'active' : '' }}">
                <h1>Grup</h1><p class="desc">Grup menampung sekumpulan pengguna yang berbagi hak akses sama.</p>

                <div class="box">
                    <h3>Tambah grup</h3>
                    @if ($groupMessage)
                        <p class="note" style="color:var(--sp-ok)">✅ {{ $groupMessage }}</p>
                    @endif
                    <div class="form-grid">
                        <x-form.input legacy="fld" label="Kode grup" wire:model="g_code" placeholder="mis. PRODUKSI_SPV" name="g_code" />
                        <x-form.input legacy="fld" label="Nama grup" wire:model="g_name" placeholder="mis. Supervisor Produksi" name="g_name" />
                        <x-form.select legacy="fld" label="Level manajemen (1=Staf .. 5=Direksi)" wire:model="g_level" x-data x-effect="$el.value = @js($g_level)"
                            :placeholder="false" :options="[1 => '1 - Staf', 2 => '2 - Supervisor', 3 => '3 - Manager', 4 => '4 - GM', 5 => '5 - Direksi']" />
                    </div>
                    <div class="actions"><button type="button" class="btn btn-primary" wire:click="addGroup">Tambah grup</button></div>
                </div>

                <div class="box">
                    <h3>Daftar grup</h3>
                    <table>
                        <thead><tr><th>Kode</th><th>Nama grup</th><th>Level</th><th>Jumlah user</th></tr></thead>
                        <tbody>
                            @foreach ($this->groups as $g)
                                <tr wire:key="group-{{ $g->id_group }}">
                                    <td>{{ $g->group_code }}</td>
                                    <td>{{ $g->group_name }}</td>
                                    <td>{{ $g->mgmt_level }}</td>
                                    <td>{{ $g->users_count }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- 3. Hak Akses Grup --}}
            <section class="panel {{ $activePanel === 'perms' ? 'active' : '' }}">
                <h1>Hak Akses Grup</h1><p class="desc">Pilih grup, lalu tentukan akses per modul (hierarki induk → sub).</p>

                <div class="box">
                    <div style="max-width:320px">
                        <x-form.select legacy="fld" label="Grup" wire:model.live="permGroupId" x-data x-effect="$el.value = @js($permGroupId)"
                            :placeholder="false" :options="$this->groups->pluck('group_name', 'id_group')->all()" />
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
