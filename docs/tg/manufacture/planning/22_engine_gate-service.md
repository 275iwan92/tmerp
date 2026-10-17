# ENGINE card: Readiness Gates (GateService)

> Written 2026-10-17 (Manufacture group). FACT = code with line numbers and mockup DB (`--env=mockup`, `tm2*` = `ddt*`) seen this session. Source read in full: `app/Services/Scheduling/Plan/GateService.php`, `AvailableStock.php`.

- **Canonical name**: Readiness Gates. **Class**: `App\Services\Scheduling\Plan\GateService`. **Registry**: key `ppic-gates`, mode `auto`. **Module/sub-module**: Manufacture > Planning (Work Order form, PPIC Workspace tab Material).
- **Synonyms**: gate, material gate, tooling gate, artwork gate, "Ready / Waiting / Blocked", READY / WAIT / BLOCK.
- **Frozen status**: not frozen. Reserve and booking belong to ProCost (Jo 2026-10-13, ATTENTION-ENGINE-03); this engine only reads them.
- **Concept cards**: `14_card_gates-and-readiness.md`, `15_card_material-availability.md`, `12_card_wo-material-bom.md`.

## Trigger (no background schedule of its own)
Searched callers in `app/`, `resources/views`, `routes/`. FACT:
| Trigger | File:line |
|---|---|
| Auto-planner, at the start of EVERY run, for every active WO | `app/Services/Scheduling/Plan/AutoPlanRunner.php:144` |
| Create Work Order, PROCESS, RESET, refresh, target edit, delete of BOM rows | `app/Http/Controllers/V2/PpicController.php:328,462,527,886,1035,1070` |
| APPLY TO LIVE SCHEDULE (shortage list only) | `app/Services/Scheduling/Plan/PlanConfirmService.php:49` |
| Button state of APPLY on the form | `app/Support/V2/Data/Ppic.php:1359` (`applyBlockedReason`) |

The auto-planner itself runs from `routes/console.php` (nightly 02:00 and at each shift start): see `23_engine_auto-plan-runner.md`.

## Flow (text twin)
`apply(wo)` (`GateService.php:212-230`) writes the three gates of every operation of the WO. Material is evaluated ONCE for the WO and written on the FIRST operation only; the other operations get `gate_material = READY`.
1. **Material, own BOM lines first** (`material` -> `ownLines` -> `materialShortages`, `:48-168`):
   - WO from an Inquiry part with no `prd_wo_material` line: state `NO_LINES` -> WAIT "No material lines".
   - Line with `qty_required > 0` and no `material_id`: `NOT_CHOSEN` -> WAIT "Material not chosen yet: ..." (the first two names). If any line is not chosen, the list stops here (`:139-144`).
   - A substituted line is judged by the stock of the substitute material (`:146-149`).
   - Available = on hand of usable locations minus ALL open bookings (`AvailableStock::of`, `AvailableStock.php:35-56`). Release = the officer's stored value, else Available. Purchase = `max(0, Required - Release)`.
   - Release above Available: `OVER_RELEASE` -> BLOCK "Release exceeds available stock". Purchase above 0.0001: `SHORT` -> WAIT "Buy ...".
   - Lines with `qty_required = 0` are ignored (`:150`).
2. **Material, Material Planning second** (only when step 1 finds nothing, `:57-87`): lines of `MaterialPlanningEngine::plan()` for this CO and part (not role FG): status `SHORT` -> BLOCK "Short x material: purchase request needed"; status `PEGGED_PO`, `PEGGED_PR`, `LATE_PO`, `LATE_PR` -> WAIT with `material_ready_at` = the latest arrival date of a non-stock allocation at 08:00. Engine card: `../../sales/order/30_engine_material-planning.md`.
3. **Tooling** (`tooling`, `:187-209`): needed when the process has `requires_die` (type `CUTTING_DIE`) or `requires_plate` (type `PLATE_SET`). Finds the best asset in `prod_tooling_asset` by `dieline_ref` of the WO OR `product_id`, ordered READY, IN_USE, IN_MAKING, NEEDS_REPAIR, NOT_MADE. Status READY or IN_USE passes; other statuses BLOCK "Die/Plates CODE is ...". No asset registered: READY with the note "Tooling is not registered in the tooling master" (unknown data never blocks).
4. **Artwork**: always `READY` (`:224`).
5. `gate_note` = material note (first operation) + tooling note, cut to 190 characters; `material_ready_at` only on the first operation.

## Input / Output
- **Input**: `prd_wo`, `prd_wo_operation`, `prd_wo_material`, `prod_process` (`requires_die`, `requires_plate`), `prod_tooling_asset`, `tm2inv.v_rm_available`, `rm_booking`, `rm_booking_d`, `MaterialPlanningEngine::plan()`.
- **Output (writes DB)**: `prd_wo_operation.gate_material`, `gate_tooling`, `gate_artwork`, `gate_note`, `material_ready_at`.
- **Read only elsewhere**: `materialShortages` and `shortageText` feed APPLY and the BOM sheet without writing.

