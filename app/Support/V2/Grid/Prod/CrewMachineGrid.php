<?php

/*
 * REMARK
 * Tujuan       : Assign Crew (grid): daftar mesin proses SBU Offset Printing; klik baris atau sel shift membuka Form Assign Crew mesin (dan shift) itu.
 * Sumber hulu  : MachineCrewService::assignableMachines() (tm2mst.prod_machine, SBU = org_division kode OFFSET, hanya filter WHERE, tidak ditampilkan; SBU Digital Printing tidak tampil), prod_machine_crew + period + member.
 * Input        : Tidak ada isian di grid; nickname dan crew diisi di form.
 * Output       : Baris: nickname (baris 1, besar; kosong = "Fill in nickname"), nama detail mesin (baris 2, selalu ada; kode mesin tidak tampil), jenis, lalu satu kolom per shift produksi di Master Shift (judul = nama shift, shift baru muncul sendiri) berisi SEMUA nama karyawan crew yang berlaku hari ini, satu nama per baris (kolom list DevLib, lebar sedang tetap).
 * Dipakai oleh : Menu Manufacture > Planning > Assign Crew (v2:crew); membuka v2:crew-form?machine={id}.
 * Status       : Aktif (dummy).
 */

namespace App\Support\V2\Grid\Prod;

use App\Models\Master\ProdMachine;
use App\Models\Master\ProdMachineCrew;
use App\Services\Scheduling\Crew\MachineCrewService;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CrewMachineGrid extends DbGrid
{
    /** @var array<int, Collection> crew aktif per mesin (dengan shift dan anggota), dimuat sekali per halaman */
    private array $crews = [];

    public function slug(): string
    {
        return 'crew';
    }

    public function title(): string
    {
        return 'Assign Crew';
    }

    public function module(): string
    {
        return 'PROD';
    }

    public function moduleLabel(): string
    {
        return 'Production';
    }

    public function family(): string
    {
        return self::TRANSACTIONAL;
    }

    public function group(): string
    {
        return 'Planning';
    }

    public function icon(): string
    {
        return 'fa-solid fa-people-group';
    }

    public function search(): string
    {
        return 'Search code, machine, nickname...';
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'crew-form').'?machine={id}';
    }

    protected function query(): Builder
    {
        return MachineCrewService::assignableMachines()->orderBy('machine_code');
    }

    protected function models(): array
    {
        return [ProdMachine::class, ProdMachineCrew::class];
    }

    protected function prefetch(Collection $items): void
    {
        $this->crews = ProdMachineCrew::query()->with('shift', 'periods.members.employee')->where('activate', true)
            ->whereIn('machine_id', $items->pluck('machine_id'))->get()->groupBy('machine_id')->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Machine', 'type' => 'identity', 'code' => 'detail', 'avatar' => false, 'lead' => true],
            ['key' => 'type', 'label' => 'Type'],
            // Satu kolom per shift produksi di Master Shift (judul = nama shift); shift baru di master muncul sendiri.
            ...MachineCrewService::productionShifts()->map(fn ($s) => ['key' => 'shift_'.$s->shift_id, 'label' => $s->shift_name, 'list' => true, 'url' => route('v2.screen', 'crew-form').'?machine={id}&shift_id='.$s->shift_id])->all(),
        ];
    }

    protected function row(Model $m): array
    {
        $crews = collect($this->crews[$m->machine_id] ?? []);
        $today = now()->toDateString();
        $nick = trim((string) $m->nickname);
        $row = ['name' => $nick !== '' ? $nick : t('Fill in nickname'), 'detail' => $m->machine_name, 'type' => $m->machine_type];
        foreach (MachineCrewService::productionShifts() as $shift) {
            $names = $crews->filter(fn ($c) => $c->shift_id === $shift->shift_id)
                ->flatMap(fn ($c) => $c->periods->first(fn ($p) => $p->covers($today))?->members ?? collect())
                ->map(fn ($mem) => $mem->employee?->full_name)->filter()->unique()->values();
            $row['shift_'.$shift->shift_id] = $names->implode('
');
        }

        return $row;
    }
}
