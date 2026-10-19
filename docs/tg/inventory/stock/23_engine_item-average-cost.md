# ENGINE card: Average Cost per Item (ItemAverageCost)

> Written 2026-10-16 (group Inventory/Stock). Source read in full: `app/Services/Inventory/ItemAverageCost.php` (254 lines), `app/Console/Commands/InvRevaluePerItem.php` (first 100 lines), report `app/Support/V2/Grid/Inv/ValuationCheckReport.php` (header). Cards: `11_card_stock-card-and-balance.md`, `21_engine_stock-card-chain.md`. No test was run (no DB writes allowed in this task). No amounts or prices are quoted in this card.
> Verified: 2026-10-16

- **Canonical name**: Average Cost per Item ("Average per item", decisions D13/D15). **Class**: `App\Services\Inventory\ItemAverageCost` (static: `enabled`, `snapshot`, `recalculate`, `currentAvgMany`, `currentAvg`, `readerAvg`, `rows`). **Registry**: NOT registered (searched `EngineRegistry`). **Module/sub-module**: Inventory > Valuation & Period.
- **Role**: valuation method of the company is moving AVERAGE (decision of Jo 2026-10-08, rule A.26; LIFO not allowed, FIFO postponed, FROM DOCUMENT in `docs/design-notes/inventory/15_inv-open-decisions.md`). This class changes WHERE the average lives: per bin (old behaviour) or per item across all bins.

## Trigger (no background / schedule)
Called by `StockCardChain::recalculate` on every posting when the setting is ITEM, by `StockTransferService` after a transfer, and by readers (reports, Warehouse Map, adjustment form) through `readerAvg`/`currentAvgMany`. The one-time switch is the console command `inv:revalue-per-item`.

