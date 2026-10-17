# ENGINE card: Schedule Solver (finite capacity)

> Written 2026-10-17 (Manufacture group). FACT = code with line numbers, tests I ran, and a scratch run of the pure classes (no database) seen this session. Source read in full: `app/Services/Scheduling/Plan/ScheduleSolver.php`, `PlanResult.php`, `Placement.php`, `PlanOp.php`, `PlanWo.php`, `app/Services/Scheduling/WorkingCalendar.php`.

- **Canonical name**: Schedule Solver. **Class**: `App\Services\Scheduling\Plan\ScheduleSolver`. **Registry**: key `ppic-schedule-solver`, mode `calc`. **Module/sub-module**: Manufacture > Planning (no screen of its own).
- **Synonyms**: solver, planner core, serial schedule generation, "Draft maker".
- **Frozen status**: not frozen; it is the heart of the PPIC Workspace. It is PURE: it reads nothing from the database and writes nothing (docblock `ScheduleSolver.php:10-18`).
- **Concept cards**: `13_card_plan-draft-and-live.md`.

## Trigger (called by another engine)
Searched callers (`grep ScheduleSolver`): FACT.
| Caller | File:line |
|---|---|
| Every auto-plan run | `app/Services/Scheduling/Plan/AutoPlanRunner.php:35-36` (through `solver()` at `:127-131`) |
| Impact preview of a drag, priority simulation and "what if" (dry run, nothing saved) | `app/Services/Scheduling/Plan/PlanChangeService.php:151,460` |
| Tests | `tests/Unit/Ppic/ScheduleSolverTest.php` |

