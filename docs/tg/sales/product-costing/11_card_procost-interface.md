# Concept card: ProCost interface (Sales side)

> Group B, 2026-10-16, awaiting Jo's approval. Only the Inquiry/ProDev <-> ProCost boundary: what ProCost receives, what it gives to Sales, and the flag `stat_confirm_procost`. Formulas and the costing engine: see `10_engine_costing-advisor.md` and the open items in `docs/ENGINE-ATTENTION.md`. HPP, margin, and price formulas are NOT in the upper part of this card (A.8-A.9); price figures only after the johiwan-only marker. FACT = seen first-hand (mockup DB, code with lines).

- **Document type**: concept card (interface). **Module/sub-module**: Sales > Inquiry & Quotation > Pro-Cost.
- **Canonical name**: ProCost interface = `ProCostContextService` (inbound), `iqp_costing.published_flag` + `InquiryStage::syncProcost` (flag), `TierPricingResolver` (outbound to Sales), `ReverseCostingService` (back from INSTANT costing).
- **Synonyms**: Pro-Cost Mode #1 (Integrasi / Ambil Data), Publish costing, price declassification, Ready to Quote.
- **NOT the same as**: Costing Instant (manual mode, `iqp_costing.mode='INSTANT'`, no Inquiry); the Costing Advisor engine.

## Facts
| Item | Content | Evidence |
|---|---|---|
| ProCost desk | An Inquiry enters the ProCost desk when `stat_confirm_sales` = 1, not cancelled/LOST, and (type NEW/REVISE) `stat_confirm_prodev` = 1; RECALC goes straight in. The ProCost grid uses module code `COSTING` (desk `PROCOST`). | FACT, `Sales.php:249-250,326-330`; `CostingListGrid.php:22-25,38-41` |
| Table contents (mockup) | `iqp_costing` 26 rows: DRAFT 4, CALCULATED 9, PUBLISHED 13; `mode` INTEGRATED 17 (all linked to an Inquiry NEW), INSTANT 9 (`inquiry_id` NULL, 0 published); `calc_type` NEW 25, RECALC 1. `iqp_costing_price_tier` 37 rows. `inquiry_id` is UNIQUE (one costing per inquiry). | FACT, `show columns` + query 2026-10-16 |
| Read-only context for ProCost | Per costing part: `iqp_part` (+ BOM `bomMaterials`, processes, finishing, ink), `prod_part` (+ processes, finishing), ProDev layout (`prodev_part_id`/`prod_layout_group_id` stored in the costing part, or the latest CONFIRMED layout), geometry from the layout ("lembar cetak per plano", "isi per lembar", print size), artwork. BOP: from ProDev if present, otherwise from Inquiry. | FACT, `ProCostContextService.php:26-47,115-150` |
| Qty | A costing tier points to an Inquiry tier: `iqp_costing_price_tier.tier_id` -> `iqp_tier.id_tier`. Data: 0 published tiers not linked to the same inquiry tier; 0 published costings without a price tier. | FACT, query 2026-10-16 |
| Publish | "PUBLISH DIREKSI = deklasifikasi harga dari Sisi A (rahasia) ke Sisi B (Sales)": only when `doc_status = PENDING_APPROVAL`; gate admin or group `DIREKSI`; writes `doc_status=PUBLISHED`, `published_flag`, `is_approved`, `approved_by/_at` and `iqp_inquiry.sub_state='PRICE_CALCULATED'`. | FACT, `pages/inquiry/costing.blade.php:554-584` |
| Flag | `stat_confirm_procost` is COMPUTED: 1 if an `iqp_costing.published_flag=1` exists for that inquiry; triggered by the event `IqpCosting::saved` (`InquiryStage::listen`). Data consistency: 0 flagged inquiries without a published costing, 0 published costings without the flag. | FACT, `InquiryStage.php:59-70,99-105`; `AppServiceProvider.php:75`; query |
| Outbound to Sales | Only when the costing is published: `TierPricingResolver::rows` fills the price per tier (`price_excl`, `price_incl`) and marks the cheapest tier; when not yet published ALL price columns are NULL. Users: Inquiry Nota, Quotation (`availableInquiries` requires `published_flag`), the "Ready to Quote" grid. | FACT, `TierPricingResolver.php:13-40`, `QuotationBuilder.php:46-57,149-154`, `Sales.php:237,285` |
| What shows in the Sales worklist | Only the costing document status (`NOT STARTED` if none yet) and the stage (`Pro-Cost`, `Ready to Quote`); no cost figures. | FACT, `Sales.php:274,280-287` |
| Formula access | Formula details and HPP only for logins in the constant `PROCOST_FORMULA_LOGINS`; value in the mockup `sys_constant` `johiwan,wiwik2`. | FACT, `FormulaAccess.php:16-26`; query |
| Reverse | INSTANT costing -> a new Inquiry NEW + Master Product DRAFT + part + `prod_layout_group` DRAFT + `iqp_tier`; the costing becomes INTEGRATED. Used 0 times in the data (`reversed_at` empty, 0 inquiries with `source='REVERSE'`). | FACT, `ReverseCostingService.php:44-197`; query |

