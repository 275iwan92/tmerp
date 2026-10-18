# Concept card: Quotation (offer)

> Group B, 2026-10-16, awaiting Jo's approval. FACT = seen directly (mockup DB `ddtsdi`, code with lines). Price figures only in the section after the johiwan-only marker (A.8-A.9).

- **Document type**: concept card. **Module/sub-module**: Sales > Inquiry & Quotation > Quotation (menu item `v2:quotation`; reports `Open Quotations`, `Follow-up Quotation`, `Win-Rate Sales` in group Report).
- **Canonical name**: Quotation = `tm2sdi.quo_quotation` + `quo_quotation_item` (+ `quo_quotation_cc`).
- **Synonyms**: offer (penawaran), QUO number, Sales Quotation, PUBLISHED/WON/LOST.
- **NOT the same as**: Purchase Quotation (Procurement module, screen `purchase-quotation`); Inquiry Note (print of price per tier from one Inquiry); Customer Order (`co_order`).

## Facts
| Item | Content | Evidence |
|---|---|---|
| Table content (mockup) | `quo_quotation` 20 rows (DRAFT 2, PUBLISHED 18); `quo_quotation_item` 24 (all `source_type='INQUIRY_TIER'`, all have `inquiry_id` and `tier_id`); `quo_quotation_cc` 0. | FACT, query 2026-10-16 |
| Header columns | `quotation_no` (unique), `customer_id`, `attn_contact`, `sales_pic` (text), `revision_no`, `issued_date`, `valid_until`, `deadline_date`, `payment_term`, `tax_scheme` (EXCL), `currency_code` (IDR), `notes`, `doc_status`, `stat_cancel`, `cancel_reason`, `issued_by`. There is NO `stat_confirm`/`stat_close`. | FACT, `show columns` |
| Line columns | `line_seq`, `source_type`, `inquiry_id`, `tier_id`, `description`, `spec_note`, `qty`, `qty_unit`, `unit_price`, `discount_pct`, `subtotal`. | FACT, `show columns` |
| Status | `doc_status` DRAFT -> PUBLISHED (button Confirm) -> CANCELLED. WON/LOST are NOT stored on the quotation; they are derived from `iqp_inquiry.sub_state` of its lines (CONVERTED = WON; all LOST = LOST). | FACT, `Data/Quotation.php:34-42`, `QuoQuotation.php:71-82` (`outcome()`) |
| Numbering | `QUO` + 2-digit year + 5-digit sequence, counter per year. | FACT, `QuoQuotation.php:103-114` (`nextQuotationNo`) |
| Inquiries that may be offered | `QuotationBuilder::availableInquiries`: costing `published_flag=true`, `customer_id` not NULL, `sub_state` not CANCELLED/LOST/CONVERTED, not yet in another quotation that is not CANCELLED. One quotation = one customer. | FACT, `QuotationBuilder.php:46-57,125-134` |
| Must be integrated (A.1) | Normal path: `INQUIRY_TIER` lines per tier from PUBLISHED costing (+ tooling lines `INQUIRY_TOOLING` per type). Data: 24/24 lines linked to PUBLISHED costing; 0 violations. | FACT, `QuotationBuilder.php:138-190`; query |
| 1 Quotation = 1 CO (A.10) | Unique index `co_order_quotation_id_unique` on `co_order.quotation_id` (Non_unique=0); WIN uses `findOrCreateFromQuotation` (idempotent: if a CO already exists it is returned). Data: 13 INTEGRATED CO, no quotation with >1 CO; 12 INSTANT CO without quotation. | FACT, `show index`; `CoOrderBuilder.php:27-40`; query |
| WIN | The CO is born from all quotation lines (snapshot of price, qty, tolerance from the costing tier), the inquiry `sub_state` becomes CONVERTED and `co_id` is filled, then `ProductMasterSync::fromInquiry` creates the Master Product. | FACT, `CoOrderBuilder.php:42-112 (ProductMasterSync pada baris 105-109)` |
| Routes | `v2.quotation.create/save/add-inquiry/delete/cancel/lost/win/activity`; all in the group `middleware('module:SALES')`. | FACT, `routes/v2.php:376-459` |
| Follow-up | Follow-up activities are stored in `crm_activity` (`QuoActivity` inherits `CrmActivity`); 15 rows in the whole dummy (not only quotation). | FACT, `QuoActivity.php:5-7`; query |
| Price visibility (A.8-A.9) | The server-side price filter (`SecUser::canViewSalesPrice`: group SALES_STAFF, SALES_SPV, MGR_SALES, MARKETING, COSTING_STAFF, OPS_MGR, DIREKSI, or admin, or management level >= 4) is used by Customer Order, Delivery Order, CRM, and the CO Report grid. NOT found in use in Quotation or Inquiry. | FACT, `SecUser.php:289-294`; `grep canViewSalesPrice` (CoReportGrid, CustomerOrderPrint, DeliveryOrderPrint, Crm, Board, print-doc) |
| Quotation grid content | Column `total` (grand total) is sent to every user who may open the screen; the child rows (Expand) are deliberately "tanpa harga" (without price). | FACT, `Data/Quotation.php:75-78` |

