# ENGINE card: PQ Matcher (supplier and line matching for Purchase Quotation files)

> Written 2026-10-17 (group Procurement). Source: `app/Services/Pur/PqMatcher.php` (96 lines) read in full; the reader `app/Services/Pur/PqFileReader.php` (199 lines) read in full (it is not a registered engine, see the end). Worked example = a read-only script run against the mockup data (`--env=mockup`); no test exists for this engine. Verified: 2026-10-17.

- **Canonical name**: PQ Matcher (Purchase Quotation Matcher). **Class**: `App\Services\Pur\PqMatcher` (static, `final`). **Registry**: key `pq-matcher`, mode `suggest`, group Procurement. **Module/sub-module**: Procurement > Order > Purchase Quotation.
- **Frozen status**: none named. It only suggests ("engine suggests, officer confirms", A.7); it writes nothing.
- **Synonyms**: matcher, cocokkan penawaran, match percent (`pq_line.c_match_pct`).
- **NOT the same as**: `ProductMasterSync` or any Sales matching; the Material Planning netting.

## Trigger (no background / schedule)
Searched `routes/console.php` (no PQ entry), jobs and observers (none) and all callers by grep. FACT: only the upload of a quotation file.
| Method | Caller | File:line |
|---|---|---|
| `supplier` | `PurchaseQuotation::upload` (once per file) | `app/Support/V2/Data/PurchaseQuotation.php:79` |
| `line` | `PurchaseQuotation::upload` (once per quoted line, with the lines of the chosen PR) | `app/Support/V2/Data/PurchaseQuotation.php:100` |
| `SURE` | the same method (file status READ or CHECK) and the screen (tick or warning before the percent) | `PurchaseQuotation.php:109`; `resources/views/v2/screens/purchase-quotation.blade.php:81` |
Upload comes from the form on `v2:purchase-quotation` (route `v2.pq.upload`, `routes/v2.php:664`).

## Input / Output
- **Input**: `supplier(name)`: the supplier name as read from the file; `line(text, prLines)`: one item text and the PR lines (`pr_line`) of the PR chosen at upload (empty when no PR was chosen). Reads active suppliers (`sup_supplier`, `activate` = 1) and active, confirmed materials (`mat_raw`, `activate` = 1, `stat_confirm` = 1).
- **Output**: `supplier` returns `[id_supplier or null, score]`; `line` returns `pr_line_id`, `item_id` and `pct`. The caller stores them in `pq_quotation.i_supplier_id` and `pq_line.pr_line_id`, `pq_line.item_id`, `pq_line.c_match_pct`. The engine itself writes nothing.

## Flow (text twin)
1. Normalize both texts (`norm`): lower case; `79 x 109`, `79*109`, `79x109` become `79x109`; `250 gr` / `250 gsm` become `250`; every character other than a-z, 0-9, `x` and space becomes a space; for company names the words pt, cv, ud, tbk, toko, tk, persero are dropped.
2. Supplier: score the name against every active supplier name; take the best; below 55 the answer is "no supplier" (the score is still returned).
3. Line: score the text against each PR line description; if the best is 55 or more, answer with that PR line (and its `item_id`).
4. Otherwise search the Material Master: keep the two longest words of three or more letters from the text, require both as `LIKE` in `mat_raw.material_name`, score the first 300 hits, and answer with the best if it reaches 55.
5. Otherwise answer no match with the best score seen.
6. The caller marks a line "sure" only at 80 or more (`SURE`); below that the officer must choose.

## Formula (one place)
`score(a, b)` on normalized texts: 100 when equal, 0 when one is empty; otherwise `round((overlap + pct) / 2)` where `overlap` = number of shared unique words / larger count of unique words x 100, and `pct` = PHP similar_text percentage of the two strings. Thresholds: `SURE` = 80 (public), `MIN` = 55 (private). Rounding: to a whole number.

