<?php

declare(strict_types=1);

namespace Tests\Unit\Ppic;

use App\Services\Scheduling\Plan\PlanOp;
use App\Services\Scheduling\Plan\PlanWo;
use App\Services\Scheduling\Plan\ScheduleSolver;
use App\Services\Scheduling\Plan\SetupTimeEngine;
use App\Services\Scheduling\WorkingCalendar;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

// Pure tests (no database): the planning engines take plain data.
final class ScheduleSolverTest extends TestCase
{
    private const RULES = [
        'base_minutes' => 15,
        'color_add_spot' => ['minutes' => 20, 'waste' => 30],
        'color_full_wash' => ['minutes' => 38, 'waste' => 60],
        'ink_type' => ['minutes' => 55, 'waste' => 80],
        'substrate' => ['minutes' => 15, 'waste' => 20, 'gsm_gap' => 20],
        'size' => ['minutes' => 12, 'waste' => 15],
        'die' => ['minutes' => 15, 'waste' => 0],
        'plate' => ['minutes' => 10, 'waste' => 0],
    ];

    // Thursday 8 Oct 2026 09:00
    private function now(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-10-08 09:00');
    }

    private function cal(array $machines = []): WorkingCalendar
    {
        return new WorkingCalendar($this->now(), $machines ?: [1 => ['shifts' => 2, 'hours' => 8], 2 => ['shifts' => 2, 'hours' => 8], 3 => ['shifts' => 1, 'hours' => 8]]);
    }

    private function solver(): ScheduleSolver
    {
        return new ScheduleSolver(new SetupTimeEngine, self::RULES);
    }

    private function op(int $id, int $wo, int $seq, array $cands, float $qty = 4500, array $facts = [], string $stage = 'PRESS', int $wait = 0, ?CarbonImmutable $minStart = null, string $gate = 'READY'): PlanOp
    {
        return new PlanOp($id, $wo, $seq, 'CETAK', $stage, $qty, $cands, $facts, $wait, $minStart, $gate);
    }

    private function wo(int $id, array $ops, ?string $due = '2026-10-20', bool $hot = false, int $tier = 2): PlanWo
    {
        return new PlanWo($id, 'WO'.$id, $due ? CarbonImmutable::parse($due) : null, $hot, $tier, 1, $ops);
    }

    public function test_calendar_skips_sunday_and_converts_both_ways(): void
    {
        $cal = $this->cal([1 => ['shifts' => 1, 'hours' => 8]]);   // 07:00 to 15:00, Sunday off
        $fri = CarbonImmutable::parse('2026-10-09 14:00');
        $w = $cal->workMinute($fri, 1);
        $this->assertSame('2026-10-10 09:00', $cal->timeAt($w + 180, 1)->format('Y-m-d H:i'));   // 60 min left Friday, 120 min into Saturday
        $sat = CarbonImmutable::parse('2026-10-10 14:00');
        $afterSat = $cal->workMinute($sat, 1) + 120;                // 1 h left on Saturday, then Sunday is closed
        $this->assertSame('2026-10-12 08:00', $cal->timeAt($afterSat, 1)->format('Y-m-d H:i'));
    }

    public function test_hot_job_is_scheduled_before_a_normal_job_on_the_same_machine(): void
    {
        $normal = $this->wo(1, [$this->op(10, 1, 10, [1 => ['rank' => 1, 'rate' => 4500, 'setup_base' => 15]])], '2026-10-12');
        $hot = $this->wo(2, [$this->op(20, 2, 10, [1 => ['rank' => 1, 'rate' => 4500, 'setup_base' => 15]])], '2026-10-30', true);
        $r = $this->solver()->solve([$normal, $hot], $this->cal(), $this->now());

        $this->assertLessThan($r->placements[10]->start, $r->placements[20]->start);
        $this->assertSame(0, $r->priority[2]['layer']);
    }

    public function test_critical_ratio_below_one_beats_due_date_order(): void
    {
        // WO 1 due earlier but plenty of slack; WO 2 due later but needs 15 working hours but has 14 before a close due date (one shift machine).
        $a = $this->wo(1, [$this->op(10, 1, 10, [1 => ['rank' => 1, 'rate' => 4500, 'setup_base' => 15]], 4500)], '2026-10-09 12:00');
        $b = $this->wo(2, [$this->op(20, 2, 10, [1 => ['rank' => 1, 'rate' => 600, 'setup_base' => 15]], 9000)], '2026-10-09 17:00');
        $r = $this->solver()->solve([$a, $b], $this->cal([1 => ['shifts' => 1, 'hours' => 8]]), $this->now());

        $this->assertSame(1, $r->priority[2]['layer']);
        $this->assertLessThan($r->placements[10]->start, $r->placements[20]->start);
    }

