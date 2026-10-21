{{--
 REMARK
 Tujuan       : Ganti password PAKSA (must_change_password = 1): dua isian password dalam kotak x-mp::auth.box; hanya bisa keluar lewat logout.
 Sumber hulu  : App\Http\Controllers\Auth\PasswordChangeController, PasswordResetService::changeByUser.
 Input        : images, quotes; galat password dari server.
 Output       : Form POST ke password.update.
 Dipakai oleh : Route password.change (routes/web.php, middleware auth, locale).
 Status       : Aktif (T10 tahap 6a, 2026-10-21). Menggantikan halaman v1 pages::auth.password-change.
--}}
@extends('auth.layout', ['title' => 'Change password', 'box' => 'center'])
@section('box')
    <x-mp::auth.box title="Change password" sub="You must set a new password before continuing." :action="route('password.update')" :error="$errors->first()" :back-url="route('logout')" back-label="Sign out" :lang="false">
        <x-dl::field.password name="password" label="New password" icon="fa-lock" autocomplete="new-password" required hint="Min. 8 characters, a mix of letters & numbers." />
        <x-dl::field.password name="password_confirmation" label="Confirm" icon="fa-lock" autocomplete="new-password" required />
        <x-dl::button type="submit" size="lg" block label="Save" />
    </x-mp::auth.box>
@endsection