## Relations
- **source**: Inquiry (costing PUBLISHED) through `QuotationBuilder`; price from `iqp_costing_price_tier` (declassified to Sales at publish); header from the customer (`payment_term`, `contact_name`) and the inquiry's `pic_sales`.
- **used_by**: Customer Order (WIN), Win-Rate Sales, Open Quotations, Follow-up, Quotation print (`QuotationPrint`), Master Product (born at WIN).
- **flows**: quotation lines are SNAPSHOTTED to `co_order_item` (price is not live); a costing change after publish does not propagate. `unit_price` may be edited before PUBLISHED (v1 `updateItem`), after that it is locked (`editable`).
- **frozen_zone**: Quotation (vital flow); changing it needs Jo's permission.

## Findings (recorded, not fixed)
1. **The rule "Quotation MUST be integrated" is not enforced by the server.** `publish()` only checks that the document is editable and has lines (`entry.blade.php:371-379`); `Quotation::confirm` only checks that lines exist (`Data/Quotation.php:246-251`). The button "Baris Bebas" (Freeform Line) (`addFreeform`, `source_type='FREEFORM'`, typed price) creates a line without an inquiry; a quotation holding only freeform lines can be Confirmed and WON (CO maps FREEFORM to `line_kind='SERVICE'`, `CoOrderBuilder.php:76`). There is no FREEFORM line in the data yet, so this is proven from code only. Needs Jo's decision: freeform lines only for accompanying services, or restricted?
2. **7 quotations without a single line**, including 5 that are PUBLISHED (QUO2600005, 06, 07, 27, 28) although `publish()` rejects documents without lines: meaning they were written outside the screen path (seeder). An empty PUBLISHED quotation can still be WON according to `Quotation::win` (checks status only, `Data/Quotation.php:280-285`); no CO exists from those 7 quotations.
3. **Price is not filtered in Quotation.** See Fact "Price visibility": the gate is only the SALES module (`routes/v2.php:377`); whether that is enough under A.9 is NOT decided yet (A.9 names Sales, Marketing, Product Costing, Operational Manager and above). A SALES module user outside the price-group list will see `total` and line prices.
4. **Quotation `sales_pic` holds names that are not in `sales_person`** for 13 of 20 rows ("Firman"); see `../master-data/11_card_sales-pic.md`.
5. **Outdated source label**: the screen list names `quo_activity` as the follow-up source (`app/Support/V2/Screens.php:65`), although the table is `crm_activity`; there is no table `quo_activity` in the database.
6. **WON/LOST are derived from the inquiry status**, not from the quotation: a quotation with 2 inquiries, one CONVERTED and one LOST, becomes WON; `Quotation::lost` only marks inquiries that are not yet terminal. Read from code only; the multi-inquiry case is not counted in the data.

## Already exists? Searched where, result
Searched: tables `quo_*` and `%quotation%` in all `ddt*`; `grep` `Quotation` in `app/`, `routes/`, `resources/views/v2`, `resources/views/pages/quotation`; marker columns "direct"/"manual" on `quo_quotation`. Result: the concept ALREADY EXISTS; no column distinguishes integrated from freeform quotations other than `quo_quotation_item.source_type`.

