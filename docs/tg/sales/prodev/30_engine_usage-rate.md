# ENGINE card: Usage Rate Engine

> DRAFT GROUP C (2026-10-16), awaiting Jo's approval. Source: `app/Services/ProDev/UsageRateEngine.php` read in full (202 lines), `RefreshUsageRates.php`, `bop-rule.blade.php:250-290`. There is no test for this engine (grep `tests/`). The engine was **not run** in this session because `refresh()` always writes to `prod_bop_rule` (see Finding 1); there is no worked example from data.

- **Canonical name**: Usage Rate Engine. **Class**: `App\Services\ProDev\UsageRateEngine`. **Registry**: key `usage-rate`, mode `suggest`, group Pro-Dev. **Module/sub-module**: Sales > Pro-Dev (raw material of the BOP/BOM rules); its source data is Production.
- **Frozen status**: ProDev is frozen (flow/data). This engine changes rule master values, not the flow.
- **Synonyms**: usage rate, waste suggestion, learning from production, "auto apply".
- **NOT the same as**: `CostingAdvisor` (learns from the officer's costing history; card `../product-costing/10_engine_costing-advisor.md`) and `SpeedTargetEngine` (machine speed).

## Trigger
FACT:
| Trigger | File:line |
|---|---|
| Monthly schedule on day 1 at 02:00, without overlap | `routes/console.php:18` -> command `prodev:usage-rates` (`app/Console/Commands/RefreshUsageRates.php`, default 3 months back, option `--dry`) |
| Refresh-suggestions button (admin only: `canEdit` = `is_admin`) | `resources/views/pages/production/bop-rule.blade.php:256` (`refreshSuggestions`, always `apply=true`) |
| Apply-suggestion button for one rule (does not call the engine, copies the suggestion columns) | `bop-rule.blade.php:261-280` |
The BOP Rule screen in the mockup menu is hidden (`PROD_MST_BOP_RULE`, `hide_in_menu`=1, target `v2:prod-bop-rule`).

## Flow (text twin, no diagram: no branching other than auto-apply)
1. Take all active rules `prod_bop_rule` (+ process name).
2. Material usage: for `COMPLETED` WOs since the start date, sum `prd_wo_material.qty_required` (plan) per `material_role`; actual usage = `tm2inv.v_inv_wo_reconcile.qty_net_used` for the same WO and material (separate query across DBs).
3. Process waste: from `prd_wo_operation_output` and `_defect` per WO step name, mapped to the master process name by word match (`stripos`).
4. Per rule compute the suggested rate and suggested waste (formulas below). Rules without a suggestion are skipped.
5. **Always** write the suggestion columns (`rate_suggested`, `waste_suggested`, `suggest_sample`, `suggest_at`) to the rule.
6. Only if `apply` and the rule has `auto_apply = 1` and sample >= `PRODEV_RATE_MIN_SAMPLE` (5): the suggestion directly overwrites `usage_rate`/`waste_pct`, `rate_source` = AUTO, `updated_by` = `usage_rate_engine`.
7. Otherwise the officer/admin decides on the BOP Rule screen (apply-suggestion button).
**Officer decision point (A.7)**: rules with `auto_apply` are the only automatic overwrite path.

## Input / Output
- **Input**: `tm2mst.prod_bop_rule` (`usage_rate`, `usage_basis`, `waste_pct`, `auto_apply`, `rate_source`, `material_role`, `process_id`), `prod_process.process_name`; `tm2prd.prd_wo` (`wo_status`, `updated_at`), `prd_wo_material` (`material_role`, `qty_required`, `material_id`), `prd_wo_operation` (`process_name`), `prd_wo_operation_output` (`qty_good`), `prd_wo_operation_output_defect` (`qty`); `tm2inv.v_inv_wo_reconcile` (`qty_net_used`); `sys_constant` `PRODEV_RATE_MIN_SAMPLE`.
- **Output (writes)**: `tm2mst.prod_bop_rule` (suggestion columns, and the rate/waste values only on the auto-apply path). Return values: `rules`, `applied`, `sample_total`, `from`, `note`.

## FORMULA
1. Suggested rate = `round(usage_rate * pemakaian_nyata / pemakaian_rencana, 4)`, only if planned > 0 and actual > 0 and the rule has `material_role` and `usage_rate` (lines 53-59). Sample = number of completed WOs with that role.
2. Suggested waste (%) = `round(defect / (good + defect) * 100, 2)`, only if `good + defect > 0` (lines 61-67). Sample = number of WOs that have that step.
3. Rule sample = maximum of the two samples. WO count = `COUNT(DISTINCT wo_id)`.
4. Auto-apply: `apply && auto_apply && sampel >= PRODEV_RATE_MIN_SAMPLE` (default 5, mockup DB 5).
5. Process name match: the first master process name that is a substring of the WO label text (`pluck` order, no guaranteed order).
No worked example: there are no `COMPLETED` WOs in the mockup DB (WO statuses present: DRAFT 51, SCHEDULED 4, CANCELLED 10; FACT, query 2026-10-16).

## Edge cases
- Without completed WOs: `rules` is empty and `note` is "Belum ada data pemakaian nyata ..." (line 107).
- A sample below the minimum still writes the suggestion, it is just not applied automatically.
- A WO step label that does not contain a master process name is used as is as the key, so it never matches a rule.
- Mockup DB: 26 active rules, 0 with `auto_apply`, all `rate_source` MANUAL (FACT, query), so the engine would only write the suggestion columns.

## Used by (relations)
- **source**: Production (WO, output, defect), Inventory (usage reconciliation). **downstream**: `prod_bop_rule`, which is read by `SpecEngine` for material rates and BOP/BOM waste. So a change here affects the next Inquiry/ProDev BOM and the next WO (not old documents: snapshot).

## Open items
No ATTENTION-ENGINE item specific to this engine (read in full).

## Findings (recorded, not fixed)
1. The registry (`EngineRegistry.php:197`) says `apply` "false = only show them". FACT from code: `refresh()` writes the suggestion columns to `prod_bop_rule` (line 89) for every rule with a suggestion, even when `apply = false` (command `--dry`). Only the overwrite of rate/waste does not happen.
2. The registry says "Below the minimum sample nothing is proposed". FACT: the suggestion is still written; the minimum only holds back auto-apply.
3. The registry says "Values typed by an officer are never overwritten". FACT: column `rate_source` is read (line 40) but not used in any decision; the only guard is `auto_apply = 0`. A rule with `auto_apply` that an officer once typed can be overwritten.
4. `processWaste` does not filter `wo_status = COMPLETED` (lines 158-164), while `materialUsage` and `woCount` do. Waste is computed from all WOs since the start date, including unfinished ones.
5. The apply-suggestion button (`bop-rule.blade.php:261-280`) marks `rate_source = AUTO` even though the admin did it manually.
6. There is no test.

## Tests
None.

> Re-checked 2026-10-17 against `EngineRegistry.php` and `engines.json`: key `paper-price` (Procurement) was added after the lines cited here; nothing in this card changed.

> Re-checked 2026-10-17 against `EngineRegistry.php` and `engines.json`: the `paper-price` entry text now names the Tax Rate screen (`fin_tax_rate`) for PPN; nothing in this card changed.

> Re-checked 2026-10-17 against `EngineRegistry.php` and `engines.json`: key `single-face-price` (Procurement) was added after the lines cited here; nothing in this card changed.

> Re-checked 2026-10-17 against `EngineRegistry.php` and `engines.json`: keys `duplex-price` and `corrugated-sheet-price` (Procurement) were added after the lines cited here; nothing in this card changed.
