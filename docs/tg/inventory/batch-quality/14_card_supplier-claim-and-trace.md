# Concept card: Supplier Claim, work-order hold and forward trace of a rejected batch

> Written 2026-10-16 (group Inventory, Batch & Quality). FACT = mockup DB and code with line numbers, seen this session; no test was run. Verified: 2026-10-16.

- **Document type**: concept card. **Module/sub-module**: Inventory > Batch & Quality > Report > Supplier Claims (`INV_BQ_CLAIM`, `v2:supplier-claims`); claim status is edited on the Conditional Stock batch page; trace and holds are shown on the same page ("Where This Batch Was Used", "Held Work Order").
- **Canonical name**: Supplier Claim = one row of `tm2inv.inv_supplier_claim` (model `InvSupplierClaim`), number `CL` + year + 5 digits. Hold = one row of `inv_qc_hold` (model `InvQcHold`) per batch and work order. Trace = `QcTraceService::forward`.
- **Synonyms**: claim to supplier, forward trace, telusur maju, WO ditahan, held work order, batch used where.
- **NOT the same as**: Shortage Request (card `15_card_shortage-request.md`, a request to send missing quantity), Debit Note (`dn_debit_note`, Purchasing), Purchase Return (`prt_return`), customer complaint (Production > Quality).

## Facts
| Item | Content | Evidence |
|---|---|---|
| Claim table | `inv_supplier_claim`: `id_claim`, `claim_no` UNIQUE, `batch_id`, `supplier_id`, `pv_id`, `material_id`, `qty_claimed`, `uom_code`, `reason` (required), `status` (default OPEN), `notes`, audit. No amount/price column. | FACT, `information_schema` |
| Status values | `OPEN`, `SENT`, `SETTLED`, `DROPPED`. No transition rule: the batch page lets QA/Purchasing set any of the four (the select offers all). | FACT, `app/Services/Inventory/QcTraceService.php:85-93`; `routes/v2_quality.php:72-82`; `app/Support/V2/Grid/Inv/SupplierClaimReport.php:13` |
| When opened | Automatically when a batch decision is `REJECTED` (not on `BLOCKED`). One open claim (status OPEN or SENT) per batch: a second reject returns the existing claim. `qty_claimed` = sum of `pv_line.c_qty_accepted` of lines with that `i_batch_id`; `supplier_id` = the batch's supplier; `pv_id` = first such line. | FACT, `QcTraceService.php:45-78`; `QcDecisionService.php:52-55` |
| Who edits the claim | QA (`canDecide`) or Purchasing follow-up (`canFollowUp`: group `MGR_PUR` or any user who can access module `PROC`) or Ops Manager and above. Others see the fields read-only and no Save button. | FACT, `QcAuthority.php:62-67`; `conditional-stock.blade.php:89-105`; `routes/v2_quality.php:75-77` |
| Hold table | `inv_qc_hold`: `batch_id`, `wo_id`, `wo_no`, `co_id`, `delivery_id`, `qty_issued`, `status` (HELD/CLEARED), `reason`, `held_by/at`, `cleared_by/at`. UNIQUE (`batch_id`, `wo_id`). Dummy: 0 rows. | FACT, index list; query |
| Trace query | Posted (`flow_status` POSTED), not cancelled `rm_stock` documents of category STOCK and type -1 (issue) that have a `wo_id`, joined to lines of the batch (`rm_stock_d.batch_id`), OR old lines without a batch matched by item + normalised supplier lot (no match when the batch has no lot). Then WO -> `source_co_id` -> `co_order.co_no`, `source_delivery_id` -> `do_delivery.do_no`. | FACT, `QcTraceService.php:22-42` |
| Hold effect | `holdWorkOrders` writes/refreshes one HELD row per WO (qty = sum issued). `clearHolds` sets all HELD rows of the batch to CLEARED when a later decision is not Rejected/Blocked. | FACT, `QcTraceService.php:51-64,80-83` |
| Claims report | List of all claims, newest first (limit), with batch, item, qty, reason, opened at; row click opens Conditional Stock for the BATCH id. Stat boxes All / Open / Sent / Settled. | FACT, `SupplierClaimReport.php:35-38,45-54,71-75` |
| Dummy data | 8 claims, all OPEN, `supplier_id` NULL, `qty_claimed` 0, reason "Failed the moisture test", created by `johiwan` on 2026-10-09..13; ALL 8 point to `batch_id` values (629..1001) that do not exist in `inv_batch` (ids 210..334). `tm2inv.inv_supplier_claim`: 0 rows. | FACT, queries (orphan count = 8) |

