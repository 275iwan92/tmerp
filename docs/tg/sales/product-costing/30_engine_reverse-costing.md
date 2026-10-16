# ENGINE card: Reverse Costing

> DRAFT GROUP C (2026-10-16), awaiting Jo's approval. Source: `app/Services/Costing/ReverseCostingService.php` read in full (198 lines). Tests run: `CostingInstantModeTest` (2 passed, 14 asserts; one test covers Reverse). No price figures in this card (HPP figures do not change, only asserted by tests).

- **Canonical name**: Reverse Costing. **Class**: `App\Services\Costing\ReverseCostingService`. **Registry**: key `reverse-costing`, mode `auto`, group Pro-Cost. **Module/sub-module**: Sales > Inquiry & Quotation > Pro-Cost (menu Pro-Cost Instant, `v2:sales-procost-instant`).
- **Frozen status**: ProCost and Inquiry/ProDev = frozen zone. Three decisions by Jo in the code comment (do not ask again): the Inquiry resulting from Reverse is always type NEW; ProDev (layout group) is always born DRAFT; existing costing figures do not change.
- **Synonyms**: Reverse button, Instant -> Integrated, "balik silsilah".
- **NOT the same as**: cancelling a costing or `reversed_at` on Stock Reversal (Inventory module, only a similar name).

## Trigger (no background / schedule)
None in `routes/console.php`, Jobs, Observers. FACT: the only caller is the **Reverse** button in `resources/views/pages/inquiry/costing-instant.blade.php:467` (`wire:click="reverse"`, confirmation `wire:confirm`, disabled when the document is read-only) -> `reverse()` line 237 -> `ReverseCostingService::reverse` (line 240). Success redirects to `inquiry.costing` for the new Inquiry; failure shows `engineError`.

## Flow (text twin, no diagram: one straight path with one transaction)
1. Check prerequisites: mode INSTANT, `customer_id` filled, `product_name_draft` filled, at least one part. A failure = `RuntimeException` with an Indonesian message.
2. Open two transactions (`tm2mst`, `tm2sdi`).
3. Create the Inquiry number, then a DRAFT Master Product in `tm2mst` (code `REV-<nomor inquiry>`).
4. Create a NEW Inquiry (`source = REVERSE`, `sub_state = PRICE_CALCULATED`).
5. Per costing part: create `prod_part`, DRAFT `prod_layout_group` (+ pivot `prod_layout_group_part`), `iqp_part`; link back to `iqp_costing_part` (`part_id`, `prod_layout_group_id`, `prodev_part_id`). The part `data_source` STAYS manual; the `i_*` and `c_*` inputs are not touched.
6. Per price tier: create `iqp_tier` (qty, excl/incl price from the costing tier) and link `tier_id`.
7. Flip the costing: `inquiry_id` filled, `mode` becomes INTEGRATED, `reversed_at/by` filled.
8. Commit `tm2mst` first, then `tm2sdi`; a failure midway = rollback of both (see Finding 1).
**Officer decision points (A.7)**: Reverse needs a human click and confirmation; its resulting ProDev can only be Confirmed by a human.

## Input / Output
- **Input**: `tm2sdi.iqp_costing` (mode, `customer_id`, `product_name_draft`, dimensions), `iqp_costing_part` (part type, dimensions, plano, `i_cut_per_plano`, `qty_per_set`, `no_urut`), `iqp_costing_price_tier`.
- **Output (writes)**: `tm2mst.prod_product`, `prod_part`, `prod_layout_group`, `prod_layout_group_part`; `tm2sdi.iqp_inquiry`, `iqp_part`, `iqp_tier`; updates `iqp_costing_part` and `iqp_costing_price_tier` (link columns only) and `iqp_costing` (`inquiry_id`, `mode`, `reversed_*`).
- **Who may overwrite**: there is no path back (INTEGRATED cannot be reversed to INSTANT through this code; the button is only for INSTANT). The resulting Master Product/ProDev are edited by the officer as usual.

