# Concept card: Sales document status and confirmation flags

> Written 2026-10-16 (group A). Cross-sub-module in Sales; placed in the Order folder because it also maps CO/DO status. FACT = mockup DB (`ddt*` via `tm2*`, `--env=mockup`) and code with line numbers. The Inquiry/Quotation cards by other authors remain the main reference for their tables; here only status.

- **Document type**: concept card. **Module/sub-module**: Sales (Inquiry & Quotation, Order, Delivery, CRM).
- **Canonical name**: Document status = the status/flag columns per document table; **Confirmation flags (A.20)** = `iqp_inquiry.stat_confirm_sales / stat_confirm_prodev / stat_confirm_procost`.
- **Synonyms**: stat_confirm, doc_status, sub_state, Inquiry stage, Confirmed/Cancelled/Closed, `stat_cancel`, WON/LOST.
- **NOT the same as**: the approval log (`flow_log`), the change trail named `doc_state_log` (does not exist, see Finding 1).

## Facts
| Document | Status column | Values and distribution in mockup | Evidence |
|---|---|---|---|
| Inquiry (`iqp_inquiry`, 23 rows: NEW 21, REVISE 2, RECALC 0) | `stat_confirm_sales`, `stat_confirm_prodev`, `stat_confirm_procost` (+ `confirm_*_by/_at`), `stat_cancel`, `sub_state` (DRAFT, SUBMITTED, CONVERTED, LOST, CANCELLED) | CONVERTED 13 (all three flags 1), DRAFT 2, SUBMITTED 7, LOST 1; the flag combinations are never `procost=1` without `prodev=1`, and `procost=1` always has a published costing (13 published costings = 13 CONVERTED) | FACT, `show columns`, query; `InquiryStage.php:12-16` |
| Quotation (`quo_quotation`, 20) | `doc_status` (DRAFT, PUBLISHED), `stat_cancel`; WON/LOST DERIVED, not stored | PUBLISHED 18, DRAFT 2, cancelled 0. `outcome()`: WON if any inquiry item is `CONVERTED`; LOST if all items are `LOST` | FACT, `QuoQuotation.php:71-80`; `Quotation.php:27-34` |
| Customer Order (`co_order`, 25) | `doc_status` (DRAFT, SUBMITTED, CONFIRMED, REJECTED, CANCELLED), `stat_cancel`, `cancel_reason` | CONFIRMED 24 (1 of them `stat_cancel=1`), DRAFT 1 | FACT, `CustomerOrder.php:25`; query |
| Delivery schedule (`co_order_delivery`, 29) | `status` PLANNED / SHIPPED / CANCELLED; `posted_at` | PLANNED 29, posted 25 | FACT, query |
| Delivery Order (`do_delivery`, 3) | `status` DRAFT, CONFIRMED, SHIPPED, CANCELLED | DRAFT 1, CONFIRMED 2 | FACT, `DeliveryOrder.php:21`; query |
| DO lines (`do_line`, 0) | `status` PLANNED, SHIPPED, CANCELLED | empty | FACT, `DispatchService.php` |
| Complaint (`crm_complaint`, 4) | `stat_confirm`, `stat_close`, `stat_cancel` (+ `confirmed_at/by`, `closed_at/by`) | Draft 1, Confirmed 2, Closed 1 | FACT, `CrmComplaint.php:59-62` |
| POD, Activity | no document status (POD: `result`, `promise_kept`; Activity: `next_done`) | - | FACT, `show columns` |

**How the Inquiry flags are set (A.20)**: `stat_confirm_sales` is set by the Inquiry screen itself (`InquiryWriter.php:67`, `InquiryDerivedWriter.php:93`; actor/time recorded in `IqpInquiry.php:96-104`). `stat_confirm_prodev` is computed by `InquiryStage::syncProdev`: it goes up to 1 when ALL non-assembly parts have a latest layout with status CONFIRMED; it goes down to 0 only while no costing exists yet (`:37-50`). `stat_confirm_procost` is computed by `syncProcost` from `iqp_costing.published_flag` (`:59-69`). Trigger: the `saved` event on `ProdLayoutGroup` and `IqpCosting` (`:99-105`). Only types NEW and REVISE pass through ProDev (`PRODEV_TYPES`, line 20); RECALC goes straight to ProCost. The grid of each section = a type filter AND a flag filter on the same table (comment on line 16).

**Confirmed / Cancelled / Closed cycle (A.12)**: not uniform. See Finding 2.

