# Concept card: Tooling Master (plates and dies)

> Written 2026-10-17 (Manufacture group). "FACT" = seen first-hand in this session: mockup DB (`--env=mockup`; counts = `count(*)` today) and code with line numbers. "FROM DOCUMENT" = not checked. Do not confuse with the "tooling cost" lines of Sales costing.

- **Document type**: concept card. **Module/sub-module**: Manufacture > PPIC > PPIC Settings > Tooling Master (`PROD_MST_TOOLING`, `v2:prod-tooling`).
- **Canonical name**: Tooling asset = `tm2mst.prod_tooling_asset` (4 rows): a plate set or a die with its status and its mounting minutes.
- **Synonyms**: pisau pond, mall, die, plate set, plat, matras emboss, foil die, `tooling_code`, `dieline_ref`.
- **NOT the same as**: the dieline drawing and Master Card (`../../sales/inquiry-product/14_card_dieline-and-master-card.md`), the tool register of the warehouse (`../../inventory/warehouse/19_card_tool-register.md`), tooling cost in Inquiry / ProCost.

## Facts
| Item | Content | Evidence |
|---|---|---|
| Table | `prod_tooling_asset`: 4 rows, PK `id_tooling_asset`; `tooling_code`, `tooling_type`, `product_id`, `dieline_ref`, `size_note`, `location`, `status`, `impressions_planned`, `impressions_used`, `setup_first_min`, `setup_repeat_min`, `activate`. Today: `DIE-PPIC-14400` and `DIE-PPIC-14403` (CUTTING_DIE), `PLT-PPIC-14400` and `PLT-PPIC-14403` (PLATE_SET), all READY, `product_id` 14400 or 14403, `dieline_ref` empty. | FACT, query |
| Values | Types: PLATE_SET, CUTTING_DIE, EMBOSS_DIE, FOIL_DIE. Statuses: NOT_MADE, IN_MAKING, READY, IN_USE, NEEDS_REPAIR, RETIRED. | FACT, `ProdToolingAsset.php:15-17` |
| Gate rule | A process with `requires_die` needs a CUTTING_DIE, with `requires_plate` a PLATE_SET. The best asset by `dieline_ref` of the WO OR by `product_id` decides: READY or IN_USE passes; any other status BLOCKS; no asset = READY with a note. | FACT, `GateService::tooling` (`GateService.php:187-209`), `../planning/22_engine_gate-service.md` |
| Mount minutes | Only `setup_first_min` is read (by `PlanLoader::toolMinutes`, by `dieline_ref` only) and used as the die mount time of the next job; EMPTY gives the constant 15 (die) or 10 (plates). `setup_repeat_min`, `impressions_planned`, `impressions_used` are read by no code. | FACT, `PlanLoader.php:246-259`, grep |
| Coverage | WOs of products 14400 and 14403: 17 of the 55 non-cancelled WOs have a registered die and plates; the other 38 have no registered tooling, so their die and plate operations pass the gate with the note "Tooling is not registered in the tooling master". | FACT, query |
| Screen | `ToolingLookup` (v2 lookup with form): code, type, product, dieline reference, size note, location, status, planned and used impressions, first and repeat setup minutes. Guide text says Ready or In use lets scheduling plan the job. | FACT, `ToolingLookup.php:36-79` |

## Relations
- **source**: product master, Master Card / dieline code, warehouse tool register (separate).
- **used_by**: `../planning/22_engine_gate-service.md`, `../planning/25_engine_setup-time.md`, `../planning/14_card_gates-and-readiness.md`.
- **flows**: tooling status -> tooling gate -> plan; die code -> mount minutes -> setup.
- **frozen_zone**: none.

## Findings (recorded, not fixed)
1. **The match key for the setup time is `dieline_ref` only, while the gate matches `dieline_ref` OR `product_id`.** Both tooling rows in the data have `dieline_ref` empty and only `product_id`, so `setup_first_min` can never be found for them (`PlanLoader.php:254`), and the gate passes by `product_id`. FACT (code, query).
2. **Status RETIRED is allowed in the master but missing from the gate's ordering** (`FIELD(status, 'READY', 'IN_USE', 'IN_MAKING', 'NEEDS_REPAIR', 'NOT_MADE')`, `GateService.php:200`): MySQL gives such a row the value 0 and sorts it first, so a retired asset could be picked ahead of a READY one and BLOCK the job. No RETIRED row exists today, so this is derived from the code, NOT observed. FACT (code).
3. **EMBOSS_DIE and FOIL_DIE are never checked**: the gate asks only for CUTTING_DIE and PLATE_SET; EMBOSS and HOT PRINT processes have `requires_die = 0`. FACT.
4. **Impressions are not counted**: `impressions_used` is not increased by production output, so die wear is not tracked. FACT (grep).
5. **The model header comment says the repeat minutes feed the Setup Time Engine**; the code never reads them. FACT.
6. **Tooling status is not linked to the physical tool register** of the warehouse (`../../inventory/warehouse/19_card_tool-register.md`): two registers. FACT (no join in code).

## Already exists? Searched where, result
`concept:find "machine"`, `"gate"`: the warehouse tool register card exists in Inventory; no card for this master. Searched: one tooling master for planning (`prod_tooling_asset`).
