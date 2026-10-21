{{--
 REMARK
 Tujuan       : Setup keamanan akun (4 data sekali isi: tanggal lahir, nama ibu, nomor HP, pertanyaan + jawaban) dalam kotak x-mp::auth.box; tanggal lahir dan HP terisi dari data karyawan bila tertaut.
 Sumber hulu  : App\Http\Controllers\Auth\SecuritySetupController, SecurityRecoveryService.
 Input        : images, quotes, questions, birthDate, phone, birthLocked, phoneLocked; galat validasi dari server.
 Output       : Form POST ke security.store.
 Dipakai oleh : Route security.setup (routes/web.php, middleware auth, locale).
 Status       : Aktif (T10 tahap 6a, 2026-10-21). Menggantikan halaman v1 pages::auth.security-setup.
--}}
@extends('auth.layout', ['title' => 'Account security setup', 'box' => 'center'])
@section('box')
    <x-mp::auth.box title="Account security setup" sub="Fill in these 4 items once. They verify your identity if you forget your password (no email)." :action="route('security.store')" :error="$errors->first()" :back-url="route('logout')" back-label="Sign out and finish later" :lang="false">
        <x-dl::field.date name="birth_date" label="Date of birth" :value="old('birth_date', $birthDate)" required
            :hint="$birthLocked ? 'Taken from your employee record. Wrong? Change it here: HR reviews the change before it takes effect.' : null" />
        <x-dl::field.text name="mother_name" label="Mother's maiden name" icon="fa-person-dress" placeholder="Your mother's full name" :value="old('mother_name')" required />
        <x-dl::field.phone name="phone" label="Registered phone" kind="mobile" :value="old('phone', $phone)" required
            :hint="$phoneLocked ? 'Taken from your employee record. Wrong? Change it here: HR reviews the change before it takes effect.' : null" />
        <x-dl::field.select name="question_id" label="Security question" placeholder="— choose a question —" :options="$questions->pluck('question_text', 'id_question')->all()" :value="old('question_id')" required />
        <x-dl::field.text name="answer" label="Your answer" icon="fa-shield-halved" :value="old('answer')" required />
        <x-dl::button type="submit" size="lg" block label="Save & continue" />
    </x-mp::auth.box>
@endsection
