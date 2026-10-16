# ENGINE card: Costing Advisor

> PHASE 1 EXAMPLE (2026-10-16), awaiting Jo's approval. Source: `app/Services/Costing/CostingAdvisor.php` read in full; test `CostingAdvisorTest` run (5 passed, 24 asserts). The part after the johiwan-only marker = confidential figures/formulas (A.8-A.9).

- **Canonical name**: Costing Advisor. **Class**: `App\Services\Costing\CostingAdvisor`. **Registry**: key `costing-advisor`, mode `suggest`. **Module/sub-module**: Sales > Inquiry & Quotation > Pro-Cost.
- **Frozen status**: ProCost = frozen zone (flow/data). This engine only suggests (A.7).

## Trigger (no background / schedule)
Searched in `routes/console.php` (none), Jobs (no folder), Observers (only 2 others), and all callers. FACT: only from the screen `pages::inquiry.costing` (`resources/views/pages/inquiry/costing.blade.php`):
| Trigger | Method | Line |
|---|---|---|
| DRAFT/CALCULATED document opened | `prefill` | 118 |
| Button/action "advise" | `prefill` | 220 |
| Officer changes an input | `markTouched`, `valueToRestore` | 415-505 |
| Calculate button | `CostingEngine::run` then `learn` | 536 |
| Screen renders (only for those allowed to see formulas) | `report`, `recsFor`, `missing` | 171, 163, 766 |

## Flow (text twin of diagram `../img/costing-advisor.svg`)
1. Costing document opened -> `prefill` -> for each **empty box**:
   - grammage: Inquiry BOM; if empty, template; then the nearest ACTIVE grammage in the master.
   - geometry (plano, UP, print size): if a ProDev layout exists, it is used as-is; otherwise a simulation by `ImpositionEngine` (read only) over all active planos, marked ESTIMATE.
   - rates (`TARIF`): priority order = history (STAT/ML) > rate template > constant/master.
   - margin & tolerance per tier: history > constant.
2. Each filled value is recorded in `iqp_costing_recommendation` (source, method, sample count).
3. Officer changes a box -> `markTouched` -> `is_override`; value 0 = `zero_intent`. Overrides and zeros are deliberately NOT overwritten again.
4. A box accidentally emptied -> `valueToRestore` returns the suggested value.
5. Calculate -> `CostingEngine` calculates -> `learn` stores the final inputs (+ qty, part type) as learning material.
6. `report` builds the Laporan Hitung; the formula steps are hidden when the trace does not equal the stored result.

**Officer decision points (A.7)**: every suggestion can be changed; the engine does not overwrite overrides.

## Input / Output
- **Input**: `iqp_costing` + `iqp_costing_part` + `iqp_costing_price_tier`; `iqp_costing_recommendation` (history); `prod_part_template`, `mat_grammage`, `mat_plano_size`, `prod_part_type`; `sys_constant` group COSTING (`MARGIN_DEFAULT_PCT`, `TOLERANCE_DEFAULT_PCT`, `ADVISOR_MIN_SAMPLE_STAT`, `ADVISOR_MIN_SAMPLE_ML`, `FILM_*`, `PLATE_PER_COLOR`, `KNIFE_FACTOR_DEFAULT`, `PPN_INPUT_MATERIAL_DIVISOR`); Inquiry/ProDev context via `ProCostContextService`.
- **Output (writes to DB)**: `iqp_costing_part`, `iqp_costing_price_tier` (empty boxes only), `iqp_costing_recommendation` (one row per box).
- **Display only**: the Laporan Hitung array (steps, comparisons, notes, simulation).

## Statistics / ML thresholds (FACT from code, lines 312-332)
- < `ADVISOR_MIN_SAMPLE_STAT` (default 5) similar history (same part type, other costings): no history suggestion.
- >= 5: median of the officer's final values -> source `STAT`.
- >= `ADVISOR_MIN_SAMPLE_ML` (default 30) and qty > 0: nearest neighbors per qty (k = 3..7), used only when the leave-one-out error is smaller than the median -> source `ML`.
- Decided by: the officer.

## Edge cases
- A suggestion with value 0 -> the box is left empty without a marker.
- A tooling rate of 0 in the template = "use the constant default", not "no tooling".
- Without a part type, history is empty (no STAT/ML suggestion).
- Only the DC family (`DC`, `DC+SF`, `DC+SHEET`, `KRAFT`) gets automatic grammage/geometry.

## Used by
`pages::inquiry.costing` (Mode 1), components `x-costing.src` and `x-costing.field` (source marker). Instant mode: FROM DOCUMENT (code REMARK: "costing-instant belum disambung"), not rechecked.

## Open items
See `docs/ENGINE-ATTENTION.md` (status stays there, not copied). New finding: the REMARK writes Mode #2 as "belum disambung", while the registry text says Mode 2 is "typed by the officer"; one of them is outdated.

## Tests
`tests/Feature/CostingAdvisorTest.php` (5 tests), `CostingAdvisorScreenTest.php`.

<!-- johiwan-only -->

## Worked example (confidential; verified via `CostingAdvisorTest`, 5/5 passed 2026-10-16)
All figures below are test asserts, not guesses.
1. **Default**: new costing type DC, qty 1.000: margin 18 (`MARGIN_DEFAULT_PCT`), tolerance 5 (`TOLERANCE_DEFAULT_PCT`), Ongkos Cetak/RIM 750.000 and QC/PC 50 (template KALENDER), `i_film_faktor` 0,25 (constant, because the template marks 0 = default), `i_dc_persen_override` 5,3 (grammage Duplex 250). Test: `test_prefill_fills_empty_boxes_with_margin_default_and_template_rates`.
2. **Statistics**: 5 old costings (KRAFT) with final margins 20, 22, 24, 26, 28 -> median 24 -> the new costing gets margin 24, source `STAT`, `sample_count` 5. Test: `test_statistics_replace_default_after_enough_history_then_ml_threshold`.
3. **Override**: the officer changes margin 18 -> 22 and zeroes Ongkos Cetak/RIM; a repeated `prefill` changes neither; `valueToRestore` for the zeroed one = null. Test: `test_officer_override_and_zero_are_never_overwritten`.
4. **Restore**: the Ongkos Cetak/RIM box is emptied -> restored to 750.000; Hot Print (template 0) -> null. Test: `test_blank_box_is_restored_to_suggested_value`.

### Price chain formula (copy of `CostingEngine::calculateTier` in `chain()`, lines 517-550; explained so the report can explain)
Per part: `gross = c_total_part_cost_pc x qty_per_set + c_tooling_cost_order / qty`; if PPN: `net = gross - inputBahan x credit`, `credit = 1 - 1/PPN_INPUT_MATERIAL_DIVISOR`; tolerance `tt` = % of the part or tier: normal part `tolNet += net + gross x tt`, SHEET part `tolNet += net / (1 - tt)`. Base = rounding (`rounding_mode`, `rounding_step`) of `tolNet`; `dpp = dasar / (1 - margin)`; final price = `dpp x (1 + ppn%)` if PPN.
Chain worked example from ddt* data: NOT written yet (needs one new test case; proposed for Phase 4, awaiting Jo's approval).

> Checked 2026-10-16 after the ProCost BOM panel change (`ProCostContextService`, `costing.blade.php`; Jo agreed): the content of this card is not affected; cited line numbers of those two files may be about 30 lines lower. Only the cited items were re-read, not the whole card.

> Checked 2026-10-16 after the ProCost Book button (costing.blade.php; Jo agreed): the content of this card is not affected; cited line numbers of costing.blade.php may be about 70 lines lower. Only the cited items were re-read.

