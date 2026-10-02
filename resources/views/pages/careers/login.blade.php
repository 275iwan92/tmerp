<?php

use App\Services\Portal\CandidateAuthService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::portal')] #[Title('Sign In')] class extends Component
{
    public string $email = '';

    public string $password = '';

    public function login(CandidateAuthService $auth): void
    {
        $this->validate(['email' => ['required', 'email'], 'password' => ['required']]);

        $candidate = $auth->attempt($this->email, $this->password);

        if (! $candidate) {
            $this->addError('email', t('Wrong email or password.'));

            return;
        }

        session(['portal_candidate_uid' => $candidate['_docId']]);
        session()->regenerate();

        $this->redirect(session('intended_url') ?? route('careers.dashboard'), navigate: false);
    }
};
?>

<div class="max-w-[400px] mx-auto px-5 py-14">
    <div class="bg-white border border-[#e6e9ef] rounded-xl p-6">
        <h1 class="text-[18px] font-bold text-navy mb-1">{{ t('Sign In') }}</h1>
        <p class="text-[12.5px] text-muted mb-5">{{ t('Sign in to apply for a position or see the status of your application.') }}</p>

        @if (session('verified'))
            <div class="bg-[#eaf7ee] text-[#1f8a4c] text-[12px] rounded-md px-3 py-2 mb-3">✔ {{ t('Email verified, please sign in.') }}</div>
        @endif

        <form wire:submit="login" class="space-y-3.5">
            <div>
                <label class="block text-[11.5px] font-bold text-[#495166] mb-1">{{ t('Email') }}</label>
                <input type="email" wire:model="email" class="w-full border border-[#d7dbe2] rounded-md px-3 py-2 text-[13px]">
                @error('email') <p class="text-[11px] text-[#a3241f] mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-[11.5px] font-bold text-[#495166] mb-1">{{ t('Password') }}</label>
                <input type="password" wire:model="password" class="w-full border border-[#d7dbe2] rounded-md px-3 py-2 text-[13px]">
                @error('password') <p class="text-[11px] text-[#a3241f] mt-1">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="w-full bg-navy text-white text-[13px] font-bold py-2.5 rounded-md hover:opacity-90">{{ t('Sign In') }}</button>
        </form>

        <p class="text-[12px] text-muted text-center mt-4">{{ t('No account yet?') }} <a href="{{ route('careers.register') }}" class="text-accent font-bold">{{ t('Register') }}</a></p>
    </div>
</div>
