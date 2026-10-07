<?php

namespace Tests\Feature;

use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpRosterSchedule;
use App\Models\Master\EmpRosterScheduleDepartment;
use App\Models\Master\EmpShift;
use App\Models\Master\EmpShiftRoster;
use App\Models\Master\EmpShiftRosterBatch;
use App\Models\Master\OrgDepartment;
use App\Models\Sys\SecUser;
use App\Support\V2\Data\ShiftRoster;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Tests\TestCase;

// Shift Roster v2 lewat rute HTTP (save JSON, publish, unlock, rotasi, jadwal). Minggu uji +3000 / +3001; jadwal uji "ZZ Test Schedule"; dibersihkan lagi.
class ShiftRosterRoutesTest extends TestCase
{
    private const WEEK = 3000;

    private ShiftRoster $roster;

    private SecUser $admin;

    private EmpShift $shift;

    private int $employeeId;

    private array $scope;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->admin = SecUser::where('is_admin', true)->firstOrFail();
        $this->roster = ShiftRoster::for(null, null, null, self::WEEK);
        $this->shift = $this->roster->shifts()->first(fn (EmpShift $s) => $s->activeDayCount() > 0) ?? $this->markTestSkipped('No shift with working hours.');
        $this->employeeId = EmpEmployee::query()->where('activate', true)->firstOrFail()->employee_id;
        $this->scope = ['sched' => $this->roster->scheduleId, 'wc' => $this->roster->workCenterId, 'w' => self::WEEK];
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

    private function save(array $assign)
    {
        return $this->actingAs($this->admin)->postJson(route('v2.roster.save', $this->scope), ['assign' => $assign]);
    }

    public function test_board_screen_renders_with_week_and_panels(): void
    {
        foreach (['', '&panel=rotate', '&panel=schedule-new', '&panel=schedule-edit'] as $panel) {
            $this->actingAs($this->admin)->get(route('v2.screen', 'hrm-roster').'?'.http_build_query($this->scope).$panel)->assertOk()->assertSee('data-dl-roster', false);
        }
    }

    public function test_save_then_publish_locks_the_week_and_unlock_reopens(): void
    {
        $this->save([$this->employeeId => (string) $this->shift->shift_id])->assertOk()->assertJsonPath('tone', fn ($t) => in_array($t, ['success', 'warn'], true));
        $this->assertCount(1, $this->roster->placements());

        $this->actingAs($this->admin)->post(route('v2.roster.confirm'), $this->scope)->assertRedirect()->assertSessionHas('v2_flash');
        $this->assertTrue($this->roster->isLocked());

        $this->save([])->assertStatus(422);
        $this->assertCount(1, $this->roster->placements());

        $this->actingAs($this->admin)->post(route('v2.roster.unlock'), $this->scope)->assertRedirect();
        $this->assertFalse($this->roster->isLocked());
        $this->save([])->assertOk();
        $this->assertSame([], $this->roster->placements());
    }

    public function test_publish_without_placements_is_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('v2.roster.confirm'), $this->scope)->assertSessionHasErrors('roster');
        $this->assertNull($this->roster->batch());
    }

    public function test_rotate_route_copies_to_the_target_week_and_moves_the_screen_there(): void
    {
        $this->save([$this->employeeId => (string) $this->shift->shift_id])->assertOk();
        $from = $this->roster->weekStart();
        $to = $from->copy()->addWeek();

        $res = $this->actingAs($this->admin)->post(route('v2.roster.rotate'), $this->scope + ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'mode' => 'copy', 'scope' => 'all']);
        $res->assertRedirect()->assertSessionHas('v2_flash');
        $this->assertStringContainsString('w='.(self::WEEK + 1), $res->headers->get('Location'));
        $this->assertTrue(EmpShiftRoster::where('schedule_id', $this->roster->scheduleId)->where('employee_id', $this->employeeId)
            ->whereBetween('roster_date', [$to->toDateString(), $to->copy()->addDays(6)->toDateString()])->exists());

        $this->actingAs($this->admin)->post(route('v2.roster.rotate'), $this->scope + ['from' => $from->toDateString(), 'to' => $from->toDateString(), 'mode' => 'copy', 'scope' => 'all'])
            ->assertSessionHasErrors('rotate');
    }

    public function test_schedule_routes_create_update_delete(): void
    {
        $dept = OrgDepartment::where('activate', true)->firstOrFail()->department_id;
        $this->actingAs($this->admin)->post(route('v2.roster.schedule.save'), ['schedule_name' => 'ZZ Test Schedule', 'departments' => [$dept]])->assertRedirect();
        $schedule = EmpRosterSchedule::where('schedule_name', 'ZZ Test Schedule')->firstOrFail();

        $this->actingAs($this->admin)->post(route('v2.roster.schedule.save'), ['schedule_id' => $schedule->schedule_id, 'schedule_name' => 'ZZ Test Schedule 2', 'departments' => [$dept]])->assertRedirect();
        $this->assertSame('ZZ Test Schedule 2', $schedule->fresh()->schedule_name);

        $this->actingAs($this->admin)->post(route('v2.roster.schedule.save'), ['schedule_name' => 'ZZ Test Schedule 3', 'departments' => []])->assertSessionHasErrors();
        $this->actingAs($this->admin)->post(route('v2.roster.schedule.delete', $schedule->schedule_id))->assertRedirect();
        $this->assertNull(EmpRosterSchedule::find($schedule->schedule_id));
    }

    public function test_shift_visibility_toggle_is_remembered_per_user(): void
    {
        $id = $this->shift->shift_id;
        $before = ShiftRoster::hiddenShifts($this->admin->login);
        $this->actingAs($this->admin)->post(route('v2.roster.shift.toggle', $id), $this->scope)->assertRedirect();
        $this->assertNotSame(in_array($id, $before, true), in_array($id, ShiftRoster::hiddenShifts($this->admin->login), true));
        $this->actingAs($this->admin)->post(route('v2.roster.shift.toggle', $id), $this->scope)->assertRedirect();
        $this->assertSame($before, ShiftRoster::hiddenShifts($this->admin->login));
    }
}
