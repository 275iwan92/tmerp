# Concept card: Work Order material line (BOM as the WO sees it)

> Written 2026-10-17 (Manufacture group). "FACT" = seen first-hand in this session: mockup DB (`--env=mockup`, `tm2*` = `ddt*`; counts = `count(*)` today), code with line numbers, and the output of `Ppic::bomSheet` for one WO (read only). "FROM DOCUMENT" = not checked.

- **Document type**: concept card. **Module/sub-module**: Manufacture > Planning > Work Order, accordion "Bill of Material".
- **Canonical name**: Work Order material line = `tm2prd.prd_wo_material` (one row per material of a WO, the copy of the ProDev BOM line with the numbers PPIC works with: Required, Release, Purchase).
- **Synonyms**: BOM line, BOM sheet, material line, `detail_id`, "bahan WO", Required / Release / Purchase / Available / On hand.
- **NOT the same as**: the ProDev BOM (`prod_part_material`, `iqp_part_material`, owner ProDev, `../../sales/prodev/20_engine_spec.md`), the Material Planning demand line (`prd_mrp_*` and the engine in `../../sales/order/30_engine_material-planning.md`), the booking (`rm_booking`, card `15_card_material-availability.md`), the pick list (`../../inventory/warehouse/13_card_pick-list.md`).

## Facts
| Item | Content | Evidence |
|---|---|---|
| Table | `prd_wo_material`: 370 rows, PK `detail_id`, FK `wo_id` to `prd_wo`. Roles in non-cancelled WOs: TINTA 110, KERTAS PLANO 43, TALI PACK 43, PLASTIK PACK 43, LEM LAMINASI BAHAN 24, PLASTIK LAMINASI 21, LEM LAMINASI 21, SHEET 15, MATERIAL (placeholder) 12, VARNISH UV 4, FOIL HOT PRINT 4, SF 2, VARNISH 2. | FACT, query |
| Born | Written from the ProDev BOM for the WO quantity: `qty_required` = `qty_standar` = engine `qty_need`, `uom`, `material_role`, `material_name`, `process_id` (the BOP step that consumes it), `material_id` (empty while the paper is not chosen), `material_needed_by`. A BOM that comes out empty gets one placeholder line "Material belum ditentukan di Inquiry / ProDev" (role MATERIAL, quantity 0). | FACT, `WoGenerationService.php:336-374` |
| Four numbers | **Required** = `qty_required` (engine, editable). **On hand** and **Available** are read live: Available = on hand of usable locations minus all open bookings (`AvailableStock::of`). **Release** = the stored `qty_release` if set, else Available. **Purchase** = `max(0, Required - Release)`. Release and Purchase are stored only when the officer changed a line (`qty_release`, `qty_purchase` are NULL in all 370 rows today). | FACT, `Ppic.php:1147-1196`, `GateService.php:91-100` |
| Officer edit | Allowed only while the WO is not applied. Required, Release, Purchase, Waste and Spill can be typed; Release must be between 0 and Available (editing Purchase sets Release = Required - Purchase, same limits); a line that differs from the engine needs ONE reason (`override_reason`, max 190 characters); `qty_standar` keeps the engine number; every change is a decision `MATERIAL_QTY`. Add row and Delete row are decisions `ROW_ADDED` and `ROW_DELETED`; a line without process can get one of the BOP processes (`MATERIAL_PROCESS`). | FACT, `PpicController.php:944-1036` |
| Waste and Spill | `waste_engine_qty` and `spill_engine_qty` are copied from the BOP step of the line's process only when both use the same unit; `waste_override_qty` and `spill_override_qty` are the officer's values; Required already carries them, so changing them moves Required by the same difference. | FACT, `WoGenerationService.php:319-329`; `PpicController.php:944-1036` |
| Substitute | `is_substituted`, `substitute_material_id`, `substitute_reason`, `substitute_approved_by/at`: set by the ProDev spec-change service (`SpecChangeService.php`) and the v1 page; a chosen substitute survives a rebuild and the gate judges it by the substitute's stock. The v2 form has no Substitute button. Table `prd_wo_material_substitution`: 0 rows. 0 of 370 lines are substituted. | FACT, grep; query |
| Rebuild | PROCESS calls `regenerateBom`: engine lines are deleted and written again, officer Required with reason, chosen substitute, process id and Waste/Spill overrides come back; officer-added lines stay; officer-deleted engine lines do not return. RESET clears the overrides and rebuilds; it refuses when the engine gives no row. | FACT, `WoGenerationService.php:272-311`, `SheetReset.php:79-92` |
| Data quality today | Of 55 non-cancelled WOs: 41 have a line with `qty_required > 0` and no `material_id` (paper or sheet not chosen); 12 have only the placeholder line; all 110 TINTA lines have Required 0; 237 of 370 lines have Required 0. | FACT, query |
| Access | Same as the WO: read with module PROD, change with the flag `PPIC_SCHEDULING`. | FACT, `PpicAccess.php` |

