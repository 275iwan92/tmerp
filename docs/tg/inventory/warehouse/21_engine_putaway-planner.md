# ENGINE card: PutawayPlanner (suggests a bin) and BinRules (hard rules on a bin)

> Written 2026-10-16. Verified: 2026-10-16. Source: `app/Services/Inventory/PutawayPlanner.php` (151 lines) and `app/Services/Inventory/BinRules.php` (103 lines) read in full; `app/Support/V2/Data/ReceivingPutaway.php`. Tests were NOT run (brief: no DB writes). Concept cards: `12_card_receiving-and-putaway.md`, `10_card_location-hierarchy.md`.

- **Canonical name**: Putaway Planner. **Class**: `App\Services\Inventory\PutawayPlanner` (instance; one instance = one run of suggestions). **Registry**: key `putaway-planner` (mode suggest). **Module/sub-module**: Inventory > Warehouse > Receiving & Putaway. `BinRules` (`App\Services\Inventory\BinRules`, static) is NOT registered; it is the hard-rule helper shared with Stock Transfer and Putaway.
- **Frozen status**: not frozen. The planner only suggests ("engine suggests, officer confirms", A.7); BinRules refuses.
- **Capacity is a hint, Mixing and count lock are laws**: capacity (kg, pallet count, stack limit) removes a bin from the suggestions but never blocks a scan; Mixing area and `count_lock` block (BinRules header comment, `BinRules.php:11-17`).

## Trigger (no schedule)
| Method | Trigger | File:line |
|---|---|---|
| `suggest` | Receiving & Putaway grid rows (top 1), task panel (5), `WarehouseTaskService::suggestions`, Map putaway layer | `app/Support/V2/Data/ReceivingPutaway.php:63,85`; `app/Support/V2/Data/Warehouse.php:272-274` |
| `toLine` | same, when the batch is Conditional (state TO_LINE) | `app/Services/Inventory/WarehouseTaskService.php:96` |
| `reserve` | after each top suggestion so the next pallet does not take the same room | `ReceivingPutaway.php:64-66` |
| `BinRules::mixViolation` | putaway confirm; transfer Confirm | `WarehouseTaskService.php:173`; `BinRules.php:74-100` |
| `BinRules::countLockViolations` | transfer Confirm, Ship, Receive | `StockTransferService.php:90,265` |

## Input / Output
- **Input**: an `InvPallet` and its `InvBatch` (may be null), a limit (default 5); stock per bin from `WarehouseStock::positions` / `binSummaries` (card for the read side: EngineRegistry key `warehouse-stock`).
- **Output**: list of `bin`, `zone`, `empty`, `free_kg`, `why` (text such as "empty bin · 1 item/bin · FIFO · capacity 1,500 kg ok"). Writes nothing.
- **BinRules output**: an error text (English, translated) or null; lists of error texts per transfer line.

## Flow of `suggest` (`PutawayPlanner.php:31-91`)
1. Batch must be allocatable (`BatchService::allocatable`: Quarantine, Rejected, Blocked give no suggestion; no batch = allowed).
2. Zones: active, not virtual, type STORAGE, stock class ANY or the pallet's class; sorted by same building as the pallet's current bin first, `sort_order`, code.
3. Bins: active, usable, not `count_lock`, not the pallet's own bin; sorted by area rank, empty before occupied, then code.
4. Scan at most 30 bins (`SCAN_LIMIT`); skip a bin that conflicts with what was already reserved this run for ONE_ITEM / ONE_BATCH areas, and (only if occupied) a bin where `BinRules::mixViolation` fires.
5. Capacity filter per candidate: used kg (stock + reserved) + the pallet's estimated weight must not exceed `capacity_kg` (+0.005); pallet count (stock + reserved + 1 unless the pallet is a PACK) must not exceed `capacity_pallet` or the area's `max_stack`. Unknown weight (null) never rejects.
6. Return the first `limit` bins that pass.

`toLine` (`PutawayPlanner.php:106-121`): for type WIP then STAGING, the same-building areas first, take the first usable, unlocked bin by code; no capacity or Mixing check; null if none ("no suitable bin: choose one and give a reason").

## Formula (as-is)
- Pallet weight: KG items = qty; sheets (LBR/SHEET/RIM) = sheets x grammage x length x width / 10,000,000 g to kg (`WarehouseStock::weightKg`); otherwise unknown.
- `free_kg` = capacity_kg - (used + reserved), rounded to 2 decimals; null when the bin has no kg capacity.
- `mixViolation`: rule ANY = ok; ONE_ITEM = another position in the bin with a different class/item/part is a violation; ONE_BATCH = any ACTIVE pallet in the bin (qty > 0, excluding the moving pallet) with a different batch (no batch counts as "none"); a bin with stock but no pallet counts as "none".

## Edge cases
- ONE_BATCH looks at pallets only; stock without pallet labels is treated as "no batch", so a labelled pallet with a batch is refused next to unlabelled stock. FROM CODE (`BinRules.php:39-52`).
- An empty bin (no positions) skips the Mixing check, so the first pallet always passes. FROM CODE (`PutawayPlanner.php:60`, `BinRules.php:28-31`).
- Within one document, `transferViolations` also checks that two lines to the same bin satisfy the rule (`BinRules.php:89-97`).
- All 16 dummy areas are `mix_rule=ANY`, so the Mixing branches never fire on dummy data. FACT (query).

## Tests (read, not run)
`WarehouseTaskServiceTest`: `test_the_suggestion_is_an_empty_bin_in_the_same_building_and_respects_capacity`, `test_bins_suggested_for_earlier_pallets_in_one_run_are_counted_as_taken`, `test_one_item_per_bin_blocks_a_second_item`, `test_the_stock_transfer_service_also_enforces_the_mixing_rule`, `test_one_batch_per_bin_compares_the_batch_of_the_pallets_in_the_bin`, `test_a_rejected_batch_gets_no_bin_and_a_quarantined_batch_waits`, `test_a_conditional_batch_goes_straight_to_the_line_area`. `CountLockTest::test_a_transfer_from_or_to_a_locked_bin_is_refused`.

## Used by
Receiving & Putaway screen, Warehouse Map putaway layer, `WarehouseTaskService` (card `26_engine_warehouse-task-service.md`), `StockTransferService` (card `23_engine_stock-transfer-service.md`).
