<?php

use App\Models\Sys\SecUser;
use App\Services\Security\SecurityRecoveryService;
use App\Support\Localization\Dictionary;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

// Lupa Password berbasis 4 pertanyaan (keputusan user 2026-08-27) --
// MENGGANTIKAN alur link-email lama (Password::broker), bukan tambahan.
// Alasan: email/WA gateway belum tentu jalan di server ini (BRIEF
// security_defaultpassword_spec.md §0), jadi self-service tak boleh
// bergantung email. Berhasil verifikasi 4-4nya -> password KEMBALI ke
// default_password yang sudah ada (diberikan Admin di awal, BUKAN
// generate baru) via SecurityRecoveryService::resetToDefaultPassword().
new #[Layout('layouts::guest')] #[Title('Lupa Password — TELASIH MAKMUR ERP')] class extends Component
{
    #[Url]
    public string $lang = 'id';

    public int $step = 1; // 1=cari akun, 2=jawab 4 pertanyaan, 3=sukses

    public string $loginField = '';

    public ?int $targetUserId = null;

    public string $questionLabel = '';

    public string $birthDate = '';

    public string $motherName = '';

    public string $phone = '';

    public string $answer = '';

    public ?string $errorMessage = null;

    public function mount(): void
    {
        if (! in_array($this->lang, ['id', 'en'], true)) {
            $this->lang = 'id';
        }
    }

    // Teks English -> bahasa pilihan ?lang di halaman ini (tamu, belum ada preferensi user) lewat kamus sys_dictionary.
    public function tr(string $en): string
    {
        return Dictionary::t($en, 'ui', $this->lang);
    }

    #[Computed]
    public function targetUser(): ?SecUser
    {
        return $this->targetUserId ? SecUser::find($this->targetUserId) : null;
    }

    protected function throttleKey(): string
    {
        return 'password-recovery:'.strtolower(trim($this->loginField)).'|'.request()->ip();
    }

    public function findAccount(SecurityRecoveryService $service): void
    {
        $this->errorMessage = null;

        $this->validate(['loginField' => ['required', 'string']]);

        if (RateLimiter::tooManyAttempts($this->throttleKey(), 8)) {
            $this->errorMessage = $this->tr('Too many attempts. Try again later or contact the Administrator.');

            return;
        }

        $user = SecUser::where('login', trim($this->loginField))->orWhere('email', trim($this->loginField))->first();

        // Pesan generik sengaja SAMA utk "tak ada akun" & "belum setup" --
        // jangan bocorkan mana yang benar (cegah enumerasi akun).
        if (! $user || ! $service->isSetupComplete($user)) {
            RateLimiter::hit($this->throttleKey(), 900);
            $this->errorMessage = $this->tr('Account not found or security questions not set up yet. Contact the Administrator.');

            return;
        }

        $this->targetUserId = $user->id_user;
        $this->questionLabel = $user->securityQuestion?->question_text ?? '';
        $this->step = 2;
    }

    public function verifyAnswers(SecurityRecoveryService $service): void
    {
        $this->errorMessage = null;

        $data = $this->validate([
            'birthDate' => ['required', 'date'],
            'motherName' => ['required', 'string'],
            'phone' => ['required', 'string'],
            'answer' => ['required', 'string'],
        ]);

        if (RateLimiter::tooManyAttempts($this->throttleKey(), 8)) {
            $this->errorMessage = $this->tr('Too many attempts. Try again later or contact the Administrator.');

            return;
        }

        $user = $this->targetUser;

        if (! $user) {
            $this->step = 1;

            return;
        }

        $ok = $service->verify($user, $data['birthDate'], $data['motherName'], $data['phone'], $data['answer']);

        if (! $ok) {
            RateLimiter::hit($this->throttleKey(), 900);
            $this->errorMessage = $this->tr('One of the answers does not match. Please check again.');

            return;
        }

        if (! $service->resetToDefaultPassword($user)) {
            $this->errorMessage = $this->tr('This account has no stored default password yet. Contact the Administrator.');

            return;
        }

        RateLimiter::clear($this->throttleKey());
        $this->step = 3;
    }

    public function backToStart(): void
    {
        $this->reset(['step', 'targetUserId', 'questionLabel', 'birthDate', 'motherName', 'phone', 'answer', 'errorMessage']);
    }
};
?>

<div class="authpage center">
    <div class="card">
        <x-auth.top-brand />
        <div class="body">
            <h2>{{ $this->tr('Forgot password') }}</h2>

            @if ($step === 1)
                <p class="sub">{{ $this->tr('Enter the username or email of your account.') }}</p>

                @if ($errorMessage)
                    <div class="hint bad" style="margin-bottom:14px">{{ $errorMessage }}</div>
                @endif

                <form wire:submit="findAccount">
                    <x-form.input legacy="field" :label="$this->tr('Username or email')" wire:model="loginField" autofocus name="loginField" />

                    <button type="submit" class="btn" wire:loading.attr="disabled" style="margin-top:16px">{{ $this->tr('Continue') }}</button>
                </form>
            @elseif ($step === 2)
                <p class="sub">{{ $this->tr('Answer these 4 questions to verify your identity.') }}</p>

                @if ($errorMessage)
                    <div class="hint bad" style="margin-bottom:14px">{{ $errorMessage }}</div>
                @endif

                <form wire:submit="verifyAnswers">
                    <x-form.date-picker legacy="field" :label="$this->tr('Date of birth')" wire:model="birthDate" name="birthDate" />
                    <x-form.input legacy="field" :label="$this->tr('Mother\'s maiden name')" wire:model="motherName" :placeholder="$this->tr('Your mother\'s full maiden name')" name="motherName" />
                    <x-form.input legacy="field" :label="$this->tr('Registered phone number')" wire:model="phone" placeholder="08xxxxxxxxxx" name="phone" />
                    <x-form.input legacy="field" label="{{ $questionLabel }}" wire:model="answer" :placeholder="$this->tr('Your answer')" name="answer" />

                    <button type="submit" class="btn" wire:loading.attr="disabled" style="margin-top:20px">{{ $this->tr('Verify') }}</button>
                </form>

                <button type="button" class="back" style="background:none;border:none;cursor:pointer" wire:click="backToStart"><span>&larr;</span> <span>{{ $this->tr('Back') }}</span></button>
            @else
                <div class="done show">{{ $this->tr('Your password has been reset to the') }} <b>{{ $this->tr('default password') }}</b> {{ $this->tr('— the password the Administrator gave when your account was first created. Please sign in with it; you will be asked to create a new password after signing in.') }}</div>
            @endif

            <a class="back" href="{{ route('login', ['lang' => $lang]) }}"><span>←</span> <span>{{ $this->tr('Back to the sign-in page') }}</span></a>
        </div>
    </div>
</div>
