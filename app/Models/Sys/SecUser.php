<?php

/*
 * REMARK
 * Tujuan       : Model autentikasi utama (tm2sys.sec_user): login, grup, modul yang boleh diakses, gerbang hak per peran.
 * Sumber hulu  : tm2sys.sec_user, sec_user_group, sec_group_permission.
 * Input        : Kolom login/pswd, relasi grup dan modul.
 * Output       : Objek user, hasGroup(), isHrOfficer(), accessibleModules() dan gerbang peran lain.
 * Dipakai oleh : Seluruh aplikasi (auth, middleware modul, layar).
 * Status       : Aktif. isProdManagerOrAbove() dihapus 2026-10-18 (hanya dipakai layar Rule Library yang sudah dihapus). Kolom admin_theme_mode / admin_theme_accent dibuang 2026-10-22 (migration 2026_10_22_000200).
 */

namespace App\Models\Sys;

use App\Models\Master\EmpEmployee;
use App\Models\Master\OrgPosition;
use App\Services\Payroll\PayrollAccess;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;

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
        'email_verified',
        'email_verified_at',
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
        'email_verified' => 'boolean',
        'email_verified_at' => 'datetime',
    ];

    // Jo 2026-10-22: default_password disimpan POLOS (tanpa enkripsi) sampai UAT selesai, supaya terbaca langsung di tabel dan mudah dipakai reset.
    // Baris lama berbentuk ciphertext Crypt (awalan eyJpdiI6) tetap terbaca. Kembali ke terenkripsi: rollback migration 2026_10_22_000300 + pulihkan
    // accessor dari security_defaultpassword_spec.md §4 (hanya atas perintah Jo). Tidak masuk serialisasi (lihat $hidden).
    protected function defaultPassword(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? (str_starts_with($value, 'eyJpdiI6') ? Crypt::decryptString($value) : $value) : null,
            set: fn (?string $value) => $value ?: null,
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

    public function securityQuestion(): BelongsTo
    {
        return $this->belongsTo(SecRecoveryQuestion::class, 'security_question_id', 'id_question');
    }

    public function maxMgmtLevel(): int
    {
        return (int) ($this->groups()->max('mgmt_level') ?? 0);
    }

    // Menentukan pengalaman landing setelah login: Direksi (bukan admin) ->
    // langsung ke Laporan Manajemen, TANPA menu operasional sama sekali.
    // is_admin (mis. johiwan) SENGAJA dikecualikan meski kebetulan anggota
    // grup Direksi -- admin butuh akses operasional penuh utk kerja
    // sehari-hari (dev/config), landing tetap ke Menu Operasional spt user
    // biasa, pindah ke Laporan Manajemen lewat pill switcher di topbar
    // (main-menu.blade.php) -- bukan lewat layar pilihan terpisah lagi
    // (dihapus 2026-09-13, keluhan user "tidak perlu saya pertanyakan").
    public function isDireksiOnly(): bool
    {
        return ! $this->is_admin && $this->groups()->where('group_name', 'Direksi')->exists();
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

    // Tanda akses "terbuka untuk semua user login" (General Report: CO yang sudah di-post, tanpa harga — user 2026-09-30).
    // Bukan modul menu (user 2026-10-04): item GENERAL tinggal di modul pemiliknya (Sales, nanti Production) dan tetap
    // tampil untuk user yang tidak punya hak modul itu (AppMenu::modules).
    public const PUBLIC_MODULE = 'GENERAL';

    public function canAccessModule(string $moduleCode): bool
    {
        if ($this->is_admin || $moduleCode === self::PUBLIC_MODULE) {
            return true;
        }

        return $this->accessibleModules()->has($moduleCode);
    }

    // Gate link "Setup" — BRIEF §3.3: is_admin=1 atau akses modul SETUP.
    public function canSetup(): bool
    {
        return (bool) $this->is_admin || $this->canAccessModule('SETUP');
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

    // Gate input Deskripsi Kerja (org_position.job_description) & SOP
    // (tm2mnt.mnt_document, doc_set kosong) -- permintaan user 2026-09-13: "Yang input adalah HR
    // Officer. Begitu pula dengan SOP." is_admin bypass total via hasGroup().
    public function isHrOfficer(): bool
    {
        return $this->hasGroup('HR_OFFICER');
    }

    // "Manager Operasional atau lebih tinggi (GM/Direksi)" -- Tier1 §9.3/§9.4
    // (approve subkontrak->PO, approve substitusi bahan).
    public function isOpsManagerOrAbove(): bool
    {
        return $this->is_admin || $this->hasGroup('OPS_MGR', 'DIREKSI') || $this->maxMgmtLevel() >= 4;
    }

    // Gate nominal gaji = peran yang melihat SEMUA payroll (user 2026-10-07: hanya GM/Direksi, bukan admin biasa, bukan developer).
    // Cakupan lain (HR Officer: Non-staff, Op Manager: slip bawahan) ada di App\Services\Payroll\PayrollAccess.
    public function canViewPayrollAmounts(): bool
    {
        return PayrollAccess::scope($this) === PayrollAccess::ALL;
    }

    // Ekspor data gaji ke Excel (user 2026-10-07): file keluar dari sistem, jadi hanya yang melihat SEMUA payroll (Direksi/GM).
    // Op Manager (OPS_MGR, level 4) hanya melihat slip bawahannya, jadi TIDAK boleh mengekspor gaji semua pegawai.
    public function canExportPayrollAmounts(): bool
    {
        return $this->canViewPayrollAmounts();
    }

    // Harga jual order (CO/laporan order dengan harga) — user 2026-09-30: "order tanpa harga untuk produksi, purchase dst;
    // order dengan harga untuk sales, marketing, product costing serta op manager ke atas". MARKETING = grup untuk ke depan
    // (dibuat 2026-09-30, belum ada anggota; saat ini praktis hanya Sales).
    public const SALES_PRICE_GROUPS = ['SALES_STAFF', 'SALES_SPV', 'MGR_SALES', 'MARKETING', 'COSTING_STAFF', 'OPS_MGR', 'DIREKSI'];

    public function canViewSalesPrice(): bool
    {
        return (bool) $this->is_admin || $this->hasGroup(...self::SALES_PRICE_GROUPS) || $this->maxMgmtLevel() >= 4;
    }

    // employee_id -> tm2mst.emp_employee (LOGICAL, lintas-DB) -- dipakai Flow
    // Engine utk resolusi "posisi user login ini" saat menyusun antrian approval
    // (PrApproval, dst). Query terpisah, bukan join, sesuai CLAUDE.md §2.
    // Jabatan dari data pegawai yang terhubung (org_position.position_name), untuk label di kartu profil
    // -- bukan nama grup hak akses (user 2026-09-28: "saya General Manager, bukan Direksi").
    public function jobTitle(): ?string
    {
        $positionId = $this->currentPositionId();

        return $positionId ? OrgPosition::where('position_id', $positionId)->value('position_name') : null;
    }

    public function currentPositionId(): ?int
    {
        if (! $this->employee_id) {
            return null;
        }

        return EmpEmployee::where('employee_id', $this->employee_id)->value('position_id');
    }

    // module_code sys_app_menu (SALES/PROC/INV/PROD/MDATA/FINACC/SETUP) yang boleh
    // dilihat user ini di mega menu — dari sys_app_menu.required_object -> sec_module.
    // Baris tanpa required_object (harusnya tak ada di level module, lihat
    // SysAppMenuSeeder) dianggap default DENY (bukan lolos bebas).
    public function visibleAppMenuCodes(): Collection
    {
        if ($this->is_admin) {
            return SysAppMenu::query()
                ->where('menu_type', 'module')
                ->where('activate', true)
                ->pluck('code');
        }

        $accessible = $this->accessibleModules()->keys();

        return SysAppMenu::query()
            ->where('menu_type', 'module')
            ->where('activate', true)
            ->whereNotNull('required_object')
            ->get()
            ->filter(fn ($m) => $m->required_object === self::PUBLIC_MODULE || $accessible->contains($m->required_object))
            ->pluck('code');
    }
}
