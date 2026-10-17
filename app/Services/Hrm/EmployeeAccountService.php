<?php

/*
 * REMARK
 * Tujuan       : Aksi akun login pegawai dari tab Account layar v2 Master Employee Form (hanya Admin App): buat login, reset password, aktif / nonaktif. Pengaturan grup tetap di Security.
 * Sumber hulu  : tm2sys.sec_user (relasi logis lewat employee_id); PasswordResetService dan EmployeeLoginProvisionService dipakai apa adanya (aturan sama dengan layar Security).
 * Input        : Pegawai, user admin yang bertindak.
 * Output       : Akun berubah; password baru dikembalikan sekali untuk kartu login; aturan "admin tidak bisa menonaktifkan akunnya sendiri" dilempar sebagai ValidationException.
 * Dipakai oleh : routes/v2.php (hrm.employee.account.*), layar hrm-employee-form tab Account, tes EmployeeAccountServiceTest.
 * Status       : Aktif.
 */

namespace App\Services\Hrm;

use App\Models\Master\EmpEmployee;
use App\Models\Sys\SecUser;
use App\Services\Migration\EmployeeLoginProvisionService;
use App\Services\Security\PasswordResetService;
use Illuminate\Validation\ValidationException;

final class EmployeeAccountService
{
    public function userOf(EmpEmployee $e): ?SecUser
    {
        return SecUser::with('groups')->where('employee_id', $e->employee_id)->first();
    }

    /** @return ?array {login, name, password} atau null bila pegawai sudah punya login */
    public function createLogin(EmpEmployee $e, SecUser $actor): ?array
    {
        return app(EmployeeLoginProvisionService::class)->provisionForEmployee($e, $actor->login);
    }

    /** @return array {login, name, password}: password baru ditampilkan sekali di kartu login */
    public function resetPassword(SecUser $target): array
    {
        $plain = app(PasswordResetService::class)->reset($target);

        return ['login' => $target->login, 'name' => $target->name, 'password' => $plain];
    }

    public function setActive(SecUser $target, bool $active, SecUser $actor): void
    {
        if (! $active && $target->getKey() === $actor->getKey()) {
            throw ValidationException::withMessages(['account' => 'You cannot deactivate your own account.']);
        }
        $target->update(['activate' => $active]);
    }
}