### Worked example (computed from `ddt*`, read only, 2026-10-17: `Ppic::bomSheet` and `GateService::materialShortages` for `WO2600301`, quantity 10000)
| Line | Required | On hand | Available | Release | Purchase |
|---|---|---|---|---|---|
| Paper Duplex 350gr (KERTAS PLANO, no material chosen) | 0 | n/a | n/a | 0 | 0 |
| BRITE INK BLACK, CYAN, MAGENTA, YELLOW (TINTA, KG) | 0 each | 10 | 10 | 10 | 0 |
| TALI RAFIA (TALI PACK, M) | 75 | n/a | n/a | 0 | 75 |
| WRAPPING PLASTIK (PLASTIK PACK, M) | 37.5 | n/a | n/a | 0 | 37.5 |
The shortage list has two lines (TALI RAFIA and WRAPPING PLASTIK, state `SHORT`); the gate note is "Buy 75 M TALI RAFIA; 37.5 M WRAPPING PLASTIK." and the material gate is WAIT. Release of the ink lines is 10 although Required is 0.

## Relations
- **source**: ProDev BOM through `20_engine_wo-generation.md` (`../../sales/prodev/20_engine_spec.md`), stock and booking (`15_card_material-availability.md`).
- **used_by**: gates (`22_engine_gate-service.md`), APPLY (`26_engine_plan-confirm.md`), pick list and stock out (`../../inventory/warehouse/13_card_pick-list.md`), shortage detection (`../../inventory/batch-quality/15_card_shortage-request.md`), usage rate (`../../sales/prodev/30_engine_usage-rate.md`), PPIC Workspace tab Material control.
- **flows**: ProDev BOM -> lines (Required) -> officer edits (reason) -> Available read live -> Release / Purchase -> gate WAIT or BLOCK -> APPLY allowed only when no shortage -> (outside this module) Material Planning books, warehouse issues.
- **frozen_zone**: the BOM itself is ProDev (frozen). Booking and reserve are ProCost territory (Jo 2026-10-13).
- **also_named_by**: `10_card_work-order.md`, `14_card_gates-and-readiness.md`, `16_card_upstream-interface.md`.

## Findings (recorded, not fixed)
1. **Release default is the whole Available, even above Required.** Rule A in `docs/globaluserrules.md:274` says Release = Available "tidak melebihi Required" so that Purchase never goes negative; the code releases `Available` as it is (`GateService::release`) and only floors Purchase at 0, so a line with Required 0 shows Release 10 (example above). The change is documented in the code remark as the new formula of 2026-10-13, so the two documents of the same day differ. FACT.
2. **`qty_purchase` is stored but no reader uses the stored value** (`bomSheet` and the gate recompute Purchase); it is part of the PROCESS fingerprint only. FACT (grep).
3. **TINTA lines are always Required 0** (area/ink consumption not computed, as the code remark for foil and UV says), and 110 lines are ignored by the gate. Material Planning still computes an ink need and blocks 12 WOs (`22_engine_gate-service.md`). Two numbers for one need. FACT.
4. **Unit not converted** between a BOM line (LBR, M, KG) and the stock (ATTENTION-ENGINE-04). FACT (code).
5. **`cutting_yield_waste_qty` and `material_needed_by` of the lines** are not read by v2 screens (`cutting_yield_waste_qty` only by the Procurement shortage readers); line `material_needed_by` keeps the CPDEL date (`20_engine_wo-generation.md`). FACT (grep).
6. **Placeholder-only WOs pass the own-line gate** (a line with Required 0 is ignored), so 12 active WOs are judged by Material Planning alone. FACT (code and query).

## Already exists? Searched where, result
`concept:find "work order"`, `"booking"`: stock and booking cards exist in Inventory; no card described the WO material lines. Searched code: Required, Release and Purchase are produced only in `GateService` and shown in `Ppic::bomSheet` (single source). The older formula (Release = min(Available, Required)) is quoted in the code remarks as "SEBELUM". User's guide: `docs/guides/production/01_work_order_bom_gates.md`.
