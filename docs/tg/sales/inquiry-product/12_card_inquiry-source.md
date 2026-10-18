# Concept card: Inquiry source (origin of the request)

> Group B, 2026-10-16. FACT = seen directly (mockup DB `ddtsdi`, code with lines). Small card; Inquiry detail is in `11_card_inquiry-and-part.md`.

- **Module/sub-module**: Sales > Inquiry & Quotation > Inquiry Product.
- **Canonical name**: Source = `tm2sdi.iqp_inquiry.source` (screen label "Source").
- **Synonyms**: inquiry origin, incoming channel, request source, lead source.
- **NOT the same as**: `material_source` (material origin, card `13_card_material-source.md`); `source` on BOM/BOP rows (`iqp_part_material.source` ENGINE/MANUAL, `iqp_part_process.source`); the CO "Price Source".

## Facts
| Item | Content | Evidence |
|---|---|---|
| Column | `iqp_inquiry.source` varchar(50), may be NULL, no FK and no master table. | FACT, `show columns` |
| Choices on the screen | Required select, fixed values `EMAIL, WA, PHONE, VISIT, MARKETPLACE`, default `EMAIL`. The list is written directly in Blade. | FACT, `inquiry-new.blade.php:48` |
| Server validation | `in:EMAIL,WA,PHONE,VISIT,MARKETPLACE`, required. | FACT, `InquiryPayload.php:17` |
| How it is saved | `InquiryPayload::from` -> `InquiryWriter::headerAttributes` -> `iqp_inquiry.source`. | FACT, `InquiryPayload.php:38`, `InquiryWriter.php:205` |
| Other values written by code | `MASTER CARD` by `MasterCardService` when a collection card is turned into an Inquiry; `REVERSE` by `ReverseCostingService` when INSTANT costing is reversed into an Inquiry (0 rows in the data). Revise/Recalc/Duplicate copy the value of the source inquiry. | FACT, `MasterCardService.php:328`, `ReverseCostingService.php:88`, `InquiryDerivedWriter.php:125`, `InquiryDuplicator.php` (`'source' => $source->source`) |
| Data distribution (mockup) | EMAIL 15 (LIVE 5, DUMMY 10), MASTER CARD 8 (DUMMY). | FACT, query 2026-10-16 |
| Readers | Inquiry print (label "Source"), loading the document into the form. NOT found: any report, grid filter, or widget that groups by `source`. | FACT that `grep` of `app/Support/V2`, `app/Services`, `packages` only finds `InquiryPrint.php:24,61` and `Sales.php:165`; reports outside those folders NOT checked |

## Relations
- **source**: Sales choice in the form; `MasterCardService` for old cards.
- **used_by**: Inquiry print. No analytic consumer yet.
- **flows**: copied to derived inquiries (Revise, Recalc, Duplicate). Does not go to Quotation or CO.
- **frozen_zone**: Inquiry Product (header field). Only recorded.

## Findings (recorded, not fixed)
1. **`MASTER CARD` and `REVERSE` are in neither the choices nor the validation rule.** `MasterCardService` sends the user to `inquiry-new?id=` to complete the DRAFT (`MasterCardService.php:290`); on Save, the `in:` rule rejects that value if `MASTER CARD` is what is actually sent. (For `REVERSE` the document is already Confirmed, so it is locked and not edited.) The behaviour of the `select` for a value outside the list was NOT tested in the browser, so it is not certain whether the user sees an error or the value silently becomes EMAIL.
2. **The channel list is embedded in code in two places** (Blade and `InquiryPayload`), with no master and no input screen; adding a channel means changing code (CLAUDE.md item 7: every value has an input screen; here the value is a list constant, not a master).
3. Data values are mixed-language (EMAIL, WA, MASTER CARD), while the database and code values should be English (B.3); harmless, only recorded.

## Already exists? Searched where, result
Searched: columns `source`/`channel`/`lead` in tm2sdi and tm2mst (`information_schema`), `grep` `source` in `app/`, `resources/views/v2`, `packages`. Result: only one column `iqp_inquiry.source`; no channel master. The concept ALREADY EXISTS; no new table is needed unless Jo wants channel analytics.

> Checked 2026-10-16 after the SpecEngine / MaterialPlanningEngine change (Jo agreed): this card only names or links the changed cards; nothing in it needs to change.

> Checked 2026-10-16 after the ProCost BOM panel change (Jo agreed): this card only names or links the changed cards; nothing in it needs to change.

> Checked 2026-10-16 after the ProCost Book button (Jo agreed): this card only names or links the changed cards; nothing in it needs to change.


> Re-checked 2026-10-17: `MasterCardService` now sets the default `ppn_percent` with `TaxRate::ppn()` and the Inquiry nota print falls back to `TaxRate::ppn(inquiry_date)` instead of a fixed 11; nothing else in this card changed.
> Label pass 2026-10-18 (Jo): the visible texts of the screens this card cites (labels, columns, tabs, buttons, messages) are English now. Tables, fields, flow and stored values are unchanged, so nothing else in this card changed. Verified: 2026-10-18.
