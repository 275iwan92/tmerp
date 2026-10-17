# Sub-module: Return (Purchase Return, Debit Note)

> Written 2026-10-17 (group Procurement). This page only summarizes and links; table / column facts are in the cards.

- **Module**: Procurement > Return (`PROC_GRP_RETURN`). Menu (FACT, `sys_app_menu` mockup, active): Purchase Return (`v2:purchase-return`, grid), Purchase Return Approval Center (`v2:approval-prt`, grid). The debit note has no menu item.
- **Purpose**: send rejected or surplus material back to the supplier with a credit note or a replacement, with one approval level.
- **Frozen zone**: the Goods Receipt / GR-IR flow (an automatic Stock Out and journal on approval would change it); ask Jo first.
- **Rules**: one approval level (Operational Manager), reject reason of 10+ characters, D.24.

## Relations
- **source**: rejected quantities of POSTED receipts (Receive), the material master, the supplier.
- **used_by**: receipt cancel guard, Stock Inspection report (Inventory).
- **flags**: `prt_return.status` (DRAFT, SUBMITTED, CONFIRMED, COMPLETED, REJECTED, CANCELLED).

## Cards and engines in this sub-module
| Type | File | Content |
|---|---|---|
| Concept | `10_card_purchase-return.md` | `prt_return`, `prt_line`, two sources, approval, what approval does not do |
| Concept | `11_card_debit-note.md` | `dn_debit_note`, born only by a refused shortage request |
| ENGINE | none | the return has no engine and no registry entry |

## Diagram
Not made yet.

## Existing User's Guide (UG)
None. The Inventory guide `docs/guides/inventory/32_kekurangan_kirim_dan_klaim_supplier.md` covers the shortage side of the debit note.

## Cross-card findings (summary; details in the cards)
Approval of a return moves nothing (no stock, journal, PO or `c_qty_returned` update), so rejected quantities never fall; `dn_debit_note` is created as Draft and then never shown; both tables are empty in the mockup data and no test names `PrtReturn`.
