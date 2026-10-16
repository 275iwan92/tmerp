# Concept card: QC sampling setup (Risk Class, Sampling Rules, Sampling Plan)

> Written 2026-10-16 (group Inventory, Batch & Quality). FACT = mockup DB (`ddtinv`, compared with `tm2inv`) and code with line numbers, seen this session; no test was run. Verified: 2026-10-16.

- **Document type**: concept card. **Module/sub-module**: Inventory > Batch & Quality > Setup: Sampling Plan (`INV_BQ_PLAN`, `v2:qc-sampling-plan`), Sampling Rules (`INV_BQ_RULES`, `v2:qc-sampling-rules`), Risk Class (`INV_BQ_RISK`, `v2:qc-risk-class`); all grids (lookups), FACT `sys_app_menu`.
- **Canonical name**: three setup tables read by the QC Sampling Service: `inv_qc_risk_class` (which class A/B/C an item or category has), `inv_qc_intensity_rule` (what each class starts with and when it moves), `inv_qc_sampling_plan` (sample size per intensity and lot size).
- **Synonyms**: AQL, risk class A/B/C, Tightened / Normal / Reduced / Skip, sample size, "Z1.4", recover lots, pass-to-reduced.
- **NOT the same as**: a quality decision (`inv_qc_decision`, card `13_card_qc-decision-and-first-article.md`), the Production QC gate (`PrdWoOperation` QC_HOLD), the old QC widget.

