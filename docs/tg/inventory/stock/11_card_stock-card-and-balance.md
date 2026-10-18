# Concept card: Stock Card (ledger) and On-hand Balance

> Written 2026-10-16 (group Inventory/Stock). FACT = mockup DB (`ddtinv`) and code with line numbers, seen this session. Row counts are the mockup's own and change with every test or staging run.
> Verified: 2026-10-16

- **Document type**: concept card. **Module/sub-module**: Inventory > Stock; the card is read by every class menu (Stock Balance, Stock Card) and by Inventory > Warehouse > Report.
- **Canonical name**: Stock Card = the table of movement rows written ONLY by posting: `rm_stockcard` (RM, item key `material_id`), `sfg_stockcard` (SFG, `parts_id`), `fg_stockcard` (FG, `product_id` + `parts_id`). On-hand balance = the LAST card row of each position, exposed by the read-only views `v_rm_onhand`, `v_sfg_onhand`, `v_fg_onhand` (model `StockOnhand`). Movement report feed = view `v_inv_movement` (model `InvMovement`).
- **Synonyms**: kartu stok, ledger, stockcard, BB (saldo awal / opening), EB (saldo akhir / closing balance), on hand, `qty_eb`, position.
- **NOT the same as**: the item field `cur_stock` in the master (a denormalised copy, see Findings 1); `inv_stock_layer` (FIFO layers, exists, 0 rows, no code reads it for valuation); the Stock Document (card `10_card_stock-document.md`).

## Facts
| Item | Content | Evidence |
|---|---|---|
| Card columns | PK `doc_id` (the card's own id, not the document's), `previous_doc_id`, `doc_date`, item key, `doc_type`, `location_id`, `warehouse_id`, `lot_no`, `qty`, `uom_code`, `reference_detail_id` + `reference_name` (points to the document LINE, e.g. `tm2inv.rm_stock_d`), `qty_bb`, `qty_in`, `qty_out`, `qty_adj`, `qty_eb`, `value_in`, `value_eb`, `avg_unitprice`, `batch_id`, `owner_type`, `owner_id`, `period_id`. FG adds `c_sku_key`. | FACT, `show columns`; `StockPostingService.php:148-171` |
| Position | One chain per item x bin; FG per product x part x bin. Order inside a position = `(doc_date, doc_id)`. | FACT, `StockCardChain.php:36-45,72-79` |
| doc_type on cards | IN, OUT, BB, AD (adjustment/opname), TF (Stock Transfer, two rows), VA (landed cost value row, qty 0). Data also has 1 legacy `ADJ`. | FACT, `StockPostingService.php:129-135`; `StockCardChain.php:29`; data (RM: IN 63, TF 94, AD 26, OUT 22, BB 2, ADJ 1) |
| Quantity rule | `qty_eb` = previous `qty_eb` + `qty_in` - `qty_out` + `qty_adj`; `qty_bb` = previous `qty_eb`. A Stock Transfer is an Out in the old bin and an In in the new one. | FACT, `StockCardChain.php:82,108`; `StockLedger.php:149` |
| Value columns | Only `value_in` (receipts, value rows) and `value_eb` + `avg_unitprice` are maintained. `value_bb`, `value_out`, `value_adj` exist but are filled in 0 of 208 RM rows. `value_in` is empty for owner other than COMPANY and for old rows (22 of 63 IN rows have it). | FACT, query; `StockPostingService.php:137-140,164` |
| On-hand views | `v_rm_onhand` picks, per `material_id` + `location_id`, the card with the highest `doc_date`+`doc_id` string stamp; adds bin and warehouse names (joined to `wh_loc`, `wh_warehouse`), `is_usable`, `qty_onhand` = `qty_eb`, `value_onhand` = `value_eb`, `last_movement`, and `last_item_movement` = latest card date of the item excluding doc types TF and VA. | FACT, `information_schema.views` (definition read) |
| Item-level balance | The Stock Card report shows BB/EB per ITEM across all bins (chained: BB of a row = EB of the row before it); the bin balance is an optional hidden column "Location Balance". | FACT, `ItemBalanceChain.php:10-14`; `StockCardReport.php:12-16`; rule A.26 (`docs/globaluserrules.md`, 2026-10-08 chain decision) |
| Period ledger | `StockLedger::between(from,to)` gives opening, movements and closing per position and item for one period (qty and value), used by Stock Card (all classes), Stock Recapitulation, Stock Summary and the daily reconciliation. Transfers change the position but are not counted as in/out of the item. | FACT, `StockLedger.php:11-19,100-161` |
| FG availability | `FgAvailability::byProduct` = usable on-hand (`v_fg_onhand` where `is_usable`=1) minus open FG booking allocations; the single source for Available FG. | FACT, `FgAvailability.php:9-34` |
| Data (mockup) | RM 208 cards, SFG 2, FG 6. On-hand positions: RM 136 (105 > 0), SFG 1, FG 5, none negative. `inv_item_avg_open` 62 rows (cut-over points, see the Average card). | FACT, queries |

