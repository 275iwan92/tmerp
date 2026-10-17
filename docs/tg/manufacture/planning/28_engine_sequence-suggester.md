# ENGINE card: Job Order Learning (SequenceSuggester, trainer, recorder)

> Written 2026-10-17 (Manufacture group). FACT = code with line numbers, tests I ran and the mockup DB (`--env=mockup`) seen this session. Source read in full: `app/Services/Scheduling/Sequence/` (`SequenceSuggester.php`, `SequenceTrainer.php`, `SequenceModel.php`, `TraceRecorder.php`, `FeatureSet.php` and the six feature classes), `app/Console/Commands/SequenceTrain.php`.

- **Canonical name**: Job Order Learning (Sequence). **Class**: `App\Services\Scheduling\Sequence\SequenceSuggester` (registered class; the trainer, the recorder and the model are its helpers). **Registry**: key `sequence-learning`, mode `suggest`. **Module/sub-module**: Manufacture > Planning > Work Order form (Simulation strip, QUE column Suggested).
- **Synonyms**: order-suggestion ML, ML order engine, "Learning n / 30", Suggested order, USE / DISMISS.
- **Frozen status**: not frozen. Rule 2026-10-14: the officer's arrangement is INPUT for the learner; the ML only SUGGESTS and never changes the plan alone.
- **Concept card**: `18_card_order-suggestion-ml.md`.

## Trigger
FACT:
| Trigger | File:line |
|---|---|
| Background, daily 03:45, when table `prd_sequence_model` exists | `routes/console.php:46` (command `sequence:train`) |
| PROCESS of a WO: `capturePins` before, `recordTrial` after | `app/Http/Controllers/V2/PpicController.php:453,466` |
| APPLY of one WO: `recordFinal` | `app/Services/Scheduling/Plan/PlanConfirmService.php:119-121` |
| Strip and QUE column (suggestion, status) | `app/Support/V2/Data/Ppic.php:623-639,1232-1239` |
| Button USE / DISMISS | `PpicController.php:401,411` |

