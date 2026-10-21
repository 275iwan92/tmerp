# Concept card: Product type (Product Type)

> PHASE 1 EXAMPLE (2026-10-16), awaiting Jo's approval. All "FACT" items were seen directly in this session (mockup DB `ddt*` via the `tm2*` connection with `--env=mockup`, and code).

- **Document type**: concept card. **Module/sub-module**: Sales > Inquiry & Quotation > Inquiry Product (matches the `sys_app_menu` menu tree).
- **Canonical name**: Product type = `prod_type.type_code` (screen label "Product Type (international dieline)").
- **Synonyms** (for search): type_code, dieline type, packaging shape, RSC_2PC, CARD_BOX.
- **NOT the same as**: *part type* (`iqp_part.part_type`: DC, DC+SHEET, DC+SF, SHEET). Two different concepts, do not merge.

## Facts
| Item | Content | Evidence |
|---|---|---|
| Master | `tm2mst.prod_type`: 63 rows, 28 active. Columns: `type_code`, `type_name`, `category`, `sub_category`, `box_style`, `dieline_spec` (JSON), `dieline_template_id`, `ecma_code`, `part_type_default`, `material_filter`, `has_multi_part`. Unique key `type_code`, DB PK `id_type`. | FACT, `show columns` + `information_schema` |
| Filled by | Inquiry New screen, choice "Product Type (international dieline)". | FACT, `resources/views/v2/screens/inquiry-new.blade.php:68` |
| How it is saved | `InquiryWriter::persistParts` freezes (snapshot, A.13) `prod_type.dieline_spec` + dimensions + `type_code` into **`iqp_part.dieline_spec` (JSON, key `type_code`)**. **Not** into column `part_type`. | FACT, `app/Services/Sales/InquiryWriter.php:274-278`; data: `json_extract(dieline_spec,'$.type_code')` is filled in ddtsdi |
| Dummy data spread | 42 parts: RSC_2PC 12, BUKU 10, RTE_BOX 8, TRAY_LID_DW 4, LOCK_BOTTOM_2PC 3, CARD_BOX 2, KALENDER_DINDING 1, empty 2. `part_type`: DC 25, DC+SHEET 13, DC+SF 3, SHEET 1. | FACT, query 2026-10-16 |
| Inquiry level | `iqp_inquiry` has NO product type column (only `inquiry_type` NEW/REVISE/RECALC, `product_draft_name`, `product_id`). Product type lives at **part** level. | FACT, `show columns` |
| Used by | Grid column "Product Type" = first part (`inquiry.blade.php:21`); `RoutingTemplateService`, `RoutingTemplateGrid` (column `prod_routing_template.prod_type_code`), `PartDieline`, `InquiryPrint`, `ProdMasterCard`. | FACT that the files refer to `ProdType`/`prod_type_code`; behaviour of each user NOT checked |
| Master input screen | Screen that fills master `prod_type` | NOT checked (CLAUDE.md rule item 7.5 requires the screen) |

## Relations
- **source**: master `prod_type` (neutral, `tm2mst`).
- **used_by**: Inquiry Product (save), ProDev (`PartDieline`), Production (`prod_routing_template`), Master Card (`ProdMasterCard`).
- **flows**: `type_code` + `dieline_spec` are frozen in `iqp_part.dieline_spec`; later master changes do NOT propagate to old Inquiries (snapshot).
- **frozen_zone**: Inquiry/ProDev. Touching = warn Jo first.

## Findings (recorded, not fixed)
1. The screen declares source `tm2sdi.iqp_part.part_type` for the Product Type field (`inquiry-new.blade.php:70`), while the value is stored in `iqp_part.dieline_spec.type_code`. The source text on the screen is misleading.
2. `persistParts` only saves `type_code` when `prod_type.dieline_spec` is filled (line 254). A type without spec is silently not saved. In the master: 1 of 28 active types has no spec. There are 2 dummy parts with empty `type_code`; cause not investigated yet.
3. Model `ProdType` uses `type_code` as primary key (`app/Models/Master/ProdType.php:14`), while the DB has PK `id_type` (naming rule: PK `id_<entitas>`).
4. The ProDev session note ("type_code declared to `part_type`") was checked: the correct version is as in the Facts table.

## Already exists? Searched where, result
Searched: column/table `type_code` + `prod_type` in the mockup DB, `inquiry*.blade.php`, `app/Services/Sales`, Sales menu tree. Result: the concept ALREADY EXISTS; no new column is needed in `iqp_part` or `iqp_inquiry`.
> Label pass 2026-10-18 (Jo): the visible texts of the screens this card cites (labels, columns, tabs, buttons, messages) are English now. Tables, fields, flow and stored values are unchanged, so nothing else in this card changed. Verified: 2026-10-18.
- Re-checked 2026-10-20 after Imposition and Inquiry Nota moved off v1 (rule D.25, T10 stage 2): `routes/web.php` replaced the v1 routes `/inquiry/imposition`, `/inquiry/{inquiry}/imposition` and `/inquiry/{inquiry}/nota` by redirects (to ProDev v2 `/v2/s/prodev-new`, `/v2/prodev/open/{id}` and the v2 print `/v2/print/inquiry-note/{id}`) on the same lines (no line added or removed), `app/Support/V2/Print/PrintDocs.php` registered the `inquiry-note` print type on the existing `inquiry` line, and `app/Support/V2/Screens.php` changed the description text of the `nota-inquiry` row on the same line. The v1 pages `pages::inquiry.imposition`, `imposition-list` and `nota` are archived in `tmerp_unused/`; `ImpositionEngine` is unchanged. Every other cited line number is unchanged and this card's content is still true.
- Re-checked 2026-10-21 after ProCost moved off v1 (rule D.25, T10 stage 3): `routes/web.php` replaced the v1 routes `/inquiry/costing`, `/inquiry/{inquiry}/costing`, `/costing/instant` and `/costing/instant/{costing}` by redirects (to `/v2/s/sales-procost`, `/v2/s/procost?inquiry={id}`, `/v2/s/sales-procost-instant`, `/v2/s/procost-instant?id={id}`) on the same lines and extended the `require` line of the v2 route files with `routes/v2_procost.php` (no line added or removed); `routes/v2.php` changed the comment and redirect target of `procost-instant.new` on the same lines; `app/Support/V2/Screens.php` and `app/Support/Menu/MenuRoutes.php` changed row text and two menu targets on the same lines; `CostingAdvisor.php` changed one REMARK line. The v1 pages `pages::inquiry.costing`, `costing-list`, `costing-instant` and `costing-instant-list` are archived in `tmerp_unused/`; the engines are unchanged. Every other cited line number is unchanged and this card's content is still true.
