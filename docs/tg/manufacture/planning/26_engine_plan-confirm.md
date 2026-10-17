# ENGINE card: Plan Confirm and Change (PlanConfirmService)

> Written 2026-10-17 (Manufacture group). FACT = code with line numbers, tests I ran and the mockup DB (`--env=mockup`) seen this session. Source read in full: `app/Services/Scheduling/Plan/PlanConfirmService.php`; header and public methods of `PlanChangeService.php`; `PpicController.php` (callers).

- **Canonical name**: Plan Confirm and Change. **Class**: `App\Services\Scheduling\Plan\PlanConfirmService` (the officer side of the plan; the change side is `PlanChangeService`). **Registry**: key `ppic-plan-control`, mode `flow`. **Module/sub-module**: Manufacture > Planning > Work Order (button APPLY TO LIVE SCHEDULE) and PPIC Workspace (button Confirm plan).
- **Synonyms**: Apply, APPLY TO LIVE SCHEDULE, Confirm plan, Undo Confirm, Discard, lock.
- **Frozen status**: not frozen. This is the ONLY code that turns a Draft into the real schedule (docblock `PlanConfirmService.php:26-29`).
- **Concept cards**: `13_card_plan-draft-and-live.md`, `10_card_work-order.md`.

## Trigger (no background schedule; officer action only)
FACT, callers in `PpicController.php`:
| Action | Method | File:line |
|---|---|---|
| APPLY TO LIVE SCHEDULE (one WO, all its operations) | `confirm(run, login, null, wo)` | `app/Http/Controllers/V2/PpicController.php:391` (through `applyOne`; also used by APPLY ALL, route `v2.ppic.apply-all`) |
| Confirm plan (whole Draft run) | `confirm(run, login)` | `PpicController.php:85`; button `resources/views/v2/screens/production-scheduling.blade.php:51` |
| Undo last Confirm | `undo` | `PpicController.php:187`; button `production-scheduling.blade.php:64` |
| Discard Draft | `discard` | `PpicController.php:98` |
Access: all four need the flag `PPIC_SCHEDULING` (`PpicAccess::canSchedule`, administrators hold it): `PpicController.php:60`.

## Flow (text twin)
`confirm` (`PlanConfirmService.php:35-124`):
1. The run must be `DRAFT`, else `DomainException` (`:39-41`).
2. Take every operation with `plan_status = DRAFT` and a `draft_start`; for one WO only those of that WO; none: exception (`:43-47`).
3. APPLY of one WO only: refuse when the WO's material shortage list is not empty (`GateService::materialShortages`, `:49-51`; rule Jo 2026-10-14).
4. Safety net against double booking: refuse when a CONFIRMED operation of another WO overlaps the draft slot on the same machine (statuses DONE, QC_PASSED, CANCELLED excluded) (`:53-62`).
5. Lock window: `lockUntil = timeAt(workMinute(now) + N x shiftMinutes, as end)` of that machine, `N = max(1, PPIC_LOCK_SHIFTS)` (1). One WO applied: ALL its operations are locked, whatever the window (`$isLocked = $onlyWoId ? true : draft_start < lockUntil`, `:77-80`). Whole plan: only operations that start inside the window are locked.
6. In one transaction (`:66-117`): keep an undo snapshot (operations and WO statuses); for each operation copy `draft_machine_id/start/finish` to `machine_id`, `target_start`, `target_finish`, set `plan_status = CONFIRMED`, `resource_type` (machine 0 = MANUAL), `is_locked`, `lock_reason` ("Applied to the live schedule" or "Confirmed, locked"), clear the draft columns, and `task_status PENDING -> ASSIGNED`; every WO touched with status DRAFT/SIMULATED becomes `SCHEDULED` and `schedule_layer = COMMITTED`.
7. Run bookkeeping: one WO applied: a NEW run with status CONFIRMED holds the undo snapshot (so Undo restores only that WO); the Draft run closes only when no DRAFT operation is left. Whole plan: the Draft run itself becomes CONFIRMED (`confirmed_by`, `confirmed_at`, undo in `summary`). Decision log `CONFIRM` (`:101-114`).
8. After the transaction, for one WO: `TraceRecorder::recordFinal` stores the officer's order as the training label for `28_engine_sequence-suggester.md` (`:119-121`).