    public function test_operations_are_chained_and_wait_after_is_respected(): void
    {
        $ops = [
            $this->op(10, 1, 10, [1 => ['rank' => 1, 'rate' => 4500, 'setup_base' => 15]], 4500, [], 'PRESS', 120),
            $this->op(11, 1, 20, [3 => ['rank' => 1, 'rate' => 3500, 'setup_base' => 15]], 4500, [], 'FINISHING'),
        ];
        $r = $this->solver()->solve([$this->wo(1, $ops)], $this->cal(), $this->now());

        $first = $r->placements[10];
        $second = $r->placements[11];
        $this->assertGreaterThanOrEqual($first->finish->addMinutes(120)->getTimestamp(), $second->start->getTimestamp());
    }

    public function test_the_faster_eligible_machine_is_chosen(): void
    {
        $slowFast = [1 => ['rank' => 1, 'rate' => 2000, 'setup_base' => 15], 2 => ['rank' => 2, 'rate' => 6000, 'setup_base' => 15]];
        $r = $this->solver()->solve([$this->wo(1, [$this->op(10, 1, 10, $slowFast, 9000)])], $this->cal(), $this->now());

        $this->assertSame(2, $r->placements[10]->machineId);
    }

    public function test_second_job_waits_for_a_busy_machine_and_pays_changeover_setup(): void
    {
        $c = [1 => ['rank' => 1, 'rate' => 4500, 'setup_base' => 15]];
        $f1 = ['colours' => 4, 'spots' => [], 'gsm' => 250, 'sheet_l' => 790, 'sheet_w' => 545, 'die' => 'D1'];
        $f2 = ['colours' => 4, 'spots' => ['P485'], 'gsm' => 350, 'sheet_l' => 790, 'sheet_w' => 545, 'die' => 'D1'];
        $a = $this->wo(1, [$this->op(10, 1, 10, $c, 4500, $f1)], '2026-10-12');
        $b = $this->wo(2, [$this->op(20, 2, 10, $c, 4500, $f2)], '2026-10-13');
        $r = $this->solver()->solve([$a, $b], $this->cal([1 => ['shifts' => 2, 'hours' => 8]]), $this->now());

        $this->assertGreaterThanOrEqual($r->placements[10]->finish->getTimestamp(), $r->placements[20]->start->getTimestamp());
        // base 15 + add spot 20 + substrate change 15 = 50, plus tooling for the same die = 0
        $this->assertSame(50, $r->placements[20]->setup['total']);
    }

    public function test_gate_wait_delays_the_start_and_is_reported(): void
    {
        $arrive = CarbonImmutable::parse('2026-10-09 08:00');
        $op = $this->op(10, 1, 10, [1 => ['rank' => 1, 'rate' => 4500, 'setup_base' => 15]], 4500, [], 'PRESS', 0, $arrive, 'WAIT');
        $r = $this->solver()->solve([$this->wo(1, [$op])], $this->cal(), $this->now());

        $this->assertGreaterThanOrEqual($arrive->getTimestamp(), $r->placements[10]->start->getTimestamp());
        $this->assertCount(1, $r->exceptionsOf('WAITING') ?: [1]);
    }

    public function test_blocked_gate_and_missing_machine_stop_the_chain_with_exceptions(): void
    {
        $blocked = $this->op(10, 1, 10, [1 => ['rank' => 1, 'rate' => 4500, 'setup_base' => 15]], 4500, [], 'PRESS', 0, null, 'BLOCK');
        $next = $this->op(11, 1, 20, [1 => ['rank' => 1, 'rate' => 4500, 'setup_base' => 15]]);
        $none = $this->op(20, 2, 10, []);
        $r = $this->solver()->solve([$this->wo(1, [$blocked, $next]), $this->wo(2, [$none])], $this->cal(), $this->now());

        $this->assertArrayNotHasKey(10, $r->placements);
        $this->assertArrayNotHasKey(11, $r->placements);
        $this->assertNotEmpty($r->exceptionsOf('BLOCKED'));
        $this->assertNotEmpty($r->exceptionsOf('CHAIN_BLOCKED'));
        $this->assertNotEmpty($r->exceptionsOf('NO_MACHINE'));
    }

