<?php

use App\Models\Sys\SecUser;
use App\Models\Sys\SysNotification;
use App\Models\Sys\SysQuote;
use App\Support\Localization\Dictionary;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::guest')] #[Title('Chart of Accounts — TELASIH MAKMUR ERP')] class extends Component
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
    public function quote(): array
    {
        $q = SysQuote::random();

        return ['t' => $q?->quote_text ?? $this->tr('Have a good day at work.'), 'a' => $q?->author ?? 'Telasih Makmur'];
    }

    // Teks English -> bahasa pilihan bendera di halaman ini (tamu, belum ada preferensi user) lewat kamus sys_dictionary.
    public function tr(string $en): string
    {
        return Dictionary::t($en, 'ui', $this->lang);
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
        return $this->isInternal() ? $this->tr('NRP (Employee ID No.)') : $this->tr('NIK (ID Card No.)');
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
            $this->emailHint = $this->tr('Invalid email format.');
            $this->emailHintClass = 'bad';

            return;
        }

        $this->emailVerified = true;
        $this->emailHint = $this->tr('Email verified.');
        $this->emailHintClass = 'ok';
    }

    // Kirim OTP WhatsApp (demo) — PRODUKSI: gateway WA (Cloud API/vendor).
    public function sendOtp(): void
    {
        if (trim(preg_replace('/\D/', '', $this->phone)) === '') {
            $this->waHint = $this->tr('Enter a WhatsApp number first.');
            $this->waHintClass = 'bad';

            return;
        }

        $this->showOtp = true;
        $this->waHint = $this->tr('OTP sent to WhatsApp (demo: enter any number, min. 4 digits).');
        $this->waHintClass = 'ok';
    }

    public function verifyOtp(): void
    {
        $digits = preg_replace('/\D/', '', $this->otp);

        if (mb_strlen($digits) < 4) {
            $this->waHint = $this->tr('The OTP code is not correct yet.');
            $this->waHintClass = 'bad';

            return;
        }

        $this->waVerified = true;
        $this->showOtp = false;
        $this->waHint = $this->tr('WhatsApp verified.');
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
            'pass_confirmation' => $this->tr('password confirmation'),
        ]);

        if (! $this->emailVerified || ! $this->waVerified) {
            $this->errorMessage = $this->tr('Verify email and WhatsApp before submitting.');

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
            'activate' => false,
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
            <x-auth.hero-brand />
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
                        <h2>{{ $this->tr('Registration submitted') }}</h2>
                        <p class="sub">{{ $this->tr('Your account is waiting for admin approval. Once approved, you can sign in with this email and password.') }}</p>
                        <a class="back" href="{{ route('login', ['lang' => $lang]) }}" style="display:inline-flex;align-items:center;gap:7px;margin-top:16px;color:var(--ap-blue);text-decoration:none;font-size:14px;font-weight:600">← {{ $this->tr('Back to the sign-in page') }}</a>
                    </div>
                @else
                    <div class="reg-box">
                        <h2>{{ $this->tr('Register account') }}</h2>
                        <p class="sub">{{ $this->tr('Create a new account to access the application.') }}</p>
                        <div class="approval">ℹ️ {{ $this->tr('New accounts must be approved by an admin. Group & access rights are set by the admin at approval.') }}</div>

                        @if ($errorMessage)
                            <div class="alert show">{{ $errorMessage }}</div>
                        @endif

                        <form wire:submit="submit">
                            <div class="field full">
                                <label>{{ $this->tr('Register as') }} <span class="req">*</span></label>
                                <div class="input">
                                    <select wire:model.live="ptype">
                                        <option value="customer">{{ $this->tr('Customer') }}</option>
                                        <option value="supplier">{{ $this->tr('Supplier') }}</option>
                                        <option value="internal">{{ $this->tr('Internal User') }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="field full">
                                <label>{{ $this->tr('Full name') }} <span class="req">*</span></label>
                                <div class="input"><input type="text" wire:model="name" placeholder="Andi Pratama"></div>
                                @error('name') <div class="hint bad">{{ $message }}</div> @enderror
                            </div>

                            <div class="field full">
                                <label>{{ $this->tr('Email') }} <span class="req">*</span></label>
                                <div class="input {{ $emailVerified ? 'ok' : '' }}">
                                    <input type="email" wire:model.live.debounce.400ms="email" placeholder="andi@perusahaan.com">
                                    @if ($emailVerified)
                                        <span class="badge-ok">✓ OK</span>
                                    @else
                                        <button type="button" class="vbtn" wire:click="verifyEmail">{{ $this->tr('Verify') }}</button>
                                    @endif
                                </div>
                                <div class="hint {{ $emailHintClass }}">{{ $emailHint ?? $this->tr('We check the email is valid, then send a verification link.') }}</div>
                                @error('email') <div class="hint bad">{{ $message }}</div> @enderror
                            </div>

                            <div class="field full">
                                <label>{{ $this->tr('Username (automatic from email)') }}</label>
                                <div class="input"><input type="text" value="{{ $this->loginPreview }}" readonly placeholder="{{ $this->tr('filled from email') }}"></div>
                            </div>

                            <div class="field full">
                                <label>{{ $this->tr('Active WhatsApp No.') }} <span class="req">*</span></label>
                                <div class="input {{ $waVerified ? 'ok' : '' }}">
                                    <input type="text" wire:model="phone" placeholder="08xxxxxxxxxx">
                                    @if ($waVerified)
                                        <span class="badge-ok">✓ OK</span>
                                    @else
                                        <button type="button" class="vbtn wa" wire:click="sendOtp">{{ $this->tr('Send OTP') }}</button>
                                    @endif
                                </div>
                                @if ($showOtp)
                                    <div class="otp-row">
                                        <div class="input">
                                            <input type="text" wire:model="otp" inputmode="numeric" placeholder="{{ $this->tr('OTP code from WhatsApp') }}">
                                            <button type="button" class="vbtn" wire:click="verifyOtp">{{ $this->tr('Verify') }}</button>
                                        </div>
                                    </div>
                                @endif
                                <div class="hint {{ $waHintClass }}">{{ $waHint ?? $this->tr('The number must have WhatsApp — the OTP code is sent there to make sure it is active.') }}</div>
                                @error('phone') <div class="hint bad">{{ $message }}</div> @enderror
                            </div>

                            <div class="field full">
                                <label>{{ $this->idLabel() }} <span class="req">*</span></label>
                                <div class="input"><input type="text" wire:model="idno" inputmode="numeric" placeholder="{{ $this->isInternal() ? '—' : $this->tr('16 digits') }}"></div>
                                @error('idno') <div class="hint bad">{{ $message }}</div> @enderror
                            </div>

                            <div class="grid2">
                                <div class="field">
                                    <label>{{ $this->tr('Password') }} <span class="req">*</span></label>
                                    <div class="input" x-data="{ show: false }">
                                        <input :type="show ? 'text' : 'password'" wire:model="pass" placeholder="{{ $this->tr('Min. 8 characters') }}">
                                        <button type="button" class="vbtn" @click="show = !show" style="border-left:none">👁</button>
                                    </div>
                                    <div class="hint">{{ $this->tr('Min. 8 characters, a mix of letters & numbers.') }}</div>
                                    @error('pass') <div class="hint bad">{{ $message }}</div> @enderror
                                </div>
                                <div class="field">
                                    <label>{{ $this->tr('Confirmation') }} <span class="req">*</span></label>
                                    <div class="input"><input type="password" wire:model="pass_confirmation" placeholder="{{ $this->tr('Repeat password') }}"></div>
                                </div>
                            </div>

                            <button type="submit" class="btn" style="margin-top:6px" wire:loading.attr="disabled">{{ $this->tr('Submit registration') }}</button>
                        </form>
                        <a class="back" href="{{ route('login', ['lang' => $lang]) }}" style="display:inline-flex;align-items:center;gap:7px;margin-top:16px;color:var(--ap-blue);text-decoration:none;font-size:14px;font-weight:600">← {{ $this->tr('Back to the sign-in page') }}</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