`undo` (`:131-161`): only a run with status CONFIRMED that has an undo snapshot, and the LATEST confirmed run; refused when any of its operations is `RUNNING`, `DONE` or in QC; restores the saved operation rows and WO statuses, run status `UNDONE`, decision `UNDO_CONFIRM`.
`discard` (`:164-170`): every `DRAFT` operation goes to `plan_status = NONE` with draft columns cleared, the run becomes `DISCARDED`, decision `DISCARD`.

Before APPLY the controller also requires: the WO status is DRAFT or SIMULATED, `Ppic::applyBlockedReason` is empty (PROCESS done and nothing changed after it, material ready, no blocked gate or unplaced operation), a fresh planner run against the schedule in force NOW, and `ScheduleGuard::issues` is empty (`PpicController.php:364-398`).

## Input / Output
- **Input**: `prd_plan_run` (Draft), `prd_wo_operation` draft columns, `prd_wo`, calendar (`PlanLoader::calendar`), constant `PPIC_LOCK_SHIFTS`.
- **Output (writes DB)**: `prd_wo_operation` (real schedule columns, lock), `prd_wo` (status, layer), `prd_plan_run`, `prd_plan_decision`, `prd_plan_trace` (through the recorder).
- **Who may overwrite**: a CONFIRMED + locked operation is never moved by the planner; only Undo or the officer's unpin (not locked yet) change it.

## Formula
Lock end time: `lockUntil = timeAt(workMinute(now, m) + N x (hours per shift x 60), m, asEnd)` per machine `m` (`PlanConfirmService.php:77`). With the default values (N = 1, 8 hours) a whole-plan confirm locks the operations that start within the current shift of their machine. Single-WO apply ignores the window.

### Worked example (computed from `ddt*`, read only, 2026-10-17)
`prd_plan_decision` holds 9 `CONFIRM` rows; the last four (run ids 101, 103, 105, 107) were written by `johiwan` within one second on 2026-10-13 09:23. They are the four single-WO applies of the 4 SCHEDULED WOs; today 13 operations are CONFIRMED (11 `ASSIGNED`, 2 `RUNNING`) and all of them have target dates between 13-Oct 09:23 and 14-Oct 07:43, i.e. more than three days behind "now" (17-Oct 22:48). The log has 3 `UNDO_CONFIRM` rows, the last from 12-Oct ("test cleanup").

## Edge cases
- A single-WO apply leaves operations without a draft slot (blocked chain, no machine) unconfirmed while the WO already becomes `SCHEDULED`; they are not locked.
- Undo is refused as soon as ONE operation of the run has started.
- Applying while the Draft is older than the schedule in force is guarded twice: the controller re-runs the planner first, and step 4 refuses a clash.
- `confirm` of a whole plan does not run the material shortage check (step 3 is only for one WO).

## Change side (`PlanChangeService`, same registry key, no separate card)
`app/Services/Scheduling/Plan/PlanChangeService.php` (518 lines) is the officer's other tool; all of it works on the Draft, never on the live schedule. FACT, read in the header, the public methods and the callers:
| Method | What it does | Called from |
|---|---|---|
| `prioritize` | Hot job (`is_hot_job` 1, priority SUPER_URGENT, ranked first, may break a lock) or Move up (priority URGENT, layer 1, breaks no lock); a reason is required; decision `PRIORITY`; runs the planner (trigger EVENT) | `PpicController.php:104-127` |
| `queuePriority`, `queueOrder`, `applyPendingHeader` | the priority or a new BOP order chosen on the Work Order form is kept in `prd_wo.pending_header` until PROCESS makes it real (the order renumbers `seq_no` and drops the Drafts of that WO) | `PpicController.php:454` (PROCESS), `:626` |
| `impact` | dry run of the solver with one operation pinned to a machine and start; returns start, finish, setup and run, other operations that move, WOs that become late; refuses an applied WO, a started operation, a locked confirmed operation of a non-hot WO, a start in the past, a machine that is not eligible, and a start before the material arrival | `PpicController.php:129` |
| `move`, `queueMove` | save the drag as an officer placement (`planned_by = OFFICER`, `is_locked` 0, reason required); `queueMove` marks it `pending_move` with the old place in `pending_from` so it can be discarded; PROCESS clears the marks | `PpicController.php:166`, `:564` |
| `discardPending`, `clearPending` | put pending drags back (decision `MOVE_DISCARD`) or forget their old places after PROCESS | `PpicController.php:553`, `:464` |
| `moveBefore` | colour order: run a job before another on the same press (a reason, same machine, a locked job only when its WO is a Hot job) | `PpicController.php:223-260` |
| `setGroupOut`, `undoPriority`, `unpin` | take a WO out of die grouping, take the last priority back, give the placement back to the engine | `PpicController.php:197,118,208` |
| `simulate`, `overrides`, `groups` | urgent-order options (baseline, Move up, Hot job with finish and lateness), list of officer placements, list of die groups | NO caller found in `app/`, `resources/`, `routes/` (grep) |

