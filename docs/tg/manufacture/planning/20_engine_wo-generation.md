# ENGINE card: Work Order Generation

> Written 2026-10-17 (Manufacture group). FACT = seen first-hand this session: code with line numbers, mockup DB (`--env=mockup`, connections `tm2*` = schema `ddt*`), tests I ran. FROM DOCUMENT = taken from an old document or from the registry text, not checked. Source read in full: `app/Services/Scheduling/WoGenerationService.php`.

- **Canonical name**: Work Order Generation. **Class**: `App\Services\Scheduling\WoGenerationService`. **Registry**: key `wo-generation`, mode `auto`. **Module/sub-module**: Manufacture > Planning > Work Order (screen `v2:production-wo`); the entry door is Sales > Order > Customer Plan Delivery.
- **Synonyms**: Post to Production, Create WO, "generate WO", F1 Job Order Generation (docblock).
- **Frozen status**: the CPDEL document flow that feeds it is frozen (see `../../sales/order/11_card_cpdel.md`). The engine itself belongs to PPIC. It builds the Work Order from ProDev output and never recomputes ProDev logic (CLAUDE.md section 2).
- **Concept cards**: `10_card_work-order.md`, `11_card_wo-operation-bop.md`, `12_card_wo-material-bom.md`, `16_card_upstream-interface.md`.

## Trigger (no background schedule)
Searched `routes/console.php` (no entry for this class), callers in `app/`, `resources/views`, `routes/`. FACT:
| Trigger | Method | File:line |
|---|---|---|
| CPDEL screen, button Post to Production | `generateFromDelivery` | `app/Support/V2/Data/Cpdel.php:93` |
| Work Order screen, Create Work Order picker (calls the same `Cpdel::post`) | `generateFromDelivery` | `app/Http/Controllers/V2/PpicController.php:321` |
| Work Order form, button PROCESS | `rebuildOperations`, `regenerateBom` | `app/Http/Controllers/V2/PpicController.php:455,457` |
| Sheet RESET on the BOP / BOM tab | `rebuildOperations`, `regenerateBom`, `engineBomCount` | `app/Services/Scheduling/Plan/SheetReset.php:113,91,57` |
| Dummy seeding command | `generateFromDelivery` | `app/Console/Commands/PpicSeedDummy.php:97` |

## Flow (text twin)
1. Refuse when the delivery line already has a non-cancelled WO (`WoGenerationService.php:57-59`) or when the CO `doc_status` is not `CONFIRMED` (`:71-73`). Rule A.16: every CO type may be posted once Confirmed and approved; Costing PUBLISHED is no longer required.
2. Source inquiry: the CO line's inquiry; a direct (INSTANT) CO line without inquiry uses the newest non-cancelled inquiry of the same `product_id` (`:65-68`).
3. No inquiry or no parts: ONE simple WO per delivery line, no operations and no materials; PPIC completes it (`:76-78`, `simpleWo` `:378-399`). Needs a Master Product, else exception.
4. Otherwise, in one transaction on `tm2prd`, ONE WO PER INQUIRY PART (`:83-100`): `wo_type=MTO`, `source_type=CO_CONFIRMED`, `wo_status=DRAFT`, `schedule_layer=SIMULATED`, `wo_no=PrdWo::nextWoNo()` (format `WO` + 2-digit year + 5 digits, `PrdWo.php:97-110`).
5. Ask ProDev: `SpecEngine::forWorkOrder(prodPart, iqpPart, qty)` returns `bop`, `bom` (`:104-105`, see `../../sales/prodev/20_engine_spec.md`). BOP step becomes one `prd_wo_operation` (seq `(i+1)*10`, `process_id`, machine or MANUAL, `qty` = step `qty_in`, `waste_qty`, `tolerance_qty`, `rate_band_value` = step target per hour) (`:107-125`).
6. When ProDev has no BOP yet: the routing snapshot of the inquiry part is copied (`:126-146`); the rate comes from `prod_part_process_machine_rate` (`lookupDefaultMachineRate`, `:401-413`).
7. BOM lines are written as `prd_wo_material` (`writeBom`, `:336-374`): Required = Standar = `qty_need` of the engine; a line with area not yet computable keeps 0; `None` lines are dropped; officer-deleted lines (tombstones in `prd_plan_decision`) are not written again; if nothing is left, ONE placeholder line "Material belum ditentukan di Inquiry / ProDev" is written.
8. Backward scheduling is persisted at creation (`BackwardSchedulingService::scheduleAndPersist`, `:157-164`); a `RuntimeException` (rate band incomplete) is swallowed.
9. Afterwards (not inside this class): `PlanEvents::replan` asks the auto-planner for a Draft (`Cpdel.php:100`); `WoPlanningEnricher` and `GateService` fill planning facts and gates (`PpicController.php:327-328` or inside `AutoPlanRunner::prepare`).

