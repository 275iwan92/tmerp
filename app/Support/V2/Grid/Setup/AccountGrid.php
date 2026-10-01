<?php

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SecUser;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Setup > Security > Account (v1: pages::security.index tab users): akun login. Edit / New = form v1 (grup, reset password);
// Aktif / Nonaktif di sini memakai aturan v1: admin tidak bisa menonaktifkan akunnya sendiri.
class AccountGrid extends DbGrid
{
    public function slug(): string
    {
        return 'security-accounts';
    }

    public function title(): string
    {
        return 'Account';
    }

    public function module(): string
    {
        return 'SETUP';
    }

    public function moduleLabel(): string
    {
        return 'Setup';
    }

    public function family(): string
    {
        return self::MASTER;
    }

    public function group(): string
    {
        return 'Security';
    }

    public function icon(): string
    {
        return 'fa-solid fa-user-gear';
    }

    public function openUrl(): ?string
    {
        return route('security.index').'?tab=users&open={id}';
    }

    public function newUrl(): ?string
    {
        return route('security.index').'?tab=users&new=1';
    }

    public function search(): string
    {
        return 'Search login, name, e-mail...';
    }

    protected function query(): Builder
    {
        return SecUser::query()->with('groups')->orderBy('login');
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Account', 'type' => 'identity', 'code' => 'login'],
            ['key' => 'email', 'label' => 'E-mail'],
            ['key' => 'groups', 'label' => 'Group'],
            ['key' => 'admin', 'label' => 'Administrator', 'type' => 'badge', 'tones' => ['Administrator' => 'danger']],
            ['key' => 'password', 'label' => 'Password', 'type' => 'badge', 'tones' => ['Must change' => 'warn']],
            ['key' => 'language', 'label' => 'Language', 'visible' => false],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'name' => $m->name, 'login' => $m->login, 'email' => $m->email, 'groups' => $m->groups->pluck('group_name')->implode(', '),
            'admin' => $m->is_admin ? 'Administrator' : null, 'password' => $m->must_change_password ? 'Must change' : null, 'language' => $m->lang_code,
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Active', 'where' => ['active' => true], 'tone' => 'success'],
            ['label' => 'Must change password', 'where' => ['password' => 'Must change'], 'tone' => 'warn'],
            ['label' => 'Inactive', 'where' => ['active' => false], 'tone' => 'danger'],
        ];
    }

    public function filters(): array
    {
        return ['groups' => 'Group', 'admin' => 'Administrator'];
    }

    public function toggle(int $id): bool
    {
        $user = SecUser::query()->findOrFail($id);
        // Akun sendiri tidak bisa dinonaktifkan (mengunci diri sendiri di luar).
        if ($id !== (int) auth()->id()) {
            $user->update(['activate' => ! $user->activate, 'updated_by' => auth()->user()?->login]);
        }

        return (bool) $user->refresh()->activate;
    }
}