## Used by
`PpicController` (Work Order form and PPIC Workspace).

## Open items
`docs/ENGINE-ATTENTION.md`: ATTENTION-ENGINE-08 mentions WOs 273, 274, 283, 286 with remaining Draft operations; the data above shows exactly that state.

## Tests
`tests/Feature/WoProcessApplyTest.php` (APPLY locks ALL operations, APPLY ALL), `tests/Feature/WoApplyGateTest.php` (material gate for APPLY), `tests/Feature/WoEngineVerifyTest.php` (Apply/Undo cases), `tests/Feature/PlanHeldJobTest.php`. Run results: `../90_findings_planning-ppic.md`.

## Relations
- **source**: `23_engine_auto-plan-runner.md` (the Draft), `22_engine_gate-service.md` (shortage list).
- **used_by**: `28_engine_sequence-suggester.md` (training label), `../ppic/21_engine_operator-timer.md` (works only on CONFIRMED operations), cards `13_card_plan-draft-and-live.md`, `10_card_work-order.md`.
- **flows**: Draft -> APPLY / Confirm -> CONFIRMED + locked -> Live Schedule and operator queue -> Undo only before any work starts.
- **frozen_zone**: none.
- **also_named_by**: `11_card_wo-operation-bop.md`, `12_card_wo-material-bom.md`, `14_card_gates-and-readiness.md`, `18_card_order-suggestion-ml.md`, `20_engine_wo-generation.md`, `24_engine_schedule-solver.md`.

## Findings (recorded, not fixed)
1. **The whole-plan Confirm skips the material check.** The shortage gate is inside `if ($onlyWoId && ...)` (`PlanConfirmService.php:49`), so Confirm plan on the Workspace can confirm operations of WOs whose material is not ready (the planner places WAIT operations at their arrival date). Single-WO APPLY is stricter. FACT (code).
2. **The 4 applied WOs would be refused today**: their first operation has material gate WAIT; they were applied before the rule of 2026-10-14. Not wrong at the time; it shows that old applies are never re-checked. FACT (query).
3. **Unlocked CONFIRMED operations return to DRAFT in the next planner run** (`23_engine_auto-plan-runner.md`), while their `task_status` stays `ASSIGNED`. WO2600273 shows 3 CONFIRMED and 5 DRAFT operations all with `task_status = ASSIGNED`. FACT.
4. **The registry lists `confirm` and `discard` only**; `undo` is public and used by a button, not registered. FACT.
5. **`wo_status` stops at SCHEDULED** (see `10_card_work-order.md` finding 1). FACT.
6. **Three public methods of the change side have no caller** (`simulate`, `overrides`, `groups`): the "urgent order" comparison that would replace the old three-option dialog (`31_engine_interruption.md`) is built but not shown on any screen. FACT (grep).
7. **Whole-plan Confirm marks every touched WO `SCHEDULED` even if only some of its operations were in the Draft slot window.** FACT (code `:94-99`).

## Already exists? Searched where, result
`concept:find "work order"`: no card on confirm. Searched code: `plan_status` is set to `CONFIRMED` only here (grep `'CONFIRMED'` in `app/Services/Scheduling`). The old plan notes (`docs/design-notes/ppic/08_ppic-tech-rules.md` item 8) say the lock default is one running shift and breaking it needs a reason: the default is right (`PPIC_LOCK_SHIFTS` = 1); a locked confirmed operation can be moved only after the WO is given the Hot job priority with a reason (`PlanChangeService.php:137-139`), and an applied WO cannot be moved at all (`:131-134`); the reason prompt on screen was NOT checked.
