# Concept card: CRM Complaint and Sales Return

> Written 2026-10-16 (group A). FACT = mockup DB (`ddt*` through `tm2*`, `--env=mockup`) and code with line numbers, seen this session. Test `CrmTest` (5 passed, 52 assertions; `--env=mockup`) covers the complaint flow; the number of `crm_*` rows is the same before and after.

- **Document type**: concept card (document with a status). **Module/sub-module**: Sales > CRM > Complaint & Return (`CRM_COMPLAINT`, `v2:crm-complaint`). Quality answer in Production: "Complaint Review (QA)" (`v2:qc-complaint-review`). FACT, `sys_app_menu`.
- **Canonical name**: CRM Complaint = `tm2sdi.crm_complaint` (header, number `CCyyNNNNN`) + `crm_complaint_item` (the complained products).
- **Synonyms**: complaint (komplain), sales return (retur penjualan), Sales Return, customer grievance (keluhan customer), claim, `complaint_no`.
- **NOT the same as**: `qc_complaint_review` (QA answer, table in `tm2prd`), internal Non-Conformance, CRM Activity (contact record, including topic COMPLAINT).

## Facts
| Item | Content | Evidence |
|---|---|---|
| Header | `crm_complaint`: 4 rows. PK `id_complaint`; `complaint_no` UNIQUE (NULL while Draft), `complaint_date`, `complaint_type` (COMPLAINT / RETURN), `customer_id` (required), `contact_id`, `contact_person`, `received_via`, `category`, `needs_qa`, `co_order_id` (FK to `co_order`), `ref_doc_no`, `description`, `requested_action`, `resolution`, `resolution_note`, `stat_confirm`+`confirmed_at/by`, `stat_close`+`closed_at/by`, `stat_cancel`+`cancel_reason`. | FACT, `show columns`; `key_column_usage` |
| Lines | `crm_complaint_item`: 4 rows (FK CASCADE from header). `product_id`, `product_name`, `qty_complaint`, `qty_return`, `qty_unit`, `defect_note`. | FACT, `show columns`; `CrmComplaints.php:198-203` |
| Values | Category: QUALITY, QUANTITY, DELIVERY, WRONG_ITEM, DOCUMENT, SERVICE, OTHER. Requested action: REPLACE, REWORK, PRICE_CUT, REFUND, EXPLANATION. Resolution: REPLACED, REWORKED, PRICE_CUT, REFUNDED, EXPLAINED, NOT_ACCEPTED. | FACT, `CrmComplaint.php:17-25` |
| Status | `status()`: CANCELLED if `stat_cancel`, CLOSED if `stat_close`, CONFIRMED if `stat_confirm`, otherwise DRAFT. Data: Draft 1, Confirmed 2, Closed 1, Cancelled 0. | FACT, `CrmComplaint.php:59-62`; query |
| Confirm | Only from Draft. Conditions: description filled; at least one product; each product `qty_complaint > 0`; for RETURN the quantity `qty_return` > 0; `qty_return` does not exceed `qty_complaint`. Number `CC` + 2-digit year + 5-digit sequence is born at Confirm. | FACT, `CrmComplaints.php:136-172`; `CrmComplaint.php:70-76` |
| QA | `needs_qa` = 1 if category is QUALITY (forced) or ticked. A Confirmed complaint with `needs_qa` appears on the QA desk; the QA answer = `qc_complaint_review` (1 row per complaint, UNIQUE `complaint_id`, `verdict`, `root_cause`, `corrective_action`, `preventive_action`, `disposition`, `stat_confirm`). Stage: NONE / WAITING / ANSWERED. | FACT, `CrmComplaints.php:96,225-240`; `CrmComplaint.php:64-68`; `show columns qc_complaint_review` |
| Close | Only from Confirmed; rejected while the QA stage is still WAITING; `resolution` required. | FACT, `CrmComplaints.php:176-187` |
| Cancel | Only from Confirmed, reason required (max 255 characters). A Draft is not cancelled but permanently deleted (`delete`, Draft only; its lines are deleted too). | FACT, `CrmComplaints.php:188-203` |
| Tests | `test_complaint_goes_to_qa_and_is_closed_by_sales_after_the_answer`, `test_complaint_without_qa_is_cancelled_with_a_reason_and_draft_can_be_deleted` passed. | FACT, `CrmTest` run |
| Data | 4 complaints (RETURN 1, COMPLAINT 3); `co_order_id` empty in all; `ref_doc_no` holds numeric numbers of the old system (e.g. `10262643`). QA: 1 row `qc_complaint_review`, Confirmed. | FACT, query today |
| Price | Holds no price/HPP. | FACT, `show columns` |

## Relations
- **source**: Sales input; customer and product master (cross-DB, separate query); new CO (`co_order_id`, only non-cancelled CO, latest 200: `CrmComplaints.php:61-63`) or old document number (`ref_doc_no`).
- **used_by**: QA desk (Production > Complaint Review), Customer 360 (tab Complaints, open complaint count), Customer Invoice (returns; NOT checked).
- **flows**: Draft -> Confirm (number is born; if `needs_qa` it appears at QA) -> QA answers -> Sales Close with a resolution, or Cancel with a reason.
- **frozen_zone**: document flow (the rule freezes all flows). Rule A.21b: complaint information to Sales, quality to QA (FROM DOCUMENT, short index).

## Findings (recorded, not fixed)
1. **No Cancel time/actor**: there are `confirmed_at/by` and `closed_at/by` but no `cancelled_at/by`; the log uses `updated_at` and `updated_by` for the "Cancelled" row (`CrmComplaints.php:215`), so the cancel time shifts if the row is changed again. FACT.
2. **Link to new CO is empty**: all complaints refer to a numeric `ref_doc_no` of the old system without FK; the new CO is not linked (0 of 4). FACT.
3. **Error messages of `act()` partly bypass `t()`**: only the Confirm error list is wrapped in `t()` (line 168); status messages and Close/Cancel messages return English strings directly (lines 165, 176, 179-192). FACT (code); impact on users of another language not tested.
4. **`stat_confirm` has a double meaning**: `crm_complaint.stat_confirm` (Sales) and `qc_complaint_review.stat_confirm` (QA) have the same name in two tables; `qaStage()` tells them apart through the relation. FACT.
5. **Customer Invoice reference for returns**: NOT checked whether Sales Return changes `qty_invoiced`/the invoice.

## Already exists? Searched where
Searched: `crm_complaint*`, `qc_complaint_review`, `CrmComplaint*`, `CrmComplaints`, menu Sales > CRM and Production, `doc_state_log`. Result: ALREADY EXISTS; no new table needed. The Confirmed/Cancelled/Closed document cycle is met (A.12) in this card.
