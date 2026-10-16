# Concept card: Landed Cost (freight, duty, insurance that arrive after the receipt)

> Written 2026-10-16 (group Inventory/Stock). FACT = mockup DB (`ddtinv`) and code with line numbers, seen this session. The tables are empty in the mockup (0 rows), so no data example exists. Tests read by name only; none run.
> Verified: 2026-10-16

- **Document type**: concept card. **Module/sub-module**: Inventory > Stock > Valuation & Period > "Landed Cost" (menu `INV_VAL_LANDED`, `v2:landed-cost`).
- **Canonical name**: Landed Cost document = `inv_landed_cost` (header) + `inv_landed_cost_line` (one row per goods-receipt stock line the cost is shared over). Models `InvLandedCost`, `InvLandedCostLine`; service `LandedCostService` (engine card `25_engine_landed-cost.md`).
- **Synonyms**: biaya impor, ongkos angkut, bea masuk, asuransi, freight-in, landed cost, Selisih Landed Cost (variance account).
- **NOT the same as**: the supplier invoice or goods receipt itself (Procurement); the purchase price on the PO line; Costing HPP of products (Sales > ProCost).

## Facts
| Item | Content | Evidence |
|---|---|---|
| Header | `inv_landed_cost`: `doc_no` (LC + 2-digit year + `-` + 5 digits, given at Confirm), `doc_date`, `supplier_id` (who charged), `cost_type` FREIGHT / DUTY / INSURANCE / OTHER, `description`, `amount`, `alloc_basis` VALUE or QTY ("Share By"), `amount_to_stock`, `amount_to_variance`, `card_synced`, `stat_confirm`, `stat_cancel`, `cancel_reason`, `voucher_id`, `variance_voucher_id`, `confirmed_at`, `confirmed_by`. | FACT, `show columns`; `InvLandedCost.php`; `LandedCostService.php:139-145` |
| Line | `pv_id`, `pv_line_id` (the goods-receipt line), item, `qty_received`, `base_value` (accepted quantity x PO unit price), and the result of Confirm: `qty_on_hand`, `allocated`, `to_stock`, `to_variance`, plus `card_value`, `card_rev`, `card_location_id` (what has been written to the stock card). | FACT, `show columns`; `LandedCostService.php:73-75` |
| Status | DRAFT -> CONFIRMED -> CANCELLED (derived from the two flags by `status()`). Draft is editable; Confirmed can be Revised (new amount) or Cancelled; Cancelled is final. | FACT, `InvLandedCost.php`; `LandedCostService.php:126-194` |
| Scope | Raw material only: receipts of type PO with POSTED status and stock lines with accepted quantity; the on-hand used for the split is the RM on-hand view. | FACT, `LandedCostService.php:60-82,254-257` |
| Split rule | Cost is shared over the lines by receipt value or by quantity (rounding difference goes to the last line). Per line the share is divided into a part for stock still on hand and a part for stock already issued: ratio = on-hand of the item now / received quantity of the item in this document, capped at 1. | FACT, `LandedCostService.php:96-124` |
| Journal | Confirm posts journal code PUR, source LANDED: debit inventory (account of the item or its category) for the on-hand part, debit the landed-cost variance account for the issued part, credit the landed-cost payable account for the full amount. Accounts come from `sys_constant` `INV_ACC_LANDED_VARIANCE`, `INV_ACC_LANDED_PAYABLE` and `INV_ACC_LANDED_COGS`. | FACT, `LandedCostService.php:219-244`; constants query |
| Stock card | Each line writes a value-only card row (doc_type VA, quantity 0, `value_in` = value) in the bin with the most stock, so the item average rises and the balance chain stays connected; Revise adds a difference row, Cancel a reversing row. | FACT, `LandedCostCards.php:30-60` |
| Month end | `closeVariance(YYYYMM)` moves the issued-part variance of all Confirmed documents of that month from the variance account to cost of goods sold (one journal, GEN / LANDED_CLOSE) and marks each document with `variance_voucher_id`. A document with a variance voucher cannot be cancelled. | FACT, `LandedCostService.php:197-217,182-184` |
| Date rules | Not on or before the inventory closed-through date; not earlier than the goods-receipt date. | FACT, `LandedCostService.php:259-269` |
| Screen | `landed-cost` (grid `LandedCostListGrid`, new form, document with Goods Receipt picker, Remove, Confirm, Revise Amount, Cancel); the month-end button is on the Valuation & Period screen. Reasons for Revise and Cancel need at least 10 characters. | FACT, `landed-cost.blade.php`; `LandedCostService.php:153-183` |
| Data (mockup) | 0 documents, 0 lines. | FACT, query |

## Relations
- **source**: posted goods receipts (`pv_receipt`, `pv_line`), supplier master, item and category accounts, `sys_constant`.
- **used_by**: stock card (VA rows), item average (card `23_engine_item-average-cost.md`), daily reconciliation (landed-cost pending), Repost report (correction and reversal journals), Stock Aging/Obsolete (value basis).
- **flows**: goods receipt -> Landed Cost Draft -> Confirm (journal + card rows) -> optional Revise / Cancel -> month-end variance close.
- **frozen_zone**: none; this is the area approved by Jo 2026-10-08 ("landed cost terlambat ke qty yang masih ada + akun Selisih Landed Cost", rule A.26, FROM DOCUMENT).

## Findings (recorded, not fixed)
1. **Revise after the variance was closed**: `revise` does not check `variance_voucher_id`, but `closeVariance` skips any document that already has one. A revised amount that changes `amount_to_variance` after the month was closed leaves the new variance in the variance account forever. FROM CODE (both methods read in full); not reproduced.
2. **Journal and stock write are not one transaction**: the voucher is committed on `tm2acc` by `JournalPostingService`; the stock-card sync runs in the `tm2inv` transaction around it. A failure in the sync rolls back the document but not the voucher. FROM CODE, not reproduced.
3. **RM only**: landed cost on SFG or FG items is not supported (the on-hand source and the card writer are RM only). FACT (code).
4. **Item average with scope BIN**: the value row is written for the bin with the most stock; with `INV_AVG_SCOPE` BIN only that bin's price rises. FROM CODE; the setting is BIN in the mockup (card `23_engine_item-average-cost.md`).
5. **Month-end close is a manual button** with a free text period (default the current month); nothing reminds the user, and a closed inventory period does not block it. FROM CODE.
6. **Untested path**: there is no test for Revise after `closeVariance`, nor for a journal failure during Confirm (grep of `LandedCost*Test.php` names).

## Tests (existing, not run now)
`LandedCostTest` (split by value and by quantity, rounding to the last line, balanced journal, no lines refused, confirmed document cannot be confirmed or edited again, revise stacks a correction, cancel reverses and is logged, close variance once), `LandedCostCardsTest`, `LandedCostRepostTest`, `LandedCostScreenTest`.

## Already exists? Searched where
Searched: `inv_landed_cost*`, `LandedCostService`, `LandedCostCards`, menu `INV_VAL_LANDED`, Procurement for a similar cost (none found), design note `08_invwh-tech-rules.md` item 3 (FROM DOCUMENT). The concept ALREADY EXISTS.