    public function test_late_forecast_is_reported(): void
    {
        $op = $this->op(10, 1, 10, [3 => ['rank' => 1, 'rate' => 500, 'setup_base' => 15]], 9000);   // 18 h on a one-shift machine
        $r = $this->solver()->solve([$this->wo(1, [$op], '2026-10-09 12:00')], $this->cal(), $this->now());

        $late = $r->exceptionsOf('LATE_RISK');
        $this->assertCount(1, $late);
        $this->assertGreaterThan(0, $late[0]['data']['hours_late']);
    }

    public function test_confirmed_locked_operation_is_never_moved_and_blocks_its_slot(): void
    {
        $now = $this->now();
        $fixed = new PlanOp(99, 9, 10, 'CETAK', 'PRESS', 4500, [1 => ['rank' => 1, 'rate' => 4500, 'setup_base' => 15]], [], 0, null, 'READY', null,
            ['machine' => 1, 'start' => $now, 'finish' => $now->addHours(3)]);
        $new = $this->wo(2, [$this->op(20, 2, 10, [1 => ['rank' => 1, 'rate' => 4500, 'setup_base' => 15]])]);
        $r = $this->solver()->solve([$this->wo(9, [$fixed]), $new], $this->cal([1 => ['shifts' => 2, 'hours' => 8]]), $now);

        $this->assertArrayNotHasKey(99, $r->placements);
        $this->assertGreaterThanOrEqual($now->addHours(3)->getTimestamp(), $r->placements[20]->start->getTimestamp());
    }

    public function test_similar_urgency_jobs_are_grouped_by_setup_distance(): void
    {
        $c = [1 => ['rank' => 1, 'rate' => 4500, 'setup_base' => 15]];
        $thin = ['colours' => 4, 'gsm' => 250, 'sheet_l' => 790, 'sheet_w' => 545];
        $thick = ['colours' => 4, 'gsm' => 400, 'sheet_l' => 790, 'sheet_w' => 545];
        // Same due day: thin, thick, thin. Setup-aware order puts both thin jobs together.
        $w1 = $this->wo(1, [$this->op(10, 1, 10, $c, 1000, $thin)], '2026-10-14 10:00');
        $w2 = $this->wo(2, [$this->op(20, 2, 10, $c, 1000, $thick)], '2026-10-14 11:00');
        $w3 = $this->wo(3, [$this->op(30, 3, 10, $c, 1000, $thin)], '2026-10-14 12:00');
        $r = $this->solver()->solve([$w1, $w2, $w3], $this->cal([1 => ['shifts' => 2, 'hours' => 8]]), $this->now());

        $order = collect($r->placements)->sortBy(fn ($p) => $p->start->getTimestamp())->keys()->all();
        $this->assertSame([10, 30, 20], $order);
    }

    public function test_setup_engine_parts(): void
    {
        $s = new SetupTimeEngine;
        $prev = ['colours' => 4, 'spots' => [], 'ink_type' => 'CONVENTIONAL', 'gsm' => 300, 'sheet_l' => 790, 'sheet_w' => 545, 'die' => 'A'];
        $next = ['colours' => 4, 'spots' => ['P485'], 'ink_type' => 'UV', 'gsm' => 300, 'sheet_l' => 720, 'sheet_w' => 520, 'die' => 'B', 'tool_min' => 12];

        $r = $s->between($prev, $next, self::RULES, 'PRESS');
        // base 15 + add spot 20 + UV 55 + size 12 + die 12
        $this->assertSame(114, $r['total']);
        $this->assertSame(['colour', 'ink_type', 'size', 'die'], array_column($r['parts'], 'kind'));
        $this->assertSame(15, $s->between($prev, $next, self::RULES, 'QC')['total']);   // stage without changeover pays base only
    }

    public function test_machine_is_chosen_by_clock_time_not_by_work_minutes_of_its_own_calendar(): void
    {
        // Machine 3 works one shift a day, machine 1 two. A 20 hour job ends days later on machine 3 although machine 3 has the better rank.
        $c = [3 => ['rank' => 1, 'rate' => 1000, 'setup_base' => 15], 1 => ['rank' => 2, 'rate' => 1000, 'setup_base' => 15]];
        $r = $this->solver()->solve([$this->wo(1, [$this->op(10, 1, 10, $c, 20000)])], $this->cal(), $this->now());

        $this->assertSame(1, $r->placements[10]->machineId);
    }

