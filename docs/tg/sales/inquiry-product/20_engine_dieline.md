# ENGINE card: Dieline Engine (TMDieline)

> DRAFT GROUP C (2026-10-16), awaiting Jo's approval. Sources read: `resources/js/tmdieline.js` (176 lines) and `app/Support/Dieline/TmDieline.php` (332 lines, PHP port); test `TmDielineTest` was run (3 passed, 8 asserts). JS vs PHP parity was tested directly with node v24 against the 27 active product types (result below).

- **Canonical name**: Dieline Engine (TMDieline). **File (registry key `dieline`, mode `calc`, group Inquiry Product)**: `resources/js/tmdieline.js`. **Module/sub-module**: Sales > Inquiry & Quotation > Inquiry Product.
- **Three copies of the same code** (FACT): `resources/js/tmdieline.js`; `packages/mockup-parts/resources/assets/tmdieline.js` (identical content, empty `diff`); PHP port `App\Support\Dieline\TmDieline`. The registry only lists the first.
- **Frozen status**: the dieline render capability is LOCKED by Jo ("already exists & verified good, do not change"; `docs/claude_vital_flows.md`). Do not change the geometry without Jo's permission.
- **Synonyms**: dieline, knife, flat layout, DSL `dieline_spec`, TMDieline.
- **NOT the same as**: `ImpositionEngine` (arranges the flat layout on the plano); **NOT** the product type (`prod_type.type_code`, see `10_card_product-type.md`): the product type is recipe data, this engine reads it.

## Trigger (no background / schedule / command)
Not in `routes/console.php`, Jobs, Observers. Everything is triggered by the display in the browser (JS) or by server code (PHP). FACT, from grep:
| Trigger | Method | File:line |
|---|---|---|
| Inquiry v2 form: dimension/type changed (component `x-mp::dieline-v3` in `x-mp::sales.inquiry-part`) | `flat`, `render`, `STROKE` | `packages/mockup-parts/resources/assets/js/components/dieline-x.js:116-119` (`redraw`); used by `Sales/InquiryPart.php:136`, `Sales/PartVisual.php:55` |
| ProDev: Part & Dieline panel | same | `packages/mockup-parts/src/Components/Prodev/PartDieline.php:75`; saved via `PartDieline::save` (`app/Services/ProDev/PartDieline.php`), route `routes/v2.php` (`prodev.dieline`) |
| Master Card Collection | same | `resources/views/v2/screens/master-card-collection.blade.php:144` |
| Inquiry v1 Form/Recalc/Nota/Hub, Quotation print | `svg`, `render` | `inquiry/form.blade.php:272`, `recalc.blade.php:302`, `nota.blade.php:472`, `hub.blade.php:33`, `quotation/print.blade.php:80`, `components/inquiry/readonly-summary.blade.php:224` |
| ProDev (nesting draws the blank) | `svg` | `production/prodev.blade.php:1593`, `:3695` |
| 3D box | `STROKE` | `resources/js/tm-box3d.js:17` |
| Bulk Master Card import (server) | `TmDieline::flat`, `::svg` | `app/Services/MasterCard/MasterCardShape.php:98`, `:133`, `:142` (called by `MasterCardService`, `MasterCardDummyEngine`) |
`resources/js/app.js:1` loads the engine for v1 pages. Livewire bridge: `resources/js/dieline-part.js:65,85`.

## Flow (text twin, no diagram: one straight path)
1. The screen sends `spec` (product type recipe, JSON `prod_type.dieline_spec`) and `dims` (the typed/chosen sizes).
2. `buildScope`: `dims` values become variables; abbreviations A/B/C/D/LH/G/TT/DF/BT/P are mapped to their long names; then the derived variables `spec.vars` are evaluated in order.
3. `flat`: evaluate `spec.flat.w` and `spec.flat.h`, round to 2 decimals, compute `area`.
4. `svg`: evaluate `spec.canvas.w/h`; if not > 0, nothing is drawn. Each element of `spec.elements` is drawn in order (line, rect, circle, tuck, dust, glue, bleed, foldlines, holes, text). Elements that fail are skipped silently.
5. `render` puts the SVG in the element, or the message "Tipe ini belum punya spesifikasi dieline" / "Pilih ukuran / isi dimensi untuk melihat dieline".
6. The flat w/h/area is used by the screen to fill the flat size boxes; the officer may fill them manually (tick `manual`, `dieline-x.js:51,116`).
7. When the Inquiry is saved, `InquiryWriter::persistParts` freezes recipe + dims + `type_code` into `iqp_part.dieline_spec` (`InquiryWriter.php:269-278`, snapshot A.13); the engine does not save.

## Input / Output
- **Input**: `prod_type.dieline_spec` (JSON, 27 of 28 active types have `elements`; FACT, query), the valid choices are read by `Sales::dielineTypes()` (`app/Support/V2/Data/Sales.php:112`, only active types with `elements`); parameters `prod_type_param`, market sizes `prod_type_size`. Does not read `sys_constant`.
- **Output**: does not write the DB. `flat` -> `{w, h, area}` (mm, mm2); `svg` -> SVG text or `null`; `render` -> fills the element. What saves: `InquiryWriter` (`iqp_part.dieline_spec`, `flat_w`, `flat_h`, `flat_area`) and `PartDieline::save` (`iqp_part` + `prod_part`, `cm_length/width/height` = dims/10).
- **Who may override**: the officer through the form (Inquiry; ProDev may change the part dieline, A.17).

