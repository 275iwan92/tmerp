# ENGINE card: Speed Target Engine

> Written 2026-10-17 (Manufacture group). FACT = code with line numbers, a test I ran, and the mockup DB (`--env=mockup`; `tm2old` read only) seen this session. Source read in full: `app/Services/Production/SpeedTarget/` (engine, settings, repository, store, history, the five sources, sample and context), `app/Console/Commands/SuggestSpeedTargets.php`. Masters of machines are REAL: the engine writes only suggestions.

- **Canonical name**: Speed Target Engine. **Class**: `App\Services\Production\SpeedTarget\SpeedTargetEngine`. **Registry**: key `speed-target`, mode `suggest`. **Module/sub-module**: Manufacture > PPIC > PPIC Settings > Master Machine (the speed target of a machine and of its process pairs). No screen of its own was found (finding 1).
- **Synonyms**: target kecepatan, speed suggestion, `prod_speed_suggestion`, "engine suggests, PPIC confirms".
- **Frozen status**: not frozen. Pure engine plus a store; the targets change only when PPIC accepts a suggestion.
- **Concept cards**: `10_card_machine.md`, `11_card_process-and-routing.md`.

## Trigger
FACT, searched `routes/console.php`, commands, controllers, views:
| Trigger | File:line |
|---|---|
| Background, daily 03:00 | `routes/console.php:21` (command `production:suggest-speed-targets`, `app/Console/Commands/SuggestSpeedTargets.php`) |
| Screen / button | NONE found: no view, route or controller calls `SpeedSuggestionStore::refresh`, `accept` or `reject` (grep `SpeedSuggestionStore`, `ProdSpeedSuggestion` in `app`, `resources`, `routes`) |