## Relations
- **source**: a REJECTED/BLOCKED decision (`QcDecisionService`), batch (`inv_batch`), receipt lines (`pv_line.i_batch_id`), issue documents (`rm_stock`, `rm_stock_d`), work orders (`prd_wo`), CO and DO numbers (read only).
- **used_by**: Conditional Stock batch page, Supplier Claims report, supplier quality history (only decisions count there; claims do not).
- **flows**: Reject -> `onReject` -> holds + claim -> Purchasing marks Sent/Settled/Dropped. Later Release -> holds CLEARED, claim untouched.
- **frozen_zone**: none named.

## Findings (recorded, not fixed)
1. **A "hold" holds nothing.** `inv_qc_hold` is written only by `QcTraceService` and read only by the batch page. No production, picking, delivery or invoicing code looks at it (grep for `InvQcHold` / `inv_qc_hold` finds only the trace service, the batch page data, the screen registry and the engine registry). The WO keeps running and the delivery can ship; the "hold" is a warning list. The scope document wanted WO **and finished goods** held (FROM DOCUMENT, `inventorywarehouse_v201_scope.md` 7.5); the code stores the CO and delivery ids but holds neither. FACT.
2. **Eight orphan claims in the dummy.** The claims point to batches that no longer exist. Cause (FACT from reading): `BatchLookupTest` posts a Reject through the real route (`tests/Feature/BatchLookupTest.php:224`, reason "Failed the moisture test"), which opens a claim, but its `tearDown` removes batches, decisions and cards only, not claims or holds (`BatchLookupTest.php:42-54`); `QcDecisionTest` does clean them. Same leak is possible for `inv_qc_hold`. The Supplier Claims screen therefore shows 8 OPEN claims with an empty supplier and quantity 0 in UAT.
3. **A claim can be opened with no supplier and quantity 0**, as in the dummy: when the batch has no `supplier_id` (81 of 82 dummy batches) or no receipt line, nothing warns that the claim cannot be sent to anyone. FACT, `QcTraceService.php:73-77`.
4. **Release leaves the claim OPEN** (card 13 finding 2), and **BLOCKED opens no claim** although it holds the work orders. FACT.
5. **No money.** The claim has quantity and reason but no value, no link to a debit note or purchase return, and no print. FACT (columns). Status SETTLED does not move any stock or journal.
6. **Trace covers raw-material issues only.** Returns of unused material to stock (`v_inv_wo_reconcile.qty_return_good`) are not netted off `qty_issued`, and finished-goods batches are not traced forward to delivered pallets. FACT, `QcTraceService.php:24-28`.
7. **The claim status route has no `try/catch`** and no per-status authority; an invalid status is stopped only by the validation `Rule::in`. Low risk. FACT, `routes/v2_quality.php:72-82`.

## Already exists? Searched where
Searched `inv_supplier_claim`, `inv_qc_hold`, `QcTraceService`, EngineRegistry key `qc-trace`, `dn_debit_note`. Result: ALREADY EXISTS for claim + trace; a debit note exists only for shortage refusals (`ShortageFollowUp::debitNote`), not for claims.
- Re-checked 2026-10-18 after `tests/Feature/BatchLookupTest.php` changed: only its menu test now walks up to the root of the Batch item and expects `INV` (menu is Inventory > Stock > Batch & Quality > Batch); the claim and tearDown findings above are unchanged; 12 tests passed on `--env=mockup`.
- Re-checked 2026-10-20 after the Back button was added to the batch page of `resources/views/v2/screens/conditional-stock.blade.php` for users who cannot record the decision: it sits on the same line as the existing info alert (line 75), so no line was added or removed and every cited line number is unchanged; content still true.
