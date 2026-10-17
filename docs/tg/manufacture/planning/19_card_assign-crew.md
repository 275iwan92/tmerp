# Concept card: Assign Crew (crew per machine and shift)

> Written 2026-10-17 (Manufacture group). "FACT" = seen first-hand in this session: mockup DB (`--env=mockup`; counts = `count(*)` today), code with line numbers, tests I ran. "FROM DOCUMENT" = not checked. Master Crew (employee x process) is in `../ppic/13_card_crew-master.md`.

- **Document type**: concept card. **Module/sub-module**: Manufacture > Planning > Assign Crew (`PROD_CREW`, `v2:crew` grid and `v2:crew-form?machine=`).
- **Canonical name**: Machine crew = `tm2mst.prod_machine_crew` (one crew of a machine in a production shift) + `prod_machine_crew_period` (validity dates) + `prod_machine_crew_member` (employee and role). Rules in `MachineCrewService`.
- **Synonyms**: crew, Assign Crew, regu mesin, crew of the shift, `crew_index`, Main operator / Co-operator / Helper / Kabag.
- **NOT the same as**: Master Crew `prod_crew` (employee x process list, `../ppic/13_card_crew-master.md`), the per-operation assignment `prd_crew_assignment` (operator on a job), the HR roster `emp_shift_roster` (`../ppic/12_card_shift-and-calendar.md`).

## Facts
| Item | Content | Evidence |
|---|---|---|
| Tables | `prod_machine_crew`: 18 rows (all active) on 6 machines, 3 shifts each (`SH1`, `SH2`, `REG`); columns `machine_id`, `crew_index`, `shift_id`, `activate`. `prod_machine_crew_period`: 18 rows from 2026-10-14, open end; columns `id_machine_crew`, `valid_from`, `valid_to`. `prod_machine_crew_member`: 54 rows (3 per crew: Main operator 18, Co-operator 18, Helper 18); columns `id_crew_period`, `employee_id`, `role`. | FACT, `show columns`; query |
| Which machines | Active process machines of division OFFSET only (`assignableMachines`): 26 machines; 6 have a crew. A machine needs a nickname before it can get a crew. | FACT, `MachineCrewService.php:44-47,92-99`; query |
| Shifts | One crew per production shift of the shift master (`emp_shift`) of the production departments (PRA-CETAK, PRESS, CETAK, PLONG-DIECUT, LAMINASI, LAMINATING, POTONG, FOLDER-GLUE, KUPAS, SHRINK, MANUAL, HARIAN-LEPAS): REG first, then by code. The number of crews per machine is capped by the number of such shifts. | FACT, `MachineCrewService.php:35-60` |
| Rules on save | At most 10 members; a person only once per crew; every member needs one of the roles `Main operator`, `Co-operator`, `Helper`, `Kabag`; only active production employees; periods of one crew may not overlap; one person may be in several crews but not in the same shift in overlapping periods; the whole machine form saves all boxes or none; an empty box deactivates that crew (never deletes). | FACT, `MachineCrewService.php:128-215`; test `tests/Feature/MachineCrewServiceTest.php` (12 passed under `--env=mockup`, 51 assertions) |
| Where it is read | The grid itself, and the column Crew of the table "On the shop floor now" in the PPIC Workspace (`MachineCrewService::namesOn`). Nothing else. | FACT, grep `MachineCrewService`, `ProdMachineCrew` |
| Operation-level crew | `prd_crew_assignment` (per operation: `employee_id`, `crew_role`): 0 rows and NO writer in `app/` (only deletes in `PpicController.php:853` and `SheetReset.php:107`). The BOP sheet actor `crew_work_center_id` is the other link (0 set). | FACT, grep, query |

## Relations
- **source**: employees and positions (HRM master), shift master, machine master (`../ppic/10_card_machine.md`).
- **used_by**: the PPIC Workspace column Crew (display), HR roster (separate), Production Output through the work center, not through this table (`../ppic/21_engine_operator-timer.md`).
- **flows**: officer fills the machine form -> crew + period + members -> displayed beside running jobs. It does not feed the solver.
- **frozen_zone**: none. Master data of employees is REAL: this module reads it only.

## Findings (recorded, not fixed)
1. **Four unconnected crew models exist**: (a) `prod_machine_crew*` (this card, display only), (b) `prod_crew` Master Crew (employee x process, 217 rows, `../ppic/13_card_crew-master.md`), (c) `prd_crew_assignment` (per operation, 0 rows, no writer), (d) HR work-center skills `emp_employee_work_center_skill` (253 rows, 154 employees) which is what Production Output really uses to find an operator's queue (`app/Support/V2/Data/ProductionOutput.php:96-107`). The planner uses none of them as capacity (PPIC Settings says so for Master Crew). FACT.
2. **The Assign Crew REMARK says nothing reaches the operator screen**: the Production Output REMARK still names `prd_crew_assignment` as its source and "Penugasan crew ke operasi belum punya layar" (no screen assigns a crew to an operation). The new crews of this card do not decide who sees which job. FACT.
3. **Only 6 of 26 Offset machines have a crew** and the dates start on 2026-10-14; there is no end date, so the crews never expire (periods are open). FACT (query).
4. **Names shown in the Workspace come from the period that covers today** (`covers(date)`): the shop-floor table shows today's crew, not the crew of the shift in which the job runs. FACT (code: `namesOn(crews, shiftId, date)`).
5. **A crew conflict test exists only for the old board**: `CrewAvailabilityChecker` reads `prd_crew_assignment` (empty), so it can never find a clash (`29_engine_pdr.md`). FACT.
6. **Known scope limit**: the screen text states that manual work stations (QC, hand glue, manual peeling) have no crew yet; the REMARK of `MachineCrewService.php` says the same ("belum punya crew"). FACT.

## Already exists? Searched where, result
`concept:find "crew"`: no TG card; tables and models only. Searched: Assign Crew is a new model set (2026-10-14) next to older ones; nothing was removed. User's guide: `docs/guides/production/03_learning_timer_crew.md`.
