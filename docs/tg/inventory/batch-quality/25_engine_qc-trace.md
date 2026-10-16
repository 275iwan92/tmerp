# ENGINE card: QcTraceService (forward trace, work-order holds, supplier claim)

> Written 2026-10-16. Source: `app/Services/Inventory/QcTraceService.php` (103 lines) read in full. Tables and findings: concept card `14_card_supplier-claim-and-trace.md` (not repeated). No test was run. Verified: 2026-10-16.

- **Canonical name**: QC Trace Service. **Class**: `App\Services\Inventory\QcTraceService` (static). **Registry**: key `qc-trace`, mode `flow`, group Inventory. **Module/sub-module**: Inventory > Batch & Quality.
- **Frozen status**: none named. It reads Production and Sales tables (`prd_wo`, `co_order`, `do_delivery`) and writes only its own two tables.
- **Synonyms**: telusur maju, where used, hold, claim. **NOT the same as**: backward trace (supplier -> batch), which does not exist.

## Trigger (no background / schedule)
| Method | Trigger | File:line |
|---|---|---|
| `forward` | Batch page "Where This Batch Was Used" | `app/Support/V2/Data/BatchQuality.php:50-51` |
| `onReject`, `holdWorkOrders`, `clearHolds` | Every QC decision (reject, block, others) | `app/Services/Inventory/QcDecisionService.php:52-58` |
| `setClaimStatus` | Claim save on the batch page (route `v2.iq.claim`) | `routes/v2_quality.php:79` |

## Input / Output
- **Input**: a batch, reason, login; claim + new status.
- **Reads**: `rm_stock`/`rm_stock_d` (issues), `prd_wo`, `co_order`, `do_delivery`, `pv_line`.
- **Output (writes)**: `inv_qc_hold` (upsert per batch + WO) and `inv_supplier_claim` (insert, status update) in `tm2inv`.

## Flow
1. **forward**: issue lines = posted, not cancelled `rm_stock` of category STOCK and type -1 with a `wo_id`, whose line has the batch id; or, for old lines with no batch id, same item and same normalised supplier lot (batch without lot: no old-line match). Then WO status, CO number and delivery number are looked up (`source_co_id`, `source_delivery_id`).
2. **holdWorkOrders**: group by WO; upsert HELD with quantity, CO and delivery ids, reason, who and when.
3. **openClaim**: reuse an OPEN/SENT claim of the batch; otherwise create `CL`+yy+5-digit with quantity = sum of accepted qty of the batch's receipt lines, supplier = batch supplier, first receipt as `pv_id`, status OPEN.
4. **clearHolds**: all HELD rows of the batch -> CLEARED.
5. **setClaimStatus**: OPEN / SENT / SETTLED / DROPPED only; notes replaced when given.

## Formula (as-is)
Claim quantity = sum of `pv_line.c_qty_accepted` where `i_batch_id` = batch (stock unit of the first line). Example: a batch received on two lines of 500 and 300 kg -> claim 800 kg; a batch with no receipt line (manual or backfilled) -> 0.

## Edge cases
- The hold is a record, not a stop (concept card 14 finding 1).
- Raw-material issues only; returns are not netted; finished goods are not traced (concept card 14 finding 6).
- Claim numbering reads the maximum without a lock (as the batch number).
- Releasing does not drop the claim.

## Tests (read, not run)
`QcDecisionTest::test_reject_traces_forward_holds_the_work_orders_and_opens_one_claim_then_release_clears_the_holds`. Not tested: claim status route, old-line matching by lot.

## Used by
Conditional Stock page, Supplier Claims report, `QcDecisionService`.
