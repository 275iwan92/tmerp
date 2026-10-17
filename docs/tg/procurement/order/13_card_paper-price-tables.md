# Concept card: Supplier paper price tables (constants of the paper price engines)

> Written 2026-10-17 (group Procurement). FACT = mockup DB (`--env=mockup`, `tm2mst` = `ddtmst`) and code with line numbers seen in this session; tests `PaperPriceEngineTest` (8 passed, 45 asserts) and `PaperPurchaseEnginesTest` (7 passed, 49 asserts) ran on the default connection. Values of the tables are confidential (A.8-A.9) and are after the johiwan-only marker. Verified: 2026-10-17.

- **Document type**: concept card (five input tables + their five screens). **Module/sub-module**: Master Data (menu codes `MD_PAPER_PRICE`, `MD_SF_PRICE`, `MD_DUPLEX_PRICE`, `MD_CORR_RULE`, `MD_CORR_INDEX`), read by Procurement through the price engines.
- **Canonical name**: Supplier paper price tables = `sup_paper_price` (ivory and similar board, by kg), `sup_single_face_price` (single face index per m2), `sup_duplex_price` (duplex index with APKey), `sup_corrugated_rule` (trim width and discounts per running-meter tier), `sup_corrugated_index` (index per m2 per substance). Every row is a constant that a person typed, **dated** like an exchange rate: a new price is a new row, the old row stays as history.
- **Synonyms**: harga kertas per supplier, index kertas, APKey, supplier price list.
- **NOT the same as**: the price a PR line or PO line carries (`pr_line.i_est_price`, `po_line.i_price`; engines only propose it), the ProCost paper costs (`iqp_costing_part`, Sales), `mat_raw.last_unitprice` (last price paid, a plain material column).

## Facts
| Item | Content | Evidence |
|---|---|---|
| `sup_paper_price` | `id_paper_price`, `supplier_id` (FK `sup_supplier`), `paper_kind` (text, saved upper case), `grammage_id` (optional FK `mat_grammage`: empty = all grammages), `roll_price` + `roll_price_unit` (KG or TON), `cut_cost_per_kg`, `sheet_price_per_kg` (screen label "Ream price / kg"), `price_basis` (INCL or EXCL PPN), `effective_date`, `activate`. Index only for lookup (no unique key): two rows with the same supplier, kind and date are allowed. 1 row in the mockup data. | FACT, `show columns`, `show index`, FK query; `SupplierPaperPriceLookup.php:56-76` |
| `sup_single_face_price` | `supplier_id`, `substance_name` (e.g. text with M and K weights), `flute_type`, `index_per_m2`, `price_basis`, `effective_date`; UNIQUE (`supplier_id`, `substance_name`, `flute_type`, `effective_date`). 0 rows. | FACT, `show index`; `SupplierSingleFacePriceLookup.php` |
| `sup_duplex_price` | `supplier_id`, `grade` (nullable, upper case), `gsm_from`, `gsm_to`, `formula` (`REF_REAM` or `WEIGHT`), `index_value`, `apkey_pct`, `price_basis`, `effective_date`; non-unique index only; the screen refuses a duplicate of supplier + grade + `gsm_from` + date and `gsm_to` below `gsm_from`. 0 rows. | FACT, `show index`; `SupplierDuplexPriceLookup.php` (`check`) |
| `sup_corrugated_rule` | `supplier_id`, `max_trim_width_cm`, `disc_300`, `disc_500`, `disc_1000`, `disc_2000`, `disc_5000` (percent; the tests use a negative number as a surcharge), `effective_date`; UNIQUE (`supplier_id`, `effective_date`). 0 rows. | FACT, `show index`; `SupplierCorrugatedRuleLookup.php` |
| `sup_corrugated_index` | `supplier_id`, `substance_name` (a full substance such as one with W and L papers, or the generic SW / DW), `index_per_m2`, `price_basis`, `effective_date`; UNIQUE (`supplier_id`, `substance_name`, `effective_date`). 0 rows. | FACT, `show index`; `SupplierCorrugatedIndexLookup.php` |
| Screens | Five Master Data lookup screens: `v2:md-supplier-paper-price`, `v2:md-supplier-single-face-price`, `v2:md-supplier-duplex-price`, `v2:md-supplier-corrugated-rule`, `v2:md-supplier-corrugated-index`. Saving a changed date adds a row; a row without any price is refused (paper price: roll or ream price required). Tests cover the three newest screens and the paper price screen. | FACT, `sys_app_menu` query; `SupplierPaperPriceLookup.php:99-102`; tests `PaperPriceEngineTest`, `PaperPurchaseEnginesTest` |
| Who reads them | `PaperPriceEngine` (ivory board) and, through it, `SingleFacePriceEngine`, `DuplexPriceEngine`, `CorrugatedSheetPriceEngine`. No other code reads these tables. | FACT, grep of the model names |
| Date rule | Every engine picks the active row with the latest `effective_date` not later than the **document date** (PR line: today; PO line: the PO date). A future-dated or inactive row is ignored. | FACT, `PaperPriceEngine.php:98-108`; test `test_the_row_in_force_on_the_document_date_is_used_and_a_matching_grammage_wins` |
| PPN basis | `price_basis` INCL means the typed price contains PPN and the engine divides by 1 + PPN/100 using `TaxRate::ppn(document date)` (table `fin_tax_rate`: 10% from 01-Apr-1985, 11% from 01-Apr-2022); EXCL is not divided. | FACT, `PaperPriceEngine.php:24-34`; query on `fin_tax_rate`; test `test_divisor_follows_the_ppn_rate_in_force_on_the_document_date_and_excl_price_is_not_divided` |

