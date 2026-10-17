# Sub-module: Planning (Manufacture > Planning)

> Written 2026-10-17 (Manufacture group). This page only summarizes and links; table, column and engine facts are in the cards. FACT = seen first-hand in this session (mockup DB with `--env=mockup`, code, tests I ran). The menu was read from `sys_app_menu` (code `PROD_PLAN`).

- **Module**: Manufacture (`PROD`) > Planning (`PROD_PLAN`). Menu rows (FACT, 14 rows, 11 active):
  - Work Order (`v2:production-wo`), PPIC Workspace (`v2:production-scheduling`), Live Schedule (`v2:production-live`), Assign Crew (`v2:crew`), Colour Sequence (`v2:ppic-color-sequence`), Learning Changes (`v2:production-learning`),
  - Demand Forecast (MPS) (`v2:demand-forecast`), Pre-Booking (`v2:material-prebooking`), Material Booking (`v2:material-booking`), Material Planning (MRP) (`v2:material-planning`),
  - Target Rate: Process x Machine / Employee: active in the menu but with NO target and status NOT_BUILT (row "Rate per product" of `../ppic/11_card_process-and-routing.md`).
  - Inactive old items: MPS - Kanban Board and MPS - Spreadsheet (legacy ScriptCase files, not repo files).
- **Purpose**: turn a Posted delivery line into a Work Order, enrich it with the facts scheduling needs, decide when and on which machine each step runs (engine proposes a Draft, the officer decides), keep the live schedule, and show the material the Work Orders need.
- **Frozen zone**: upstream, the CO and CPDEL flow and ProDev (BOP/BOM) are frozen; this sub-module only continues them. ProCost owns reserve and booking. Read `docs/ENGINE-ATTENTION.md` before touching an engine.
- **Rules**: A.3 (output can be recorded for a simple WO), A.7 (engine suggests, officer decides), A.16 (Post to Production), Jo 2026-10-13 (BOM formula, Available), 2026-10-14 (apply only when material is ready; officer swaps freely before Apply; a problem job must not hold its machine; schedule guard), CLAUDE.md section 2 (PPIC screens: fast and accurate; no ProDev/ProCost logic here).

## Relations
- **source**: Sales (CPDEL row, CO, Inquiry part, ProDev BOP/BOM/layout/Master Card): card `16_card_upstream-interface.md`; masters in `../ppic/`.
- **used_by**: Production Output (operator queue), Warehouse (pick list), Procurement (PR/PO from shortages), Delivery (dates), Maintenance (machine status).
- **flags**: access `PPIC_SCHEDULING` changes the plan; `PPIC_INVENTORY` is defined but not checked anywhere.

## Cards and engines in this sub-module
| Type | File | Content |
|---|---|---|
| Concept | `10_card_work-order.md` | `prd_wo`: header, status machine, source, priority, PROCESS stamp, delete |
| Concept | `11_card_wo-operation-bop.md` | `prd_wo_operation` and candidate machines: the BOP as the WO sees it |
| Concept | `12_card_wo-material-bom.md` | `prd_wo_material`: Required, Release, Purchase, Waste, Spill |
| Concept | `13_card_plan-draft-and-live.md` | Draft, Live, Simulation, runs, decisions, exceptions, Live Schedule, Colour Sequence |
| Concept | `14_card_gates-and-readiness.md` | Ready / Waiting / Blocked, APPLY rules, schedule guard |
| Concept | `15_card_material-availability.md` | Demand Forecast, Pre-Booking, Booking, Material Planning screens, Available |
| Concept | `16_card_upstream-interface.md` | what the WO takes from Inquiry, ProDev, ProCost, CPDEL |
| Concept | `17_card_learning-changeover.md` | changeover values, time log, Learning Changes screen |
| Concept | `18_card_order-suggestion-ml.md` | trace and model tables of the order suggestion |
| Concept | `19_card_assign-crew.md` | crew per machine and shift |
| ENGINE | `20_engine_wo-generation.md` | Post to Production -> WO; PROCESS rebuild of BOP and BOM |
| ENGINE | `21_engine_wo-planning-enricher.md` | facts, candidate machines, targets, setup |
| ENGINE | `22_engine_gate-service.md` | material, tooling, artwork gates |
| ENGINE | `23_engine_auto-plan-runner.md` | Draft plan runs, triggers, metrics |
| ENGINE | `24_engine_schedule-solver.md` | ranking and placement (finite capacity) |
| ENGINE | `25_engine_setup-time.md` | changeover and tool mounting minutes |
| ENGINE | `26_engine_plan-confirm.md` | Confirm, APPLY, Undo, Discard, lock |
| ENGINE | `27_engine_learning.md` | learning of changeover minutes |
| ENGINE | `28_engine_sequence-suggester.md` | job order learning (pairwise model) |
| ENGINE | `29_engine_pdr.md` | old board engine (priority score, backward schedule, rate band, calendar, crew check) |
| ENGINE | `30_engine_scheduling-rules.md` | rule library batching and sequencing (old board) |
| ENGINE | `31_engine_interruption.md` | hot job options (old board) |
| ENGINE | `32_engine_maintenance-block.md` | Maintenance windows for scheduling |
| ENGINE | not written here | Material Planning Engine: see `../../sales/order/30_engine_material-planning.md` (link only); Booking Maintenance: no card yet |

## Diagram
Not made (a text twin is in the "Flow" sections of the engine cards).

## Existing User's Guide (UG)
`docs/guides/production/00_alur_data_dan_sistem.md`, `01_work_order_bom_gates.md`, `02_planning_scheduling.md`, `03_learning_timer_crew.md` (Indonesian, written 2026-10-16 from the code). The TG does not repeat them.

## Cross-card findings (summary; details and evidence in the cards)
The WO header never moves past SCHEDULED in the v2 path; the changeover learning loop is open at both ends (nobody writes `change_kind`, nobody reads the learned values); the planner uses its own calendar (machine shifts from 07:00) and ignores the shift master, the roster, the crew and the Maintenance windows; three older engines (PDR, rule library, hot job options) serve only the old v1 board; the whole-plan Confirm skips the material check that single-WO APPLY enforces; the Live Gantt hides operations that are more than a day late. The full list with the tests run and the residue I left in `tm2*` is in `../90_findings_planning-ppic.md`.
