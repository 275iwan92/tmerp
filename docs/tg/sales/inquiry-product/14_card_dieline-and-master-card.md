# Concept card: Dieline and Master Card (Sales side)

> Group B, 2026-10-16. FACT = seen directly (mockup DB `ddtsdi`/`ddtmst`, code with lines, file listing). Behaviour on the browser screen was NOT tested.

- **Module/sub-module**: Sales > Inquiry & Quotation > Inquiry Product (the dieline is born here) and > Pro-Dev (Master Card and its collection).
- **Canonical name**: Dieline = `iqp_part.dieline_spec` (JSON) + `flat_w/flat_h/flat_area`. Master Card = ONE form (decision Jo 2026-10-16): the combination of the Master Product and the layout that comes from Inquiry Product + ProDev + ProCost. Code today still has two shapes (see Facts): (a) `tm2mst.vw_master_card`, (b) collection `tm2mst.prod_master_card`.
- **Synonyms**: flat layout, flat size, international dieline, knife/die, MC, parent card, Master Card Collection.
- **NOT the same as**: product type (`prod_type`, card `10_card_product-type.md`); ProDev layout/imposition (`prod_layout_group`); artwork (design image, column `artwork_path`).

## Facts
| Item | Content | Evidence |
|---|---|---|
| Dieline snapshot in Inquiry | `InquiryWriter::persistParts` copies the DSL `prod_type.dieline_spec` + the user-entered `dims` + `type_code` into `iqp_part.dieline_spec`. Keys present: `dims, flat, vars, canvas, elements, type_code` (+ `gen` on certain types). | FACT, `InquiryWriter.php:252-258`; `json_keys` on `iqp_part` 1 and 2 |
| Dimensions | `dims.product_length/width/height` in **mm** (L x W x H); `flat_w/flat_h/flat_area` are separate columns, **mm** (A.19). Converted to cm only when creating `prod_part` (`/10`). | FACT, `InquiryWriter.php:106-108` (`/ 10`); example `iqp_part` 1: 200 x 50 x 120, flat 515 x 190 |
| Completeness of dimensions (mockup) | 33 of 36 component parts have L, W, H; 34 of 36 have flat W and H; 31 have artwork. The three parts without height are non-box types (example BUKU uses `page_count`). | FACT, query 2026-10-16 |
| Types Sales may choose | Only ACTIVE types that have `dieline_spec.elements`: 27 of 28 active (1 active without spec). | FACT, `Sales.php:116-119`; `prod_type` query |
| Render | The live dieline preview uses `resources/js/tmdieline.js` (DSL TMDieline). | FACT that the file exists (`Test-Path`); "not changed" FROM DOCUMENT (`claude_vital_flows.md`) |
| ProDev changes the dieline | The "Part & Dieline" panel in ProDev saves in TWO DIRECTIONS: `iqp_part` (snapshot) and `prod_part` (master); the Master Product header size follows only if the product is ACTIVE and it is the first part. | FACT, `app/Services/ProDev/PartDieline.php:29-53`; rule A.17 |
| Form (a) automatic Master Card | View `vw_master_card` (69 rows) joins `prod_product` + `prod_part` + `prod_part_ink/_material/_process` + `prod_mastercard_signoff` (0 rows) into a card: item code, design no, dimensions, knife code, join, finishing, content per print, print size, colour CS/KS, job sequence. | FACT, `show create view vw_master_card`; `count(*)` |
| Form (b) old card collection | `prod_master_card` 15 rows (XLSX 11, PHOTO 2, PDF 1, MANUAL 1), all status `PRODUCT`, all have L/W/H dimensions; 14 have `dieline_svg` and flat; files `prod_master_card_file` 27 rows (SOURCE 6, PROOF 5, CUT_PLAN 2, ARTWORK 11, PHOTO 3). Card -> Inquiry (source `MASTER CARD`, 8 dummy inquiries) -> Master Product via `ProductMasterSync::fromInquiry`. | FACT, `show columns`, query 2026-10-16; `MasterCardService.php:17-19,328` |
| Jo's original files | `docs/attachments/MC-*.xlsx`: 5 files. `docs/attachments/mastercard/`: 21 files (10 PDF, 2 PNG, 9 JPG dieline). In the initial `git status` of the session, the new PDFs were untracked (not yet in git). | FACT, `Get-ChildItem`, `git status --short` |
| Master Product & card | `prod_product.card_status`: DRAFT 14,011, ACTIVE 14. The official Master Product is born at WIN; before that there are DRAFT products from `ensureDraftProduct`. | FACT, query; `CoOrderBuilder.php:104-110`, `IqpInquiry.php:132-154` |

## Relations
- **source**: dieline = `prod_type` (master) + Sales input; Master Card (a) = Master Product + master parts resulting from ProDev/WIN; (b) = Jo's old files.
- **used_by**: ProDev (imposition reads `dieline_spec`/flat), ProCost (geometry), Master Card print, PPIC (inherits), Master Card Collection (Pro-Dev menu).
- **flows**: Inquiry (dieline snapshot) -> `prod_part` (sendToProDev copies the size in cm, not `dieline_spec`; the full copy happens in `ProductMasterSync::part`, lines 61-78, at WIN) -> Master Card (a).
- **frozen_zone**: Inquiry dieline and Master Card. Jo's original files are used as they are; the dieline is not redrawn.

## Findings (recorded, not fixed)
1. **"Master Card" has two shapes in code, but ONE form by decision (Jo 2026-10-16)**: Master Card is the combination of the Master Product and the layout from Inquiry Product + ProDev + ProCost. Today (a) the view `vw_master_card` (from product/part) and (b) the collection of imported cards (`prod_master_card`) both exist. Merging them into one is a flow/data change in the frozen zone: not done; needs narrative, preview and Jo's permission first.
2. **Column `prod_part.dieline_spec` is only filled at WIN.** `sendToProDev` only copies L/W/H (cm), `pack_qty`, `cfg_color`, finishing, artwork (`InquiryWriter.php:109-119`); it does not copy `part_type`, `dieline_spec`, flat. In the mockup, 2 of 35 master parts linked to an inquiry have `part_type` NULL although their `iqp_part.part_type` is `DC`. Impact on the engine not proven.
3. **Artwork attachments are always given `file_type='JPEG'`** even though the upload may be PNG (`InquiryWriter.php:144-146`; validation `mimes:jpg,jpeg,png`, `routes/v2.php:276`).
4. **Mixed types in JSON**: `dims` stores numbers as text with varying decimals (`"200"` vs `"40.0000"`); harmless, but a text comparison would be wrong.
5. **One active type without `dieline_spec`** does not appear in the Sales choices (see also the product type card, finding 2 there).
6. **Jo's original files are not yet in git** (new Master Card PDFs were untracked at the start of the session); do not assume they are safe in history.

## Already exists? Searched where, result
Searched: `dieline`/`mastercard`/`master_card` in all tables and views `ddt*` (`information_schema`), `app/`, `resources/views/v2`, `packages/`, `docs/attachments`. Result: the whole concept ALREADY EXISTS (dieline in `iqp_part`, Master Card (a) view, (b) collection). No new column or table is needed for the Sales side.
