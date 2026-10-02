<?php

namespace App\Services\Chat;

use App\Models\Master\EmpEmployee;
use App\Models\Master\OrgPosition;
use App\Models\Sys\SecUser;
use Chat\Contracts\Directory;
use Illuminate\Contracts\Auth\Authenticatable;

// Sambungan paket chat (packages/chat) ke user tmerp: yang bisa di-chat = user terdaftar yang aktif dan sudah disetujui
// (user 2026-10-02), keterangan di bawah nama = jabatan pegawainya. Kunci di tabel chat = login, sama dengan panel chat v1.
class UserDirectory implements Directory
{
    public function people(Authenticatable $me): array
    {
        $users = $this->registered()->where('id_user', '!=', $me->getAuthIdentifier())->orderBy('name')->get(['id_user', 'login', 'name', 'employee_id']);
        $position = EmpEmployee::whereIn('employee_id', $users->pluck('employee_id')->filter())->pluck('position_id', 'employee_id');
        $title = OrgPosition::whereIn('position_id', $position->filter()->unique())->pluck('position_name', 'position_id');

        return $users->map(fn (SecUser $u) => $this->row($u, (string) ($title[$position[$u->employee_id] ?? null] ?? '')))->all();
    }

    public function person(int $id): ?array
    {
        $u = $this->registered()->find($id);

        return $u ? $this->row($u, '') : null;
    }

    public function key(Authenticatable $user): string
    {
        return (string) $user->login;
    }

    public function name(Authenticatable $user): string
    {
        return (string) ($user->name ?: $user->login);
    }

    private function registered()
    {
        return SecUser::query()->where('activate', true)->where('approval_status', 'approved');
    }

    private function row(SecUser $u, string $role): array
    {
        return ['id' => (int) $u->id_user, 'key' => (string) $u->login, 'name' => $this->name($u), 'role' => $role, 'photo' => null];
    }
}
