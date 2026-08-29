<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Validation\ValidationException;

// SecUser (tm2sys.sec_user) login pakai kolom `login`+`pswd`, BUKAN
// email/password bawaan Filament (lihat SecUser::getAuthPassword()). Kalau
// class Login bawaan Filament dipakai apa adanya, attempt(['email'=>..])
// akan SELALU gagal krn kolom `email` bukan identifier akun -- override 2
// titik ini (form field + credentials array) supaya cocok dgn skema lama,
// TANPA mengubah SecUser/guard 'web' yang sudah dipakai /login custom.
class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('login')
            ->label('Username')
            ->required()
            ->autocomplete()
            ->autofocus();
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            'login' => $data['login'],
            'password' => $data['password'],
        ];
    }

    // Base class men-attach pesan gagal ke 'data.email' -- field itu tak ada
    // lagi di form ini (sudah jadi 'data.login'), jadi errornya akan
    // "hilang" diam-diam kalau tidak dioverride juga.
    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.login' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }
}
