# ENGINE card: Stock Period Ledger and read helpers (StockLedger, StockItemMeta, WarehouseStock, FgAvailability)

> Written 2026-10-16 (group Inventory/Stock). Source read in full: `app/Services/Inventory/StockLedger.php` (195 lines), `StockItemMeta.php` (38), `FgAvailability.php` (40), `ItemBalanceChain.php` (48, see card 21), and `WarehouseStock.php` (382) as far as the stock-reading part; the bin map side (capacity, stack, label) belongs to the Warehouse cards, not here. Tables: `11_card_stock-card-and-balance.md`. No test was run (no DB writes allowed in this task).
> Verified: 2026-10-16

- **Canonical name**: Stock Period Ledger. **Classes**: `App\Services\Inventory\StockLedger` (static `between`, `forget`, `kind`, `itemKey`; instance `movements`, `positions`, `items`), `App\Services\Inventory\StockItemMeta` (static `load`, `fallback`), `App\Services\Inventory\FgAvailability` (static `byProduct`, `available`), `App\Services\Inventory\WarehouseStock` (static readers). **Registry**: key `stock-ledger` (StockLedger) and key `warehouse-stock` (WarehouseStock); StockItemMeta and FgAvailability are not registered. **Module/sub-module**: Inventory > Stock reports.
- **Role**: read-only. None of these classes writes anything; the writers are the posting engine and Stock Transfer.

## Trigger (no background / schedule)
Called at display or print time by report grids; `ReconcileService::run` also calls `StockLedger` from the daily command. No schedule of its own.

## StockLedger
- **Input**: first and last date of a period (`between`; one instance per period per request, `forget` clears the cache). **Output**: three views of the same data: `positions()` (item x bin, FG also by part: opening, in, out, adjustment, transfer, closing, in quantity and value), `items()` (positions of one item added up) and `movements()` (every card row of the period with value and the running item balance).
- **Flow (`build`, 100-161)**: opening of each position = its last card row before the first day (`opening`, 169-194, empty positions dropped); then the period's rows from `v_inv_movement` in `(doc_date, doc_id)` order. Kind of movement per row (`kind`, 56-66): TF -> Transfer in / out; VA -> Value adjustment; `qty_adj` <> 0 -> Adjustment; `qty_in` > 0 -> In; `qty_out` > 0 -> Out; else Opening. Value of a movement = value of the position after the row minus its value before (`value_eb`, or quantity x average when `value_eb` is empty), so opening + in - out + adjustment (+ transfer) = closing always holds. Transfers change the position and are counted separately, not as item in/out.
- **Edge cases**: the value of a movement is a DIFFERENCE of stored balances, so a card row with an empty `value_eb` (29 RM rows in the mockup) shows value from quantity x average, or 0 when the average is empty too (FROM CODE; data in card 11). Revaluation of later out rows moves value between In and Out lines of a period without any visible document (FROM CODE).

## StockItemMeta
`load(class, ids)` reads name, group and minimum stock in one query per class from `mat_raw` (RM, group = `category1`), `prod_product` (FG, group = category name) or `prod_part` (SFG, group "Semi Finished Goods"), ignoring the data-origin filter so stock of any origin shows. `fallback` returns "(item not found in master)" and "No category".

## FgAvailability
`byProduct`: `onhand` = sum of `v_fg_onhand.qty_onhand` where `is_usable` = 1; `booked` = sum of `rm_booking_alloc.qty` with `source_type` FG on bookings that are Confirmed, not cancelled and not closed; `available` = onhand - booked. Used by `MaterialPlanningEngine` for FG netting. Note the table name: FG booking rows live in `rm_booking_alloc` / `rm_booking_d` (the RM booking tables). FACT (`FgAvailability.php:22-26`).

## WarehouseStock (reading side only)
`positions()` lists every item x bin with a positive balance from the on-hand views, adds the average (item average when scope ITEM), last item movement, idle days, "dead" (idle longer than `ASSISTANT_DEADSTOCK_MONTHS` months, or never moved) and the arrival date (last IN or BB card of the bin, else of the item). The same dead-stock definition feeds the assistant and the Deadstock widget. Weight, volume, bin geometry, stacks and bin status are the Warehouse cards' subject. Dead-stock definition: transfers do not count as movement (rule A.22).

## Edge cases and findings
- **Two dead-stock definitions**: Warehouse uses `ASSISTANT_DEADSTOCK_MONTHS` (6 months in the mockup), Obsolete Stock uses `INV_AGING_SLOW/NONMOVING/DEAD_DAYS` (90/180/365 days). Both exclude transfers; they can disagree on whether an item is "dead" (a 7-month-idle item is Dead in the map and Non-moving in the provision). FACT (constants, code).
- **`positionKey` for FG** includes the part in the ledger (`StockLedger.php:163-166`), but the Stock Balance report's monthly movement map is keyed by item and bin without the part (`StockBalanceReport.php:53-60`), so FG rows of two parts of one product in one bin would share one month figure. FROM CODE; no such case in the data (5 FG positions).
- Report lines are built in PHP from whole-period queries; no pagination of the ledger. Fine for the mockup; large live periods NOT measured.

## Tests (existing, not run now)
`StockReportsTest` (period ledger ties opening, movements and closing; Stock Card chains BB to EB across locations and the total ties; recapitulation and summary agree; opname and loss/gain; inspection; period from the query string; reachable from the sidebar). `BoardStockDocumentsTest` (dashboard counts). Not found: a direct test of `FgAvailability` or `StockItemMeta` (grep names).

## Used by
Stock Card, Stock Recapitulation, Stock Summary (all classes), Stock Balance (per class), Daily Reconciliation, Warehouse Map and Stock Transfer screens, Material Planning (FG available).