## Formula (one place)
- `Available = max(0, onhand - sum(open booking left))`; open booking left per line = `qty_book - qty_issued` where the line and header are not cancelled, header not closed, and not a HELD pre-booking whose `hold_until` has passed (`AvailableStock.php:44-48`).
- `Release = stored ?? Available`; `Purchase = max(0, Required - Release)` (`GateService.php:91-100`). Rule from Jo 2026-10-13; the same numbers are shown on the BOM sheet.
- Unit: the unit of the BOM line and the unit of the stock are compared as they are, with NO conversion (ATTENTION-ENGINE-04).

### Worked example (computed from `ddt*`, read only, 2026-10-17)
Of the 55 non-cancelled WOs, the material gate of the first operation is: WAIT 43, BLOCK 12, READY 0. Gate notes of those first operations: "Material not chosen yet ..." on 41 and "Buy ..." on 2 (the 43 WAIT, step 1); "Short x BRITE INK BLACK: purchase request needed" on the 12 BLOCK (step 2, Material Planning). `rm_booking_d` has 368 lines and none has `qty_book > 0`, so today Available equals on hand. At least 51 operations carry the note "Tooling is not registered in the tooling master" (only 4 rows exist in `prod_tooling_asset`: die and plates for two products, all READY). 29 of the 55 WOs have no `dieline_ref`.

## Edge cases
- Required 0 never holds: all 110 `TINTA` lines of non-cancelled WOs have `qty_required = 0` (ink area not computed), so they are skipped by step 1, while Material Planning blocks 12 WOs for the same ink (see finding 3).
- `NOT_CHOSEN` hides every other shortage of the WO until the papers are chosen (the list returns early).
- A WO without `source_iqp_part_id` and without lines passes step 1 and is judged by step 2.

## Used by
`AutoPlanRunner`, `PlanConfirmService`, `PpicController`, `Ppic::applyBlockedReason`, `ScheduleGuard` (reads the gate columns), the PPIC Workspace tab Material control.

## Open items
`docs/ENGINE-ATTENTION.md`: ATTENTION-ENGINE-02 (two definitions of Available), -03 (Release does not book), -04 (units), -05 (Purchase does not create a PR), -06 (Required 0, artwork, unregistered tooling). Their status is kept there, not copied. Line numbers cited in those items (for example `GateService.php:118,159,181`) have moved: the same facts are now at `:150`, `:202` and `:224`.

## Tests
`tests/Feature/WoApplyGateTest.php` (4), `tests/Feature/WoAvailableTest.php` (5), `tests/Feature/WoEngineVerifyTest.php` (23; includes the "known gap" cases for Release and artwork), `tests/Feature/PlanHeldJobTest.php`. Run results: `../90_findings_planning-ppic.md`, section "Tests run".

## Relations
- **source**: `12_card_wo-material-bom.md`, `15_card_material-availability.md`, `../../sales/order/30_engine_material-planning.md`, `../ppic/14_card_tooling.md`.
- **used_by**: `23_engine_auto-plan-runner.md` (gates decide whether an operation can be placed), `26_engine_plan-confirm.md` (APPLY refuses a WO with shortages), `24_engine_schedule-solver.md` (reads `gate_*` through the loader).
- **flows**: BOM line -> shortage list -> gate -> solver (BLOCK stops the chain, WAIT delays the start) -> APPLY check.
- **frozen_zone**: Material Planning and Booking are ProCost territory; do not change them from here.
- **also_named_by**: `11_card_process-and-routing.md`, `11_card_wo-operation-bop.md`, `13_card_plan-draft-and-live.md`, `16_card_upstream-interface.md`, `20_engine_wo-generation.md`, `21_engine_wo-planning-enricher.md`, `28_engine_sequence-suggester.md`.

## Findings (recorded, not fixed)
1. **Two sources decide the material gate and they can disagree**: the WO's own BOM lines (step 1) and the Material Planning lines (step 2). In the data the BOM ink lines are 0 while the gate is blocked by the planner for ink. FACT (query and code).
2. **`NOT_CHOSEN` returns early** and so hides shortages of other lines until the paper is chosen. 41 of 55 active WOs have a paper or sheet line without a chosen material. FACT.
3. **No active WO has a READY first-operation material gate today.** The 4 SCHEDULED WOs (`WO2600273`, `WO2600274`, `WO2600283`, `WO2600286`) were confirmed on 2026-10-13 (decision log, `CONFIRM`, actor `johiwan`), before the rule "apply only when all raw material is ready" dated 2026-10-14 in the code; their first operation shows WAIT today. FACT (query).
4. **`prod_tooling_asset.setup_repeat_min` and `impressions_*` are not read** by any planning code; `PlanLoader` takes only `setup_first_min` (`PlanLoader.php:254`). FACT (grep).
5. **ATTENTION line references are stale** (see Open items). FACT.
6. **`gate_artwork` is NULL on 12 operations**, all of cancelled WOs, and READY on 374: the column is written only by `apply`. FACT (query).

## Already exists? Searched where, result
`concept:find "gate"`: only the column names `prd_wo_operation.gate_*` and the QC batch gate in Inventory (different concept). Searched code for other writers of `gate_material`: only `apply` writes it (grep `gate_material`). Old plan note `docs/design-notes/ppic/06_ppic-tech-engines.md` says the gate also reads "customer-supplied goods, capacity, proof approvals": NOT in the code (FROM DOCUMENT, wrong). User's guide: `docs/guides/production/01_work_order_bom_gates.md` (exists, not repeated).