**Worked example** (mockup data, `PqMatcher::line` against the three lines of PR2600009: "IVORY 300 79 X 109", "HVS 58 GR RAINBOW 79 X 109 CM", "DUPLEX NONE COATED 260 GR 76 X 108 CM"):
| Text from the file | Result |
|---|---|
| `DUPLEX 260 GR 79 X 109` | no match, best 52 (the PR line differs in size 76x108 and has extra words) |
| `Duplex 260 gsm` | no match, best 47 |
| `HVS 58` | no match, best 43 (highest of the PR-line and the material scores) |
| `karton bungkus kosong` | no match, best 8 |
| supplier `PT Central Grafik` | supplier 2, score 100 (the company prefix is removed before scoring) |
| supplier `zzzz` | no supplier, score 8 |
| `score('a b', 'a b')` | 100; `score('duplex 260 79x109', 'duplex 260 gsm')` = 69 |

## Edge cases
- Short or generic texts rarely reach 55: of the four line texts above none matched, including a text that a person would match to the Duplex line.
- Words of fewer than three letters, and pure numbers, are ignored by the material search; a text such as `58 gr` yields no word at all and returns no material.
- The material search sees at most 300 candidates, taken in database order, so a common word can hide the best item.
- With no PR chosen at upload `prLines` is empty and only the Material Master is searched.
- Two suppliers with the same normalized name: the first with the highest score wins (no tie rule).

## Used by
`PurchaseQuotation::upload` only (card `10_card_purchase-quotation.md`).

## Open items
No `ATTENTION-ENGINE` item in `docs/ENGINE-ATTENTION.md` concerns this engine. New findings (this card): the registry entry has no `tests` field and no test exists for `PqMatcher` (`tests/` has `PqFileReaderCsvTest` for the reader only); the matcher is strict for the text forms seen above (example table). `PqFileReader` (reads the file: Claude when `services.anthropic.key` is set, otherwise Excel / CSV by header words) has no registry entry, and neither have `PrAllocationService`, `PoBuilderService` or `PvPostingService`.

## Tests
None for this engine. Related: `tests/Unit/PqFileReaderCsvTest.php` (3 tests, 16 asserts, passed 2026-10-17) covers only the CSV reader and `PqFileReader::number`.

Re-checked 2026-10-19: line 38 of esources/views/v2/screens/purchase-quotation.blade.php (Back button of the compare form) was reworded in place, no line added or removed; the lines cited by this card (81) are unchanged, content still true.
- Re-checked 2026-10-20 after the Quotation and Customer Order entry rules moved from the v1 components to services (rule D.25, T10): `routes/web.php` replaced the v1 routes `/quotation/new`, `/quotation/{quotation}/edit`, `/quotation/{quotation}/print`, `/co/{order}/edit` and `/co/{order}/print` by redirects on the same lines (no line added or removed), `routes/v2.php` changed only two comment lines, `app/Support/V2/Screens.php` changed the description text of the `quotation` and `customer-order` rows on the same lines, and `app/Support/V2/Data/Quotation.php` and `CustomerOrder.php` now call `QuotationEntryService` and `CoEntryService` instead of the v1 Livewire components (the `component()` helper is gone; cards that cite lines of those two files were remapped from the diff). The v1 pages `pages::quotation.entry`, `pages::co.entry` and the two v1 prints are archived in `tmerp_unused/`. Every other cited line number is unchanged and this card's content is still true.
- Re-checked 2026-10-20 after Imposition and Inquiry Nota moved off v1 (rule D.25, T10 stage 2): `routes/web.php` replaced the v1 routes `/inquiry/imposition`, `/inquiry/{inquiry}/imposition` and `/inquiry/{inquiry}/nota` by redirects (to ProDev v2 `/v2/s/prodev-new`, `/v2/prodev/open/{id}` and the v2 print `/v2/print/inquiry-note/{id}`) on the same lines (no line added or removed), `app/Support/V2/Print/PrintDocs.php` registered the `inquiry-note` print type on the existing `inquiry` line, and `app/Support/V2/Screens.php` changed the description text of the `nota-inquiry` row on the same line. The v1 pages `pages::inquiry.imposition`, `imposition-list` and `nota` are archived in `tmerp_unused/`; `ImpositionEngine` is unchanged. Every other cited line number is unchanged and this card's content is still true.
