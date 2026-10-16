# Sub-module: Order (Customer Order and CPDEL)

> Written 2026-10-16 (group A). This page only summarizes and links; table/column facts are in the cards.

- **Module**: Sales > Order (`SALES_ORDGRP`). Menu (FACT, `sys_app_menu` mockup, active): Customer Order (`v2:customer-order`), Customer Plan Delivery (`v2:cpdel`), CO Approval Center (`v2:approval-co`), Report group (Customer Order Summary, Customer Order Report, Customer Order Shipment Detail), General Report group (the same three reports without prices, `GENERAL_*`). The v1 items `grid_so.php`, `rpt_sales.php`, `grid_monitoring_order.php` are inactive.
- **Purpose**: turn a winning quotation (or a direct order) into an approved Customer Order, then into a delivery schedule (CPDEL) that is the basis for the Work Order and Delivery Order.
- **Frozen zone**: the CO and CPDEL document flow (the rule that freezes all flows). Read: `docs/claude_vital_flows.md` (Quotation and Customer Order sections), `docs/ENGINE-ATTENTION.md` (no items for CO/CPDEL).
- **Rules**: A.1 Quotation must be integrated; A.2 CO may be direct (INSTANT); A.4 1 Quotation = 1 CO; A.10 CO due date; A.11 CO approval; A.13 snapshot; A.15 one qty per product; A.16 Post to Production; A.20 confirmation flags.

## Relations
- **source**: Quotation `PUBLISHED` (Inquiry & Quotation; cards by other authors), Master Product and Customer, product ACC Digital, ProCost tier (tolerance only).
- **used_by**: Delivery (Delivery Order pulls CPDEL schedules that are already Posted), Production (WO from CPDEL lines), Material Planning (CO Confirmed = firm), Customer Invoice, CRM (Customer 360, Complaint).
- **flags**: `co_order.doc_status` and `stat_cancel` (not `stat_confirm`); see the Status card.

## Cards and engines in this sub-module
| Type | File | Content |
|---|---|---|
| Concept | `10_card_customer-order.md` | `co_order`, `co_order_item`, status, due date, tolerance, approval, cancel |
| Concept | `11_card_cpdel.md` | `co_order_delivery`, `co_delivery_negotiation`, split/merge/post |
| Concept | `12_card_document-status.md` | status of all Sales documents, A.20 flags, `doc_state_log` (does not exist) |
| Concept | `13_card_snapshot.md` | map of master freezing in Sales (A.13) |
| ENGINE | `20_engine_co-order-builder.md` | WIN -> CO, direct CO, submit/confirm (CPDEL), cancel |
| ENGINE | `21_engine_cpdel-promise.md` | two promises: customer slot vs ready for production, due-date colors |
| ENGINE | not written yet | `CoDueDate` (covered in the CoOrderBuilder engine card), `FlowEngineService` (approval, owned by the Flow module) |

## Diagram
Not made yet (a text twin is in the "Flow" section of the engine cards).

## Existing User's Guide (UG)
`docs/guides/sales/04_quotation_order_cpdel.md`. The TG does not repeat its content.

## Cross-card findings (summary; details in the cards)
CO2600021 is CONFIRMED and also `stat_cancel=1`, with its schedule still shown in CPDEL; the v1 `confirm` path skips approval; A.10 (PHYSICAL proof) conflicts with the code (ACC Digital); `sales_person_id` is empty in all COs; ESCALATED loses to LATE in the promise engine; merging lines deletes the negotiation log.
