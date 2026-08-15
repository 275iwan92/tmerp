<?php

namespace App\Models\Sys;

use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

// Model autentikasi UTAMA aplikasi (bukan tabel `users` bawaan Laravel — sudah
// dibuang, lihat CLEANUP_tm2sys.sql). config/auth.php provider 'users' diarahkan
// ke sini. username = kolom `login`, password = kolom `pswd` (bcrypt).
class SecUser extends Authenticatable implements CanResetPasswordContract
{
    use Notifiable;

    protected $connection = 'tm2sys';

    protected $table = 'sec_user';

    protected $primaryKey = 'id_user';

    protected $fillable = [
        'login',
        'pswd',
        'name',
        'email',
        'phone',
        'active',
        'is_admin',
        'reg_type',
        'id_number',
        'employee_id',
        'linked_type',
        'linked_id',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'pswd',
        'mfa',
        'activation_code',
    ];

    protected $casts = [
        'active' => 'boolean',
        'is_admin' => 'boolean',
        'is_active' => 'boolean',
        'mfa_last_updated' => 'datetime',
        'pswd_last_updated' => 'datetime',
    ];

    // Authenticatable default membaca kolom `password` — kolom kita `pswd`.
    public function getAuthPassword(): ?string
    {
        return $this->pswd;
    }

    // sec_user tak punya kolom remember_token (skema LOCKED, lihat TIER_3A_SECURITY.sql)
    // — nonaktifkan "ingat saya" persisten alih-alih error SQL saat Authenticatable
    // mencoba menyimpannya.
    public function getRememberTokenName(): string
    {
        return '';
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(SecGroup::class, 'sec_user_group', 'user_id', 'group_id')
            ->withPivot('id_user_group');
    }

    public function maxMgmtLevel(): int
    {
        return (int) ($this->groups()->max('mgmt_level') ?? 0);
    }

    // Izin efektif per modul (union dari semua grup user — paling permisif menang).
    // Key = id_module.
    protected function effectivePermissions(): Collection
    {
        $groupIds = $this->groups()->pluck('sec_group.id_group');

        if ($groupIds->isEmpty()) {
            return collect();
        }

        return SecGroupPermission::query()
            ->whereIn('group_id', $groupIds)
            ->get()
            ->groupBy('module_id')
            ->map(fn (Collection $rows) => [
                'can_access' => $rows->contains(fn ($r) => $r->can_access),
                'can_input' => $rows->contains(fn ($r) => $r->can_input),
                'can_approve' => $rows->contains(fn ($r) => $r->can_approve),
                'can_export' => $rows->contains(fn ($r) => $r->can_export),
            ]);
    }

    // Modul yang boleh diakses user ini, keyed by module_code — default DENY.
    // is_admin=1 bypass total (akses penuh semua modul aktif).
    public function accessibleModules(): Collection
    {
        $modules = SecModule::query()->where('is_active', true)->orderBy('sort_order')->get();

        if ($this->is_admin) {
            return $modules->keyBy('module_code')->map(fn (SecModule $m) => (object) [
                'module' => $m,
                'can_input' => true,
                'can_approve' => true,
                'can_export' => true,
            ]);
        }

        $perms = $this->effectivePermissions();
        $level = $this->maxMgmtLevel();

        return $modules
            ->filter(function (SecModule $m) use ($perms, $level) {
                $p = $perms->get($m->id_module);

                return $p && $p['can_access'] && $level >= $m->min_mgmt_level;
            })
            ->keyBy('module_code')
            ->map(function (SecModule $m) use ($perms) {
                $p = $perms->get($m->id_module);

                return (object) [
                    'module' => $m,
                    'can_input' => $p['can_input'],
                    'can_approve' => $p['can_approve'],
                    'can_export' => $p['can_export'],
                ];
            });
    }

    public function canAccessModule(string $moduleCode): bool
    {
        if ($this->is_admin) {
            return true;
        }

        return $this->accessibleModules()->has($moduleCode);
    }

    // Gate link "Setup" — BRIEF §3.3: is_admin=1 atau akses modul SETUP.
    public function canSetup(): bool
    {
        return (bool) $this->is_admin || $this->canAccessModule('SETUP');
    }

    // module_code sys_app_menu (SALES/PROC/INV/PROD/MDATA/FINACC/SETUP) yang boleh
    // dilihat user ini di mega menu — dari sys_app_menu.required_object -> sec_module.
    // Baris tanpa required_object (harusnya tak ada di level module, lihat
    // SysAppMenuSeeder) dianggap default DENY (bukan lolos bebas).
    public function visibleAppMenuCodes(): Collection
    {
        if ($this->is_admin) {
            return \App\Models\Sys\SysAppMenu::query()
                ->where('menu_type', 'module')
                ->where('is_active', true)
                ->pluck('code');
        }

        $accessible = $this->accessibleModules()->keys();

        return \App\Models\Sys\SysAppMenu::query()
            ->where('menu_type', 'module')
            ->where('is_active', true)
            ->whereNotNull('required_object')
            ->get()
            ->filter(fn ($m) => $accessible->contains($m->required_object))
            ->pluck('code');
    }
}
