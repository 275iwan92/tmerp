<?php

use App\Exceptions\Portal\CandidateAlreadyExistsException;
use App\Mail\Portal\CandidateEmailVerification;
use App\Models\Master\GeoCity;
use App\Services\Portal\CandidateAuthService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

// P1 -- Registrasi (recruitment_tier4.md §2), versi web pro. Field sesuai
// instruksi user 2026-08-30: No. KTP, Nama (sesuai KTP), Email, No. HP,
// Password. Akun tersimpan di Firestore (App\Services\Portal\CandidateAuthService).
new #[Layout('layouts::portal')] #[Title('Chart of Accounts')] class extends Component
{
    public string $national_id = '';

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $registered = false;

    public function register(CandidateAuthService $auth): void
    {
        $this->validate([
            'national_id' => ['required', 'digits:16', function (string $attribute, mixed $value, \Closure $fail) {
                $this->validateNikStructure($value, $fail);
            }],
            'name' => ['required', 'min:3'],
            'email' => ['required', 'email', 'regex:/^[^@\s]+@[^@\s]+\.[^@\s]{2,}$/'],
            'phone' => ['required', 'min:9', 'max:15'],
            'password' => ['required', 'min:8', 'confirmed'],
        ], [
            'national_id.digits' => t('ID Card No. must be 16 digits.'),
            'email.regex' => t('Email must have a full domain, e.g. name@example.com.'),
        ]);

        try {
            $candidate = $auth->register([
                'national_id' => $this->national_id,
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'password' => $this->password,
            ]);
        } catch (CandidateAlreadyExistsException $e) {
            $this->addError('email', $e->getMessage());

            return;
        }

        $verifyUrl = URL::temporarySignedRoute('careers.verify-email', now()->addMinutes(60), ['uid' => $candidate['_docId']]);

        Mail::to($this->email)->send(new CandidateEmailVerification($this->name, $verifyUrl));

        // Auto-login langsung (bisa browse/lamar), tapi verifikasi email tetap
        // WAJIB sebelum submitApplication -- dicek di halaman apply.
        session(['portal_candidate_uid' => $candidate['_docId']]);
        session()->regenerate();

        $this->registered = true;
    }

    // NIK tidak punya check-digit/checksum, tapi punya struktur baku (Dukcapil):
    // 4 digit awal = kode wilayah provinsi+kab/kota, 6 digit berikut = tanggal
    // lahir DDMMYY (perempuan: DD+40). Ini menyaring input asal-asalan (mis.
    // "9999999999999999") tanpa perlu database NIK asli.
    private function validateNikStructure(string $value, \Closure $fail): void
    {
        $regionCode = substr($value, 0, 4);

        if (! GeoCity::where('city_code', 'bps:'.$regionCode)->exists()) {
            $fail(t('The region code in the ID Card No. (first 4 digits) is not recognized.'));

            return;
        }

        $day = (int) substr($value, 6, 2);
        $month = (int) substr($value, 8, 2);

        if ($day > 40) {
            $day -= 40; // penanda jenis kelamin perempuan
        }

        if ($month < 1 || $month > 12 || ! checkdate($month, $day, 2000)) {
            $fail(t('The birth date in the ID Card No. is not valid.'));
        }
    }
};
?>

<div class="max-w-[440px] mx-auto px-5 py-12">
    @if ($registered)
        <div class="bg-white border border-[#e6e9ef] rounded-xl p-6 text-center">
            <p class="text-3xl mb-2">📧</p>
            <h1 class="text-[16px] font-bold text-navy mb-2">{{ t('Check Your Email') }}</h1>
            <p class="text-[13px] text-muted mb-4">{{ t('We have sent a verification link to') }} <b>{{ $email }}</b>. {{ t('Verify it before submitting an application.') }}</p>
            <a href="{{ route('careers.landing') }}" class="inline-block bg-navy text-white text-[13px] font-bold px-4 py-2 rounded-md">{{ t('View Vacancies') }}</a>
        </div>
    @else
        <div class="bg-white border border-[#e6e9ef] rounded-xl p-6">
            <h1 class="text-[18px] font-bold text-navy mb-1">{{ t('Create Account') }}</h1>
            <p class="text-[12.5px] text-muted mb-5">{{ t('One account to apply for every position & track the status of your applications.') }}</p>

            <form wire:submit="register" class="space-y-3.5">
                <div>
                    <label class="block text-[11.5px] font-bold text-[#495166] mb-1">{{ t('ID Card No.') }}</label>
                    <input type="text" wire:model="national_id" maxlength="16" inputmode="numeric" placeholder="{{ t('16 digits as on the ID card') }}" class="w-full border border-[#d7dbe2] rounded-md px-3 py-2 text-[13px]">
                    @error('national_id') <p class="text-[11px] text-[#a3241f] mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-[11.5px] font-bold text-[#495166] mb-1">{{ t('Full Name (as on ID card)') }}</label>
                    <input type="text" wire:model="name" class="w-full border border-[#d7dbe2] rounded-md px-3 py-2 text-[13px]">
                    @error('name') <p class="text-[11px] text-[#a3241f] mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-[11.5px] font-bold text-[#495166] mb-1">{{ t('Email') }}</label>
                    <input type="email" wire:model="email" class="w-full border border-[#d7dbe2] rounded-md px-3 py-2 text-[13px]">
                    @error('email') <p class="text-[11px] text-[#a3241f] mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-[11.5px] font-bold text-[#495166] mb-1">{{ t('Mobile No. / Smartphone') }}</label>
                    <input type="text" wire:model="phone" placeholder="08xxxxxxxxxx" class="w-full border border-[#d7dbe2] rounded-md px-3 py-2 text-[13px]">
                    @error('phone') <p class="text-[11px] text-[#a3241f] mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-[11.5px] font-bold text-[#495166] mb-1">{{ t('Password') }}</label>
                    <input type="password" wire:model="password" class="w-full border border-[#d7dbe2] rounded-md px-3 py-2 text-[13px]">
                    @error('password') <p class="text-[11px] text-[#a3241f] mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-[11.5px] font-bold text-[#495166] mb-1">{{ t('Repeat Password') }}</label>
                    <input type="password" wire:model="password_confirmation" class="w-full border border-[#d7dbe2] rounded-md px-3 py-2 text-[13px]">
                </div>
                <button type="submit" class="w-full bg-navy text-white text-[13px] font-bold py-2.5 rounded-md hover:opacity-90">{{ t('Register') }}</button>
            </form>

            <p class="text-[12px] text-muted text-center mt-4">{{ t('Already have an account?') }} <a href="{{ route('careers.login') }}" class="text-accent font-bold">{{ t('Sign In') }}</a></p>
        </div>
    @endif
</div>
