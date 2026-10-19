# ENGINE card: Repost, Reconcile and valuation helpers (RepostService, ReconcileService, ObsolescenceService, GrIrAgingService, InvPeriodService, ValuationAccounts)

> Written 2026-10-16 (group Inventory/Stock). Source read in full: `app/Services/Inventory/Valuation/RepostService.php` (134 lines), `ReconcileService.php` (73), `ObsolescenceService.php` (139), `GrIrAgingService.php` (109), `InvPeriodService.php` (64), `ValuationAccounts.php` (63), `ValuationRefused.php`. Document-level rules: concept cards `14`, `16`, `17`, `19` (not repeated). No test was run (no DB writes allowed); one read-only command run is noted. No amounts are quoted.
> Verified: 2026-10-16

- **Canonical name**: Valuation services. **Classes** (all `final`, namespace `App\Services\Inventory\Valuation`): `RepostService` (`correct`, `reverse`, `revalueIssued`, `revalueFromChain`), `ReconcileService` (`run`, `glValue`), `ObsolescenceService` (`classify`, `pct`, `rows`, `summary`, `previousTotal`, `post`), `GrIrAgingService` (`rows`, `totals`, `bucket`), `InvPeriodService` (`close`, `open`, `createNext`), `ValuationAccounts` (`id`, `codes`, `ids`, `stockFor`, `cogsFor`), exception `ValuationRefused`. **Registry**: NOT registered (searched `EngineRegistry` for each name). **Module/sub-module**: Inventory > Valuation & Period.
- **Role**: rules and journals of the valuation side. They enforce; the user presses the buttons. Messages of `ValuationRefused` are already translated and shown as a red alert.

## Trigger
| Service | Trigger | Evidence |
|---|---|---|
| `ReconcileService::run` | schedule `inv:reconcile` daily 04:00; console command `InventoryReconcile` (options `--date`, `--no-save`) | `routes/console.php:51`; `InventoryReconcile.php:13` |
| `RepostService` | Landed Cost Revise / Cancel; `StockPostingService::postRevaluation`; `LandedCostCards::sync` | `LandedCostService.php:167,188`; `StockPostingService.php:253-266`; `LandedCostCards.php:51-55` |
| `ObsolescenceService::post` | button Post provision (`v2.val.obsolete.post`) | `routes/v2_valuation.php` |
| `InvPeriodService` | buttons Close / Open / Add Next Period (`v2.val.period`) | `routes/v2_valuation.php` |
| `GrIrAgingService`, `ObsolescenceService::rows` | report grids at display time | `GrIrAgingReport.php`, `StockAgingReport.php` |

## Input / Output
- **RepostService**: input = the original `FinVoucher` and the intended lines (or a reversal reason); output = a new voucher through `JournalPostingService` (kind CORRECTION, dated today, GL period must be open) and one `inv_repost_log` row (kind CORRECTION, REVERSAL or REVALUATION). `correct` computes the net debit minus credit per account for both sets and posts only the per-account differences of at least 0.005; it returns null and logs nothing when nothing differs (27-54). `revalueIssued` posts Dr cost of goods sold / Cr inventory when the delta is positive and the opposite when negative, journal USG source REVALUE, accounts from the item (RM only); missing accounts give "Item #:id has no COGS / Inventory account (item or category); the revaluation was not posted." (70-90). `revalueFromChain` filters the card ids to RM usage issues (skips reversals and receipt cancels) and sums their deltas (98-115).
- **ReconcileService**: reads `fin_voucher_line` / `fin_voucher` on `tm2acc` (POSTED, posting date up to the day) and `StockLedger`; writes `inv_reconcile_log` unless told not to.
- **ObsolescenceService**: reads the on-hand views and constants, writes `inv_obsolete_provision(_line)` and a voucher (GEN / OBS_PROVISION).
- **GrIrAgingService**: reads `tm2pur` and `tm2acc`; writes nothing.
- **InvPeriodService**: writes `inv_period`; reads the GL period through `FinPeriodGuard::periodFor`.
- **ValuationAccounts**: resolves account codes named by constants to `fin_account.account_id` (active accounts only) and item/category accounts through `JournalPostingService::resolveAccount`.

