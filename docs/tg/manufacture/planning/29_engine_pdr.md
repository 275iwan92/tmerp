# ENGINE card: PDR Engine (old scheduling board engine)

> Written 2026-10-17 (Manufacture group). FACT = code with line numbers and mockup DB (`--env=mockup`) seen this session. Source read in full: `app/Services/Scheduling/PdrEngine.php`, `CompositePriorityScorer.php`, `CrewAvailabilityChecker.php`, `BackwardSchedulingService.php`, `RateBandResolver.php`, `MachineCalendarService.php`, `SchedulingServiceProvider.php`; callers in `resources/views/pages/production/`.

- **Canonical name**: PDR Engine (Scheduling), the "old" priority-dispatching engine. **Class**: `App\Services\Scheduling\PdrEngine`. **Registry**: key `pdr`, mode `suggest`. **Module/sub-module**: the v1 page Scheduling Board (route `production.scheduling-board`), NOT the v2 PPIC Workspace.
- **Synonyms**: Composite Priority Score, backward scheduling, rate band, manual reschedule validation, "Tier1 5A".
- **Frozen status**: not frozen; superseded for planning by `23_engine_auto-plan-runner.md` and `24_engine_schedule-solver.md` (see finding 1).
- **Concept cards**: `13_card_plan-draft-and-live.md`.

## Trigger (what really calls it)
Searched `app/`, `resources/views`, `routes/`, `tests/`. FACT:
| Method | Caller | File:line |
|---|---|---|
| `validateManualReschedule` | v1 Scheduling Board: drag, resize, reassign | `resources/views/pages/production/scheduling-board.blade.php:517,545,578` |
| `backwardSchedule` | v1 board, hot job dialog | `scheduling-board.blade.php:711` |
| `buildResourceQueue` | only inside `InterruptionOptionGenerator::generateOptions` (`31_engine_interruption.md`) | `app/Services/Scheduling/InterruptionOptionGenerator.php:27` |
| `pickNext`, `backwardScheduleAndPersist` | no caller (grep finds only the definition and the registry text) | `app/Services/Scheduling/PdrEngine.php:85,112` |
| Container wiring | `SchedulingServiceProvider` | `app/Providers/SchedulingServiceProvider.php:48` |
The v1 board is registered at `routes/web.php:314` (middleware `module:PROD`). The menu entry `PROD_MPS` points to `v2:production-scheduling` (table `sys_app_menu`); the v1 route is still mapped for the v1 menu at `app/Support/Menu/MenuRoutes.php:205`. The v1 Work Order entry redirects to the board (`resources/views/pages/production/wo-entry.blade.php:914`).

## Flow (text twin)
1. `buildResourceQueue(machine, process)`: operations with `task_status = PENDING` on that machine and process name; `SchedulingRuleEngine::applyBatching` joins operations that share a rule value (`30_engine_scheduling-rules.md`); each group is scored by its first member (`CompositePriorityScorer`); groups sorted by layer, tiebreak, resource fit, FIFO; then `applySequencing` smooths inside equal priority.
2. Score layers (`CompositePriorityScorer::score`): layer 0 `HOT_JOB` (`prd_wo_operation.is_hot_job`), tiebreak = critical ratio; layer 1 `AT_RISK_CR<1`; layer 2 `NORMAL_EDD` by the operation's `target_finish`, then resource-fit penalty (1,000,000 when the proposed crew has a clash), then the WO's `confirmed_at`.
3. Critical ratio of an operation: `(target_finish - now) hours / remaining work hours` where remaining work = sum of (target_finish - target_start) of the operations of the same WO from this sequence on; no `target_finish` gives a huge value (not urgent).
4. `validateManualReschedule`: finish after start; at least min_task_duration_minutes (30); no crew clash in the new window (needs rows in `prd_crew_assignment`); `QC_HOLD` is locked; no overlap with a Maintenance block (`32_engine_maintenance-block.md`).
5. `backwardSchedule` (`BackwardSchedulingService::schedule`): from the WO due date backwards through the operations, duration = qty / rate, rate from `RateBandResolver` (history of finished operations when at least 5 data points, else 80 percent of `prod_machine.max_speed_per_hour`, else exception), time moved back over working time of `MachineCalendarService` (Monday to Saturday, `sys_holiday`; shift window from `emp_shift_roster` + `emp_shift_detail` when the machine has roster history, else 08:00 to 20:00; Maintenance blocks excluded).