### PROCESS side (same class, WO still DRAFT or SIMULATED)
- `rebuildOperations` (`:182-256`): operations follow the BOP ProDev gives now. Same `process_id` stays (id, placement, target, progress kept; only `seq_no`/label renumbered when the officer has not re-ordered); new process gets a new operation; engine step that the officer deleted does not come back; an operation whose process left the BOP is removed ONLY when not CONFIRMED, not started, without output, and not added by the officer; else kept and counted. Nothing happens when the engine returns no BOP.
- `regenerateBom` (`:272-311`): deletes the engine's own lines (not officer-added), writes them again, then restores: officer Required with reason, chosen substitute, process id when the engine gives none, Waste/Spill overrides. Waste/Spill engine values are copied from the BOP step of the line's process only when both use the same unit (`writeWasteSpill`, `:319-329`).
- `engineBomCount` (`:259-266`): number of real BOM rows now; RESET refuses when 0.

## Input / Output
- **Input**: `CoOrderDelivery` (`co_order_delivery`), its CO and CO line, inquiry parts with processes and materials, `prod_part`, `SpecEngine` result.
- **Output (writes DB)**: `prd_wo`, `prd_wo_operation`, `prd_wo_material` (all `tm2prd`). `prd_wo_operation_machine` is written later by `WoPlanningEnricher`.
- **Who may overwrite**: PROCESS rewrites engine lines on a Draft WO; a WO with `wo_status` other than DRAFT/SIMULATED is refused (`:187`, `:274-276`).

## Formula (one place)
- WO quantity: `prd_wo.qty = co_order_delivery.ship_qty x iqp_part.qty_per_sku` (`qty_per_sku` 0 or empty counts as 1) (`:84`). Unit: the ordering unit of the CO line (pieces).
- WO due date: `prd_wo.due_date = co_order_delivery.ship_date` (`:96`), the CPDEL customer slot, not the CO line `due_date`.
- Line `material_needed_by`: set to the delivery `ship_date` at creation (`:148`, `:369`); the header value is replaced by backward scheduling.
- Operation `qty`: step `qty_in` of ProDev, else the WO quantity (`:116`).

### Worked example (computed from `ddt*` data, read only, 2026-10-17)
Of 65 WO rows, 63 carry `source_delivery_id`. For all 63: `qty` equals `ship_qty x qty_per_sku` of their delivery and the date of `due_date` equals the date of `ship_date` (0 exceptions; script compared both databases). The 63 WOs belong to 22 COs, all `CONFIRMED`. The other 2 (`WO2600250`, `WO2600260`) have no source and are `CANCELLED`.

## Edge cases
- Direct CO line without any inquiry/parts: simple WO without operations (A.3: output can still be recorded).
- A part without `prod_part_id`: `forWorkOrder` is skipped; BOP empty, so the routing snapshot is used and BOM is the placeholder line.
- Layout missing in ProDev: `SpecEngine` assumes 1 up per sheet and reports a warning (`SpecEngine.php:1072` and the ATTENTION item below).
- Double generation: refused with an exception (test below).

## Used by
`Cpdel::post` (Sales), `PpicController::createWo` / `processWo`, `SheetReset`. Registered `used`: "Customer Plan Delivery (Post to Production)".

## Open items
`docs/ENGINE-ATTENTION.md`: ATTENTION-ENGINE-01 (SpecEngine patch; status is kept there). Items 02 to 06 concern the BOM numbers this engine writes.

## Tests
`tests/Feature/ProductionWoGenerationTest.php` (5 tests), `tests/Feature/WoBomFromInquiryTest.php`, `tests/Feature/WoBomWasteSpillTest.php`, `tests/Feature/WoResetTest.php`, `tests/Feature/WoProcessApplyTest.php`. Results of this session are in `../90_findings_planning-ppic.md` section "Tests run".
Update 2026-10-18: `ProductionWoGenerationTest` was rewritten (5 passed on `--env=mockup`): each test builds its own Inquiry, CO, Plan Delivery and WO through `tests/Support/WoFixture.php` and cleans up; the generate test now checks the routing snapshot path (3 operations, Cetak rate 6000 from `prod_part_process_machine_rate`).

## Relations
- **source**: CPDEL row (`../../sales/order/11_card_cpdel.md`), ProDev engine (`../../sales/prodev/20_engine_spec.md`), Inquiry part (`../../sales/inquiry-product/11_card_inquiry-and-part.md`).
- **used_by**: `21_engine_wo-planning-enricher.md`, `22_engine_gate-service.md`, `23_engine_auto-plan-runner.md`, cards `10_card_work-order.md`, `11_card_wo-operation-bop.md`, `12_card_wo-material-bom.md`.
- **flows**: CO Confirmed -> CPDEL row -> Post to Production -> WO DRAFT -> PROCESS -> APPLY (`26_engine_plan-confirm.md`).
- **frozen_zone**: upstream flow (CO, CPDEL, ProDev) frozen; the WO side is PPIC.
- **also_named_by**: `11_card_process-and-routing.md`, `32_engine_maintenance-block.md`.

