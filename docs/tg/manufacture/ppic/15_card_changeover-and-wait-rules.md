# Concept card: Setup and Changeover master, Wait Rules

> Written 2026-10-17 (Manufacture group). "FACT" = seen first-hand in this session: mockup DB (`--env=mockup`; counts = `count(*)` today), code with line numbers and a test I ran. "FROM DOCUMENT" = not checked. The learning side of the changeover table is in `../planning/17_card_learning-changeover.md`; the formulas are in `../planning/25_engine_setup-time.md`.

- **Document type**: concept card. **Module/sub-module**: Manufacture > PPIC > PPIC Settings > Setup and Changeover (`PROD_MST_CHANGEOVER`, `v2:prod-changeover`) and Wait Rules (`PROD_MST_WAIT_RULE`, `v2:prod-wait-rule`).
- **Canonical name**: Changeover master = `tm2mst.prod_machine_changeover` (0 rows). Wait rule = `tm2mst.prod_wait_rule` (0 rows): minutes the next process must wait after a process (ink drying, coating curing).
- **Synonyms**: setup, changeover, cuci, wash, wait after, drying time, curing, wait_after.
- **NOT the same as**: the base setup of a process x machine pair (`prod_process_machine.setup_minutes`), tooling mount minutes (`14_card_tooling.md`).

## Facts
| Item | Content | Evidence |
|---|---|---|
| Changeover master | See `../planning/17_card_learning-changeover.md` for the columns; entered per machine and kind; the screen guide says rows "override the start values in `config/ppic.php`" and that scheduling "adds them to the base setup between two jobs". | FACT, `ChangeoverLookup.php:10-12,36-39` |
| Wait rule master | `prod_wait_rule`: 0 rows; PK `id_wait_rule`; columns `after_process_id`, `after_process_name`, `kind`, `wait_minutes`, `activate`. The screen takes the start of the process name (for example `CETAK`). | FACT, `show columns`; `WaitRuleLookup.php:10-39` |
| Start values | Without rows, `config('ppic.wait_after')`: `CETAK` 120, `VARNISH` 45, `UV` 0, `LAMINASI` 0 (minutes). | FACT, `config/ppic.php` |
| How the planner applies a wait | `PlanLoader::waitRules` starts from the config array and overwrites or adds each active DB row, key = upper-case trimmed `after_process_name`; for each operation every key that is a PREFIX of the upper-case `process_name` sets the wait, and the LAST matching key in array order wins (not the longest). The wait is added between the operation and the next one of the same WO; the machine is free during the wait; the solver adds it to `remaining` work too. | FACT, `PlanLoader.php:77-89,141-146`; `ScheduleSolver.php:253,364`; test `test_operations_are_chained_and_wait_after_is_respected` (passed) |
| Where wait shows | Colour Sequence and Machine Idle explain gaps as "waiting for the previous process plus its wait"; the order-learning feature wait_before uses it. | FACT, `Ppic.php`, `WaitBeforeFeature.php`, `tests/Feature/MachineIdleTest.php` (4 passed) |
| Who edits | A user with module PROD on the master screens (no `PPIC_SCHEDULING` check on these masters was found). | FACT, grep of `schedulerOnly` (controller only) |

## Relations
- **source**: processes (`11_card_process-and-routing.md`), constants (`16_card_planning-parameters.md`).
- **used_by**: `../planning/24_engine_schedule-solver.md`, `../planning/25_engine_setup-time.md`, `../planning/27_engine_learning.md`, `../planning/28_engine_sequence-suggester.md`.
- **flows**: wait rule -> wait minutes between operations -> start of the next operation.
- **frozen_zone**: none.
- **also_named_by**: `23_engine_auto-plan-runner.md`.

## Findings (recorded, not fixed)
1. **Wait rules match by prefix in the planner but by "contains" in the order-learning feature** (`WaitBeforeFeature.php`: str_contains), so for a process named `QC CETAK` the feature sees the 120-minute wait of `CETAK` while the planner does not apply it. FACT (code).
2. **The last matching key wins, not the longest**: if a rule `CETAK` (120) and a rule `CETAK 4 WARNA` were both entered, the one that comes later in array order decides, depending on insertion order (config keys first, new DB keys appended). FACT (code).
3. **`after_process_id` and `kind` of the wait rule are not read**; only the name and minutes are. FACT (grep).
4. **The config start values include `LAMINASI` 0 and `UV` 0**; the processes `LAMINATING GLOSSY`, `LAMINATING DOFT`, `VARNISH`, `WAXING` follow no rule except `VARNISH`. Not necessarily wrong; recorded because the master is empty and the plan uses only these four. FACT.
5. **Screen text for Setup and Changeover claims an effect the planner does not have** (`../planning/25_engine_setup-time.md` finding 1). FACT.

## Already exists? Searched where, result
`concept:find "machine"`: none for these. Searched: wait minutes exist only in `config/ppic.php` and `prod_wait_rule`; changeover only in `prod_machine_changeover` and the config start values.
