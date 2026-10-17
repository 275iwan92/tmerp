# ENGINE card: Single Face Price Engine

> Written 2026-10-17 (group Procurement). Source: `app/Services/Pur/SingleFacePriceEngine.php` (57 lines) and `app/Services/Pur/PaperSpec.php` read in full. Test `test_single_face_price_is_index_times_area_divided_by_the_ppn_divisor` ran inside `PaperPriceEngineTest` (8 passed). Table facts: `13_card_paper-price-tables.md`; table `sup_single_face_price` is empty in the mockup data. Verified: 2026-10-17.

- **Canonical name**: Single Face Price Engine. **Class**: `App\Services\Pur\SingleFacePriceEngine`. **Registry**: key `single-face-price`, mode `suggest`, group Procurement. **Module/sub-module**: Procurement > Order (called from the router in card `30_engine_paper-price.md`).
- **Frozen status**: none; suggests only (A.7).
- **Synonyms**: SF, S FACE, single facer, harga single face.
- **NOT the same as**: single wall / double wall corrugated sheets (card `33_engine_corrugated-sheet-price.md`).

## Trigger (no background / schedule)
Not called by a screen directly. Only `PaperPriceEngine::priceFor` calls it (`app/Services/Pur/PaperPriceEngine.php:65-67`), so its triggers are the two of the paper-price engine: add a master item to a PR (`resources/views/pages/pur/pr/entry.blade.php:205`) and turn a PR allocation into a PO line (`PoBuilderService.php:88`). Searched `routes/console.php`, jobs, observers: none.

## Input / Output
- **Input**: a `MatRaw` whose **name** says SF, S FACE or SINGLE FACE as a whole word (`matches`), a supplier id, a document date.
- **Output**: price per sheet (unit LBR, PCS or empty) or per RIM (500 sheets) **excluding PPN**; `null` for any other unit, an unreadable size, no row, or no PPN divisor. Writes nothing.
- **Reads**: `sup_single_face_price`, `fin_tax_rate`.

## Flow (text twin)
1. `matches`: the name contains SF / S FACE / SINGLE FACE.
2. Read the size from the name (`PaperSpec::sizeM`): "A x B" with CM or MM; with no unit both sides at most 250 count as CM, else MM; the **last** "A x B" in the name is used.
3. `row()`: rows of the supplier, active, dated on or before the document date, newest first; the first whose substance key and flute key equal those of the name (`PaperSpec::substanceKey`, `fluteKey`).
4. Divisor `D` from the row's `price_basis` (see the paper-price card).
5. Price per sheet, times 500 when the unit is RIM, rounded to 4 decimals.

## Formula (one place)
`sheet = index_per_m2 x A(m) x B(m) / D`; RIM = `sheet x 500`. `A` and `B` in metres in the order written in the name (the order does not change the product). Substance key: tokens such as K, M, WK, WL, KL with their weights joined in order, so "SF M 125 / K 125", "S FACE 125 M / 125 K" and "M125/K125" all give `M125K125`; flute key: E/F, EF, B/F and similar give EF, BF, CF, BCF. Rounding: 4 decimals.

## Edge cases
- A name with no substance, no flute, or no size: no price (`row` returns null before any query when the keys are empty; no size returns null before `row`).
- Another substance or flute than the typed rows: no price.
- Unit KG or any other: no price ("per kg is not a sheet price", test).
- A future-dated row is ignored; an inactive row is ignored.

## Used by
`PaperPriceEngine::priceFor` only; indirectly PR estimate and PO line price.

## Open items
No `ATTENTION-ENGINE` item concerns it. New finding: the table is empty in the mockup data, so no Single Face proposal can occur there; the engine was checked only through the test constants. Unit and size rules were not checked against real Single Face materials of the master (no query was run for names containing SF).

## Tests
`tests/Feature/PaperPriceEngineTest.php`, the single face test above (asserts per sheet, per RIM, MM and CM sizes, newer row from its date, wrong substance / flute / no size / KG unit give null, `PaperSpec` keys).

<!-- johiwan-only -->

## Worked example (confidential; test asserts, arithmetic checked in a script)
Test constants: index 2,600 per m2 INCL PPN; document date 17-Oct-2026 (divisor 1.11).
1. "SF EF M125/K125 865 X 560 MM": `2,600 x 0.865 x 0.56 / 1.11` = 1,134.63 per sheet; per RIM `x 500` = 567,315.32.
2. "DN SINGLE FACE E/F M125/K125 UK 72 x 52 CM": `2,600 x 0.72 x 0.52 / 1.11` = 876.97 per sheet.
3. A second row dated 01-Nov-2026 with index 2,700 applies only to documents from that date.
