<?php

/*
 * REMARK
 * Tujuan       : Aturan Assign Crew: mesin Offset saja, crew per mesin (satu per shift produksi aktif di Master Shift, nama = nickname + nama shift), periode tanpa tabrakan, anggota maks 10 dari karyawan produksi.
 * Sumber hulu  : prod_machine (nickname, SBU), emp_shift (Master Shift), emp_employee + org_position + org_department (karyawan produksi),
 *                prod_machine_crew, prod_machine_crew_period, prod_machine_crew_member.
 * Input        : machine_id / crew id, shift_id, tanggal dari-sampai, daftar [employee_id, role].
 * Output       : Baris tersimpan ; ValidationException berisi pesan (bukan mesin Offset, nickname kosong, crew ke-5, tanggal kosong / terbalik, tabrakan periode,
 *                lebih dari 10, orang dua kali, peran kosong, bukan karyawan produksi, orang yang sama bentrok di crew lain pada shift dan periode yang sama).
 * Dipakai oleh : Rute v2.crew.*, layar v2 crew-form, grid Assign Crew dan kolom Crew di PPIC Workspace (namesOn).
 * Status       : Aktif (dummy). "Karyawan produksi" = PRODUCTION_DEPARTMENTS (keputusan Jo 14-Okt-2026: PLAT / PRA-CETAK masuk; QC, LEM MANUAL dan kupas manual = kerja manusia tanpa mesin, belum punya crew). Grup tetap menyusul.
 */

namespace App\Services\Scheduling\Crew;

