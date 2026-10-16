# Concept card: Inquiry, Part, Qty tier

> Group B, 2026-10-16, awaiting Jo's approval. FACT = seen directly in this session: mockup DB (`--env=mockup`, connection `tm2sdi` -> `ddtsdi`), `show columns`, and code with line numbers. Nothing was changed.

- **Document type**: concept card. **Module/sub-module**: Sales > Inquiry & Quotation > Inquiry Product.
- **Canonical name**: Inquiry = `tm2sdi.iqp_inquiry`; Part = `tm2sdi.iqp_part`; Qty tier = `tm2sdi.iqp_tier`.
- **Synonyms**: inquiry product, InqProd, quotation request, IQ-number, component part, assembly, qty tier.
- **NOT the same as**: Master Product (`tm2mst.prod_product`) and master part (`tm2mst.prod_part`), both born from Inquiry, different tables; *part type* `iqp_part.part_type` and *product type* (card `10_card_product-type.md`).

## Facts
| Item | Content | Evidence |
|---|---|---|
| Table contents (mockup) | `iqp_inquiry` 23 rows, `iqp_part` 42 (COMPONENT 36, ASSEMBLY 6), `iqp_tier` 33. Part child tables: `iqp_part_material` 129, `iqp_part_finishing`, `iqp_part_ink`, `iqp_part_process`; documents: `iqp_tooling` 0 rows, `iqp_external_cost` 0, `iqp_revise_change` 0, `iqp_prove` 0. | FACT, `count(*)` 2026-10-16 (other children are only information_schema estimates) |
| Columns of `iqp_inquiry` | PK `id_inquiry`; `inquiry_no` (unique); `inquiry_date`; `source`; `pic_sales` (text); `customer_id` / `is_prospect` / `prospect_name`; `deadline_quotation`; `product_draft_name`; `sales_note`; `inquiry_type`; `parent_inquiry_id`; `product_id`; `revision_no`; `revise_scope`; `revise_reason`; `material_source`; `job_nature`; `cust_material_spec`; `cust_material_eta`; `tax_scheme`; `pph_applicable`; `ppn_percent`; `pph_percent`; `discount_doc_*`; `discount_qty_*`; `prove_*`; `due_date`; `design_number`; `co_id`; `sub_state`; three flags `stat_confirm_sales/_prodev/_procost` each with `_by` and `_at`; `stat_cancel`; `cancel_reason`; `is_approved` (+by/at); `data_origin`. | FACT, `show columns` |
| Columns of `iqp_part` | `inquiry_id`, `prod_part_id` (bridge to master part), `parent_part_id`, `part_kind` (COMPONENT/ASSEMBLY), `join_type_id`, `qty_in_parent`, `part_seq`, `part_code`, `part_name`, `part_type`, `qty_per_sku`, `pack_qty`, `cfg_color`, `cfg_ink`, `dieline_spec` (JSON), `flat_w`, `flat_h`, `flat_area`, `artwork_path`, `artwork_inner_path` (+ original file name), `artwork_colors` (JSON). | FACT, `show columns` |
| Columns of `iqp_tier` | `inquiry_id`, `tier_seq`, `qty`, `is_primary`, `discount_item_type/_value`, `price_excl`, `price_incl`, `primary_key_guard` (UNIQUE). | FACT, `show columns` |
| Three types | Master `iqp_type` 3 rows: NEW "Produk Baru", RECALC "Hitung Ulang Harga", REVISE "Ubah Spesifikasi". In-DB FK `iqp_inquiry.inquiry_type` -> `iqp_type.type_code`. Data: NEW 21, REVISE 2, **RECALC 0**. | FACT, `IqpInquiry.php:107-110`; `group by` query |
| Origin of REVISE/RECALC | `InquiryDerivedWriter::create` copies the header + part structure from the source inquiry (`parent_inquiry_id`); RECALC only fills Qty + Deadline; REVISE only the scope blocks (ARTWORK, COLOR, DIMENSION, MATERIAL, PROCESS, JOIN) and records the difference in `iqp_revise_change`. | FACT, `app/Services/Sales/InquiryDerivedWriter.php:21,38-51 (recalc),53-85 (revise),121-148 (create),192-223 (recordDiff)` |
| Numbering | `IQ` + 2-digit year + 5-digit sequence, counter per year, taken from `max(inquiry_no)` with the same prefix (`substr($last,4)`). | FACT, `IqpInquiry.php:163-176` |
| Screen & save | Screen `inquiry-new` posts to `v2.inquiry.save` -> `InquiryPayload::from` -> `InquiryWriter::save` (one transaction: header, tier, part+material+finishing, tooling, external cost). After `stat_confirm_sales`, the form is locked. | FACT, `inquiry-new.blade.php:35-39`, `routes/v2.php:272-287`, `InquiryWriter.php:27-55` |
| Lifecycle | Save = DRAFT; Confirm = `stat_confirm_sales=1` and `sub_state=SUBMITTED`; Cancel only when already Confirmed and not yet cancelled; Mark Lost from any `sub_state` that is not yet CANCELLED/CONVERTED/LOST. | FACT, `InquiryWriter.php:57-79` |
| Tier | Requires >= 1 row and EXACTLY 1 `is_primary`; qty > 0. DB unique key `primary_key_guard`. | FACT, `InquiryWriter.php:181-197`; `show columns` |
| Assembly | Via `InquiryAssembly::create` (>= 2 children, join type `mat_join_type`), not via the part form. `persistParts` does not delete assemblies. | FACT, `routes/v2.php:354-374`, `InquiryWriter.php:287-288` |
| Duplicate | `InquiryDuplicator`: born as NEW DRAFT, structure copied, qty tier and price are NOT copied. | FACT, `InquiryDuplicator.php:8-10 (komentar), 13-45` |
| Flag distribution (mockup) | NEW CONVERTED (sales=1, prodev=1, procost=1): 13; NEW SUBMITTED prodev=1: 2; NEW SUBMITTED prodev=0: 3; REVISE SUBMITTED prodev=0: 2; NEW DRAFT: 2; NEW LOST: 1. No inquiry has `stat_confirm_procost=1` without a PUBLISHED costing, and vice versa (0 rows). | FACT, query 2026-10-16 |
| Statuses written by code | Writers of `sub_state`: DRAFT (DB default), SUBMITTED (Confirm), CANCELLED, LOST (`InquiryWriter.php`, `pages/quotation/entry.blade.php:426`), CONVERTED (`CoOrderBuilder.php:101`), PRICE_CALCULATED (`ReverseCostingService.php:93` and costing Publish `pages/inquiry/costing.blade.php:582`). | FACT, grep `app/`, `resources/`, `routes/`, `packages/` |

