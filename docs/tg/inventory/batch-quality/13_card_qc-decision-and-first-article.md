# Concept card: QC decision (sampling, usage decision, deviation) and First Article

> Written 2026-10-16 (group Inventory, Batch & Quality). FACT = mockup DB and code with line numbers, seen this session; no test was run (tests listed are READ only). Verified: 2026-10-16.

- **Document type**: concept card. **Module/sub-module**: Inventory > Batch & Quality > Conditional Stock (decision card), Batch (Quality decisions tab and card), First Article (`INV_BQ_FA`, `v2:qc-first-article`).
- **Canonical name**: QC decision = one row of `tm2inv.inv_qc_decision` (model `InvQcDecision`) written by `QcDecisionService::record`; First Article = one row of `inv_qc_first_article` (model `InvQcFirstArticle`) that also writes a `FIRST_ARTICLE` decision.
- **Synonyms**: Release, Reject, Block, Conditional use, Usage Decision, sampling at dock, first piece at the machine, deviation.
- **NOT the same as**: sampling setup (card `12_card_qc-sampling-setup.md`), Production QC gate on a work-order operation (`prd_wo_operation`, QC_HOLD / QC_PASSED), customer complaint review.

## Facts
| Item | Content | Evidence |
|---|---|---|
| Decision table | `inv_qc_decision`: PK `id_decision`, `batch_id`, `decision_type`, `decision`, `status_before`, `status_after`, `supplier_id`, `risk_class`, `intensity`, `lot_qty`, `sample_size`, `sample_defects`, test result, `wo_id`, `reason`, `decided_by`, `decided_at`. Insert-only (no `updated_at`). Dummy: 1 row (`SAMPLING_DOCK`, `RELEASED`, `RELEASED` -> `RELEASED`). | FACT, `information_schema`; `app/Models/Inventory/InvQcDecision.php:21` (`$timestamps = false`) |
| Types and decisions | Types: `SAMPLING_DOCK`, `FIRST_ARTICLE`, `USAGE_DECISION`, `DEVIATION`. Decisions -> resulting batch status: `RELEASED` -> RELEASED, `REJECTED` -> REJECTED, `BLOCKED` -> BLOCKED, `CONDITIONAL_USE` -> CONDITIONAL. | FACT, `app/Services/Inventory/QcDecisionService.php:20-25` |
| Reason rule | Rejected or Blocked needs a reason of at least 5 characters (message "Give a reason (at least 5 characters) when a batch is rejected or blocked."). `BatchService::setStatus` repeats the check (any non-empty reason). | FACT, `QcDecisionService.php:36-38`; `app/Services/Inventory/BatchService.php:100-106` |
| One transaction | Status change + decision row + trace effect are inside one `tm2inv` transaction. REJECTED -> hold work orders + open claim; BLOCKED -> hold work orders only; RELEASED / CONDITIONAL_USE -> clear holds. | FACT, `QcDecisionService.php:42-60` |
| Who may decide | `QcAuthority::canDecide`: group `QA`, `QA_HEAD`, or Ops Manager and above. Checked by the route, NOT inside the service (so tests and screens share one path). The Quality Decision card is hidden for others; the batch page shows the message "Only Quality Assurance or an operations manager can record the decision." | FACT, `app/Services/Inventory/QcAuthority.php:21,70-75`; `routes/v2_quality.php:33-35`; `conditional-stock.blade.php:52,75` |
| Decision form | Inspection (type), lot quantity (default = received qty), sample size (default = plan), defective in sample, test result, reason; buttons Conditional, Block, Reject, Release (all `formnovalidate`). Default type: SAMPLING_DOCK when the batch is CONDITIONAL, else USAGE_DECISION. The same two buttons Release/Reject exist on the Batch form (`from=inv-batch`). | FACT, `conditional-stock.blade.php:52-76`; `inv-batch.blade.php:101-114` |
| Sample defects | `sample_defects` is stored and shown, but nothing compares it with the AQL or the sample; the verdict is always the person's. | FACT, grep `sample_defects` (only record + display) |
| First Article | Route `v2.iq.first-article`: QA only; fields work order (latest 300), material batch (only `CONDITIONAL`, `QUARANTINE`, `RELEASED`, active, latest 300), result PASS/FAIL, notes. PASS -> `RELEASED`; FAIL -> `REJECTED` (notes = reason, 5+ chars) -> holds + claim. Writes a `FIRST_ARTICLE` decision AND an `inv_qc_first_article` row (`decision_id` links them). Dummy: 0 rows. | FACT, `QcDecisionService.php:65-75`; `routes/v2_quality.php:84-97`; `app/Support/V2/Data/BatchQuality.php:104-108` |
| Supplier history | Dock sampling decisions (`SAMPLING_DOCK`) per `supplier_id` feed the intensity (card `22_engine_qc-sampling.md`); a REJECTED of any type counts as a bad event. | FACT, `QcSamplingService.php:95-123` |
| Batch history tab | The "History" log merges stock-card movements and one "Status A -> B" line per decision. | FACT, `app/Support/V2/Data/Batches.php:113-132` |
| Test coverage (read) | `QcDecisionTest` (reason needed, status per decision, reject -> trace/hold/claim, release clears holds, first article pass/fail, age/escalation), `QcScreensTest` (decision route, first article route). | FACT, `tests/Feature/QcDecisionTest.php`, `tests/Feature/QcScreensTest.php` (names listed, not run) |

