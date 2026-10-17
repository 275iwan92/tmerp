# ENGINE card: Maintenance Machine Hold (MaintenanceBlockProvider)

> Written 2026-10-17 (Manufacture group). FACT = code with line numbers and mockup DB (`--env=mockup`) seen this session. Source read in full: `app/Services/Scheduling/MaintenanceBlockProvider.php`; callers; test I ran: `tests/Feature/MaintenanceSchedulingBlockTest.php`.

- **Canonical name**: Maintenance Machine Hold (Scheduling). **Class**: `App\Services\Scheduling\MaintenanceBlockProvider`. **Registry**: key `maintenance-block`, group Maintenance in the engine map (it serves scheduling), mode `calc`. **Module/sub-module**: serves Manufacture > Planning (old board and creation-time backward scheduling).
- **Synonyms**: machine hold, maintenance window, PM block, "jendela Maintenance".
- **Frozen status**: not frozen.
- **Concept cards**: `13_card_plan-draft-and-live.md`.

## Trigger (called by another engine)
FACT, searched `app/`, `resources/views`, `tests/`:
| Caller | File:line |
|---|---|
| `MachineCalendarService` (backward scheduling at WO creation and old board) | `app/Services/Scheduling/MachineCalendarService.php:37` (constructor), blocks read in the loop `:93` |
| `PdrEngine::validateManualReschedule` (refuse a drag onto a hold) | `app/Services/Scheduling/PdrEngine.php:156` |
| v1 board, grey bands | `resources/views/pages/production/scheduling-board.blade.php:384` |
| NOT called by `AutoPlanRunner`, `PlanLoader` or `ScheduleSolver` | grep `MaintenanceBlockProvider` in `app/Services/Scheduling/Plan`: no result |

## Flow (text twin)
`blocksFor(from, to, machineIds)` (`MaintenanceBlockProvider.php`):
1. If the Maintenance database or table `mnt_order` does not exist, return no blocks (production scheduling must not fail).
2. Assets with a `machine_id` and `activate` (`mnt_asset`) give the machine link (45 of 48 assets in `ddt*` are linked to a machine).
3. Orders of those assets that are Confirmed (`stat_confirm`), not cancelled, not closed, `needs_stop = 1`, `work_status` not `DONE`, with a `planned_start` not after the window end.
4. Block = from the actual start (else planned start) to the planned end; if there is no end, start + `est_minutes`; if that is missing or not after the start, 60 minutes (`DEFAULT_MINUTES`). Blocks that end before the window start are dropped. Sorted by end time, latest first.
5. `blocksOn(day, machine)` caches per machine and day for 30 seconds. `overlapping(machine, start, end)` returns the first block that touches the interval.

## Input / Output
- **Input**: `tm2mnt.mnt_asset` (`machine_id`, `activate`), `tm2mnt.mnt_order` (`asset_id`, `stat_confirm`, `stat_cancel`, `stat_close`, `needs_stop`, `work_status`, `planned_start`, `planned_end`, `actual_start`, `est_minutes`, `doc_no`, `description`).
- **Output**: arrays of blocks (`machine_id`, `start`, `end`, `order_id`, `label`). Writes nothing.

## Formula
`end = planned_end ?? (start + est_minutes ?? 60 minutes)`; `start = actual_start ?? planned_start`. Overlap test: `block.start < end AND block.end > start`.

### Worked example (test I ran without `--env=mockup`, so against `tm2*`, 2026-10-17)
`tests/Feature/MaintenanceSchedulingBlockTest.php` passed (3 tests, 7 assertions): a Confirmed order that stops the machine shortens the working time that backward scheduling counts. `ddt*` today has 2 Maintenance orders, both `DRAFT` (one needs a stop), so no block exists in the dummy data.

## Edge cases
- Orders in `DRAFT` (not Confirmed) never block.
- The machine status `MAINTENANCE` and `BREAKDOWN` is a separate path: the v2 planner skips machines in those statuses (`PlanLoader.php:50`) but does not read order windows.

## Used by
`MachineCalendarService`, `PdrEngine`, the v1 board.

## Open items
None in `docs/ENGINE-ATTENTION.md`.

## Tests
`tests/Feature/MaintenanceSchedulingBlockTest.php` (3).

## Relations
- **source**: Maintenance module (`mnt_order`, `mnt_asset`); no TG card for Maintenance exists yet.
- **used_by**: `29_engine_pdr.md`, `20_engine_wo-generation.md` (backward scheduling at creation).
- **flows**: confirmed PM / breakdown order that needs a stop -> block -> working time reduced -> dates move.
- **frozen_zone**: none.

## Findings (recorded, not fixed)
1. **The v2 planner ignores Maintenance order windows.** `AutoPlanRunner` and the solver read only the present machine status; the PPIC Settings screen lists "Preventive maintenance windows in the plan" as not built. A PM order that is Confirmed for next week does not reserve the machine in the v2 Draft. FACT (grep, screen text).
2. **The registry puts this class in group Maintenance** and the engine map lists `maintenance-block` under Maintenance although it is only consumed by Production scheduling code. FACT.
3. **Cache of 30 seconds per machine and day inside one object**: a long queue worker is protected by resetting after 30 seconds (comment), but a Confirm done meanwhile is seen up to 30 seconds late. FACT (code).

## Already exists? Searched where, result
`concept:find "machine"`: only `docs/tg/01_engine-map.md` mentions `maintenance-block`. Searched: the only reader of order windows for scheduling is this class.
