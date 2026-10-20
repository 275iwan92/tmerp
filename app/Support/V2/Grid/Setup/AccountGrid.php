<?php

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SecUser;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/*
 * REMARK
 * Tujuan       : Grid Setup > Security > Account: akun login (sec_user) beserta grup, status administrator dan status password; Open / + New Record membuka form v2 security-account-form (ubah akun, reset password, aktif / nonaktif; admin tidak bisa menonaktifkan akunnya sendiri).
 * Sumber hulu  : tm2sys.sec_user + sec_user_group + sec_group.
 * Input        : Pencarian, filter, kotak status.
 * Output       : Baris grid (hanya baca; perubahan lewat form).
 * Dipakai oleh : Menu Setup > Security (v2:security-accounts), layar security-account-form (kembali ke daftar).
 * Status       : Aktif. Halaman v1 pages::security.index (tab users dan groups) sudah dipindah ke tmerp_unused (aturan D.25, T8 Setup).
 */
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
        return route('v2.screen', 'security-account-form').'?id={id}';
    }

    public function newUrl(): ?string
    {
        return route('v2.screen', 'security-account-form').'?new=1';
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
