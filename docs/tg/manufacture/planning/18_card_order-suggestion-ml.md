# Concept card: Order suggestion (ML on the officer's arrangement)

> Written 2026-10-17 (Manufacture group). "FACT" = seen first-hand in this session: mockup DB (`--env=mockup`; counts = `count(*)` today), code with line numbers, tests I ran. "FROM DOCUMENT" = not checked. The logic is in `28_engine_sequence-suggester.md`; this card is the data and the rule behind it.

- **Document type**: concept card. **Module/sub-module**: Manufacture > Planning > Work Order form (Simulation strip, QUE column Suggested).
- **Canonical name**: Order suggestion = the learned preference for the order of jobs on a machine, stored as `tm2prd.prd_sequence_model` (versions of weights), trained from `tm2prd.prd_plan_trace` (officer arrangements).
- **Synonyms**: Job Order Learning, sequence learning, "Learning n / 30", Suggested order, USE, DISMISS, officer arrangement as input.
- **Rule** (Jo 2026-10-14, as written in the screens and in the registry text of `sequence-learning`): the officer arranges not-yet-applied WOs freely in Simulation; the arrangement is input for the ML; the ML only suggests; a problem job must not hold its machine (`PlanLoader.php:131`).
- **NOT the same as**: the engine's own ordering (`24_engine_schedule-solver.md`) or the old Rule Library sequencing (`30_engine_scheduling-rules.md`).

## Facts
| Item | Content | Evidence |
|---|---|---|
| Trace table | `prd_plan_trace`: 57 rows, all `TRIAL` (14 snapshots), 0 `FINAL`. Columns `snapshot_wo_id`, `snapshot_key`, `wo_id`, `wo_operation_id`, `machine_id`, `process_id`, `status` (TRIAL, FINAL), `process_hash`, `engine_pos`, `officer_pos`, `features` (JSON of six numbers), `pull_reason`, `machine_trouble`, `from_suggestion`, `run_id`. TRIAL is written at PROCESS and replaced by the next PROCESS; FINAL at APPLY of one WO. | FACT, `show columns`; query; `TraceRecorder.php` |
| Model table | `prd_sequence_model`: 0 rows. Columns `version`, `status` (ACTIVE, CANDIDATE, FROZEN), `weights` (JSON: `w` per feature, `sig`), `feature_names`, `pairs_count`, `arrangements_count`, `holdout_accuracy_model`, `holdout_accuracy_engine`, `trained_at`. | FACT, `show columns`; query |
| Six features | `urgency`, `priority`, `duration`, `similarity`, material_gate, wait_before (definitions in the engine card). | FACT, `FeatureSet.php:20-27` |
| Gate to ACTIVE | at least 30 pairs AND better pair accuracy than the engine on the newest 20 percent of arrangements. Until then the strip reads "Learning n / 30". | FACT, `SequenceTrainer.php:23,102` |
| Decisions | `SEQ_USE` (officer took the suggested order) and `SEQ_REJECT` (dismissed) in `prd_plan_decision`; both 0 today. A suggestion taken and left unchanged is excluded from training (`from_suggestion`). | FACT, query; `TraceRecorder.php` |
| Schedule | `sequence:train` daily 03:45 when the model table exists. | FACT, `routes/console.php:46` |

## Relations
- **source**: the Draft plan and the officer's drag/PROCESS/APPLY (`13_card_plan-draft-and-live.md`, `26_engine_plan-confirm.md`).
- **used_by**: `28_engine_sequence-suggester.md`; screen Work Order.
- **flows**: arrangement -> TRIAL -> APPLY -> FINAL -> train -> ACTIVE (if better) -> suggestion -> officer decides.
- **frozen_zone**: none.

## Findings (recorded, not fixed)
1. **Nothing to learn from yet** (0 FINAL). The earliest ACTIVE model needs 30 pairs, which one snapshot of 8 jobs already gives (28 pairs), but the hold-out needs at least one separate newer snapshot, so at least two applies with several jobs on one machine. FACT (code), reasoning from the formula, not observed.
2. **The learning label ignores why**: `pull_reason` and `machine_trouble` are stored but not features. FACT.
3. **Features come from `Ppic::operations()`**: when a column changes there (for example a new gate value) the stored TRIAL/FINAL `features` of old rows keep the old meaning; the model version has a signature of feature names only. FACT (code).

## Already exists? Searched where, result
`concept:find "learning"`: none. Searched: no other table stores officer order; `prd_decision_feedback_log` (0 rows) belongs to the old hot-job dialog (`31_engine_interruption.md`).
