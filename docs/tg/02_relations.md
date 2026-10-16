# Cross-module relations list (TG Phase 3)

> 2026-10-16. Taken from the Relations block of the Sales cards (`source`, `used_by`, `flows`). Each row names its source card; table/column facts are in the card. Column Target side: **exists** when the target module already has a card that writes the opposite relation; **one-sided** when not yet (Phase 4 rule: one-sided relations are flagged by the checker).

| From | To | What flows | Source card | Target side |
|---|---|---|---|---|
| Master `prod_type` | Inquiry (part) | `type_code` + `dieline_spec` frozen in `iqp_part.dieline_spec` | product-type | exists |
| Master Customer | Inquiry, Quotation, CO | `payment_term`, `currency_code` copied then frozen (A.13) | customer | exists |
| Master Sales Person | Inquiry, Quotation, CO | `pic_sales` (text) -> `sales_pic` (text) -> `sales_person_id` (id by name) | sales-pic | exists |
| Supporting masters (finishing, joint type) | Inquiry | name frozen (`finishing_name`, `rawmat_name`) | inquiry-supporting-masters | exists |
| Inquiry | ProDev | `sendToProDev`: size in cm to `prod_part`; Confirm Sales | inquiry-and-part, prodev-interface | exists |
| ProDev | Inquiry | layout/BOP/BOM/ink go back to `iqp_part_*`; `stat_confirm_prodev` | prodev-interface | exists |
| ProDev | ProCost | read-only context; `stat_confirm_procost` computed from published costing | prodev-interface, procost-interface | exists |
| ProCost | Quotation | price per tier (`unit_price`), declassified at publish | procost-interface, quotation | exists |
| ProCost | CO | tier `tolerance_pct` frozen | procost-interface, customer-order | exists |
| Quotation | CO | WIN: Quotation lines snapshotted to `co_order_item` | quotation, customer-order | exists |
| Inquiry | Master Product | `ProductMasterSync::fromInquiry` at WIN | inquiry-and-part, snapshot | one-sided (Master Product card not yet) |
| Master Card Collection | Inquiry | old card becomes an Inquiry with source `MASTER CARD` | inquiry-and-part | exists |
| Reverse Costing | Inquiry | INSTANT Inquiry; writes `stat_confirm_*` | inquiry-and-part, reverse-costing | exists |
| CO | CPDEL | CO CONFIRMED creates slot rows (CO engine) | customer-order, cpdel | exists |
| CPDEL | Delivery Order | `do_line.co_delivery_id` (FK RESTRICT); pull slot into DO | cpdel, delivery-order | exists |
| Delivery Order | POD | arrival and `qty_received` | delivery-order, proof-of-delivery | exists |
| CPDEL | Manufacture (WO, PPIC) | `prd_wo.source_delivery_id`; `PlanEvents::replan`; first promise | cpdel | one-sided |
| CO | Manufacture (WO) | `prd_wo.source_co_id` | customer-order | one-sided |
| CO | Material Planning (Planning) | CO Confirmed = firm | customer-order, material-planning | exists (engine card) |
| ProDev | Manufacture (WO, PPIC) | `forWorkOrder`; Master Card inherited | prodev-interface, dieline-and-master-card | one-sided |
| Dieline/Master Card | ProDev, ProCost | `dieline_spec`/flat for imposition and geometry | dieline-and-master-card | exists |
| Material Source | ProCost, SpecEngine | material owner per row; copied to BOM | material-source | exists |
| POD | Finance (Customer Invoice) | invoice basis `do_line.qty_received` (A.21, FROM DOCUMENT) | proof-of-delivery | one-sided, not checked |
| Complaint | QA (Production) and Finance | QA desk; returns (NOT checked) | crm-complaint | one-sided |
| Delivery Order | Inventory/QA | `QcTraceService` reads the DO | delivery-order | one-sided |
| Customer 360 | (read-only) | display only: Customer, Inquiry/Quotation, CO, DO, Activity, Complaint | customer-360 | does not write |

## One-way relations between cards that Jo/Phase 4 should check
1. The Quotation card says Master Product is "born at WIN"; the Customer Order and Snapshot cards say it from the other side, but the Master Product card does not exist yet.
2. Sales Win-Rate: the CRM Activity card writes "NOT checked whether it uses activities".
