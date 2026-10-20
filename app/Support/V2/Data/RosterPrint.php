<?php

declare(strict_types=1);

/* REMARK
 * Tujuan       : Isi cetakan Roster Shift (tiga mode: roster mingguan, master definisi shift, formasi mingguan) untuk objek DevLib x-dl::print-sheet; angka, urutan, kode formulir dan tanda tangan sama dengan cetakan v1.
 * Sumber hulu  : emp_roster_schedule(+_department), emp_shift_roster, emp_shift(+details), emp_work_center, emp_employee (tm2mst), preferensi sembunyi shift per user (tm2sys.sys_grid_preference), RosterFormationService::matrix; pindahan layar v1 pages::hrm.shift-roster-print.
 * Input        : query mode (roster | shift | formasi), schedule (id), week (selisih minggu dari minggu ini).
 * Output       : array props x-dl::print-sheet (title, code, info, sections, signatures, footer).
 * Dipakai oleh : rute v2.hrm.roster.print (layar v2 print-roster), tombol Print di layar hrm-roster.
 * Status       : Aktif (T9 pindahan v1, 2026-10-20).
 */

namespace App\Support\V2\Data;

use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpRosterSchedule;
use App\Models\Master\EmpRosterScheduleDepartment;
use App\Models\Master\EmpShift;
use App\Models\Master\EmpShiftRoster;
use App\Models\Master\EmpWorkCenter;
use App\Models\Master\OrgDepartment;
use App\Models\Sys\SysGridPreference;
use App\Services\Hrm\RosterFormationService;
use Carbon\Carbon;
use Devlib\Locale;
use Illuminate\Support\Facades\Auth;

final class RosterPrint
{
    public const MODES = ['roster', 'shift', 'formasi'];

    public readonly string $mode;

    public readonly ?int $scheduleId;

    public readonly Carbon $weekStart;

    public readonly Carbon $weekEnd;

