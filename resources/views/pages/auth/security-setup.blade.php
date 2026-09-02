<?php

use App\Models\Sys\SecUser;
use App\Services\Security\SecurityRecoveryService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

// Setup 4 pertanyaan keamanan (keputusan user 2026-08-27) -- dipaksa lewat
// ForcePasswordChange middleware begitu security_setup_at masih NULL,
// terlepas dari status must_change_password (biar 146 akun lama yang sudah
// pernah ganti password tetap ke-cover). Tanggal lahir otomatis dari
// emp_employee kalau akun ini tertaut karyawan (SecurityRecoveryService).
new #[Layout('layouts::guest')] #[Title('Setup Keamanan Akun — TELASIH MAKMUR ERP')] class extends Component
{
    public string $birthDate = '';

    public bool $birthDateFromEmployee = false;

    public string $motherName = '';

    public string $phone = '';

    public bool $phoneFromEmployee = false;

    public ?int $questionId = null;

    public string $answer = '';

    public ?string $errorMessage = null;

    public function mount(SecurityRecoveryService $service): void
    {
        /** @var SecUser $user */
        $user = Auth::user();

        $fromEmployee = $service->resolveBirthDateFromEmployee($user);

        if ($fromEmployee) {
            $this->birthDate = $fromEmployee;
            $this->birthDateFromEmployee = true;
        }

        $phoneFromEmployee = $service->resolvePhoneFromEmployee($user);

        if ($phoneFromEmployee) {
            $this->phone = $phoneFromEmployee;
            $this->phoneFromEmployee = true;
        } else {
            $this->phone = (string) $user->phone;
        }
    }

    #[Computed]
    public function questionOptions()
    {
        return app(SecurityRecoveryService::class)->activeQuestions();
    }

    public function submit(SecurityRecoveryService $service): mixed
    {
        $this->errorMessage = null;

        $data = $this->validate([
            'birthDate' => ['required', 'date'],
            'motherName' => ['required', 'string', 'min:2', 'max:150'],
            'phone' => ['required', 'string', 'min:8', 'max:20'],
            'questionId' => ['required', 'integer', 'exists:tm2sys.sec_recovery_question,id_question'],
            'answer' => ['required', 'string', 'min:2', 'max:150'],
        ]);

        /** @var SecUser $user */
        $user = Auth::user();

        if ($user->phone !== $data['phone']) {
            $user->phone = $data['phone'];
            $user->save();
        }

        $service->saveSetup($user, $data['birthDate'], $data['motherName'], (int) $data['questionId'], $data['answer']);
        $service->syncIdentityToEmployee($user, $data['birthDate'], $data['phone']);

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
            <h2>Setup keamanan akun</h2>
            <p class="sub">Isi 4 data ini sekali saja — dipakai untuk verifikasi identitas kalau Anda lupa password nanti (tanpa email).</p>

            @if ($errorMessage)
                <div class="hint bad" style="margin-bottom:14px">{{ $errorMessage }}</div>
            @endif

            <form wire:submit="submit">
                <x-form.date-picker legacy="field" label="Tanggal lahir" wire:model="birthDate" name="birthDate"
                    :hint="$birthDateFromEmployee ? 'Diambil dari data karyawan Anda. Salah? Ubah di sini — perubahan akan direview HR dulu sebelum berlaku.' : null" />
                <x-form.input legacy="field" label="Nama ibu kandung" wire:model="motherName" placeholder="Nama lengkap ibu kandung Anda" name="motherName" />
                <x-form.input legacy="field" label="Nomor HP terdaftar" wire:model="phone" placeholder="08xxxxxxxxxx" name="phone"
                    :hint="$phoneFromEmployee ? 'Diambil dari data karyawan Anda. Salah? Ubah di sini — perubahan akan direview HR dulu sebelum berlaku.' : null" />
                <x-form.select legacy="field" label="Pertanyaan keamanan" wire:model="questionId" name="questionId"
                    style="width:100%;padding:10px 12px;border:1px solid var(--ap-border,#d7dbe2);border-radius:8px;font-size:14px"
                    placeholder="— pilih pertanyaan —" :options="$this->questionOptions->pluck('question_text', 'id_question')->all()" />
                <x-form.input legacy="field" label="Jawaban Anda" wire:model="answer" placeholder="Jawaban Anda" name="answer" />

                <button type="submit" class="btn" wire:loading.attr="disabled" style="margin-top:20px">Simpan &amp; lanjutkan</button>
            </form>

            <button type="button" class="back" style="background:none;border:none;cursor:pointer" wire:click="logout"><span>&larr;</span> <span>Keluar dan lengkapi nanti</span></button>
        </div>
    </div>
</div>
