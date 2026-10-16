# ENGINE card: Spec Engine (BOP, BOM, Ink)

> DRAFT GROUP C (2026-10-16), awaiting Jo's approval. Source: `app/Services/ProDev/SpecEngine.php` read in full (1248 lines). Test run: `WoBomFromInquiryTest` (1 passed, 60 asserts). The example numbers were computed myself from `ddt*` data via `forWorkOrder` (without writing to the DB) and hand-checked.

- **Canonical name**: Spec Engine. **Class**: `App\Services\ProDev\SpecEngine`. **Registry**: key `spec`, mode `suggest`, group Pro-Dev. **Module/sub-module**: Sales > Inquiry & Quotation > Pro-Dev (downstream: Production > Work Order).
- **Frozen status**: ProDev = frozen zone. The `processOfBom` patch was applied with Jo's permission on 14 Oct (see ATTENTION-ENGINE-01, status DONE there). Do not change the flow without Jo's permission.
- **Synonyms**: BOP BOM engine, Bill of Process, Bill of Material, Ink Configuration, "ProDev engine".
- **NOT the same as**: `ImpositionEngine` (geometry, card `10_engine_imposition.md`); **NOT** `CostingEngine` (price).

## Trigger (no background / schedule)
Searched in `routes/console.php` (none), `app/Jobs` (none), Observers (none). FACT, all callers:
| Trigger | Method | File:line |
|---|---|---|
| ProDev: BOM/Ink/BOP panel recomputed (JS `w.call('generateSpec', ...)`) | `generate` | `resources/views/pages/production/prodev.blade.php:347` (JS `:2279`); route v2 `routes/v2.php:1667-1675` (`Prodev::CALLS`: `generateSpec`, `saveLayout`) |
| ProDev: Save / Confirm button (`saveLayout`) | `generate` then `persist` | `prodev.blade.php:316-318` |
| ProDev: material search dropdown | `stockFor` | `prodev.blade.php:373` |
| ProDev: initial screen data | `saved`, `tierQty` | `prodev.blade.php:673-674` |
| WO creation from the CPDEL delivery schedule | `forWorkOrder` | `WoGenerationService.php:105` (`generateFromDelivery`) |
| PROCESS button in PPIC (re-arrange operations) | `forWorkOrder` | `WoGenerationService.php:190` (`rebuildOperations`), `:263` (`engineBomCount`), `:279` (`regenerateBom`); controller `PpicController.php:444` (`processWo`) |
| Master Card dummy data generator | `persist` (via `saveLayout`) | `app/Services/MasterCard/MasterCardDummyEngine.php:396` |
| Library `x-mp::prodev.workbench` (devlib.test) | does not call | `packages/mockup-parts/resources/assets/js/components/prodev/workbench.js:38` only returns sample data (`this.data.spec`) |
`ProductMasterSync` only mentions this engine in a comment (FACT, grep: no call).

