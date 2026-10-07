<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Models\Master\EmpEmployee;
use App\Models\Master\OrgDepartment;
use App\Models\Master\OrgPosition;

// HRM > Organization > Organization Chart (v2): visualisasi read-only struktur jabatan untuk objek x-dl::org-chart. Hierarki 100% dari
// org_position.reports_to_position_id (diatur di Master Position); satu jabatan boleh dipegang beberapa pegawai aktif (status kerja WORKING),
// jabatan tanpa pemegang tampil "Lowong". Aturan sama dengan layar v1.
final class OrgStructure
{
    /** @return list<array{id: int, parent: int|null, title: string, dept: string, people: list<string>}> */
    public static function nodes(): array
    {
        $positions = OrgPosition::with('department')->where('activate', true)->orderBy('position_name')->get();
        $people = EmpEmployee::whereIn('position_id', $positions->pluck('position_id'))->where('activate', true)->where('work_status', 'WORKING')
            ->orderBy('full_name')->get()->groupBy('position_id');
        $ids = $positions->pluck('position_id')->all();

        return $positions->map(fn (OrgPosition $p) => [
            'id' => (int) $p->position_id,
            // Atasan yang nonaktif / tidak ada di daftar dianggap puncak, supaya kartu tidak hilang dari pohon.
            'parent' => $p->reports_to_position_id && in_array($p->reports_to_position_id, $ids, true) ? (int) $p->reports_to_position_id : null,
            'title' => $p->position_name, 'dept' => (string) ($p->department?->department_name ?? ''),
            'people' => ($people->get($p->position_id) ?? collect())->pluck('full_name')->all(),
        ])->all();
    }

    /** @return list<string> nama departemen aktif untuk kotak pilih */
    public static function departments(): array
    {
        return OrgDepartment::where('activate', true)->orderBy('department_name')->pluck('department_name')->all();
    }

    /** True bila tidak ada satu pun relasi atasan-bawahan (semua kartu berdiri sendiri). */
    public static function isFlat(array $nodes): bool
    {
        return $nodes !== [] && collect($nodes)->every(fn ($n) => $n['parent'] === null);
    }
}
