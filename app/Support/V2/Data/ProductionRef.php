<?php

declare(strict_types=1);

/*
 * REMARK
 * Tujuan       : Bahan lookup master Production v2: cari proses (kode, nama, tahap; juga hanya proses yang memakai mesin), mesin (kode, nama; juga hanya mesin proses) dan model katalog mesin untuk baris langkah routing, matriks proses x mesin dan form Machine. Hanya baca.
 * Sumber hulu  : tm2mst.prod_process (activate = 1) dan prod_machine (activate = 1); data real pabrik, tidak diubah.
 * Input        : Kata cari, id.
 * Output       : Baris lookup (Devlib\Lookup search / find).
 * Dipakai oleh : App\Support\V2\Lookups (prod_process, prod_machine, prod_machine_proc, prod_process_machine_side, prod_machine_model), layar prod-routing-form, prod-process-form dan prod-machine-form.
 * Status       : Aktif (Master T6b).
 */

namespace App\Support\V2\Data;

use App\Models\Master\ProdMachine;
use App\Models\Master\ProdMachineModel;
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

    /** @return list<array<string, mixed>> mesin proses aktif (alat penunjang seperti kompresor dan forklift tidak ikut) untuk baris matriks proses x mesin */
    public static function processMachineSearch(string $q, int $limit = 30): array
    {
        return ProdMachine::query()->where('activate', true)->where('is_process_machine', true)->where(fn ($w) => $w->where('machine_code', 'like', "%{$q}%")->orWhere('machine_name', 'like', "%{$q}%"))
            ->orderBy('machine_code')->limit($limit)->get()->map(fn (ProdMachine $m) => self::machineRow($m))->all();
    }

    /** @return list<array<string, mixed>> proses aktif yang memakai mesin (bukan manual) untuk baris matriks sisi mesin */
    public static function machineProcessSearch(string $q, int $limit = 30): array
    {
        return ProdProcess::query()->where('activate', true)->where('is_manual', false)->where(fn ($w) => $w->where('process_code', 'like', "%{$q}%")->orWhere('process_name', 'like', "%{$q}%"))
            ->orderBy('process_name')->limit($limit)->get()->map(fn (ProdProcess $p) => self::processRow($p))->all();
    }

    /** @return list<array<string, mixed>> model katalog aktif; kolom spek ikut baris supaya memilih model mengisi form mesin */
    public static function modelSearch(string $q, int $limit = 30): array
    {
        return ProdMachineModel::query()->where('activate', true)->where(fn ($w) => $w->where('model_code', 'like', "%{$q}%")->orWhere('alias_codes', 'like', "%{$q}%")->orWhere('maker_hint', 'like', "%{$q}%"))
            ->orderBy('machine_type')->orderBy('work_length_mm')->limit($limit)->get()->map(fn (ProdMachineModel $m) => self::modelRow($m))->all();
    }

    /** @return array<string, mixed>|null */
    public static function modelFind(mixed $value): ?array
    {
        $m = ProdMachineModel::query()->find((int) $value);

        return $m ? self::modelRow($m) : null;
    }

    private static function modelRow(ProdMachineModel $m): array
    {
        $num = fn ($v) => $v === null ? '' : (string) (float) $v + 0;

        return ['id' => (int) $m->id_machine_model, 'code' => (string) $m->model_code, 'size' => $m->work_length_mm ? $m->work_length_mm.'×'.$m->work_width_mm.' mm' : '—', 'drive' => ProdMachineModel::DRIVES[$m->drive] ?? (string) $m->drive,
            'label' => $m->model_code.($m->alias_codes ? ' ('.$m->alias_codes.')' : '').' · '.($m->work_length_mm ? $m->work_length_mm.'×'.$m->work_width_mm.' mm · ' : '').(ProdMachineModel::DRIVES[$m->drive] ?? $m->drive),
            'work_length_mm' => $num($m->work_length_mm), 'work_width_mm' => $num($m->work_width_mm), 'speed_per_hour' => $num($m->speed_per_hour), 'motor_kw' => $num($m->motor_kw)];
    }

    private static function machineRow(ProdMachine $m): array
    {
        return ['id' => (int) $m->machine_id, 'code' => (string) $m->machine_code, 'name' => (string) $m->machine_name, 'label' => $m->machine_code.' — '.$m->machine_name];
    }
}
