# Concept card: Gates and readiness (Ready / Waiting / Blocked)

> Written 2026-10-17 (Manufacture group). "FACT" = seen first-hand in this session: mockup DB (`--env=mockup`; counts = `count(*)` today) and code with line numbers. "FROM DOCUMENT" = not checked. The column facts of the gate live in the engine card `22_engine_gate-service.md`; this card explains the meaning for the officer and the rules that stop a plan. The table `prd_wo_operation` is described in `11_card_wo-operation-bop.md`.

- **Document type**: concept card. **Module/sub-module**: Manufacture > Planning > Work Order (BOP sheet column Gates, button APPLY), PPIC Workspace (tab Material, exceptions).
- **Canonical name**: Gate = the three readiness flags of an operation: `gate_material`, `gate_tooling`, `gate_artwork` (values `READY`, `WAIT`, `BLOCK`) plus the explanation `gate_note` and the arrival time `material_ready_at`, all on `tm2prd.prd_wo_operation`.
- **Synonyms**: readiness gate, Ready, Waiting, Blocked, "not ready" bucket, schedule guard.
- **NOT the same as**: the QC batch gate of Inventory (`../../inventory/batch-quality/13_card_qc-decision-and-first-article.md`), the QC task states of an operation (`QC_HOLD`), the approval gates of Sales documents.

## Facts
| Item | Content | Evidence |
|---|---|---|
| Meaning | READY: nothing stops the operation. WAIT: it can be planned, but not before `material_ready_at` (or the date is unknown); the solver delays the start and reports `WAITING`. BLOCK: the operation is not placed and every later operation of the WO is not placed either (`BLOCKED`, `CHAIN_BLOCKED`). Three gates collapse to one: any BLOCK gives BLOCK, else any WAIT gives WAIT, else READY. | FACT, `PlanLoader.php:148-149`; `ScheduleSolver.php:292-301` |
| Where the officer sees it | Column Gates of the BOP sheet ("Ready / Waiting / Blocked" per gate), Gantt bar dots, PPIC Workspace tab Material control, exceptions list, Work Order form note. Bucket of an operation: `done`, `running`, not_ready (BLOCK, or WAIT without a start), `queued` (has a start), `ready` (no start, gates OK). | FACT, `Ppic::bucket` (`Ppic.php:341-350`); `_ppic-material.blade.php` |
| What is checked | Material (own BOM lines, then Material Planning), tooling (die or plates in the tooling master) and artwork (always Ready). Rules and data are in `22_engine_gate-service.md`. | FACT, `GateService.php` |
| When the gates are written | At every planner run (all active WOs), at Create Work Order, PROCESS, RESET, refresh, target edit and BOM row delete. | FACT, `AutoPlanRunner.php:144`; `PpicController.php:328,462,527,886,1035,1070` |
| Rule for APPLY | One WO can be applied only when: its status is DRAFT or SIMULATED; PROCESS was done and nothing changed after it (`process_hash`); the material shortage list is empty; no gate is BLOCK and every operation has a start (`Ppic::applyBlockedReason`, `engineCheck`); then the planner is re-run and the schedule guard is empty. | FACT, `Ppic.php:1350-1365`, `PpicController.php:364-398` |
| Schedule guard | "No BOP process may stay unscheduled": a process without a start (not done, not running) blocks PROCESS and refuses APPLY and APPLY ALL, with the reason: gate blocked (the gate note), no machine can run it, waiting for material or tooling, or no slot in the horizon. SUBCON and MANUAL (crew) operations always get a time, so they never appear. | FACT, `ScheduleGuard.php`; test `tests/Feature/ScheduleGuardTest.php` (2 passed under `--env=mockup`) |
| Held job | A confirmed, locked, not started job whose gate is BLOCK, or whose material arrives after its slot, does not hold its machine: the engine pulls the next ready job into the slot and proposes the held one again in the Draft; the officer decides at Confirm (rule Jo 2026-10-14). | FACT, `PlanLoader.php:131-133`; test `tests/Feature/PlanHeldJobTest.php` (1 passed) |
| Data today | First-operation material gate of the 55 non-cancelled WOs: WAIT 43, BLOCK 12, READY 0. Tooling: 51 operations READY with the note "Tooling is not registered in the tooling master"; `prod_tooling_asset` has 4 rows. Artwork: READY on 374 operations. Operations without a start: 94 of 350 active (81 chain-blocked, 13 blocked). | FACT, query; `Ppic::stats` call |

## Relations
- **source**: `12_card_wo-material-bom.md`, `15_card_material-availability.md`, `../ppic/14_card_tooling.md`, `../ppic/11_card_process-and-routing.md` (the `requires_die` and `requires_plate` flags).
- **used_by**: `24_engine_schedule-solver.md` (gates stop or delay placement), `26_engine_plan-confirm.md` (APPLY), `28_engine_sequence-suggester.md` (feature material_gate), `13_card_plan-draft-and-live.md` (exceptions, buckets).
- **flows**: BOM line short or tooling missing -> gate WAIT / BLOCK -> planner delays or skips -> exception -> officer buys, makes tooling, or waits -> next planner run updates the gate.
- **frozen_zone**: none in PPIC; the booking numbers belong to ProCost.
- **also_named_by**: `10_card_work-order.md`.

## Findings (recorded, not fixed)
1. **READY does not mean ready for artwork or tooling.** The artwork gate is a constant (`GateService.php:224`) and unregistered tooling passes with a note; 29 of 55 WOs have no `dieline_ref` and only 4 tooling assets exist, so tooling readiness is not really tested. This is ATTENTION-ENGINE-06 (status stays there); the code lines it quotes have moved to `:150`, `:202`, `:224`. FACT.
2. **The gate is a point in time**: written at the planner run or at an officer action, not when stock or an artwork approval changes. A gate can be hours old. FACT (no observer on stock tables writes the gate).
3. **The "not_ready" count mixes causes**: the Workspace shows one number for blocked material, missing tooling and missing paper selection. The reasons are only in `gate_note` text. FACT.
4. **Old plan note lists gates that do not exist**: "customer-supplied goods, capacity, proof approval" (`docs/design-notes/ppic/06_ppic-tech-engines.md`). Only material, tooling, artwork exist; the artwork gate has no data source. FROM DOCUMENT, wrong. FACT for the code.
5. **Applied WOs are not re-checked**: the 4 SCHEDULED WOs show a WAIT first gate today (`26_engine_plan-confirm.md` finding 2). FACT.

## Already exists? Searched where, result
`concept:find "gate"`: the Inventory QC gate (different) and the columns only. Searched code: gates are written only by `GateService::apply`. The Indonesian guide `docs/guides/production/01_work_order_bom_gates.md` explains them for users.
