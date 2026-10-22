# SECURITY — `sec_user.default_password` + `must_change_password`

> **2026-10-22 (perintah Jo): `default_password` disimpan POLOS, TIDAK dienkripsi, sampai Jo bilang UAT selesai.** Bagian "terenkripsi / Crypt" di bawah berlaku lagi hanya atas perintah Jo (rollback migration `2026_10_22_000300` + pulihkan accessor di `SecUser`). Admin kini juga punya tombol Reset to Default di layar Account.

**Untuk:** Claude Code (Jo) — eksekusi langsung, spec self-contained, tanpa keputusan tersisa.
**Ditulis oleh:** Telma (Chat) berdasarkan diskusi arsitektur dengan Jo.
**Target DB:** `tm2sys` · tabel `sec_user` · Stack Laravel 13 + Livewire + MySQL 8.
**Sifat tugas:** FIX + fitur baru (migration + logic). Tidak menyentuh costing/regresi.

---

## 0. Tujuan bisnis (kenapa fitur ini ada)

Admin butuh **password default yang bisa dibaca** untuk:
1. **Migrasi** dari sistem lama — user login pakai password lama, tanpa reset massal.
2. **Fallback lupa password** — email/WA gateway BELUM tentu jalan di Docker (SMTP eksternal Gmail bisa diblokir server). Selama gateway belum pasti, admin officer **memberi tahu user secara manual** password default-nya. Karena itu password default WAJIB bisa dibaca admin.

Password default disimpan **terenkripsi (dapat di-decrypt)**, BUKAN hash — karena harus bisa dibaca kembali. Pengaman lapis kedua: begitu user login pakai default, sistem **memaksa ganti password**.

---

## 1. KEPUTUSAN TERKUNCI (jangan re-question)

1. **Dua kolom baru** di `sec_user`:
   - `default_password` — ciphertext (Laravel `Crypt`, AES-256, key = `APP_KEY`). **Permanen**, tidak pernah di-NULL.
   - `must_change_password` — `tinyint(1)`, DEFAULT `0`.
2. **Enkripsi = Laravel `Crypt` (encryptString/decryptString)**, BUKAN `AES_ENCRYPT` native MySQL.
   Alasan: key tak pernah masuk query/log, portable Docker/MariaDB, admin baca lewat **layar app** (bukan SQL langsung).
3. **Admin baca default_password lewat grid/screen admin** (app auto-decrypt via accessor). Bukan via SQL.
4. **Alur reset password** (self-service ke depan / manual sekarang):
   - Generate password baru random readable.
   - `default_password = Crypt::encryptString(passwordBaru)` → **timpa** nilai lama.
   - `pswd = Hash::make(passwordBaru)`.
   - `must_change_password = 1`.
   - `pswd_last_updated = now()`.
5. **Login dengan `must_change_password=1`** → user DIPAKSA ke halaman ganti password sebelum akses fitur apa pun.
6. **Setelah user ganti password sendiri**:
   - `pswd = Hash::make(passwordPilihanUser)`.
   - `must_change_password = 0`.
   - `default_password` **TETAP** tersimpan (fallback reset berikutnya). **JANGAN di-NULL/di-ubah** saat user ganti password.
   - Password pilihan user **wajib berbeda** dari default_password saat ini (tolak bila sama).
7. **Konsekuensi sadar & diterima:** setelah reset pertama, `default_password` bukan lagi "password lama sistem lama" — sudah ketimpa password baru. Perannya bergeser menjadi "password terbaca yang berlaku sekarang sebagai fallback". Ini benar & disengaja.
8. **Notifikasi = toggle, bukan dependency.** `sys_setting.notify_channel` ∈ `{manual, email, whatsapp}`, default `manual`.
   - Jalur **manual SELALU** ada: reset selalu tulis `default_password` + tampil ke admin. Tidak bergantung email.
   - Bila `email` aktif → kirim SMTP eksternal sebagai **tambahan**. Gagal kirim TIDAK memblokir reset (user tetap dilayani manual).
   - `whatsapp` = placeholder struktur untuk fase depan (belum implement pengiriman).
   - Tujuan toggle: Jo bisa uji "server Docker bisa kirim SMTP atau tidak" tanpa mematikan fungsi reset.

---

## 2. Temuan struktur existing (sudah diverifikasi dari `tm2sys.sql`)

- Kolom password bernama **`pswd`** varchar(255) — bukan `password`.
- **`pswd_last_updated`** timestamp — sudah ada, dipakai untuk stempel reset.
- `sec_user` PK = `id_user`; unique `login`.
- **Belum ada** `default_password` maupun `must_change_password`.
- **`sys_setting`** = key-value (`setting_key` PK, `setting_value`) — dipakai simpan `notify_channel`.
- Sudah ada: `mfa`, `activation_code`, `approval_status`, `email_verified`, `wa_verified`, `email`, `phone` — jalur notifikasi email/WA ke depan setengah tersiapkan.
- `sec_group` existing HANYA: `SALES_STAFF`(1), `SALES_SPV`(2), `DIREKSI`(3), `PRODUKSI`(4). **Group warehouse/HR/PPIC/purchasing/costing/op-manager BELUM ada.**