## Findings (recorded, not fixed)
1. **The registry lists only `generateFromDelivery`**, but the methods that PROCESS and RESET call every day (`rebuildOperations`, `regenerateBom`, `engineBomCount`) are public and NOT registered (`EngineRegistry.php:1347`). FACT.
2. **Registry text "Target rates are taken from the product and part code"** is true only in the fallback branch without BOP (`:126-146`). With a BOP, the rate is the step's `target_qty_per_hour` from ProDev (`:122`). FACT.
3. **Two schedulers at creation**: this class persists a legacy backward schedule (`target_start/target_finish` through `MachineCalendarService`: shift hours from `emp_shift_roster`/`emp_shift_detail`, fallback 08:00 to 20:00), while the v2 planner later writes `draft_*` using the machine calendar (07:00, shifts x hours). Two calendars decide the first dates of one WO. See `23_engine_auto-plan-runner.md`. FACT (code).
4. **`prd_wo.wo_status` never reaches IN_PROGRESS or COMPLETED in the v2 path** (see `10_card_work-order.md` finding 1). FACT (grep).
5. **`material_needed_by` of the material lines keeps the CPDEL ship date** (`:148`), not the start of the first operation that uses the material; only the WO header is replaced by backward scheduling. FACT (code).
6. **Primary keys**: `prd_wo.wo_id`, `prd_wo_operation.operation_id`, `prd_wo_material.detail_id` do not follow `id_<entity>` (CLAUDE.md section 5). FACT (`show columns`).

## Already exists? Searched where, result
`php artisan concept:find "work order"` and `"prd_wo"`: no card in `docs/tg` described the Manufacture side before this one; the Sales and Inventory cards only name `prd_wo.source_delivery_id` / `source_co_id`. Searched code: only this class creates WOs from delivery lines (grep `generateFromDelivery`: the five callers above). The old guide `docs/guides/production/01_work_order_bom_gates.md` exists (user's guide, not repeated).
- Re-checked 2026-10-20 after the roster print, the Work Order print and Process Rate moved to v2 (rule D.25, T9): `routes/web.php` replaced the v1 `/hrm/shift-roster/print`, `/production/wo/{wo}/print` and `/production/process-rate` routes by redirects on the same lines (no line added or removed), `app/Support/Menu/MenuRoutes.php` maps `PROD_PROCESS_RATE` to the v2 screen on the same line, `app/Support/V2/Grid/Grids.php` and `app/Support/V2/Screens.php` list the new rate sheets and the `production-process-rate` screen on existing lines, `routes/v2_hrm.php` and `routes/v2_production.php` gained one route each at the end of the file, and the v2 Work Order screen shows a Print button on the same line as Back (no line shift). Every cited line number is unchanged and this card's content is still true.
- Re-checked 2026-10-20 after the Work Order execution moved to v2 (rule D.25, T9): `routes/web.php` replaced the v1 `/production/wo`, `/production/wo/new` and `/production/wo/{wo}` routes by redirects on the same lines (no line added or removed), `app/Support/Menu/MenuRoutes.php` maps `PROD_WO_LIST` and `PROD_OUTPUT` to the v2 screen on the same lines, `app/Support/V2/Lookups.php` registers two lookups on the existing `material` line and two private helpers after the last method, `routes/v2_production.php` gained the execution routes at the end of the file and the v2 Work Order screen includes two partials (QC / breakdown / subcontract card and material substitution card; the card line numbers of the screen are unchanged). The v1 component `pages::production.wo-entry` no longer exists: its rules now live in `WoQcGateService`, `WoBreakdownService` and `WoMaterialSubstitutionService`. Every cited line number is unchanged and this card's content is still true. Test count: `ProductionWoGenerationTest` has 3 tests since 2026-10-20 (the two tests that drove the v1 page were removed with it; WO generation itself is still tested).
- Re-checked 2026-10-20 after the Quotation and Customer Order entry rules moved from the v1 components to services (rule D.25, T10): `routes/web.php` replaced the v1 routes `/quotation/new`, `/quotation/{quotation}/edit`, `/quotation/{quotation}/print`, `/co/{order}/edit` and `/co/{order}/print` by redirects on the same lines (no line added or removed), `routes/v2.php` changed only two comment lines, `app/Support/V2/Screens.php` changed the description text of the `quotation` and `customer-order` rows on the same lines, and `app/Support/V2/Data/Quotation.php` and `CustomerOrder.php` now call `QuotationEntryService` and `CoEntryService` instead of the v1 Livewire components (the `component()` helper is gone; cards that cite lines of those two files were remapped from the diff). The v1 pages `pages::quotation.entry`, `pages::co.entry` and the two v1 prints are archived in `tmerp_unused/`. Every other cited line number is unchanged and this card's content is still true.
