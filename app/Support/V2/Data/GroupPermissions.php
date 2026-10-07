<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Models\Sys\SecGroup;
use App\Models\Sys\SecGroupPermission;
use App\Models\Sys\SecModule;
use Illuminate\Validation\ValidationException;

// Setup > Security > Groups on Modules (v2): objek x-dl::permission-matrix di atas sec_group_permission (grup x modul, 4 tanda).
// Tingkat matriks <-> tanda (BRIEF §4: baca-saja = akses tanpa input):
//   0 No access = tidak ada baris / semua tanda mati      1 Read only = akses
//   2 Read-write = akses + input                          3 Read-write + Export = + export
//   4 All = + export + approve
// Baris yang tingkatnya tidak berubah TIDAK ditulis ulang (kombinasi tanda lama yang tidak baku tetap utuh).
final class GroupPermissions
{
    public const NONE = 0;

    public const ALL = 4;

    /** @return list<array{key: string, name: string, type: string, module: string}> */
    public static function objects(): array
    {
        return SecModule::query()->where('activate', true)->orderBy('sort_order')->get()
            ->map(fn (SecModule $m) => ['key' => (string) $m->id_module, 'name' => $m->module_name, 'type' => $m->module_code, 'module' => $m->module_name])->all();
    }

    /** @return list<string> */
    public static function groups(): array
    {
        return SecGroup::query()->where('activate', true)->orderBy('group_name')->pluck('group_name')->all();
    }

    /** @return array<string, array<string, int>> nama grup => [id modul => tingkat] */
    public static function levels(): array
    {
        $names = SecGroup::query()->pluck('group_name', 'id_group');
        $out = [];
        foreach (SecGroupPermission::all() as $p) {
            if (isset($names[$p->group_id])) {
                $out[$names[$p->group_id]][(string) $p->module_id] = self::levelOf($p);
            }
        }

        return $out;
    }

    public static function levelOf(SecGroupPermission $p): int
    {
        return match (true) {
            ! $p->can_access => self::NONE,
            (bool) $p->can_approve => self::ALL,
            (bool) $p->can_export => 3,
            (bool) $p->can_input => 2,
            default => 1,
        };
    }

    /** @return array{can_access: bool, can_input: bool, can_approve: bool, can_export: bool} */
    public static function flags(int $level): array
    {
        return ['can_access' => $level >= 1, 'can_input' => $level >= 2, 'can_export' => $level >= 3, 'can_approve' => $level >= self::ALL];
    }

    /** @param array<string|int, mixed> $perms id modul => tingkat; @return int jumlah baris yang berubah */
    public static function save(string $groupName, array $perms, string $by): int
    {
        $group = SecGroup::query()->where('group_name', $groupName)->first()
            ?? throw ValidationException::withMessages(['group' => t('Group not found.')]);
        $modules = SecModule::query()->pluck('id_module')->all();
        $existing = SecGroupPermission::query()->where('group_id', $group->id_group)->get()->keyBy('module_id');
        $changed = 0;

        foreach ($perms as $moduleId => $level) {
            $moduleId = (int) $moduleId;
            if (! in_array($moduleId, $modules, true) || ! is_numeric($level) || $level < self::NONE || $level > self::ALL) {
                throw ValidationException::withMessages(['perms' => t('Invalid access level.')]);
            }
            $row = $existing->get($moduleId);
            if (($row ? self::levelOf($row) : self::NONE) === (int) $level) {
                continue;
            }
            $values = self::flags((int) $level) + ['updated_by' => $by];
            $row ? $row->update($values) : SecGroupPermission::create($values + ['group_id' => $group->id_group, 'module_id' => $moduleId, 'created_by' => $by]);
            $changed++;
        }

        return $changed;
    }
}