    public function test_jobs_on_the_same_die_are_put_together_inside_the_due_tolerance(): void
    {
        $c = [1 => ['rank' => 1, 'rate' => 4500, 'setup_base' => 15]];
        $x = ['stage' => 'DIE', 'die' => 'DIE-X', 'gsm' => 300];
        $y = ['stage' => 'DIE', 'die' => 'DIE-Y', 'gsm' => 300];
        $a = $this->wo(1, [$this->op(10, 1, 10, $c, 1000, $x, 'DIE')], '2026-10-14 10:00');
        $b = $this->wo(2, [$this->op(20, 2, 10, $c, 1000, $y, 'DIE')], '2026-10-14 12:00');
        $d = $this->wo(3, [$this->op(30, 3, 10, $c, 1000, $x, 'DIE')], '2026-10-15 20:00');   // 34 h after A: outside 8 h window, inside 48 h
        $r = $this->solver()->solve([$a, $b, $d], $this->cal([1 => ['shifts' => 2, 'hours' => 8]]), $this->now());

        $order = collect($r->placements)->sortBy(fn ($p) => $p->start->getTimestamp())->keys()->all();
        $this->assertSame([10, 30, 20], $order);
        // The die is mounted once: the second X job pays no die setup.
        $this->assertSame(15, $r->placements[30]->setup['total']);
    }

    public function test_same_die_outside_the_tolerance_is_not_pulled_forward(): void
    {
        $c = [1 => ['rank' => 1, 'rate' => 4500, 'setup_base' => 15]];
        $x = ['stage' => 'DIE', 'die' => 'DIE-X'];
        $y = ['stage' => 'DIE', 'die' => 'DIE-Y'];
        $a = $this->wo(1, [$this->op(10, 1, 10, $c, 1000, $x, 'DIE')], '2026-10-14 10:00');
        $b = $this->wo(2, [$this->op(20, 2, 10, $c, 1000, $y, 'DIE')], '2026-10-14 12:00');
        $d = $this->wo(3, [$this->op(30, 3, 10, $c, 1000, $x, 'DIE')], '2026-10-19 12:00');   // five days later
        $r = $this->solver()->solve([$a, $b, $d], $this->cal([1 => ['shifts' => 2, 'hours' => 8]]), $this->now());

        $order = collect($r->placements)->sortBy(fn ($p) => $p->start->getTimestamp())->keys()->all();
        $this->assertSame([10, 20, 30], $order);
    }

    public function test_hot_job_is_never_held_back_by_grouping(): void
    {
        $c = [1 => ['rank' => 1, 'rate' => 4500, 'setup_base' => 15]];
        $a = $this->wo(1, [$this->op(10, 1, 10, $c, 1000, ['stage' => 'DIE', 'die' => 'DIE-X'], 'DIE')], '2026-10-14 10:00');
        $hot = $this->wo(2, [$this->op(20, 2, 10, $c, 1000, ['stage' => 'DIE', 'die' => 'DIE-Y'], 'DIE')], '2026-10-16 10:00', true);
        $b = $this->wo(3, [$this->op(30, 3, 10, $c, 1000, ['stage' => 'DIE', 'die' => 'DIE-X'], 'DIE')], '2026-10-14 11:00');
        $r = $this->solver()->solve([$a, $hot, $b], $this->cal([1 => ['shifts' => 2, 'hours' => 8]]), $this->now());

        $first = collect($r->placements)->sortBy(fn ($p) => $p->start->getTimestamp())->keys()->first();
        $this->assertSame(20, $first);
    }

    public function test_ink_order_light_to_dark_washes_lighter_than_dark_to_light(): void
    {
        $s = new SetupTimeEngine;
        $rules = self::RULES + ['color_order_factor' => 0.7];
        $light = ['inks' => ['YELLOW', 'CYAN'], 'colours' => 2];
        $dark = ['inks' => ['BLACK', 'MAGENTA'], 'colours' => 2];

        $up = $s->between($light, $dark, $rules, 'PRESS');
        $down = $s->between($dark, $light, $rules, 'PRESS');
        $this->assertSame(15 + 27, $up['total']);      // 38 x 0.7 rounded
        $this->assertSame(15 + 38, $down['total']);
        $this->assertSame(15, $s->between($light, ['inks' => ['CYAN', 'YELLOW'], 'colours' => 2], $rules, 'PRESS')['total']);   // same set: no wash
    }

