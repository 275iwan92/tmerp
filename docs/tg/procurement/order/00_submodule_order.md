# Sub-module: Order (Purchase Quotation, Purchase Order, supplier prices)

> Written 2026-10-17 (group Procurement). This page only summarizes and links; table / column facts are in the cards.

- **Module**: Procurement > Order (`PROC_GRP_ORDER`). Menu (FACT, `sys_app_menu` mockup, active): Purchase Quotation (`v2:purchase-quotation`, form), Purchase Order (`v2:purchase-order`, form), PO Approval Center (`v2:approval-po`, grid), group Report with Outstanding PO (`v2:pur-rpt-outstanding-po`). The price tables are maintained in Master Data (five screens, see the price tables card).
- **Purpose**: collect the supplier's offers (optional), order from the approved requisition allocations at an agreed price, get the PO approved and sent, and propose paper prices from supplier constants.
- **Frozen zone**: the PO / Goods Receipt / GR-IR flow (statuses and numbers of the PO) is frozen; ask Jo before changing it.
- **Rules**: A.11 (PO approval levels, see the approval card for the difference with the data), A.8-A.9 (supplier prices confidential: figures only after the johiwan-only marker), A.7 (engines suggest, the officer decides), A.14 (master changed only by the user), D.24.

## Relations
- **source**: approved PR allocations (Requisition), supplier master, supplier price tables, `fin_tax_rate` (PPN by date), supplier files (PQ).
- **used_by**: Goods Receipt (Receive), Subcon stock and Shortage Request (Inventory), Material Planning (open PO = supply), Petty Cash (Cash PO), Finance GR/IR.
- **flags**: `po_order.status` (+ `stat_cancel`, never set); line status in `po_line.c_line_status`; `pq_quotation.status`.

## Cards and engines in this sub-module
| Type | File | Content |
|---|---|---|
| Concept | `10_card_purchase-quotation.md` | `pq_quotation`, `pq_line`, upload and read, compare, make PO |
| Concept | `11_card_purchase-order.md` | `po_order`, `po_line`, creators, tax, statuses, Cash PO, Subcon PO |
| Concept | `12_card_supplier-and-category.md` | `sup_supplier`, `sup_category` as Procurement uses them |
| Concept | `13_card_paper-price-tables.md` | five supplier price tables and their screens |
| ENGINE | `20_engine_pq-matcher.md` | `pq-matcher`: supplier and line matching of a quotation file |
| ENGINE | `30_engine_paper-price.md` | `paper-price`: entry point and ivory board by kg |
| ENGINE | `31_engine_single-face-price.md` | `single-face-price` |
| ENGINE | `32_engine_duplex-price.md` | `duplex-price` |
| ENGINE | `33_engine_corrugated-sheet-price.md` | `corrugated-sheet-price` |
| ENGINE | not written | `PoBuilderService` and `PrAllocationService` (covered in the PO and PR cards; they have no registry entry) |

## Diagram
Not made yet (flow lines are in the "flows" bullets of the cards).

## Existing User's Guide (UG)
None for Procurement. Old documents `docs/md_for_systems/procurement_tier2.md`, `procurement_tier3.md`, `purchasereceive_delta_multipo.md` are FROM DOCUMENT; only quoted claims were checked.

## Cross-card findings (summary; details in the cards)
No Cancel or Close for a PO (status words exist, nothing writes them); deleting a Draft PO leaves the PR line marked as ordered; the PO never records the PQ, the currency or an import checklist; `mat_raw.suggest_supplier_id` empty so the supplier price engines never run on a PR line; four of the five price tables are empty in the mockup data; the PQ matcher is strict (none of four sample texts matched) and has no test; blacklisted suppliers and supplier payment terms have no effect on a PO.
