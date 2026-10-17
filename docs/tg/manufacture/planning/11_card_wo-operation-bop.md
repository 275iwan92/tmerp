# Concept card: Work Order operation (BOP as the WO sees it)

> Written 2026-10-17 (Manufacture group). "FACT" = seen first-hand in this session: mockup DB (`--env=mockup`, `tm2*` = `ddt*`; counts = `count(*)` today) and code with line numbers. "FROM DOCUMENT" = not checked.

- **Document type**: concept card. **Module/sub-module**: Manufacture > Planning > Work Order, accordion "Bill of Process" (BOP sheet) and Simulation.
- **Canonical name**: Work Order operation = `tm2prd.prd_wo_operation` (one row per process step of a WO, the copy of the ProDev BOP step) + `prd_wo_operation_machine` (the machines that can run it).
- **Synonyms**: BOP line, task, step, operation, routing step, `operation_id`, "proses".
- **NOT the same as**: the ProDev BOP (`prod_part_process`, `iqp_part_process`, owner ProDev, `../../sales/prodev/10_card_prodev-interface.md`), the routing template (`prod_routing_template`, empty), the Master Card process list.

## Facts
| Item | Content | Evidence |
|---|---|---|
| Table | `prd_wo_operation`: 386 rows (343 `resource_type = MANUAL`, 43 `MACHINE`), PK `operation_id`, FK `wo_id` to `prd_wo`. `task_status`: PENDING 354, ASSIGNED 30, RUNNING 2. `plan_status`: NONE 130, DRAFT 243, CONFIRMED 13. | FACT, `show columns`; query |
| Copy of ProDev | A BOP step becomes a row: `process_name` = step label, `seq_no` = (i+1) x 10, `process_id`, `machine_id` (or MANUAL), `qty` = step input quantity, `waste_qty` and `tolerance_qty` (the "Spill") with their unit, `rate_band_value` = target per hour. Rebuilt on PROCESS without losing placement or progress. | FACT, `WoGenerationService.php:107-125,182-256` |
| Units and quantities | `qty` in, `qty_out`, `qty_uom` (LBR up to the die-cut/finishing steps, PCS afterwards, `WoPlanningEnricher.php:74-79`). Filled for 374 of 386 rows (12 empty, all cancelled WOs). `waste_qty` and `tolerance_qty` are filled on 194 rows each. | FACT, code and query |
| Target and run | `rate_band_value` (target per hour) 232 rows; `run_minutes` 338 rows; setup split `setup_base_min`, `setup_change_min`, `setup_tool_min`. The officer edits the target on the BOP sheet; each edit is logged as decision `TARGET` with the reason and written to the candidate row of the chosen machine (`PpicController.php:593`). | FACT, query; code |
| Candidate machines | `prd_wo_operation_machine`: 1719 rows (1357 eligible, 362 not), columns `eligible`, `reason`, `preference_rank`, `target_qty_per_hour`, `setup_minutes`. Written by `21_engine_wo-planning-enricher.md`. | FACT, query |
| Plan columns | `plan_status` (NONE, DRAFT, CONFIRMED), `planned_by` (ENGINE or OFFICER), `draft_machine_id`, `draft_start`, `draft_finish`, `draft_run_id` (the proposal); `machine_id`, `target_start`, `target_finish`, `is_locked`, `lock_reason` (the schedule in force); `gate_material`, `gate_tooling`, `gate_artwork`, `gate_note`, `material_ready_at` (gates, card `14_card_gates-and-readiness.md`); `pending_move`, `pending_from`, `pending_reason` (a drag waiting for PROCESS). | FACT, `show columns`; `Ppic::operations` (`Ppic.php:269-323`) |
| Effective placement | The screens read the Draft slot when `plan_status = DRAFT`, otherwise the confirmed `target_*` (`Ppic.php:292-296`). | FACT, code |
| Actor | One of `crew_work_center_id` (a work center of HRM, shown as `C:id`) or `subcon_supplier_id` (a vendor, `S:id`), never both (`PrdWoOperation.php:202-216`). 0 of 386 are set today. Production Output finds the operator's queue through the work center (`app/Support/V2/Data/ProductionOutput.php:96-107`). | FACT, code and query |
| Task status | State machine `PrdWoOperation::TRANSITIONS`: PENDING -> ASSIGNED (APPLY) -> RUNNING (operator Play) -> DONE (Stop of production); BREAKDOWN, SUBCONTRACT and the QC states are written only by the v1 page and the subcontract PO service (`SubcontractPoService.php:58,86`). | FACT, code |
| Officer edits | Add row, Delete row (with a reason), re-order, target, actor. Each is a decision row in `prd_plan_decision` (`ROW_ADDED`, `ROW_DELETED`, `RESEQUENCE`, `TARGET`); PROCESS never removes an added row and does not bring back a deleted engine row; RESET (`SheetReset`) retires those decisions and restores the engine output, except for operations that are CONFIRMED, started or have output. | FACT, `SheetReset.php`, `WoGenerationService.php:182-256` |
| Output of the shop floor | `prd_wo_operation_output` (good quantity, 2 rows), `prd_wo_operation_output_defect` (reject lines per scrap type, 0 rows), `prd_operation_time_log` (5 segments), `prd_output_action_log` (0 rows). Card `../ppic/21_engine_operator-timer.md`. | FACT, query |

