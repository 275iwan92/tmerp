# Concept card: Supplier and Supplier Category (as Procurement uses them)

> Written 2026-10-17 (group Procurement). FACT = mockup DB (`--env=mockup`, `tm2mst` = `ddtmst`; read only; supplier data may be real, nothing was written) and code with line numbers. The Master Data module has no TG folder yet; this card records only what Procurement relies on. Verified: 2026-10-17.

- **Document type**: concept card. **Module/sub-module**: Master Data > Supplier (`v2:md-supplier`, grid `SupplierGrid`) and Master Data > Supplier Category (`v2:md-supplier-category`, `SupplierCategoryLookup`); used by all of Procurement.
- **Canonical name**: Supplier = `tm2mst.sup_supplier` (every vendor: material suppliers, service houses, subcontractors, forwarders and banks) + `sup_supplier_bank`, `sup_supplier_branch`, `sup_supplier_contact`. Supplier Category = `tm2mst.sup_category`.
- **Synonyms**: vendor, pemasok, subkon (a supplier with `is_subcon` = 1), `supplier_code`, nickname.
- **NOT the same as**: Customer (`cust_customer`), the legacy vendor tables `mst_vendor*` and `v1_mst_supplier*` of `tm2old`, the sheet suppliers `mat_sheet_supplier` (UJK, RCA; 2 rows read by `CostingEngine`, i.e. ProCost, not by Procurement).

## Facts
| Item | Content | Evidence |
|---|---|---|
| Supplier table | `sup_supplier`: 289 rows (LEGACY 283, LIVE 6). Key columns: `id_supplier`, `supplier_code`, `supplier_name`, `nickname`, `alias_code`, `category` (text), `is_subcon`, `payment_term`, `tax_code`, `tax_purchase_value`, `tax_service_value`, `is_blacklisted`, `activate`, `legacy_vendor_code`, `data_origin`. 30 are subcontractors, 2 are blacklisted, 265 have no payment term, 0 have a purchase tax value. | FACT, `show columns`, group-by queries |
| Child tables | `sup_supplier_bank` 83 rows; `sup_supplier_branch` 0; `sup_supplier_contact` 0. | FACT, counts |
| The six LIVE rows | BCA, MANDIRI, BRI, BNI, SEABANK and BTN, category BANK: banks used as lookup for payroll accounts (comment of the category screen). All other suppliers are LEGACY data from the old system. | FACT, query; `SupplierCategoryLookup.php:9-10` |
| Category | `sup_category`: 27 rows (`category_code`, `description`, `activate`), e.g. BAHAN BAKU, KERTAS, JASA UV, JASA PLONG, EKSPEDISI, BANK, ATK, SPARE PART MESIN. `sup_supplier.category` holds the **code as text**, matched by the relation `categoryRef` (`category` -> `category_code`); 286 of 289 suppliers have a code, 3 are NULL; the FK sup_supplier_category_foreign points `sup_supplier.category` to `sup_category` (so a code in use cannot be removed), and sup_supplier_payment_term_foreign points `sup_supplier.payment_term` to `fin_payment_term`. A saved code cannot be changed (screen rule); deactivate it instead. | FACT, query; `SupSupplier.php:75-78`; `SupplierCategoryLookup.php:27-31` |
| Left-over table | `sup_category__oldpk` (27 rows) has the same 27 category codes and the same columns without the `id_category` key (its purpose was not checked); nothing in `app/` or `database/` names it. | FACT, count; grep |
| How Procurement refers to a supplier | Logically by `id_supplier` in `po_order.i_supplier_id`, `pr_line_alloc.i_supplier_id`, `pq_quotation.i_supplier_id`, `pv_receipt.i_supplier_id`, `prt_return.i_supplier_id`, `dn_debit_note.i_supplier_id`, and in the paper-price tables; no database FK across databases (rule of CLAUDE.md section 5), so integrity depends on the screens. | FACT, `show columns` of those tables; FK query shows none to `sup_supplier` |
| Default supplier of a material | `mat_raw.suggest_supplier_id`; the default allocation of a PR line and the Buy button read it. It is NULL on all 11,401 materials in this data. | FACT, query; `PrAllocationService.php:35` |
| What the PO takes from the supplier | Only the id. `payment_term`, `tax_code`, `is_subcon` and `is_blacklisted` are **not** copied or checked when a PO or PQ is made: 7 of 8 POs have `i_payment_term` NULL; two blacklisted suppliers (ATK) could be chosen. | FACT, `PoBuilderService.php:28-47` (no copy); grep `is_blacklisted`: only the master screens, `ArApService.php`, the dummy builder and migration, not `app/Services/Pur` or the PO / PQ data classes |
| Where the flags are read | `is_blacklisted` and `tax_*`: finance (`ArApService.php`) and the master screen; `is_subcon`: the master screen and subcontract screens. | FACT, grep over `app/` and `resources/views/pages` |
| Matching to the file of a quotation | `PqMatcher::supplier` compares the name with active suppliers (card `20_engine_pq-matcher.md`). | FACT |
| Paper prices | Five price tables per supplier hold the constants of the paper engines (card `13_card_paper-price-tables.md`). | FACT |

## Relations
- **source**: master data screens (Supplier, Supplier Category, Payment Term), migration from the old vendor tables (`VendorMigrationService`).
- **used_by**: PR allocation, PQ, PO (`11_card_purchase-order.md`) and its Cash PO supplier "TOKO KONTAN", Goods Receipt, Purchase Return (`../return/10_card_purchase-return.md`), Debit Note, Shortage Request (source SUPPLIER / SUBCON), supplier sampling in Inventory, Finance payables.
- **flows**: master -> chosen on PQ / PO -> carried by id through receipt, return and shortage; nothing flows back to the master (A.14: master is changed only by the user).
- **frozen_zone**: none.

## Findings (recorded, not fixed)
1. **The master flags `is_blacklisted`, `payment_term` and `tax_purchase_value` have no effect in Procurement.** A blacklisted supplier can receive a PO and the supplier's agreed payment term is not proposed on the PO. FACT (grep; data).
2. **3 suppliers have no category** (the column is nullable although it carries a FK), so a report grouped by category shows an empty bucket. FACT.
3. **Leftover table `sup_category__oldpk`** is unused. FACT.
4. **`mat_raw.suggest_supplier_id` is empty everywhere**, so the default supplier of every PR allocation is empty (PR card finding 4). FACT.
5. **Contacts and branches are empty** (0 rows), so PO print and quotation contact show only `contact_name` of the header. FROM CODE for the print (not read), FACT for the counts.
6. **Subcontractors**: 30 suppliers have `is_subcon`, but the Subcon PO type does not check the flag of the chosen supplier (`PoBuilderService::findOrCreateDraft` sets only the supplier id). FROM CODE.

## Already exists? Searched where, result
`concept:find "supplier category"`: menu "Master Supplier Category", columns `mst_vendor.supplier_category` and `v1_mst_supplier.supplier_category` (old system), files `SupplierCategoryLookup.php`, `MstVendorSupplierCategory.php`; help topic `md-supplier-category`. Tables `sup_*`. Result: ALREADY EXISTS; no supplier card existed in the TG (only the Customer and Sales PIC cards in `../../sales/master-data/`).