## Flow (text twin)
1. **Fixed placements first**: operations that are running, finished, confirmed and locked, or placed by an officer occupy their slot on the machine and are never moved (`:56-64`).
2. **Rank the Work Orders** (`rank`, `:102-127`). For each WO: `remaining` = sum over its non-fixed operations of (fastest candidate run minutes + that candidate's setup base, else the base constant) + wait after, in hours (60 minutes when an operation has no candidate; `:240-257`); `left` = work hours between now and the due date on the calendar of the first candidate machine found (`anyMachine`, `:259-268`); `cr = left / remaining`; `slack = left - remaining`.
   - Layer 0: hot job. Layer 1: priority "Move up" or `cr < 1`. Layer 2: everything else.
   - Layer 0 by `cr` then id; layer 1 "Move up" first, then `cr`; layer 2 by due date, tier, id and then `smooth`.
3. **Smooth layer 2** (`smooth`, `:136-183`): take the first job and every job whose due date is within `PPIC_SIMILAR_WINDOW_HOURS` (8) of it, or on the same die within `PPIC_GROUP_DUE_HOURS` (48); order this group greedily by score `Wu x urgency + Ws x (1 - distance / maxDistance) + Wt x (4 - tier) / 3`, with `urgency = 1 - clamp(slack / PPIC_HORIZON_HOURS, 0, 1)` (0.5 when there is no due date) and weights 0.50 / 0.35 / 0.15 (`PPIC_WEIGHT_*`). Setup saving never moves a job out of its window.
4. **Distance between two jobs** (`distance`, `:216-237`): `min(3, abs(gsm1 - gsm2) / 20)`, plus 1.0 when the sheet size differs, plus `1.5 x (1 - shared inks / all inks)` when both jobs carry real inks (else 1.5 when the colour count or spot colours differ), plus 3.0 when the ink type differs, plus 1.0 when the die differs.
5. **Dependencies**: an assembly WO is placed after the WOs of its components; a cycle is broken in rank order (`:71-90`). An assembly cannot start before its components finish plus their wait (`placeWo`, `:274-284`).
6. **Place the operations of a WO in sequence** (`placeWo`, `:272-379`), each starting after the previous finish plus its wait, not before now and not before `minStart` (material arrival or Earliest start):
   - gate `BLOCK`: exception `BLOCKED`, and every later operation of the WO gets `CHAIN_BLOCKED`;
   - `SUBCON`: start at the earliest time, finish after `lead days` calendar days (at least 1), machine 0 (`placeOutsideMachines`, `:382-395`);
   - `MANUAL` (crew job, QC, packing): 30 minutes on the calendar of machine 0;
   - `MACHINE` without candidates: exception `NO_MACHINE`, chain blocked;
   - `MACHINE`: for each candidate find the earliest gap that fits setup + run (`bestSlot`, `:401-426`; gaps between already placed blocks are used, setup is computed against the job before the gap); choose by `[clock finish time, preference rank, machine load, machine id]` (`:332`). Machines are compared by real clock time, not by work minutes of their own calendars (test `test_machine_is_chosen_by_clock_time_not_by_work_minutes_of_its_own_calendar`).
7. **Die grouping** (`:338-352`): when the operation has a die and the WO is not taken out of grouping, a machine whose job before the gap uses the same die is preferred if it finishes within `PPIC_GROUP_SLACK_HOURS` (8) of the best machine and the due date holds (or the best machine is already late). A warning "Grouped on die ..." is stored.
8. After placing: exception `MOVED_FROM_DOWN` for a locked job proposed away from a machine that is down; `WAITING` when the gate is WAIT and the start is later than the arrival; `LATE_RISK` when the last operation finishes after the due date (hours late stored).
9. **Machine load** (`loads`, `:454-468`): busy work minutes in the next 7 days over the work minutes available, per machine, percent capped at 100.

## Input / Output
- **Input**: list of `PlanWo` (each with `PlanOp`s: candidates with rank, rate, setup base; facts; gate; wait; fixed slot), a `WorkingCalendar`, now, setup rules, weights, windows.
- **Output**: `PlanResult` with placements (machine, start, finish, setup parts, warnings), exceptions, priority per WO (layer, `cr`, slack_h), loads. Nothing is written by this class.

## Formula (one place)
- Run minutes of an operation on a machine: `max(1, ceil(qty / rate x 60))`, where `qty` is the quantity into the operation and `rate` is that candidate's target per hour (units per hour of the operation unit); no rate or rate 0 gives 60 minutes and the warning "No target per hour in the master; 60 minutes assumed" (`runMinutes`, `:429-436`; constant `FALLBACK_RUN_MINUTES`).
- Start of a placement = the later of previous finish plus wait, now, and `minStart`, moved to the first calendar gap that holds `setup + run`. Finish = start + setup + run in work minutes of that machine's calendar.
- Calendar (`WorkingCalendar`): a machine works `shifts x hours per shift` minutes per day from the day start (07:00), Monday to Saturday, minus holidays; Sunday is off (test `test_calendar_skips_sunday_and_converts_both_ways`).
- Setup minutes: see `25_engine_setup-time.md`.

### Worked example (scratch run of the pure classes, no database, 2026-10-17)
Calendar: one machine, 1 shift of 8 hours, start Monday 19-Oct-2026 09:00 (calendar 07:00 to 15:00). Machine rate 6000 per hour, setup base 15.
- Job A: 9000 units, 4 colours, 250 gsm, sheet 790 x 545, due 24-Oct. Run `ceil(9000 / 6000 x 60)` = 90; setup 15 (no job before). Placed 09:00 to 10:45. Priority: `left` = 38 working hours, `remaining` = 1.75 hours, `cr` = 21.714, slack 36.3 hours, layer 2.
- Job B: 3000 units, 350 gsm, same sheet. Run 30; setup 15 base + 15 board change (gsm gap 100 above 20) = 30. Placed 10:45 to 11:45.
- A subcontract step with lead 3 days: 19-Oct 09:00 to 22-Oct 09:00 (3 calendar days, 4320 minutes, machine 0), then a manual QC step: 22-Oct 09:00 to 09:30.
- An operation with no rate and no setup in the master: setup 15 (constant), run 60, with the warning text.
The tests `test_second_job_waits_for_a_busy_machine_and_pays_changeover_setup` (total setup 50 = 15 + 20 + 15) and `test_setup_engine_parts` (114) pass in `tests/Unit/Ppic/ScheduleSolverTest.php` (23 tests, 47 assertions, passed this session).

## Edge cases
- A job with no due date: `cr` unknown, ranked after jobs with a date inside layer 2 (sorted by `PHP_INT_MAX`).
- Operations with `kind` SUBCON, MANUAL or no machine do NOT occupy capacity (machine 0 is never `occupy()`-ed): their bars can overlap each other (ATTENTION-ENGINE-07).
- The calendar of machine 0 is the default calendar (2 shifts x 8 hours) because machine 0 is not in the machine list.
- `timeAt` throws `RuntimeException` when a work minute is beyond 400 days of calendar.

## Used by
`AutoPlanRunner`, `PlanChangeService` (impact, simulate).

## Open items
`docs/ENGINE-ATTENTION.md`: ATTENTION-ENGINE-07. Its line numbers `ScheduleSolver.php:382-395` and `WoGenerationService.php:225-239` are still the same place for the solver; the WoGeneration lines have moved (now `:233-252`).

## Tests
`tests/Unit/Ppic/ScheduleSolverTest.php` (23 tests, pure: hot job first, critical ratio, chain and wait, faster machine, busy machine and setup, blocked gate, late forecast, locked stays, grouping by setup distance and by die, ink order, machine down, assembly after components).

## Relations
- **source**: `23_engine_auto-plan-runner.md` (supplies the facts), `25_engine_setup-time.md`, `../ppic/12_card_shift-and-calendar.md`.
- **used_by**: `23_engine_auto-plan-runner.md`, `26_engine_plan-confirm.md` (confirms what the solver proposed), `13_card_plan-draft-and-live.md`.
- **flows**: facts -> rank -> place -> placements and exceptions -> stored as Draft.
- **frozen_zone**: none.
- **also_named_by**: `11_card_wo-operation-bop.md`, `14_card_gates-and-readiness.md`, `15_card_changeover-and-wait-rules.md`, `20_engine_speed-target.md`, `22_engine_gate-service.md`.

## Findings (recorded, not fixed)
1. **The solver never uses crew, operator skill or maintenance windows.** `CrewAvailabilityChecker` is used only by the old PDR engine (`29_engine_pdr.md`); the PPIC Settings screen says Master Crew is "not used as capacity by this planner yet". FACT.
2. **Manual and subcontract steps ignore real quantities**: a manual step is always 30 minutes whatever the quantity (`MANUAL_MINUTES`, `:24`); a subcontract step is `lead days` x 24 hours, on the clock, not on working days. FACT (code and scratch run).
3. **A missing rate silently becomes 60 minutes** (a warning is stored, not an exception). In the data 464 of 1357 eligible candidate rows have no rate (see `21_engine_wo-planning-enricher.md`). FACT.
4. **`left` (time to due date) uses the calendar of the FIRST candidate machine found**, not of the machines that will really run the job (`anyMachine`). With different shift settings per machine (34 machines 1 shift x 8 h, 11 machines 2 shifts x 7.8 h) the same due date gives different `cr`. FACT (code and `prod_machine` query).
5. **similar_window_hours is applied to ALL jobs as an absolute gap from the FIRST job of the group**, not a sliding window. FACT (code `:143-153`).
6. **Capacity of the plan is not tested against `prod_process.is_bottleneck`, `allow_parallel`, `min_lot_qty` or `max_lot_qty`**; those flags are stored in the process master and read by no planner (see `../ppic/11_card_process-and-routing.md`). FACT.

## Already exists? Searched where, result
`concept:find "schedule"`, `"machine"`: no solver card existed. Searched code: ranking and placement exist only here and in the old `CompositePriorityScorer` + `PdrEngine` pair (`29_engine_pdr.md`, used by the old board only). The old plan note `docs/design-notes/ppic/12_ppic-tech-engine-flow.md` step 6 promises overload options and smoothing by knife, ink set and board; the code smooths by setup distance within a window and does not produce options for overload (the Workspace adds an "Overload" line from stored loads).
