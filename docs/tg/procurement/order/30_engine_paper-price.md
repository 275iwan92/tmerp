# ENGINE card: Paper Price Engine (entry point of the purchase price suggestion, ivory board by kg)

> Written 2026-10-17 (group Procurement). Source: `app/Services/Pur/PaperPriceEngine.php` (114 lines) and `app/Services/Pur/PaperSpec.php` (74 lines) read in full. Tests run: `PaperPriceEngineTest` (8 passed, 45 asserts), `PaperPurchaseEnginesTest` (7 passed, 49 asserts), `PaperPricePrPoTest` (1 skipped: its guard found no usable ivory material or free supplier in the test database). A read-only script ran `priceFor` on mockup data. Table facts: `13_card_paper-price-tables.md` (not repeated). Verified: 2026-10-17.

- **Canonical name**: Paper Price Engine. **Class**: `App\Services\Pur\PaperPriceEngine`. **Registry**: key `paper-price`, mode `suggest`, group Procurement. **Module/sub-module**: Procurement > Order (used by Requisition and Order lines).
- **Frozen status**: none. It only suggests (A.7); the officer may overwrite the price.
- **Synonyms**: harga kertas, price for, rim price, saran harga.
- **NOT the same as**: the ProCost paper cost engine (Sales, `iqp_costing_part`), `mat_raw.last_unitprice`.

## Trigger (no background / schedule)
Searched `routes/console.php` (none), jobs and observers (none), and all callers by grep of the four engine class names in `app/`, `resources/views`, `routes`, `tests`. FACT: two callers, both inside a Livewire / service action.
| Trigger | Method | File:line |
|---|---|---|
| Add a master item to a PR (supplier = `mat_raw.suggest_supplier_id`; no date = today; no quantity) | `priceFor` | `resources/views/pages/pur/pr/entry.blade.php:205` |
| A PR allocation becomes a PO line (supplier = the PO supplier; date = the PO date; quantity = allocation qty) | `priceFor` | `app/Services/Pur/PoBuilderService.php:88` |
The Material Planning Create PR / Buy buttons reach the first row through `addMasterItem`. A PQ line copied to a PO overwrites the result with the quoted price (`PurchaseQuotation.php:313`).

## Input / Output
- **Input**: a `MatRaw` (name, categories, `gram_weight`, `cm_length`, `cm_width`, `uom_purchase`), a supplier id (nullable), a document date (nullable = today), an order quantity (only the corrugated branch uses it).
- **Output**: a price per purchase unit **excluding PPN** (RIM, LBR or KG) or `null` when data is missing. Writes nothing.
- **Reads**: `sup_paper_price`, `mat_grammage`, `fin_tax_rate` (through `TaxRate::ppn`) and the three sibling engines.

## Flow (text twin)
1. `priceFor` asks `SingleFacePriceEngine::matches`; if yes the answer is that engine's (card `31_engine_single-face-price.md`).
2. Else `DuplexPriceEngine::matches` (card `32_engine_duplex-price.md`).
3. Else `CorrugatedSheetPriceEngine::matches` (card `33_engine_corrugated-sheet-price.md`, the only branch that uses the quantity).
4. Else the ivory/board branch: no supplier = no price; `row()` finds the active row of the supplier whose `paper_kind` appears in the text made of the material name and its five categories, dated on or before the document date; a row whose grammage equals the material grammage (within 0.005) wins over a row without grammage; among equals the latest date and then the highest id.
5. Unit KG: price = roll price per kg / divisor. Unit RIM: price of one ream; unit LBR: ream price / 500. Any other unit: no price.
6. The result is rounded to 4 decimals and given to the caller, which stores it as a Draft value.

