# Concept card: Machine (Master Machine and Machine Model Catalog)

> Written 2026-10-17 (Manufacture group). "FACT" = seen first-hand in this session: mockup DB (`--env=mockup`; counts = `count(*)` today) and code with line numbers. "FROM DOCUMENT" = not checked. **Master data of machines is REAL**: this card only READ it; nothing was changed and no machine specification was invented.

- **Document type**: concept card. **Module/sub-module**: Manufacture > PPIC > PPIC Settings > Master Machine (`MD_MACHINE`, `v2:prod-machine`; the same slug is also under Maintenance as `MAINT_MACHINE`) and Machine Model Catalog (`PROD_MACHINE_MODEL`, `v2:prod-machine-model`).
- **Canonical name**: Machine = `tm2mst.prod_machine` (45 rows) with downtime history `prod_machine_downtime` (10 rows) and the model catalog `prod_machine_model` (32 rows). The capability of a machine for a process is a separate table (`prod_process_machine`, card `11_card_process-and-routing.md`).
- **Synonyms**: mesin, machine_code (`OFF-01`), nickname, asset (Maintenance links it through `mnt_asset.machine_id`), resource.
- **NOT the same as**: a work center of HRM (`emp_work_center`, 57 rows), a Maintenance asset (`mnt_asset`, 48 rows, 45 linked to a machine), a tooling asset (`prod_tooling_asset`).

## Facts
| Item | Content | Evidence |
|---|---|---|
| Table | `prod_machine`: 45 rows, PK `machine_id`, all `data_origin = LIVE`; `division_id` (OFFSET 28, DIGITAL 17), `machine_type` (for example Cutting Plotter 9, Digital Printing 6, Die Cutting 4, Paper Cutting 3, Laminating 3, Stitching 3, Offset Printing 2), `nickname`, `machine_model_id`, size limits (`max_sheet_length_mm` and others), `gsm_min`, `gsm_max`, `max_colors_per_pass`, `max_speed_per_hour`, `speed_target_per_hour`, `shifts_per_day`, `hours_per_shift`, `operator_min`, `hourly_rate` (cost, confidential, not read by PPIC), `machine_status`, `activate`, `is_process_machine`. | FACT, `show columns`; query |
| Status | `machine_status`: READY 43, BREAKDOWN 1 (`OFF-02`, open downtime since 2026-10-01), MAINTENANCE 1 (`CMP-01`, planned maintenance since 2026-10-01). Blocking statuses: MAINTENANCE, BREAKDOWN, RETIRED (`ProdMachine::STATUSES_BLOCKING`, `ProdMachine.php:32`). | FACT, query; code |
| Process machine | `is_process_machine = 1` for 42 machines; 3 are supporting equipment (2 READY, 1 MAINTENANCE; not planned). The planner and Assign Crew take only process machines. | FACT, query; `WoPlanningEnricher`/`MachineCapabilityService.php:132-150` |
| Shift pattern read by the planner | `shifts_per_day` and `hours_per_shift` only: 34 active machines 1 shift x 8.0 h, 11 machines 2 shifts x 7.8 h (the two offset presses are among them). The shift master and the roster are NOT read (`../planning/24_engine_schedule-solver.md`). | FACT, query; `PlanLoader.php:91-100` |
| Speed | Three layers: pair row (`prod_process_machine.speed_per_hour`), process default (`prod_process.default_speed_per_hour`), machine target (`speed_target_per_hour`). `speed_target_per_hour` is filled on 22 of 45 machines; `max_speed_per_hour` on 12. | FACT, query; `MachineCapabilityService.php:82-107` |
| Downtime | `prod_machine_downtime`: 10 rows, 2 open (`end_at` NULL: `OFF-02` BREAKDOWN, `CMP-01` PLANNED_MAINT); a created or closed downtime triggers a new Draft plan (`MachineDowntimeObserver`). | FACT, query; `app/Observers/MachineDowntimeObserver.php` |
| Used by the planner | Candidate eligibility (size either orientation, grammage, status, colours per pass for print), calendar (shifts), speed layers, skip when status is blocking, load and bottleneck percent. | FACT, `WoPlanningEnricher.php`, `PlanLoader.php:50,91-100`, `Ppic::exceptions` |
| Screen | v2 grid over the v1 form: the grid is `MachineGrid` (columns machine, nickname, model, role, type, max speed, year); the edit form is the v1 Livewire component `pages::master.machine`. | FACT, `MachineGrid.php:25-65` |

## Relations
- **source**: model catalog `prod_machine_model` (picking a model fills the general spec into the form; the officer can still change it), Maintenance (status through downtime and orders), HRM (division, work center).
- **used_by**: `../planning/21_engine_wo-planning-enricher.md`, the planner (`../planning/23_engine_auto-plan-runner.md`), Assign Crew (`../planning/19_card_assign-crew.md`), speed targets (`20_engine_speed-target.md`), Live Schedule and OEE (`app/Services/Production/MachineOeeService.php`), Maintenance (no TG card exists yet).
- **flows**: master row -> candidate rows of each WO operation (`prd_wo_operation_machine`) -> placements.
- **frozen_zone**: REAL master data, never given `data_origin = DUMMY`, specs never invented.
- **also_named_by**: `11_card_wo-operation-bop.md`, `12_card_shift-and-calendar.md`.

## Findings (recorded, not fixed)
1. **The two offset presses have no `max_colors_per_pass`** (NULL on both `OFF-01` and `OFF-02`); the eligibility check "needs more colours than the machine prints per pass" is skipped for them (`WoPlanningEnricher.php:109` requires a value above 0). With `OFF-02` in BREAKDOWN, `OFF-01` is the only press the planner can use, so print is a single-machine bottleneck today. FACT (query, code).
2. **18 of the 41 READY process machines have no sheet size limit** (`max_sheet_length_mm` empty): they never fail the size test. 20 of the 41 have no `speed_target_per_hour`. Data completeness, not a code fault. FACT (query).
3. **`hourly_rate` and `acquisition_cost` are cost fields inside a table that PPIC screens read**; the PPIC code does not select them (grep), but any new column list on this table must keep them out of PPIC screens (A.8 and A.9). FACT (grep).
4. **A machine in MAINTENANCE status for a planned job is a blocking status for new work**, yet the PM calendar of Maintenance is not read; only the status flag is (`../planning/32_engine_maintenance-block.md`). FACT.
5. **The same slug `prod-machine` is mounted twice in the menu** (`MD_MACHINE` under PPIC Settings and `MAINT_MACHINE` under Maintenance Master Data). FACT (menu query).

## Already exists? Searched where, result
`concept:find "machine"`: only the engine map and the inventory tool register mention machines; no TG card for the machine master existed. Searched: one machine table (`prod_machine`); legacy `cac_idx_machine` (Costing) is a different, old costing table.
