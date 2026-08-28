<?php

namespace App\Services\Security;

use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpFamily;
use App\Models\Sys\SecRecoveryQuestion;
use App\Models\Sys\SecUser;
use App\Services\Hrm\FamilyEmploymentCrossCheckService;
use Illuminate\Support\Facades\Hash;

// Fitur "Lupa Password" berbasis 4 pertanyaan (keputusan user 2026-08-27,
// menggantikan alur link-email lama -- email/WA gateway belum tentu jalan
// di server ini, lihat security_defaultpassword_spec.md §0). Reuse
// default_password (SecUser::defaultPassword() accessor, sudah ada) sbg
// tujuan akhir reset -- TIDAK generate password baru, murni "kembalikan ke
// password default yang diberikan Admin di awal".
class SecurityRecoveryService
{
    public function isSetupComplete(SecUser $user): bool
    {
        return $user->birth_date !== null
            && $user->mother_name_hash !== null
            && $user->security_question_id !== null
            && $user->security_answer_hash !== null;
    }

    public function activeQuestions()
    {
        return SecRecoveryQuestion::where('activate', true)->orderBy('sort_order')->get();
    }

    // Tanggal lahir dari emp_employee kalau akun ini tertaut karyawan --
    // ditampilkan read-only di layar setup, user tinggal konfirmasi.
    // Null kalau tak tertaut (mis. akun admin/pelanggan/supplier) -- user isi manual.
    public function resolveBirthDateFromEmployee(SecUser $user): ?string
    {
        if (! $user->employee_id) {
            return null;
        }

        $date = EmpEmployee::where('employee_id', $user->employee_id)->value('birth_date');

        return $date ? \Illuminate\Support\Carbon::parse($date)->toDateString() : null;
    }

    // No HP dari emp_employee.phone kalau akun ini tertaut karyawan -- pola
    // sama dengan resolveBirthDateFromEmployee(). sec_user.phone sering kosong
    // meski emp_employee.phone terisi (data drift lama), jadi employee data
    // yang diprioritaskan sbg sumber kebenaran saat setup.
    public function resolvePhoneFromEmployee(SecUser $user): ?string
    {
        if (! $user->employee_id) {
            return null;
        }

        $phone = EmpEmployee::where('employee_id', $user->employee_id)->value('phone');

        return $phone ?: null;
    }

    public function saveSetup(SecUser $user, string $birthDate, string $motherName, int $questionId, string $answer): void
    {
        $user->birth_date = $birthDate;
        $user->mother_name_hash = Hash::make(mb_strtolower(trim($motherName)));
        $user->security_question_id = $questionId;
        $user->security_answer_hash = Hash::make(mb_strtolower(trim($answer)));
        $user->security_setup_at = now();
        $user->save();

        $this->syncMotherNameToFamily($user, $motherName);
    }

    // Keputusan user 2026-08-27 (interupsi soal field readonly di security-setup,
    // lalu diperjelas beberapa kali): field tanggal lahir/No HP di layar ini
    // SELALU bisa diedit (readonly dicabut -- sempat bikin user literal
    // terkunci di alur login saat datanya salah, tak ada jalan keluar). Tapi
    // TIDAK PERNAH langsung menimpa emp_employee -- kalau nilai yang diketik
    // BEDA dari yang tercatat (termasuk saat sebelumnya kosong), diajukan sbg
    // EmpCorrectionRequest (tabel yang SUDAH ADA, dipakai self-service "Ajukan
    // Koreksi", label field disamakan persis: "Tanggal Lahir"/"Telepon / WA")
    // supaya lewat alur review HR yang sama, bisa di-reject. Kalau nilai SAMA
    // dgn yang sudah tercatat, tidak ada pengajuan sama sekali (no-op, tak
    // bikin noise). Lihat resources/views/pages/hrm/correction-requests.blade.php
    // (layar review -- emp_correction_request sebelumnya tak pernah punya
    // layar approve/reject sama sekali, request cuma numpuk PENDING selamanya).
    public function syncIdentityToEmployee(SecUser $user, string $birthDate, string $phone): void
    {
        if (! $user->employee_id) {
            return;
        }

        $employee = EmpEmployee::find($user->employee_id);

        if (! $employee) {
            return;
        }

        $currentBirthDate = $employee->birth_date?->toDateString();

        if ($birthDate && $birthDate !== $currentBirthDate) {
            $this->submitCorrectionRequest($employee, 'Tanggal Lahir', $currentBirthDate, $birthDate);
        }

        if ($phone && $phone !== $employee->phone) {
            $this->submitCorrectionRequest($employee, 'Telepon / WA', $employee->phone, $phone);
        }
    }

