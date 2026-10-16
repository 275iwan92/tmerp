# ENGINE card: Stock Card Chain (StockCardChain + ItemBalanceChain)

> Written 2026-10-16 (group Inventory/Stock). Source read in full: `app/Services/Inventory/StockCardChain.php` (166 lines), `app/Services/Inventory/ItemBalanceChain.php` (48). Tables: card `11_card_stock-card-and-balance.md`. No test was run (no DB writes allowed in this task).
> Verified: 2026-10-16

- **Canonical name**: Stock Card Chain. **Classes**: `App\Services\Inventory\StockCardChain` (static `recalculate`, `position`, constant `VALUE_ADJUSTMENT` = VA) and `App\Services\Inventory\ItemBalanceChain` (static `forNewest`, a READ-side helper for the report). **Registry**: NOT registered as its own key (searched `EngineRegistry`: it is described only inside the `stock-posting` steps). **Module/sub-module**: Inventory > Stock.
- **Role**: keeps every balance column of a position consistent when a row is inserted by date. It decides nothing; it reports the first row that became negative and the posting engine decides to refuse.
- **Called only from** (grep `StockCardChain::`): `StockPostingService.php:172` and `LandedCostCards.php:48` (value rows). `StockTransferService` does NOT call it: it writes its two TF card rows itself, always as the LAST row of both bins (date = now or later), computes their balances and average on the spot, and, when the item scope is ITEM, calls `ItemAverageCost::recalculate` (`StockTransferService.php:360-404`). Screens never call the chain.

## Trigger (no background / schedule)
Called inside the `tm2inv` transaction of a writer. `ItemBalanceChain::forNewest` is called by the Stock Card report at display time.

## Input / Output
- **Input**: stock class, the card row just inserted (or the first row to recalculate), `$fresh` = true when that row is new.
- **Output (writes)**: for every row of the position from that row on (rows in `(doc_date, doc_id)` order): `previous_doc_id`, `qty_bb`, `qty_eb`, `avg_unitprice`, `value_eb`. Only rows whose numbers changed are saved. For a Stock Transfer out row whose price changed, the paired In row's `value_in` is rewritten.
- **Returns**: `rows` (rows written), `negative` (first row that turned negative: `doc_id`, `doc_date`, `qty`), revalued_rows, revalued_value, revalued_docs (card id => value change of later OUT rows whose average price changed).

## Flow
1. If the setting `INV_AVG_SCOPE` is ITEM (`ItemAverageCost::enabled`), take a snapshot of the item's old prices, run the bin chain for quantity, then let `ItemAverageCost::recalculate` overwrite value and average per item (lines 53-66, card `23_engine_item-average-cost.md`). Otherwise only the bin chain runs.
2. Bin chain (`recalculatePosition`, 69-155): find the row just before the new one (`lockForUpdate`) and all rows from the new one on; start with `qty_eb` and `avg_unitprice` of the previous row (0 and none when there is no previous row).
3. For each row in order: receipt (`qty_in` > 0): price = `value_in` / `qty_in` when set, otherwise rebuilt from the old stored numbers; new average = (balance x average + in x price) / (balance + in); value-adjustment row (doc_type VA): value rises by `value_in` while quantity stays, average recomputed; other rows keep the average. `qty_eb` = balance + in - out + adj; `value_eb` = `qty_eb` x average.
4. The first row whose new `qty_eb` is below zero (tolerance 0.00005) AND is new or got worse than before is reported as `negative`; rows that were already negative and did not get worse are not reported.
5. An OUT row whose average price changed counts as "revalued" (value change = out quantity x price difference); a TF out row instead queues a cascade.
6. Cascade: for each TF out row with a changed price, the paired In row (same `reference_detail_id`, reference name ending IN) gets a new `value_in` and the destination position is recalculated the same way (recursion; always moves forward in time, so it ends).
7. `ItemBalanceChain::forNewest` (report side): per item, BB of a shown row = item total over all bins before it; EB = BB + in - out + adj; computed from the net of the whole history minus the rows shown (rounded to 4 decimals), so a report that shows only the newest rows still chains correctly.

## Formula (as-is, no amounts)
New average after a receipt = (balance before x old average + quantity in x price in) / (balance before + quantity in). Value after a row = balance after x average after. Adjustment and out rows do not change the average.

## Edge cases and findings
- The negative check protects only against rows that got worse by this recalculation; an old negative row stays unreported (data has one such row, card `11_card_stock-card-and-balance.md` Findings 3).
- `previous_doc_id` is written only when numbers change or the pointer differs; 99 of 208 RM rows in the mockup have none (Findings 2 in the same card). One RM row has `qty_bb` different from its predecessor's `qty_eb` (card id 35), so a historic recalculation did not reach it. FACT (query).
- Rows with `value_in` NULL (old rows) are priced from their stored numbers, which can hide an earlier error: the chain trusts what it finds. FROM CODE.
- Performance: each posting rewrites every later row of the position (and of the transfer destination). Fine for the mockup (208 rows); no measurement for a year of live data. FROM CODE.
- The pairing of transfer rows is by `reference_detail_id` and the reference name text; if the name pattern changes the cascade silently finds no pair. FROM CODE.

## Tests (existing, not run now)
`StockPostingOrderTest` (running balance and average in date order; back-dated receipt reprices stock already transferred), `BatchStockCardChainTest`, `LandedCostCardsTest` (value rows keep the chain), `StockReportsTest::test_stock_card_chains_bb_to_eb_across_locations_and_the_total_row_ties` (report side).

## Used by
Posting engine, landed cost value rows, Stock Card reports (item balance via `ItemBalanceChain`). Stock Transfer posting is a separate writer (Warehouse cards).