## Relations
- **source**: posting engine (card `20_engine_stock-posting.md`) for IN/OUT/BB/AD, `StockTransferService` for TF (writes its own two rows, Warehouse side), `LandedCostCards` for VA (card `25_engine_landed-cost.md`).
- **used_by**: all stock reports (card `18_card_stock-reports.md`), Warehouse Map and bin summaries (`WarehouseStock`), Obsolete Stock, Valuation Check, daily reconciliation, Material Planning (`v_rm_available`), Pick List.
- **flows**: document line -> card row -> chain recalculation (card `21_engine_stock-card-chain.md`) -> view -> reports.
- **frozen_zone**: Stock Card report format is "already correct, unchanged" (Jo, rule A.26); only the connected BB/EB formula was approved 2026-10-08.

## Findings (recorded, not fixed)
1. **Master `cur_stock` drifts from the card**: for 81 RM items that have cards, 46 have `mat_raw.cur_stock` different from the sum of `v_rm_onhand` (45 of them NULL; item 1 shows 1809 against 1479 on the card). The Raw Material grid and form show `cur_stock` as "Stok" and `MaterialPlanningEngine` orders by it (`MaterialPlanningEngine.php:655`). Posting adds the net delta to it (`StockPostingService.php:218-221`) but Stock Transfer and the staged dummy do not. FACT (query), cause on the staging side NOT confirmed.
2. **One broken chain**: card 35 (`rm_stockcard`, item 1, bin 1, adjustment 09-Jul-2026) has `qty_bb` 1090 while the previous card's `qty_eb` is 1100. One of 68 rows with a `previous_doc_id`; 99 of 208 RM rows have none. FACT (query).
3. **One historic negative row**: card 28 (RM item 1, Out 60 on 24-Apr-2026) has `qty_eb` -60 and no `avg_unitprice`; current balance of that position is not negative. The posting rule forbids new negatives, so this row predates the rule. FACT.
4. **29 RM cards without value**: `value_eb` and `avg_unitprice` are NULL in 29 of 208 RM rows (FG 2 of 6, SFG 1 of 2); the period reports then show value 0 for those movements. FACT (query).
5. **Unused value columns**: `value_bb`, `value_out`, `value_adj` are never written; reports derive movement value as the difference of `value_eb`. FACT (query; grep of `StockPostingService`).
6. **Views embed the schema name**: `v_rm_onhand` is defined with literal `ddtinv`/`ddtmst` names in the mockup; the live view must reference `tm2inv`/`tm2mst`. Anything that copies a view between sites must rebuild it. FACT (view definition); handling NOT checked.
7. **Dead-stock date rule**: `last_item_movement` excludes TF and VA, so moving stock or adding landed cost does not make an item "alive". Consistent with rule A.22 ("memindahkan stok tidak dihitung sebagai pergerakan"). FACT.

## Already exists? Searched where
Searched: `*_stockcard`, `v_*_onhand`, `v_inv_movement`, `inv_stock_layer`, `StockLedger`, `ItemBalanceChain`, `FgAvailability`, design note `03_invwh-tech-stock-model.md` (FROM DOCUMENT). The concept ALREADY EXISTS. FIFO layers are a table without code (FROM DOCUMENT: FIFO postponed, LIFO not allowed).
- Re-checked 2026-10-19 after Return from Production moved to the v2 form `inv-stock-doc-form` (rule D.25, Master T5c): `routes/web.php` replaced the v1 `/inventory/stock-return/{category}` route by a redirect on the same line (no line added or removed), `app/Support/Menu/MenuRoutes.php` lost the two Return entries, `app/Support/V2/Lookups.php` gained the `stock_return_bin` lookup (lines after 68 moved by two), `app/Services/Inventory/StockPostingService.php` gained the `addReturned` call on an existing line and one method at the end of the file, and `routes/v2_stockdocs.php`, `StockDocGrid.php` and the screen got the Return kind; this card does not depend on the changed lines and its content is still true.
