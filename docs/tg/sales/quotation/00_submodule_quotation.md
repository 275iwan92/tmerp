# Sub-module: Quotation

> Group B, 2026-10-16. The sub-module page only summarizes and links. FACT = menu `sys_app_menu` mockup and code with lines.

- **Module**: Sales > Inquiry & Quotation. Menu (FACT): item `Quotation` (`v2:quotation`, id 113) directly under the group "Inquiry & Quotation" (not a group of its own); group Report (id 1089): Open Quotations (`v2:quotation-outstanding`), Follow-up Quotation (`v2:quotation-followup`), Win-Rate Sales (`v2:sales-win-rate`; the same item also exists in Sales > CRM > Report, id 1194). Item "Inquiry Note" (id 931) is inactive.
- **Purpose**: Sales picks priced inquiries (costing PUBLISHED) of one customer, builds the offer, publishes it (PUBLISHED), follows up (follow-up), then WIN (CO is born) or LOST.
- **Hard rules**: Quotation MUST be integrated (A.1); 1 Quotation = 1 CO (A.4/A.10, unique index in the DB); CO may be direct (INSTANT) without Quotation (A.2); price only for those entitled (A.8-A.9).
- **Frozen zone**: Quotation is part of the vital flow (`docs/claude_vital_flows.md`).

## Relations
- **source**: Inquiry with costing PUBLISHED (card `../product-costing/11_card_procost-interface.md`), customer, Sales PIC.
- **used_by**: Customer Order (WIN; the CO card belongs to another group), Master Product (born at WIN), Win-Rate, Follow-up (`crm_activity`).
- **flags**: Quotation has no `stat_confirm_*`; its status is `doc_status` DRAFT/PUBLISHED/CANCELLED, WON/LOST are derived from the inquiry status.

## Concept cards in this sub-module
| Card | Content |
|---|---|
| `10_card_quotation.md` | Quotation (table, status, must be integrated, 1 Quotation = 1 CO, price visibility; the confidential price part is after the johiwan-only marker) |
| `../master-data/10_card_customer.md`, `../master-data/11_card_sales-pic.md` | Customer and Sales PIC used by the Quotation header |

## Engines in this sub-module
No engine belongs to this sub-module. Line builder: `QuotationBuilder` (an ordinary service; registration in `EngineRegistry` NOT checked). Downstream WIN: `../order/20_engine_co-order-builder.md`, card `../order/10_card_customer-order.md` and `../order/12_card_document-status.md` (another group).

## Cross-card findings (summary)
- The server does not enforce "must be integrated": a freeform line (FREEFORM) can create a quotation without an inquiry; 5 PUBLISHED quotations in the data have no line at all.
- Quotation does not use the price filter `canViewSalesPrice` (only the SALES module gate).
- Outdated source label `quo_activity` (the real table is `crm_activity`).

## Diagram
Not yet made. Text flow: Inquiry (costing PUBLISHED) -> `QuotationBuilder::createFromInquiries` -> DRAFT -> Confirm (PUBLISHED) -> WIN: `CoOrderBuilder::findOrCreateFromQuotation` -> CO + Master Product; or LOST/Cancel.

## Existing user guides (UG)
`docs/guides/sales/04_quotation_order_cpdel.md` (FROM DOCUMENT, not opened this session).

