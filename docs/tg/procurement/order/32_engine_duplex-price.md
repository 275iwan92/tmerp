# ENGINE card: Duplex Price Engine

> Written 2026-10-17 (group Procurement). Source: `app/Services/Pur/DuplexPriceEngine.php` (85 lines) and `app/Services/Pur/PaperSpec.php` read in full. Tests run: `PaperPurchaseEnginesTest` (7 passed, 49 asserts, includes the three duplex tests). Table facts: `13_card_paper-price-tables.md`; table `sup_duplex_price` is empty in the mockup data. Verified: 2026-10-17.

- **Canonical name**: Duplex Price Engine. **Class**: `App\Services\Pur\DuplexPriceEngine`. **Registry**: key `duplex-price`, mode `suggest`, group Procurement. **Module/sub-module**: Procurement > Order (router: card `30_engine_paper-price.md`).
- **Frozen status**: none; suggests only (A.7). When the grade is ambiguous the engine answers nothing so the officer chooses.
- **Synonyms**: duplex, karton duplex, APKey, harga duplex.
- **NOT the same as**: ivory board priced by kg (card `30_engine_paper-price.md`).

## Trigger (no background / schedule)
Only `PaperPriceEngine::priceFor` calls it (`app/Services/Pur/PaperPriceEngine.php:69-71`); triggers = add a master item to a PR (`resources/views/pages/pur/pr/entry.blade.php:205`) and turn a PR allocation into a PO line (`PoBuilderService.php:88`). Searched `routes/console.php`, jobs, observers: none.

## Input / Output
- **Input**: a `MatRaw` whose name has the whole word DUPLEX (`matches`), a supplier id, a document date. Grammage and size come from the master columns; if empty from the name (`PaperSpec::gsm`: the number before GR / GSM; `PaperSpec::sizeCm`).
- **Output**: price per RIM or per LBR (RIM / 500) **excluding PPN**, or `null` (other unit, no size or grammage, no row, ambiguous grade, no divisor). Writes nothing.
- **Reads**: `sup_duplex_price`, `fin_tax_rate`.

## Flow (text twin)
1. `matches`: the name contains DUPLEX as a whole word; unit must be RIM or LBR.
2. Rows of the supplier: active, dated on or before the document date, with `gsm_from` <= grammage <= `gsm_to`; keep the newest row per grade + `gsm_from`.
3. Pick: if the name (or the brand) contains a grade written on a row (whole word, case-insensitive) take it, but only when exactly one grade matches; else a row **without** grade (the first); else, when there is exactly one grade left, that row; else no price.
4. Ream price per the row formula; divide by `D`; for LBR divide by 500.
5. Round to 4 decimals.

## Formula (one place)
`REF_REAM`: ream = `L x W / (79 x 109) x index x APKey / 100`; `WEIGHT`: ream = `g x L x W / 20000 x index x APKey / 100`; with `L`, `W` in cm, `g` in gsm, `APKey` in percent (500 = x 5.00; "PLUS" in the Excel). Price = ream / `D` (D = 1 for EXCL, else 1 + PPN / 100 on the document date). RIM = price; LBR = price / 500. Rounding: 4 decimals. Cut-from-roll pieces are not calculated (stated in the class REMARK).

## Edge cases
- Master columns win over the name: grammage 350 and size 79 x 109 in the master are used even if the name says other numbers (test).
- No grade named, two or more graded rows, no open row: no price ("the officer chooses").
- An open (gradeless) row is used when no grade is named, but a named grade still wins over it.
- Grammage outside every range: no price.
- A row with `gsm_to` below `gsm_from` cannot be saved (screen rule).

## Used by
`PaperPriceEngine::priceFor`; indirectly PR estimate and PO line price.

## Open items
No `ATTENTION-ENGINE` item concerns it. New findings: the table is empty in the mockup data, so no proposal occurs there; the comparison of the grade uses the material **name and brand** only, `mat_raw` has no grade column (column query: only `brand`, `gram_weight`, `cm_length`, `cm_width` among the relevant ones).

## Tests
`tests/Feature/PaperPurchaseEnginesTest.php`: `test_duplex_reference_sheet_and_weight_formulas_equal_the_excel_values`, `test_duplex_excl_price_is_not_divided_and_master_columns_win_over_the_name`, `test_duplex_picks_the_row_by_date_gsm_range_and_grade`, and the screen test `test_the_three_input_screens_save_dated_rows_and_refuse_duplicates`.

<!-- johiwan-only -->

## Worked example (confidential; test asserts that equal values of Jo's Excel, arithmetic checked in a script)
Document date 17-Oct-2026, divisor 1.11, price basis INCL.
1. REF_REAM, "DUPLEX 400 GR 89 X 119,4", index 353,000, APKey 480: ream = `89 x 119.4 / (79 x 109) x 353,000 x 480 / 100` = 2,091,012.78 (Excel INDEX!H3); price per RIM = 1,883,795.30.
2. WEIGHT, "DUPLEX 310 GR 79 X 109", index 2,173, APKey 600: ream = `310 x 79 x 109 / 20,000 x 2,173 x 600 / 100` = 1,740,188.38 (Excel INDEX!H10); per RIM = 1,567,737.28; per LBR = 3,135.47.
3. EXCL with master 350 gsm, 79 x 109, index 1,000, APKey 505: `1 x 1,000 x 5.05` = 5,050 per RIM (not divided).
