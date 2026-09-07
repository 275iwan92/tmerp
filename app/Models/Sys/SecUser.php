<?php

namespace App\Models\Sys;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;

// Model autentikasi UTAMA aplikasi (bukan tabel `users` bawaan Laravel — sudah
// dibuang, lihat CLEANUP_tm2sys.sql). config/auth.php provider 'users' diarahkan
// ke sini. username = kolom `login`, password = kolom `pswd` (bcrypt).
class SecUser extends Authenticatable implements CanResetPasswordContract, FilamentUser
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
        'activate',
        'created_by',
        'updated_by',
        'default_password',
        'must_change_password',
        'approval_status',
        'theme_code',
        'lang_code',
        'admin_theme_mode',
        'admin_theme_accent',
        'pswd_last_updated',
        'birth_date',
        'mother_name_hash',
        'security_question_id',
        'security_answer_hash',
        'security_setup_at',
    ];

    protected $hidden = [
        'pswd',
        'mfa',
        'activation_code',
        'default_password',
        'mother_name_hash',
        'security_answer_hash',
    ];

    protected $casts = [
        'active' => 'boolean',
        'is_admin' => 'boolean',
        'activate' => 'boolean',
        'must_change_password' => 'boolean',
        'mfa_last_updated' => 'datetime',
        'pswd_last_updated' => 'datetime',
        'birth_date' => 'date',
        'security_setup_at' => 'datetime',
    ];

    // security_defaultpassword_spec.md §4 — SET/GET plaintext, tersimpan
    // ciphertext (Crypt::encryptString, key=APP_KEY). Admin baca lewat layar
    // app (accessor auto-decrypt), bukan lewat SQL/serialisasi (lihat $hidden).
    protected function defaultPassword(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Crypt::decryptString($value) : null,
            set: fn (?string $value) => $value ? Crypt::encryptString($value) : null,
        );
    }

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

    public function securityQuestion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SecRecoveryQuestion::class, 'security_question_id', 'id_question');
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
        $modules = SecModule::query()->where('activate', true)->orderBy('sort_order')->get();

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

    // Interface FilamentUser (2026-08-30, migrasi Filament v5 bertahap) —
    // gate MASUK ke panel /admin, bukan gate per-modul (itu tetap ditangani
    // shouldRegisterNavigation()/getEloquentQuery() per Resource, cermin
    // canAccessModule()/visibleAppMenuCodes() yang sudah ada). Akun nonaktif
    // (activate=0) diblokir total, konsisten dgn shell lama.
    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->activate;
    }

    // Role gate PPIC/Production Scheduling (production_scheduling_tier1.md §9.7,
    // tier2.md §9 permission matrix). is_admin bypass total, konsisten pola
    // canAccessModule() di atas.
    public function hasGroup(string ...$groupCodes): bool
    {
        if ($this->is_admin) {
            return true;
        }

        return $this->groups()->whereIn('sec_group.group_code', $groupCodes)->exists();
    }

    public function isQa(): bool
    {
        return $this->hasGroup('QA');
    }

    // "Manager Produksi atau lebih tinggi" -- Tier1 §9.7 (Rule Library ACTIVATE).
    public function isProdManagerOrAbove(): bool
    {
        return $this->is_admin || $this->hasGroup('PROD_MGR', 'OPS_MGR', 'DIREKSI') || $this->maxMgmtLevel() >= 3;
    }

    // "Manager Operasional atau lebih tinggi (GM/Direksi)" -- Tier1 §9.3/§9.4
    // (approve subkontrak->PO, approve substitusi bahan).
    public function isOpsManagerOrAbove(): bool
    {
        return $this->is_admin || $this->hasGroup('OPS_MGR', 'DIREKSI') || $this->maxMgmtLevel() >= 4;
    }

    // employee_id -> tm2mst.emp_employee (LOGICAL, lintas-DB) -- dipakai Flow
    // Engine utk resolusi "posisi user login ini" saat menyusun antrian approval
    // (PrApproval, dst). Query terpisah, bukan join, sesuai CLAUDE.md §2.
    public function currentPositionId(): ?int
    {
        if (! $this->employee_id) {
            return null;
        }

        return \App\Models\Master\EmpEmployee::where('employee_id', $this->employee_id)->value('position_id');
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
                ->where('activate', true)
                ->pluck('code');
        }

        $accessible = $this->accessibleModules()->keys();

        return \App\Models\Sys\SysAppMenu::query()
            ->where('menu_type', 'module')
            ->where('activate', true)
            ->whereNotNull('required_object')
            ->get()
            ->filter(fn ($m) => $accessible->contains($m->required_object))
            ->pluck('code');
    }
}