---

## 3. Migration

> MySQL 8 TIDAK support `ADD COLUMN IF NOT EXISTS`. Pakai Schema builder standar.
> `default_password` pakai **TEXT** (bukan varchar) — ciphertext Laravel `Crypt` untuk password pendek bisa mendekati 250 char; TEXT memberi margin aman.

```php
// database/migrations/xxxx_add_default_password_to_sec_user.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('tm2sys')->table('sec_user', function (Blueprint $t) {
            $t->text('default_password')->nullable()->after('pswd')
              ->comment('Ciphertext Laravel Crypt (AES-256, key=APP_KEY). Password default terbaca oleh admin. Permanen, timpa saat reset.');
            $t->boolean('must_change_password')->default(false)->after('default_password')
              ->comment('1 = user wajib ganti password saat login berikutnya (habis reset / user baru).');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sys')->table('sec_user', function (Blueprint $t) {
            $t->dropColumn(['default_password', 'must_change_password']);
        });
    }
};
```

> Ganti `'tm2sys'` bila nama koneksi di `config/database.php` berbeda. Bila model `sec_user` tidak memakai koneksi terpisah, hapus `->connection('tm2sys')`.

---

## 4. Model (`sec_user`)

Cari model yang memetakan tabel `sec_user` (kemungkinan `App\Models\User` dengan `$table='sec_user'`, `$primaryKey='id_user'`). Tambahkan:

```php
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Crypt;

// casting kolom flag
protected $casts = [
    'must_change_password' => 'boolean',
    // ...cast lain yang sudah ada
];

/**
 * default_password: simpan terenkripsi, baca ter-decrypt otomatis.
 * SET: $user->default_password = 'PlainBaru';   // otomatis Crypt::encryptString
 * GET: $user->default_password;                 // otomatis Crypt::decryptString (plaintext)
 */
protected function defaultPassword(): Attribute
{
    return Attribute::make(
        get: fn ($value) => $value ? Crypt::decryptString($value) : null,
        set: fn ($value) => $value ? Crypt::encryptString($value) : null,
    );
}
```

> Pastikan `pswd` TIDAK muncul di `$fillable` polos yang bisa mass-assign plaintext, dan `default_password` + `pswd` masuk `$hidden` agar tidak bocor ke JSON/API. Admin membaca default_password lewat query eksplisit di layar admin, bukan lewat serialisasi default.

---

## 5. Service — generator + reset

`app/Services/Security/PasswordResetService.php`

```php
namespace App\Services\Security;

use App\Models\User;              // model sec_user
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetService
{
    /**
     * Generate password readable: hindari karakter ambigu (0/O, 1/l/I).
     * Format contoh: Tmk-7fK9p2  (prefix + 6 char)
     */
    public function generate(): string
    {
        $pool = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $body = '';
        for ($i = 0; $i < 6; $i++) $body .= $pool[random_int(0, strlen($pool) - 1)];
        return 'Tmk-' . $body;
    }

    /**
     * Reset ke password baru (dipakai admin manual & self-service depan).
     * Return plaintext baru (untuk ditampilkan ke admin / dikirim notifikasi).
     */
    public function reset(User $user): string
    {
        $plain = $this->generate();

        $user->default_password    = $plain;                 // accessor → Crypt::encryptString
        $user->pswd                = Hash::make($plain);
        $user->must_change_password = true;
        $user->pswd_last_updated   = now();
        $user->save();

        // Notifikasi = tambahan, TIDAK memblokir. Gagal kirim tetap sukses reset.
        try {
            app(PasswordNotifier::class)->send($user, $plain);
        } catch (\Throwable $e) {
            report($e); // log saja; admin tetap bisa kasih tahu manual
        }

        return $plain;
    }

    /**
     * User mengganti password sendiri (setelah dipaksa / sukarela).
     * default_password TIDAK diubah. Tolak bila sama dengan default.
     */
    public function changeByUser(User $user, string $new): void
    {
        if ($user->default_password && hash_equals($user->default_password, $new)) {
            throw new \InvalidArgumentException('Password baru tidak boleh sama dengan password default.');
        }
        $user->pswd                 = Hash::make($new);
        $user->must_change_password = false;              // default_password TETAP
        $user->pswd_last_updated    = now();
        $user->save();
    }
}
```

`app/Services/Security/PasswordNotifier.php` (toggle):