## Flow (text twin)
1. `SpeedSuggestionStore::refresh` loads one context per active PROCESS machine through `SpeedDataRepository::contexts`: the machine's model and drive, `max_speed_per_hour` (or the model's), current `speed_target_per_hour`, its capability pairs (process, unit, current pair speed, rank), its v2 output samples, old-system samples by legacy machine name, and old samples by process name for processes done by one machine type only.
2. v2 samples: operations with a machine, both actual times, `DONE` or `QC_PASSED`, finished within `SPEED_V2_LOOKBACK_DAYS` (365); quantity = recorded good output, else the operation quantity; rate = quantity per hour of the actual run. Old samples: `tm2old.prd_output` rows with good quantity above 0 and stop after start (121,271 rows today).
3. A sample counts only if its run is between `SPEED_RUN_MIN_MINUTES` (5) and `SPEED_RUN_MAX_MINUTES` (960) minutes, has a quantity, and its process name does not contain a word of `SPEED_LEGACY_EXCLUDE` (SETTING, GANTI, REPARASI, CLEANING, PERBAIKAN, PERSIAPAN, HEAT UP, HITUNG, QC).
4. Pass 1 gives the history suggestion of each machine (source 1 V2_ACTUAL, else 2 LEGACY_MACHINE); pass 2 lets machines without history borrow, in this order: 3 LEGACY_PROCESS (first capability with enough history), 4a SAME_MODEL (average of machines of the same catalog model that have history), 4b PEER_RATIO (maximum speed x the median actual/maximum ratio of machines of the same type and drive), 5 MODEL_SPEC (maximum speed x the efficiency of the drive: calibrated from real history of machines with the same drive when at least `SPEED_EFF_CALIBRATE_MIN` (2) exist, else the constant `SPEED_EFF_AUTO` 0.6 or `SPEED_EFF_MANUAL` 0.5), 6 PROCESS_DEFAULT (default speed of the main process). The first source that gives a number wins (`SpeedTargetEngine::suggestMachine`).
5. A pair (process x machine) is suggested only from its OWN history (v2 then old system by process name) and only if it already has its own value or differs from the machine suggestion by more than `SPEED_CHANGE_MIN_PCT` (5) (`suggestCapabilities`).
6. PPIC is asked only if the current value is empty, or the suggestion is not LOW confidence and differs more than 5 percent from the current value (`shouldPropose`). A suggestion the officer REJECTED earlier with the same source and the same number (within 0.5) is not offered again (`SpeedSuggestionStore::sync`).
7. The store keeps one PENDING row per machine or pair, marks older PENDING rows SUPERSEDED, and writes nothing else. Only `accept` (value = the suggestion or the officer's own number) writes the master: `prod_machine.speed_target_per_hour` or `prod_process_machine.speed_per_hour`, plus a trace line in the notes of the machine or pair; `reject` closes the row.

## Input / Output
- **Input**: `prod_machine`, `prod_machine_model`, `prod_process`, `prod_process_machine`, `prd_wo_operation` (+ `prd_wo_operation_output`), `tm2old.prd_output`, constants group `SPEED_ENGINE` (12 rows).
- **Output (writes DB)**: `tm2mst.prod_speed_suggestion` (PENDING, SUPERSEDED; ACCEPTED and REJECTED by the officer); the machine and pair masters only on `accept`.
- **Display only**: the `basis` text (for example "P75 of 1,366 runs ...").

## Formula (one place)
Rate of a sample = `quantity / (minutes / 60)`. History target = the percentile `SPEED_PERCENTILE` (75) of the rates, rounded: `rates[ceil(p x (n - 1) - 1e-9)]` after sorting (the same method as MySQL percent rank; test `test_percentile_matches_mysql_percent_rank_method`). Confidence: with at least `SPEED_HIGH_SAMPLE` (200) samples the high level, else the lower (V2_ACTUAL and LEGACY_MACHINE: HIGH or MEDIUM; LEGACY_PROCESS: MEDIUM or LOW; SAME_MODEL MEDIUM; PEER_RATIO MEDIUM with 3 or more peers else LOW; MODEL_SPEC and PROCESS_DEFAULT LOW). A history with fewer than `SPEED_MIN_SAMPLE` (20) samples gives no suggestion. If the dominant unit of the history is not the process unit, the confidence is lowered one step.

### Worked example (stored result of the run of 2026-10-01 in `ddt*`, and the test I ran)
`prod_speed_suggestion` holds 28 rows (24 machine level, 4 pair level), all computed 2026-10-01 04:04 to 04:15: PENDING 26, SUPERSEDED 2; by source: LEGACY_MACHINE 16 (HIGH 12, MEDIUM 4), LEGACY_PROCESS 8, MODEL_SPEC 4. Example: machine `CUT-01` suggestion 12,000 sheets per hour from 1,366 old runs (source LEGACY_MACHINE, HIGH), current target 10,286. `tests/Unit/SpeedTargetEngineTest.php`: 14 tests passed, 38 assertions (percentile, skipping setup rows and odd runs, unit fallback, too few records, source priority, process history, same model, peer ratio, efficiency constant and calibration, last resort, pair rules, `shouldPropose`, excluded words).

## Edge cases
- No `max_speed_per_hour` (machine and model empty): sources 4b and 5 give nothing.
- A pair whose suggestion is close to the machine target (5 percent) and which has no own value is not offered; it inherits the machine target through the three-layer rule.
- The engine and sources never read or write the database themselves; the repository loads, the store saves.

## Used by
Command `production:suggest-speed-targets`; the targets it may change are read by `WoPlanningEnricher` (three layers) and `RateBandResolver` (only `max_speed_per_hour`).

## Open items
None in `docs/ENGINE-ATTENTION.md`.

## Tests
`tests/Unit/SpeedTargetEngineTest.php` (14). No test for the repository or the store.

## Relations
- **source**: `10_card_machine.md`, `11_card_process-and-routing.md`, `21_engine_operator-timer.md` (the v2 actual output it learns from), old production output (`tm2old`).
- **used_by**: `../planning/21_engine_wo-planning-enricher.md` (reads the accepted targets), `../planning/24_engine_schedule-solver.md` (run minutes).
- **flows**: operator output -> samples -> nightly suggestion (PENDING) -> officer accepts -> master target -> next WO enrichment -> run minutes.
- **frozen_zone**: machine and process masters are REAL; only an officer's accept changes them.

## Findings (recorded, not fixed)
1. **There is no screen to see or decide a speed suggestion.** 26 PENDING suggestions exist; `accept` and `reject` have no caller. The registry says it is used by "Master Machine"; the Master Machine grid and the v1 form show no suggestion (the form only fills the model's general spec). So the loop "engine suggests, officer confirms" has no officer side. FACT (grep).
2. **The suggestions are 16 days old** (computed 2026-10-01); the command is scheduled daily 03:00 but no run has refreshed them (the scheduler may not run on the dev machine; NOT verified). FACT (query).
3. **The v2 learning source is empty**: `ddt*` has 0 operations with status DONE, and v2 samples need `DONE` or `QC_PASSED` operations with actual start and finish (the operator timer writes them at Stop of production), so every suggestion today comes from the old system or from the specification. FACT (query, code).
4. **Two speed vocabularies**: this engine writes `speed_target_per_hour` (units per hour of the process unit), while the old rate band used `max_speed_per_hour x 0.8` (`RateBandResolver`, still used by backward scheduling at WO creation). FACT.
5. **The percentile 75 is a choice**, not an average: a target at the 75th percentile of past runs is optimistic for planning; recorded because planning uses it as the expected rate (`PPIC` run minutes are `qty / rate`). FACT (code); whether 75 is right is Jo's call.
6. **Registry text and code agree** on the order of sources; the registry says "v2 output, old-system output of the machine, old-system output of the process, same machine model, ratio, model spec, process default": verified one by one in `SpeedTargetEngine.php` (seven source objects, `__construct`). FACT.

## Already exists? Searched where, result
`concept:find "machine"`: none. Searched: the old rate band (`RateBandResolver`, history of finished operations, v1 path) is a second, separate rate estimator; there is no other writer of `prod_speed_suggestion`.
