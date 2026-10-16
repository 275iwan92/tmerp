# Sub-module: Stock (Stock In/Out/Return/Adjustment for RM, SFG, FG; Stock Card; Reports; Valuation & Period)

> Written 2026-10-16. Verified: 2026-10-16. This page only summarizes and links; table/column facts are in the cards. Screens were read from code, not opened in a browser; tests were read, not run.

- **Module**: Inventory > Stock (`INV_STOCK`). Menu (FACT, `sys_app_menu` mockup, active), per class RM / FG / SFG: Stock In, Stock Out, Return from Production, Stock Adjustment & Physical Count, Adjustment Approval (`v2:approval-stock`), and Report: Stock Balance, Stock Card, Slow Moving Item (`v2:inv-rm-*`, `v2:inv-fg-*`, `v2:inv-sfg-*`). Also: Valuation & Period (`v2:inv-valuation`, `v2:landed-cost`, `v2:obsolete-stock`, reports GR/IR Aging, Daily Reconciliation, Repost, Stock Aging) and Reports (`INV_WH_RPT`: Stock Card, Stock Recapitulation, Stock Summary, Stock Opname, Stock Loss & Gain, Stock in Inspection, Valuation Check).
- **Purpose**: one posting door writes every stock movement as a card row (BB, In, Out, Adj, EB per item and location); value follows one running Average per item; month close and reconciliation keep stock and the general ledger in step.
- **Frozen zone**: none of this sub-module; stock posting rules (no negative stock, no LIFO) are in the cards.
- **Rules**: Average = running per item for the whole company, Issue never changes it (FROM DOCUMENT `docs/design-notes/inventory/08_invwh-tech-rules.md`; the code is in `23_engine_item-average-cost.md`); rulebook A.24 on the revaluation journal is out of date (see finding below). Cost figures and account codes sit after the johiwan-only line in cards 12, 16 and 17.

## Relations
- **source**: Purchase Receive / Invoice (goods in, GR/IR), Production (material issue, return), Warehouse Stock Transfer (moves between bins), Batch (`batch_id` on card rows).
- **used_by**: Warehouse map and pick (on-hand), Material Planning (availability), Finance (GL journals, GR/IR), Costing (average cost), Management reports.
- **flags**: stock documents use their own status values (see the stock document card), not the common `stat_confirm/_cancel/_close` set in every case.

## Cards and engines in this sub-module
| Type | File | Content |
|---|---|---|
| Concept | `10_card_stock-document.md` | stock document: Stock In/Out, Return, Adjustment, Reversal |
| Concept | `11_card_stock-card-and-balance.md` | stock card ledger and on-hand balance |
| Concept | `12_card_adjustment-and-opname.md` | adjustment, physical count, approval |
| Concept | `13_card_return-from-production.md` | Stock Out and Return from Production |
| Concept | `14_card_inventory-period.md` | inventory period close |
| Concept | `15_card_landed-cost.md` | landed cost |
| Concept | `16_card_obsolete-stock.md` | obsolete stock provision, aging |
| Concept | `17_card_reconciliation-and-repost.md` | daily reconciliation, repost |
| Concept | `18_card_stock-reports.md` | stock reports overview |
| Concept | `19_card_gr-ir-aging.md` | GR/IR aging |
| ENGINE | `20_engine_stock-posting.md` | `StockPostingService`, `StockDocumentService` |
| ENGINE | `21_engine_stock-card-chain.md` | `StockCardChain`, `ItemBalanceChain` |
| ENGINE | `22_engine_stock-reversal.md` | `StockReversalService` |
| ENGINE | `23_engine_item-average-cost.md` | `ItemAverageCost` |
| ENGINE | `24_engine_stock-ledger.md` | `StockLedger`, `StockItemMeta`, `WarehouseStock`, `FgAvailability` |
| ENGINE | `25_engine_landed-cost.md` | `LandedCostService`, `LandedCostCards` |
| ENGINE | `26_engine_repost-reconcile.md` | repost, reconcile, obsolescence, GR/IR, period, valuation accounts |

## Diagram
Not yet made.

## Existing user guides (UG)
`docs/guides/inventory/10_stok_masuk_keluar_retur.md`, `11_penyesuaian_dan_opname.md`, `12_kartu_stok_dan_laporan.md`, `13_valuasi_dan_periode.md`.

## Cross-card findings (short; details in the cards)
A REJECTED adjustment shows no buttons and a posting refused after the last approval leaves the document stuck at SUBMITTED; finished-goods stock moves only by manual Stock In, adjustment and count (Delivery Order deducts nothing, production output adds nothing; FG Stock Out/Return forms are read-only); Return posts no counter-journal and cancelling a Stock Out with returns is not refused; in the mockup `mat_raw.cur_stock` differs from the card on-hand for 46 of 81 items, only inventory period 202608 exists, and seven reversal documents point to missing originals; six `INV_*` constants are seeded but never read; Daily Reconciliation shows a DIFF on all three classes (cause not verified).
