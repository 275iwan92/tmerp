<?php

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SecGroup;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Setup > Security > Group (v1: pages::security.index tab groups): grup = level akses, bukan jabatan (rulebook C.14).
// Edit / New = form v1; hak per modul diatur di Group per Submodule (v1).
class GroupGrid extends DbGrid
{
    // sec_group.mgmt_level: skala 1-5 (komentar kolom di database).
    private const LEVELS = [1 => 'Staff', 2 => 'Supervisor', 3 => 'Manager', 4 => 'General Manager', 5 => 'Director'];

    public function slug(): string
    {
        return 'security-groups';
    }

    public function title(): string
    {
        return 'Group';
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
        return 'fa-solid fa-people-group';
    }

    public function openUrl(): ?string
    {
        return route('security.index').'?tab=groups&open={id}';
    }

    public function newUrl(): ?string
    {
        return route('security.index').'?tab=groups&new=1';
    }

    public function search(): string
    {
        return 'Search group code, group name...';
    }

    protected function query(): Builder
    {
        return SecGroup::query()->withCount('users')->orderBy('group_name');
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Group', 'type' => 'identity', 'code' => 'code'],
            ['key' => 'level', 'label' => 'Access Level', 'type' => 'number'],
            ['key' => 'level_name', 'label' => 'Level Name'],
            ['key' => 'members', 'label' => 'Members', 'type' => 'number'],
            ['key' => 'filled', 'label' => 'Membership', 'type' => 'badge', 'tones' => ['Has members' => 'success', 'Empty' => 'neutral']],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'name' => $m->group_name, 'code' => $m->group_code, 'level' => $m->mgmt_level, 'level_name' => self::LEVELS[$m->mgmt_level] ?? null,
            'members' => (int) $m->users_count, 'filled' => $m->users_count > 0 ? 'Has members' : 'Empty',
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Has members', 'where' => ['filled' => 'Has members'], 'tone' => 'success'],
            ['label' => 'Empty', 'where' => ['filled' => 'Empty'], 'tone' => 'neutral'],
            ['label' => 'Inactive', 'where' => ['active' => false], 'tone' => 'danger'],
        ];
    }

    public function filters(): array
    {
        return ['level_name' => 'Level Name', 'filled' => 'Membership'];
    }
}