## Flow (each engine is short; the order that matters)
1. Posting a stock document may change the average of later issues -> the chain reports revalued_docs -> after commit `postRevaluation` calls `RepostService::revalueFromChain` once per item (RM only).
2. Landed cost Revise -> `RepostService::correct`; Cancel -> `RepostService::reverse`; both log in `inv_repost_log` and the Repost report reads that table.
3. 04:00: `ReconcileService::run` for today (date start of day), per class compares GL and cards, saves a row.
4. Month end: user closes variance (Landed Cost card), then closes the inventory period (card `14`), then Finance closes the GL month (it needs the inventory month CLOSED).

## Edge cases and findings
- Every journal call needs an OPEN general-ledger period for its date (`FinPeriodGuard::assertOpen`); a revaluation or correction dated today fails when today's GL month is closed, and in the posting path that only becomes a warning. FROM CODE.
- `revalueFromChain` is for raw material only; FG and SFG repricing is reported but not journalled (card `17`, finding 6).
- `ValuationAccounts::id` throws if a constant is empty; `ids` silently returns an empty list, so a class with no account list reconciles to GL 0. FROM CODE.
- The command run on the mockup (no save) gave DIFF for all three classes; see the johiwan-only part of card `17`. FACT.

## Tests (existing, not run now)
`InvPeriodServiceTest`, `InvReconcileTest`, `ObsolescenceTest`, `GrIrAgingTest`, `LandedCostRepostTest` (higher issue cost debits cost of goods sold and credits inventory and is logged; lower flips; a correction posts only the difference; an identical journal posts nothing). No test file names `revalueFromChain` directly (grep of `tests/`); it is exercised only through the landed cost and posting tests.

## Used by
Valuation & Period screen, Landed Cost, Stock posting, Finance period close, Repost / Reconciliation / GR/IR / Stock Aging reports.
- Re-checked 2026-10-19 after Return from Production moved to the v2 form `inv-stock-doc-form` (rule D.25, Master T5c): `routes/web.php` replaced the v1 `/inventory/stock-return/{category}` route by a redirect on the same line (no line added or removed), `app/Support/Menu/MenuRoutes.php` lost the two Return entries, `app/Support/V2/Lookups.php` gained the `stock_return_bin` lookup (lines after 68 moved by two), `app/Services/Inventory/StockPostingService.php` gained the `addReturned` call on an existing line and one method at the end of the file, and `routes/v2_stockdocs.php`, `StockDocGrid.php` and the screen got the Return kind; this card does not depend on the changed lines and its content is still true.
- Re-checked 2026-10-19 after Stock Adjustment and Opname moved to the v2 form `inv-stock-adj-form` (rule D.25, Master T5d): `routes/web.php` replaced the v1 `/inventory/stock-adjustment/{category}/{doc?}` route by a redirect on the same line (no line added or removed), `app/Support/Menu/MenuRoutes.php` lost the three Adjustment entries, `app/Support/V2/Screens.php` got one item appended on an existing line, `app/Support/V2/Grid/Inv/StockDocGrid.php` and `app/Support/V2/Data/ApprovalCenter.php` changed in the inventory and approval parts, and `routes/v2_stockdocs.php` got a second route group at the end; this card does not depend on the changed lines and its content is still true.
- Re-checked 2026-10-19 after Purchase Return moved to the v2 form `pur-return-form` (rule D.25, Master T6): `routes/web.php` replaced the v1 `/purchasing/prt` and `/purchasing/prt/{prt}/edit` routes by redirects on the same lines and now also requires `routes/v2_purchase_return.php` on the line that requires `v2_finance.php`, `app/Support/Menu/MenuRoutes.php` lost `PROC_RET`, `app/Support/V2/Screens.php` got one item appended on an existing line, and `app/Support/V2/Data/ApprovalCenter.php` and `PurchaseReturnGrid.php` changed in the Purchase Return parts; this card does not depend on the changed lines and its content is still true.
