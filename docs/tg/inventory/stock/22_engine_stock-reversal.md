# ENGINE card: Stock Reversal (cancel a posted stock document)

> Written 2026-10-16 (group Inventory/Stock). Source read in full: `app/Services/Inventory/StockReversalService.php` (200 lines), screen side `app/Livewire/Concerns/HasStockReversal.php` and `resources/views/partials/inventory-reversal.blade.php`. Tables: card `10_card_stock-document.md`. No test was run (no DB writes allowed in this task).
> Verified: 2026-10-16

- **Canonical name**: Stock Reversal Service. **Class**: `App\Services\Inventory\StockReversalService` (methods `refusal`, `state`, `reverse`, `duplicate`, `defaultReason`). **Registry**: key `stock-reversal`. **Module/sub-module**: Inventory > Stock.
- **Role**: enforces "a posted document is never edited or deleted". Cancel = a NEW document in the opposite direction, posted through the normal path, so the Stock Card stays chained. Decided by Jo 2026-10-08 with the mandatory reason text "Kesalahan transaksi no. XXX" (rule A.26 in `docs/globaluserrules.md`, FROM DOCUMENT; the default reason in code matches, `StockReversalService.php:47-50`).

## Trigger (no background / schedule)
User action on a POSTED Stock In / Out / Return form: buttons Cancel (reason dialog) and Duplicate, shown by the partial `inventory-reversal.blade.php`. No schedule, no other caller (grep `StockReversalService`: only the trait, the registry and tests).

## Input / Output
- **Input**: class (RM, SFG, FG), `docId`, reason (trimmed to 255 characters), login.
- **Output (writes)**: a new header in the class table with `doc_cat` REVERSAL, prefix RV, `ref_doc_type` REVERSAL, `ref_doc_id` and `reverses_doc_id` = original, notes "Reversal of {no}: {reason}", `doc_date` = now; its lines copy item, quantity (absolute), unit, lot, bin, batch, owner, price; the card rows of that document (via posting); on the original: `reversed_by_doc_id`, then `stat_cancel` = 1, `flow_status` CANCELLED, `cancel_reason`, `cancelled_by`, `cancelled_at`; reversal vouchers in `tm2acc` for usage journals of the original.
- **Returns**: `ok`, `error`, `doc_id`, `doc_no`, journal_warning, `stock_warning`.

## Flow
1. `refusal` (53-63), first match wins: not posted ("Only a posted document can be cancelled with a reversal. A Draft is edited or deleted."); already cancelled or already reversed ("The document is already cancelled."); is itself a reversal ("A reversal document cannot be cancelled again."); `doc_cat` is not STOCK or RETURN ("Adjustment, Opname and opening balance are corrected from their own screens, not by a reversal."); `ref_doc_type` is not MANUAL, OUT or blank ("This document was created by another process (:ref). Cancel it from its source document.").
2. `reverse` (88-166): reason required ("Fill in the reason for cancelling."); original must have card rows (lines without a card row are left out; none = "This document has no stock card rows to reverse."). Direction flips: a former In (or Return) becomes an Out and the reverse. Price: a returned-in line keeps its original unit price; a line that goes out again uses the average price of its original card row.
3. The reversal is saved as a Draft through `StockDocumentService`, the original is locked and claimed (`reversed_by_doc_id`) so only one reversal can exist, then `StockPostingService::postDocument` runs. If posting is refused (closed period, or a negative balance), the claim is released and the draft reversal is deleted ("discard"), and the posting error is shown; nothing remains.
4. On success the original is marked CANCELLED in a second transaction, and `reverseJournal` (169-183) reverses every POSTED usage voucher of the original (`source_type` STOCK_OUT_RM or STOCK_OUT_SFG) with the same reason; a failure there is only a warning (stock is already reversed). The usage journal is not built a second time for the reversal itself (posting skips REVERSAL documents).
5. `duplicate` (186-199): a new Draft copy with a new number (same prefix, today's date) for correcting and re-posting; refused for reversal documents; also offered on a cancelled document.

## Rules in words
- Reversal date is the moment of cancelling, not the date of the original. The correction therefore lands in the CURRENT open period; the original month's movement report keeps showing the mistake and the correction separately (the Stock Card report labels the original "· Cancelled" and the reversal "· Reversal" in the Doc No. column, `StockCardAllReport.php:142-160`).
- Refusals shown to the user come from posting too: "The document date is in a closed inventory period..." cannot occur for a reversal dated today unless the closed period reaches today; the common refusal is a balance that would go below zero (for example cancelling a receipt whose stock was already issued).

## Edge cases and findings
- **RESOLVED 2026-10-19 (Master T5c, Jo): returns and `qty_returned`**: `refusal` now ends with `returnedRefusal` (a Stock Out whose lines have `qty_returned` > 0 is refused: "This Stock Out has been returned (partly or fully). Cancel its Return documents first.") and `reverse` calls `restoreReturned` after the reversal document is posted, which gives the remainder back on the source lines (`GREATEST(COALESCE(qty_returned, 0) - qty, 0)`); `duplicate` keeps `source_detail_id`, `return_condition` and `line_note`. FACT, `StockReversalService.php:61,161,186-221`. See card `13_card_return-from-production.md`.
- **Stray reversal documents in the mockup**: 7 RM reversal documents RV/26/00001 to RV/26/00007 (posted 09-10 Oct 2026, 100 each) point to originals numbered `ZZV/1/...` whose headers no longer exist; their Out cards are still in `rm_stockcard`. Looks like test residue left behind by a test that deleted only the originals. FACT (query); cause NOT confirmed.
- **Original's cancel mark is a second transaction**: a failure after the reversal was posted leaves the original with `reversed_by_doc_id` but without `stat_cancel`; `refusal` still blocks a second reversal, but the grid shows it as POSTED. FROM CODE; not reproduced.
- **No document is Cancelled in the mockup** (0 headers with `stat_cancel` = 1) although the feature exists; Draft "Batalkan" (via `StockDocumentService::cancel`) is a different action that needs no reversal.

## Tests (existing, not run now)
`StockReversalServiceTest` (reversal of a wrong Stock Out keeps the card chained; second cancel and cancel of a reversal refused with nothing written; a reversal that would make a balance negative is refused whole; closed period refused; adjustment and other-process documents refused; duplicate makes a Draft with a new number; `state` tells the screen what to show; the Stock In form offers Cancel and shows the result), `StockPostingOrderTest::test_reversing_a_receipt_whose_stock_was_moved_away_is_refused`. Not found: a test for a Stock Out that has returns.

## Used by
Stock In / Out / Return forms (trait `HasStockReversal` on the v1 pages, route `v2.inv.stockdoc.reverse` on the v2 form `inv-stock-doc-form`); the Engine Registry page.
- Re-checked 2026-10-19 (Master T5c, Return from Production on the v2 form): the three Return rules above were added to `StockReversalService.php` as same-line and appended code (the refusal `match` got one `default` arm, `returnedRefusal` and `restoreReturned` were added after `duplicate`); the line numbers cited earlier in this card still hold.
