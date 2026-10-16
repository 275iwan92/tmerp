# Sub-module: CRM (Customer 360, Activities, Complaint)

> Written 2026-10-16 (group A). This page only summarizes and links; table/column facts are in the cards.

- **Module**: Sales > CRM (`SALES_CRMGRP`). Menu (FACT, `sys_app_menu` mockup, active): Customer 360 (`v2:crm-customer`), Activities & Follow-up (`v2:crm-activity`), Complaint & Return (`v2:crm-complaint`), group Master Data (Master Customer `v2:md-customer`, Sales Person `v2:md-sales-person`), group Report (Win-Rate Sales `v2:sales-win-rate`). The quality answer to a complaint is in the Production menu: Complaint Review (QA) (`v2:qc-complaint-review`).
- **Purpose**: build the relationship with the customer: summarize history (Customer 360, read-only), record contacts and follow-ups, and receive complaints/returns whose quality is answered by QA.
- **Frozen zone**: no CRM-specific frozen zone besides the document flow (the rule freezes all flows).
- **Rules**: A.21b (Sales is one big group; `crm_activity` per customer; complaints: information to Sales, quality to QA; Customer 360 is read-only, FROM DOCUMENT short index); A.9 (rupiah values only for holders of the price right); A.12 (Confirmed/Cancelled/Closed cycle, fulfilled by Complaint).

## Relations
- **source**: Master Customer and contacts, Quotation, CO (new and archive), DO/Surat Jalan (new and archive), Production (QA answer).
- **used_by**: Sales (daily work), QA (complaint desk), Win-Rate Sales (NOT checked whether it uses activities).
- **flags**: Complaint uses `stat_confirm/_close/_cancel`; Activity has no document status (`next_done`); Customer 360 has no table.

## Cards and engines in this sub-module
| Type | File | Content |
|---|---|---|
| Concept | `10_card_crm-activity.md` | `crm_activity`, follow-up, due-date color, edit right |
| Concept | `11_card_crm-complaint.md` | `crm_complaint`, `crm_complaint_item`, QA, Confirm/Close/Cancel |
| Concept | `12_card_customer-360.md` | read-only summary, segments Active/Sleeping/Prospect/No order |
| ENGINE | None | CRM has no engine, schedule, or background job (searched: `routes/console.php`, `app/Services`, registry; zero) |

## Diagram
Not needed (no engine).

## Existing user guides (UG)
There is no CRM file yet in `docs/guides/sales/` (folder content: 00 to 04, no CRM). Noted; not created in this task.

## Cross-card findings (short; details in the cards)
Table `quo_activity` no longer exists but class `QuoActivity` and comments still mention it; Cancel Complaint has no `cancelled_at/by`; the Customer 360 segment counts archive DRAFT/UNKNOWN as orders and REJECTED CO is not excluded; Customer 360 tabs are silently cut at 100/200 rows.