    public function test_locked_operation_on_a_machine_that_is_down_is_proposed_elsewhere_with_a_reason(): void
    {
        $now = $this->now();
        $moved = new PlanOp(10, 1, 10, 'CETAK', 'PRESS', 4500, [2 => ['rank' => 1, 'rate' => 4500, 'setup_base' => 15]], [], 0, null, 'READY', null, null, 'MACHINE', 0, true);
        $r = $this->solver()->solve([$this->wo(1, [$moved])], $this->cal(), $now);

        $this->assertSame(2, $r->placements[10]->machineId);
        $this->assertCount(1, $r->exceptionsOf('MOVED_FROM_DOWN'));
    }

    public function test_second_job_with_the_same_die_follows_the_first_one_on_its_machine_when_the_due_date_holds(): void
    {
        $c = [1 => ['rank' => 1, 'rate' => 4500, 'setup_base' => 15], 2 => ['rank' => 2, 'rate' => 4500, 'setup_base' => 15]];
        $x = ['stage' => 'DIE', 'die' => 'DIE-X'];
        $a = $this->wo(1, [$this->op(10, 1, 10, $c, 1000, $x, 'DIE')], '2026-10-14 10:00');
        $b = $this->wo(2, [$this->op(20, 2, 10, $c, 1000, $x, 'DIE')], '2026-10-14 11:00');
        $r = $this->solver()->solve([$a, $b], $this->cal([1 => ['shifts' => 2, 'hours' => 8], 2 => ['shifts' => 2, 'hours' => 8]]), $this->now());

        $this->assertSame($r->placements[10]->machineId, $r->placements[20]->machineId);
        $this->assertSame(15, $r->placements[20]->setup['total']);   // no die mounted again
    }

    public function test_grouping_on_the_same_die_is_given_up_when_it_would_make_the_job_late(): void
    {
        $x = ['stage' => 'DIE', 'die' => 'DIE-X'];
        $both = [1 => ['rank' => 1, 'rate' => 4500, 'setup_base' => 15], 2 => ['rank' => 2, 'rate' => 4500, 'setup_base' => 15]];
        // The first job (hot, only machine 1) ends 10:15. Following it on machine 1 would finish the second job about 10:43,
        // after its due date 10:30, while machine 2 finishes it before 10:00: the die must not pull it onto machine 1.
        $a = $this->wo(1, [$this->op(10, 1, 10, [1 => $both[1]], 4500, $x, 'DIE')], '2026-10-08 12:00', true);
        $b = $this->wo(2, [$this->op(20, 2, 10, $both, 1000, $x, 'DIE')], '2026-10-08 10:30');
        $r = $this->solver()->solve([$a, $b], $this->cal([1 => ['shifts' => 2, 'hours' => 8], 2 => ['shifts' => 2, 'hours' => 8]]), $this->now());

        $this->assertSame(2, $r->placements[20]->machineId);
        $this->assertSame([], $r->exceptionsOf('LATE_RISK'));
    }

    public function test_a_work_order_taken_out_of_die_grouping_is_not_pulled_next_to_the_others(): void
    {
        $c = [1 => ['rank' => 1, 'rate' => 4500, 'setup_base' => 15]];
        $x = ['stage' => 'DIE', 'die' => 'DIE-X'];
        $y = ['stage' => 'DIE', 'die' => 'DIE-Y'];
        $a = $this->wo(1, [$this->op(10, 1, 10, $c, 1000, $x, 'DIE')], '2026-10-14 10:00');
        $b = $this->wo(2, [$this->op(20, 2, 10, $c, 1000, $y, 'DIE')], '2026-10-14 12:00');
        $d = new PlanWo(3, 'WO3', CarbonImmutable::parse('2026-10-15 20:00'), false, 2, 1, [$this->op(30, 3, 10, $c, 1000, $x, 'DIE')], '', false, true);
        $r = $this->solver()->solve([$a, $b, $d], $this->cal([1 => ['shifts' => 2, 'hours' => 8]]), $this->now());

        $order = collect($r->placements)->sortBy(fn ($p) => $p->start->getTimestamp())->keys()->all();
        $this->assertSame([10, 20, 30], $order);
    }
}
