# ENGINE card: PPIC Auto-Planner (AutoPlanRunner)

> Written 2026-10-17 (Manufacture group). FACT = code with line numbers, `routes/console.php`, and mockup DB (`--env=mockup`, `tm2*` = `ddt*`) seen this session. Source read in full: `AutoPlanRunner.php`, `PlanLoader.php`, `PlanEvents.php`, `PlanChangeService.php` (header and callers), `app/Console/Commands/PpicAutoPlan.php`.

- **Canonical name**: PPIC Auto-Planner (autopilot). **Class**: `App\Services\Scheduling\Plan\AutoPlanRunner`. **Registry**: key `ppic-auto-plan`, mode `suggest`. **Module/sub-module**: Manufacture > Planning > PPIC Workspace (`v2:production-scheduling`).
- **Synonyms**: auto-plan, run, "Draft plan", re-plan, `PrdPlanRun`.
- **Frozen status**: not frozen. It writes a DRAFT only; the confirmed and locked part is never touched (docblock `AutoPlanRunner.php:14-18`). Only `26_engine_plan-confirm.md` makes a Draft real.
- **Concept cards**: `13_card_plan-draft-and-live.md`, `14_card_gates-and-readiness.md`.

## Trigger
Searched `routes/console.php`, `app/Console`, `app/Observers`, `PlanEvents`, controllers. FACT:
| Trigger | How | File:line |
|---|---|---|
| Background, nightly | `ppic:auto-plan --trigger=DAILY` at 02:00 | `routes/console.php:40` |
| Background, each shift start | `--trigger=SHIFT` at `config('ppic.day_start')` + n x `config('ppic.default_hours_per_shift')` for n below `config('ppic.default_shifts')`: 07:00 and 15:00 with the config values | `routes/console.php:41-44` |
| Condition for both | `$ppicReady` = table `prd_plan_run` exists in `tm2prd` | `routes/console.php:39` |
| Event, WO created from a delivery line | `PlanEvents::replan` | `app/Support/V2/Data/Cpdel.php:100` |
| Event, machine goes down / comes back | observer on `prod_machine_downtime` | `app/Observers/MachineDowntimeObserver.php:15,22` |
| Event, target per hour edited | `PlanEvents::replan` | `app/Http/Controllers/V2/PpicController.php:595` |
| Officer: priority, move, unpin, die group, undo priority | `$this->runner->run('EVENT', ...)` | `app/Services/Scheduling/Plan/PlanChangeService.php:46,258,431,446,516` |
| Officer: button Run now | `run('MANUAL')` | `app/Http/Controllers/V2/PpicController.php:74` |
| Officer: PROCESS and APPLY of one WO (APPLY runs the planner again before confirming) | `run('MANUAL')` | `PpicController.php:463,377` |
| Dummy seeding | `run('EVENT')` | `app/Console/Commands/PpicSeedDummy.php:105` |

