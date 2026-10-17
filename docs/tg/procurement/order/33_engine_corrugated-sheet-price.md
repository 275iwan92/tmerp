# ENGINE card: Corrugated Sheet Price Engine (BF, BCF, single wall, double wall)

> Written 2026-10-17 (group Procurement). Source: `app/Services/Pur/CorrugatedSheetPriceEngine.php` (119 lines) and `app/Services/Pur/PaperSpec.php` read in full. Tests run: `PaperPurchaseEnginesTest` (7 passed, 49 asserts). Tables `sup_corrugated_rule` and `sup_corrugated_index` are empty in the mockup data. Verified: 2026-10-17.

- **Canonical name**: Corrugated Sheet Price Engine. **Class**: `App\Services\Pur\CorrugatedSheetPriceEngine`. **Registry**: key `corrugated-sheet-price`, mode `suggest`, group Procurement. **Module/sub-module**: Procurement > Order (router: card `30_engine_paper-price.md`).
- **Frozen status**: none; suggests only (A.7).
- **Synonyms**: lembar gelombang, sheet, SH, running meter, ups, trim, tingkat diskon.
- **NOT the same as**: Single Face (card `31_engine_single-face-price.md`), the ProCost corrugated costing (Sales).

## Trigger (no background / schedule)
Only `PaperPriceEngine::priceFor` calls it (`app/Services/Pur/PaperPriceEngine.php:73-75`) and passes the quantity. Of the two triggers of the router only the PO one has a quantity: allocation to PO line (`PoBuilderService.php:88`). The PR trigger (`resources/views/pages/pur/pr/entry.blade.php:205`) passes none, so `quote` answers reason `qty` and the PR line gets no price. Searched `routes/console.php`, jobs, observers: none. `quote()` (the breakdown with reason codes) has no caller other than `priceFor`.

## Input / Output
- **Input**: a `MatRaw` whose name **starts** with SH or SHEET and holds a substance key (`matches`), a supplier id, the order quantity (sheets; RIM x 500), a document date.
- **Output**: `priceFor` = price per sheet (unit LBR, PCS or empty) or per RIM (x 500) **excluding PPN**, else `null`. `quote` = an array: `price`, `reason` (`size`, `rule`, `index`, `qty`, `width`, `minimum`, `ppn`), `ups`, trim_width, running_meter, `tier`, `discount_pct`, `index`. Writes nothing.
- **Reads**: `sup_corrugated_rule`, `sup_corrugated_index`, `fin_tax_rate`.

## Flow (text twin)
1. Size from the name: first number = grain width (cm), second = length (cm).
2. `rule()`: the newest active rule of the supplier dated on or before the document date.
3. `index()`: the newest active row of the supplier with the same substance key as the name; if none, the generic row "SW" or "DW" (`PaperSpec::wall`).
4. `ups` = integer part of (max trim width - 2) / grain width; below 1: reason `width`.
5. Trim width = next multiple of 5 above (grain width x ups + 2).
6. Running meter = sheets / ups x length / 100.
7. Tier: the highest of 5000, 2000, 1000, 500, 300 reached whose discount column is filled; below 300 (or no filled column): reason `minimum`.
8. Price per sheet and divide by `D`; RIM = price x 500.

## Formula (one place)
`ups = INT((maxTrim - 2) / w)`; `trim = CEILING(w x ups + 2, 5)`; `rm = sheets / ups x l / 100`; `price_incl = (trim x l / 10000 x index / ups) x (1 - disc / 100)`; price = `price_incl / D`; with `w`, `l` in cm, `disc` in percent (the tier column; a negative number is a surcharge in the tests), `D` = 1 for EXCL else 1 + PPN / 100 on the document date. Rounding: 4 decimals. Variable sources: `w`, `l` from the name only (this engine never reads `cm_length`/`cm_width` of the master).

## Edge cases
- No quantity: reason `qty`, no price.
- Sheets under the lowest filled tier: reason `minimum`, no price.
- A tier column left empty is skipped, the next lower filled tier applies (test: 5000 sheets with an empty 2000 column give tier 1000).
- RIM unit: quantity is multiplied by 500 before the running meter; other units than LBR / PCS / RIM / empty: no price.
- Grain width larger than the max trim width minus 2: reason `width`.
- The index of the substance wins over the generic SW / DW row.

## Used by
`PaperPriceEngine::priceFor` (PO line only gets a price; PR does not).

## Open items
No `ATTENTION-ENGINE` item concerns it. New findings: the PR line gets no corrugated price by design of the call (no quantity); the engine ignores the master size columns; the table facts (empty tables) are in `13_card_paper-price-tables.md`; the reason codes are not shown to the officer on the PO screen (no caller of `quote` outside the engine; grep).

## Tests
`tests/Feature/PaperPurchaseEnginesTest.php`: `test_corrugated_quotes_equal_the_excel_values`, `test_corrugated_tiers_minimum_quantity_and_missing_data`, `test_paper_price_engine_sends_each_material_type_to_its_engine`, `test_the_three_input_screens_save_dated_rows_and_refuse_duplicates`.

<!-- johiwan-only -->

## Worked example (confidential; test asserts that equal Jo's Excel UJK and RCA sheets, arithmetic checked in a script)
Document date 17-Oct-2026, divisor 1.11, INCL.
1. UJK, "SH SW K150/M125/K125 64 X 78,5 CM", 800 sheets, max trim width 180, disc_300 = -10, SW index 4,410: `ups` = INT((180-2)/64) = 2; trim = CEILING(64 x 2 + 2, 5) = 130; running meter = 800 / 2 x 0.785 = 314; tier 300; price incl = `(130 x 78.5 / 10,000 x 4,410 / 2) x 1.10` = 2,475.22; price excl = 2,229.93 per sheet.
2. Other Excel checks asserted by the test: RCA width 95 x 57.5, 1,500 sheets, max trim 220, disc 15: 1,893.25 per sheet; RCA 150 WL / 125 / 125 KL, 67 x 246.4, 370 sheets, disc -25, index 5,285: 10,020.87; UJK same substance 34.4 x 113.3, 1,800 sheets, disc -9: 2,058.01.
