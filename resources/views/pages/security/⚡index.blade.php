<?php

use App\Models\Sys\SecGroup;
use App\Models\Sys\SecGroupPermission;
use App\Models\Sys\SecModule;
use App\Models\Sys\SecUser;
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
            'is_active' => true,
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
        $user->update(['active' => ! $user->active]);
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
            'is_active' => true,
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

        if (! in_array($this->activePanel, ['users', 'groups', 'perms'], true)) {
            $this->activePanel = 'users';
        }
    }

    #[Computed]
    public function modulesTree()
    {
        return SecModule::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
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
        if (in_array($panel, ['users', 'groups', 'perms'], true)) {
            $this->activePanel = $panel;
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

<div class="secpage" x-data="{ ddOpen: false }">
    <header>
        <div class="brand"><span class="logo">📦</span> TELASIH MAKMUR</div>
        <span class="htitle">Setup &amp; Security</span>
        <a href="{{ route('menu') }}" class="back">← Menu utama</a>
        <div class="sp-spacer"></div>
        <div class="user" @click.outside="ddOpen = false">
            <button type="button" class="avatar-btn" @click="ddOpen = !ddOpen">
                <span class="avatar"><svg viewBox="0 0 24 24"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-5 0-9 2.5-9 6v1h18v-1c0-3.5-4-6-9-6z"/></svg></span>
                {{ Auth::user()->name ?: 'Pengguna' }} ▾
            </button>
            <div class="dropdown" :class="{ open: ddOpen }">
                <div class="dd-list">
                    <a href="{{ route('menu') }}" class="dd-item"><span>🏠</span> Menu utama</a>
                    <button type="button" class="dd-item exit" wire:click="logout"><span>↩︎</span> Keluar aplikasi</button>
                </div>
            </div>
        </div>
    </header>

    <div class="shell">
        <aside class="side">
            <div class="cap">Keamanan</div>
            <button type="button" class="nav-item {{ $activePanel === 'users' ? 'active' : '' }}" wire:click="setPanel('users')"><span class="i">👤</span><span class="t">Pengguna</span></button>
            <button type="button" class="nav-item {{ $activePanel === 'groups' ? 'active' : '' }}" wire:click="setPanel('groups')"><span class="i">👥</span><span class="t">Grup</span></button>
            <button type="button" class="nav-item {{ $activePanel === 'perms' ? 'active' : '' }}" wire:click="setPanel('perms')"><span class="i">🔐</span><span class="t">Hak Akses Grup</span></button>
        </aside>

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
                        <div class="fld">
                            <label>Username</label>
                            <input wire:model="u_login" placeholder="mis. andi.pratama">
                            @error('u_login') <div class="note" style="color:var(--sp-danger)">{{ $message }}</div> @enderror
                        </div>
                        <div class="fld">
                            <label>Nama lengkap</label>
                            <input wire:model="u_name" placeholder="Andi Pratama">
                            @error('u_name') <div class="note" style="color:var(--sp-danger)">{{ $message }}</div> @enderror
                        </div>
                        <div class="fld">
                            <label>Email</label>
                            <input wire:model="u_email" placeholder="andi@telasih.co.id">
                            @error('u_email') <div class="note" style="color:var(--sp-danger)">{{ $message }}</div> @enderror
                        </div>
                        <div class="fld">
                            <label>Password awal</label>
                            <input type="password" wire:model="u_pswd" placeholder="Minimal 8 karakter">
                            @error('u_pswd') <div class="note" style="color:var(--sp-danger)">{{ $message }}</div> @enderror
                        </div>
                        <div class="fld">
                            <label>Grup</label>
                            <select wire:model="u_group_id" x-data x-effect="$el.value = @js($u_group_id)">
                                <option value="">— pilih grup —</option>
                                @foreach ($this->groups as $g)
                                    <option value="{{ $g->id_group }}">{{ $g->group_name }}</option>
                                @endforeach
                            </select>
                            @error('u_group_id') <div class="note" style="color:var(--sp-danger)">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="actions"><button type="button" class="btn btn-primary" wire:click="addUser">Tambah pengguna</button></div>
                </div>

                <div class="box">
                    <h3>Daftar pengguna</h3>
                    <table>
                        <thead><tr><th>Username</th><th>Nama</th><th>Grup</th><th>Status</th><th>Aksi</th></tr></thead>
                        <tbody>
                            @foreach ($this->users as $u)
                                <tr wire:key="user-{{ $u->id_user }}">
                                    <td>{{ $u->login }}</td>
                                    <td>{{ $u->name }}</td>
                                    <td>{{ $u->groups->pluck('group_name')->implode(', ') ?: '—' }}{{ $u->is_admin ? ' (Admin)' : '' }}</td>
                                    <td><span class="pill {{ $u->active ? 'on' : 'off' }}">{{ $u->active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                    <td>
                                        @if ($u->id_user !== Auth::id())
                                            <button type="button" class="link-act" wire:click="toggleUserActive({{ $u->id_user }})">{{ $u->active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
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
                        <div class="fld">
                            <label>Kode grup</label>
                            <input wire:model="g_code" placeholder="mis. PRODUKSI_SPV">
                            @error('g_code') <div class="note" style="color:var(--sp-danger)">{{ $message }}</div> @enderror
                        </div>
                        <div class="fld">
                            <label>Nama grup</label>
                            <input wire:model="g_name" placeholder="mis. Supervisor Produksi">
                            @error('g_name') <div class="note" style="color:var(--sp-danger)">{{ $message }}</div> @enderror
                        </div>
                        <div class="fld">
                            <label>Level manajemen (1=Staf .. 5=Direksi)</label>
                            <select wire:model="g_level" x-data x-effect="$el.value = @js($g_level)">
                                @foreach ([1 => '1 - Staf', 2 => '2 - Supervisor', 3 => '3 - Manager', 4 => '4 - GM', 5 => '5 - Direksi'] as $lvl => $label)
                                    <option value="{{ $lvl }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
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
                    <div class="fld" style="max-width:320px">
                        <label>Grup</label>
                        <select wire:model.live="permGroupId" x-data x-effect="$el.value = @js($permGroupId)">
                            @foreach ($this->groups as $g)
                                <option value="{{ $g->id_group }}">{{ $g->group_name }}</option>
                            @endforeach
                        </select>
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
        </div>
    </div>
</div>