<!-- johiwan-only -->

## Price flow into Quotation (confidential; figures from the mockup DB 2026-10-16)
- `quo_quotation_item.unit_price` = `iqp_costing_price_tier.price_per_pcs` (same tier, column `tier_id`) for **24 of 24** lines: none were edited in the data. Formula: `QuotationBuilder.php:175` `'unit_price' => $row->price_excl`, and `TierPricingResolver::rows` fills `price_excl = price_per_pcs`, `price_incl = c_after_ppn_pc` only if costing `published_flag` (`TierPricingResolver.php:13-27`; before publish all price columns are NULL).
- Example (first 3 lines): `QUO2600021`/`IQ99KSP001`, qty 30,000: 2,491.7647; `QUO2600022`/`IQ99MC0001`, qty 20,000: 1,395.2941; `QUO2600023`/`IQ99MC0002`, qty 15,000: 2,525.8824. `c_after_ppn_pc` respectively 2,765.8588; 1,548.7765; 2,803.7294 (price + PPN, not stored in the quotation line; PPN is recomputed from `TaxRate::ppn()` through `QuoQuotation::totals()` for scheme EXCL).
- Total: scheme `EXCL`: `ppn = round(dpp x ppn%/100, 2)`, `grand = dpp + ppn`; scheme `INCL`: `dpp = round(subtotal/(1+ppn%/100), 2)`. `QuoQuotation.php:84-101` (`totals()`).
- HPP and margin are not read in this card (there is no HPP/margin column in `quo_*`).


> Re-checked 2026-10-17: `QuoQuotation::totals()` now reads the PPN rate with `TaxRate::ppn(issued_date)` (table `fin_tax_rate`, screen Master Data > Tax Rate); the rate for current documents is unchanged (11).

- Re-checked 2026-10-18 after the duplicate-module cleanup (v1 Scheduling Board, Rule Library and Master Crew removed): line numbers of `SecUser.php` re-read (REMARK block added, `isProdManagerOrAbove` removed): `canViewSalesPrice` is now 289-294; content unchanged.
- Re-checked 2026-10-18 after the SOP register merge (sop_document folded into mnt_document): citations of `app/Models/Sys/SecUser.php` (comment only, lines unchanged) still correct.

- Re-checked 2026-10-18 after `app/Support/V2/Screens.php` gained a `Master` group in module FINACC (screen `acc-coa-form`, inserted after line 95); every line cited by this card is before line 96 and unchanged, content still true.
- Re-checked 2026-10-18 after `app/Support/V2/Screens.php` gained the screen `acc-journal-form` in the FINACC `Master` group (inserted after line 95); every line cited by this card is before line 96 and unchanged, content still true.
- Re-checked 2026-10-18 after `app/Support/V2/Screens.php` gained the screen `acc-cash-bank-form` in the FINACC `Master` group (inserted after line 95); every line cited by this card is before line 96 and unchanged, content still true.
- Re-checked 2026-10-18 after `app/Support/V2/Screens.php` gained the screen `acc-payables-form` in the FINACC `Master` group (inserted after line 95); every line cited by this card is before line 96 and unchanged, content still true.
- Re-checked 2026-10-18 after `app/Support/V2/Screens.php` gained the screens `acc-fixed-asset-form` and `acc-depreciation-run` in the FINACC `Master` group (inserted after line 95); every line cited by this card is before line 96 and unchanged, content still true.
- Re-checked 2026-10-18 after `app/Support/V2/Screens.php` gained the screen `acc-amortization-form` in the FINACC `Master` group (inserted after line 95); every line cited by this card is before line 96 and unchanged, content still true.
- Re-checked 2026-10-18 after `app/Support/V2/Screens.php` gained the module `MDATA` with the screen `md-customer-form` (inserted after line 95, before the Developer module) and the source label on line 17 was reworded; every line cited by this card is before line 96 and unchanged, content still true.
- Re-checked 2026-10-18 after `app/Support/V2/Screens.php` gained the screen `md-supplier-form` in the module `MDATA` (inserted after line 95); every line cited by this card is before line 96 and unchanged, content still true.
