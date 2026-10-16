# Concept card: Inventory Period (month close for stock)

> Written 2026-10-16 (group Inventory/Stock). FACT = mockup DB (`ddtinv`) and code with line numbers, seen this session. Tests were read by name only; none was run (they would write to the DB).
> Verified: 2026-10-16

- **Document type**: concept card. **Module/sub-module**: Inventory > Stock > Valuation & Period > "Valuation & Period" (menu `INV_VAL_PERIOD`, `v2:inv-valuation`; the screen has the cards Value by Class, Inventory Period, Close Landed Cost Variance).
- **Canonical name**: Inventory Period = one row of `inv_period` per month, code `YYYYMM` in `i_period_code`, status OPEN or CLOSED (a value CLOSING is declared in the model comment but nothing sets it). Model `App\Models\Inventory\InvPeriod`; service `App\Services\Inventory\Valuation\InvPeriodService`.
- **Synonyms**: periode inventory, tutup bulan stok, period close, closed through, `closedThrough`.
- **NOT the same as**: the general-ledger period (`fin_period`, Finance; it needs the inventory month CLOSED first); the landed cost variance close (monthly transfer to cost of goods sold, card `15_card_landed-cost.md`).

## Facts
| Item | Content | Evidence |
|---|---|---|
| Table | `inv_period`: `period_id`, `i_period_code`, `i_date_start`, `i_date_end`, `i_status`, `c_closed_by`, `c_closed_at`, `i_notes`, `created_by`, `updated_by`. | FACT, `InvPeriod.php`; query |
| Effect of CLOSED | `InvPeriod::closedThrough()` = the end of day of the latest `i_date_end` among CLOSED periods. `StockPostingService` refuses any document dated on or before it (also reversals and adjustments); the correction must be dated in an open period. `LandedCostService` refuses a landed cost dated on or before it. | FACT, `InvPeriod.php:29-34`; `StockPostingService.php:99-103`; `LandedCostService.php:259-263` |
| Close | Allowed when the period exists, is not already CLOSED, and every earlier existing period is CLOSED ("Close the earlier periods first."). Writes status, `c_closed_by`, `c_closed_at`. | FACT, `InvPeriodService.php:20-32` |
| Open again | Only a CLOSED period ("Only a closed period can be opened again."), only the latest closed one ("Open the later closed periods first."), and only if the general-ledger period of that month is not CLOSED ("The general ledger period of that month is closed; it cannot be opened for inventory."). | FACT, `InvPeriodService.php:34-49` |
| Add next | "Add Next Period" creates the month after the last row (or the current month when the table is empty), status OPEN, dates first to last day. Periods are never created by posting or by a schedule. | FACT, `InvPeriodService.php:51-58`; `v2_valuation.php` route `v2.val.period` |
| GL gate | Closing a general-ledger period requires the inventory period of the same year-month to be CLOSED, and gives "DOES NOT EXIST" when that month has no row. | FACT, `FinPeriodGuard.php:45-52` |
| Screen | `inv-valuation` shows a table of the last 24 periods (period, dates, status, closed by) and a selector with the buttons Add Next Period, Open, Close; both Open and Close ask for confirmation. Errors come back as a red alert. | FACT, `resources/views/v2/screens/inv-valuation.blade.php`; `ValuationDesk::periodRows` |
| Route rights | The routes sit in the group with middleware `module:INV` only; no separate right for Close or Open was found. | FACT, `routes/v2_valuation.php:16` |
| Data (mockup) | 1 period: 202608 (01-Aug-2026 to 31-Aug-2026), OPEN, created by system. Stock cards exist up to October 2026. | FACT, query |

## Relations
- **source**: calendar months; GL period (`fin_period`) for the re-open rule.
- **used_by**: posting engine, reversal, landed cost, Finance period close, Stock Adjustment as the escape route.
- **flows**: user Close -> documents dated inside are refused -> corrections as Adjustment in an open month -> Finance may close the GL month.
- **frozen_zone**: none.

## Findings (recorded, not fixed)
1. **No pre-close checks**: closing a period does not look for Draft or Submitted stock documents dated inside it, for open Pick Lists, for a pending reconciliation difference, or for an unposted landed-cost variance. A Draft dated in a closed month simply fails when someone later posts it. The GL close has a blocker list; the inventory close has none. FROM CODE (`InvPeriodService::close` read in full).
2. **Only one period exists** in the mockup (August); documents of September and October are posted with no period row. The GL gate would block a September GL close with "DOES NOT EXIST" until someone adds the months one by one. FACT (query, code).
3. **`CLOSING` is a status without a path**: declared in the model comment and treated as "not closed" by Finance, but no code sets it. FACT (grep `CLOSING`).
4. **`period_id` on documents and cards is never filled**, so a period cannot be reconstructed from the cards by id, only by date (card `10_card_stock-document.md` Findings 6). FACT.
5. **Close/Open rights**: any user with the Inventory module can close or re-open a period. FROM CODE; the Setup security tables were NOT read.
6. **Test periods are fake months**: `InvPeriodServiceTest` creates periods 190001, 190002 and 209901 and removes them by `created_by`; a crash in the middle would leave a 2099 period and "Add Next Period" would continue from it. FROM CODE (test read).

## Tests (existing, not run now)
`InvPeriodServiceTest` (oldest period closes and opens again; a period cannot close while an earlier one is open; only a closed period can be opened; create-next adds the month after the last), `StockPostingOrderTest::test_a_document_dated_in_a_closed_inventory_period_is_refused`, `StockReversalServiceTest` (reversal in a closed period refused), `LandedCostScreenTest::test_period_actions_report_a_refusal`.

## Already exists? Searched where
Searched: `inv_period`, `InvPeriod`, `InvPeriodService`, `closedThrough`, `FinPeriodGuard`, design notes (FROM DOCUMENT: "periods already closed refuse new posting", `03_invwh-tech-stock-model.md`). The concept ALREADY EXISTS.