## Flow (text twin of diagram `../img/spec-engine.svg`)
1. Prepare the context: finishing (from `iqp_part_finishing`, or `prod_part_finishing` when without Inquiry = Mode #2), material chosen by Sales, active rules `prod_bop_rule` (`part_type` empty or equal to the part type), layout decision (sheet, plano, machine, UP, qty).
2. Determine the conditional facts: `PLANO_NEEDS_CUT`, `MULTI_LAYER`, `NEEDS_GLUE`.
3. **Ink**: from the colour notation `a/b` (front/back) and the artwork analysis, build ink rows (CMYK if >= 4 units, the rest spot).
4. **BOM**: PLANO PAPER row (always), SHEET/SF layer (or "None"), then rows from the rules that have `material_pattern`.
5. **BOP**: each rule that has a process becomes a step (STANDARD if the condition matches; FINISHING once per chosen finishing). Adjacent steps with the same `merge_group` are merged. Process name, in-house status and hour target are filled (`decorate`).
6. **BACKWARD qty chain** from the order qty (`chain`): each step receives the output of the next step + waste + tolerance. The first sheet coming in = `printSheets`.
7. **BOM requirement** is computed from `printSheets` (`figures`) and compared with warehouse stock.
8. Officer override (the edited `bop`/`bom`/`inks` part) replaces the engine result in that part only.
9. Only `persist` writes. A part that already has non-ENGINE rows is not overwritten (reported as `skipped`).
**Officer decision point (A.7)**: each part can be overridden and returned ("reset") to the engine result.

## Input / Output
- **Input (read)**: `tm2mst.prod_bop_rule`, `prod_process`, `prod_machine` (+ `max_speed_per_hour`), `prod_part_process_machine_rate`, `prod_part_finishing`, `ref_finishing_type`, `prod_part_material`, `prod_part_ink`, `prod_part_process`, `mat_raw`, `mat_category`, `sup_supplier`; `tm2sdi.iqp_part` (+ `iqp_part_finishing`, `_material`, `_process`, `_ink`), `iqp_tier`, `iqp_layout`, `do_delivery`; `tm2mst.prod_layout_group` (+ `_part`); `tm2pur.pr_requisition`; `tm2inv.v_rm_available`; `tm2sys.sys_constant` `RIM_SHEETS` (default 500). These tables and columns were read directly from the code (SpecEngine.php:148-1051).
- **Output (return values)**: `generate` -> `inputs`, `bop`, `bom`, `inks`, `colors`, `override`, `warnings`. `forWorkOrder` -> `bop`, `bom` (each row + `process_id`), `warnings`, `layout`.
- **Output (writes DB)**: only `persist`: `tm2mst.prod_part_process`, `prod_part_material`, `prod_part_ink`; and, if there is an Inquiry, `tm2sdi.iqp_part_process`, `iqp_part_material`, `iqp_part_ink` (SpecEngine.php:1153-1247). The registry only mentions the `tm2mst` tables (see Finding 3).
- **Who may override**: `source` ENGINE is overwritten by the engine; OVERRIDE / MANUAL are not overwritten by the engine, only through the officer's "reset" (`deleteNonEngine`, `:1140`).

## FORMULA
Unit: LBR = print sheet, PCS, M, KG. All from the code; formulas are not prices (safe in the general part).
1. **Order qty**: `ctx.orderQty` if present, otherwise the primary tier qty of the Inquiry (`tierQty`: `iqp_tier` ordered by `is_primary` descending then `tier_seq`) (lines 69, 198-208).
2. **BOP chain** (`chain`, lines 330-372), for `i` from the last step to the first:
   - `cut` = index of the last PLONG step (process name starts with "PLONG", case-insensitive) or the last step if none. Steps `i <= cut` are in LBR, after that in PCS.
   - `out` = `need` if PCS; `ceil(need/up)` if LBR (null if `up` is empty).
   - `waste = ceil(out * waste_pct/100)`, `tol = ceil(out * tolerance_pct/100)`, `in = out + waste + tol`.
   - `need` for the previous step = `in * up` if LBR, `in` if PCS.
   - `est_hours = round(in / target_qty_per_hour, 1)`.
   - Empty `waste_pct`/`tolerance_pct` count as 0.
   - Merged steps (`merge_group`): waste and tolerance are **added** (lines 270-271).
3. **printSheets** = `qty_in` of the first step with uom LBR (`firstSheets`).
4. **BOM per sheet** (`figures`, lines 693-716), `per`:
   - PLANO: `1 / sheetsPerPlano`
   - SHEET_LENGTH: `sheetW/100 * rate` (M per sheet)
   - SHEET_AREA: `sheetL * sheetW / 10000 * rate / 1000` (KG per sheet; rate g/m2)
   - INK_COVERAGE: `coverage_cm2_sheet / 10000 * rate / 1000` (KG per sheet). Updated 2026-10-16 (Jo): when `coverage_cm2_sheet` is empty (artwork not analysed) it is estimated as `BOOKING_INK_COVERAGE_PCT` (default 25) % of `sheetL * sheetW` (`inkFallbackCm2`) and the row carries `estimated = true`.
   - PER_PCS: `rate * up / 1000`
   - PER_PACK: `rate * up / packQty` (display only, see step 5)
   - other basis (e.g. AREA_UNKNOWN): empty, the officer fills manually.
5. **Requirement**: `need = per * printSheets`; for PER_PACK: `ceil(orderQty/packQty) * rate`; if the uom is LBR it is rounded up (`ceil`); stored with 4 decimals.
6. **Ink**: `area_cm2 = die_w_cm * die_h_cm`, if empty `flat_w * flat_h / 100` (flat in mm). `coverage_cm2 = pct/100 * area` (2 decimals); `coverage_cm2_sheet = coverage_cm2 * up` (1 decimal). Notation `a/b` is rejected if it is not the pattern digit/digit (warning).
7. **Stock status**: `CUKUP` if `avail >= need` (after conversion to the stock unit), `KURANG`, `TIDAK ADA`, `CEK SATUAN`, `BELUM ADA ITEM`. Conversions only: same, LBR->RIM (`/RIM_SHEETS`), KG->G/GR (`*1000`), M->CM (`*100`), M->ROLL (length from the item name). Updated 2026-10-16 (Jo): when none of these rules fits, the master `mat_raw` "Stock -> Usage" conversion is used (`stockFor` now also returns `usage_uom` and `usage_conv`; need in usage unit / `conversion_to_usage`). Without it the result stays null (see ATTENTION-ENGINE-04; the master columns are empty for most materials).

**Worked example (ddt* data, computed by `forWorkOrder`, WO qty 1,000)**
- Part 532 (type DC, UP 3, pack content 100, BOP from rules): Potong Plano -> Cetak -> QC -> Laminating -> Plong -> Kupas & Hitung -> Pack. From the back: Pack out 1,000 in 1,000; Kupas & Hitung waste 0.5% = ceil(5) = 5, in 1,005; Plong out `ceil(1005/3)` = 335, waste 1.5% = ceil(5.025) = 6, tolerance 0.5% = ceil(1.675) = 2, in 343; Laminating out `ceil(343*3/3)` = 343, waste 4, tol 2, in 349; QC out 349, waste 2, in 351; Cetak out 351, waste 11, tol 4, in 366; Potong Plano out 366, waste 2, in 368. The engine result is exactly the same (368 LBR in), so `printSheets` = 368.
- Part 462 (DC, UP 4, plano 845x720 mm, pack 400, BOP comes from saved MANUAL rows without waste): `printSheets` = `ceil(1000/4)` = 250 LBR. BOM: KERTAS PLANO 250 LBR (per 1, `perPlano` 1); PLASTIK LAMINASI `SHEET_LENGTH`: 72/100*1 = 0.72 M/sheet * 250 = 180 M; LEM `SHEET_AREA`: 84.5*72/10000*10/1000 = 0.006084 KG/sheet * 250 = 1.521 KG; TALI RAFIA `PER_PACK`: `ceil(1000/400)=3` * rate 3 = 9 M. All match the engine result. Ink and varnish/foil are empty because "Artwork belum dianalisa" and basis `AREA_UNKNOWN`.

## Edge cases
- No ProDev layout yet (`upCount` 0): `forWorkOrder` uses 1 UP and the warning "Layout ProDev belum ada: 1 up per lembar diasumsikan" (lines 1066-1090).
- An empty override BOM `[]` does not shadow the engine BOM (`?: null`, line 1080).
- Material not chosen yet: the paper row is named `(kertas belum dipilih di Inquiry)`; in `saved()` it is named from the category path ("Paper › Duplex › 350gr") (lines 954-963).
- Finishing rule without a process: the warning "belum punya aturan proses"; the step is not created.
- Rows with `source` INQUIRY in `prod_part_process` are treated by `saved()` as non-ENGINE (line 943), whereas for materials they are filtered (line 950). In ddt*: 104 INQUIRY rows, 89 ENGINE, 33 MANUAL.
- Part `inks` uses the unique key `pass_seq-unit_no`; units are limited to 1-12.

## Used by (relations)
- **source**: Inquiry (finishing, material, colour notation, pack, qty), ProDev layout, rules `prod_bop_rule` (screen Setup > BOP Rules).
- **used_by**: ProDev (BOM/Ink/BOP tabs), WO creation and re-arrangement (PPIC), Master Card (dummy). **downstream**: `prd_wo_operation`, `prd_wo_material` (via `WoGenerationService`), costing (BOP/BOM as read-only context via `ProCostContextService`).
- **flows**: flag `stat_confirm_prodev` is recomputed after the layout is saved (`routes/v2.php:1672`, `InquiryStage::syncProdevForParts`).

## Open items
ATTENTION-ENGINE-01 (SpecEngine patch, DONE 14 Oct), ATTENTION-ENGINE-02 (two definitions of Available; `stockFor` uses `v_rm_available`), ATTENTION-ENGINE-04 (unit of Required vs stock without conversion; conversion limited in `toStockUom`). Status stays in `docs/ENGINE-ATTENTION.md`, not copied. The line numbers cited by items 02 and 04 (`:768`, `:727`) have shifted (now `stockFor` lines 771-791, `toStockUom` line 737).

## Findings (recorded, not fixed)
1. The registry (`EngineRegistry.php:156`) does not list the public method `processOfBom` (line 1007), although `forWorkOrder` uses it.
2. Registry step 4 says "Returns the result with a source per row: ENGINE, OVERRIDE, MANUAL". FACT: the result rows of `generate` have no `source` key; the source only exists per part in `saved()` (`'source' => OVERRIDE|MANUAL`).
3. Registry `writes` only lists `tm2mst.prod_part_*`; the code also writes `tm2sdi.iqp_part_process`, `_material`, `_ink`. Registry `reads` does not include many tables (see Input).
4. Registry `used` says "Master Product sync"; there is no call (only a comment in `ProductMasterSync.php`).
5. `deleteNonEngine` (line 1149) calls `where('source', 'OVERRIDE', 'OVERRIDE')` for `bom`; it works because Laravel swaps an invalid operator for the value, but it is hard to read.
6. Every ProDev Save calls `persist`: ENGINE rows are deleted and rewritten (new ids) in two databases in nested transactions (`prod_part_*` in `tm2mst`, `iqp_part_*` in `tm2sdi`, two connections, not one distributed transaction). A failure in the middle can leave one side newer.
7. There is no dedicated test for `generate`/`persist`/`saved`/`processOfBom`; only `forWorkOrder` via `WoBomFromInquiryTest` and `WoRowsTest`.
8. `PROCESS_INKS` uses literal hex (`#1a1a1a`, etc.) in code, not tokens; display data only.

## Tests
`tests/Feature/ProCostBomAvailabilityTest.php` (ink estimate, added 2026-10-16; 2 passed). `tests/Feature/WoBomFromInquiryTest.php` (1 test), `tests/Feature/WoRowsTest.php` (uses `forWorkOrder`, line 164). These tests clean up their own data.

> Checked 2026-10-16 after the ProCost BOM panel change (`ProCostContextService`, `costing.blade.php`; Jo agreed): the content of this card is not affected; cited line numbers of those two files may be about 30 lines lower. Only the cited items were re-read, not the whole card.

> Checked 2026-10-16 after the ProCost Book button (costing.blade.php; Jo agreed): the content of this card is not affected; cited line numbers of costing.blade.php may be about 70 lines lower. Only the cited items were re-read.