## Flow (text twin)
1. `prepare` (`AutoPlanRunner.php:134-146`): for every WO with status DRAFT, SIMULATED, SCHEDULED or IN_PROGRESS (`PlanLoader::ACTIVE_WO`): run the enricher when an operation has no `process_id` or no candidate row exists (`21_engine_wo-planning-enricher.md`); ALWAYS refresh the gates (`22_engine_gate-service.md`).
2. Load facts (`PlanLoader::wos`, `PlanLoader.php:34-75`): operations of the active WOs; eligible candidate machines minus machines now in status `BREAKDOWN` or `MAINTENANCE`; process stage, `inhouse`, `is_manual`, `subcon_lead_days`; tooling minutes (`prod_tooling_asset.setup_first_min` by `dieline_ref`); inks per part (`prod_part_ink`); components of an assembly (same delivery line, component part has the assembly part as parent).
3. Classify each operation (`PlanLoader::op`, `:124-175`): `SUBCON` when the process is not in-house (or the officer added the step without a machine); `MANUAL` when the process is manual or has no machine and its stage is QC, PACKING or LOGISTIC; else `MACHINE`. Wait after a process = the longest matching keyword rule from `prod_wait_rule` over `config('ppic.wait_after')` (`:77-89`, `:141-146`). Fixed placements: finished or running operations, locked CONFIRMED operations and officer placements (`:186-204`); a confirmed locked operation on a machine that is down, or one that is blocked/late for material, is released from its slot (`wasDown`, `held`).
4. Calendar (`PlanLoader::calendar`, `:91-100`): per machine `shifts_per_day` x `hours_per_shift` from the day start `config('ppic.day_start')`, Monday to Saturday, minus active `sys_holiday` rows inside the horizon.
5. Solve: `ScheduleSolver::solve` (`24_engine_schedule-solver.md`).
6. In one transaction (`:38-50`): mark every earlier DRAFT run `DISCARDED`, create the new `prd_plan_run` (status DRAFT, trigger, counts, duration, JSON summary with exceptions, priority layer per WO, machine loads, metrics, baseline), write the Draft (`writeDraft`, `:148-160`), add decision `AUTO_PLAN`.
7. `writeDraft`: operations that an earlier ENGINE draft held go back to `plan_status = NONE` with draft columns cleared; then every placed operation gets `plan_status = DRAFT`, `planned_by = ENGINE`, `draft_machine_id/start/finish`, `draft_run_id` and the setup columns `setup_base_min`, `setup_change_min`, `setup_tool_min`, `run_minutes`. Officer placements (`planned_by = OFFICER`) are fixed inputs and stay.

## Input / Output
- **Input**: see step 2 to 4; constants `PPIC_*` through `PpicSettings` (`16_card_planning-parameters.md` in `../ppic/`).
- **Output (writes DB)**: `prd_plan_run`, `prd_wo_operation` (draft and setup columns, gates), `prd_plan_decision`, and (through prepare) `prd_wo`, `prd_wo_operation_machine`.
- **Display only**: nothing; the screens read the stored run (`Ppic::latestRun`, `Ppic::exceptions`).

## Formula (summary numbers stored in the run, `metrics`, `:60-102`)
`planned` = placements; `late` = distinct WOs with `LATE_RISK`; `attention` = number of exceptions; `setup_minutes` = sum of placement setup minutes; die_mounts and `washes` = count of setup parts of kind die and colour; die_saved_minutes = for each operation with a die whose placement mounts no die, its tool_min (else the base setup constant); `moved` = confirmed operations whose draft place differs; load_avg = mean of machine load percent over the next 7 days. `baseline` = the same counts for the schedule in force, with setup counted for unlocked operations only (`:109-125`).

### Worked example (computed from `ddt*`, read only, 2026-10-17)
Last run `1201` (2026-10-16 17:33, trigger EVENT, status DRAFT): 55 WOs, 243 operations placed, 100 exceptions (BLOCKED 12, CHAIN_BLOCKED 81, LATE_RISK 6, NO_MACHINE 1), duration 1099 ms; metrics: late 6, setup 5169 minutes, 12 die mounts, 11 washes, die saved 165 minutes, average machine load 30.1 percent, 26 machines with load, 3 at 90 percent or more (threshold constant `PPIC_BOTTLENECK_LOAD_PCT` = 90). Runs in the table by trigger: EVENT 84, MANUAL 36, DAILY 0, SHIFT 0.

## Edge cases
- Every run discards the previous DRAFT: a Draft never accumulates; the table grows by one row per event.
- A BLOCKED operation stops the rest of its WO (`CHAIN_BLOCKED`); this is why 12 blocked first operations produce 93 exceptions.
- An unlocked operation is placed again by every run and set back to `plan_status = DRAFT` while its old `target_start` and `machine_id` remain. Data: `WO2600273` (status SCHEDULED) has 3 CONFIRMED locked operations and 5 unlocked operations that are `DRAFT` with an old target. Why those 5 were not locked at APPLY is NOT verified.
- Without the table `prd_plan_run`, `PlanEvents::replan` returns silently and the schedule commands are skipped (`PlanEvents.php`, `routes/console.php:39`).

## Used by
PPIC Workspace (Gantt, Kanban, Calendar, Grid, Exceptions), Work Order form (PROCESS, Simulation), `PlanChangeService` (impact preview runs the solver without writing).

