# Sub-module: Delivery (Delivery Order, Delivery Notes, Proof of Delivery)

> Written 2026-10-16 (group A). This page only summarizes and links; table/column facts are in the cards.

- **Module**: Sales > Delivery (`SALES_DLVGRP`). Menu (FACT, `sys_app_menu` mockup, active): Delivery Order (`v2:delivery-order`), Delivery Notes (`v2:delivery-notes`), Subcontract Delivery Note (`v2:subcon-delivery-note`), Proof of Delivery (`v2:proof-of-delivery`).
- **Purpose**: from a delivery schedule already Posted to Production, create the physical delivery document (DO/Surat Jalan without price), mark it Shipped, then record the arrival at the customer and the received qty (basis of invoicing).
- **Frozen zone**: Delivery document flow (the rule freezes all flows).
- **Rules**: A.3 (DO/DN after POST, production output must still be entered), A.9 (price only for holders of the right; Surat Jalan without price), A.21 (invoice basis = received qty, FROM DOCUMENT).

## Relations
- **source**: CPDEL (`co_order_delivery` via view `v_co_delivery_outstanding`), CO (price, description), Master Customer/Supplier.
- **used_by**: Customer Invoice (`qty_received`), on-time delivery KPI, Customer 360, Delivery Track (route diagram).
- **flags**: `do_delivery.status` (DRAFT/CONFIRMED/SHIPPED/CANCELLED), not `stat_confirm`; see the Status card at `../order/12_card_document-status.md`.

## Cards and engines in this sub-module
| Type | File | Content |
|---|---|---|
| Concept | `10_card_delivery-order.md` | `do_delivery`, `do_line`, Surat Jalan = DO CONFIRMED/SHIPPED, subcon, price |
| Concept | `11_card_proof-of-delivery.md` | `do_delivery_pod`, on-time promise, received qty, KPI |
| ENGINE | not yet written | `DispatchService` (pull/confirm/shipped/cancel; covered in the DO card), `DeliveryPromiseService` (on-time promise and received qty rules; covered in the POD card), `DeliveryTrack` (route diagram, NOT read) |

## Diagram
Not yet made.

## Existing user guides (UG)
`docs/guides/sales/04_quotation_order_cpdel.md` (mentions CPDEL; whether it covers DO/POD is NOT checked).

## Cross-card findings (short; details in the cards)
CONFIRMED DO without lines in the dummy; POD does not change the DO status; the Post-to-Production gate exists only on the v2 screen; `price_change_approval_status` and `split_from_line_id` are unused; there is no test for `DeliveryPromiseService`.
- Re-checked 2026-10-22 after the entry rules of PR, PO, receipt, Delivery Order and CPDEL moved from the v1 pages to services (rule D.25 and D.26, Master T10 stage 7a-7c): `routes/v2.php` and `app/Support/V2/Screens.php` only had comment and description text changed on their existing lines (no line added or removed), and `app/Support/V2/Data/DeliveryOrder.php` and `MaterialPlanning.php` changed in the lines their cards do not cite or were remapped from the diff; this card's content is still true.
- Re-checked 2026-10-23 after the DO/Delivery Note defect fixes (Jo: always fix): `DispatchService.php` (Confirm/Ship/Cancel judge the status on the locked DO row, a refused Confirm returns a message instead of a 500, the CO ship-to address is copied to the DO and required at Confirm, the DO number is issued under a named lock), `DeliveryNotePrint.php` (refuses a DRAFT or CANCELLED DO) and the DO screen (price only for the price right, cross-card finding 10 of the DO card) changed; this page only links and summarizes, and its content is still true.
