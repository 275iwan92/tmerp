<?php

/*
 * REMARK
 * Tujuan       : Data form Assign Crew satu mesin: kotak per shift produksi aktif (isi dari input terakhir bila simpan gagal, kalau tidak dari crew dan periode yang berlaku hari ini).
 * Sumber hulu  : MachineCrewService (shifts, crewFor, employees), prod_machine (nickname), prod_machine_crew + period + member.
 * Input        : ProdMachine (mesin Offset yang boleh punya crew); old() dan session v2_flash / errors dari simpan terakhir.
 * Output       : Array untuk x-mp::production.crew-form.
 * Dipakai oleh : resources/views/v2/screens/crew-form.blade.php. Hanya membaca database.
 * Status       : Aktif (dummy).
 */

namespace App\Support\V2\Data;

use App\Models\Master\ProdMachine;
use App\Services\Scheduling\Crew\MachineCrewService as Crews;
use App\Support\V2\Src;

class CrewForm
{
    public static function desk(ProdMachine $machine): array
    {
        $today = now()->toDateString();
        $shifts = Crews::shifts();
        $sent = old('crews');
        $boxes = [];
        $period = null;
        foreach (array_keys($shifts) as $i => $shiftId) {
            $crew = Crews::crewFor($machine, $shiftId)?->load('periods.members');
            $p = $crew?->periods->first(fn ($x) => $x->covers($today)) ?? $crew?->periods->last();
            $period ??= $p;
            $rows = $sent !== null
                ? collect($sent[$i]['members'] ?? [])->filter(fn ($m) => ! empty($m['employee_id']))->map(fn ($m) => ['id' => (string) $m['employee_id'], 'role' => $m['role'] ?? Crews::ROLES[1]])->values()->all()
                : ($p ? $p->members->map(fn ($m) => ['id' => (string) $m->employee_id, 'role' => $m->role])->all() : []);
            $boxes[] = ['shift' => (int) ($sent[$i]['shift_id'] ?? $shiftId), 'rows' => $rows, 'exists' => (bool) $crew];
        }
        $nick = trim((string) $machine->nickname);

        return [
            'title' => $machine->machine_code.' · '.$machine->machine_name,
            'status' => collect($boxes)->contains('exists', true) ? 'active' : 'new',
            'action' => route('v2.crew.save'),
            'back' => route('v2.screen', 'crew'),
            'hidden' => ['machine' => $machine->machine_id],
            'from' => old('valid_from', $period?->valid_from->toDateString() ?? $today),
            'to' => old('valid_to', $period?->valid_to?->toDateString()),
            'source' => Src::of('tm2mst.prod_machine_crew', 'tm2mst.prod_machine_crew_period', 'tm2mst.prod_machine_crew_member'),
            'nick' => $nick !== '' ? $nick : t('Fill in nickname'),
            'shifts' => $shifts,
            'employees' => Crews::employees(),
            'roles' => Crews::ROLES,
            'max' => Crews::MAX_MEMBERS,
            'boxes' => $boxes,
            'flash' => session('v2_flash'),
            'error' => ($errors = session('errors')) ? $errors->first() : null,
        ];
    }
}
