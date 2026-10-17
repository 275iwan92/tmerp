# ENGINE card: Learning Engine (changeover)

> Written 2026-10-17 (Manufacture group). FACT = code with line numbers and mockup DB (`--env=mockup`, `tm2*` = `ddt*`) seen this session. Source read in full: `app/Services/Scheduling/Learning/LearningEngine.php`, `app/Console/Commands/PpicLearn.php`, `PpicController.php` (learn actions), `resources/views/v2/screens/production-learning.blade.php`. I did NOT run the engine (it writes master data).

- **Canonical name**: Learning Engine (changeover). **Class**: `App\Services\Scheduling\Learning\LearningEngine`. **Registry**: key `ppic-learning`, mode `auto`. **Module/sub-module**: Manufacture > Planning > Learning Changes (`v2:production-learning`).
- **Synonyms**: learn, "Learn now", Assumed / Learning / Calibrated values, revert and freeze.
- **Frozen status**: not frozen. Rule in the docblock: master data is changed by users only; the engine touches nothing but the ASSUMED changeover values (`LearningEngine.php:12-18`). See finding 3.
- **Concept card**: `17_card_learning-changeover.md`.

## Trigger
FACT, searched `routes/console.php`, controllers, commands:
| Trigger | File:line |
|---|---|
| Background, daily 03:30, only when table `prd_plan_run` exists | `routes/console.php:45` (command `ppic:learn`, `app/Console/Commands/PpicLearn.php`) |
| Button Learn now on the screen | `app/Http/Controllers/V2/PpicController.php:1109-1115` (route `v2.ppic.learn.run`) |
| Button Put back and freeze | `PpicController.php:1117-1129` (`revert`) |

## Flow (text twin)
`run(actor)` (`LearningEngine.php:25-73`):
1. Settings: minimum samples `PPIC_LEARN_MIN_SAMPLES` (5), blend `PPIC_LEARN_BLEND` (0.3), max step `PPIC_LEARN_MAX_STEP_PCT` (20 percent), base setup `PPIC_BASE_SETUP_MIN` (15).
2. Samples (`samples`, `:95-107`): segments of `prd_operation_time_log` with `kind = SETUP`, a NOT NULL `change_kind` and an end time, summed per operation, machine and change kind; per "machine|kind" the list of minutes, oldest first, and the end time of the newest.
3. For each key with at least the minimum number of samples and a known kind (COLOR, INK_TYPE, SUBSTRATE, SIZE, PLATE, DIE): `recent` = the last N samples, `typical = max(0, median(recent) - base)`.
4. Skip when the row for (machine, kind) is frozen or its status is not ASSUMED / LEARNING / empty (a CALIBRATED value stops learning), or when the newest sample is not newer than the row's `updated_at` (so a repeated run never drifts on the same samples) (`:41-47`).
5. `old` = the row's `extra_minutes`, else the start value of the kind from `config('ppic.setup')`; `new = round(old + clamp((typical - old) x blend, -limit, +limit))`, `limit = max(1, old x max step)`; no change when `new = old` and a row exists (`:49-55`).
6. In one transaction on `tm2mst`: create the row if missing (status ASSUMED, note "Created by the learning engine"), set `extra_minutes = new`, `value_status` = CALIBRATED when `samples >= 3 x N` else LEARNING, `sample_count`; write decision `LEARN` to `prd_plan_decision` with old and new text `prod_machine_changeover#<id> extra_minutes=<n>` (`:57-67`).
7. `revert(decisionId)` (`:76-92`): only a `LEARN` row that is not yet reverted; puts the old minutes back, sets `frozen = 1`, writes decision `LEARN_REVERT`.

## Input / Output
- **Input**: `prd_operation_time_log` (SETUP segments), `prod_machine_changeover`, constants `PPIC_*`, `config/ppic.php` start values.
- **Output (writes DB)**: `tm2mst.prod_machine_changeover` (creates and updates rows), `tm2prd.prd_plan_decision` (`LEARN`, `LEARN_REVERT`).
- **Who may overwrite**: the officer edits rows on Setup and Changeover (`../ppic/15_card_changeover-and-wait-rules.md`); a frozen row is never touched by the engine.

## Formula (one place)
`typical = max(0, median(last N setup minutes) - base)`; `step = (typical - old) x blend`; `new = round(old + max(-limit, min(limit, step)))`; `limit = max(1, old x max_step_pct / 100)`. Units: minutes (integer). Start values (`config/ppic.php`): COLOR 20, INK_TYPE 55, SUBSTRATE 15, SIZE 12, PLATE 10, DIE 15.
A worked example is NOT written: there are no learning samples in `ddt*` (see finding 1) and the engine has no test.

## Edge cases
- Only the minimum number of samples is required; with fewer, nothing happens and nothing is logged.
- The newest sample must be newer than the last update of the row, so one night's jobs are used once.
- A row created by the engine has `created_by` = the actor (`ENGINE` or the officer).

## Used by
Screen Learning Changes, the command `ppic:learn`.

## Open items
None in `docs/ENGINE-ATTENTION.md` names this engine.

## Tests
None. `grep LearningEngine tests/` returns nothing; the screen and the command are untested.

## Relations
- **source**: operator timer (`../ppic/21_engine_operator-timer.md`), `25_engine_setup-time.md` (the consumer that should use the learned values), master `../ppic/15_card_changeover-and-wait-rules.md`.
- **used_by**: card `17_card_learning-changeover.md`; screen Learning Changes.
- **flows**: operator timer -> SETUP segments -> learn -> `prod_machine_changeover` -> (expected) setup time engine -> plan. The last arrow is missing (finding 2).
- **frozen_zone**: none, but `prod_machine_changeover` is master data.

## Findings (recorded, not fixed)
1. **The engine can never learn anything today: nothing writes `prd_operation_time_log.change_kind`.** The only code that reads the column is `LearningEngine::samples` (`:97`); `OperatorTimerService::begin` inserts operation, machine, operator, kind, pause reason and times only (`OperatorTimerService.php:269`), and the screen has no field for the kind of change. In `ddt*`: 3 SETUP segments, all with `change_kind` NULL, so the sample query returns 0 rows; `prod_machine_changeover` has 0 rows. FACT (grep and query).
2. **Even a learned value is not used by the planner**: no code reads `prod_machine_changeover` for planning (`25_engine_setup-time.md` finding 1). FACT.
3. **The engine creates rows in a master table** (`ProdMachineChangeover::create`, `:60`) although rule "master changed only by the user" applies; the docblock justifies it as "assumed values" and the screen lets the officer put every change back. FACT (code); whether this is accepted is Jo's call.
4. **The user guide lists "setup time from the timer corrects changeover" as a working feedback path** (`docs/guides/production/00_alur_data_dan_sistem.md`, section 3, path 2). Because of findings 1 and 2 this path is not closed in the code. FACT.
5. **No test and no log of `ppic:learn` runs**: a run that adjusts nothing leaves no trace. FACT (code).
6. **`PPIC_LEARN_*` descriptions in the old plan note** (`docs/design-notes/ppic/08_ppic-tech-rules.md` item 7) say extremes are ignored and use the name alpha; the code uses the median (which tolerates extremes) and the name blend. FACT, wording only.

## Already exists? Searched where, result
`concept:find "machine"`, `"shift"`: no card existed. Searched: a second learning pair exists for job order (`28_engine_sequence-suggester.md`) and one for speed targets (`../ppic/20_engine_speed-target.md`); they are separate. The planned table prd_learning_proposal (old plan note `05_ppic-tech-database.md`) does not exist (no such table in any database).
