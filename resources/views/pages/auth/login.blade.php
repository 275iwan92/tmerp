<?php

use App\Models\Sys\SecLoginAttempt;
use App\Models\Sys\SecUser;
use App\Models\Sys\SysLoginHero;
use App\Models\Sys\SysQuote;
use App\Support\Localization\AuthText;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

// layouts::guest — halaman penuh layar (Alur A Tier 1), TIDAK ikut container/
// padding layouts::app biasa (dipakai app shell pasca-login).
new #[Layout('layouts::guest')] #[Title('Masuk — TELASIH MAKMUR ERP')] class extends Component
{
    #[Url]
    public string $lang = 'id';

    public string $loginField = '';

    public string $pswd = '';

    public ?string $errorMessage = null;

    public bool $loginTouched = false;

    public bool $pswdTouched = false;

    public function mount(): void
    {
        if (! in_array($this->lang, ['id', 'en'], true)) {
            $this->lang = 'id';
        }
    }

    // Mirror objek T[lang] di mockup — sumbernya sys_translations (BRIEF §3.1.5).
    #[Computed]
    public function t(): array
    {
        return AuthText::forLang($this->lang);
    }

    public function setLang(string $lang): void
    {
        $this->lang = in_array($lang, ['id', 'en'], true) ? $lang : 'id';
    }

    // Kutipan hero (panel foto) — sumber sama dengan quote menu utama (sys_quotes),
    // tidak perlu tabel/array terpisah seperti di mockup.
    #[Computed]
    public function quote(): array
    {
        $q = SysQuote::random();

        return [
            't' => $q?->quote_text ?? 'Selamat bekerja.',
            'a' => $q?->author ?? 'Telasih Makmur',
        ];
    }

    // Foto hero — Tier 3B-S3 (sys_login_hero), acak server-side per pemuatan
    // halaman (dulu hardcode di Alpine init()).
    #[Computed]
    public function hero(): array
    {
        $h = SysLoginHero::random();

        return [
            'url' => $h?->image_url ?? 'https://upload.wikimedia.org/wikipedia/commons/e/ea/Offset_press_4_color.jpg',
            'caption' => $h?->caption ?? '',
        ];
    }

    // Akun UAT: config uat.accounts (hanya di .env.mockup, keputusan user 2026-09-25). Kosong = panel tidak tampil.
    #[Computed]
    public function uatAccounts(): array
    {
        return collect(explode(';', (string) config('uat.accounts')))
            ->map(fn ($l) => array_map('trim', explode('|', $l)))
            ->filter(fn ($p) => count($p) >= 2 && $p[0] !== '')
            ->map(fn ($p) => ['login' => $p[0], 'password' => $p[1], 'role' => $p[2] ?? ''])
            ->values()->all();
    }

    public function fillUat(int $i): void
    {
        $a = $this->uatAccounts[$i] ?? null;
        if ($a) {
            $this->loginField = $a['login'];
            $this->pswd = $a['password'];
        }
    }

    public function submit()
    {
        $this->errorMessage = null;
        $this->loginTouched = true;
        $this->pswdTouched = true;

        $login = trim($this->loginField);

        if ($login === '' || $this->pswd === '') {
            $this->errorMessage = $this->t['err_empty'] ?? 'Isi username dan password.';

            return;
        }

        // Label mockup "Username atau email" — terima keduanya di kolom yang sama.
        $user = SecUser::query()->where('login', $login)->orWhere('email', $login)->first();
        $ok = $user && $user->active && $user->activate && Hash::check($this->pswd, (string) $user->pswd);

        // BRIEF §3.1.3: catat SETIAP percobaan, berhasil atau gagal.
        SecLoginAttempt::create([
            'login' => $login,
            'success' => $ok,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
        ]);

        if (! $ok) {
            $this->errorMessage = $this->t['err_wrong'] ?? 'Username atau password salah.';
            $this->pswd = '';

            return;
        }

        Auth::login($user);
        request()->session()->regenerate();
        // Login baru = mulai bersih di Shell v2 (tab lama ditutup, menu di lapis utama, opening screen tampil) -- user 2026-10-03.
        request()->session()->put('dl_fresh_login', true);

        // Landing setelah login berbasis LEVEL, bukan role/nama grup spesifik
        // (instruksi eksplisit user 2026-09-13): "check level dulu, kalau
        // Operational Manager keatas -> default Management Report, kalau
        // kebawah -> Operational Menu". canAccessModule('MGMT') sudah
        // menegakkan aturan yang SAMA PERSIS (can_access + mgmt_level >=
        // min_mgmt_level, is_admin bypass -- lihat EnsureModuleAccess), jadi
        // dipakai ulang di sini supaya "siapa default ke mana" tidak pernah
        // menyimpang dari "siapa yang BENERAN boleh buka" (min_mgmt_level
        // MGMT diturunkan ke 4/Manager Operasional di migration
        // 2026_09_13_000011, sebelumnya 5/Direksi-only).
        //
        // Direksi murni tetap ditegakkan lagi di main-menu.blade.php::mount()
        // (hard lock, tak ada menu operasional sama sekali) kalau /menu
        // diketik manual -- match ini hanya soal landing DEFAULT.
        //
        // Sebelumnya admin dilempar ke layar pilihan dulu ("Mau buka yang
        // mana dulu?", route landing.choice) -- DIHAPUS (keluhan user
        // 2026-09-13: "jelek banget dan sangat tidak praktis, tidak perlu
        // saya pertanyakan"). Sejak tombol "Laporan Manajemen" ditambahkan
        // langsung di topbar Menu Operasional (main-menu.blade.php), pindah
        // ke Laporan Manajemen sudah 1 klik kapan saja -- pertanyaan
        // "mana dulu" di setiap login jadi friksi tanpa guna.
        $default = match (true) {
            // v2: yang punya papan widget (top management, user 2026-10-02) mendarat di layar pembukanya sendiri -- tanpa tab yang
            // menutupinya. Database yang belum punya tabel papan: Direksi / akses MGMT tetap mendarat dengan tab Laporan Eksekutif.
            config('app.ui') === 'v2' && \App\Support\V2\Board\Board::profile($user) !== null => route('v2.home'),
            config('app.ui') === 'v2' => ($user->isDireksiOnly() || $user->canAccessModule('MGMT')) ? route('v2.home', ['open' => 'MGMT_EXEC']) : route('v2.home'),
            $user->isDireksiOnly() => route('report.executive'),
            $user->canAccessModule('MGMT') => route('report.executive'),
            default => route('menu'),
        };

        return redirect()->intended($default);
    }
};
?>

