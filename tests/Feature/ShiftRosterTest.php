<?php

namespace Tests\Feature;

use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpShift;
use App\Models\Master\EmpShiftRoster;
use App\Models\Master\EmpShiftRosterBatch;
use App\Support\V2\Data\ShiftRoster;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// Shift Roster v2 (C6): penempatan mingguan + siklus Draft / Confirmed. Memakai minggu jauh di masa depan (+3000 minggu) supaya tidak menyentuh
// roster nyata; semua baris emp_shift_roster dan batch minggu itu dibersihkan lagi.
class ShiftRosterTest extends TestCase
{
    private const WEEK = 3000;

    private ShiftRoster $roster;

    private int $employeeId;

    private EmpShift $shift;

    protected function setUp(): void
    {
        parent::setUp();
        $this->roster = ShiftRoster::for(null, null, null, self::WEEK);
        $this->employeeId = EmpEmployee::query()->where('activate', true)->firstOrFail()->employee_id;
        $this->shift = $this->roster->shifts()->first(fn (EmpShift $s) => $s->activeDayCount() > 0) ?? $this->markTestSkipped('No shift with working hours.');
    }

    protected function tearDown(): void
    {
        if (isset($this->roster)) {
            $range = [$this->roster->weekStart()->toDateString(), $this->roster->weekEnd()->toDateString()];
            EmpShiftRoster::where('schedule_id', $this->roster->scheduleId)->whereBetween('roster_date', $range)->delete();
            EmpShiftRosterBatch::where('schedule_id', $this->roster->scheduleId)->where('date_from', $range[0])->delete();
        }
        parent::tearDown();
    }

    public function test_save_places_one_row_per_active_day_and_creates_a_draft(): void
    {
        $d = $this->roster->save([$this->employeeId => (string) $this->shift->shift_id], 'test');

        $this->assertSame(1, $d['placed']);
        $rows = EmpShiftRoster::where('employee_id', $this->employeeId)->where('schedule_id', $this->roster->scheduleId)
            ->whereBetween('roster_date', [$this->roster->weekStart()->toDateString(), $this->roster->weekEnd()->toDateString()])->get();
        $this->assertCount($this->shift->activeDayCount(), $rows);
        $this->assertSame('DRAFT', $this->roster->batch()->status);
        $this->assertSame($this->roster->batch()->batch_id, (int) $rows->first()->batch_id);
        $this->assertSame((string) $this->shift->shift_id, $this->roster->board('test')['assign'][$this->employeeId] ?? null);
    }

    public function test_saving_again_without_changes_places_nothing_and_empty_board_removes(): void
    {
        $assign = [$this->employeeId => (string) $this->shift->shift_id];
        $this->roster->save($assign, 'test');
        $this->assertSame(0, $this->roster->save($assign, 'test')['placed']);

        $this->assertSame(1, $this->roster->save([], 'test')['removed']);
        $this->assertSame([], $this->roster->placements());
    }

    public function test_confirm_locks_and_unlock_reopens(): void
    {
        $this->roster->save([$this->employeeId => (string) $this->shift->shift_id], 'test');
        $this->roster->confirm('test');
        $this->assertTrue($this->roster->isLocked());

        try {
            $this->roster->save([], 'test');
            $this->fail('A locked week must reject changes.');
        } catch (ValidationException) {
            $this->assertCount(1, $this->roster->placements());
        }

        $this->roster->unlock('test');
        $this->assertFalse($this->roster->isLocked());
        $this->assertSame(1, $this->roster->save([], 'test')['removed']);
    }

    public function test_confirm_needs_placements_and_unknown_shift_is_rejected(): void
    {
        try {
            $this->roster->confirm('test');
            $this->fail('Nothing to publish.');
        } catch (ValidationException) {
            $this->assertNull($this->roster->batch());
        }
        $this->expectException(ValidationException::class);
        $this->roster->save([$this->employeeId => '99999999'], 'test');
    }
}
