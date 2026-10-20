# Concept card: Material Source (material from the company or from the customer)

> Group B, 2026-10-16. FACT = seen directly (mockup DB `ddtsdi`, code with lines).

- **Module/sub-module**: Sales > Inquiry & Quotation > Inquiry Product (downstream: Stock > Customer Material, Costing).
- **Canonical name**: Material Source = `tm2sdi.iqp_inquiry.material_source` (`OWN` | `CUSTOMER`) together with `job_nature`, `cust_material_spec`, `cust_material_eta`, `pph_applicable`.
- **Synonyms**: consigned material, customer supply, consignment, service (JASA), own material (OWN), `cust_material_*`.
- **NOT the same as**: inquiry `source` (card `12_card_inquiry-source.md`); the inventory module "Customer Material" (screen `customer-material`, report).

## Facts
| Item | Content | Evidence |
|---|---|---|
| Header columns | `material_source` varchar(10) default `OWN`; `job_nature` varchar(10) default `PRODUCT`; `cust_material_spec` text NULL; `cust_material_eta` date NULL; `pph_applicable` tinyint default 0. | FACT, `show columns` |
| Per-material column | `iqp_part_material.material_source` varchar(10) default `OWN`. | FACT, `show columns` |
| Screen | One checkbox "Material Source: Customer supplies its own raw material". When ticked, "Customer material description" (required) and "Material ETA" appear. | FACT, `inquiry-new.blade.php:85-95`; JS `inquiry-form.js:305,330` |
| Header save | `CUSTOMER` -> `job_nature='JASA'`, `cust_material_spec/_eta` saved; otherwise `job_nature='PRODUCT'` and both NULL. | FACT, `InquiryWriter.php:221-238` |
| PPh | New document: `pph_applicable = (bahan dari customer)`; old documents keep their value. | FACT, `InquiryPayload.php:48` |
| Per-material save | The header value is copied to EVERY material row (`'material_source' => $materialSource`) with the comment "granularitas per-layer belum diputuskan". | FACT, `InquiryWriter.php:333-334` |
| Validation | `cust_material_spec` required when `material_source` is ticked. | FACT, `InquiryPayload.php:22` |
| Readers | `SpecialReceiptService` (consigned-material spec card enters Receiving at no cost), `ProCostContextService` (column `owner` in costing BOM), `InquiryPrint` ("own material" / "customer material"), `SpecEngine.php:1225` (copies to BOM rows). | FACT that the files refer to it; behaviour of each reader NOT checked |
| Screen rule | "Customer material -> material COGS = 0; masuk lewat Receiving non-costed (consignment). Handling & spoilage dihitung Costing." | FROM DOCUMENT (screen alert text, `inquiry-new.blade.php:94`); the COGS = 0 calculation NOT checked in ProCost |
| Data distribution (mockup) | `iqp_inquiry`: 23 rows all `OWN`/`PRODUCT`, 0 have `cust_material_spec`. `iqp_part_material`: 129 rows all `OWN` (ENGINE 85, MANUAL 44). | FACT, query 2026-10-16 |

## Relations
- **source**: checkbox in Inquiry New (Sales). Revise/Recalc/Duplicate copy from the source inquiry.
- **used_by**: ProCost (material owner per row), special Receiving (consigned material), Inquiry print, SpecEngine (copies to BOM).
- **flows**: Inquiry -> part BOM (`material_source`) -> costing. Master changes are not relevant; this is transaction data.
- **frozen_zone**: Inquiry and ProCost.

## Findings (recorded, not fixed)
1. **There is no test data at all for the CUSTOMER path** (0 of 23 inquiries, 0 of 129 materials). The JASA path, PPh, the Customer Material card, and COGS = 0 cannot be assessed from data yet.
2. **Two storage levels, one input level.** The screen has only one switch for the whole inquiry, but each material stores its own `material_source`; if a mix (some materials from the customer) ever exists, there is no place to input it (acknowledged in the comment `InquiryWriter.php:333`).
3. **`job_nature` and `pph_applicable` are derived, not entered.** `pph_applicable` is only filled automatically when a new document is created; on old documents the value is kept even if the material switch changes (`InquiryPayload.php:48`), so PPh can be out of sync with `material_source` if the switch is changed during edit. Not proven on screen.
4. The name `cust_material_*` in this task is only two header columns; there is NO table `cust_material*` in the database (searched in all `ddt*`).

## Already exists? Searched where, result
Searched tables `cust_material%` / `%material_source%` in all `ddt*` (`information_schema`): none. `grep` `material_source|cust_material|job_nature` in `app/`, `resources/views/v2`, `packages`. Result: the concept ALREADY EXISTS in the header columns + material rows; its downstream (Receiving, Customer Material) belongs to the Stock module, not this card.

> Checked 2026-10-16 after the SpecEngine / MaterialPlanningEngine change (Jo agreed): the content of this card is not affected; `SpecEngine.php:1225` now lies about 25 lines lower. Only the cited items were re-read, not the whole card.

> Checked 2026-10-16 after the ProCost BOM panel change (Jo agreed): this card is only linked from a changed card; nothing in it needs to change.


> Checked 2026-10-16 after the ProCost Book button (Jo agreed): this card only names or links the changed cards; nothing in it needs to change.
> Label pass 2026-10-18 (Jo): the visible texts of the screens this card cites (labels, columns, tabs, buttons, messages) are English now. Tables, fields, flow and stored values are unchanged, so nothing else in this card changed. Verified: 2026-10-18.
- Re-checked 2026-10-20 after Imposition and Inquiry Nota moved off v1 (rule D.25, T10 stage 2): `routes/web.php` replaced the v1 routes `/inquiry/imposition`, `/inquiry/{inquiry}/imposition` and `/inquiry/{inquiry}/nota` by redirects (to ProDev v2 `/v2/s/prodev-new`, `/v2/prodev/open/{id}` and the v2 print `/v2/print/inquiry-note/{id}`) on the same lines (no line added or removed), `app/Support/V2/Print/PrintDocs.php` registered the `inquiry-note` print type on the existing `inquiry` line, and `app/Support/V2/Screens.php` changed the description text of the `nota-inquiry` row on the same line. The v1 pages `pages::inquiry.imposition`, `imposition-list` and `nota` are archived in `tmerp_unused/`; `ImpositionEngine` is unchanged. Every other cited line number is unchanged and this card's content is still true.