## Relations
- **source**: the Purchasing officer types the prices from the supplier's letters; supplier master (`12_card_supplier-and-category.md`); `mat_grammage`; `fin_tax_rate`.
- **used_by**: the four engine cards `30_engine_paper-price.md`, `31_engine_single-face-price.md`, `32_engine_duplex-price.md`, `33_engine_corrugated-sheet-price.md`; indirectly the PR line estimate and the PO line price.
- **flows**: typed prices -> engine proposal on a new PR line or PO line -> officer may overwrite.
- **frozen_zone**: none. Rule A.7: the engine suggests, the officer decides.

## Findings (recorded, not fixed)
1. **Four of the five tables are empty**, so the Single Face, Duplex and corrugated engines return no price in the mockup data; only the ivory table has one row. In tmerp (live) the tables were not checked (this session read the mockup only). FACT.
2. **Duplicate rows are possible in `sup_paper_price`**: no unique key and no screen duplicate check (the other four have one of the two). The engine takes the highest `id_paper_price` among the same date, so the later typed row wins silently. FACT (indexes, `SupplierPaperPriceLookup.php` has no duplicate check).
3. **`mat_grammage` match is by value only**: a row with a grammage wins only when the material's `gram_weight` equals the grammage value within 0.005; otherwise the row without a grammage is used. FACT (`PaperPriceEngine.php:98-108`).
4. **The formulas come from two Excel files of Jo** kept in docs/attachments (a duplex workbook and a workbook for BF, BCF, single wall and double wall); both were untracked in git at the start of this session and were not opened here. FROM DOCUMENT (REMARKs of the engines name the sheets INDEX, UJK and RCA).

## Already exists? Searched where, result
`concept:find "paper price"`: engine map line `paper-price`; menu `Supplier Paper Price`; columns `sup_paper_price.*` and `iqp_costing_part.i_paper_price_override` (ProCost, other meaning); files `SupPaperPrice.php`, `PaperPriceEngine.php`, `SupplierPaperPriceLookup.php`. Result: ALREADY EXISTS (built 2026-10-17, commits named in the project log). The table `iqp_costing_part` price override is a different concept (Sales costing).

<!-- johiwan-only -->

## Confidential values (A.8-A.9; ddt* data and test asserts)
- The one `sup_paper_price` row in the mockup data: supplier 15, kind IVORY, no grammage, roll price 13,000 per KG, cut cost 250 per kg, ream price 12,500 per kg, INCL PPN, effective 17-Oct-2026, created by the migration. It has the same numbers as the engine tests.
- Material "IVORY 300 79 X 109" (the first line of PR2600009) has no grammage and no size in the master columns and a purchase unit RIM, so `priceFor` for supplier 15 returns nothing (see engine card 30, worked example). Of 314 ivory-like materials in the mockup master, 291 have grammage, size and unit RIM or LBR.
- Test constants (not real supplier prices): single face index 2,600 per m2; duplex index 1,000, APKey 500 (= x 5.00); corrugated max trim width 180 with discount tiers of -10; these appear in the engine cards' formulas as examples.