## Flow (text twin)
1. **Record** (`TraceRecorder`): PROCESS stores a `TRIAL` snapshot per machine of the WO (all movable Draft operations of the machine in the officer's order, with the six features); the next PROCESS replaces it, never stacks. APPLY turns the arrangement now in force into a `FINAL` snapshot (the training label) and drops the TRIAL of that WO. A failure only writes a log line; PROCESS and APPLY never fail because of it (`TraceRecorder.php`, try/catch).
2. **Six features per job** (`FeatureSet::all`): `urgency` = `-(hours left to due - hours of work) / 240` clamped to -3..3 (no due date: -3); `priority` = 1 hot, 0.5 "Move up", else 0; `duration` = (setup + run) hours / 8 clamped 0..3; `similarity` = 0.5 same customer + 0.5 same product as the last fixed job on the machine; material_gate = READY 1, WAIT = minus hours until material / 24 clamped -3..0, BLOCK -2; wait_before = wait minutes of the previous process of the same WO / 60 / 24 clamped 0..3.
3. **Pairs** (`SequenceTrainer::pairs`): within one FINAL snapshot, every two jobs (a before b in the officer order) give the difference vector `f(a) - f(b)` and the flag "the engine had the same order". Snapshots from a suggestion (`from_suggestion`) and of cancelled WOs are not used.
4. **Train** (`SequenceTrainer::run`, `sequence:train`): the newest 20 percent of snapshots (at least 1) are the hold-out; the rest train a pairwise linear ranking model by logistic regression with L2 (`SequenceModel::train`: 400 epochs, learning rate 0.3, L2 0.01, weights rounded to 6 decimals). The model is `ACTIVE` ONLY if total pairs (training + hold-out) >= 30 (`MIN_PAIRS`), the hold-out is not empty, and the model's hold-out pair accuracy is above the engine's order; otherwise it is stored as `CANDIDATE`. A new ACTIVE model freezes the previous one (`FROZEN`). No new data gives no new version (signature).
5. **Suggest** (`SequenceSuggester::suggest`): only with an ACTIVE model and at least two movable Draft operations on the machine; each operation is scored `sum(weight x feature)`; best first; cached 30 seconds; hidden after DISMISS until the arrangement changes.
6. **USE** (`use`): operation k takes the start of slot k through `PlanChangeService::queueMove` (a pending move, still DRAFT, same path as a drag); decision `SEQ_USE`. **DISMISS**: decision `SEQ_REJECT`.

## Input / Output
- **Input**: `Ppic::operations()` (Draft order), `prd_wo.priority`, `PlanLoader::waitRules`, `prd_plan_trace`, `prd_sequence_model`.
- **Output (writes DB)**: `prd_plan_trace` (TRIAL, FINAL), `prd_sequence_model` (versions), `prd_plan_decision` (`SEQ_USE`, `SEQ_REJECT`), and pending moves on `prd_wo_operation` through `queueMove`.
- **Display only**: the strip "Learning n / 30" or "Suggested order", the shadow lane and the QUE column.

## Formula (one place)
`score(job) = sum over features (weight_k x feature_k)`; pairwise: P(a before b) = sigmoid(score(a) - score(b)); pair accuracy = share of pairs where `score(a) - score(b) > 0`. Gate: `pairs >= 30` and `accuracy_model > accuracy_engine` on the hold-out (`SequenceTrainer.php:102`).

### Worked example (tests I ran, `--env=mockup`, 2026-10-17: `tests/Feature/SequenceLearningTest.php`, 6 passed, 42 assertions)
- `test_learns_a_known_rule_and_passes_the_gate`: with a synthetic rule the model becomes `ACTIVE`, has at least 30 pairs, beats the engine on the hold-out and gives the `urgency` weight above 0.5; a second run returns `null` (nothing new).
- `test_below_thirty_pairs_there_is_no_suggestion`: 18 pairs, needed 30, status `CANDIDATE`, no ACTIVE model.
- `test_pairs_from_a_suggestion_are_not_used`: pairs 0.
- Data today (`ddt*`): `prd_plan_trace` 57 rows, all `TRIAL` (14 snapshots), 0 `FINAL`; `prd_sequence_model` 0 rows; so the strip shows "Learning 0 / 30".

## Edge cases
- Snapshots with a single job are skipped (a pair needs two).
- A machine in trouble or a job pulled forward because another job is blocked is recorded as context (`machine_trouble`, `pull_reason`) but changes nothing in the solver.
- `similarity` has no data for material, size or colours (the operation row has none); remark of `SimilarityFeature.php`.

## Used by
Work Order form (Simulation, QUE), `PlanConfirmService`, `PpicController`.

## Open items
None in `docs/ENGINE-ATTENTION.md`. The thresholds (30 pairs, 20 percent hold-out) come from the rule of Jo 2026-10-14 in the registry text and the screen.

## Tests
`tests/Feature/SequenceLearningTest.php` (6).

## Relations
- **source**: `23_engine_auto-plan-runner.md` (engine order), `26_engine_plan-confirm.md` (FINAL label), `22_engine_gate-service.md` (material feature).
- **used_by**: card `18_card_order-suggestion-ml.md`, screen Work Order.
- **flows**: officer arranges -> PROCESS (TRIAL) -> APPLY (FINAL) -> nightly train -> ACTIVE only if better than the engine -> suggestion -> officer USE or DISMISS.
- **frozen_zone**: none.
- **also_named_by**: `14_card_gates-and-readiness.md`, `15_card_changeover-and-wait-rules.md`.

## Findings (recorded, not fixed)
1. **Not yet active and no data**: 0 FINAL snapshots and 0 models in `ddt*`; the earliest the gate can open is 30 pairs of FINAL decisions (3 or more WOs applied with 3+ jobs on one machine each). FACT (query).
2. **The learning label is the officer's arrangement at APPLY of ONE WO**, so jobs of other WOs on the machine enter the snapshot as they stand; a snapshot says nothing about why two jobs of different WOs are in that order. Design choice, recorded not judged. FACT (code `TraceRecorder.php`, `recordFinal`).
3. **The strip text and the registry say "30 decision pairs"; the code counts every pair inside a snapshot**, so one snapshot of 8 jobs gives 28 pairs at once (`count x (count - 1) / 2`, `SequenceTrainer::counts`). 30 pairs can therefore come from very few real decisions. FACT (code).
4. **The `similarity` feature is customer and product only** (remark of the class); size, board and colours are not features although the solver's setup distance uses them. FACT.
5. **Key mismatch in naming**: the engine map says "Job Order Learning (Sequence)" is scheduled (`sequence:train`) and "also a screen"; the screen is part of the Work Order form, there is no screen of its own. FACT.

## Already exists? Searched where, result
`concept:find "learning"`: no earlier card. Searched code: the model, trainer and recorder exist only in `app/Services/Scheduling/Sequence`; no other ranking learner exists (the Sales `CostingAdvisor` has its own statistics for costing, different table). Old plan note `docs/design-notes/ppic/` has no document about this engine (rule of Jo 2026-10-14 only).