```php
namespace App\Services\Security;

use App\Models\User;
use App\Models\SysSetting;   // model sys_setting (setting_key PK)

class PasswordNotifier
{
    public function send(User $user, string $plainPassword): void
    {
        $channel = optional(SysSetting::find('notify_channel'))->setting_value ?? 'manual';

        match ($channel) {
            'email'    => $this->sendEmail($user, $plainPassword),
            'whatsapp' => $this->sendWhatsapp($user, $plainPassword), // TODO fase depan: no-op sekarang
            default    => null, // 'manual' → tidak kirim apa pun; admin lihat di grid
        };
    }

    protected function sendEmail(User $user, string $plain): void
    {
        if (empty($user->email)) return;
        // Mail::to($user->email)->send(new DefaultPasswordMail($user, $plain));
        // SMTP eksternal (Gmail) via config MAIL_* di .env. Bila server Docker blok SMTP → exception
        // ditangkap di PasswordResetService; reset tetap sukses.
    }

    protected function sendWhatsapp(User $user, string $plain): void
    {
        // TODO fase depan (gateway WA). Sekarang no-op agar toggle bisa dipilih tanpa error.
    }
}
```

> Tambah row `sys_setting`: `('notify_channel', 'manual')` bila belum ada (lihat seeder §7).

---

## 6. Middleware — paksa ganti password

`app/Http/Middleware/ForcePasswordChange.php`

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForcePasswordChange
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && $user->must_change_password) {
            // izinkan route ganti password + logout agar tak infinite-redirect
            if (! $request->routeIs('password.change', 'password.change.update', 'logout')) {
                return redirect()->route('password.change');
            }
        }
        return $next($request);
    }
}
```

Registrasi di `bootstrap/app.php` (Laravel 11+), pasang ke grup `web` **setelah** `auth`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->appendToGroup('auth', [\App\Http\Middleware\ForcePasswordChange::class]);
    // atau daftarkan alias & pasang di route group yang butuh auth
})
```

Butuh route + Livewire component `password.change` (form: password baru + konfirmasi) yang memanggil `PasswordResetService::changeByUser()`.

---

## 7. Seeder — 6 user key person UAT

`database/seeders/UatKeyPersonSeeder.php`

Login = nama depan (lowercase). Email = `<login>@telasih.co.id`. `is_admin=0`, `active=1`, `must_change_password=1`. Password random per user, di-generate & **ditampilkan di console log** agar tester langsung catat.

```php
namespace Database\Seeders;

use App\Models\User;
use App\Services\Security\PasswordResetService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UatKeyPersonSeeder extends Seeder
{
    public function run(): void
    {
        $svc = app(PasswordResetService::class);

        $people = [
            ['login' => 'nisa',   'name' => 'Nisa',   'role' => 'Operational Manager'],
            ['login' => 'arinda', 'name' => 'Arinda', 'role' => 'Warehouse'],
            ['login' => 'sania',  'name' => 'Sania',  'role' => 'HR'],
            ['login' => 'tya',    'name' => 'Tya',    'role' => 'PPIC'],
            ['login' => 'eka',    'name' => 'Eka',    'role' => 'Purchasing'],
            ['login' => 'wiwik',  'name' => 'Wiwik',  'role' => 'Costing'],
        ];

        $this->command->info('=== UAT KEY PERSON — CATAT PASSWORD INI ===');
        foreach ($people as $p) {
            $plain = $svc->generate();

            $user = User::updateOrCreate(
                ['login' => $p['login']],
                [
                    'name'                 => $p['name'],
                    'email'                => $p['login'] . '@telasih.co.id',
                    'pswd'                 => Hash::make($plain),
                    'default_password'     => $plain,     // accessor → encrypt
                    'must_change_password' => true,
                    'active'               => 1,
                    'is_admin'             => 0,
                    'approval_status'      => 'approved',
                    'theme_code'           => 'navy',
                    'pswd_last_updated'    => now(),
                ]
            );

            $this->command->line(sprintf('%-8s | %-20s | %s', $p['login'], $p['role'], $plain));
        }
        $this->command->info('===========================================');
    }
}
```

Pastikan `sys_setting.notify_channel` ada (idempotent):

```php
// di seeder terpisah atau di atas run() UatKeyPersonSeeder
\App\Models\SysSetting::updateOrCreate(
    ['setting_key' => 'notify_channel'],
    ['setting_value' => 'manual']
);
```

### TODO opsional — assignment group (JANGAN diarang tanpa keputusan Jo)
Group untuk Warehouse/HR/PPIC/Purchasing/Costing/Op-Manager **belum ada** di `sec_group` (baru ada SALES_STAFF, SALES_SPV, DIREKSI, PRODUKSI). Pilihan, tunggu keputusan Jo:
- (a) Buat group baru + permission per modul, lalu isi `sec_user_group`, atau
- (b) UAT sementara tanpa group (akses diatur `is_admin`/manual), atau
- (c) Map sementara: Costing→(butuh group costing), dst.
Seeder di atas **tidak** menyentuh `sec_user_group` — aman dijalankan lebih dulu.

