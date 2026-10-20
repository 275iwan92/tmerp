# ENGINE card: QcDecisionService (one door for quality decisions)

> Written 2026-10-16. Source: `app/Services/Inventory/QcDecisionService.php` (93 lines) and the routes in `routes/v2_quality.php` read in full. Concept card: `13_card_qc-decision-and-first-article.md` (tables, rights, findings, not repeated). No test was run. Verified: 2026-10-16.

- **Canonical name**: QC Decision Service. **Class**: `App\Services\Inventory\QcDecisionService` (static). **Registry**: key `qc-decision`, mode `flow`, group Inventory. **Module/sub-module**: Inventory > Batch & Quality.
- **Frozen status**: none named. It records the person's decision (A.7); it computes no verdict.
- **Synonyms**: record decision, Release/Reject/Block, first article, batch age. **NOT the same as**: `QcSamplingService` (suggests), `QcTraceService` (what a reject triggers).

## Trigger (no background / schedule)
| Method | Trigger | File:line |
|---|---|---|
| `record` | Decision buttons on the Conditional Stock and Batch pages (route `v2.iq.decide`) | `routes/v2_quality.php:28-47`; `resources/views/v2/screens/conditional-stock.blade.php:65-71` |
| `record` | Dock decision written when a normal receipt makes a NEW batch | `app/Services/Inventory/ReceiptBatchService.php:57` |
| `firstArticle` -> `record` | First Article form (route `v2.iq.first-article`) | `routes/v2_quality.php:84-97` |
| `age` | Conditional Stock grid and page; escalation job | `app/Support/V2/Grid/Inv/ConditionalStockListGrid.php:50`; `app/Services/Inventory/QcEscalationService.php:23` |

## Input / Output
- **Input** (`record`): batch, type (`SAMPLING_DOCK`/`FIRST_ARTICLE`/`USAGE_DECISION`/`DEVIATION`), decision (`RELEASED`/`REJECTED`/`BLOCKED`/`CONDITIONAL_USE`), login, optional `reason`, `lot_qty`, `sample_size`, `sample_defects`, test result, `wo_id`, `intensity`.
- **Output (writes, one `tm2inv` transaction)**: `inv_batch` status via `BatchService::setStatus`; one `inv_qc_decision` row; then `QcTraceService` effects (`inv_qc_hold`, `inv_supplier_claim`). `firstArticle` adds an `inv_qc_first_article` row after the transaction.
- **Returns**: the decision row. Errors: `InvalidArgumentException` (unknown type/decision; reason shorter than 5 characters for Rejected/Blocked: "Give a reason (at least 5 characters) when a batch is rejected or blocked."; First Article result not PASS/FAIL; work order not found: "Work order not found.").

## Flow
1. Validate type and decision, require the reason for Rejected/Blocked.
2. For a dock sampling with a lot quantity, compute the plan (`QcSamplingService::planFor`) to store class, intensity and default sample size; for other types store the class only.
3. In one transaction: `status_before` = current status; `status_after` from the map (CONDITIONAL_USE -> CONDITIONAL); set the batch status; insert the decision.
4. Effects: REJECTED -> `onReject` (hold WOs + open claim); BLOCKED -> hold WOs; RELEASED / CONDITIONAL_USE -> clear holds.
5. `firstArticle`: PASS = RELEASED, FAIL = REJECTED (notes = reason); the decision `wo_id` is the tested WO.
6. `age`: hours = minutes since `qc_status_at` / 60 (1 decimal); OVERDUE at >= `INV_QC_DEADLINE_HOURS` (8), ESCALATE at >= `INV_QC_ESCALATE_HOURS` (4), else OK.

## Formula (as-is)
Age example: status set Monday 15:00, now Tuesday 02:30 = 11.5 h -> OVERDUE. The same batch at 4.0 h exactly is ESCALATE (>=).

## Edge cases
- No check of the current status (any decision on any status), and no check that the batch is active (concept card finding 1).
- `reason` is cut to 255 characters; test result to 255.
- The right to decide is NOT checked here (stated in the file header); the routes check `QcAuthority::canDecide`.
- The decision is stored even when the status does not change (RELEASED on a Released batch). If the user picks the Inspection type "Sampling at dock" (not the default on a non-Conditional batch), each such RELEASED row counts as one more clean lot in the supplier history. FACT, `QcDecisionService.php:42-50`, `QcSamplingService.php:100`, `conditional-stock.blade.php:56`.

## Tests (read, not run)
`QcDecisionTest` (reason needed; each decision sets the status; reject traces forward, holds, opens one claim; release clears holds; first article pass/fail; age by the settings), `QcScreensTest` (decision route releases, a reject without a reason shows the error; first article route).

## Used by
Conditional Stock, Batch form, First Article, receipt posting (dock decision), supplier history (sampling engine), URGENT Usage report.
- Re-checked 2026-10-20 after the Back button was added to the batch page of `resources/views/v2/screens/conditional-stock.blade.php` for users who cannot record the decision: it sits on the same line as the existing info alert (line 75), so no line was added or removed and every cited line number is unchanged; content still true.
