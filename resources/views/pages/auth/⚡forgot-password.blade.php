<?php

use App\Models\Sys\SysTranslation;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::guest')] #[Title('Lupa Password — TELASIH MAKMUR ERP')] class extends Component
{
    #[Url]
    public string $lang = 'id';

    public string $email = '';

    public bool $sent = false;

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

    // BRIEF §3.2: pesan sukses SELALU sama — tak boleh bocorkan email terdaftar
    // atau tidak. Broker Laravel sendiri sudah tidak melempar exception utk
    // email tak dikenal (mengembalikan status string), jadi cukup abaikan hasilnya.
    public function send(): void
    {
        $this->validate(['email' => ['required', 'email']]);

        Password::broker()->sendResetLink(['email' => $this->email]);

        $this->sent = true;
    }
};
?>

<div class="authpage center">
    <div class="card">
        <div class="top"><span class="logo">📦</span> TELASIH MAKMUR</div>
        <div class="body">
            <h2>{{ $this->t['fp_heading'] ?? 'Lupa password' }}</h2>
            <p class="sub">{{ $this->t['fp_sub'] ?? 'Masukkan email akun Anda. Kami kirim tautan untuk atur ulang password.' }}</p>

            @if ($sent)
                <div class="done show">{{ $this->t['fp_done'] ?? 'Jika email terdaftar, tautan reset sudah dikirim.' }}</div>
            @else
                <form wire:submit="send">
                    <label>{{ $this->t['label_email'] ?? 'Email' }}</label>
                    <input type="email" wire:model="email" placeholder="nama@telasih.co.id">
                    @error('email') <div class="hint" style="color:var(--ap-danger);margin-top:6px;font-size:12.5px">{{ $message }}</div> @enderror
                    <button type="submit" class="btn" wire:loading.attr="disabled">{{ $this->t['btn_send'] ?? 'Kirim tautan reset' }}</button>
                </form>
            @endif

            <a class="back" href="{{ route('login', ['lang' => $lang]) }}"><span>←</span> <span>{{ $this->t['back_login'] ?? 'Kembali ke halaman masuk' }}</span></a>
        </div>
    </div>
</div>
