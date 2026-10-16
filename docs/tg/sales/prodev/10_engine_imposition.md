# ENGINE card: Imposition Engine

> DRAFT GROUP C (2026-10-16), awaiting Jo's approval. Source: `app/Services/Imposition/ImpositionEngine.php` read in full (472 lines); test `ImpositionEngineProDevToolsTest` was run (7 passed, 19 asserts). The example numbers were computed myself via tinker (`--env=mockup`, without writing to the DB) and hand-checked.

- **Canonical name**: Imposition Engine. **Class**: `App\Services\Imposition\ImpositionEngine`. **Registry**: key `imposition`, mode `suggest`, group Pro-Dev. **Module/sub-module**: Sales > Inquiry & Quotation > Pro-Dev.
- **Frozen status**: ProDev = frozen zone (flow/data). This engine is pure calculation, it does not write the DB itself (FACT: no `DB::`/`save` in the file).
- **Synonyms**: UP, nesting, plano layout, print layout, TEGAK/PUTAR/CAMPUR.
- **NOT the same as**: manual nesting on the new ProDev screen (drawn in JS `prodev.blade.php`, not by this engine; see Finding 1). **NOT** `ProCostContextService::layoutGeometry` (that maps the layout result to the costing boxes).

## Trigger (no background / schedule)
Searched in `routes/console.php` (none), `app/Jobs` (none), Observers (none), all of `app/`, `resources/views`, `packages`. FACT, all callers of `new ImpositionEngine` / `app(ImpositionEngine::class)`:
| Trigger | Method | File:line | Note |
|---|---|---|---|
| v1 Imposition screen per Inquiry opened / size changed | `run`, `cells` | `resources/views/pages/inquiry/imposition.blade.php:67` (recompute), `:118` (suggestion for all active planos) | route `inquiry.imposition` (`routes/web.php:150`); incoming link from v1 form `form.blade.php:2621`. Menu v2 `SALES_IMPO` points to `v2:prodev`, not this screen. |
| Substitute material preview in Form WO | `run` | `resources/views/pages/production/wo-entry.blade.php:439` | display only, does not save |
| Costing Advisor, when no ProDev layout exists yet | `run` | `app/Services/Costing/CostingAdvisor.php:276` | read-only, marked ESTIMATE (see the Costing Advisor card) |
| Compute "effective cut" of a multi-part group | `applyFlap` | `app/Models/Master/ProdLayoutGroup.php:88` (from `ProCostContextService.php:139`, `costing.blade.php:268`) | flat size only, not `run` |
| OLD ProDev (archived) | all tools (`run`, `sweepFlap` line 622, `sweepInterlock` 673, `bestSheet` 726, `dieRuleMeterLari` 771, `combinePair` 854, `cells`, `applyFlap`) | `resources/views/pages/production/old_prodev.blade.php` | route `production.old-prodev` (`routes/web.php:339`) is alive, but the comment in `MenuRoutes.php` and the content of mockup `sys_app_menu` do not link it |

**Does not call this engine** (FACT, grep): the active ProDev screen `pages::production.prodev` (menu `v2:prodev`, `v2:prodev-new`, `v2:prodev-revise`): nesting is drawn and computed in JS on the client, the Save button only stores the result (`saveLayout`). `CostingEngine` does not call it either.

## Flow (text twin of diagram `../img/imposition.svg`)
1. `run(plano, flat, gap, gripper, margin)` computes the usable area = plano minus margin and gripper.
2. Compute the TEGAK arrangement (flat as is) and PUTAR (flat rotated 90 degrees); the one with more UP wins (tie = TEGAK).
3. Try CAMPUR: main block TEGAK, then the remaining width is filled with a PUTAR block; used only if the total UP is higher AND the rotated block is not empty.
4. Compute efficiency and waste; return `up`, `method`, `layout` (columns, rows, size per block).
5. `cells` turns `layout` into the coordinates of each cell (mm from the top-left of the plano) for drawing.
6. Extra tools (all call `run` repeatedly): `sweepFlap` (flap length variation), `sweepInterlock` (flap + shift "69"), `bestSheet` (all active planos), `combinePair` (two parts at once), `dieRuleMeterLari` (knife running metres).
7. The result is a suggestion; the officer decides (A.7). The engine does not save.