## Open items
`docs/ENGINE-ATTENTION.md`: ATTENTION-ENGINE-07 (operations without machine do not take capacity; crew conflicts are not checked; no split of a process over several machines).

## Tests
`tests/Unit/Ppic/ScheduleSolverTest.php` (the pure part), `tests/Feature/WoProcessApplyTest.php`, `tests/Feature/PlanHeldJobTest.php`, `tests/Feature/WoImpactPreviewTest.php`, `tests/Feature/WoEngineVerifyTest.php`. There is no test for the schedule commands or for `PlanEvents`.

## Relations
- **source**: `21_engine_wo-planning-enricher.md`, `22_engine_gate-service.md`, `../ppic/10_card_machine.md`, `../ppic/12_card_shift-and-calendar.md`, `../ppic/15_card_changeover-and-wait-rules.md`.
- **used_by**: `26_engine_plan-confirm.md` (turns a Draft run into the real schedule), `28_engine_sequence-suggester.md` (records the officer's order on top of this plan), cards `13_card_plan-draft-and-live.md`.
- **flows**: event or schedule -> prepare -> load -> solve -> Draft run -> officer reviews exceptions -> PROCESS / APPLY.
- **frozen_zone**: CPDEL and CO feed it but are not changed by it.
- **also_named_by**: `11_card_wo-operation-bop.md`, `20_engine_wo-generation.md`.

## Findings (recorded, not fixed)
1. **The shift-start schedule reads `config/ppic.php`, not the constants.** `routes/console.php:41-44` uses `config('ppic.default_shifts')` and `config('ppic.default_hours_per_shift')`, while the calendar and the planning screens read `PPIC_DEFAULT_SHIFTS` and `PPIC_HOURS_PER_SHIFT` through `PpicSettings`. Changing the constants in Settings > Constants does not move the run times. FACT (code).
2. **No scheduled run ever left a record here**: `prd_plan_run` has no DAILY or SHIFT row (only EVENT 84 and MANUAL 36), so the two nightly/shift commands were never recorded in this database (the scheduler may simply not run on the dev machine). The last run is 2026-10-16 17:33. FACT (query); the cause is NOT verified.
3. **Two calendars decide one plan**: this planner uses machine shifts from `prod_machine` (07:00 start, Monday to Saturday) while the creation-time backward scheduler uses `emp_shift_roster` and `emp_shift_detail` (`MachineCalendarService`, default 08:00 to 20:00). The shift master (`emp_shift`) and the roster are not read here. Maintenance windows from Maintenance orders are also not read here (only the machine status), as the PPIC Settings screen states ("Preventive maintenance windows in the plan: Not built"). FACT (code, `ppic-settings.blade.php`).
4. **Plate code is invented** (`'PL-'.product_id`, `PlanLoader.php:164`) and ink type is fixed to `CONVENTIONAL` with no spot colours (`:161`), so ink-type changes (conventional to UV, 55 minutes) can never occur in a plan. FACT (code).
5. **`ageDays` is loaded for every WO but no ranking rule uses it** (`PlanLoader.php:70`, `PlanWo.php:19`); the old plan note adds an age weight to the score (`docs/design-notes/ppic/08_ppic-tech-rules.md`, item 3). The real weights are urgency, setup and tier only. FACT (grep `ageDays`).
6. **The registry lists "overload" as an exception of the planner**; the solver has none: "Overload" is added by `Ppic::exceptions` from the stored loads (`Ppic.php:879`). FACT.
7. **The old notes name services that do not exist**: `CapacityLoadService`, `WasteAllowanceService`, `MonitoringService`, table prd_learning_proposal (`docs/design-notes/ppic/05_ppic-tech-database.md`, `06_ppic-tech-engines.md`). No such class file or table exists (search of `app/` and of `information_schema`). FACT.

## Already exists? Searched where, result
`concept:find "schedule"`: Sales cards mention the schedule only as `PlanEvents::replan`. Searched code: `AutoPlanRunner` is the only writer of draft placements (`writeDraft`); `PlanChangeService` and the controller call it. One other planner exists (`BackwardSchedulingService` at WO creation, see finding 3; the old PDR planner was removed 2026-10-18). The Indonesian user guide `docs/guides/production/02_planning_scheduling.md` exists; not repeated.
