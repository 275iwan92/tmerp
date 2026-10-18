# ENGINE card: Stock Posting (StockPostingService + StockDocumentService)

> Written 2026-10-16 (group Inventory/Stock). Source read in full: `app/Services/Inventory/StockPostingService.php` (340 lines), `app/Services/Inventory/StockDocumentService.php` (69), `app/Services/Inventory/StockPostingRefused.php`. Tables/columns: concept cards `10_card_stock-document.md` and `11_card_stock-card-and-balance.md` (not repeated). No test was run (no DB writes allowed in this task).
> Verified: 2026-10-16

- **Canonical name**: Stock Posting. **Class**: `App\Services\Inventory\StockPostingService` (method `postDocument`) and `App\Services\Inventory\StockDocumentService` (methods `nextDocNo`, `saveDraft`, `cancel`). **Registry**: key `stock-posting`. **Module/sub-module**: Inventory > Stock.
- **Role**: the only legal writer of DOCUMENT stock card rows (IN, OUT, BB, AD; comment in the class header and on `RmStockcard`: screens must not insert). Two other writers exist by design: `StockTransferService` (TF rows, `StockTransferService.php:360-404`, always appended last in both bins) and `LandedCostCards` (VA value rows, through the chain). It enforces rules; it suggests nothing. The usage journal it makes follows the existing accounting setup (Dr cost of goods sold / Cr inventory).
- **Callers (FACT, grep `postDocument(`)**: the four v1 stock forms and the Approval Center (Adjustment approval); `PvPostingService` (three call sites: lines 214, 299, 394); `PickListService::issueStock`; `CountTaskService`; `CustomerMaterialService`; `SubconStockService`; `PartSupplyService` (Maintenance); `StockReversalService::reverse`.

## Trigger (no background / schedule)
Only user actions and other services call it; nothing in `routes/console.php` posts stock documents. Documents are always created first by `StockDocumentService::saveDraft` (Draft).

## Input / Output
- **Input**: stock class (RM, SFG or FG), `docId` of a Draft header with its lines, acting user login.
- **Output (writes, all in `tm2inv` unless noted)**: one card row per usable line in the class card table; header `stat_confirm` = 1, `flow_status` POSTED, `posted_by`, `posted_at`; the master item `cur_stock` in `tm2mst` (`mat_raw`, `prod_product`, `prod_part`); a usage journal in `tm2acc` (`fin_voucher`, through `JournalPostingService`); possibly a revaluation journal (card `26_engine_repost-reconcile.md`).
- **Returns**: array `posted` (bool), `error` (text or null), optionally journal_warning, `stock_warning`, `revaluation` (rows, value).
- **Does NOT write**: Delivery Order, Customer Invoice, `inv_period`, batch or pallet tables (the card carries `batch_id`; batch status belongs to the Batch cards).

## Flow
`postDocument` (lines 74-249), one database transaction on `tm2inv`:
1. Lock and load the header with lines (`lockForUpdate`). Refuse if `stat_confirm` ("The document has already been posted before.") or `stat_cancel` ("The document is already cancelled, it cannot be posted.") (91-96).
2. Closed period: if `InvPeriod::closedThrough()` exists and `doc_date` is on or before it, refuse with "The document date is in a closed inventory period (closed through :date). Enter a Stock Adjustment dated in an open period instead." (99-103).
3. Per line, in order: skip lines with `action_type` PURCHASE (line 112, kept for purchase lines that are not stock); skip quantity 0 unless the category is BB (115); skip a line already posted (a card exists with the same `reference_name` and `reference_detail_id`, idempotency, 123-127).
4. Decide the card kind: BB -> `doc_type` BB; RETURN -> IN; AD or OP -> AD; otherwise by `doc_type` 1 -> IN, else OUT (129-135). Quantity: Adjustment/Opname keep their sign into `qty_adj`; RETURN is the absolute value, and after the card row is written a RETURN line also increases `qty_returned` of its source Stock Out line in the same transaction (`addReturned`, `StockPostingService.php:203,343-348`); others are positive for in and negative for out (`qtyFinal`, 332-338).
5. Owner: a line whose `owner_type` is not COMPANY (customer or supplier material) gets no price and no value, and is never journalled (137-140, 199).
6. Insert the card row (148-171) with `value_in` = quantity x `unit_price` for receipts of company stock, then call `StockCardChain::recalculate` for that position (172, card `21_engine_stock-card-chain.md`).
7. Negative check: if the chain reports a first row that became negative, throw `StockPostingRefused` and roll the whole transaction back, so no card row of the document remains. Message for the new row: "Saldo item #... di lokasi #... akan negatif (...) -- posting dibatalkan."; for a later row: "Item #:item in location #:loc would go below zero on :date (:qty) because of movements dated after this document -- posting refused." Adjustment and Opname skip this check (175-180).
8. Collect revaluation data (rows of later stock-outs whose average price changed) and the usage journal rows (only OUT of RM and SFG, owner COMPANY, header `ref_doc_type` not PV_CANCEL or REVERSAL) (181-201).
9. After the loop: update the header (206-212); add the net delta of each item to the master `cur_stock` with COALESCE (218-221).
After commit: build the revaluation warning ("The average price of :n later stock-out row(s) changed; their value changed by :value. Journals already posted for them were not changed.") (230-236); post the usage journal (238-240, `postUsageJournal` 273-319); post one revaluation journal per RM item (`postRevaluation`, 253-266). Journal failures only become journal_warning; the stock stays posted.

