<?php

declare(strict_types=1);

/*
 * REMARK
 * Tujuan       : Bahan lookup master Production v2: cari proses (kode, nama, tahap) dan mesin (kode, nama) untuk baris langkah routing dan layar master Production berikutnya. Hanya baca.
 * Sumber hulu  : tm2mst.prod_process (activate = 1) dan prod_machine (activate = 1); data real pabrik, tidak diubah.
 * Input        : Kata cari, id.
 * Output       : Baris lookup (Devlib\Lookup search / find).
 * Dipakai oleh : App\Support\V2\Lookups (prod_process, prod_machine), layar prod-routing-form.
 * Status       : Aktif (Master T6b).
 */

namespace App\Support\V2\Data;

use App\Models\Master\ProdMachine;
use App\Models\Master\ProdProcess;

final class ProductionRef
{
    /** @return list<array<string, mixed>> */
    public static function processSearch(string $q, int $limit = 30): array
    {
        return ProdProcess::query()->where('activate', true)->where(fn ($w) => $w->where('process_code', 'like', "%{$q}%")->orWhere('process_name', 'like', "%{$q}%"))
            ->orderBy('process_name')->limit($limit)->get()->map(fn (ProdProcess $p) => self::processRow($p))->all();
    }

    /** @return array<string, mixed>|null */
    public static function processFind(mixed $value): ?array
    {
        $p = ProdProcess::query()->find((int) $value);

        return $p ? self::processRow($p) : null;
    }

    private static function processRow(ProdProcess $p): array
    {
        return ['id' => (int) $p->id_process, 'code' => (string) $p->process_code, 'name' => (string) $p->process_name, 'stage' => $p->process_stage ? $p->stageLabel() : '—',
            'label' => trim(($p->process_code ? $p->process_code.' — ' : '').$p->process_name.' ['.($p->process_stage ? $p->stageLabel() : 'no stage').']')];
    }

    /** @return list<array<string, mixed>> */
    public static function machineSearch(string $q, int $limit = 30): array
    {
        return ProdMachine::query()->where('activate', true)->where(fn ($w) => $w->where('machine_code', 'like', "%{$q}%")->orWhere('machine_name', 'like', "%{$q}%"))
            ->orderBy('machine_code')->limit($limit)->get()->map(fn (ProdMachine $m) => self::machineRow($m))->all();
    }

    /** @return array<string, mixed>|null */
    public static function machineFind(mixed $value): ?array
    {
        $m = ProdMachine::query()->find((int) $value);

        return $m ? self::machineRow($m) : null;
    }

    private static function machineRow(ProdMachine $m): array
    {
        return ['id' => (int) $m->machine_id, 'code' => (string) $m->machine_code, 'name' => (string) $m->machine_name, 'label' => $m->machine_code.' — '.$m->machine_name];
    }
}