## Input / Output
- **Input**: method arguments (no DB reads), in mm: `planoW, planoH, flatW, flatH, gap, gripper, margin`; sweep: `flapMin, flapMax, step, flapSide` (TOP/BOTTOM/LEFT/RIGHT); `bestSheet`: collection `mat_plano_size` (given by the caller; the engine itself does not query). Screen defaults: `sys_constant` `PRODEV_GAP_MM`=3, `PRODEV_GRIPPER_MM`=12, `PRODEV_MARGIN_MM`=8, `PRODEV_FLAP_MIN_MM`=20 (FACT, mockup DB), but the engine does not read them itself.
- **Output (return values only)**: `up`, `method` (TEGAK/PUTAR/CAMPUR), `efficiency_pct`, `waste_pct`, `layout`, `area_w`, `area_h`; sweep adds `flap_length_mm`, `shift_offset_mm`, `flat_w`, `flat_h`; `bestSheet` adds `plano_size_id`, `plano_name`, `cost_metric`, `gross_sheets`; `combinePair` gives `recommend` (COMBINED/SEPARATE/NONE), `combined`, `separate`, `saving_pct`.
- **Who writes the result**: the caller. The v1 screen writes `iqp_layout` (`imposition.blade.php:75-82`: `method`, `up_count`, `efficiency_pct`, `waste_pct`, `layout_detail`), only when `stat_confirm` is not yet 1 (`:63`). The active ProDev writes `prod_layout_group` and `iqp_layout` from its own JS result.

## FORMULA
Unit: mm, except `up` (pieces per sheet) and percent. No rounding unless stated.
1. `areaW = planoW - 2*margin`; `areaH = planoH - gripper - 2*margin` (lines 11-12).
2. `fit(avail, item, gap) = max(0, floor((avail + gap) / (item + gap)))` (line 458); `n = fit(areaW, itemW) * fit(areaH, itemH)` (lines 461-471). An item with a side <= 0 gives n = 0.
3. TEGAK = `n(flatW, flatH)`; PUTAR = `n(flatH, flatW)`; `up` = the larger, TEGAK if equal (line 17).
4. CAMPUR: `mainCols = fit(areaW, flatW)`, `usedW = mainCols*(flatW+gap) - gap`, `restW = areaW - usedW - gap`, `mainRows = fit(areaH, flatH)`; `rotCols = fit(restW, flatH)`, `rotRows = fit(areaH, flatW)`; total = `mainCols*mainRows + rotCols*rotRows`; replaces the previous result when `total > up` AND `rotCols*rotRows > 0` (lines 27-46).
5. `efficiency = up * flatW * flatH / (areaW * areaH) * 100`, clamped to 0-100 and rounded to 2 decimals; `waste = 100 - efficiency` clamped the same way (lines 48-56). Note: the efficiency base = usable area (not the whole plano).
6. `applyFlap`: LEFT/RIGHT adds the flap to the width, otherwise to the height (lines 166-172).
7. `sweepFlap`: for `flap = flapMin ... flapMax` (multiples of `step`, tolerance +0.0001) compute `run` on the `applyFlap` flat size; sort UP descending then efficiency descending; take `topN` (default 5). `flapMax < flapMin` or `step <= 0` gives an empty list.
8. `sweepInterlock`: the same, but for each `flap` try `shift = 0 ... flap` (multiples of `shiftStep`) and the effective flat size uses `flap - shift` (line 206). The code author states that this model is an approximation, not a precise specification (comment lines 181-189); shift 0 = the `sweepFlap` result.
9. `bestSheet`: per plano take the top `sweepFlap` result (UP >= 1). "Cheapest" metric: if `targetQty > 0`: `ceil(targetQty/up) * (planoW*planoH)`; otherwise: `planoW*planoH / up`. Sorted ascending (lines 264-279). Material price is not used (assumes the same material on all planos).
10. `combinePair`: the area is split into two vertical columns, column A of width `x` (shifted by `splitStep`, default 5), B the rest; `upA`/`upB` = maximum of the two orientations; `sets = min(upA/qtyPerSkuA, upB/qtyPerSkuB)`; keep the `x` with the largest `sets`. `area_per_set = planoArea / sets`. Separate comparison: `planoArea/soloUpA*qtyA + planoArea/soloUpB*qtyB`. `saving_pct = (1 - gabung/pisah)*100` (lines 309-391). `recommend` = COMBINED only when combined < separate; NONE when no `x` holds both.
11. `dieRuleMeterLari`: cut = `(cols+1)*gridH + (rows+1)*gridW` (shared knife between cells), with `gridW = cols*iw + (cols-1)*gap`, etc.; for CAMPUR the two grids are added. Fold = `up * panjangGarisLipat` (body width when the flap is TOP/BOTTOM, height when LEFT/RIGHT). Perforation = 0 (comment: no data source yet). Result = `(potong+lipat)/1000` metres, 4 decimals.