## FORMULA
Unit mm. No rounding except `nf`/`flat` (2 decimals). The formulas are written in DATA (`dieline_spec`), not in code; the engine only evaluates them.
1. Expression evaluator (`evalExpr`): operators `+ - * /`, unary minus, parentheses, functions `max min abs sqrt round floor ceil`, constant `PI`. Unknown or infinite variable = 0. **Division by 0 = 0** (line 88). Non-numeric result = 0.
2. `flat.w`, `flat.h` = result of expression `spec.flat.w/h` (e.g. RSC_2PC: `G+A+B+2*P`, `C+B+2*flap_extra+2*P`), rounded `round(x*100)/100`; `area = round(w*h*100)/100`.
3. Fixed line colours: CUT `#ff3b3b`, CREASE `#00e676`, BLEED `#888` dashed `5 3`, HOLE `#4da3ff`; viewBox margin `max(24, (W+H)/16)`.
4. Bleed size (`draw_bleed`): square from `-p` of width `W+2p` x `H+2p`; not drawn when `p <= 0`.
5. `tuck`: curved flap with radius `min(8, (b-a)/6)`; `dust`: trapezoid with inset `min(h, (b-a)/3)`; `glue`: trapezoid glue tab of length `g`.
6. The PHP port is the same: `flat` uses `round(...,2)`, same evaluator (`TmDieline::eval`).

**Worked example (computed myself; dims A=200, B=150, C=100, G=20, P=3, others from the type parameters, `flap_extra` not filled = 0)**
| Type | Flat | Hand check |
|---|---|---|
| RSC_2PC | 376 x 256 mm, area 96,256 | `G+A+B+2P` = 20+200+150+6 = 376; `C+B+0+2P` = 100+150+6 = 256 |
| CARD_BOX | 726 x 146 mm, area 105,996 | `G+2A+2B+2P` = 20+400+300+6 = 726; `max(TT,DF)+C+max(BT,DF)+2P` with TT=20, DF=15, BT=20 = 20+100+20+6 = 146 |
**JS vs PHP parity test (run myself)**: 27 active types with spec, dims above; `flat` and `svg` text from `tmdieline.js` (node) and `TmDieline` (PHP) are **byte-for-byte identical for 27 of 27**, no null SVG. The test script is in the session's temporary folder (not part of the repo).

## Edge cases
- Type without spec / `elements`: `svg` = null, the screen shows the message; one active type has no spec (28 active, 27 with spec). `persistParts` does not save `type_code` when the spec is empty (see `10_card_product-type.md`).
- Expression with a syntax error in `vars` or an element: value 0 / element skipped without a message (empty `catch`, lines 98, 164). A typo in the recipe is not visible to the officer.
- Dims that are not numbers (`parseFloat` NaN) = 0.
- `text` elements use the fixed fill colour `#9aa` (line 139); `esc()` is used for the text content.

## Used by (relations)
- **source**: master `prod_type` (+ `_param`, `_size`). **used_by**: Inquiry Product (New/Revise/Recalc), ProDev Part & Dieline, Master Card Collection, Quotation print, 3D box. **downstream**: `iqp_part.dieline_spec`/`flat_*` -> ImpositionEngine (initial size), SpecEngine (`blankArea`: `flat_w*flat_h/100` when `die_*_cm` is empty), Master Card (dieline SVG must be filled on import).

## Open items
No ATTENTION-ENGINE item specific to this engine (read in full).

## Findings (recorded, not fixed)
1. The registry (`EngineRegistry.php:90-117`) only lists the JS file; it does not mention the PHP port `TmDieline` (used by bulk Master Card import) or the copy in `packages/mockup-parts`. A change in one copy must be repeated manually in the other two (the comment `TmDieline.php:11` orders "ubah salah satu = ubah keduanya"; `TmDielineTest` guards only 3 cases). The result of this session's test is 27/27 identical at present.
2. The registry says `flat` returns "width and height"; the code also returns `area`. The exports `eval`, `scope`, `STROKE` (line 175) are not recorded in the registry (`STROKE` is used by tm-box3d and dieline-x).
3. Expression evaluation failures are hidden (value 0, empty `catch`); there is no log.
4. The line colours and `#9aa` in the renderer are literal hex in JS/PHP, not tokens (UI rule: black/bright). These are semantic dieline line colours (cut/fold/bleed); the design decision is Jo's.
5. The PHP test (`TmDielineTest`) states that parity of 27 types x 2 sizes was checked (2026-10-01): FROM DOCUMENT/comment; this session re-tested 27 types x 1 size (see above).

## Tests
`tests/Unit/TmDielineTest.php` (3 tests: flat equals the knife of real Master Cards for 3 cards, PHP SVG equals JS for one case, expression evaluator). No JS tests.

> Re-checked 2026-10-17 against `EngineRegistry.php` and `engines.json`: key `paper-price` (Procurement) was added after the lines cited here; nothing in this card changed.

> Re-checked 2026-10-17 against `EngineRegistry.php` and `engines.json`: the `paper-price` entry text now names the Tax Rate screen (`fin_tax_rate`) for PPN; nothing in this card changed.

> Re-checked 2026-10-17: only the Inquiry nota print label changed (tax rate fallback from `TaxRate::ppn`); the dieline engine is untouched.

> Re-checked 2026-10-17 against `EngineRegistry.php` and `engines.json`: key `single-face-price` (Procurement) was added after the lines cited here; nothing in this card changed.

> Re-checked 2026-10-17 against `EngineRegistry.php` and `engines.json`: keys `duplex-price` and `corrugated-sheet-price` (Procurement) were added after the lines cited here; nothing in this card changed.

- Re-checked 2026-10-18 after the duplicate-module cleanup (v1 Scheduling Board, Rule Library and Master Crew removed): the registry entry of key `dieline` is now `EngineRegistry.php:90-117` (REMARK block added, imports and 3 Scheduling engine entries removed elsewhere in the file); `engines.json` lost the 3 removed engines only; nothing else in this card changed.