## Relations
- **source**: the screen of each document; `InquiryStage` for the two downstream flags; `FlowEngineService` (`flow_log`, tm2mst) for CO/PR/PO approval; `CoOrderBuilder` for CO transitions.
- **used_by**: ProDev/ProCost grids (filters), Customer 360 (counts open quotations/COs), Material Planning (CO Confirmed = firm), board widgets.
- **flows**: Inquiry (Sales -> ProDev -> ProCost) -> Quotation PUBLISHED -> WIN (inquiry CONVERTED) -> CO -> schedule -> DO -> POD. Transition engine details: card `20_engine_co-order-builder.md`.
- **frozen_zone**: Inquiry/ProDev/ProCost and all document flows.

## Findings (recorded, not fixed)
1. **`doc_state_log` does not exist.** CLAUDE.md item 5 ("transisi di `doc_state_log`") and comments in old migrations mention that table; a search of `information_schema` across the whole server (names containing `doc_state`/`state_log`) found only `iqp_costing_state_log` (0 rows, columns `from_state`, `to_state`, `changed_at/by`). The real traces: `flow_log` (approval), `co_delivery_negotiation` (CPDEL), `confirmed_at/closed_at` (Complaint), `confirm_*_by/_at` (Inquiry). There is no general transition log. FACT.
2. **Three status styles** side by side: (a) separate `stat_confirm/_close/_cancel` (Complaint, partly Inquiry); (b) one column `doc_status`/`status` + `stat_cancel` (Quotation, CO); (c) only `status` (DO, schedule). There is no `stat_close` in Quotation, CO, DO. Rule A.12 (every module has clear Confirmed/Cancelled/Closed) is complete only in Complaint. FACT.
3. **WON/LOST is stored in Inquiry, not Quotation**: the Quotation status depends on the `sub_state` of its child inquiries (`outcome()`); the rule: WON if ONE item is `CONVERTED`; LOST only if ALL items are `LOST`; otherwise `null` (still open). Users: `Quotation.php`, `CustomerOrder.php`, `Crm.php`. No test calls `outcome()` (grep `tests/`: zero). FACT.
4. **The lowering of `stat_confirm_prodev` is restricted**: the flag goes down only if no `iqp_costing` row exists yet; afterwards it stays 1 even if the layout is changed (`InquiryStage.php:47`). This follows the code comment, not the rule document (A.20 says "Pro-Dev Confirm part demi part"). FACT.
5. **Naming**: CO uses `doc_status`, which conflicts with the CLAUDE.md naming item (`stat_confirm/stat_cancel/stat_close`); the same for DO. The only `stat_*` flag in `co_*`/`do_*` is CO `stat_cancel`. FACT.
6. **Data**: CO2600021 `CONFIRMED` + `stat_cancel=1` (CO card finding 1); DO CONFIRMED without lines (DO card finding 1). FACT.

## Already exists? Searched where, result
Searched: columns `stat_*`, `doc_status`, `sub_state`, `status` in `ddtsdi`, `ddtmst`, `ddtsys`; tables `%state_log%`/`%doc_state%` across the whole server; `InquiryStage`, `IqpInquiry`, `QuoQuotation`, `CoOrder`; CLAUDE.md and `docs/ringkas/globaluserrules.md` (A.12, A.20). Result: the A.20 flags ALREADY EXIST and are consistent with the data; the `doc_state_log` table does NOT exist; no new column is proposed.

> Re-checked 2026-10-17: `QuoQuotation::totals()` now reads the PPN rate with `TaxRate::ppn(issued_date)` (table `fin_tax_rate`, screen Master Data > Tax Rate); the rate for current documents is unchanged (11).
- Re-checked 2026-10-18 after `app/Support/V2/Data/Crm.php` line 216 changed (the Customer 360 button "Master Customer" now opens the v2 form `md-customer-form?id=` instead of the v1 page); no line was added or removed, so every cited line number is unchanged, content still true.
- Re-checked 2026-10-20 after the Quotation and Customer Order entry rules moved from the v1 components to services (rule D.25, T10): `routes/web.php` replaced the v1 routes `/quotation/new`, `/quotation/{quotation}/edit`, `/quotation/{quotation}/print`, `/co/{order}/edit` and `/co/{order}/print` by redirects on the same lines (no line added or removed), `routes/v2.php` changed only two comment lines, `app/Support/V2/Screens.php` changed the description text of the `quotation` and `customer-order` rows on the same lines, and `app/Support/V2/Data/Quotation.php` and `CustomerOrder.php` now call `QuotationEntryService` and `CoEntryService` instead of the v1 Livewire components (the `component()` helper is gone; cards that cite lines of those two files were remapped from the diff). The v1 pages `pages::quotation.entry`, `pages::co.entry` and the two v1 prints are archived in `tmerp_unused/`. Every other cited line number is unchanged and this card's content is still true.