    public function __construct(array $q)
    {
        $this->mode = in_array($q['mode'] ?? null, self::MODES, true) ? (string) $q['mode'] : 'roster';
        $this->scheduleId = ! empty($q['schedule']) ? (int) $q['schedule'] : EmpRosterSchedule::where('activate', true)->orderBy('schedule_id')->value('schedule_id');
        $this->weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY)->addWeeks((int) ($q['week'] ?? 0));
        $this->weekEnd = $this->weekStart->copy()->addDays(6);
    }

    /** Shift yang disembunyikan user pencetak di papan roster; cetakan mengikuti papan. */
    private function hiddenShifts(): array
    {
        return SysGridPreference::where('user_login', Auth::user()->login)->where('grid_key', 'hrm.shift-roster.hidden-shifts')->value('visible_columns') ?? [];
    }

    private function shifts()
    {
        $hidden = collect($this->hiddenShifts())->map(fn ($v) => (int) $v)->all();

        return EmpShift::with('details')->where('activate', true)->when($hidden, fn ($q) => $q->whereNotIn('shift_id', $hidden))->orderBy('shift_code')->orderBy('shift_name')->get();
    }

    /** work_center_id => shift_id => [EmpEmployee]. */
    private function placements(): array
    {
        $rows = EmpShiftRoster::query()->where('schedule_id', $this->scheduleId)->whereBetween('roster_date', [$this->weekStart->toDateString(), $this->weekEnd->toDateString()])
            ->get()->unique(fn ($row) => $row->employee_id.'|'.$row->work_center_id.'|'.$row->shift_id);
        $employees = EmpEmployee::with('position')->whereIn('employee_id', $rows->pluck('employee_id')->unique()->all() ?: [0])->get()->keyBy('employee_id');
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->work_center_id][(int) $row->shift_id][] = $employees[$row->employee_id] ?? null;
        }

        return $out;
    }

    private function week(): int
    {
        return (int) $this->weekStart->isoWeek();
    }

    public function title(): string
    {
        return match ($this->mode) {
            'shift' => 'Shift Definition Master',
            'formasi' => tf('Formation Week :n', ['n' => $this->week()]),
            default => 'Weekly Shift Roster',
        };
    }

    public function code(): string
    {
        return match ($this->mode) {
            'shift' => 'FRM-HR-SHF-01 Rev.0',
            'formasi' => 'FRM-HR-FRM-01 Rev.0',
            default => 'FRM-HR-ROS-01 Rev.0',
        };
    }

    /** @return list<string> baris di bawah judul */
    public function info(): array
    {
        if ($this->mode === 'shift') {
            return [tf('Effective from :date', ['date' => Locale::date(now())])];
        }
        $schedule = $this->scheduleId ? EmpRosterSchedule::find($this->scheduleId) : null;

        return array_values(array_filter([(string) ($schedule?->schedule_name ?? ''),
            tf('Week :n · :from – :to', ['n' => $this->week(), 'from' => Locale::date($this->weekStart), 'to' => Locale::date($this->weekEnd)])]));
    }

    public function fileTitle(): string
    {
        return $this->mode === 'formasi' ? t($this->title()) : t($this->title()).' '.$this->week();
    }

    private function shiftSection(int $no): array
    {
        $dayLabels = collect(EmpShift::DAY_ORDER)->map(fn ($d) => EmpShift::DAY_LABELS[$d])->all();
        $columns = [['key' => 'shift', 'label' => 'Shift', 'width' => 42]];
        foreach ($dayLabels as $i => $label) {
            $columns[] = ['key' => 'd'.$i, 'label' => $label, 'align' => 'center', 'mono' => true];
        }
        $columns[] = ['key' => 'week', 'label' => 'Hours/wk', 'align' => 'center', 'mono' => true, 'width' => 20];

        $rows = $this->shifts()->map(function (EmpShift $shift) {
            $row = ['shift' => ['text' => (string) $shift->shift_name, 'bold' => true, 'sub' => (string) $shift->shift_code]];
            foreach ($shift->dayPattern() as $i => $day) {
                $row['d'.$i] = $day['on'] ? $day['in']."\n".$day['out'].(EmpShift::isNight($day) ? ' +1' : '') : '—';
            }
            $row['week'] = EmpShift::formatMinutes($shift->weekMinutes());

            return $row;
        })->all();
        $hidden = count($this->hiddenShifts());

        return ['no' => $no, 'title' => 'Shift Hours in Effect', 'blocks' => [['type' => 'table', 'columns' => $columns, 'rows' => $rows,
            'note' => t('The day columns show clock-in (top) and clock-out (bottom). "+1" means clock-out falls on the next day. Hours per week are net of break time.')
                .($hidden ? ' '.tf(':n shifts are hidden from this document according to the printer\'s display settings.', ['n' => $hidden]) : '')]]];
    }

    private function rosterParties(): array
    {
        $schedule = $this->scheduleId ? EmpRosterSchedule::find($this->scheduleId) : null;
        $ids = EmpRosterScheduleDepartment::where('schedule_id', $this->scheduleId)->pluck('department_id');
        $departments = OrgDepartment::whereIn('department_id', $ids)->orderBy('department_name')->pluck('department_name')->implode(', ');
        $placements = $this->placements();
        $assigned = collect($placements)->flatten(1)->flatten()->filter()->unique('employee_id')->count();

        return ['type' => 'parties', 'items' => [
            ['label' => 'Schedule coverage', 'lines' => [(string) ($schedule?->schedule_name ?? '—'), $departments ?: '—']],
            ['label' => 'Summary', 'lines' => [tf('Total scheduled: :n people', ['n' => $assigned]), tf('Work units filled: :n', ['n' => count($placements)]), tf('Active shifts: :n', ['n' => $this->shifts()->count()])]],
        ]];
    }

    private function placementSection(int $no): array
    {
        $placements = $this->placements();
        $centers = EmpWorkCenter::whereIn('work_center_id', array_keys($placements) ?: [0])->orderBy('sort_order')->orderBy('work_center_name')->get();
        $blocks = [];
        foreach ($centers as $center) {
            $rows = [];
            foreach ($this->shifts() as $shift) {
                $members = collect($placements[$center->work_center_id][$shift->shift_id] ?? [])->filter();
                if ($members->isEmpty()) {
                    continue;
                }
                $rows[] = ['shift' => ['text' => (string) $shift->shift_name, 'bold' => true, 'sub' => implode(' · ', $shift->summaryLines())], 'count' => (string) $members->count(),
                    'names' => $members->map(fn ($e) => $e->full_name.' ('.$e->employee_code.' · '.($e->position?->position_name ?? '—').')')->implode(', ')];
            }
            $blocks[] = ['type' => 'table', 'heading' => (string) $center->work_center_name, 'columns' => [['key' => 'shift', 'label' => 'Shift & hour pattern', 'width' => 52],
                ['key' => 'count', 'label' => 'Qty', 'align' => 'center', 'mono' => true, 'width' => 12], ['key' => 'names', 'label' => 'Employee names']], 'rows' => $rows];
        }

        return ['no' => $no, 'title' => 'Employee Placement per Work Unit', 'blocks' => $blocks ?: [['type' => 'text', 'text' => t('This schedule has no placements in that week.')]]];
    }

    private function formationSection(): array
    {
        $formation = $this->scheduleId ? app(RosterFormationService::class)->matrix($this->scheduleId, $this->weekStart, false) : [];
        $days = iterator_to_array($this->weekStart->copy()->daysUntil($this->weekEnd->copy()->subDay()));
        $columns = [['key' => 'no', 'label' => '#', 'align' => 'center', 'width' => 9], ['key' => 'position', 'label' => 'Position', 'width' => 32], ['key' => 'hours', 'label' => 'Working hours', 'width' => 30]];
        foreach ($days as $i => $d) {
            $columns[] = ['key' => 'c'.$i, 'label' => $d->format('d/m/Y'), 'align' => 'center'];
        }
        $rows = [];
        $n = 0;
        foreach ($formation as $row) {
            $r = ['hours' => (string) ($row['shift']?->shift_name ?? '—')];
            if ($row['first']) {
                $r['no'] = ['text' => (string) ++$n, 'rowspan' => $row['span'], 'class' => 'is-center'];
                $r['position'] = ['text' => (string) $row['wc']->work_center_name, 'rowspan' => $row['span'], 'bold' => true];
            } else {
                $r['no'] = false;
                $r['position'] = false;
            }
            foreach ($days as $i => $d) {
                $r['c'.$i] = collect($row['cells'][$d->toDateString()] ?? [])->map(fn ($id) => $row['people'][$id]['label'])->implode(', ');
            }
            $rows[] = $r;
        }

        return ['no' => 1, 'title' => 'Formation per Position, Working Hours & Date', 'blocks' => [['type' => 'table', 'columns' => $columns, 'rows' => $rows]]];
    }

    /** @return list<array<string, mixed>> */
    public function sections(): array
    {
        return match ($this->mode) {
            'shift' => [$this->shiftSection(1)],
            'formasi' => [$this->formationSection()],
            default => [['title' => '', 'blocks' => [$this->rosterParties()]], $this->shiftSection(1), $this->placementSection(2)],
        };
    }

    /** @return list<array{role: string, name: string}> */
    public function signatures(): array
    {
        return [['role' => 'Prepared By', 'name' => 'PPIC / HR'], ['role' => 'Checked By', 'name' => 'Head of Production'], ['role' => 'Approved By', 'name' => 'General Manager']];
    }

    public function footer(): string
    {
        return t('Printed on').' '.Locale::stamp(now()).' · '.t('Working hours follow the Shift Master version in effect; changes outside this schedule must go through a written revision.');
    }

    /** Props x-dl::print-sheet. */
    public function sheet(): array
    {
        return ['title' => $this->title(), 'code' => $this->code(), 'info' => $this->info(), 'sections' => $this->sections(), 'signatures' => $this->signatures(), 'footer' => $this->footer()];
    }
}
