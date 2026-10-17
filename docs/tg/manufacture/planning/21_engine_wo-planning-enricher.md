# ENGINE card: Work Order Enrichment (WoPlanningEnricher)

> Written 2026-10-17 (Manufacture group). FACT = code with line numbers and mockup DB (`--env=mockup`, `tm2*` = `ddt*`) seen this session. Source read in full: `app/Services/Scheduling/Plan/WoPlanningEnricher.php`, `PlanFacts.php`, `app/Services/Production/MachineCapabilityService.php`.

- **Canonical name**: Work Order Enrichment. **Class**: `App\Services\Scheduling\Plan\WoPlanningEnricher`. **Registry**: key `ppic-wo-enrich`, mode `suggest` (but see finding 3). **Module/sub-module**: Manufacture > Planning > Work Order.
- **Synonyms**: enrich, "planning facts", candidate machines, eligibility.
- **Frozen status**: not frozen. It reads ProDev / Inquiry data and never changes it (docblock of `PlanFacts`).
- **Concept cards**: `11_card_wo-operation-bop.md`, `10_card_work-order.md`; master side `../ppic/10_card_machine.md`, `../ppic/11_card_process-and-routing.md`.

## Trigger (no background schedule of its own)
Searched callers in `app/`, `resources/views`, `routes/`. FACT:
| Trigger | File:line |
|---|---|
| Create Work Order (after `Cpdel::post`) | `app/Http/Controllers/V2/PpicController.php:327` |
| Button PROCESS (after the BOP/BOM rebuild) | `app/Http/Controllers/V2/PpicController.php:461` |
| RESET of the BOP / BOM sheet | `app/Http/Controllers/V2/PpicController.php:526` |
| Edit of target cells (Start / Stop / target per hour) | `app/Http/Controllers/V2/PpicController.php:809` |
| Button Refresh from ProDev | `app/Http/Controllers/V2/PpicController.php:1069` |
| Auto-planner, for a WO that has an operation without `process_id` or no candidate machine row at all | `app/Services/Scheduling/Plan/AutoPlanRunner.php:138-143` |

## Flow (text twin)
1. Read planning facts of the WO (`PlanFacts::forWo`, `PlanFacts.php`): inquiry part, ProDev layout (`iqp_layout`, else `prod_layout_group`), primary board `iqp_part_material.gram_weight`, colour setting `cfg_color` (first number: "4/0" and "4/4" = 4 colours), customer tier from CO -> customer (`cust_customer.customer_tier`, default 2, clamped to 1 to 3), die code in this order: part `dieline_ref`, part `die_code`, master card `knife_code`, layout `knife_code`.
2. Write to `prd_wo`: `ups_per_sheet`, `sheet_length_mm`, `sheet_width_mm`, `board_gsm`, `colour_count`, `dieline_ref` ONLY when still empty (`??`, `WoPlanningEnricher.php:31-36`); `customer_tier` and `layout_json` are overwritten every time (`:37-38`).
3. For each operation in `seq_no` order (`:44-57`): find the process (stored `process_id`; else exact name match in upper case; else name prefix, `:62-71`); `qty_uom ??=` waste unit, else `LBR` for stage PRESS / POSTPRESS / PREPRESS or a PLONG process when the WO has ups, else `PCS` (`:74-79`); `qty_out` = WO qty for the last operation, otherwise quantity of the next operation (divided by ups and rounded up when the unit changes from sheets to pieces, `:81-93`).
4. Candidate machines (`candidates`, `:96-138`): ask `MachineCapabilityService::machinesForProcess` with sheet length and width, grammage and quantity. A machine is NOT eligible when: inactive; status `MAINTENANCE`, `BREAKDOWN` or `RETIRED` (`ProdMachine.php:32`); not a process machine; sheet larger than the machine (either orientation) or smaller than its minimum; grammage outside min and max; for print processes (`CETAK...`) more colours than `max_colors_per_pass` (`:109`).
5. Delete old rows of `prd_wo_operation_machine` of the operation and insert one row per machine: `eligible`, `reason` (English, 190 characters), `preference_rank`, `target_qty_per_hour`, `setup_minutes` (`:103-126`).
6. First eligible machine fills the operation: `rate_band_value ??=` its rate (never overwrites an existing rate), `setup_base_min` = its setup, `run_minutes = ceil(qty / rate x 60)` (`:129-135`).

## Input / Output
- **Input**: `prd_wo`, `prd_wo_operation`; `iqp_part`, `iqp_layout`, `iqp_part_material`, `co_order`, `prod_part`, `prod_layout_group`, `prod_master_card`, `cust_customer`; `prod_process`, `prod_process_machine`, `prod_machine`.
- **Output (writes DB)**: `prd_wo` (header facts), `prd_wo_operation` (`process_id`, `qty_uom`, `qty_out`, `rate_band_value`, `setup_base_min`, `run_minutes`), `prd_wo_operation_machine` (rows deleted and rewritten).
- **Display only**: nothing. The "reason" text is shown on the Work Order form.