## Input / Output
- **Input**: `prd_wo`, `prd_wo_operation`, `prd_crew_assignment`, `prd_scheduling_rule`, `prod_machine`, `emp_shift_roster`, `emp_shift_detail`, `sys_holiday`, `mnt_order` (through the block provider).
- **Output**: queue (collection), target dates (not saved except `backwardScheduleAndPersist`), a list of refusal reasons. The persisted variant is used by `WoGenerationService` at WO creation through `BackwardSchedulingService::scheduleAndPersist` (`20_engine_wo-generation.md`).

## Formula
Critical ratio `CR = timeLeftHours / remainingWorkHours` (`CompositePriorityScorer::criticalRatio`); layer 1 when `CR < 1`. Rate band: `rate = average(qty_good / hours)` over the last up to 200 finished operations with `DONE` or `QC_PASSED` and both actual times, only if `datapoint_count >= 5` (`RateBandResolver.php`); else `0.8 x max_speed_per_hour`. A worked example is NOT written: `ddt*` has no DONE operation (all 13 confirmed operations are ASSIGNED or RUNNING), so the history path cannot be shown.

## Edge cases
- No `target_finish`: CR is the largest float, never urgent.
- A machine that has roster history but no shift on a date is treated as closed that day (`MachineCalendarService::machineWorkingWindow` returns false).
- A MANUAL operation has no machine: its rate comes from history of manual operations of the same process name and THROWS when there are fewer than 5 data points (swallowed by `WoGenerationService`).

## Used by
Only the v1 Scheduling Board and `InterruptionOptionGenerator`; `WoGenerationService` uses `BackwardSchedulingService` (not `PdrEngine`).

## Open items
`docs/ENGINE-ATTENTION.md`: ATTENTION-ENGINE-07 mentions that `CrewAvailabilityChecker` is used only by this engine and not by the solver.

## Tests
`tests/Feature/ProductionSchedulingBoardSmokeTest.php` (7 tests on the v1 board). NOT run this session: it changes real rows (it restores them in `tearDown`) and it depends on the August 28 dummy seed (`WO2600250` is `CANCELLED` today).

## Relations
- **source**: `30_engine_scheduling-rules.md`, `32_engine_maintenance-block.md`, `19_card_assign-crew.md`, `../ppic/12_card_shift-and-calendar.md`.
- **used_by**: `31_engine_interruption.md`, `20_engine_wo-generation.md` (backward schedule at creation), card `13_card_plan-draft-and-live.md`.
- **flows**: v1 board -> validation / queue -> (officer) -> `target_start` / `target_finish`.
- **frozen_zone**: none.

## Findings (recorded, not fixed)
1. **This engine is not used by the screens the menu opens.** Planning > PPIC Workspace is v2 and uses `AutoPlanRunner`; `PdrEngine` is reached only through the v1 board (a route that is still registered) and through the hot-job dialog there. The registry text "used by Scheduling Board, Work Order Worklist" does not say that this board is v1. FACT.
2. **Two hot-job flags.** The old scorer reads `prd_wo_operation.is_hot_job`; the v2 planner writes and reads `prd_wo.is_hot_job` (`PlanChangeService.php:42`). Both columns are 0 for all rows in `ddt*` (65 WOs, 386 operations). A hot job set in v2 is invisible to this engine. FACT.
3. **Different rate source**: the old engine falls back to `prod_machine.max_speed_per_hour x 0.8`; v2 uses the three layers `prod_process_machine` / `prod_process` / `prod_machine.speed_target_per_hour` (`21_engine_wo-planning-enricher.md`). Two numbers can exist for one machine. FACT.
4. **`pickNext` has no caller**, and `prd_crew_assignment` (the only data for the crew check) has 0 rows and no writer in `app/` (only deletes). FACT (grep, query).
5. **Rules table empty**: `prd_scheduling_rule` has 0 rows, so batching and sequencing do nothing. FACT (query).
6. **Hard-coded Indonesian labels** in the option generator (`InterruptionOptionGenerator.php:35,47`) are not translated with `t()`. FACT.

## Already exists? Searched where, result
`concept:find "schedule"`: no earlier card. Searched code: ranking exists in two independent places (this pair and the solver). The old notes (`docs/design-notes/ppic/06_ppic-tech-engines.md`) say `CompositePriorityScorer` and `PdrEngine` are "extended" with tier, ageing and setup-aware scores; the extension was built inside the solver instead (`24_engine_schedule-solver.md`), and these two classes were not extended. FROM DOCUMENT, contradicted by code.
