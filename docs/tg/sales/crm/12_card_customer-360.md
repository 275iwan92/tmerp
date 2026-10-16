# Concept card: Customer 360

> Written 2026-10-16 (group A). FACT = mockup DB (`ddt*` through `tm2*`, `--env=mockup`) and code with line numbers (`app/Support/V2/Data/Crm.php`, 244 lines, read in full). `CrmTest::test_customer_360_profile_reads_one_customer` passed (part 5 of the `CrmTest` tests, 52 assertions).

- **Document type**: concept card (summary screen, not a document; has no table of its own). **Module/sub-module**: Sales > CRM > Customer 360 (`CRM_CUST360`, `v2:crm-customer`, FACT `sys_app_menu`).
- **Canonical name**: Customer 360 = class `App\Support\V2\Data\Crm` (list `rows()`, single-customer page `profile()` / `card()`), read-only.
- **Synonyms**: customer profile (profil customer), 360 view, relationship view, customer segment (Active / Sleeping / Prospect / No order).
- **NOT the same as**: Master Customer (`cust_customer`, where data is changed), CRM Activity, CRM Complaint. Customer 360 only summarizes (rule A.21b: "Customer 360 hanya membaca", i.e. read-only).

## Facts
| Item | Content | Evidence |
|---|---|---|
| Data source | Reads: `tm2mst.cust_customer` (+contacts, branches, terms), `tm2sdi.quo_quotation` + items + inquiry, `co_order` (new), `co_order_legacy` + `_detail` (archive), `do_delivery`, `co_delivery_legacy`, `crm_activity`, `crm_complaint`. Cross-DB through separate queries matched in PHP. No table of its own. | FACT, `Crm.php:17-19,38-60,97-149` |
| Segment | From the last order date (maximum of non-CANCELLED archive and new CO with `stat_cancel=0`): empty -> `Prospect` if `is_prospect`, otherwise `No order`; older than `CRM_SLEEPING_DAYS` (180 days) -> `Sleeping`; otherwise `Active`. Result of computing with the same rule over 882 active customers in the mockup today: Active 165, Sleeping 545, No order 172, Prospect 0 (no customer has `is_prospect=1`). | FACT, `Crm.php:32-35,75-82`; SQL query imitating the rule |
| List | One row per active customer: orders of the last 12 months, open quotations (PUBLISHED not yet WIN/LOST/cancelled), open CO (DRAFT/SUBMITTED/CONFIRMED), last contact, next follow-up and its color, open complaints (Confirmed not yet Closed/Cancelled). Order: overdue/today/soon follow-up first, then those with an open complaint, then most orders in 12 months. | FACT, `Crm.php:38-73,84-89` |
| Customer page | Tabs Overview, Activities, Quotations, Orders, Deliveries, Complaints, Contacts; key figures First/Last Order, Orders (all time), Order Value, Last Contact; 24-month chart; 15 most often ordered products (archive + new). Buttons: + Activity, + Complaint, Master Customer, Back. | FACT, `Crm.php:151-243` |
| List limit | Tabs are loaded with a limit: quotation 100, new order 100, archive order 200, DO 100, archive surat jalan 100; summary figures are computed separately (no limit). | FACT, `Crm.php:103-107` |
| Price | Rupiah values only if `Auth::user()->canViewSalesPrice()` (A.9): `Order Value`, total per order/quotation row, value per month in the chart; otherwise `null` and the column is hidden. Filtered on the server in `profile()`, not in the view. | FACT, `Crm.php:27-30,117-128,138-139,169,205-207` |
| Supporting data | Constants `CRM_SLEEPING_DAYS` = 180, `CRM_FOLLOWUP_SOON_DAYS` = 2. Old order archive 20,669 rows (sum of the four groups) (CONFIRMED 18,079, DRAFT 2,354, UNKNOWN 120, CANCELLED 116). | FACT, `sys_constant`; query |

## Relations
- **source**: Master Customer; Inquiry/Quotation; CO (new and archive); DO and Surat Jalan (new and archive); Activity; Complaint. See each card.
- **used_by**: Sales users (screen only); shortcut buttons to Activity, Complaint, Master Customer.
- **flows**: writes no data. No engine; no schedule; no other caller in `routes/console.php`.
- **frozen_zone**: none (read-only); the document flow of its sources is frozen.

## Findings (recorded, not fixed)
1. **Archive DRAFT and UNKNOWN are counted as orders** for the segment and "Last Order": only CANCELLED is excluded (`Crm.php:42,110,156`). The archive holds 2,354 DRAFT and 120 UNKNOWN; this can make a customer look Active although there is no valid order. Impact on the segment not computed. FACT (code+data).
2. **REJECTED CO counts as an order**: the filter `stat_cancel = 0` (`Crm.php:44,112,158`) does not exclude `doc_status=REJECTED` (CO card finding 2); conversely CO2600021, which is CONFIRMED but has `stat_cancel=1`, is treated as cancelled. FACT.
3. **Segment Prospect unreachable in the data**: 0 customers with `is_prospect=1` in the mockup, so the Prospect branch has never been tested with data. FACT.
4. **List silently cut** for customers with many documents (limit 100/200) without a "more available" marker. FROM CODE (lines 103-107), not tested on screen.
5. **Read load**: `rows()` reads all active customers (882) and all open follow-ups without a limit at every open. Load time not measured.
6. The archive date `co_date` is treated as text (`substr(…, 0, 10)`), the archive column type was not checked this session. FROM CODE.

## Already exists? Searched where
Searched: `Crm`, `CrmActivities`, `CrmComplaints`, `x-mp::sales.customer-card`, menu Sales > CRM, routes `v2.crm.*`. Result: ALREADY EXISTS; no new table needed (the rule "Customer 360 hanya membaca", i.e. read-only, is met: no writes in `Crm.php`).
