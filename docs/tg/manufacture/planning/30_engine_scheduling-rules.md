# ENGINE card: Scheduling Rule Engine (rule library)

> Written 2026-10-17 (Manufacture group). FACT = code with line numbers and mockup DB (`--env=mockup`) seen this session. Source read in full: `app/Services/Scheduling/SchedulingRuleEngine.php`, `app/Models/Prd/PrdSchedulingRule.php` (header), `app/Support/V2/Grid/Prod/RuleLibraryGrid.php` (header).

- **Canonical name**: Scheduling Rule Engine. **Class**: `App\Services\Scheduling\SchedulingRuleEngine`. **Registry**: key `scheduling-rules`, mode `calc`. **Module/sub-module**: Manufacture > PPIC > PPIC Settings > Rule Library (`v2:prod-rule-library`, master) feeding the old board.
- **Synonyms**: Rule Library, batching rule, sequencing rule, constraint rule library.
- **Frozen status**: not frozen.
- **Concept cards**: `../ppic/16_card_planning-parameters.md` (the screen), `13_card_plan-draft-and-live.md`.

## Trigger (called by another engine)
FACT: the only caller is `PdrEngine::buildResourceQueue` (`app/Services/Scheduling/PdrEngine.php:54,75`), which itself is reached only from the v1 Scheduling Board and the hot-job option generator (`29_engine_pdr.md`). No background schedule, no v2 screen calls it. The PPIC Settings screen says the same ("Read by the old scheduling board only, not by this planner", `resources/views/v2/screens/ppic-settings.blade.php`).

## Flow (text twin)
1. `applyBatching(candidates, machine, process)`: reads the active rules of type `BATCHING` for that resource (`PrdSchedulingRule::active()->forResource()`); with no rule every operation is its own group of one.
2. For each rule: the candidates not yet used are grouped by the value of `condition_field` (read with data_get from the operation, for example `dieline_ref`); groups of two or more become one batch; the rest are single (`:21-52`).
3. `applySequencing(sorted, machine, process)`: active rules of type `SEQUENCING` ordered by `rule_id` (oldest = main key); applied in reverse so the oldest rule wins; direction by `condition_operator` (`SORT_DESC` descending, anything else ascending) (`:75-96`). The priority order of hot job, critical ratio and due date is never changed because the engine is called only after the priority sort.

## Input / Output
- **Input**: `prd_scheduling_rule` (`rule_id`, `rule_type`, `rule_name`, `applies_to_machine_id`, `applies_to_process`, `condition_field`, `condition_operator`, `rule_status`, `effective_from`, `effective_to`, `approved_by`, `approved_at`).
- **Output**: groups or an ordered list. Writes nothing.

## Formula
No formula. Rule value read = `data_get(operation, condition_field)`; batch when two or more operations of different WOs have the same non-null value. A worked example is NOT written: `prd_scheduling_rule` has 0 rows in `ddt*`, and the engine has no test.

## Edge cases
- A null value is never batched (`$value === null`).
- Several rules on one resource are legal since the 2026-09-03 fix in the comment, but a rule has no explicit priority column; `rule_id` order is the priority.

## Used by
`PdrEngine` only (v1 board).

## Open items
None in `docs/ENGINE-ATTENTION.md`.

## Tests
None found: `grep SchedulingRuleEngine tests/` returns nothing.

## Relations
- **source**: `../ppic/16_card_planning-parameters.md` (Rule Library grid, master table `prd_scheduling_rule`).
- **used_by**: `29_engine_pdr.md`.
- **flows**: rule rows -> batching and sequencing of the old queue.
- **frozen_zone**: none.

## Findings (recorded, not fixed)
1. **The rule library has no effect on the v2 plan.** The solver does its own die grouping and setup-distance ordering (`24_engine_schedule-solver.md`) and never reads `prd_scheduling_rule`. FACT (grep).
2. **0 rows and no test**: nothing proves that the engine works. FACT.
3. **Table `prd_scheduling_rule` lives in `tm2prd` but its screen sits under PPIC (master data of `tm2mst`)**: the master screen writes to a production-database table. FACT (`show columns` in `ddtprd`; screen slug `prod-rule-library`).
4. **PK name** `rule_id` does not follow `id_<entity>`. FACT.

## Already exists? Searched where, result
`concept:find "schedule"`: no earlier card. Searched: the die grouping that the old notes expect from this library is implemented inside the solver (`PPIC_GROUP_DUE_HOURS`, `PPIC_GROUP_SLACK_HOURS`, `prd_wo.die_group_off`), not here.