## Relations
- **source**: ProDev BOP through `20_engine_wo-generation.md`; machine and process masters (`../ppic/10_card_machine.md`, `../ppic/11_card_process-and-routing.md`).
- **used_by**: the planner (`23_engine_auto-plan-runner.md`, `24_engine_schedule-solver.md`), gates (`22_engine_gate-service.md`), confirm (`26_engine_plan-confirm.md`), Live Schedule and Colour Sequence (`13_card_plan-draft-and-live.md`), operator timer, machine OEE (`app/Services/Production/MachineOeeService.php`), subcontract PO (`app/Services/Pur/SubcontractPoService.php`).
- **flows**: ProDev BOP -> operations (PENDING, plan NONE) -> enrich + gates -> planner Draft -> APPLY (CONFIRMED, ASSIGNED, locked) -> operator RUNNING -> DONE.
- **frozen_zone**: the BOP itself is ProDev (frozen). The operation rows are PPIC.
- **also_named_by**: `10_card_work-order.md`, `13_card_crew-master.md`, `16_card_upstream-interface.md`.

## Findings (recorded, not fixed)
1. **Two columns for the same loss**: ProDev's "Spill" goes to `tolerance_qty`, and the v2 BOP sheet column "Spoilage" also edits `tolerance_qty` (`PpicController.php:294`), while `spoilage_qty` is written by no v2 code (0 of 386 rows). `MachineOeeService` reads `spoilage_qty` (`MachineOeeService.php:131-142`), so the OEE spoilage figure stays 0 for v2 work orders. The v1 page edits `spoilage_qty`. FACT.
2. **`resource_type` is MANUAL on 343 of 386 operations**, also where eligible machines exist: it is set from the BOP step (`WoGenerationService.php:114`) and changed to MACHINE only at APPLY (`PlanConfirmService.php:82`). Readers that use `resource_type` before APPLY get a wrong picture. FACT.
3. **v1-only columns**: `critical_ratio`, `batched_with_operation_id`, `qc_gate_required`, `rejection_reason`, `is_hot_job` (operation level) are used by the old board and the v1 WO page only; v2 uses the WO-level flag. FACT (grep).
4. **A CONFIRMED operation that is not locked is set back to `plan_status = DRAFT` by the next planner run** while `task_status` stays ASSIGNED (19 operations are `DRAFT` + `ASSIGNED` today). FACT; see `23_engine_auto-plan-runner.md`.
5. **The old plan note says the operation has no process id and no setup columns** (`docs/design-notes/ppic/05_ppic-tech-database.md` item 2); both exist now (`process_id` empty on 1 of 386 rows). FROM DOCUMENT, outdated.
6. **Two operations are RUNNING with open timer segments since 13-Oct 09:23** (4 days, 2 `RUN` segments with `ended_at` empty): dummy data, but the screen "On the shop floor now" will show them as working. FACT (query).

## Already exists? Searched where, result
`concept:find "work order"`: no card on operations. Searched: the routing of a WO is stored only here; `prod_routing_step` is the (empty) template. The BOP sheet component is described in the screen REMARK (`production-wo.blade.php`).
