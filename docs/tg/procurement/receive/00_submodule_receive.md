# Sub-module: Receive (Goods Receipt, Shortage Request)

> Written 2026-10-17 (group Procurement). This page only summarizes and links; table / column facts are in the cards.

- **Module**: Procurement > Receive (`PROC_GRP_RECEIVE`). Menu (FACT, `sys_app_menu` mockup, active): Purchase Receive (`v2:purchase-receive`, form), Shortage Request (`v2:supplier-shortage`, form; the same screen as in Inventory > Batch & Quality), group Report with Outstanding PV (`v2:pur-rpt-outstanding-pv`) and Shortage by Supplier (`v2:shortage-by-supplier`).
- **Purpose**: record what a supplier, a subcontractor or a customer delivered against an order, check quantity and quality, post it into stock and the GR/IR account, update the PO, and follow up what was not delivered.
- **Frozen zone**: the Goods Receipt posting flow and its link to PO and GR/IR are frozen; ask Jo before changing them.
- **Rules**: PV-02 and PV-04 checks in the builder, receipt tolerance `RECEIVE_TOLERANCE_PCT`, number born at posting (Jo 2026-10-02), v2.01 rules for URGENT and shortage (Inventory), D.24.

## Relations
- **source**: SENT or PARTIALLY_RECEIVED POs (Order), the CO (customer material), Subcon Delivery Notes (Sales), warehouse and bin master (Inventory).
- **used_by**: Inventory (Stock In RM, batch, putaway), Finance (GR/IR journal, aging, invoice receipt), Purchase Return, Shortage Request, Material Planning (via the PO status).
- **flags**: `pv_receipt.status` (DRAFT, QC_PENDING, POSTED, CANCELLED); `is_handed_over` (never set).

## Cards and engines in this sub-module
| Type | File | Content |
|---|---|---|
| Concept | `10_card_purchase-receive.md` | `pv_receipt`, `pv_line`, quality check, tolerance, numbers, data |
| ENGINE | `20_engine_pv-posting.md` | post, cancel, recalculate PO; the receipt builder (no registry entry) |
| ENGINE | `21_engine_shortage-request-service.md` | `shortage-request` trigger map; helper `shortage-detector` |
| Other authors | [Shortage Request concept card](../../inventory/batch-quality/15_card_shortage-request.md), [Urgent Receipt engine](../../inventory/batch-quality/26_engine_urgent-receipt.md) (registry key `urgent-receipt`), [Shortage Auto Detector engine](../../inventory/batch-quality/29_engine_shortage-auto-detector.md) (key `shortage-auto-detector`) | written in the Inventory group; not repeated here |

## Diagram
Not made yet.

## Existing User's Guide (UG)
Inventory has guides for receiving and shortage (`docs/guides/inventory/20_penerimaan_dan_putaway.md`, `docs/guides/inventory/32_kekurangan_kirim_dan_klaim_supplier.md`); Procurement has none.

## Cross-card findings (summary; details in the cards)
The PO counts delivered rather than accepted quantity; `is_handed_over` and `c_qty_invoiced` are never written; the three posted dummy receipts have no stock document; Post is not atomic across the three databases and a closed GL period raises an error after the receipt is already POSTED; reversals use today's date; the line picker is unsorted; "Close Lapsed" has no schedule.
