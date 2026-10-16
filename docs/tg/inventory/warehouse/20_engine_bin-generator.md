# ENGINE card: BinGenerator (makes the bins of one storage area)

> Written 2026-10-16. Verified: 2026-10-16. Source: `app/Services/Inventory/BinGenerator.php` (71 lines) read in full, route `routes/v2_warehouse.php:68-81`, bin tests `StockTransferServiceTest::test_bin_generator_makes_floor_and_rack_bins_and_skips_existing_ones` (read, not run: the brief forbids DB writes). Tables: concept card `10_card_location-hierarchy.md`.

- **Canonical name**: BinGenerator. **Class**: `App\Services\Inventory\BinGenerator` (static methods `plan`, `generate`). **Registry**: NOT registered in `EngineRegistry` (grep: no key). **Module/sub-module**: Inventory > Warehouse > Map & Monitor (button Generate Bins).
- **Frozen status**: not frozen. It only creates master rows; it never changes or deletes a bin.
- **Mode**: auto-fill helper. It suggests nothing; the officer enters the ranges and presses the button (A.7: engine proposes, officer decides, here the officer decides the whole range).

## Trigger
| Method | Trigger | File:line |
|---|---|---|
| `generate` | Warehouse Map > Generate Bins > Create (POST `bins.generate`) | `routes/v2_warehouse.php:68-81` |
| `plan` | called by `generate`; no preview screen uses it | `BinGenerator.php:57-59` |
No schedule, observer or job (grep `BinGenerator` in `app`, `routes`, `tests`: only the route, the service and one test).

## Input / Output
- **Input**: a `WhZone` (with its building), and `rows_from/rows_to` (1-99), `cols_from/cols_to` (1-99), `levels` (1-9, racks only), `capacity_kg`, `capacity_pallet`, plus the login.
- **Output (writes, database `tm2mst`)**: new `wh_loc` rows with `warehouse_id`, `zone_id`, `location_type` (= area type), `is_usable=1`, `activate=1`, the two capacities (NULL when 0 or empty), `created_by`, `updated_by`.
- **Returns**: `['created' => n, 'skipped' => n]`; the screen shows ":n bins created in :zone (:s already existed)."
- **Does NOT write**: pallets, stock, `wh_zone`; it does not set `map_x`/`map_y`.

## Flow
1. A virtual area (SUBCON, TRANSIT, LOSS) returns an empty plan; the route refuses with "A virtual area has no physical bins to generate." (`BinGenerator.php:24-26`; route line 73-75).
2. Storage mode AREA: ONE bin, code `<building>-<area>`, `col_code` and `stack_code` "0". FLOOR: one bin per lane x slot, code `<building>-<area>-L<lane 2 digits>-<slot 2 digits>`, `stack_code` "1". RACK: one bin per rack x column x level (levels 1-9, default 1), code `<building>-<area>-R<rack>-<col>-<level>`. `row_code` is `<area>-L<lane>` or `<area>-R<rack>` because the unique key is (building, row, column, level) (`BinGenerator.php:29-49`).
3. The codes that already exist (`wh_loc.location_code`, unique across all buildings) are marked `exists` and skipped, so the generator can be run again to add lanes (`BinGenerator.php:51-53`).
4. More than `MAX_BINS` (2000) in the plan aborts with "Too many bins in one run." (HTTP 422) (`BinGenerator.php:60`). The plan stops building at 2001 rows, so the count shown is never above 2001.
5. All new rows are written in one transaction on `tm2mst` (`BinGenerator.php:62-67`).

## Formula (as-is)
- FLOOR/RACK count = (rows_to - rows_from + 1) x (cols_to - cols_from + 1) x levels; `max(1, rows_from)` etc. so 0 becomes 1.
- Worked example: area `PPR` (FLOOR) of building `A10`, lanes 1-2, slots 1-3 gives `A10-PPR-L01-01` ... `A10-PPR-L02-03` (6 bins). RACK area `INK`, racks 1-1, columns 1-2, levels 2 gives `A10-INK-R01-01-1`, `A10-INK-R01-01-2`, `A10-INK-R01-02-1`, `A10-INK-R01-02-2`.

## Edge cases
- `rows_to` below `rows_from` produces no rows (the loop does not run); the route then reports "0 bins created". FROM CODE.
- Codes are limited by `location_code` (60 chars); the zone code is at most 6 letters (checked on the Storage Area screen, not here). FROM CODE.
- The generator ignores `count_lock`, `capacity` of the area and map position. FROM CODE.
- `WhLoc::create` is used, so only `$fillable` fields are written; `count_lock` is not in `$fillable` (default 0 applies). FACT, `app/Models/Master/WhLoc.php:20-23`.
- A bin created in an area whose building has no layout does not appear on the map until the building has a size. FROM CODE (`Warehouse::desk`).

## Tests (read, not run)
`StockTransferServiceTest::test_bin_generator_makes_floor_and_rack_bins_and_skips_existing_ones`. Not tested: the 2000 limit, an inverted range, the route.

## Used by
Warehouse Map (Generate Bins modal), indirectly every bin reader. Seeders (`UatWarehouseSeeder`, FROM DOCUMENT) make the dummy bins; whether they call this class was not checked.
