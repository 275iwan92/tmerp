# Concept card: Learning and changeover values

> Written 2026-10-17 (Manufacture group). "FACT" = seen first-hand in this session: mockup DB (`--env=mockup`; counts = `count(*)` today) and code with line numbers. "FROM DOCUMENT" = not checked. Logic is in `27_engine_learning.md` and `25_engine_setup-time.md`; the master screens are in `../ppic/15_card_changeover-and-wait-rules.md`. This card is the data and the screen Learning Changes.

- **Document type**: concept card. **Module/sub-module**: Manufacture > Planning > Learning Changes (`PROD_LEARNING`, `v2:production-learning`); master side Manufacture > PPIC > Setup and Changeover.
- **Canonical name**: Changeover value = a row of `tm2mst.prod_machine_changeover` (extra minutes and extra waste sheets when a machine changes colour, ink type, substrate, size, plate or die). Learning evidence = `tm2prd.prd_operation_time_log` (timer segments) and the decisions `LEARN` / `LEARN_REVERT` in `tm2prd.prd_plan_decision`.
- **Synonyms**: Assumed / Learning / Calibrated value, Frozen, "menit cuci", setup learning, Learn now.
- **NOT the same as**: base setup minutes per process x machine (`prod_process_machine.setup_minutes`), the wait rules after a process (`prod_wait_rule`), the speed targets (`../ppic/20_engine_speed-target.md`).

## Facts
| Item | Content | Evidence |
|---|---|---|
| Value table | `prod_machine_changeover`: 0 rows. PK `changeover_id`; columns `machine_id`, `change_kind` (`COLOR`, `INK_TYPE`, `SUBSTRATE`, `SIZE`, `PLATE`, `DIE`), `from_value`, `to_value`, `extra_minutes`, `extra_waste_qty`, `note`, `activate`, `value_status` (`ASSUMED`, `LEARNING`, `CALIBRATED`), `sample_count`, `frozen`. | FACT, `show columns`; `ChangeoverLookup::KINDS`, `STATUSES` |
| Start values | Without a row, the start values of `config/ppic.php` apply: colour partial wash 20 min / 30 sheets, colour full wash 38 / 60, ink type 55 / 80, board 15 / 20, size 12 / 15, die 15, plate 10. | FACT, `config/ppic.php` |
| Time log | `prd_operation_time_log`: 5 rows (SETUP 3, RUN 2) with `operation_id`, `machine_id`, `operator`, `kind` (SETUP, RUN, PAUSE), `pause_reason`, `change_kind`, `started_at`, `ended_at`, `minutes`. `change_kind` is NULL in all 5. | FACT, query |
| Log of changes | A change by the engine is a decision `LEARN` (old and new text `prod_machine_changeover#id extra_minutes=n`), a put-back is `LEARN_REVERT`; the screen lists the last 60 and offers "Put back and freeze" for the open ones. In `ddt*` both events: 0 rows. | FACT, `production-learning.blade.php`; query |
| Screen | Learning Changes: button Learn now (`PPIC_SCHEDULING`), select "Put a change back", table Changeover values (machine, change, extra minutes, status, samples, frozen) and the Log. Values are typed on Setup and Changeover, not here. | FACT, `production-learning.blade.php`, `PpicController.php:1109-1129` |
| Limits | Constants `PPIC_LEARN_MIN_SAMPLES` (5), `PPIC_LEARN_BLEND` (0.3), `PPIC_LEARN_MAX_STEP_PCT` (20), `PPIC_BASE_SETUP_MIN` (15) in group PPIC of `sys_constant`. | FACT, `sys_constant` query |

## Relations
- **source**: operator timer (`../ppic/21_engine_operator-timer.md`) for the segments, constants (`../ppic/16_card_planning-parameters.md`).
- **used_by**: `27_engine_learning.md`; the planner should read the values through `25_engine_setup-time.md` (it does not, see finding 1).
- **flows**: operator Play / Stop of SETUP -> segment -> (needs `change_kind`) -> learn -> value -> (expected) plan.
- **frozen_zone**: none; the table is PPIC master data.

## Findings (recorded, not fixed)
1. **The two ends of the loop are missing**: nobody writes `change_kind` (so no sample ever exists) and nobody reads the values for planning. Details with evidence: `27_engine_learning.md` findings 1 and 2, `25_engine_setup-time.md` finding 1. FACT.
2. **The master screen text claims scheduling uses the values**: guide of Setup and Changeover ("Scheduling adds them to the base setup between two jobs") and the REMARK of Colour Sequence list `prod_machine_changeover` as a source; the code of both does not read it. FACT.
3. **Status CALIBRATED ends learning** (`LearningEngine.php:42`): a row becomes CALIBRATED at 15 samples (3 x 5) and is then never adjusted again, even if the plant changes. Only a person can reopen it (set the status back). FACT (code).
4. **Open timer segments are ignored by the learner**: 1 SETUP and 2 RUN segments in `ddt*` have no end time since 13-Oct and the sample query requires `ended_at` (`LearningEngine.php:97`). Recorded so nobody expects them to count. FACT (code, query).

## Already exists? Searched where, result
`concept:find "learning"`, `"shift"`: no card existed. Searched: three learning loops exist (changeover here, job order `18_card_order-suggestion-ml.md`, speed `../ppic/20_engine_speed-target.md`) and ProDev's usage rate (`../../sales/prodev/30_engine_usage-rate.md`); they share no table.