## Relations
- **source**: Sales screen (`inquiry-new`), INSTANT Reverse costing (`ReverseCostingService`, see the ProCost interface card), Master Card Collection (old card becomes source Inquiry `MASTER CARD`, `MasterCardService.php:328`), Duplicate, Revise/Recalc from Master Product.
- **used_by**: ProDev (card `../prodev/10_card_prodev-interface.md`), ProCost (card `../product-costing/11_card_procost-interface.md`), Quotation (card `../quotation/10_card_quotation.md`), Master Product (`ProductMasterSync::fromInquiry` on WIN), Nota Inquiry / Print.
- **flows**: Inquiry (stat_confirm_sales) -> ProDev (stat_confirm_prodev, computed from layout) -> ProCost (stat_confirm_procost, computed from published costing) -> Quotation -> WIN = CO + `sub_state=CONVERTED`. Flags are governed by A.20. RECALC skips ProDev (`InquiryStage::PRODEV_TYPES = ['NEW','REVISE']`, line 20).
- **frozen_zone**: Inquiry Product, ProDev, ProCost (CLAUDE.md item 1). This card only records.

## Findings (recorded, not fixed)
1. **The Inquiry number can jump.** Data contains `IQ26090067`, `IQ2690068`, `IQ2690069` (seeder output). `nextInquiryNo` takes the largest value with prefix `IQ26` then adds 1 to `substr(,4)`; from code + data, the next real inquiry becomes `IQ2690070`, not `IQ2600007`. Computed from code, NOT run.
2. **Two inquiries flagged ProDev without master parts**: `IQ2600001` and `IQ2600002` have `stat_confirm_prodev=1` but their component parts have no `prod_part_id`. The flag should be computed by `InquiryStage::syncProdev` from the master part layout (`InquiryStage.php:37-50`); both rows seem to have been written outside that path (who wrote them not traced yet).
3. **Dummy REVISE does not match the code rule.** `IQ99PD0003/4` (REVISE) use the SAME `product_id` as their parent inquiry (14404 and 14403), while `ensureDraftProduct` creates a derived product (`IqpInquiry.php:132-154`; rule A.4: Revision = derived product). `iqp_revise_change` is empty, so the card "Revision: what changed" in ProDev has no content for these two documents.
4. **No RECALC document** in the data; the RECALC path (`inquiry-recalc`) is not tested with data. REMARK `inquiry-recalc.blade.php:3` and the message in `routes/v2.php` (Confirm) say "lanjut ProDev dan ProCost", while A.20 and `InquiryStage` set Recalc straight to Pro-Cost: outdated text.
5. **Customer target price per tier has no v2 input screen.** `iqp_tier.price_excl/price_incl` have 0 rows filled; `InquiryWriter::persistTiers` does not write them (lines 227-245) and screen `inquiry-new` only has `qty` and `is_primary` (lines 101-105). The reader is `nota.blade.php` (v1) as "Target customer" (lines 180-206); the only WRITER found is `ReverseCostingService.php:170-171`, which fills them with the costing RESULT price (`price_per_pcs`, `c_after_ppn_pc`). One column, two meanings (customer target vs computed price); the Reverse path has 0 rows in the data so this is not visible yet. The discount columns (`discount_doc_*`, `discount_qty_*`, `discount_item_*`) also have no input on the v2 screen (`InquiryPayload.php:51-54,58`, old values kept); all are empty in the mockup. CLAUDE.md item 5 (every value has an input screen): not met.
6. **NEW DRAFT Inquiry has no Delete.** `InquiryForm.php` only has Cancel and Mark Lost (lines 78-80), and `routes/v2.php` group `inquiry.` has no delete route; RECALC/REVISE, in contrast, have `derived.delete`. Not decided whether this is intended.
7. **Prospects cannot be created via the v2 screen.** `InquiryPayload.php:41` always sets `is_prospect=false`; the 4 prospect inquiries in the data come from outside this screen. A prospect (without `customer_id`) also cannot enter Quotation (`QuotationBuilder.php:53`, `whereNotNull('customer_id')`).
8. **`pic_sales` is free text** (not an FK to `sales_person`): see card `../master-data/11_card_sales-pic.md`.
9. **Ownerless statuses.** `Sales::STATES` contains WAITING_COST, PRICE_APPROVED, QUOTED, PROVING, PROVE_APPROVED, but no code writes them; they only exist as display mapping on the v1 page. All 0 rows in the data.
10. **`is_approved`**: 11 inquiries have value 1 in the mockup; the writer has not been searched yet. `doc_state_log` (naming rule CLAUDE.md item 5) is NOT FOUND in any database in the mockup; Inquiry transitions are only visible through `sub_state` and `confirm_*_by/_at`.

## Already exists? Searched where, result
Searched: `show columns` + `information_schema` tm2sdi/tm2mst/tm2sys; `grep` `app/`, `routes/`, `resources/views/v2`, `packages/`; synonyms: inquiry, IQ, revisi, recalc, tier, rakitan, prospect. Result: the whole concept ALREADY EXISTS; the findings above are not requests for a new schema.

## User's Guide (UG) already existing
`docs/guides/sales/01_inquiry_product.md` (FROM DOCUMENT, not opened in this session).

> Checked 2026-10-16 after the ProCost BOM panel change (`ProCostContextService`, `costing.blade.php`; Jo agreed): the content of this card is not affected; cited line numbers of those two files may be about 30 lines lower. Only the cited items were re-read, not the whole card.
