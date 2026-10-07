<?php

namespace Tests\Feature;

use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpRosterSchedule;
use App\Models\Master\EmpRosterScheduleDepartment;
use App\Models\Master\EmpShift;
use App\Models\Master\EmpShiftRoster;
use App\Models\Master\EmpShiftRosterBatch;
use App\Models\Master\OrgDepartment;
use App\Support\V2\Data\ShiftRoster;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// Shift Roster v2 (C6): CRUD jadwal + rotasi antar minggu (swap / cycle / copy). Minggu uji +3000 / +3001 dari hari ini; jadwal uji bernama
// "ZZ Test Schedule"; semuanya dibersihkan lagi.
class ShiftRosterRotateTest extends TestCase
{
    private const WEEK = 3000;

    private ShiftRoster $roster;

    private int $employeeId;

    /** @var list<EmpShift> */
    private array $shifts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->roster = ShiftRoster::for(null, null, null, self::WEEK);
        $this->employeeId = EmpEmployee::query()->where('activate', true)->firstOrFail()->employee_id;
        $this->shifts = $this->roster->shifts()->filter(fn (EmpShift $s) => $s->activeDayCount() > 0)->take(2)->values()->all();
        count($this->shifts) === 2 || $this->markTestSkipped('Need two shifts with working hours.');
    }

    protected function tearDown(): void
    {
        foreach (EmpRosterSchedule::where('schedule_name', 'like', 'ZZ Test Schedule%')->pluck('schedule_id') as $id) {
            EmpShiftRoster::where('schedule_id', $id)->delete();
            EmpShiftRosterBatch::where('schedule_id', $id)->delete();
            EmpRosterScheduleDepartment::where('schedule_id', $id)->delete();
            EmpRosterSchedule::where('schedule_id', $id)->delete();
        }
        if (isset($this->roster)) {
            $from = $this->roster->weekStart()->toDateString();
            $to = $this->roster->weekStart()->copy()->addWeeks(2)->addDays(6)->toDateString();
            EmpShiftRoster::where('schedule_id', $this->roster->scheduleId)->whereBetween('roster_date', [$from, $to])->delete();
            EmpShiftRosterBatch::where('schedule_id', $this->roster->scheduleId)->whereBetween('date_from', [$from, $to])->delete();
        }
        parent::tearDown();
    }

    private function place(int $shiftIndex = 0): void
    {
        $this->roster->save([$this->employeeId => (string) $this->shifts[$shiftIndex]->shift_id], 'test');
    }

    private function targetShiftId(): ?int
    {
        $to = $this->roster->weekStart()->copy()->addWeek();

        return EmpShiftRoster::where('schedule_id', $this->roster->scheduleId)->where('employee_id', $this->employeeId)
            ->whereBetween('roster_date', [$to->toDateString(), $to->copy()->addDays(6)->toDateString()])->value('shift_id');
    }

    public function test_copy_swap_and_cycle_map_shifts_to_the_target_week(): void
    {
        $from = $this->roster->weekStart();
        $to = $from->copy()->addWeek();

        $this->place(0);
        $this->assertSame(['placed' => 1, 'skipped' => 0], $this->roster->rotate($from, $to, 'copy', null, null, 'all', false, 'test'));
        $this->assertSame($this->shifts[0]->shift_id, (int) $this->targetShiftId());

        $this->assertSame(1, $this->roster->rotate($from, $to, 'swap', $this->shifts[0]->shift_id, $this->shifts[1]->shift_id, 'all', true, 'test')['placed']);
        $this->assertSame($this->shifts[1]->shift_id, (int) $this->targetShiftId());

        $live = $this->roster->shifts()->values();
        $next = $live[($live->search(fn ($s) => $s->shift_id === $this->shifts[0]->shift_id) + 1) % $live->count()];
        $this->roster->rotate($from, $to, 'cycle', null, null, 'all', true, 'test');
        $this->assertSame($next->shift_id, (int) $this->targetShiftId());
    }

    public function test_employees_already_placed_in_the_target_week_are_skipped_unless_overwritten(): void
    {
        $from = $this->roster->weekStart();
        $to = $from->copy()->addWeek();
        $this->place(0);
        $this->roster->rotate($from, $to, 'copy', null, null, 'all', false, 'test');

        $this->assertSame(['placed' => 0, 'skipped' => 1], $this->roster->rotate($from, $to, 'copy', null, null, 'all', false, 'test'));
        $preview = $this->roster->rotatePreview($from, $to, 'all');
        $this->assertTrue($preview['occupied']);
        $this->assertSame(1, $preview['busy']);
        $this->assertSame(1, $this->roster->rotate($from, $to, 'copy', null, null, 'all', true, 'test')['placed']);
    }

    public function test_rotate_rejects_same_week_empty_source_locked_target_and_bad_swap(): void
    {
        $from = $this->roster->weekStart();
        $to = $from->copy()->addWeek();
        $cases = [
            fn () => $this->roster->rotate($from, $from, 'copy', null, null, 'all', false, 'test'),
            fn () => $this->roster->rotate($from, $to, 'copy', null, null, 'all', false, 'test'),
        ];
        foreach ($cases as $case) {
            try {
                $case();
                $this->fail('Expected a ValidationException.');
            } catch (ValidationException) {
                $this->assertNull($this->targetShiftId());
            }
        }

        $this->place(0);
        $this->expectException(ValidationException::class);
        $this->roster->rotate($from, $to, 'swap', $this->shifts[0]->shift_id, $this->shifts[0]->shift_id, 'all', false, 'test');
    }

    public function test_locked_target_week_rejects_rotation(): void
    {
        $from = $this->roster->weekStart();
        $target = ShiftRoster::for($this->roster->scheduleId, null, $this->roster->workCenterId, self::WEEK + 1);
        $this->place(0);
        $target->save([$this->employeeId => (string) $this->shifts[1]->shift_id], 'test');
        $target->confirm('test');

        $this->expectException(ValidationException::class);
        $this->roster->rotate($from, $from->copy()->addWeek(), 'copy', null, null, 'all', true, 'test');
    }

    public function test_schedule_create_update_and_delete(): void
    {
        $dept = OrgDepartment::where('activate', true)->firstOrFail()->department_id;
        $s = ShiftRoster::saveSchedule(null, 'ZZ Test Schedule', [$dept], 'test');
        $this->assertSame([$dept], array_map('intval', $s->departmentIds()));

        $s = ShiftRoster::saveSchedule($s->schedule_id, 'ZZ Test Schedule 2', [$dept], 'test');
        $this->assertSame('ZZ Test Schedule 2', $s->fresh()->schedule_name);

        $this->assertSame(0, ShiftRoster::deleteSchedule($s->schedule_id));
        $this->assertNull(EmpRosterSchedule::find($s->schedule_id));
    }

    public function test_schedule_needs_a_name_and_a_department(): void
    {
        $this->expectException(ValidationException::class);
        ShiftRoster::saveSchedule(null, 'ZZ Test Schedule', [], 'test');
    }
}