<div class="authpage hero"
     x-data="{
        panelWidth: null,
        dragging: false,
        startDrag() { this.dragging = true; document.body.classList.add('resizing'); },
        onDrag(e) {
            if (! this.dragging) return;
            const min = Math.max(window.innerWidth * 0.15, 260), max = window.innerWidth * 0.40;
            this.panelWidth = Math.max(min, Math.min(max, window.innerWidth - e.clientX)) + 'px';
        },
        stopDrag() { this.dragging = false; document.body.classList.remove('resizing'); },
     }"
     @mousemove.window="onDrag($event)" @mouseup.window="stopDrag()">
    <div class="wrap">
        <div class="hero-panel">
            <img src="{{ $this->hero['url'] }}" alt="{{ $this->hero['caption'] }}" @load="$el.classList.add('on')" x-init="$el.complete && $el.classList.add('on')">
            <div class="hero-veil"></div>
            <x-auth.hero-brand />
            <div class="hero-clock" wire:key="clock-{{ $lang }}" x-data="{ time: '', date: '' }" x-init="
                    const fmt = () => {
                        const now = new Date();
                        const loc = @js($lang) === 'id' ? 'id-ID' : 'en-US';
                        time = now.toLocaleTimeString(loc, { hour12: false });
                        date = now.toLocaleDateString(loc, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
                    };
                    fmt(); setInterval(fmt, 1000);
                ">
                <div class="t" x-text="time">--:--:--</div>
                <div class="d" x-text="date">—</div>
            </div>
            <div class="hero-quote">
                <div class="qi">&#8220;</div>
                <div class="q">{{ $this->quote['t'] }}</div>
                <div class="a">{{ $this->quote['a'] }}</div>
            </div>
        </div>

        <div class="grip" @mousedown="startDrag()" title="Tarik untuk melebarkan"></div>

        <div class="hero-side" :style="panelWidth ? { width: panelWidth } : {}">
            <div class="flagbar">
                <button type="button" class="flag {{ $lang === 'id' ? 'active' : '' }}" wire:click="setLang('id')" title="Bahasa Indonesia">🇮🇩</button>
                <button type="button" class="flag {{ $lang === 'en' ? 'active' : '' }}" wire:click="setLang('en')" title="English">🇬🇧</button>
            </div>

            <div class="form-center">
                <div class="login-box">
                    <h2>{{ $this->t['login_heading'] ?? 'Masuk' }}</h2>
                    <p class="sub">{{ $this->t['login_sub'] ?? 'Gunakan akun terdaftar untuk masuk.' }}</p>

                    @if ($errorMessage)
                        <div class="alert show">{{ $errorMessage }}</div>
                    @endif

                    <form wire:submit="submit">
                        <div class="field">
                            <label>{{ $this->t['label_username'] ?? 'Username atau email' }}</label>
                            <div class="input {{ $loginTouched && trim($loginField) === '' ? 'error' : '' }}">
                                <i class="fa-solid fa-user fld-i"></i>
                                <input type="text" wire:model="loginField" autocomplete="username" autofocus>
                            </div>
                        </div>
                        <div class="field" x-data="{ show: false }">
                            <label>{{ $this->t['label_password'] ?? 'Password' }}</label>
                            <div class="input {{ $pswdTouched && $pswd === '' ? 'error' : '' }}">
                                <i class="fa-solid fa-lock fld-i"></i>
                                <input :type="show ? 'text' : 'password'" wire:model="pswd" autocomplete="current-password">
                                <button type="button" class="toggle-pw" @click="show = !show"><i class="fa-solid" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i></button>
                            </div>
                        </div>
                        <div class="row">
                            <a href="{{ route('password.request', ['lang' => $lang]) }}">{{ $this->t['forgot'] ?? 'Lupa password?' }}</a>
                        </div>
                        <button type="submit" class="btn" wire:loading.attr="disabled">{{ $this->t['btn_login'] ?? 'Masuk' }}</button>
                    </form>

                    <div class="divider"><span>atau</span></div>
                    <div class="reg">Belum punya akun? <a href="{{ route('register', ['lang' => $lang]) }}">Daftar</a></div>

                    {{-- Akun UAT: daftar dari .env.mockup (UAT_ACCOUNTS), klik baris = login & password terisi.
                         Hanya tampil di mockup.test; tmerp.test live tidak menampilkannya. --}}
                    @if ($this->uatAccounts)
                        <div class="uat-box" x-data="{ open: false }">
                            <button type="button" class="uat-toggle" @click="open = !open" title="Daftar akun untuk UAT (User Acceptance Test)">
                                🧪 Akun Uji Coba (UAT) <span x-text="open ? '▲' : '▼'"></span>
                            </button>
                            <div class="uat-panel" x-show="open" x-cloak x-transition>
                                <p class="uat-note">Klik satu baris: login &amp; password terisi, lalu tekan Masuk.</p>
                                <table class="uat-table">
                                    <tbody>
                                        @foreach ($this->uatAccounts as $i => $a)
                                            <tr wire:click="fillUat({{ $i }})" style="cursor:pointer" title="Isi login {{ $a['login'] }}"><td>{{ $a['role'] }}</td><td><code>{{ $a['login'] }}</code></td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
