# ENGINE card: Setup Time Engine

> Written 2026-10-17 (Manufacture group). FACT = code with line numbers, tests I ran, and the mockup DB (`--env=mockup`) seen this session. Source read in full: `app/Services/Scheduling/Plan/SetupTimeEngine.php`, `config/ppic.php`, `PpicSettings.php`, the callers.

- **Canonical name**: Setup Time Engine. **Class**: `App\Services\Scheduling\Plan\SetupTimeEngine`. **Registry**: key `ppic-setup-time`, mode `calc`. **Module/sub-module**: Manufacture > Planning (no screen of its own; shown as setup parts on the Work Order form and the Colour Sequence screen).
- **Synonyms**: changeover, wash, make-ready, set-up minutes, "menit cuci".
- **Frozen status**: not frozen; PURE (no database).
- **Concept cards**: `17_card_learning-changeover.md`, `../ppic/15_card_changeover-and-wait-rules.md`, `../ppic/14_card_tooling.md`.

## Trigger (called by another engine, plus one screen)
Searched callers (`grep SetupTimeEngine`): FACT.
| Caller | File:line |
|---|---|
| Solver, for every candidate slot (setup against the job before) | `app/Services/Scheduling/Plan/ScheduleSolver.php:418` |
| Colour Sequence screen, wash before each print job | `app/Support/V2/Data/Ppic.php:125-178` (`colorSequence`, calls `between`) |
| Constructed for the solver | `app/Services/Scheduling/Plan/AutoPlanRunner.php:129` |

## Flow (text twin)
`between(prev, next, rules, stage, baseMinutes)` (`SetupTimeEngine.php:29-80`):
1. `base` = the candidate's own setup minutes (`prod_process_machine.setup_minutes` through the enricher), else `rules['base_minutes']` (constant `PPIC_BASE_SETUP_MIN`, 15).
2. Which changes count depends on the stage of the process (`kindsFor`, `:83-91`): PRESS: colour, ink type, board, size, plate, die. FINISHING: board, size, die. POSTPRESS: size, board. Every other stage (PREPRESS, QC, PACKING, LOGISTIC): base only.
3. Only when there is a job before (`prev`): colour wash (below), ink type (conventional to UV), board change, size change.
4. Tooling is mounted when the next job's die (or plates) differs from the previous job's, or on a cold start (no previous job): `tool` minutes.
5. Result: `base`, `change`, `tool`, `total`, extra_waste (sheets), `parts` (list of kind, label, minutes) for display.

## Formula (one place)
Symbols: `a` = ink set of the previous job, `b` = ink set of the next job (real inks from `prod_part_ink` when known, else the first `n` of C, M, Y, K plus spot colours).
- **Colour** (`colour`, `:94-118`): `a = b` gives 0. If every ink of `a` is also in `b` (the next job only adds spot colours) then partial wash `color_add_spot` = 20 minutes, waste 30. Otherwise full wash `color_full_wash` = 38 minutes, waste 60; when BOTH jobs carry their real inks and the mean darkness of `b` is larger than of `a`, the wash is multiplied by `PPIC_COLOR_ORDER_FACTOR` (0.7), rounded. Darkness: yellow 1, cyan 2, magenta 3, black 5, any other name 3.
- **Ink type** differs (CONVENTIONAL / UV): ink_type = 55 minutes, waste 80 (`:47-52`).
- **Board** (`substrateChanged`, `:147-157`): grammage differs by more than gsm_gap (20) when both are known, or the board names differ when both are given: `substrate` = 15 minutes, waste 20.
- **Size** (`sizeChanged`, `:159-170`): the longer and the shorter side differ by more than 1 mm: `size` = 12 minutes, waste 15.
- **Die** mounted: `next.tool_min` (from `prod_tooling_asset.setup_first_min`) else `die` = 15 minutes. **Plates** mounted: `next.plate_min` else `plate` = 10 minutes.
- `total = base + change + tool`. Start values come from `config/ppic.php` `setup`; only base_minutes and color_order_factor are replaced by the Settings constants (`PpicSettings::setupRules`, `PpicSettings.php:57-64`).