## FORMULA
No price formula. Only unit mapping (FACT, lines 102-151): `cm_length = i_dim_panjang_mm / 10` (4 decimals), `cm_width = i_dim_lebar_mm / 10`; plano mm = plano cm * 10; `up_count = max(1, round(i_cut_per_plano))`; `qty_up_in_group = 1`; `flat_w = dimP (mm)`, `flat_h = dimL (mm)`, `flat_area = dimP * dimL`; `part_code = 'P' . no_urut`; `mastercard_code = product_code . '-' . no_urut`. The part dimensions are used if present, otherwise the costing header dimensions.

## Edge cases
- Costing already INTEGRATED: rejected ("cuma berlaku utk costing mode INSTANT").
- Without customer or product name or part: rejected with a message.
- A second Reverse on the same costing: rejected by the mode check.
- Empty dimensions: `cm_length/width` 0, `flat_*` null.
- Current ddt* data: 9 INSTANT costings, 17 INTEGRATED, 0 with `reversed_at`, 0 inquiries from `REVERSE` (FACT, query 2026-10-16), so this path is touched only by tests.

## Used by (relations)
- **source**: Pro-Cost Instant. **downstream**: Inquiry (list), ProDev (DRAFT layout that a human must Confirm), Master Product (DRAFT, `card_status` not yet approved), Quotation (needs a PUBLISHED costing, A.1), Material Planning (pre-booking reads a PUBLISHED costing without CO). Rule: Quotation must be integrated; Reverse is the entry of an instant costing into that path.

## Open items
See `docs/ENGINE-ATTENTION.md`: no item specific to Reverse (read in full).

## Findings (recorded, not fixed)
1. The class comment (lines 37-40) acknowledges two separate transactions: if `tm2sdi` fails to commit after `tm2mst` has committed, the master rows remain; `rollBack` on an already committed connection no longer undoes those rows (Laravel behavior, not tested). Code: commit `tm2mst` line 187, `tm2sdi` line 188.
2. An Inquiry resulting from Reverse is immediately `stat_confirm_sales = true` and `stat_confirm_prodev = true` (lines 94-95), although the created ProDev has status DRAFT (line 126) and the class comment states "Confirmed cuma lewat klik manusia". The upstream A.20 flag is already on before ProDev is Confirmed. The behavior of this flag against `InquiryStage::syncProdevForParts` has NOT been tested (that function recalculates the flag when ProDev is saved). A decision by Jo is needed if this is unintended.
3. `prod_layout_group.up_count` is filled from `i_cut_per_plano` (line 125). Elsewhere `i_cut_per_plano` = print sheets per plano and `i_isi_per_lb` = UP per print sheet (`ProCostContextService.php:110-113` and `CostingEngine.php:80-81`); Material Planning uses `up_count` of the CONFIRMED layout as UP per plano (`MaterialPlanningEngine.php:554`). If `i_isi_per_lb > 1`, the Reverse `up_count` is smaller than the real UP. The real impact only occurs after the layout is Confirmed by a human (until then a DRAFT layout is not used by Material Planning). ASSUMPTION, not tested.
4. `iqp_part` `flat_w/flat_h/flat_area` are filled from the product dimensions (P x L), whereas in the Inquiry path those columns hold the flat spread from TMDieline. ASSUMPTION of a wrong meaning; not checked against the users of that column.
5. A Reverse part does not carry `dieline_spec`/`type_code`/artwork (not in `IqpPart::create`), so a Master Card born from this flow has no dieline until completed in ProDev (A.17).
6. The registry (`EngineRegistry.php:322`) writes "Pro-Dev layout, Created as Draft; only a person can Confirm it": correct for the layout, but see Finding 2 for the flag.

## Tests
`tests/Feature/CostingInstantModeTest.php::test_instant_screen_reproduces_legacy_raja_bakery_and_reverse`: after Reverse, `mode` = INTEGRATED, the Inquiry type is `NEW`, and the main tier HPP equals the value before Reverse. No dedicated tests for: failure midway, the `stat_confirm_*` flags, the `up_count` value, or the `flat_*` mapping.
