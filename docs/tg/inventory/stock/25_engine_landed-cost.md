# ENGINE card: Landed Cost (LandedCostService + LandedCostCards)

> Written 2026-10-16 (group Inventory/Stock). Source read in full: `app/Services/Inventory/Valuation/LandedCostService.php` (284 lines), `app/Services/Inventory/Valuation/LandedCostCards.php` (72), routes `routes/v2_valuation.php`. Tables and document rules: concept card `15_card_landed-cost.md` (not repeated). No test was run (no DB writes allowed in this task). No amounts are quoted.
> Verified: 2026-10-16

- **Canonical name**: Landed Cost Service. **Classes**: `App\Services\Inventory\Valuation\LandedCostService` (`create`, `saveHeader`, `addReceipt`, `removeLine`, `allocate`, `confirm`, `revise`, `cancel`, `closeVariance`) and `App\Services\Inventory\Valuation\LandedCostCards` (`sync`). **Registry**: NOT registered (searched `EngineRegistry`). **Module/sub-module**: Inventory > Valuation & Period.
- **Role**: enforces rules and posts journals; the user decides the amount, the receipts and the basis (A.7).

## Trigger (no background / schedule)
Routes `v2.val.landed.*` (create, save, receipt, line remove, confirm, revise, cancel) and `v2.val.landed.close-variance` in `routes/v2_valuation.php`, all with middleware `module:INV`. No schedule.

## Input / Output
- **Input**: the Draft document, goods receipt id, new amount and reason, period code, login.
- **Output (writes)**: `inv_landed_cost` and `inv_landed_cost_line` (allocation fields); vouchers in `tm2acc` through `JournalPostingService` and `RepostService`; `rm_stockcard` value rows; `inv_repost_log` for revise and cancel.
- **Refusals** (`ValuationRefused`, shown as a red alert on the screen, nothing saved): "Choose a valid cost type and allocation basis."; "Only a posted goods receipt can carry a landed cost."; "That receipt has no stock line left to add."; "The lines have no value or quantity to share the cost by."; "Enter the cost amount and add at least one receipt line before Confirm."; "Only a Draft landed cost can be changed."; "Only a Confirmed landed cost can be revised or cancelled."; "The reason is required, at least 10 characters."; "The new amount must be more than zero. To remove the cost, cancel the document."; "The variance of this landed cost was already closed to cost of goods sold; it cannot be cancelled."; "The inventory period up to :date is closed; use a later document date."; "The landed cost date cannot be earlier than the goods receipt date."; "Item #:id has no Inventory account (item or category); the landed cost was not posted."; "The period must be written as YYYYMM."; account constant missing: "The account for :key is not set or not found (Settings > Constants)."

## Flow
1. **Create / save** (Draft only): header fields, amount rounded to 2 decimals.
2. **Add receipt** (60-83): a POSTED PO receipt; adds every stock line with an accepted quantity not already in the document; `base_value` = accepted quantity x PO unit price.
3. **Allocate** (96-124, pure calculation, also used for the planned share on screen): total basis = sum of line values or quantities; each line share = amount x basis / total, the last line takes the rounding rest; the share of a line splits into `to_stock` (share x min(1, item on-hand / item received in this document)) and `to_variance` (the rest).
4. **Confirm** (126-150): checks amount > 0 and lines exist, checks the date rules, computes the plan, builds journal lines (debit inventory per account, debit variance, credit payable), then in one `tm2inv` transaction: takes the next number (LCyy- prefix, highest + 1 with `lockForUpdate`), posts the journal, saves the allocation, marks Confirmed, and calls `LandedCostCards::sync`.
5. **Card sync** (`LandedCostCards::sync`, 30-60): per line, delta = intended value (the `to_stock` while Confirmed, 0 when Cancelled) - `card_value` already written; skipped under 0.005; otherwise one VA card row (quantity 0, `value_in` = delta, dated the later of now and the document date, owner COMPANY) in the bin already used or the bin with the largest on-hand, then `StockCardChain::recalculate` and a revaluation call for later out rows. `card_synced` = every line matches. Idempotent: calling it again writes nothing.
6. **Revise** (153-174): reason >= 10 chars; recomputes the plan for the new amount; `RepostService::correct` stacks a correction voucher with the difference per account on the original; saves allocation; sync writes difference rows.
7. **Cancel** (176-194): reason >= 10 chars; refused when the variance was already closed; `RepostService::reverse` posts the reversing voucher; marks cancelled; sync writes reversing rows.
8. **Close variance** (197-217): all Confirmed, not cancelled documents of the month with a variance and without `variance_voucher_id`; one GEN journal debit cost of goods sold / credit variance account; marks every document.

## Edge cases and findings
- See concept card findings 1 and 2 (revise after the month close; journal outside the stock transaction).
- The card row of a back-dated value is dated today or later on purpose ("the part already issued went to the variance account, so earlier issues must not be re-priced"); later out rows ARE repriced and the difference is journalled once through `RepostService` (`revalueFromChain`).
- A bin must exist: an item with no balance anywhere gets no card row and its `to_stock` is 0 by construction. FROM CODE.
- Concurrency: the number is taken under a lock; `card_value`/`card_rev` give the idempotent card key (`inv_landed_cost_line#rev`).

## Tests (existing, not run now)
`LandedCostTest`, `LandedCostCardsTest` (item scope chained value row, bin scope accepts the row, card labelled Value adjustment, sync is idempotent, revise and cancel, later issue revalued once, back-dated receipt one revaluation journal), `LandedCostRepostTest`, `LandedCostScreenTest` (every valuation screen opens; create, add receipt, confirm and cancel from the screen; refused step shows the message; period actions report a refusal).

## Used by
Landed Cost screen, Valuation & Period screen (month-end button), Daily Reconciliation (`landedPending`), Repost report.