## Relations
- **source**: Inquiry (spec, qty tiers), ProDev (layout, BOP, BOM), rate masters and COSTING constants (owned by another ProCost card).
- **used_by**: Quotation (price per tier), Inquiry Nota, CO (qty tolerance from the tier `tolerance_pct`), Win-Rate.
- **flows**: costing PUBLISHED -> Quotation line (`unit_price` = tier price; snapshot) -> CO. Before publish, Sales sees no figures; after publish sees the price, not HPP/margin (A.8). The tolerance percentage is frozen into the CO.
- **frozen_zone**: ProCost and Quotation. Only recorded.

## Findings (recorded, not fixed)
1. **Reverse writes the ProDev flag directly.** `ReverseCostingService.php:94-95` sets `stat_confirm_sales=true` AND `stat_confirm_prodev=true`, while the layout is created `DRAFT` (line 126). A.20 says the ProDev flag is computed from a CONFIRMED layout. `syncProdev` only lowers it when no costing exists yet, but the costing already exists, so the flag stays 1 even though the layout is DRAFT. Has never happened in the data (the path was used 0 times).
2. **One column, two meanings.** `iqp_tier.price_excl/_incl` is read by the Inquiry Nota as "Target customer", but the only writer found is Reverse, which fills it with the costing result price (`ReverseCostingService.php:170-171`). See card `../inquiry-product/11_card_inquiry-and-part.md` finding 5.
3. **The formula login list differs from the rule summary.** `docs/ringkas/globaluserrules.md` (A.8) writes "rumus ProCost hanya login `johiwan`"; the mockup constant = `johiwan,wiwik2`; the code comment names three people (johiwan, Wiwik Ardiatno, Carrij Suhendra, the third has no account yet). Which applies needs to be confirmed by Jo. Only recorded.
4. **Two publish statuses.** `doc_status` knows `PENDING_APPROVAL` (publish condition, `costing.blade.php:569`), but the data has only DRAFT, CALCULATED, PUBLISHED; the `PENDING_APPROVAL` button path is not visible in the data. Not an error; not yet tested with data.
5. **Reverse is not atomic across DBs** (acknowledged in the code comment, lines 37-40): two connections, sequential commits.
6. **`Screens.php:40` registers the screen `sales-price` ("Prices from Pro-Cost")** but the file `resources/views/v2/screens/sales-price.blade.php` does not exist and there is no menu item `v2:sales-price`; the list entry appears outdated (not checked whether it is used elsewhere).
7. **The claim in `claude_vital_flows.md` "ProCost Mode #1 sudah ada via `pickLayoutGroup()`" is PROVEN**: the method exists at `pages/inquiry/costing.blade.php:352` and pulls geometry via `ProCostContextService::layoutGeometry` (lines 259-272).

## Already exists? Searched where, result
Searched: `published_flag`, `stat_confirm_procost`, `TierPricingResolver`, `ProCostContextService`, `price_per_pcs`, `canViewSalesPrice` in `app/`, `resources/`, `routes/`, `packages/`; tables `iqp_costing*` in `ddtsdi`. Result: the whole interface ALREADY EXISTS; no new table is needed. Price visibility in Quotation: see the Quotation card finding 3.

<!-- johiwan-only -->

## Worked example of the price flow (confidential; from mockup DB 2026-10-16)
- Published tier `IQ99KSP001`, qty 30.000: `price_per_pcs` 2.491,7647; `c_after_ppn_pc` 2.765,8588. `IQ99MC0001`, qty 20.000: 1.395,2941 / 1.548,7765. `IQ99MC0002`, qty 15.000: 2.525,8824 / 2.803,7294.
- Quotation lines use `price_per_pcs` (excl. PPN) as `unit_price`: 24 of 24 lines are exactly equal (none edited). PPN is recalculated by Quotation from `TaxRate::ppn()`, not taken from `c_after_ppn_pc`.
- The HPP -> margin -> price chain is in `10_engine_costing-advisor.md` (confidential part); this card does not read the HPP or margin columns.

## Added 2026-10-16 (Jo agreed)
`ProCostContextService::forPart` now also takes the selected tier qty and returns `bomLive`: BOM of ProDev (`SpecEngine::forWorkOrder`, read only) with availability per line from `MaterialPlanningEngine::availabilityFor`. The ProCost screen shows it as the accordion "BOM and availability" when the part is linked to ProDev (otherwise the old Inquiry BOM panel). It writes nothing and creates no PR/PO/booking. Cited line numbers of `ProCostContextService.php` and `costing.blade.php` below this change are about 30 lines lower. Test: `ProCostBomAvailabilityTest`.

## Added 2026-10-16 (Jo agreed): Book
The ProCost screen has a Book button in the panel BOM and availability: after Publish it runs MaterialPlanningEngine::persistPre for the inquiry and hold (stock of the pre-booking, paper only, ends by itself after INV_BOOKING_HOLD_WORKDAYS workdays); Release hold undoes it. No PR/PO. Test: ProCostBomAvailabilityTest.


- Re-checked 2026-10-18 after `app/Support/V2/Screens.php` gained a `Master` group in module FINACC (screen `acc-coa-form`, inserted after line 95); every line cited by this card is before line 96 and unchanged, content still true.
- Re-checked 2026-10-18 after `app/Support/V2/Screens.php` gained the screen `acc-journal-form` in the FINACC `Master` group (inserted after line 95); every line cited by this card is before line 96 and unchanged, content still true.