`StockDocumentService`: `nextDocNo` = prefix + `/` + 2-digit year + `/` + 5 digits, highest existing number with that prefix + 1 (21-31); `saveDraft` replaces all lines each time and refuses a posted document with "The document is already posted, it cannot be changed." (39-58); `cancel` marks a Draft CANCELLED and refuses a posted one with "The document is already posted, it cannot be cancelled -- correct it with a Stock Adjustment." (61-68).

## Rules in words (no numbers)
- Moving average per bin by default; per item when the setting `INV_AVG_SCOPE` is ITEM (card `23_engine_item-average-cost.md`). A receipt blends the price of the stock before with the new price; out and adjustment rows carry the average price of the row before. FIFO/LIFO layers are not implemented (comment at line 147).
- Back-dated documents are inserted by `(doc_date, id)` and later rows are recalculated; the document date is not limited to a window (setting `INV_BACKDATE_DAYS` is not read).
- Usage journal accounts: item account, then category default (RM); for SFG the part, then product, then product category default. Lines without accounts are counted and reported, not posted.

## Edge cases and findings
- **Atomicity**: card rows, header update and `cur_stock` are in one transaction; the journals are not (separate connection `tm2acc`). A posted stock document can exist without its usage journal when accounts are missing or the ledger period is closed; only a warning tells. FROM CODE; the daily reconciliation exists to catch the difference.
- **Second posting is safe**: header flag check plus per-line idempotency; a concurrent double click is serialized by the header lock. FROM CODE; test `StockPostingOrderTest` covers ordering, not concurrency.
- **Receipt value 0**: a manual Stock In with `unit_price` 0 (the form default) writes `value_in` 0 and lowers the average; nothing requires a price. FROM CODE.
- **Quantity rounding**: card quantities are `decimal(20,4)`; negative detection uses a tolerance of 0.00005 in the chain.
- **`cur_stock` drift**: see card `11_card_stock-card-and-balance.md` Findings 1.
- Not found in this engine: reservation/booking check, WO check, approval, supplier or customer logic.

## Tests (existing, not run now)
`StockPostingOrderTest` (running balance and average in date order, back-dated receipt, same-day documents, refusal leaves no card row, back-dated receipt reprices transferred stock and reports later stock-out value change, closed period refused, reversing a receipt whose stock moved away is refused), `PvStockIntegrationTest` (goods receipt path), `BatchOwnerPostingTest` (customer-owned stock is not valued or journalled; a company Stock Out is offered to the usage journal, asserted weakly: journalled OR warning), `BatchStockCardChainTest`. Not found (grep of `tests/`): a test of the debit/credit accounts of the usage journal, of the SFG usage journal, or of concurrent posting.

## Used by
Stock In/Out/Return/Adjustment forms, Approval Center, Purchase Receive, Pick List Confirm, Count Task close, Customer Material receipt, Subcon issue, Maintenance part supply, Stock Reversal.
- Re-checked 2026-10-19 (Master T5c): `StockPostingService.php` gained the one-statement `addReturned` call on line 203 and the method at the end of the file; no other cited line moved.
