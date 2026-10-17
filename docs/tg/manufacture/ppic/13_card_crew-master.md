# Concept card: Master Crew and work-center skills

> Written 2026-10-17 (Manufacture group). "FACT" = seen first-hand in this session: mockup DB (`--env=mockup`; counts = `count(*)` today) and code with line numbers. "FROM DOCUMENT" = not checked. Employees are REAL master data: only read here. The crew of a machine per shift (Assign Crew) is in `../planning/19_card_assign-crew.md`.

- **Document type**: concept card. **Module/sub-module**: Manufacture > PPIC > PPIC Settings > Master Crew (`PROD_MST_CREW`, `v2:prod-crew`).
- **Canonical name**: Master Crew = `tm2mst.prod_crew` (217 rows: a person by name for a process). Work-center skill = `tm2mst.emp_employee_work_center_skill` (253 active rows, 154 employees, 36 of 57 work centers), which belongs to HRM.
- **Synonyms**: kru, operator list, crew per process, `crew_name`, skill, work center.
- **NOT the same as**: the crew of a machine per shift (`prod_machine_crew*`), the operator assignment on one operation (`prd_crew_assignment`), the HR roster.

## Facts
| Item | Content | Evidence |
|---|---|---|
| Table | `prod_crew`: 217 rows, PK `id_crew`; columns `crew_name`, `process_id`, `employee_id`, `legacy_employee_code`, `notes`, `activate`. `employee_id` is NULL on ALL 217 rows; `legacy_employee_code` is filled on 87; every `crew_name` is distinct. By process: KUPAS 80, LAMINASI 12, QC PACK 9, PLONG 6, CETAK 5, UV 5, QC CETAK 2, QC 1 (top rows of the list). | FACT, `show columns`; query |
| Screen | v2 grid `CrewGrid` over the v1 form `pages::production.master-crew` (create, edit, delete). | FACT, `CrewGrid.php`, `master-crew.blade.php:54-134` |
| Readers | None in the planner or in Production Output. The only code that touches the table outside its own screen is `EmployeeMergeService` (re-pointing `employee_id` when two employee records are merged). | FACT, grep `prod_crew`, `ProdCrew` |
| PPIC Settings text | "Master Crew: Not used as capacity by this planner yet" and "Operator skills per process: No screen and no data model yet". | FACT, `ppic-settings.blade.php` |
| What Production Output uses | `emp_employee_work_center_skill` (`employee_id`, `work_center_id`) through `prd_wo_operation.crew_work_center_id` (the BOP "actor" cell, `C:` + work center id) to find the queue of the operator that logs in. 0 of 386 operations have a work center set today. | FACT, `app/Support/V2/Data/ProductionOutput.php:96-107`; query |

## Relations
- **source**: HRM employees, Master Process (`11_card_process-and-routing.md`), HR work centers.
- **used_by**: nothing in planning; the HR skill table feeds `../planning/11_card_wo-operation-bop.md` (actor cell) and `21_engine_operator-timer.md` (queue lookup).
- **flows**: HR skill -> BOP actor cell -> operator queue. Master Crew -> nowhere.
- **frozen_zone**: employee data is REAL, never changed by PPIC (and never `employee_id = 1` for tests).

## Findings (recorded, not fixed)
1. **Master Crew is an isolated list**: names only, no link to the employee master (`employee_id` empty on 217 of 217), no reader. It cannot be used as capacity or for conflict checks until each row points to an employee. FACT.
2. **Two skill concepts**: process-based (`prod_crew.process_id`) and work-center-based (`emp_employee_work_center_skill`); the work-center one is the live one. FACT.
3. **Four crew models in total** with no join between them: see `../planning/19_card_assign-crew.md` finding 1. FACT.
4. **Names in capitals and abbreviations** (for example initials) come from the legacy data; duplicates by person cannot be detected without the employee link. FACT (distinct names 217 = rows).

## Already exists? Searched where, result
`concept:find "crew"`: tables and models only; no TG card. Searched: no other table lists operators by process. The user guide `docs/guides/production/03_learning_timer_crew.md` explains the crew screens.
