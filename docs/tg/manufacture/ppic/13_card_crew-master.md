# Concept card: Work-center skills (the Master Crew list was removed)

> Written 2026-10-17 (Manufacture group), updated 2026-10-18. "FACT" = seen first-hand: mockup DB (`--env=mockup`; counts = `count(*)`) and code with line numbers. "FROM DOCUMENT" = not checked. Employees are REAL master data: only read here. The crew of a machine per shift (Assign Crew) is in `../planning/19_card_assign-crew.md`.

- **Document type**: concept card. **Module/sub-module**: HRM data read by Manufacture > Planning and Production Output.
- **Canonical name**: Work-center skill = `tm2mst.emp_employee_work_center_skill` (253 active rows, 154 employees, 36 of 57 work centers), which belongs to HRM.
- **Synonyms**: skill, work center, operator list, kru.
- **NOT the same as**: the crew of a machine per shift (`prod_machine_crew*`), the operator assignment on one operation (`prd_crew_assignment`), the HR roster.
- **Removed 2026-10-18 (duplicate module audit, Jo agreed A6)**: the screen Manufacture > PPIC > PPIC Settings > Master Crew (`v2:prod-crew`) and its table `prod_crew` (217 names migrated from `tm2old.mst_machine`, `employee_id` empty on all rows, no reader). A copy of the rows is in `tmerp_unused/prod_crew_tm2mst_2026-10-18.json`; the source data still sits in `tm2old.mst_machine`. Migration `2026_10_18_000300_remove_master_crew_prod_crew.php`.

## Facts
| Item | Content | Evidence |
|---|---|---|
| Table | `emp_employee_work_center_skill`: `employee_id`, `work_center_id`. | FACT, `show columns` |
| What Production Output uses | `emp_employee_work_center_skill` through `prd_wo_operation.crew_work_center_id` (the BOP "actor" cell, `C:` + work center id) to find the queue of the operator that logs in. 0 of 386 operations have a work center set today. | FACT, `app/Support/V2/Data/ProductionOutput.php:96-107`; query |
| PPIC Settings text | "Operator skills per process: No screen and no data model yet". | FACT, `ppic-settings.blade.php` |

## Relations
- **source**: HRM employees, HR work centers.
- **used_by**: the HR skill table feeds `../planning/11_card_wo-operation-bop.md` (actor cell) and `21_engine_operator-timer.md` (queue lookup).
- **flows**: HR skill -> BOP actor cell -> operator queue.
- **frozen_zone**: employee data is REAL, never changed by PPIC (and never `employee_id = 1` for tests).

## Findings (recorded, not fixed)
1. **Three crew models remain** with no join between them: Assign Crew (`prod_machine_crew*`), `prd_crew_assignment`, and the HR work-center skills; the planner uses none as capacity. See `../planning/19_card_assign-crew.md` finding 1. FACT.

## Already exists? Searched where, result
`concept:find "crew"`: tables and models only. The duplicate Master Crew list was found by the module audit and removed. The user guide `docs/guides/production/03_learning_timer_crew.md` explains the crew screens.