## Formula (one place)
Three layers for each parameter of a machine (`MachineCapabilityService::resolveParams`, `MachineCapabilityService.php:82-107`): (1) the process x machine row `prod_process_machine`, (2) the process default (`prod_process.default_*`), (3) for speed and manpower the machine value (`prod_machine.speed_target_per_hour`, `operator_min`). A parameter with no value in any layer is `MISSING` (null); it is never replaced by 0. Run minutes `= ceil(qty / rate x 60)`; sheets and pieces follow the unit rule of step 3.

### Worked example (computed from `ddt*`, read only, 2026-10-17)
`prd_wo_operation_machine` holds 1719 rows: 1357 eligible and 362 not eligible. Of the 1357 eligible rows, 464 have no `target_qty_per_hour` and 1103 have no `setup_minutes`. Master side: of 68 active pairs in `prod_process_machine`, 31 have no speed in any of the three layers and 55 have no setup minutes in the pair or in the process default. The solver treats a missing rate as 60 minutes (`24_engine_schedule-solver.md`).

## Edge cases
- A process that is not found by name: `process_id` stays empty, no candidate rows (counted as "without machine"). In the data: 1 of 386 operations has no `process_id`.
- A process with no machine at all (QC, HITUNG, PACK, QC PACK, PACKING, HOT PRINT): the operation keeps 0 eligible rows; the solver then treats it as MANUAL or SUBCON (`23_engine_auto-plan-runner.md`). In `ddt*` 53 operations of non-cancelled WOs are in this case.
- Missing sheet size or grammage on the WO: that check is simply skipped (`blockingReasons` only checks when the value is present).

## Used by
`PpicController` (screen Work Order), `AutoPlanRunner::prepare`.

## Open items
`docs/ENGINE-ATTENTION.md`: ATTENTION-ENGINE-04 (units not converted between BOM lines and stock) touches the unit rule above; ATTENTION-ENGINE-08 (HOT PRINT / FOIL STAMPING with `prod_process.inhouse = 0`).

## Tests
`tests/Feature/WoBopTimesTest.php`, `tests/Feature/WoEngineVerifyTest.php`, `tests/Feature/MachineProcessCapabilityTest.php` (25 tests on the capability service).

## Relations
- **source**: `20_engine_wo-generation.md` (WO and operations), ProDev layout (`../../sales/prodev/10_card_prodev-interface.md`), master `../ppic/10_card_machine.md`, `../ppic/11_card_process-and-routing.md`.
- **used_by**: `22_engine_gate-service.md`, `23_engine_auto-plan-runner.md`, `24_engine_schedule-solver.md` (reads the candidate rows).
- **flows**: WO created -> enrich -> gates -> plan.
- **frozen_zone**: none on this side.
- **also_named_by**: `16_card_upstream-interface.md`, `20_engine_speed-target.md`.

## Findings (recorded, not fixed)
1. **`prod_process_machine.is_certified` is not checked.** The old plan note (`docs/design-notes/ppic/15_ppic-tech-wo-knowledge.md`, item 4) says a machine is eligible only with certification; no planning code reads the column (grep `is_certified` outside Models and Grid: no result). FACT.
2. **Die and plate "match" is not part of eligibility**, also claimed by that note: eligibility checks size, grammage, status and colours only (`MachineCapabilityService.php:132-183`, `WoPlanningEnricher.php:109`). Tooling is handled by the gate (`22_engine_gate-service.md`). FACT.
3. **Mode in the registry is `suggest`** (`EngineRegistry.php:1954`) but the class writes three tables directly with no officer step. FACT.
4. **The old note says `prd_wo` keeps a frozen copy of grain, yield and model**; `prd_wo` has no such columns (`show columns`; it has `ups_per_sheet`, sheet size, `board_gsm`, `colour_count`, `dieline_ref`, `layout_json`). The grain direction and yield may sit inside `layout_json`: NOT checked. FACT for the columns.
5. **Plate code is invented** by the loader: `'PL-'.product_id` (`PlanLoader.php:164`); it is not read from `prod_tooling_asset`. See `25_engine_setup-time.md`.

## Already exists? Searched where, result
`concept:find "machine"` / `"gate"`: no earlier TG card for eligibility. Searched code: the one place that inserts `prd_wo_operation_machine` rows is this class (`WoPlanningEnricher.php:126`); other writers only edit or delete (`PpicController.php:593` target edit, `:852` and `SheetReset.php:106` delete). The three-layer rule is also named in `MachineCapabilityService` comments as the single place; ProCost use of it was NOT checked.
