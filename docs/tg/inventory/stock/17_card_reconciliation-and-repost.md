# Concept card: Daily Reconciliation and Repost (control of the inventory value)

> Written 2026-10-16 (group Inventory/Stock). FACT = mockup DB and code with line numbers, seen this session. The command `inv:reconcile --no-save` was run once against the mockup (it writes nothing); its result is in the johiwan-only part. Tests read by name only.
> Verified: 2026-10-16

- **Document type**: concept card. **Module/sub-module**: Inventory > Stock > Valuation & Period > Report > "Daily Reconciliation" (`INV_VAL_RECON`, `v2:inv-reconcile`) and "Repost" (`INV_VAL_REPOST`, `v2:inv-repost`).
- **Canonical name**: Reconciliation = row of `inv_reconcile_log` per day and class (RM, SFG, FG): general-ledger value of the inventory accounts compared with the stock card value. Repost = row of `inv_repost_log` per correction, reversal or revaluation journal.
- **Synonyms**: rekonsiliasi persediaan, GL vs stok, jurnal koreksi bertumpuk, stacked correction, revaluation.
- **NOT the same as**: Stock Opname (physical count); Valuation Check (item chain versus bin values, card `23_engine_item-average-cost.md`); the GL period close.

## Facts
| Item | Content | Evidence |
|---|---|---|
| Reconcile rule | Per class: GL value = debit minus credit of the accounts listed in `INV_RECON_ACCTS_RM` / `_SFG` / `_FG` over POSTED vouchers with posting date up to the day; card value = closing value of all positions that day from `StockLedger`; pending = landed cost Confirmed but not yet in the cards (RM only); difference = GL - (cards + pending); status DIFF when the absolute difference is above `INV_RECON_TOLERANCE`, else OK. | FACT, `ReconcileService.php:27-72` |
| Saving | Result saved with one row per date and class (`updateOrCreate`), unless `--no-save`. | FACT, `ReconcileService.php:46-48` |
| Schedule | `inv:reconcile` runs daily at 04:00 without overlap when the table exists. | FACT, `routes/console.php:51` |
| Screen | Report with period selector; row tone red on DIFF; the Valuation & Period screen shows the last result per class. | FACT, `ReconcileReport.php`; `ValuationDesk::classRows` |
| Repost rule | Posted vouchers are never edited. `correct` posts a new voucher with only the per-account difference between the intended and the original lines, kind CORRECTION; `reverse` posts the reversing voucher; `revalueIssued` posts Dr/Cr cost of goods sold vs inventory for the value change of later issues. Each is logged in `inv_repost_log` with before, after and delta. | FACT, `RepostService.php:27-96` |
| Repost callers | Landed cost Revise and Cancel, and the posting engine / landed cost cards for revaluation of issued raw material. | FACT, `LandedCostService.php:167,188`; `StockPostingService.php:253-266` |
| Data (mockup) | `inv_reconcile_log` 0 rows (the daily job has not stored anything there); `inv_repost_log` 0 rows. | FACT, query |

## Relations
- **source**: `fin_voucher`, `fin_voucher_line`, `fin_account` (Finance), stock cards, landed cost documents.
- **used_by**: Finance month-end review, Valuation & Period screen.
- **flows**: stock posting and journals -> (04:00 job) reconcile log -> report; value changes of the past -> repost log -> report.
- **frozen_zone**: none.

## Findings (recorded, not fixed)
1. **Journals do not cover every card movement**, so DIFF is expected after normal work: manual Stock In, Stock Return, Opening Balance (BB), Adjustment/Opname and all FG/SFG movements post stock cards but no journal (only goods receipts and Stock Out of RM/SFG are journalled; code comments say so). The reconciliation therefore compares things that are designed to differ. FROM CODE; effect visible in the run below.
2. **Comment out of date**: `ReconcileService` says landed cost stays "pending" until value rows exist in the cards; those rows now exist (`LandedCostCards`), so `landedPending` only shows documents not yet synced. FACT (both read).
3. **Fixed tolerance** `INV_RECON_TOLERANCE` is an absolute amount, not a percentage. FACT.
4. **Class accounts are settings**: if the chart of accounts changes the three constants must follow; a wrong list silently changes the GL side. FROM CODE.
5. **Repost log not written for unchanged journals**: `correct` returns null without a log when nothing differs. FROM CODE (by design).
6. **Rule A.24 is out of date**: its last bullet says the journal for later issues is "not decided, shown as warning"; the code now posts a revaluation journal for raw material (`revalueFromChain`), FG and SFG are still only warned. FACT (code versus `docs/globaluserrules.md`).

## Tests (existing, not run now)
`InvReconcileTest` (difference is ledger minus stock card and flagged; equal is OK; saved once per day and class; ledger counts only posted vouchers up to the date; the command prints without saving), `LandedCostRepostTest`.

## Already exists? Searched where
Searched: `inv_reconcile_log`, `inv_repost_log`, `ReconcileService`, `RepostService`, command `InventoryReconcile`. The concept ALREADY EXISTS.

<!-- johiwan-only -->
## Result of `inv:reconcile --no-save` on the mockup (16-Oct-2026)
GL against cards, RM: 1,305,309,211.46 vs 558,324,000.00 (difference 746,985,211.46); SFG: 214,000,000.00 vs 0.00; FG: 392,000,000.00 vs 0.00. All three DIFF. The GL accounts hold dummy journal opening values while the cards carry far less; account codes RM 113100,113200,113600, SFG 113400, FG 113500; tolerance 1.
- Re-checked 2026-10-19 after Return from Production moved to the v2 form `inv-stock-doc-form` (rule D.25, Master T5c): `routes/web.php` replaced the v1 `/inventory/stock-return/{category}` route by a redirect on the same line (no line added or removed), `app/Support/Menu/MenuRoutes.php` lost the two Return entries, `app/Support/V2/Lookups.php` gained the `stock_return_bin` lookup (lines after 68 moved by two), `app/Services/Inventory/StockPostingService.php` gained the `addReturned` call on an existing line and one method at the end of the file, and `routes/v2_stockdocs.php`, `StockDocGrid.php` and the screen got the Return kind; this card does not depend on the changed lines and its content is still true.
