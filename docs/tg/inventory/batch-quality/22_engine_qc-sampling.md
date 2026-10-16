# ENGINE card: QcSamplingService (sampling plan: class -> intensity -> sample size)

> Written 2026-10-16. Source: `app/Services/Inventory/QcSamplingService.php` (153 lines) read in full. Setup tables: concept card `12_card_qc-sampling-setup.md` (not repeated). No test was run. Verified: 2026-10-16.

- **Canonical name**: QC Sampling Service. **Class**: `App\Services\Inventory\QcSamplingService` (static). **Registry**: key `qc-sampling`, mode `suggest`, group Inventory. **Module/sub-module**: Inventory > Batch & Quality.
- **Frozen status**: none named. It only suggests (A.7): the person decides the real sample and the verdict.
- **Synonyms**: sampling plan, intensity, tightened/normal/reduced/skip, supplier history. **NOT the same as**: the decision (`QcDecisionService`, card `23_engine_qc-decision.md`).

## Trigger (no background / schedule)
Called when a page or service needs a plan; nothing runs by itself. FACT (grep `QcSamplingService`):
| Caller | File:line |
|---|---|
| Batch page "Sampling Plan for this Lot" and default sample size | `app/Support/V2/Data/BatchQuality.php:44`; `resources/views/v2/screens/conditional-stock.blade.php:48,58` |
| Goods Receipt URGENT/Sampling card, per stock line | `app/Support/V2/Data/BatchQuality.php:69` |
| Stored on each dock decision (class, intensity, sample size) | `app/Services/Inventory/QcDecisionService.php:40,47` |
| Risk-class text shown in Setup | `app/Support/V2/Grid/Inv/QcIntensityRuleLookup.php:58-59` |

## Input / Output
- **Input**: item id, supplier id (may be null), lot quantity (stock unit).
- **Reads**: `inv_qc_risk_class`, `inv_qc_intensity_rule`, `inv_qc_sampling_plan`, `inv_qc_decision`, `inv_shortage_request`, `mat_raw`, `mat_category`, constant `INV_QC_NEW_SUPPLIER_LOTS` (5).
- **Output**: array `risk_class`, `intensity`, `reason` (NEW_SUPPLIER / RECOVERING / CONSISTENT_SUPPLIER / RISK_CLASS), `sample_size`, `aql_pct`, lots_decided, passes_in_row. **Writes nothing.**

## Flow
1. **Risk class** (`riskClass`): item row `I:<id>` (active) wins; else the deepest of the item's category chain level 1..5 (matched by name + parent); else `B`.
2. **History** (`history`): `SAMPLING_DOCK` decisions of the supplier, newest first. `lots` = how many; passes_in_row = consecutive `RELEASED` from the newest, stopping at the first non-release or at any decision dated at or before the last bad event. Bad event = latest `REJECTED` decision of a type other than dock sampling, or latest confirmed shortage request that is not a usage variance. has_reject = any `REJECTED` decision of the supplier or a bad event.
3. **Intensity** (`intensity`), in this order: (a) `lots` < `INV_QC_NEW_SUPPLIER_LOTS` -> TIGHTENED, reason NEW_SUPPLIER (for every class, class C included); (b) has_reject and passes_in_row < `recover_pass` -> TIGHTENED, RECOVERING; (c) start is not SKIP and passes_in_row >= `pass_to_reduced` -> REDUCED, CONSISTENT_SUPPLIER; (d) otherwise the rule's `start_intensity`, reason RISK_CLASS. No rule row -> NORMAL.
4. **Sample size** (`sampleSize`): SKIP or lot <= 0 -> 0; else the `sample_size` of the active plan row of that intensity with the highest `lot_from` <= lot; if the lot is below every row, the lowest row; result capped at `ceil(lot)`.

## Formula (as-is) and worked example
Values read from the dummy (same in tm2): rule A = TIGHTENED / AQL 1.00, B = NORMAL / 2.50, C = SKIP / 4.00, all with `pass_to_reduced` 10 and `recover_pass` 5. Plan rows for lot 16-25: TIGHTENED 8, NORMAL 5, REDUCED 2.
- Item without a risk row (class B), supplier with 12 clean dock lots, lot 20: lots 12 >= 5; no reject; passes 12 >= 10 -> REDUCED, CONSISTENT_SUPPLIER -> 2 samples.
- Same supplier after one first-article reject and only 2 passes since: has_reject and 2 < 5 -> TIGHTENED, RECOVERING -> 8 samples. After 5 clean passes it is back to the start intensity (NORMAL -> 5 samples) until it again reaches 10 passes.
- Class C item, new supplier (0 lots), lot 20: NEW_SUPPLIER -> TIGHTENED (the SKIP of class C does not apply) -> 8 samples. Class C with 6 clean lots: start SKIP -> 0 samples (certificate only).
- Lot 1,000 kg of a class B item, known supplier with 6 clean lots: NORMAL band 501-1200 -> 80 samples (screen prints "80 pcs").
- Batch without supplier (81 of the 82 dummy batches): `history` returns lots 0 -> always NEW_SUPPLIER / TIGHTENED.

## Edge cases
- A lot below the first band uses the lowest band; a fractional lot between two bands uses the lower band; `lot_to` is never read.
- Supplier history is built only from `SAMPLING_DOCK` decisions written at receipt posting or recorded on the batch page; the old backfilled lots have none.
- has_reject counts a rejected decision of any type, even years old; only the passes since it matter (until `recover_pass` is reached).

## Tests (read, not run)
`QcSamplingTest` (default class B and new supplier TIGHTENED; intensity follows history; class C skipped and sample never above lot; history ignores other suppliers and other decision types), `QcSamplingShortageTest` (a confirmed shortage tightens the supplier; a reject of any type counts).

## Used by
Conditional Stock page, Goods Receipt URGENT/Sampling card, `QcDecisionService::record`, the Setup grids (labels).
