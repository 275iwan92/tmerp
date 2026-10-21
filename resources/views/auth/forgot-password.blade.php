{{--
 REMARK
 Tujuan       : Lupa password 3 langkah (cari akun, jawab 4 pertanyaan, sukses) dalam kotak x-mp::auth.box; jawaban benar semua mengembalikan password ke default akun.
 Sumber hulu  : App\Http\Controllers\Auth\ForgotPasswordController (langkah dari sesi), SecurityRecoveryService.
 Input        : images, quotes, step (1 | 2 | 3), question (pertanyaan akun); galat recovery dari server.
 Output       : Form POST ke password.find / password.verify / password.restart.
 Dipakai oleh : Route password.request (routes/web.php, middleware guest).
 Status       : Aktif (T10 tahap 6a, 2026-10-21). Menggantikan halaman v1 pages::auth.forgot-password.
--}}
@extends('auth.layout', ['title' => 'Forgot password', 'box' => 'center'])
@section('box')
    @if ($step === 1)
        <x-mp::auth.box title="Forgot password" sub="Answer 4 questions matching your account. No email needed." :action="route('password.find')" :error="$errors->first('recovery') ?: $errors->first('login')" :back-url="route('login')">
            <x-dl::field.text name="login" label="Username or email" icon="fa-user" :case-toggle="false" autocomplete="username" required />
            <x-dl::button type="submit" size="lg" block label="Next" />
        </x-mp::auth.box>
    @elseif ($step === 2)
        <x-mp::auth.box title="Forgot password" sub="Answer the 4 questions of your account." :action="route('password.verify')" :error="$errors->first('recovery') ?: $errors->first()">
            <x-dl::field.date name="birth_date" label="Date of birth" :value="old('birth_date')" required />
            <x-dl::field.text name="mother_name" label="Mother's maiden name" icon="fa-person-dress" :value="old('mother_name')" required />
            <x-dl::field.phone name="phone" label="Registered phone" kind="mobile" :value="old('phone')" required />
            <x-dl::field.text name="security_answer" :label="raw($question)" icon="fa-shield-halved" required />
            <x-dl::button type="submit" size="lg" block label="Reset password" />
            <x-dl::button type="submit" variant="link" icon="back" label="Back" :formaction="route('password.restart')" formnovalidate />
        </x-mp::auth.box>
    @else
        <x-mp::auth.box title="Password reset" sub="Your password is back to the default password given by the Administrator. Sign in with it, then change it." :back-url="route('login')" back-label="Back to sign in" />
    @endif
@endsection