## Formula (one place)
Symbols: `L`, `W` = length and width in metres (master `cm_length` / 100, `cm_width` / 100), `g` = grammage (gsm), `k` = price per kg, `D` = divisor.
- Ream weight (kg) = `L x W x g / 2`.
- `D` = 1 when `price_basis` is EXCL; otherwise 1 + PPN / 100 with PPN = `TaxRate::ppn(document date)`; a PPN of 0 gives no price.
- RIM = `k x L x W x (g / 2) / D`, with `k` = `sheet_price_per_kg` for a standard-size ream; the method `rimPrice` can also take `k` = roll price per kg + `cut_cost_per_kg` (cut from a roll).
- LBR = RIM / 500; KG = roll price per kg / `D` (a TON roll price is divided by 1000).
- Reams from a roll of `m` kg = `m / ream weight` (`reamsFromRoll`).
- Rounding: 4 decimals after the divide. Variable sources: all from the master columns of the material, no name parsing in this branch.

## Edge cases
- Material master columns empty (grammage 0 or size 0): no price even if the name says "79 X 109" (the sibling engines do read the name through `PaperSpec`; this branch does not).
- Only a roll price typed, ream price empty: RIM and LBR give no price (the standard-size branch uses `sheet_price_per_kg`), KG still works.
- `cut_cost_per_kg` and `reamsFromRoll` are never used by a proposal: `priceFor` calls `rimPrice` with `cutFromRoll` false (callers of `reamsFromRoll`: tests only; grep).
- Two rows with the same supplier, kind and date: the highest id wins (no unique key).
- PR line: the supplier comes from `suggest_supplier_id`, which is NULL on every material in the mockup data, so a PR line never gets an engine price there (PR card finding 4).

## Used by
`PurchaseRequisition` (estimate), `PoBuilderService` (PO line), the screen Supplier Paper Price (input), the sibling engines (they call `divisor`).

## Open items
No `ATTENTION-ENGINE` item in `docs/ENGINE-ATTENTION.md` concerns the purchase price engines. New findings: the PR branch passes no quantity, so a PR line for a corrugated sheet never gets a price; the engine is not applied when a Cash PO line is added (`PurchaseOrder::addMaterial` sets no price; Cash PO takes the shop receipt). The registry `used` list says "Supplier Paper Price (input screen)" although that screen reads no engine; the input screen only writes the rows.

## Tests
`tests/Feature/PaperPriceEngineTest.php` (8 tests: PPN divisor by date, Tax Rate screen, ream price standard and cut from roll, roll per ton equals per kg, price per unit, row by date and grammage, single face, input screen), `tests/Feature/PaperPurchaseEnginesTest.php` (7 tests, router test `test_paper_price_engine_sends_each_material_type_to_its_engine`), `tests/Feature/PaperPricePrPoTest.php` (PO line price from the engine; skipped on the test database).

<!-- johiwan-only -->

## Worked example (confidential; formula asserts of `PaperPriceEngineTest`, arithmetic checked in a script)
Test constants (not real supplier prices): ream price 12,500 per kg, roll price 13,000 per kg, cut cost 250 per kg, INCL PPN; document date 17-Oct-2026 (PPN 11, divisor 1.11).
1. Standard ream, 79 x 109 cm, 250 gsm: `12,500 x 0.79 x 1.09 x 125 / 1.11` = 1,212,134.01 per RIM; per LBR = 2,424.27.
2. Cut from a roll, 1.0 x 0.7 m, 80 gsm: `(13,000 + 250) x 1.0 x 0.7 x 40 / 1.11` = 334,234.23 per RIM; a 1,000 kg roll gives `1,000 / 28` = 35.7143 reams.
3. Unit KG: `13,000 / 1.11` = 11,711.71 per kg.
4. Mockup data: material 305 "IVORY 300 79 X 109" (the first line of PR2600009) has master grammage 0 and size 0, purchase unit RIM; `priceFor` with supplier 15 (the only supplier with a paper price row) on 17-Oct-2026 returns nothing. Of 314 ivory-like materials 291 have grammage, size and unit RIM or LBR.
