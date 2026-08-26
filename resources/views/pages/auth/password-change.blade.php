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
new #[Layout('layouts::guest')] #[Title('Ganti Password — TELASIH MAKMUR ERP')] class extends Component
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
            $this->errorMessage = 'Konfirmasi password tidak sama.';

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
        <div class="top"><span class="logo">📦</span> TELASIH MAKMUR</div>
        <div class="body">
            <h2>Ganti password</h2>
            <p class="sub">Anda login memakai password default. Buat password baru sebelum melanjutkan.</p>

            @if ($errorMessage)
                <div class="hint bad" style="margin-bottom:14px">{{ $errorMessage }}</div>
            @endif

            <form wire:submit="submit">
                <label>Password baru</label>
                <input type="password" wire:model="pswdNew" autocomplete="new-password" placeholder="Minimal 8 karakter">
                @error('pswdNew') <div class="hint bad">{{ $message }}</div> @enderror

                <div style="margin-top:16px">
                    <label>Konfirmasi password baru</label>
                    <input type="password" wire:model="pswdConfirm" autocomplete="new-password" placeholder="Ulangi password baru">
                    @error('pswdConfirm') <div class="hint bad">{{ $message }}</div> @enderror
                </div>

                <button type="submit" class="btn" wire:loading.attr="disabled">Simpan password baru</button>
            </form>

            <button type="button" class="back" style="background:none;border:none;cursor:pointer" wire:click="logout"><span>&larr;</span> <span>Keluar dan masuk lagi nanti</span></button>
        </div>
    </div>
</div>
