# ENGINE card: Hot Job Option Generator (InterruptionOptionGenerator)

> Written 2026-10-17 (Manufacture group). FACT = code with line numbers and mockup DB (`--env=mockup`) seen this session. Source read in full: `app/Services/Scheduling/InterruptionOptionGenerator.php`, `InterruptionOption.php` (name only), the v1 board lines that call it.

- **Canonical name**: Hot Job Option Generator. **Class**: `App\Services\Scheduling\InterruptionOptionGenerator`. **Registry**: key `interruption`, mode `suggest`. **Module/sub-module**: the v1 Scheduling Board (dialog "insert hot job"); NOT the v2 PPIC Workspace.
- **Synonyms**: interruption, insert hot job, displace, reroute to subcontract.
- **Frozen status**: not frozen. Gives options; the officer decides.
- **Concept cards**: `13_card_plan-draft-and-live.md`.

## Trigger
FACT, searched `app/`, `resources/views`, `routes/`:
| Method | Caller | File:line |
|---|---|---|
| `generateOptions` | v1 board, hot job dialog | `resources/views/pages/production/scheduling-board.blade.php:726` |
| `recordDecision` | v1 board, applying an option | `scheduling-board.blade.php:797,886` |
| Container wiring | `SchedulingServiceProvider` | `app/Providers/SchedulingServiceProvider.php:57-58` |
No schedule, no v2 caller. In v2 the hot job is a priority of the WO (`PlanChangeService::prioritize`, `26_engine_plan-confirm.md` section on change) and the engine shows the impact of a drag (`PlanChangeService::impact`).

## Flow (text twin)
1. Take the queue of the machine and process from `PdrEngine::buildResourceQueue` (`29_engine_pdr.md`) without the hot operation (`InterruptionOptionGenerator.php:27-28`).
2. For the first two waiting operations build an option "Sisip, geser <WO> (aman)" or "(BERISIKO)": impact hours = the duration of the hot operation (`target_finish - target_start`); safe when the displaced operation's `target_finish + impact` is not after its WO due date (`:30-41`, `:76-97`).
3. Always add a last option "Reroute subkontrak" with impact 0, safe, flag `requiresSubcontractPo` (a subcontract PO is needed; see `app/Services/Pur/SubcontractPoService.php`) (`:46-53`).
4. `recordDecision` writes one row to `prd_decision_feedback_log` (event type, WO, customer, machine, options shown, option chosen, who, when).

## Input / Output
- **Input**: hot operation, machine id, process name; the queue of that machine.
- **Output**: 1 to 3 `InterruptionOption` objects (label, displaced operation id, impact hours, safe flag, cost = null); `recordDecision` writes `prd_decision_feedback_log`.

## Formula
`impactHours = (hot.target_finish - hot.target_start) / 60`; `safe = candidate.target_finish + impactHours <= wo.due_date` (true when a date is missing). It is a simple shift by the duration of the hot job; later operations in the chain are not recomputed. A worked example is NOT written: no test, and `prd_decision_feedback_log` has 0 rows in `ddt*`.

## Edge cases
- Fewer than two waiting operations: fewer options, the subcontract option is always there.
- A candidate without dates: impact 0 and safe.

## Used by
v1 Scheduling Board only.

## Open items
None in `docs/ENGINE-ATTENTION.md`.

## Tests
`tests/Feature/ProductionSchedulingBoardSmokeTest.php` has `test_hot_job_dialog_generates_and_applies_displace_option` (not run this session, see `29_engine_pdr.md`).

## Relations
- **source**: `29_engine_pdr.md`.
- **used_by**: v1 board only; card `13_card_plan-draft-and-live.md`.
- **flows**: hot job -> options -> officer -> `recordDecision`.
- **frozen_zone**: none.

## Findings (recorded, not fixed)
1. **Labels are hard-coded Indonesian text** without `t()` (`:35,47`), against the rule that the interface text is English and translated through the dictionary. FACT.
2. **The estimate is a single shift by the hot job's duration**; it does not use the setup change, the machine calendar or the next operations. The v2 impact preview (`PlanChangeService::impact`) runs the real solver instead. FACT.
3. **Not connected to v2**: the v2 Work Order form has no "insert hot job options" step; priority Hot job goes straight to a new Draft run. A v2 successor exists (`PlanChangeService::simulate`: baseline, Move up, Hot job with finish and late WOs) but it has no caller (`26_engine_plan-confirm.md`, section Change side). FACT (code, grep).
4. **`prd_decision_feedback_log` has 0 rows, and the setting feedback_calibration_cycle in `config/production_scheduling.php` has no reader** (grep in `app`, `resources`, `routes`: no result). FACT.

## Already exists? Searched where, result
`concept:find "work order"`: no earlier card. Searched: the "insert a hot job" function exists twice (this generator on the v1 board and the priority + impact path in v2).
