<?php

namespace App\Services\Migration;

use App\Models\Master\EmpEmployee;
use App\Models\Sys\SecUser;
use App\Services\Security\PasswordResetService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// Buat akun login (sec_user) untuk karyawan hasil EmployeeMigrationService,
// supaya data hasil migrasi tm2old.emp bisa benar-benar dipakai login/self-
// service, bukan cuma baris di grid. Dipicu manual dari tab "Migrasi Data
// Legacy" (Setup, admin-only), sama pola idempotent dgn service migrasi lain.
//
// Keputusan sadar:
// - HANYA karyawan is_active=true yang dibuatkan login (137 dari 484) --
//   karyawan yang sudah keluar/terminated di dunia nyata tidak dapat akun
//   aktif, memberi mereka login akan MELANGGAR premis "seolah-olah data
//   real" itu sendiri.
// - login = nama depan lowercase (pola sama dgn UatKeyPersonSeeder), tabrakan
//   ditambah angka urut (mis. "budi", "budi2", "budi3").
// - Password: App\Services\Security\PasswordResetService::generate() (format
//   readable, dipakai admin/UAT seeder yang sudah ada) -- BUKAN reset(), sebab
//   reset() butuh SecUser yang sudah ada; user di sini baru dibuat.
//   must_change_password=1 supaya tetap masuk alur security_defaultpassword_spec.md
//   (dipaksa ganti password begitu login pertama).
// - approval_status='approved' + active/is_active=1 -- langsung bisa login,
//   tak nyangkut di alur approval pendaftaran.
// - employee_id ditaut ke emp_employee (LOGICAL, lintas-DB) supaya Portal
//   Karyawan/self-service otomatis aktif untuk akun ini (App\Models\Sys\SecUser
//   sudah punya currentPositionId() dst yang baca dari sini).
class EmployeeLoginProvisionService
{
    public function run(string $actor): array
    {
        ini_set('memory_limit', '512M');
        set_time_limit(120);

        $report = [
            'created' => 0,
            'skippedExisting' => 0,
            'sample' => [],
        ];

        $existingLogins = SecUser::pluck('login')
            ->map(fn ($l) => mb_strtolower((string) $l))
            ->flip()
            ->toArray();

        $employeesWithLogin = SecUser::whereNotNull('employee_id')->pluck('employee_id')->flip()->toArray();

        EmpEmployee::whereNotNull('legacy_employee_id')
            ->where('activate', true)
            ->orderBy('employee_id')
            ->chunk(100, function ($chunk) use (&$report, &$existingLogins, $employeesWithLogin, $actor) {
                foreach ($chunk as $emp) {
                    if (isset($employeesWithLogin[$emp->employee_id])) {
                        $report['skippedExisting']++;

                        continue;
                    }

                    $cred = $this->provisionForEmployee($emp, $actor, $existingLogins);
                    $existingLogins[mb_strtolower($cred['login'])] = true;

                    $report['created']++;
                    if (count($report['sample']) < 15) {
                        $report['sample'][] = $cred;
                    }
                }
            });

        return $report;
    }

    // Provisioning SATU karyawan (dipakai jalur registrasi baru lewat Master
    // Employee -- keputusan user 2026-08-27: "diangkat jadi employee" =
    // login auto dibuat, dicetak IT/HR buat kandidat -- BUKAN lewat layar
    // "Tambah Pengguna" manual yg tak menaut employee_id, itu khusus akun
    // non-employee spt customer/supplier). Idempotent: kalau employee_id ini
    // sudah punya login, return null, tak bikin dobel.
    public function provisionForEmployee(EmpEmployee $emp, string $actor, ?array $existingLoginsHint = null): ?array
    {
        if (SecUser::where('employee_id', $emp->employee_id)->exists()) {
            return null;
        }

        $existingLogins = $existingLoginsHint ?? SecUser::pluck('login')
            ->map(fn ($l) => mb_strtolower((string) $l))
            ->flip()
            ->toArray();

        $login = $this->uniqueLogin($emp->full_name, $existingLogins);
        $plain = app(PasswordResetService::class)->generate();

        SecUser::create([
            'login' => $login,
            'pswd' => Hash::make($plain),
            'default_password' => $plain, // accessor -> Crypt::encryptString
            'must_change_password' => true,
            'name' => $emp->full_name,
            'email' => $login.'@telasih.co.id',
            'employee_id' => $emp->employee_id,
            'active' => true,
            'activate' => true,
            'is_admin' => false,
            'approval_status' => 'approved',
            'pswd_last_updated' => now(),
            'created_by' => $actor,
            'updated_by' => $actor,
        ]);

        return ['login' => $login, 'name' => $emp->full_name, 'password' => $plain];
    }

    protected function uniqueLogin(string $fullName, array &$existingLogins): string
    {
        $first = Str::of($fullName)->trim()->explode(' ')[0] ?? 'user';
        $base = Str::of($first)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '')->toString();
        $base = $base !== '' ? $base : 'user';

        $login = $base;
        $suffix = 1;
        while (isset($existingLogins[$login])) {
            $suffix++;
            $login = $base.$suffix;
        }

        return $login;
    }
}