## Relations
- **source**: batch (`inv_batch`), work order (`prd_wo` for First Article), person with `QA` rights.
- **used_by**: supplier sampling history, Conditional Stock history, URGENT Usage report (latest decision), claim and hold creation (`QcTraceService`), the Batch form history.
- **flows**: record decision -> `BatchService::setStatus` -> decision row -> trace effects. See also `14_card_supplier-claim-and-trace.md`, engine `23_engine_qc-decision.md`.
- **frozen_zone**: none named; the decision flow is a v2.01 rule (A.26 / D16), changes need Jo.

## Findings (recorded, not fixed)
1. **No state machine.** `QcDecisionService::record` accepts any decision on any current status: a REJECTED or BLOCKED batch can be Released again (clearing holds), a RELEASED batch can be Rejected, and a First Article PASS can release a QUARANTINE batch that never passed dock sampling. The screen offers the buttons on every batch, including Released ones. FACT, `QcDecisionService.php:30-60`.
2. **Release after Reject leaves the claim OPEN.** `clearHolds` frees work-order holds, but `inv_supplier_claim` stays `OPEN`; someone must set it to Dropped by hand. FACT, `QcTraceService.php:80-83` and the `else` branch at `QcDecisionService.php:57`.
3. **First Article is not tied to the batch actually issued to the work order.** The batch is any batch in the dropdown; nothing checks that material of that batch was issued to the chosen WO. A FAIL then holds the WOs found by the forward trace of that batch, which may not include the tested WO. FACT, `BatchQuality::batchOptions`.
4. **A First Article FAIL rejects the whole batch**, even if only one pallet or one machine run failed; there is no partial qty. By design in the code comments (FROM CODE); not discussed with Jo in the documents read.
5. **Status `QUARANTINE` has no producer.** The status exists in the flags, the Conditional Stock list, the escalation job, the pick/putaway rules and the Batch stat boxes, but no code path sets it: `QcDecisionService` maps no decision to it and every caller of `BatchService::create` passes `RELEASED` or `CONDITIONAL` or nothing. A decision made on a Quarantine batch can never return a batch to Quarantine. FACT, grep `QUARANTINE` in `app/` and the foundation migration (comment only).
6. **Decision rows have no cancel/undo.** A wrong click is corrected by a new decision; the history keeps both. Acceptable, but combined with finding 1 it means a mistaken Reject opens a claim that stays. FACT.

## Already exists? Searched where
Searched `inv_qc_decision`, `inv_qc_first_article`, `QcDecisionService`, EngineRegistry key `qc-decision`, menu `INV_BQ_FA`. Result: ALREADY EXISTS. Production's own QC gate (`prd_wo_operation`) is a different mechanism and was not read.
- Re-checked 2026-10-20 after the Back button was added to the batch page of `resources/views/v2/screens/conditional-stock.blade.php` for users who cannot record the decision: it sits on the same line as the existing info alert (line 75), so no line was added or removed and every cited line number is unchanged; content still true.
