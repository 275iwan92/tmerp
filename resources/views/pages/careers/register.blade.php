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
new #[Layout('layouts::portal')] #[Title('Daftar Akun')] class extends Component
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
            'national_id.digits' => 'No. KTP harus 16 digit angka.',
            'email.regex' => 'Email harus punya domain lengkap, mis. nama@contoh.com.',
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
            $fail('Kode wilayah pada No. KTP (4 digit awal) tidak dikenali.');

            return;
        }

        $day = (int) substr($value, 6, 2);
        $month = (int) substr($value, 8, 2);

        if ($day > 40) {
            $day -= 40; // penanda jenis kelamin perempuan
        }

        if ($month < 1 || $month > 12 || ! checkdate($month, $day, 2000)) {
            $fail('Tanggal lahir pada No. KTP tidak valid.');
        }
    }
};
?>

<div class="max-w-[440px] mx-auto px-5 py-12">
    @if ($registered)
        <div class="bg-white border border-[#e6e9ef] rounded-xl p-6 text-center">
            <p class="text-3xl mb-2">📧</p>
            <h1 class="text-[16px] font-bold text-navy mb-2">Cek Email Anda</h1>
            <p class="text-[13px] text-muted mb-4">Kami sudah kirim tautan verifikasi ke <b>{{ $email }}</b>. Verifikasi dulu sebelum mengirim lamaran.</p>
            <a href="{{ route('careers.landing') }}" class="inline-block bg-navy text-white text-[13px] font-bold px-4 py-2 rounded-md">Lihat Lowongan</a>
        </div>
    @else
        <div class="bg-white border border-[#e6e9ef] rounded-xl p-6">
            <h1 class="text-[18px] font-bold text-navy mb-1">Buat Akun</h1>
            <p class="text-[12.5px] text-muted mb-5">Satu akun untuk melamar semua posisi &amp; melacak status lamaran Anda.</p>

            <form wire:submit="register" class="space-y-3.5">
                <div>
                    <label class="block text-[11.5px] font-bold text-[#495166] mb-1">No. KTP</label>
                    <input type="text" wire:model="national_id" maxlength="16" inputmode="numeric" placeholder="16 digit sesuai KTP" class="w-full border border-[#d7dbe2] rounded-md px-3 py-2 text-[13px]">
                    @error('national_id') <p class="text-[11px] text-[#a3241f] mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-[11.5px] font-bold text-[#495166] mb-1">Nama Lengkap (sesuai KTP)</label>
                    <input type="text" wire:model="name" class="w-full border border-[#d7dbe2] rounded-md px-3 py-2 text-[13px]">
                    @error('name') <p class="text-[11px] text-[#a3241f] mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-[11.5px] font-bold text-[#495166] mb-1">Email</label>
                    <input type="email" wire:model="email" class="w-full border border-[#d7dbe2] rounded-md px-3 py-2 text-[13px]">
                    @error('email') <p class="text-[11px] text-[#a3241f] mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-[11.5px] font-bold text-[#495166] mb-1">No. HP / Smartphone</label>
                    <input type="text" wire:model="phone" placeholder="08xxxxxxxxxx" class="w-full border border-[#d7dbe2] rounded-md px-3 py-2 text-[13px]">
                    @error('phone') <p class="text-[11px] text-[#a3241f] mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-[11.5px] font-bold text-[#495166] mb-1">Password</label>
                    <input type="password" wire:model="password" class="w-full border border-[#d7dbe2] rounded-md px-3 py-2 text-[13px]">
                    @error('password') <p class="text-[11px] text-[#a3241f] mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-[11.5px] font-bold text-[#495166] mb-1">Ulangi Password</label>
                    <input type="password" wire:model="password_confirmation" class="w-full border border-[#d7dbe2] rounded-md px-3 py-2 text-[13px]">
                </div>
                <button type="submit" class="w-full bg-navy text-white text-[13px] font-bold py-2.5 rounded-md hover:opacity-90">Daftar</button>
            </form>

            <p class="text-[12px] text-muted text-center mt-4">Sudah punya akun? <a href="{{ route('careers.login') }}" class="text-accent font-bold">Masuk</a></p>
        </div>
    @endif
</div>