**Worked example (computed and hand-checked, plano 79x109 = 790x1090, gap 3, gripper 12, margin 8)**
| Flat (mm) | `run` result | Hand check |
|---|---|---|
| 300 x 200 | 10 UP, TEGAK, efficiency 72.99% | areaW 774, areaH 1062; columns floor(777/303)=2, rows floor(1065/203)=5 = 10; 10*60,000/821,988 = 72.99% |
| 250 x 100 | 30 UP, TEGAK, 91.24% | 3 columns x 10 rows; 750,000/821,988 |
| 128 x 102 | 58 UP, CAMPUR, 92.12% | TEGAK 5x10 = 50, PUTAR 7x8 = 56; mixed 5x10 + 1x8 = 58 > 56; `offX` 655 |
| 780 x 300 | 2 UP, PUTAR, 56.94% | (engine result, not hand-checked) |
| 2000 x 2000 | 0 UP, TEGAK, 0% | does not fit |
- `sweepFlap`, body 200x150, flap TOP 20-50 step 5, top 3: flap 40 = 20 UP PUTAR 92.46%; flap 35 = 20 UP 90.03%; flap 30 = 20 UP 87.59%. Hand check flap 40: flat 200x190, PUTAR 4 columns x 5 rows = 20, 20*38,000/821,988 = 92.46%. The other two were not hand-checked.
- `combinePair` (plano 790x1090; A 300x200 qty 1, B 150x100 qty 1; step 10): COMBINED, `x` 610, 10 UP A + 10 UP B, 10 sets per sheet, saving 16.67% (engine result; the test asserts only COMBINED and saving > 0).
- `dieRuleMeterLari` grid 2x3, 100x80, 6 UP, gap 3, flap TOP, body 100x80 = 2.15 m (same as the test).

## Edge cases
- Flat 0 or larger than the plano: `up` 0, efficiency 0, `method` stays TEGAK (FACT, executed). The caller must check `up < 1`.
- Negative `restW`: `fit` gives 0, no rotated block.
- The result does not consider grain direction, press size, or minimum bite other than the `gripper` given by the caller.
- Ties between flaps are sorted stably by order of appearance (PHP `usort` is stable).

## Used by (relations)
- **source**: `prod_part` / `iqp_part` (flat, flap), `mat_plano_size` (plano). **used_by**: v1 Imposition screen, Form WO (preview), Costing Advisor, OLD ProDev. **downstream**: `iqp_layout`, `prod_layout_group` (filled by the caller); Costing reads `up_count`/layout via `ProCostContextService`. Related concepts: Product type, `../inquiry-product/20_engine_dieline.md` (source of the flat size).

## Open items
`docs/ENGINE-ATTENTION.md` has no item for this engine (read in full; status stays there, not copied).

## Findings (recorded, not fixed)
1. The registry lists the user "Pro-Dev workbench (Imposition tab)" (`EngineRegistry.php:148`). FACT: the active ProDev screen does not call the engine; only the old ProDev does, which is not linked from the menu. The registry text is misleading.
2. The registry lists the user "Costing Engine (flat area per part)". FACT: `CostingEngine` does not call this engine. The comment `ImpositionEngine.php:164` refers to `IqpCostingPart::allocateProportionalCost()` which does not exist (grep); what exists is `ProdLayoutGroup::effectiveCutFor` (uses `applyFlap`).
3. The values gap 3 / gripper 12 / margin 8 are written literally in `imposition.blade.php:45-50` and `CostingAdvisor.php:276` (and `old_prodev` via constants), although constants `PRODEV_GAP_MM`, `PRODEV_GRIPPER_MM`, `PRODEV_MARGIN_MM` exist (same values now). If the constants are changed, three callers do not follow.
4. Tests do not cover `run` (the TEGAK/PUTAR/CAMPUR basis), `sweepFlap`, `sweepInterlock`, `cells`, `applyFlap` directly (only three tools: `dieRuleMeterLari`, `bestSheet`, `combinePair`).
5. Each sweep tool is only called from the old ProDev; if the old ProDev is dropped, five methods have no caller. Jo's decision.

## Tests
`tests/Unit/ImpositionEngineProDevToolsTest.php` (7 tests; `dieRuleMeterLari` x2, `bestSheet` x2, `combinePair` x3).

> Checked 2026-10-16 after the ProCost BOM panel change (`ProCostContextService`, `costing.blade.php`; Jo agreed): the content of this card is not affected; cited line numbers of those two files may be about 30 lines lower. Only the cited items were re-read, not the whole card.

> Checked 2026-10-16 after the ProCost Book button (costing.blade.php; Jo agreed): the content of this card is not affected; cited line numbers of costing.blade.php may be about 70 lines lower. Only the cited items were re-read.


> Checked 2026-10-16 after adding the Technical Guide diagram route (`routes/web.php`: one `require` appended to an existing line, no line numbers moved; `routes/web.php:150` and `:339` re-read and still correct). Content of this card is not affected.

> Checked 2026-10-16 after the ProCost Book button (costing.blade.php; Jo agreed): the content of this card is not affected; cited line numbers of costing.blade.php may be about 70 lines lower. Only the cited items were re-read.

