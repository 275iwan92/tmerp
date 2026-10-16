# Concept card: Stock reports overview (Balance, Card, Slow Moving, Recapitulation, Summary, Opname, Loss & Gain, Inspection, Valuation Check)

> Written 2026-10-16 (group Inventory/Stock). FACT = mockup DB and code with line numbers, seen this session. Report grids were read (header, query, columns), not opened in a browser; no test was run.
> Verified: 2026-10-16

- **Document type**: concept card (reports; they own no table). **Module/sub-module**: Inventory > Stock. Two menus: (a) per class, under each class's "Report" group (`INV_TRX_RM_RPT`, `INV_TRX_FG_RPT`, `INV_TRX_SFG_RPT`): Stock Balance, Stock Card, Slow Moving Item; (b) all classes together, group "Reports" (`INV_WH_RPT`): Stock Card, Stock Recapitulation, Stock Summary, Stock Opname, Stock Loss & Gain, Stock in Inspection, Valuation Check. The Valuation & Period report group (`INV_VAL_RPT`) adds GR/IR Aging, Daily Reconciliation, Repost, Stock Aging (cards `19`, `17`, `16`). (FACT, `sys_app_menu`.) Stock by Location, Stack Monitor and the other warehouse reports belong to the Warehouse cards.
- **Canonical name**: Stock Reports = grid classes in `app/Support/V2/Grid/Inv`, based on `StockReportGrid` (per class) or `AllStockReport` (all classes), reading the on-hand views, `v_inv_movement` and `StockLedger`.
- **Synonyms**: laporan stok, kartu stok, saldo stok, rekap, ringkasan, opname, selisih lebih kurang, barang inspeksi.
- **NOT the same as**: the Stock Document lists (Stock In/Out/... grids); Warehouse Map; the Executive dashboard widgets.

## Facts
| Report | Slug | What it shows and from where | Evidence |
|---|---|---|---|
| Stock Balance (RM, FG, SFG) | `inv-rm-rpt-balance`, `inv-fg-rpt-balance`, `inv-sfg-rpt-balance` | On hand now per item x bin from `v_*_onhand`, plus Opening/In/Out/Adjustment of the CURRENT month from `v_inv_movement`, value, last movement, idle days and a Stock badge: OK, Below minimum (usable stock of the item below its minimum stock), Empty, Not usable (bin blocked). | FACT, `StockBalanceReport.php` |
| Stock Card (per class) | `inv-rm-rpt-card` etc. | Every movement, newest first: BB, In, Out, Adj, EB with BB/EB per ITEM across bins (chained), movement badge (In, Out, Adjustment, Opening, Transfer in/out, Value adjustment); location balances are optional hidden columns. | FACT, `StockCardReport.php`; `ItemBalanceChain.php` |
| Slow Moving Item (per class) | `inv-rm-rpt-slow` etc. | Items with usable stock whose latest movement (any bin, transfers excluded) is 3 months ago or older; idle badge 3-6, 6-9, 9-12, over 12 months, never moved. | FACT, `SlowMovingReport.php` |
| Stock Card (all classes) | `inv-rpt-card` | Period report: one head per item (BB and EB of the item for the period) with rows Date, Location, BB, In, Out, Adj, EB; no price or value; cancelled documents and reversals labelled; item filter; print = the card of the item. | FACT, `StockCardAllReport.php:12-20,142-160` |
| Stock Recapitulation | `inv-rpt-recap` | One row per item for the period: opening, in, out, adjustment, closing (quantity) and, in optional columns, average price and the values; Turnover = out value / average of opening and closing value; badges With/No movement, In stock/Empty/Negative. Transfers not counted. | FACT, `StockRecapReport.php` |
| Stock Summary | `inv-rpt-summary` | One row per class x item category: item count, opening/in/out/adjustment/closing VALUE, change and share of total (quantities are not added up). Print has signature boxes (Prepared by Warehouse, Checked by Accounting, Approved by Head of Warehouse). | FACT, `StockSummaryReport.php` |
| Stock Opname | `inv-rpt-opname` | Adjustment and Physical Count documents of the period (all statuses), one row per item: system qty, counted qty, difference (Gain, Loss, Match) and value; only Posted ones changed the stock. | FACT, `StockOpnameReport.php` |
| Stock Loss & Gain | `inv-rpt-loss-gain` | Per item per month, from posted adjustment card rows (`qty_adj` not 0, not transfers): Gain = added, Loss = taken off, valued at the average price of that moment. | FACT, `StockLossGainReport.php:8-50` |
| Stock in Inspection | `inv-rpt-inspection` | Stock that exists but cannot be used, now: receipt lines waiting for QC, quantity rejected and not yet returned, stock in QC Hold areas, stock in blocked bins; days since receipt or last movement. | FACT, `StockInspectionReport.php:14-20` |
| Valuation Check | `inv-rpt-valuation-check` | Per item: company quantity, item average, item value by the Average chain, sum of bin values, difference (must be 0). | FACT, `ValuationCheckReport.php:11-14`; card `23` |
| Period input | all period reports | Period from the query string (`from`, `to`); default = start of the current month to today; reports open and print from the sidebar. | FACT, `AllStockReport.php`; test `StockReportsTest::test_period_comes_from_the_query_string_and_reports_open_and_print` |

## Relations
- **source**: stock cards and views (card `11`), item master (`StockItemMeta`), documents (Opname), Procurement receipts (Inspection), Warehouse zones and bins.
- **used_by**: Warehouse, Accounting (Summary, Loss & Gain), Finance month end, Direksi.
- **flows**: read only; they write nothing and post nothing.
- **frozen_zone**: Stock Card and Stock Summary are "already correct" (Jo, rule A.26); format changes need a real-screenshot preview first.

## Findings (recorded, not fixed)
1. **Value columns have no price-right check**: Stock Balance (Stock Value), Recapitulation, Summary and Valuation Check show value to anyone who may open the Inventory module; only the all-class Stock Card is price-free by design. FROM CODE (no gate found in `app/Support/V2/Grid/Inv` or `DbGrid`, grep); whether stock value counts as costing-secret (rule A.8) is not decided in the documents read.
2. **Different periods in similar reports**: Stock Balance shows the current month only, Stock Card (per class) shows all history, the all-class reports take a chosen period; the same word "Opening" means different things. FACT.
3. **Stock Balance key without part**: the monthly movement map is keyed by item and bin, not by FG part (card `24`, findings). FROM CODE.
4. **Value from stored balances**: movement values are differences of `value_eb`; rows with empty value (29 RM cards in the mockup) show 0 value (card `24`). FACT.
5. **Loss & Gain includes legacy types**: it selects card `doc_type` AD and ADJ (the legacy spelling) or the opname categories, so legacy adjustments are counted too. FACT (`StockLossGainReport.php:48-50`).
6. **Two Slow definitions** (3 months here, 90 days in Aging). Card `16`. FACT.

## Tests (existing, not run now)
`StockReportsTest` (period ledger ties; Stock Card chains BB to EB and total ties; screen and print show only card columns; recapitulation and summary agree; opname lists every count and loss/gain only posted ones; inspection lists blocked bins and QC waiting lines; period from the query string; reachable from the sidebar). Not found: tests naming Slow Moving or Stock Balance (grep names).

## Already exists? Searched where
Searched: menu groups above, `app/Support/V2/Grid/Inv/*Report.php`, `docs/globaluserrules.md` A.23 (report set decided 2026-10-02, FROM DOCUMENT). The concept ALREADY EXISTS.
