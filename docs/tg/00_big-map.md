# Big map ERP TM (TG Phase 3)

> 2026-10-16. Modules and sub-modules = the `sys_app_menu` tree (FACT, mockup query: 9 modules, 394 active menu rows). Flows between modules are only those written in the TG Sales cards (already verified by the cards themselves). Modules without a TG card are marked "not yet"; do not read a missing arrow as a missing flow. Diagram: [img/big-map.svg](./img/big-map.svg) (text twin = the table and the flow list below).

## Modules and sub-modules
| Module (code) | Sub-module (screen count) | TG |
|---|---|---|
| Sales (SALES) | Inquiry & Quotation (23), Order (9), Delivery (4), CRM (6) | **exists**: [sales/](./sales/) |
| Management Reports (MGMT) | Summary (1), Reports (6) | not yet |
| Procurement (PROC) | Requisition (3), Order (4), Receive (4), Return (2) | not yet |
| Inventory (INV) | Stock (56), Warehouse (19) | **exists**: [inventory/](./inventory/) (stock, warehouse, batch-quality) |
| Manufacture (PROD) | Planning (11), PPIC (13), Production (1), Maintenance (19), QA (2) | not yet (Design Notes exist for ppic, maintenance; Guides for production) |
| Human Resources (HRM) | Organization (5), Employee (4), Time Attendance (6), Payroll (8), Recruitment (11) | not yet |
| Finance & Accounting (FINACC) | Control Center (2), Transaction Flows (8), Accounting (5), Controls & Tax (2), Financial Reports (7) | not yet |
| Master Data (MDATA) | 21 items (Customer, Sales Person, Product, Raw Material, Supplier, UoM, Warehouse, Shift, Currency, etc.) | partial: Customer and Sales PIC cards |
| Setup (SETUP) | Security, Support & Ticket, Help and Support, Language, Dummy Data, Constants, Dashboard, Menu, Identification, Others | not yet |

Note: the Sales sub-modules in TG use the folders `inquiry-product`, `product-costing`, `prodev`, `quotation`, `order`, `delivery`, `crm`, `master-data`. The menu Sales > Inquiry & Quotation puts Pro-Dev and Pro-Cost as groups inside it; the TG folders separate them so that the engine cards sit near their users. Remapping exactly to the menu tree is postponed to Phase 4 (checker).

## Flows between modules (verified in the Sales cards)
1. Master Data -> Sales: `prod_type` (dieline snapshot), customer (`payment_term`, `currency_code`), sales person. Cards: [Product type](./sales/inquiry-product/10_card_product-type.md), [Customer](./sales/master-data/10_card_customer.md), [Sales PIC](./sales/master-data/11_card_sales-pic.md).
2. Sales upstream: Inquiry -> ProDev -> ProCost -> Quotation -> CO (flags A.20). Cards: [Inquiry](./sales/inquiry-product/11_card_inquiry-and-part.md), [ProDev](./sales/prodev/10_card_prodev-interface.md), [ProCost](./sales/product-costing/11_card_procost-interface.md), [Quotation](./sales/quotation/10_card_quotation.md), [CO](./sales/order/10_card_customer-order.md).
3. Sales downstream: CO -> CPDEL -> Delivery Order -> POD. Cards: [CPDEL](./sales/order/11_card_cpdel.md), [DO](./sales/delivery/10_card_delivery-order.md), [POD](./sales/delivery/11_card_proof-of-delivery.md).
4. Sales -> Manufacture: CPDEL slot to WO (`prd_wo.source_delivery_id`), CO to WO (`prd_wo.source_co_id`), PPIC Planning (`PlanEvents::replan`); CO Confirmed = firm for Material Planning; ProDev -> `forWorkOrder`; Master Card inherited by PPIC. Manufacture side: no card yet (one-sided relation).
5. Sales -> Finance: POD `do_line.qty_received` becomes the basis of the invoice (A.21, FROM DOCUMENT, not checked); returns from Complaint (NOT checked). Finance side: no card yet.
6. Sales -> Inventory/QA: `QcTraceService` reads the DO; Complaint appears on the QA desk (Production > Complaint Review). Inventory/QA side: no card yet.
7. Sales -> Master Data (reverse direction): at WIN, the transaction fills Master Product (`ProductMasterSync`).

Inventory sub-modules: [stock](./inventory/stock/00_submodule_stock.md), [warehouse](./inventory/warehouse/00_submodule_warehouse.md), [batch-quality](./inventory/batch-quality/00_submodule_batch-quality.md). Cross-module: Purchase Receive -> Batch/Stock In; Work Order -> Pick List -> Stock Out; Stock Posting -> Finance journals and GR/IR. Delivery Shipped posts no FG stock out (see Sales DO card).

Full relations list per pair: [02_relations.md](./02_relations.md). Engine map: [01_engine-map.md](./01_engine-map.md).

## Frozen zone
Inquiry Product, ProDev, ProCost, Quotation, Customer Order, CPDEL, Delivery (flow and documents). Before touching them: warn Jo first (CLAUDE.md item 1).
