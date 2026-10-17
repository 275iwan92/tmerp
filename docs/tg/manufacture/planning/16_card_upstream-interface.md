# Concept card: Upstream interface of the Work Order (Inquiry, ProDev, ProCost, CPDEL)

> Written 2026-10-17 (Manufacture group). "FACT" = seen first-hand in this session: code with line numbers and mockup DB (`--env=mockup`). This card only lists WHAT the Work Order takes from upstream and WHERE; the facts of the upstream tables are in the Sales cards and are not repeated.

- **Document type**: concept card (interface). **Module/sub-module**: Manufacture > Planning, receiving side of Sales.
- **Canonical name**: WO upstream interface = the set of reads that `WoGenerationService`, `WoPlanningEnricher` and `Ppic` make into Sales and ProDev tables.
- **Synonyms**: hulu, source chain, Post to Production, "BOP/BOM from ProDev", Master Card inherited by PPIC.
- **Rule** (CLAUDE.md section 2): upstream owns the data; PPIC only continues. PPIC must not add ProDev or ProCost logic.

## Facts
| From | What the WO takes | Where in PPIC code | Evidence |
|---|---|---|---|
| CPDEL (`co_order_delivery`), card `../../sales/order/11_card_cpdel.md` | `id_delivery` (to `source_delivery_id`), `order_id` (to `source_co_id`), `ship_qty` (x `qty_per_sku` = WO qty), `ship_date` (= WO due date). A row must be `PLANNED` and not yet Posted; Posting is Sales' button; after it date and qty are locked in CPDEL. | `Cpdel::post` (`Cpdel.php:80-103`), `WoGenerationService.php:55-100` | FACT |
| Customer Order (`co_order`, `co_order_item`), card `../../sales/order/10_card_customer-order.md` | `doc_status` must be `CONFIRMED` (refused otherwise); item `inquiry_id` or, for an INSTANT CO, `product_id` (newest non-cancelled inquiry of that product is the source); `customer_id` (for the customer tier and the name on screens). | `WoGenerationService.php:61-73`, `WoPlanningEnricher.php` via `PlanFacts` | FACT |
| Inquiry part (`iqp_part`, `iqp_part_material`, `iqp_layout`, `iqp_part_process`), card `../../sales/inquiry-product/11_card_inquiry-and-part.md` | one WO per part; `qty_per_sku`; `part_code` / `part_name`; `prod_part_id` (the ProDev part); primary board grammage (`iqp_part_material.gram_weight`); colour setting `cfg_color`; layout (ups, plano, knife, canvas); routing snapshot when ProDev has no BOP. | `WoGenerationService.php:83-146`, `PlanFacts.php` | FACT |
| ProDev (`SpecEngine::forWorkOrder`), cards `../../sales/prodev/10_card_prodev-interface.md`, `../../sales/prodev/20_engine_spec.md` | `bop` (steps with process, machine, quantity in, waste, tolerance, target per hour) and `bom` (lines with role, name, material id, quantity needed, unit, BOP process). Computed for the WO quantity; the officer's saved override in ProDev wins over the engine. | `WoGenerationService.php:104-105,190,263,279` | FACT |
| ProDev masters (`prod_part`, `prod_layout_group`, `prod_master_card`, `prod_part_ink`) | die code (`dieline_ref`, then `die_code`, then master card `knife_code`, then layout `knife_code`), inks per part (colour wash), assembly parent (`parent_part_id`) for component order. | `PlanFacts.php`, `PlanLoader.php:206-244` | FACT |
| Master Card (`prod_master_card`, `prod_master_card_file`), card `../../sales/inquiry-product/14_card_dieline-and-master-card.md` | the Work Order form shows the card (dieline, artwork, size P x L x T and flat size) read by product, never retyped; the Create Work Order picker offers only delivery lines of products that have a card, and shows "Complete / Master Card incomplete: dieline, artwork, size". | `Ppic::masterCard` (`Ppic.php:1405`), `Ppic::pendingDeliveries` (`Ppic.php:1506`) | FACT |
| Tooling master (`prod_tooling_asset`), `../ppic/14_card_tooling.md` | die and plate set status by `dieline_ref` or `product_id`. | `GateService::tooling` | FACT |
| ProCost (`iqp_costing*`), card `../../sales/product-costing/11_card_procost-interface.md` | NOTHING. No planning code reads a costing table; no table in `ddtprd` holds price, cost, HPP or margin (only the unused substitution cost columns and `spc_change_request.cost_impact`). Costing stays confidential (A.8, A.9). | grep and `information_schema` query | FACT |
| Material Planning (`MaterialPlanningEngine::plan`) | per CO and part: shortage and arrival dates (second source of the material gate). | `GateService.php:57-87` | FACT |

### Counts (mockup DB, 2026-10-17)
63 of 65 WOs carry the full chain (delivery, CO, part) and the delivery row of every non-cancelled WO is Posted; 22 distinct COs, all `CONFIRMED`. 55 non-cancelled WOs have a `layout_json`, only 23 have `ups_per_sheet` and sheet size (`10_card_work-order.md`).

## Relations
- **source**: the Sales cards named in the table.
- **used_by**: `20_engine_wo-generation.md`, `21_engine_wo-planning-enricher.md`, `22_engine_gate-service.md`, `10_card_work-order.md`, `11_card_wo-operation-bop.md`, `12_card_wo-material-bom.md`.
- **flows**: Inquiry -> ProDev (BOP, BOM, layout, Master Card) -> ProCost -> Quotation -> CO -> CPDEL -> Post to Production -> WO.
- **frozen_zone**: Inquiry Product, ProDev, ProCost, Quotation, Customer Order, CPDEL (documents and flow). Do not change them from the Manufacture side.

## Findings (recorded, not fixed)
1. **The rule "no WO without a READY Master Card" is enforced only in the picker, and only for the existence of a card.** The comment in `Ppic::pendingDeliveries` says the WO follows the card; the picker shows "incomplete" lines but still allows them; `PpicController::createWo` does not check completeness; and Post to Production on the CPDEL screen (`Cpdel::post`) checks no card at all. FACT (code).
2. **Direct COs without inquiry make a WO with no operations or materials** (`simpleWo`); the picker shows them as "Simple Work Order (no operations)". Allowed by A.3, recorded here so that the planner is not surprised. FACT.
3. **Three places pick the die code in different orders**: `PlanFacts` (part `dieline_ref`, `die_code`, card knife, layout knife) vs the ProDev layout group knife vs the tooling lookup by `dieline_ref` or `product_id`. Same code is assumed. FACT (code); mismatch in data not measured.
4. **The WO copies upstream data once and refreshes it by hand**: header facts use `??` and are not overwritten by later PROCESS or Refresh (`WoPlanningEnricher.php:31-36`), so a changed layout in ProDev updates the BOP/BOM on PROCESS but NOT `ups_per_sheet`, sheet size, grammage, colours or die code already stored. The fingerprint (`Ppic::fingerprint`) does notice a changed ProDev part (stamps), so PROCESS asks to be run again. FACT (code).
5. **The old plan note promises a frozen copy "like other transactions"** (`docs/design-notes/ppic/15_ppic-tech-wo-knowledge.md`); in fact BOP and BOM are rewritten on PROCESS for a Draft WO, and only an applied WO is frozen. FACT (code), the intent is explained in the user's guide section 1.

## Already exists? Searched where, result
`concept:find "work order"`, `"prd_wo"`: `docs/tg/00_big-map.md` item 4 and `02_relations.md` name the arrows "Sales -> Manufacture" as one-sided; this card is the Manufacture side. Searched: no second route creates a WO.
