# ENGINE card: Shortage Request Service (with its helper Shortage Detector)

> Written 2026-10-17 (group Procurement). Source: `app/Services/Pur/ShortageRequestService.php` (257 lines) read in full; `app/Services/Pur/ShortageDetector.php` (61 lines) read in full; routes `routes/v2_quality.php:122-162`. The document facts (table, statuses, effects, findings) are in the Inventory concept card [Shortage Request](../../inventory/batch-quality/15_card_shortage-request.md) and the nightly detector in [29_engine_shortage-auto-detector.md](../../inventory/batch-quality/29_engine_shortage-auto-detector.md); this card adds only the trigger map and the method contract, and does not repeat their facts. No test was run for this card. Verified: 2026-10-17.

- **Canonical name**: Shortage Request Service. **Class**: `App\Services\Pur\ShortageRequestService` (static). **Registry**: key `shortage-request`, mode `flow`, group Procurement. Helper class `App\Services\Pur\ShortageDetector` (registry key `shortage-detector`, mode `suggest`, compares only, writes nothing). **Module/sub-module**: Procurement > Receive > Shortage Request (`PROC_SHORT`, the same screen as `INV_BQ_SHORT` in Inventory).
- **Frozen status**: it changes the received quantity, the PO line and the GR/IR of a posted receipt, so it sits inside the frozen Goods Receipt / PO / GR-IR flow; the rule is a v2.01 rule (Jo). Ask Jo before changing it.
- **Synonyms**: kurang kirim, short delivery, recount, window lapsed.
- **NOT the same as**: Purchase Return (a physical return, card `../return/10_card_purchase-return.md`), Supplier Claim (Inventory), the PO tolerance close (`CLOSED_BY_TOLERANCE`, in `20_engine_pv-posting.md`).

## Trigger (no background / schedule for this class; the nightly run belongs to the Auto Detector)
Searched `routes/console.php` (only `inv:detect-shortage`, daily 05:15, which calls the Auto Detector, which calls `create`), jobs and observers (none), and all callers by grep. FACT.
| Method | Trigger | File:line |
|---|---|---|
| `create` | Screen Shortage Request, "New Record" (route `v2.shortage.create`); also the Auto Detector | `routes/v2_quality.php:123-133`; `app/Services/Pur/ShortageAutoDetector.php:74` (card 29) |
| `recount`, `confirm`, `dismiss`, `send`, `receive`, `refuse`, `close` | Step buttons on the request form (route `v2.shortage.step`, one route for the seven steps) | `routes/v2_quality.php:145-162` |
| `closeLapsed` | Button "Close Lapsed" | `routes/v2_quality.php:135-143` |
| `hasCorrections` | Cancel of a posted receipt (guard) | `app/Services/Pur/PvPostingService.php:154` |
| `ShortageDetector::reconcileOptions` | the pick list of work order + material on the form | `app/Support/V2/Data/ShortagePage.php:26` |
| `ShortageDetector::evaluate`, `tolerancePct`, `reconcile` | inside `create` | `ShortageRequestService.php:42-60` |
The route group has no module gate: the right of each step is checked inside the service by `QcAuthority`.

## Input / Output
- **Input**: `create`: work order + material key `wo_id:material_id` from the reconcile view, a receipt line (`pv_line_id`) of a **POSTED** receipt of type PO, SUBCON or CUSTOMER, good output in the material unit, optional standard waste (empty = the work order's), need date, notes. Other steps: the request and a note or the typed recount (counted quantity, weight, confirmed shortage).
- **Output (writes)**: `inv_shortage_request` rows; on Confirm the receipt line, the PO line, a GR/IR correction journal, a Stock Out with reference PV_SHORT; a Draft additional PR and a Draft debit note (`ShortageFollowUp`, see the PR sources card `../requisition/12_card_pr-sources-and-buy.md` and the Debit Note card `../return/11_card_debit-note.md`); notices. Details in the Inventory card.

## Flow (text twin)
1. `create` checks: the issued material exists for the work order, the receipt is POSTED and of a known type, then `ShortageDetector::evaluate`; refuses when the candidate is 0 ("usage variance") or inside the PO tolerance; otherwise a DRAFT row numbered `SR` + year + 5 digits and a notice to the head of the warehouse.
2. `recount` (head of the warehouse, only while DRAFT): confirmed shortage above 0 and not above the suspected quantity; a note of 5+ characters.
3. `dismiss` or `confirm` (PPIC): dismiss closes as usage variance; confirm corrects the receipt, the PO and the GR/IR and reduces the stock.
4. `send` (Purchasing): the window starts. `receive` or `refuse` follows; `close` finishes. `closeLapsed` refuses every SENT request past its window.
5. Each step first checks the status (`assertStatus`) and the right of the actor.

## Formula
Only in the helper: candidate = max(0, issued net - (output good + standard waste)), tolerance quantity = issued net x tolerance % / 100, a request only if the candidate is above both 0 and the tolerance quantity. The numbers and a worked example are in the Auto Detector card (not repeated).

## Edge cases
- `recount` is possible only in DRAFT, so a wrong recount cannot be corrected after PPIC confirms.
- No Cancel step exists (a wrong Draft is dismissed as usage variance).
- `closeLapsed` is a manual button; the nightly schedule does not call it (only the Auto Detector is scheduled), so lapsed requests stay SENT until someone clicks. FACT (grep of `routes/console.php`: only `inv:detect-shortage` is scheduled; `closeLapsed` has the single caller above).

## Used by
Shortage Request screen, Shortage by Supplier report, Purchase Receive (cancel guard), Auto Detector.

## Open items
No `ATTENTION-ENGINE` item concerns it. New finding (this card): the manual "Close Lapsed" has no schedule, so the 7-day window is only enforced when someone presses the button. The ten findings of the Inventory card (customer stock, no duplicate guard, not atomic across databases, and so on) remain open there.

## Tests
`tests/Feature/ShortageRequestTest.php` and `tests/Feature/ShortageAutoDetectTest.php` exist (named in the Inventory cards; not run in this session).
