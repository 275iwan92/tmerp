# Concept card: Obsolete Stock (provision) and Stock Aging

> Written 2026-10-16 (group Inventory/Stock). FACT = mockup DB (`ddtinv`, `ddtsys`) and code with line numbers, seen this session. Both provision tables are empty in the mockup. No test was run.
> Verified: 2026-10-16

- **Document type**: concept card. **Module/sub-module**: Inventory > Stock > Valuation & Period > "Obsolete Stock" (`INV_VAL_OBS`, `v2:obsolete-stock`) and the report "Stock Aging" (`INV_VAL_AGING`, `v2:inv-stock-aging`, group "Report").
- **Canonical name**: Obsolete Stock provision = a posting of `inv_obsolete_provision` (header) + `inv_obsolete_provision_line` (photo of every item x bin at that moment), made by `ObsolescenceService::post`. Aging class = Active, Slow, Non-moving or Dead by days without movement.
- **Synonyms**: provisi stok usang, penyisihan persediaan usang, dead stock, slow moving, non-moving, aging.
- **NOT the same as**: the per-class report "Slow Moving Item" (3/6/9/12 months, usable bins only, card `18_card_stock-reports.md`); the dead-stock flag of the Warehouse Map (`ASSISTANT_DEADSTOCK_MONTHS`, card `24_engine_stock-ledger.md`).

## Facts
| Item | Content | Evidence |
|---|---|---|
| Classification | Idle days since the last movement of the ITEM (any bin, transfers and value rows excluded; the date comes from the view column `last_item_movement`). Never moved or idle at or above the Dead limit = Dead; at or above Non-moving = Non-moving; at or above Slow = Slow; else Active. Limits: `INV_AGING_SLOW_DAYS`, `INV_AGING_NONMOVING_DAYS`, `INV_AGING_DEAD_DAYS` in `sys_constant`. | FACT, `ObsolescenceService.php:37-49`; view definition |
| Rows | One row per item x bin with quantity > 0 and value > 0, all three classes; provision = stock value x the percentage of the class (`INV_OBSOLETE_PCT_SLOW`, `_NONMOVING`, `_DEAD`; Active 0). Stock of customer or supplier owners has no value and is left out. | FACT, `ObsolescenceService.php:57-84` |
| Posting | Only on the button "Post provision". Target = total provision of all rows now; previous = `provision_total` of the latest posting; the journal covers the DIFFERENCE: up = debit expense account / credit provision account; down = the opposite. If the difference is below 0.005: "The provision is already up to date; there is nothing to post." The posting stores header + all lines and the voucher id. Journal GEN, source OBS_PROVISION, dated today. | FACT, `ObsolescenceService.php:107-139` |
| Accounts | Constants `INV_ACC_OBS_EXPENSE` and `INV_ACC_OBS_PROVISION` (account codes, set in the mockup); a missing account gives "The account for :key is not set or not found (Settings > Constants)." | FACT, `ValuationAccounts.php:17-26`; constants query |
| Screens | Obsolete Stock: info banner ("Nothing is posted until you click Post provision"), summary table per class (Item x Bin, Stock Value, Provision), buttons Stock Aging and Post provision, then Provision History. Stock Aging: per item x bin with Aging badge, Provision %, Provision; filters Aging/Class/Location; "To date" selector. | FACT, `obsolete-stock.blade.php`; `StockAgingReport.php` |
| Data (mockup) | 0 provisions, 0 lines. | FACT, query |

## Relations
- **source**: the on-hand views (`v_rm_onhand`, `v_sfg_onhand`, `v_fg_onhand`), item master names, settings, GL accounts.
- **used_by**: Finance (voucher), Valuation & Period screen links, executive reports.
- **flows**: report (read only) -> user reviews -> Post provision -> voucher + history; next month the user posts again and only the change is journalled.
- **frozen_zone**: none. Decision by Jo 2026-10-08: provision journal only on user action (rule A.26, FROM DOCUMENT).

## Findings (recorded, not fixed)
1. **"To date" only moves the idle-day calculation**: stock and value are always the current on-hand (the views have no history); an as-of date in the past mixes old idle days with today's stock. FROM CODE (`rows($asOf)`).
2. **Provision is a running total of the LATEST posting**: it ignores postings dated after the one being made, so posting for an earlier date after a later one gives a wrong difference. FROM CODE; not reproduced.
3. **Stock Aging has no cancel**: a wrong posting is corrected by the next posting (the difference), not by reversal. FROM CODE.
4. **FG and SFG are included** but a position enters only when its on-hand value is above 0; cards without value (FG 2 of 6, SFG 1 of 2 in the mockup) therefore drop out. FROM CODE and card data.
5. **Two idle definitions** (days here, months in Warehouse and Slow Moving). See card `24_engine_stock-ledger.md`.
6. **Rights**: route `v2.val.obsolete.post` uses only `module:INV`. FROM CODE.

## Tests (existing, not run now)
`ObsolescenceTest` (classes follow the day limits and never-moved is Dead; every row carries class, percentage and provision; rows alone never post a journal; post journals the difference to the previous provision only; a lower provision reverses part of the previous one).

## Already exists? Searched where
Searched: `inv_obsolete_provision*`, `ObsolescenceService`, `StockAgingReport`, `WarehouseStock`, `SlowMovingReport`. The concept ALREADY EXISTS.

<!-- johiwan-only -->
## Settings values (mockup, Settings > Constants)
Idle limits 90 / 180 / 365 days; provision 10 / 25 / 50 percent for Slow / Non-moving / Dead; expense account 620000, provision account 113900.