## Facts
| Item | Content | Evidence |
|---|---|---|
| Risk class table | `inv_qc_risk_class`: `scope_key` UNIQUE (`C:<category id>` or `I:<material id>`), `scope_type` CATEGORY/ITEM, `category_id`, `material_id`, `material_code`, `risk_class`, `note`, `activate`. Dummy and tm2: 0 rows (everything is class B by default). | FACT, `information_schema`; counts |
| Risk class rule | A row picks a category OR an item code (both or neither is refused; unknown item code refused; one row per scope). Resolution: item row wins, otherwise the deepest category (level 1..5 matched by NAME along the item's `category1..5`), otherwise `B`. | FACT, `app/Support/V2/Grid/Inv/QcRiskClassLookup.php:101-114`; `app/Services/Inventory/QcSamplingService.php:45-87` |
| Intensity rule table | `inv_qc_intensity_rule`: `risk_class` UNIQUE, `start_intensity`, `aql_pct`, `pass_to_reduced`, `recover_pass`, `note`, `activate`. Three fixed rows (screen guide says they are not added or deleted). Seed (dummy = tm2): A = TIGHTENED, AQL 1.00; B = NORMAL, 2.50; C = SKIP, 4.00; all three: `pass_to_reduced` 10, `recover_pass` 5. | FACT, query; `app/Support/V2/Grid/Inv/QcIntensityRuleLookup.php:11-13` |
| Sampling plan table | `inv_qc_sampling_plan`: `intensity` (TIGHTENED/NORMAL/REDUCED), `lot_from`, `lot_to` (note only), `sample_size`, `note`, `activate`. Seed 45 rows = 15 lot bands x 3 intensities, note "ANSI/ASQ Z1.4 level II, simplified". NORMAL: lot 1-8 -> 2, 9-15 -> 3, 16-25 -> 5, 26-50 -> 8, ... 500001+ -> 1250. Dummy rows equal tm2 rows (0 differences). | FACT, query; `QcSamplingPlanLookup.php:11-13` |
| Plan rule | The row with the highest `lot_from` that is <= the lot quantity applies (`lot_to` is never read). A duplicate (intensity, `lot_from`) is refused on save. SKIP needs no plan row (0 samples, certificate only). Sample is capped at the lot quantity (rounded up). | FACT, `QcSamplingPlanLookup.php:72-77`; `QcSamplingService.php:142-152` |
| Who edits | Menu is open to users with module `INV`; the screens' guide says "the head of QC changes it here". No code limits the Setup grids to QA (not checked in the generic lookup base). | FACT for the guide text (`QcSamplingPlanLookup.php:11-13`); NOT checked for access |
| Where the result is shown | Conditional Stock batch page ("Sampling Plan for this Lot"), the Goods Receipt URGENT/Sampling card (per line: class, intensity, N pcs or "certificate only"). | FACT, `conditional-stock.blade.php:48`; `app/Support/V2/Data/BatchQuality.php:63-77` |
| Rulebook | Q-01 rencana sampling, D21 kelas risiko awal (FROM DOCUMENT, `inventorywarehouse_v201_scope.md` section 7.3 and 13; `docs/globaluserrules.md` line 267 says initial class values are still open). | FROM DOCUMENT |

## Relations
- **source**: `mat_raw` (item, `category1..5`), `mat_category`, user-entered setup rows, constant `INV_QC_NEW_SUPPLIER_LOTS` (5), supplier history from `inv_qc_decision` and `inv_shortage_request`.
- **used_by**: `QcSamplingService::planFor` (engine card `22_engine_qc-sampling.md`), `QcDecisionService::record` (stores class/intensity/sample on the decision row), URGENT/Sampling card on Goods Receipt, Conditional Stock batch page.
- **flows**: setup rows -> `planFor(item, supplier, lot qty)` -> suggested sample size shown to QA -> QA records the real sample in the decision.
- **frozen_zone**: none named; sampling rules are Jo's/QC head's data, not a frozen flow.

## Findings (recorded, not fixed)
1. **Risk class is looked up in the raw-material master for every batch class.** `riskClass($itemId)` queries `mat_raw` by `material_id` and the `I:<id>` scope; for an SFG or FG batch the `item_id` is a part or product id, so the same number can hit an unrelated raw material (`QcDecisionService.php:40,47` and `QcSamplingService.php:45-68` never receive the class). FACT; effect is limited to the class stored on a decision row and the plan shown for non-RM batches.
2. **Sampling "pcs" versus stock unit.** The lot quantity used for the plan is `pv_line.c_qty_accepted` in the stock unit (kg for paper reams, sheets or pieces depending on the item), but the screen prints the sample as "pcs" and the plan bands read as piece counts. Whether a 5 000 kg lot should be sampled as 200 pieces is not defined anywhere (FACT: `BatchQuality.php:44,76`; meaning NOT verified with QA).
3. **Class A jumps from TIGHTENED straight to REDUCED.** For A (start TIGHTENED) a clean supplier with 10 passes in a row moves to REDUCED, skipping NORMAL (`QcSamplingService.php:135-137`). The scope document describes "lulus N lot berturut-turut -> Reduced" without this subtlety (FROM DOCUMENT). Possibly intended; not confirmed.
4. **The risk class table is empty in both databases**, so every item is class B/NORMAL and no item is ever sampled TIGHTENED because of its class or SKIPped; only the new-supplier rule can tighten. FACT (counts).
5. **`lot_to` is stored and shown ("To (note)") but ignored**; overlapping or gapped bands are therefore not detected. FACT, `QcSamplingPlanLookup.php:60`.
6. **Seed plan values are a simplification of Z1.4 level II** (note text on every row); who approved them as the factory standard is not recorded (FROM DOCUMENT item D21 says user approved "kelas risiko awal" as a setting).
7. **Category match by name, not id.** `categoryChain` finds each level by `category_name` + `parent_id`; two categories with the same name under the same parent cannot be told apart. FACT, `QcSamplingService.php:75-84`.

## Already exists? Searched where
Searched `inv_qc_*`, `QcSamplingService`, `Qc*Lookup`, menu `INV_BQ_PLAN/RULES/RISK`, EngineRegistry key `qc-sampling`. Result: ALREADY EXISTS; no new table is needed. An older "QC widget" exists on the dashboard (not read).
