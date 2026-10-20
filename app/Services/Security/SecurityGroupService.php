<?php

/*
 * REMARK
 * Tujuan       : Grup akses (sec_group) dari layar v2 security-group-form: grup baru (kode, nama, level), ubah nama / level, aktif / nonaktif. Grup = level akses, bukan jabatan (rulebook C.14); hak per modul diatur di layar Groups on Modules (security-permissions).
 * Sumber hulu  : Aturan dipindah dari halaman v1 pages::security.index tab groups (diarsipkan di tmerp_unused/security_index_v1_2026-10-20.blade.php): kode unik maks 40 ditulis HURUF BESAR dengan spasi menjadi garis bawah dan tidak berubah sesudah dibuat, nama wajib maks 100, level 1-5, grup baru aktif. Baru (Jo 2026-10-20, mode full speed): kode hanya huruf / angka / garis bawah, nama unik, grup yang masih punya anggota aktif tidak boleh dinonaktifkan (v1 membolehkan sehingga anggotanya kehilangan akses diam-diam).
 * Input        : grup (opsional), array isian, user admin yang bertindak.
 * Output       : tm2sys.sec_group berubah; ValidationException bila aturan dilanggar.
 * Dipakai oleh : routes/v2_setup.php (v2.setup.group.save), layar security-group-form, tes SecurityGroupServiceTest.
 * Status       : Aktif (T8 Setup, 2026-10-20).
 */

namespace App\Services\Security;

use App\Models\Sys\SecGroup;
use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SecurityGroupService
{
    /** Nama level akses (mgmt_level) untuk tampilan. */
    public const LEVELS = [1 => 'Staff', 2 => 'Supervisor', 3 => 'Manager', 4 => 'General Manager', 5 => 'Director'];

    public function save(?SecGroup $g, array $in, SecUser $actor): SecGroup
    {
        $new = ! $g?->exists;
        $rules = [
            'group_name' => ['required', 'string', 'max:100', Rule::unique('tm2sys.sec_group', 'group_name')->ignore($g?->getKey(), 'id_group')],
            'mgmt_level' => ['required', 'integer', 'between:1,5'],
        ];
        if ($new) {
            $rules['group_code'] = ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_ ]+$/'];
        }
        $d = Validator::make($in, $rules, ['group_code.regex' => 'The code may only contain letters, digits, spaces and underscores.', 'group_name.unique' => 'This group name is already used.'],
            ['group_name' => 'Group name', 'mgmt_level' => 'Access level', 'group_code' => 'Group code'])->validate();

        return DB::connection('tm2sys')->transaction(function () use ($g, $new, $d, $actor) {
            if ($new) {
                $code = strtoupper(str_replace(' ', '_', trim($d['group_code'])));
                if (SecGroup::where('group_code', $code)->exists()) {
                    throw ValidationException::withMessages(['group_code' => 'This group code is already used.']);
                }

                return SecGroup::create(['group_code' => $code, 'group_name' => trim($d['group_name']), 'mgmt_level' => (int) $d['mgmt_level'], 'activate' => true, 'created_by' => $actor->login]);
            }
            $g->update(['group_name' => trim($d['group_name']), 'mgmt_level' => (int) $d['mgmt_level'], 'updated_by' => $actor->login]);

            return $g;
        });
    }

    public function setActive(SecGroup $g, bool $active, SecUser $actor): void
    {
        if (! $active && $g->users()->where('sec_user.activate', true)->exists()) {
            throw ValidationException::withMessages(['group' => 'This group still has active members; move them to another group first.']);
        }
        $g->update(['activate' => $active, 'updated_by' => $actor->login]);
    }
}