## Input / Output
- **Input**: stock class, item id, part id (FG only), the card row from which to recalculate.
- **Settings**: `INV_AVG_SCOPE` in `sys_constant`: ITEM turns this engine on, anything else (BIN) leaves the old per-bin average. Mockup value now: BIN (FACT, query); the cut-over table `inv_item_avg_open` already holds 62 rows (RM 58, FG 3, SFG 1), written by `inv:revalue-per-item --apply`.
- **Output (writes)**: card rows of the item: `avg_unitprice` (item average after the row), `value_eb` (value of the BIN after the row; the sum of the last `value_eb` of all bins is the item's value), `value_in` for transfer-in and non-company rows.
- **Returns**: `avg`, `qty`, `value`, number of rows written, and the list of later OUT rows whose average changed (revalued_docs).

## Flow (`recalculate`, 56-135)
1. Start state: the cut-over point of the item from `inv_item_avg_open` (quantity, value, average at a given card), or zero for an item without one. With a start row `$from`, the state just before it is read back from the cards (`stateBefore`, 143-170); if the row is before or at the cut-over point, the chain is recomputed in full (a documented shortcut).
2. Walk the item's cards in `(doc_date, id)` order. Cards at or before the cut-over point are skipped except for the bin values.
3. Per row: owner other than COMPANY -> outside the chain; transfer (TF) -> moves quantity x item average between bins, total and average unchanged; value row (VA) -> item value rises by `value_in` if the item has stock; receipt -> quantity and value rise by the received value, new average = value / quantity; out and adjustment -> value changes by quantity x current average, average unchanged; when the item quantity reaches zero the value is forced to zero and the rounding remainder goes to that row.
4. Numbers are rounded to the column precision (value 4 decimals, average 5) so a partial recalculation equals a full one.
5. Rows are saved only when something changed.

## Readers
`currentAvgMany` returns, per `item:part`, the average of the item's last COMPANY card, or the cut-over average if no card follows it; `readerAvg` falls back to the bin price when the scope is BIN. Used by Warehouse Stock, the Adjustment form and reports.

## Console tools
`inv:revalue-per-item --check` reports value now versus after the chain per item and fails if the TOTAL inventory value would change ("Do not switch INV_AVG_SCOPE to ITEM"); `--apply` writes the cut-over points (idempotent; items already cut over are skipped); `--class=`, `--item=` narrow it. The Valuation Check report (menu `INV_RPT_VALCHECK`, slug `inv-rpt-valuation-check`) shows per item quantity, average, item value, sum of bin values and the difference, which must be 0; with scope BIN it only lists bin totals.

## Edge cases and findings
- **Switch not made**: `INV_AVG_SCOPE` is still BIN in the mockup although cut-over rows exist, so today the average is per bin (an item split over bins can carry different prices). Whether tm2 (live) is also BIN is NOT checked. FACT (mockup query).
- A back-dated receipt can change the average of later OUT rows; the posting engine reports the value change and posts a revaluation journal for raw material only (cards `20_engine_stock-posting.md`, `26_engine_repost-reconcile.md`). Revaluation of SFG and FG outs is reported but not journalled (the revaluation call is made only for RM, `StockPostingService.php:183`). FROM CODE.
- When the item quantity is zero a later receipt starts from the last known average; when it is below zero the value logic is not defined (negative stock is refused except by adjustment). FROM CODE.
- The Warehouse reports show average and value without a price-right check (no check found in `app/Support/V2/Grid/Inv`, grep). Stock value is a purchase cost, not HPP of the product; whether it falls under the costing secrecy rule A.8 is NOT decided in the documents read.

## Tests (existing, not run now)
`AvgPerItemTest` (second receipt at a higher price raises the item average across two bins; issue uses the item average whichever bin is taken; transfer keeps item value and average; customer-owned stock is not valued; reversal of an issue keeps the chain; scope BIN keeps the old average; the revalue command is idempotent and keeps the total; back-dated receipt reprices a later issue and reports it; incremental chain equals the full one, also after cut-over), `AvgPerItemReadersTest` (current average follows the item; right after cut-over it is the cut-over average; transfer approval value uses the item average; Valuation Check shows zero difference per item), `LandedCostCardsTest::test_item_scope_writes_a_chained_value_row_and_the_average_rises`. Names only; not run now.

## Used by
Posting engine (through the chain), Stock Transfer, Landed Cost, reports, Warehouse Stock, Valuation Check, Obsolete Stock (value basis).
- Re-checked 2026-10-19 after Return from Production moved to the v2 form `inv-stock-doc-form` (rule D.25, Master T5c): `routes/web.php` replaced the v1 `/inventory/stock-return/{category}` route by a redirect on the same line (no line added or removed), `app/Support/Menu/MenuRoutes.php` lost the two Return entries, `app/Support/V2/Lookups.php` gained the `stock_return_bin` lookup (lines after 68 moved by two), `app/Services/Inventory/StockPostingService.php` gained the `addReturned` call on an existing line and one method at the end of the file, and `routes/v2_stockdocs.php`, `StockDocGrid.php` and the screen got the Return kind; this card does not depend on the changed lines and its content is still true.
- Re-checked 2026-10-19 after Stock Adjustment and Opname moved to the v2 form `inv-stock-adj-form` (rule D.25, Master T5d): `routes/web.php` replaced the v1 `/inventory/stock-adjustment/{category}/{doc?}` route by a redirect on the same line (no line added or removed), `app/Support/Menu/MenuRoutes.php` lost the three Adjustment entries, `app/Support/V2/Screens.php` got one item appended on an existing line, `app/Support/V2/Grid/Inv/StockDocGrid.php` and `app/Support/V2/Data/ApprovalCenter.php` changed in the inventory and approval parts, and `routes/v2_stockdocs.php` got a second route group at the end; this card does not depend on the changed lines and its content is still true.
- Re-checked 2026-10-19 after Purchase Return moved to the v2 form `pur-return-form` (rule D.25, Master T6): `routes/web.php` replaced the v1 `/purchasing/prt` and `/purchasing/prt/{prt}/edit` routes by redirects on the same lines and now also requires `routes/v2_purchase_return.php` on the line that requires `v2_finance.php`, `app/Support/Menu/MenuRoutes.php` lost `PROC_RET`, `app/Support/V2/Screens.php` got one item appended on an existing line, and `app/Support/V2/Data/ApprovalCenter.php` and `PurchaseReturnGrid.php` changed in the Purchase Return parts; this card does not depend on the changed lines and its content is still true.
