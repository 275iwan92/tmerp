<?php

use App\Models\Sys\SecUser;
use App\Services\Security\PasswordResetService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

// security_defaultpassword_spec.md §6 — ditampilkan paksa (middleware
// force.password) ketika must_change_password=1: habis reset admin, atau
// user baru. default_password TIDAK diubah oleh alur ini (tetap fallback).
new #[Layout('layouts::guest')] #[Title('Change Password — TELASIH MAKMUR ERP')] class extends Component
{
    public string $pswdNew = '';

    public string $pswdConfirm = '';

    public ?string $errorMessage = null;

    public function submit(PasswordResetService $service): mixed
    {
        $this->errorMessage = null;

        $this->validate([
            'pswdNew' => ['required', 'string', 'min:8'],
            'pswdConfirm' => ['required', 'string'],
        ]);

        if ($this->pswdNew !== $this->pswdConfirm) {
            $this->errorMessage = t('Password confirmation does not match.');

            return null;
        }

        /** @var SecUser $user */
        $user = Auth::user();

        try {
            $service->changeByUser($user, $this->pswdNew);
        } catch (\InvalidArgumentException $e) {
            $this->errorMessage = $e->getMessage();

            return null;
        }

        return redirect()->intended(route('menu'));
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

<div class="authpage center">
    <div class="card">
        <x-auth.top-brand />
        <div class="body">
            <h2>{{ t('Change your password') }}</h2>
            <p class="sub">{{ t('You signed in with the default password. Create a new password before continuing.') }}</p>

            @if ($errorMessage)
                <div class="hint bad" style="margin-bottom:14px">{{ $errorMessage }}</div>
            @endif

            <form wire:submit="submit">
                <x-form.input legacy="field" :label="t('New password')" type="password" wire:model="pswdNew" autocomplete="new-password" :placeholder="t('Minimum 8 characters')" name="pswdNew" />
                <x-form.input legacy="field" :label="t('Confirm new password')" type="password" wire:model="pswdConfirm" autocomplete="new-password" :placeholder="t('Repeat new password')" name="pswdConfirm" />

                <button type="submit" class="btn" wire:loading.attr="disabled">{{ t('Save new password') }}</button>
            </form>

            <button type="button" class="back" style="background:none;border:none;cursor:pointer" wire:click="logout"><span>&larr;</span> <span>{{ t('Sign out and sign in again later') }}</span></button>
        </div>
    </div>
</div>