---

## 8. Urutan eksekusi

1. Jalankan migration §3.
2. Tambah accessor + cast + `$hidden` di model §4.
3. Buat `PasswordResetService` + `PasswordNotifier` §5.
4. Buat middleware + route/Livewire `password.change` §6.
5. Seed `notify_channel` + `UatKeyPersonSeeder` §7 → **catat password dari console**.
6. Uji: login salah satu user UAT → harus dipaksa ganti password → setelah ganti, `must_change_password=0`, `default_password` tetap terbaca di grid admin.

## 9. Catatan keamanan (operasional)
- File dump `.sql` berisi `default_password` = **ciphertext**, aman tanpa `APP_KEY`. Tetap: **JANGAN commit `.env` (APP_KEY) ke Git.** Bila APP_KEY hilang/di-rotate, seluruh `default_password` tak terbaca — dokumentasikan APP_KEY di brankas.
- `default_password` & `pswd` masuk `$hidden` model → tak bocor lewat serialisasi API/JSON.

---

## 10. ADDENDUM 2026-08-27 — Lupa Password berbasis 4 pertanyaan (MENGGANTIKAN alur email)

Keputusan user: alur `Password::broker()->sendResetLink()` (link email) di
`auth/forgot-password.blade.php` **diganti total**, bukan ditambah pilihan —
alasannya sama dengan §0 di atas (gateway email/WA belum tentu jalan). User
self-service jawab **4 pertanyaan**, semua benar → password **kembali ke
`default_password` yang sudah ada** (bukan generate baru).

**Kolom baru `sec_user`** (migration `2026_08_27_000006`, direvisi
`2026_08_27_000007`): `birth_date` (date), `mother_name_hash` (bcrypt,
**bukan** Crypt — tak pernah perlu ditampilkan balik), `security_question_id`
(FK fisik → `sec_recovery_question.id_question`, sesama tm2sys jadi FK boleh
dipakai, CLAUDE.md §2), `security_answer_hash` (bcrypt), `security_setup_at`
(NULL = belum setup).

**Tabel baru `tm2sys.sec_recovery_question`** (migration `2026_08_27_000007`)
— daftar 6 pertanyaan keamanan tetap, awalnya sempat hardcode array PHP
(`App\Support\SecurityQuestions`, **DIHAPUS**) lalu direvisi ke tabel atas
instruksi user: ini murni domain security/system, harus di tm2sys (bukan
tm2mst), prefix `sec_` (sejalur `sec_user`/`sec_group`/`sec_module`, bukan
`sys_` yang di database ini isinya UI/config spt `sys_app_menu`/`sys_theme`).
Model: `App\Models\Sys\SecRecoveryQuestion`.

**4 pertanyaan & sumber datanya:**
1. Tanggal lahir — kalau `sec_user.employee_id` tertaut, otomatis disalin dari
   `emp_employee.birth_date` sekali saat setup (read-only di layar, user
   tinggal konfirmasi); tak tertaut → isi manual.
2. Nama ibu kandung — isi manual, di-hash.
3. No HP terdaftar — pakai `sec_user.phone` yang sudah ada.
4. Pertanyaan keamanan pilihan user (dropdown dari `sec_recovery_question`
   yang `activate=1`) + jawaban, di-hash.

**Setup dipaksa via `ForcePasswordChange` middleware** (diperluas, bukan
middleware baru) — independen dari `must_change_password`, supaya 146 akun
lama yang sudah pernah ganti password dulu (jadi tak pernah lagi kena gerbang
`must_change_password`) tetap kebagian setup. Urutan kalau user butuh
dua-duanya: `password.change` dulu, baru `security.setup`.

**Layar baru:** `/security/setup` (`pages::auth.security-setup`) — setup
sekali. `/forgot-password` (`pages::auth.forgot-password`) — dirombak total
jadi 3-langkah: cari akun → jawab 4 pertanyaan → sukses.

**Service baru:** `App\Services\Security\SecurityRecoveryService` —
`isSetupComplete()`, `resolveBirthDateFromEmployee()`, `saveSetup()`,
`verify()` (semua-atau-tidak-sama-sekali, tak bilang field mana yang salah),
`resetToDefaultPassword()` (reuse `default_password` apa adanya, **tidak**
generate baru — beda dari `PasswordResetService::reset()` yang memang
generate password baru untuk kasus reset manual oleh Admin).

**Rate limit:** `RateLimiter` bawaan Laravel, key `login+ip`, 8 percobaan per
15 menit, di kedua langkah (cari akun & jawab pertanyaan) — mencegah tebak
paksa tanggal lahir/no HP.
