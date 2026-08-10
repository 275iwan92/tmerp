<?php

use App\Models\Sys\SecUser;
use App\Models\Sys\SysNotification;
use App\Models\Sys\SysQuote;
use App\Models\Sys\SysTranslation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::guest')] #[Title('Daftar Akun — TELASIH MAKMUR ERP')] class extends Component
{
    #[Url]
    public string $lang = 'id';

    public string $ptype = 'customer';

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $idno = '';

    public string $pass = '';

    public string $pass_confirmation = '';

    public bool $emailVerified = false;

    public ?string $emailHint = null;

    public string $emailHintClass = '';

    public bool $waVerified = false;

    public bool $showOtp = false;

    public string $otp = '';

    public ?string $waHint = null;

    public string $waHintClass = '';

    public ?string $errorMessage = null;

    public bool $submitted = false;

    public function mount(): void
    {
        if (! in_array($this->lang, ['id', 'en'], true)) {
            $this->lang = 'id';
        }
    }

    #[Computed]
    public function t(): array
    {
        return SysTranslation::forLang($this->lang) ?: SysTranslation::forLang('id');
    }

    #[Computed]
    public function quote(): array
    {
        $q = SysQuote::random();

        return ['t' => $q?->quote_text ?? 'Selamat bekerja.', 'a' => $q?->author ?? 'Telasih Makmur'];
    }

    public function setLang(string $lang): void
    {
        $this->lang = in_array($lang, ['id', 'en'], true) ? $lang : 'id';
    }

    public function isInternal(): bool
    {
        return $this->ptype === 'internal';
    }

    public function idLabel(): string
    {
        return $this->isInternal() ? 'NRP (No. Induk Pegawai)' : 'NIK (No. KTP)';
    }

    // Username diturunkan dari lokal-email (bagian sebelum @), ditampilkan
    // readonly — sesuai mockup. Keunikan sesungguhnya diselesaikan server-side
    // saat submit() (sufiks angka bila bentrok), bukan di sini.
    #[Computed]
    public function loginPreview(): string
    {
        $local = Str::of($this->email)->before('@')->lower()->toString();

        return preg_replace('/[^a-z0-9._-]/', '', $local);
    }

    public function updatedEmail(): void
    {
        $this->emailVerified = false;
        $this->emailHint = null;
        $this->emailHintClass = '';
    }

    public function updatedPtype(): void
    {
        $this->idno = '';
    }

    // Verifikasi email (demo) — CLAUDE.md/mockup: PRODUKSI cek MX/deliverability
    // lalu kirim tautan verifikasi. Di sini hanya validasi format.
    public function verifyEmail(): void
    {
        if (! filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $this->emailHint = 'Format email tidak valid.';
            $this->emailHintClass = 'bad';

            return;
        }

        $this->emailVerified = true;
        $this->emailHint = 'Email terverifikasi.';
        $this->emailHintClass = 'ok';
    }

    // Kirim OTP WhatsApp (demo) — PRODUKSI: gateway WA (Cloud API/vendor).
    public function sendOtp(): void
    {
        if (trim(preg_replace('/\D/', '', $this->phone)) === '') {
            $this->waHint = 'Masukkan nomor WhatsApp dulu.';
            $this->waHintClass = 'bad';

            return;
        }

        $this->showOtp = true;
        $this->waHint = 'OTP dikirim ke WhatsApp (demo: masukkan angka apa saja min. 4 digit).';
        $this->waHintClass = 'ok';
    }

    public function verifyOtp(): void
    {
        $digits = preg_replace('/\D/', '', $this->otp);

        if (mb_strlen($digits) < 4) {
            $this->waHint = 'Kode OTP belum benar.';
            $this->waHintClass = 'bad';

            return;
        }

        $this->waVerified = true;
        $this->showOtp = false;
        $this->waHint = 'WhatsApp terverifikasi.';
        $this->waHintClass = 'ok';
    }

    protected function rules(): array
    {
        $rules = [
            'ptype' => ['required', 'in:customer,supplier,internal'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:tm2sys.sec_user,email'],
            'phone' => ['required', 'string', 'max:64'],
            'pass' => ['required', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/'],
        ];

        $rules['idno'] = $this->isInternal()
            ? ['required', 'string', 'max:30']
            : ['required', 'digits:16'];

        return $rules;
    }

    public function submit(): void
    {
        $this->errorMessage = null;

        $this->validate($this->rules(), [], [
            'pass_confirmation' => 'konfirmasi password',
        ]);

        if (! $this->emailVerified || ! $this->waVerified) {
            $this->errorMessage = 'Verifikasi email dan WhatsApp dulu sebelum mengirim.';

            return;
        }

        // Resolusi keunikan login server-side (mockup: "tambah sufiks angka bila bentrok").
        $base = $this->loginPreview !== '' ? $this->loginPreview : 'user';
        $login = $base;
        $suffix = 1;

        while (SecUser::where('login', $login)->exists()) {
            $login = $base.($suffix++);
        }

        $user = SecUser::create([
            'login' => $login,
            'pswd' => Hash::make($this->pass),
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'active' => false,
            'is_active' => false,
            'reg_type' => $this->ptype,
            'id_number' => $this->idno,
        ]);

        // TIER_4 §6.2 — beri tahu admin ada pendaftaran baru yang menunggu approval.
        SecUser::where('is_admin', true)->pluck('login')->each(
            fn (string $adminLogin) => SysNotification::create([
                'user_id' => $adminLogin,
                'title' => 'Pendaftaran baru menunggu approval',
                'message' => "{$user->name} ({$user->login}) mendaftar sebagai {$user->reg_type}.",
                'type' => 'info',
                'link' => route('security.index', ['tab' => 'users']),
            ])
        );

        $this->submitted = true;
    }
};
?>

<div class="authpage hero" x-data="{ heroImg: null, init() {
        const heroes = [
            'https://upload.wikimedia.org/wikipedia/commons/e/ea/Offset_press_4_color.jpg',
            'https://upload.wikimedia.org/wikipedia/commons/e/ed/Offset_printing_press.jpg',
        ];
        this.heroImg = heroes[Math.floor(Math.random() * heroes.length)];
    } }">
    <div class="wrap">
        <div class="hero-panel">
            <img :src="heroImg" @load="$el.classList.add('on')" alt="">
            <div class="hero-veil"></div>
            <div class="hero-brand"><span class="logo-badge"></span><div><b>TELASIH MAKMUR</b><span>packaging solution</span></div></div>
            <div class="hero-quote">
                <div class="qi">&#8220;</div>
                <div class="q">{{ $this->quote['t'] }}</div>
                <div class="a">{{ $this->quote['a'] }}</div>
            </div>
        </div>

        <div class="hero-side fixed">
            <div class="flagbar">
                <button type="button" class="flag {{ $lang === 'id' ? 'active' : '' }}" wire:click="setLang('id')" title="Bahasa Indonesia">🇮🇩</button>
                <button type="button" class="flag {{ $lang === 'en' ? 'active' : '' }}" wire:click="setLang('en')" title="English">🇬🇧</button>
            </div>

            <div class="form-center">
                @if ($submitted)
                    <div class="reg-box" style="text-align:center">
                        <div class="badge-ok" style="justify-content:center;font-size:30px;margin:0 auto 18px;width:66px;height:66px;border-radius:50%;background:#e6f6ee">✓</div>
                        <h2>Pendaftaran terkirim</h2>
                        <p class="sub">Akun Anda menunggu persetujuan admin. Setelah disetujui, Anda bisa masuk memakai email dan password ini.</p>
                        <a class="back" href="{{ route('login', ['lang' => $lang]) }}" style="display:inline-flex;align-items:center;gap:7px;margin-top:16px;color:var(--ap-blue);text-decoration:none;font-size:14px;font-weight:600">← Kembali ke halaman masuk</a>
                    </div>
                @else
                    <div class="reg-box">
                        <h2>Daftar akun</h2>
                        <p class="sub">Buat akun baru untuk mengakses aplikasi.</p>
                        <div class="approval">ℹ️ Akun baru harus disetujui admin. Grup &amp; hak akses ditentukan admin saat persetujuan.</div>

                        @if ($errorMessage)
                            <div class="alert show">{{ $errorMessage }}</div>
                        @endif

                        <form wire:submit="submit">
                            <div class="field full">
                                <label>Daftar sebagai <span class="req">*</span></label>
                                <div class="input">
                                    <select wire:model.live="ptype">
                                        <option value="customer">Customer</option>
                                        <option value="supplier">Supplier</option>
                                        <option value="internal">Internal User</option>
                                    </select>
                                </div>
                            </div>

                            <div class="field full">
                                <label>Nama lengkap <span class="req">*</span></label>
                                <div class="input"><input type="text" wire:model="name" placeholder="Andi Pratama"></div>
                                @error('name') <div class="hint bad">{{ $message }}</div> @enderror
                            </div>

                            <div class="field full">
                                <label>Email <span class="req">*</span></label>
                                <div class="input {{ $emailVerified ? 'ok' : '' }}">
                                    <input type="email" wire:model.live.debounce.400ms="email" placeholder="andi@perusahaan.com">
                                    @if ($emailVerified)
                                        <span class="badge-ok">✓ OK</span>
                                    @else
                                        <button type="button" class="vbtn" wire:click="verifyEmail">Verifikasi</button>
                                    @endif
                                </div>
                                <div class="hint {{ $emailHintClass }}">{{ $emailHint ?? 'Kami cek keabsahan email lalu kirim tautan verifikasi.' }}</div>
                                @error('email') <div class="hint bad">{{ $message }}</div> @enderror
                            </div>

                            <div class="field full">
                                <label>Username (otomatis dari email)</label>
                                <div class="input"><input type="text" value="{{ $this->loginPreview }}" readonly placeholder="terisi dari email"></div>
                            </div>

                            <div class="field full">
                                <label>No. WhatsApp aktif <span class="req">*</span></label>
                                <div class="input {{ $waVerified ? 'ok' : '' }}">
                                    <input type="text" wire:model="phone" placeholder="08xxxxxxxxxx">
                                    @if ($waVerified)
                                        <span class="badge-ok">✓ OK</span>
                                    @else
                                        <button type="button" class="vbtn wa" wire:click="sendOtp">Kirim OTP</button>
                                    @endif
                                </div>
                                @if ($showOtp)
                                    <div class="otp-row">
                                        <div class="input">
                                            <input type="text" wire:model="otp" inputmode="numeric" placeholder="Kode OTP dari WhatsApp">
                                            <button type="button" class="vbtn" wire:click="verifyOtp">Verifikasi</button>
                                        </div>
                                    </div>
                                @endif
                                <div class="hint {{ $waHintClass }}">{{ $waHint ?? 'Nomor harus punya WhatsApp — kode OTP dikirim ke sana untuk memastikan aktif.' }}</div>
                                @error('phone') <div class="hint bad">{{ $message }}</div> @enderror
                            </div>

                            <div class="field full">
                                <label>{{ $this->idLabel() }} <span class="req">*</span></label>
                                <div class="input"><input type="text" wire:model="idno" inputmode="numeric" placeholder="{{ $this->isInternal() ? '—' : '16 digit' }}"></div>
                                @error('idno') <div class="hint bad">{{ $message }}</div> @enderror
                            </div>

                            <div class="grid2">
                                <div class="field">
                                    <label>Password <span class="req">*</span></label>
                                    <div class="input" x-data="{ show: false }">
                                        <input :type="show ? 'text' : 'password'" wire:model="pass" placeholder="Min. 8 karakter">
                                        <button type="button" class="vbtn" @click="show = !show" style="border-left:none">👁</button>
                                    </div>
                                    <div class="hint">Min. 8 karakter, kombinasi huruf &amp; angka.</div>
                                    @error('pass') <div class="hint bad">{{ $message }}</div> @enderror
                                </div>
                                <div class="field">
                                    <label>Konfirmasi <span class="req">*</span></label>
                                    <div class="input"><input type="password" wire:model="pass_confirmation" placeholder="Ulangi password"></div>
                                </div>
                            </div>

                            <button type="submit" class="btn" style="margin-top:6px" wire:loading.attr="disabled">Kirim pendaftaran</button>
                        </form>
                        <a class="back" href="{{ route('login', ['lang' => $lang]) }}" style="display:inline-flex;align-items:center;gap:7px;margin-top:16px;color:var(--ap-blue);text-decoration:none;font-size:14px;font-weight:600">← Kembali ke halaman masuk</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