### Worked example (test I ran, 2026-10-17)
`tests/Unit/Ppic/ScheduleSolverTest.php` (23 passed, 47 assertions):
- `test_setup_engine_parts`: previous job 4 colours conventional 300 gsm, sheet 790 x 545, die A; next job adds spot P485, UV ink, same board, sheet 720 x 520, die B with tool_min 12. Total 114 = base 15 + add spot 20 + UV 55 + size 12 + die 12. A QC stage pays only the base (15).
- `test_ink_order_light_to_dark_washes_lighter_than_dark_to_light`: yellow+cyan to black+magenta = 15 + 27 (38 x 0.7 rounded); the reverse = 15 + 38; same inks in another order = 15.
- `test_second_job_waits_for_a_busy_machine_and_pays_changeover_setup`: 15 + add spot 20 + board 15 = 50.

## Edge cases
- No real inks on either job: colour comparison falls back to the colour count; spot colours are listed by name (`spots`), but the loader always passes an empty list (`PlanLoader.php:161`).
- `board` is never filled by the loader, so the board test uses grammage only (`facts` has `gsm`, no `board`).
- A machine with sheet_l or sheet_w of 0 or null never triggers a size change.
- The first job on a machine has no changeover but still mounts its die/plates.

## Used by
`ScheduleSolver`, `Ppic::colorSequence`, display of "setup parts" on the Work Order form.

## Open items
None in `docs/ENGINE-ATTENTION.md` (ATTENTION-ENGINE-01 to 08 do not name this engine). The assumed factor 0.7 is marked "ASSUMED, not proven for this plant" in `config/ppic.php`.

## Tests
`tests/Unit/Ppic/ScheduleSolverTest.php` (setup cases listed above). No test for the Colour Sequence screen data.

## Relations
- **source**: `config/ppic.php`, `PPIC_BASE_SETUP_MIN` and `PPIC_COLOR_ORDER_FACTOR` (`../ppic/16_card_planning-parameters.md`), tooling minutes (`../ppic/14_card_tooling.md`), process x machine setup (`21_engine_wo-planning-enricher.md`).
- **used_by**: `24_engine_schedule-solver.md`, `13_card_plan-draft-and-live.md`, `17_card_learning-changeover.md`.
- **flows**: two jobs on a machine -> setup parts -> minutes in the slot -> stored in `prd_wo_operation.setup_base_min`, `setup_change_min`, `setup_tool_min`.
- **frozen_zone**: none.
- **also_named_by**: `11_card_process-and-routing.md`.

## Findings (recorded, not fixed)
1. **Machine changeover values are NOT used.** The docblock (`SetupTimeEngine.php:12`) and the config comment say machine rows in `prod_machine_changeover` override the start values. The engine receives one global rule table (`PpicSettings::setupRules()`), and no code reads `prod_machine_changeover` for planning (grep `prod_machine_changeover`: only the learning engine, the Setup and Changeover grid, the Learning screen, the model relation, a master test, and the REMARK of the Colour Sequence screen, whose code does not read it). So neither the manual values on the screen Setup and Changeover nor the values learned by `27_engine_learning.md` change any plan. The table has 0 rows in `ddt*`. FACT.
2. **extra_waste (extra sheets) is calculated but never stored or shown**: `grep extra_waste` finds no reader outside this class, the solver's zero array and the master columns. FACT.
3. **Plate code and plate minutes are not real**: the loader sets the plate to `'PL-'.product_id` (`PlanLoader.php:164`) and plate_min is never filled, so every print job mounts plates for 10 minutes whenever the product changes. `prod_tooling_asset.setup_repeat_min` is never read. FACT.
4. **Ink type is always CONVENTIONAL** (`PlanLoader.php:161`), so the 55-minute UV change can occur only in tests. FACT.
5. **Setup minutes in the master are mostly empty**: `prod_process.default_setup_minutes` is NULL for all 29 processes and 55 of 68 active `prod_process_machine` rows have no setup minutes, so most operations use the constant 15. FACT (query).
6. **The die mount is only counted for processes whose name starts with PLONG** (`PlanLoader.php:135`); `KETREK` also has `requires_die = 1` but is not treated as a die process here. FACT.

## Already exists? Searched where, result
`concept:find "machine"`: no card existed for setup logic. Searched: no second place computes changeover for planning; `Ppic::colorSequence` reuses this class. The old plan note `docs/design-notes/ppic/08_ppic-tech-rules.md` item 4 ("a job is never delayed by setup saving more than one lock period") has no counterpart in the code; the only limit is the similar-urgency window of the solver.