    protected function submitCorrectionRequest(EmpEmployee $employee, string $fieldName, ?string $oldValue, string $newValue): void
    {
        $alreadyPending = \App\Models\Master\EmpCorrectionRequest::where('employee_id', $employee->employee_id)
            ->where('field_name', $fieldName)
            ->where('status', 'PENDING')
            ->where('new_value', $newValue)
            ->exists();

        if ($alreadyPending) {
            return;
        }

        \App\Models\Master\EmpCorrectionRequest::create([
            'employee_id' => $employee->employee_id,
            'field_name' => $fieldName,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'reason' => 'Diajukan mandiri lewat Setup Keamanan Akun.',
            'requires_hr_approval' => true,
            'status' => 'PENDING',
            'requested_at' => now(),
        ]);
    }

    // Keputusan user 2026-08-27: nama ibu kandung harus ada DI DUA tempat --
    // sec_user.mother_name_hash (hash, rahasia, khusus verifikasi lupa-password)
    // DAN emp_family relation=IBU (plain, data HR). Untuk 136 karyawan lama yang
    // belum punya data keluarga sama sekali, ini satu-satunya field family yang
    // "dipaksa" -- ayah/mertua/pasangan/anak tetap opsional buat mereka, hanya
    // wajib penuh utk karyawan BARU lewat Master Employee (lihat employee.blade.php).
    protected function syncMotherNameToFamily(SecUser $user, string $motherName): void
    {
        if (! $user->employee_id) {
            return;
        }

        $existing = EmpFamily::where('employee_id', $user->employee_id)->where('relation', 'IBU')->first();

        if ($existing) {
            $existing->update(['full_name' => trim($motherName), 'updated_by' => $user->login]);
            app(FamilyEmploymentCrossCheckService::class)->checkFamilyMember($existing->fresh());

            return;
        }

        $family = EmpFamily::create([
            'employee_id' => $user->employee_id,
            'relation' => 'IBU',
            'full_name' => trim($motherName),
            'activate' => true,
            'created_by' => $user->login,
            'updated_by' => $user->login,
        ]);

        app(FamilyEmploymentCrossCheckService::class)->checkFamilyMember($family);
    }

    // Cocokkan 4 jawaban. Tak bilang mana yang salah (cegah tebak-tebak
    // bertahap) -- semua-atau-tidak-sama-sekali.
    public function verify(SecUser $user, string $birthDate, string $motherName, string $phone, string $answer): bool
    {
        if (! $this->isSetupComplete($user)) {
            return false;
        }

        $birthOk = $user->birth_date && (string) $user->birth_date->toDateString() === $birthDate;
        $phoneOk = $user->phone && $this->normalizePhone($user->phone) === $this->normalizePhone($phone);
        $motherOk = Hash::check(mb_strtolower(trim($motherName)), $user->mother_name_hash);
        $answerOk = Hash::check(mb_strtolower(trim($answer)), $user->security_answer_hash);

        return $birthOk && $phoneOk && $motherOk && $answerOk;
    }

    protected function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    // "Kembali ke password default" -- default_password TIDAK diganti/regenerate,
    // cuma dijadikan aktif lagi. must_change_password=1 spy user tetap dipaksa
    // ganti password sendiri stlh masuk (pola sama security_defaultpassword_spec.md §6).
    public function resetToDefaultPassword(SecUser $user): bool
    {
        if (! $user->default_password) {
            return false;
        }

        $user->pswd = Hash::make($user->default_password);
        $user->must_change_password = true;
        $user->pswd_last_updated = now();
        $user->save();

        return true;
    }
}