use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpShift;
use App\Models\Master\OrgDivision;
use App\Models\Master\ProdMachine;
use App\Models\Master\ProdMachineCrew;
use App\Models\Master\ProdMachineCrewMember;
use App\Models\Master\ProdMachineCrewPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MachineCrewService
{
    public const MAX_MEMBERS = 10;

    public const ROLES = ['Main operator', 'Co-operator', 'Helper', 'Kabag'];

    // Karyawan aktif yang posisinya di departemen produksi SBU Offset (bukan Digital / Indostry / Legacy belum dipetakan); PRA-CETAK = operator CTP (PLAT).
    public const PRODUCTION_DEPARTMENTS = ['PRA-CETAK', 'PRESS', 'CETAK', 'PLONG-DIECUT', 'LAMINASI', 'LAMINATING', 'POTONG', 'FOLDER-GLUE', 'KUPAS', 'SHRINK', 'MANUAL', 'HARIAN-LEPAS'];

    /** Mesin yang boleh punya crew: aktif, mesin proses, SBU Offset Printing (satu aturan untuk grid, form, dan simpan). */
    public static function assignableMachines(): Builder
    {
        return ProdMachine::query()->where('activate', true)->where('is_process_machine', true)
            ->whereIn('division_id', OrgDivision::query()->where('division_code', 'OFFSET')->select('division_id'));
    }

    /** Shift aktif milik departemen produksi dari Master Shift (emp_shift): Reguler dulu, lalu menurut kode (SH1, SH2, ...). */
    public static function productionShifts(): Collection
    {
        return EmpShift::query()
            ->whereIn('department_id', DB::connection('tm2mst')->table('org_department')->whereIn('department_code', self::PRODUCTION_DEPARTMENTS)->select('department_id'))
            ->where('activate', true)->get()
            ->sortBy(fn ($s) => ($s->shift_code === 'REG' ? '0' : '1').$s->shift_code)->values();
    }

    /** [shift_id => nama shift] pilihan shift crew (Master Shift produksi). */
    public static function shifts(): array
    {
        return self::productionShifts()->mapWithKeys(fn ($s) => [$s->shift_id => $s->shift_name])->all();
    }

    /** Crew aktif pertama mesin ini pada shift itu (satu crew = satu mesin + satu shift pada layar sederhana); null = belum ada. */
    public static function crewFor(ProdMachine $machine, int $shiftId): ?ProdMachineCrew
    {
        return ProdMachineCrew::query()->where('machine_id', $machine->machine_id)->where('shift_id', $shiftId)->where('activate', true)->orderBy('crew_index')->first();
    }

    /** Nama anggota crew pada shift itu yang periodenya mencakup $date; $crews = crew aktif satu mesin (dengan periods.members.employee). */
    public static function namesOn(iterable $crews, int $shiftId, string $date): Collection
    {
        return collect($crews)->filter(fn ($c) => $c->shift_id === $shiftId)
            ->flatMap(fn ($c) => $c->periods->first(fn ($p) => $p->covers($date))?->members ?? collect())
            ->map(fn ($m) => $m->employee?->full_name)->filter()->unique()->values();
    }

    /** [employee_id => "kode · nama"] karyawan produksi aktif. */
    public static function employees(): array
    {
        return EmpEmployee::query()->select('emp_employee.employee_id', 'emp_employee.employee_code', 'emp_employee.full_name')
            ->join('org_position as p', 'p.position_id', '=', 'emp_employee.position_id')
            ->join('org_department as d', 'd.department_id', '=', 'p.department_id')
            ->where('emp_employee.activate', true)->whereIn('d.department_code', self::PRODUCTION_DEPARTMENTS)
            ->orderBy('emp_employee.full_name')->get()
            ->mapWithKeys(fn ($e) => [$e->employee_id => trim($e->employee_code.' · '.$e->full_name, ' ·')])->all();
    }

    private static function checkShift(?int $shiftId): void
    {
        if ($shiftId !== null && ! array_key_exists($shiftId, self::shifts())) {
            self::fail(t('This shift is not a production shift.'));
        }
    }

    public static function addCrew(ProdMachine $machine, ?int $shiftId): ProdMachineCrew
    {
        if (! self::assignableMachines()->whereKey($machine->machine_id)->exists()) {
            self::fail(t('Only Offset Printing machines can be assigned a crew.'));
        }
        if (trim((string) $machine->nickname) === '') {
            self::fail(t('Fill in the machine nickname first.'));
        }
        // Satu crew per shift produksi: batas = jumlah shift aktif di Master Shift (ikut bertambah / berkurang bersama master).
        if (ProdMachineCrew::query()->where('machine_id', $machine->machine_id)->where('activate', true)->count() >= count(self::shifts())) {
            self::fail(t('This machine already has a crew for every production shift (the maximum). Deactivate one first.'));
        }
        self::checkShift($shiftId);
        $login = auth()->user()?->login;

        // Indeks = tertinggi yang pernah dipakai mesin ini + 1, termasuk crew nonaktif (tidak dipakai ulang).
        return ProdMachineCrew::query()->create([
            'machine_id' => $machine->machine_id,
            'crew_index' => (int) ProdMachineCrew::query()->where('machine_id', $machine->machine_id)->max('crew_index') + 1,
            'shift_id' => $shiftId, 'activate' => true, 'created_by' => $login, 'updated_by' => $login,
        ]);
    }

    /** Delete pada layar = crew dinonaktifkan, tidak dihapus; indeksnya tidak dipakai ulang. */
    public static function deactivate(ProdMachineCrew $crew): void
    {
        $crew->update(['activate' => false, 'updated_by' => auth()->user()?->login]);
    }

    /**
     * Simpan semua kotak crew satu mesin sekaligus (semua atau tidak sama sekali). $boxes = [['shift_id' => int, 'members' => [[employee_id, role], ...]], ...].
     * Kotak berisi: crew mesin + shift itu dibuat / diperbarui pada periode yang berlaku hari ini (atau yang terakhir) dengan tanggal $from-$to.
     * Kotak kosong: crew shift itu dinonaktifkan (bila ada). Satu shift hanya boleh dipakai satu kotak.
     */
    public static function saveMachine(ProdMachine $machine, ?string $from, ?string $to, array $boxes): void
    {
        $shiftIds = array_map(fn ($b) => (int) ($b['shift_id'] ?? 0), $boxes);
        if (count($shiftIds) !== count(array_unique($shiftIds))) {
            self::fail(t('Each shift can be used once for a machine.'));
        }

        DB::connection('tm2mst')->transaction(function () use ($machine, $from, $to, $boxes, $shiftIds) {
            $today = now()->toDateString();
            foreach ($boxes as $i => $box) {
                $members = array_filter((array) ($box['members'] ?? []), fn ($m) => (int) ($m['employee_id'] ?? 0) > 0);
                $crew = self::crewFor($machine, $shiftIds[$i]);
                if (! $members) {
                    $crew && self::deactivate($crew);

                    continue;
                }
                $crew ??= self::addCrew($machine, $shiftIds[$i]);
                $period = $crew->periods->first(fn ($p) => $p->covers($today)) ?? $crew->periods->last();
                self::savePeriod($crew, $period?->id_crew_period, $from, $to, $members);
            }
        });
    }

    /**
     * Simpan satu periode crew (baru bila $periodId null). $members = [[employee_id, role], ...]; baris kosong diabaikan.
     * Rumus periode berlaku pada tanggal d: valid_from <= d AND (valid_to IS NULL OR valid_to >= d).
     */
    public static function savePeriod(ProdMachineCrew $crew, ?int $periodId, ?string $from, ?string $to, array $members): ProdMachineCrewPeriod
    {
        $from = $from ?: null;
        $to = $to ?: null;
        if (! $from) {
            self::fail(t('Fill in the start date of the period.'));
        }
        if ($to && $to < $from) {
            self::fail(t('The end date cannot be before the start date.'));
        }

        $rows = [];
        foreach ($members as $m) {
            $id = (int) ($m['employee_id'] ?? 0);
            if ($id <= 0) {
                continue;   // baris kosong
            }
            if (isset($rows[$id])) {
                self::fail(t('The same person is listed twice in this crew.'));
            }
            if (! in_array($m['role'] ?? null, self::ROLES, true)) {
                self::fail(t('Choose a role for every person.'));
            }
            $rows[$id] = $m['role'];
        }
        if (count($rows) > self::MAX_MEMBERS) {
            self::fail(t('A crew has at most 10 people.'));
        }
        $unknown = array_diff(array_keys($rows), array_keys(self::employees()));
        if ($unknown) {
            self::fail(t('Only production employees can be assigned to a crew.'));
        }

        $clash = $crew->periods()->when($periodId, fn ($q) => $q->where('id_crew_period', '!=', $periodId))->get()
            ->first(fn ($p) => $from <= ($p->valid_to?->toDateString() ?? '9999-12-31') && ($to ?? '9999-12-31') >= $p->valid_from->toDateString());
        if ($clash) {
            self::fail(tf('Overlaps with the period :from to :to of :crew. Change the dates, or close the old period first.', [
                'from' => $clash->valid_from->toDateString(), 'to' => $clash->valid_to?->toDateString() ?? t('open'), 'crew' => $crew->name(),
            ]));
        }

        // Satu orang boleh masuk lebih dari satu crew, tetapi tidak di shift yang sama pada periode yang bertabrakan.
        $busy = ProdMachineCrewMember::query()->with('employee', 'period.crew')->whereIn('employee_id', array_keys($rows))
            ->whereHas('period', fn ($p) => $p->where('valid_from', '<=', $to ?? '9999-12-31')->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $from))
                ->whereHas('crew', fn ($c) => $c->where('activate', true)->where('shift_id', $crew->shift_id)->where('id_machine_crew', '!=', $crew->id_machine_crew)))
            ->first();
        if ($busy) {
            self::fail(tf(':person is already in crew :crew on the same shift for :from to :to.', [
                'person' => $busy->employee->full_name, 'crew' => $busy->period->crew->name(),
                'from' => $busy->period->valid_from->toDateString(), 'to' => $busy->period->valid_to?->toDateString() ?? t('open'),
            ]));
        }

        return DB::connection('tm2mst')->transaction(function () use ($crew, $periodId, $from, $to, $rows) {
            $login = auth()->user()?->login;
            $period = $periodId ? $crew->periods()->findOrFail($periodId) : new ProdMachineCrewPeriod(['id_machine_crew' => $crew->id_machine_crew, 'created_by' => $login]);
            $period->fill(['valid_from' => $from, 'valid_to' => $to, 'updated_by' => $login])->save();
            $period->members()->delete();
            foreach ($rows as $employeeId => $role) {
                $period->members()->create(['employee_id' => $employeeId, 'role' => $role]);
            }

            return $period;
        });
    }

    private static function fail(string $message): never
    {
        throw ValidationException::withMessages(['crew' => $message]);
    }
}
