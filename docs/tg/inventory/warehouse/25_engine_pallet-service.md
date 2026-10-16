# ENGINE card: PalletService (pallet labels, stack, remnant)

> Written 2026-10-16. Verified: 2026-10-16. Source: `app/Services/Inventory/PalletService.php` (315 lines) read in full, with `app/Models/Inventory/InvPallet.php`, `app/Support/V2/Data/Pallets.php`, routes `routes/v2_warehouse.php:140-235`. Tests were NOT run (brief: no DB writes). Concept card: `14_card_pallet.md`.

- **Canonical name**: Pallet Labels. **Class**: `App\Services\Inventory\PalletService` (static methods). **Registry**: key `pallet-labels` (mode suggest). **Module/sub-module**: Inventory > Warehouse > Labels & Scan.
- **Frozen status**: not frozen. Invariant stated in the file: a pallet NEVER changes stock; the stock card stays the only truth of quantity (`PalletService.php:18-24`).

## Trigger
| Method | Trigger | File:line |
|---|---|---|
| `worklist`, `receiptLines` | Print Pallet Label screen, Item label screen, Labels | `app/Support/V2/Data/Pallets.php:68-95`; `app/Support/V2/Data/Labels.php:211-241` |
| `create` | Create & Print | `routes/v2_warehouse.php:168-187` |
| `relabel` | Relabel & Print; automatically after Confirm Pick | `routes/v2_warehouse.php:192-215`; `PickListService.php:322-330` |
| `printed` | Print route for type pallet | `routes/v2_warehouse.php:147-149` |
| `restack` | Map panel arrows | `routes/v2_warehouse.php:218-228` |
| `followTransfer`, `revertTransfer` | after every transfer posting / cancel | `StockTransferService.php:168,198,240,280,310` |
No schedule.

## Input / Output
- **Input**: position key `CLASS:item:part:bin` (`WarehouseStock::key`), rows (qty, supplier pallet code, lot, expiry), common (arrival date, receipt no., note, unit kind), a pallet and the remaining qty, direction up/down, a transfer document.
- **Output (writes, `tm2inv`)**: `inv_pallet` (all columns of the concept card), `inv_pallet_log`.
- **Returns**: pallets / the pallet / a list of work rows; refusals throw `RuntimeException` with translated text (the route shows it under the form).

## Flow
1. **worklist** (`:95-124`): positions with stock joined with ACTIVE pallets by position key; plus pallets whose position has no stock any more (so their label can be closed). Row fields: `pallets`, `pallet_qty`, `unlabelled = max(0, stock - labelled)`, `excess = max(0, labelled - stock)`, `need` = NEW (gap > 0.00005), RELABEL (gap < -0.00005), else null.
2. **create** (`:136-176`): only rows with qty > 0; the position must still hold stock; total <= `unlabelled` (error ":item: only :qty in this bin is not on a pallet label yet."); in a transaction renumber the bin's stack, then each new unit gets `PL`yy+5 (`InvPallet::nextNo`, `lockForUpdate`), `qty_initial = qty`, `stack_no` = next on top, batch via `BatchService::findByLot`, unit kind from `kindFor`, `expiry_date` given or `suggestedExpiry`, `label_count` 1; log CREATED.
3. **relabel** (`:180-202`): pallet must be ACTIVE; `0 <= remaining <= qty_initial`; set `qty`; 0 -> CLOSED with `stack_no` NULL and the bin's stack renumbered, else kind re-decided, `label_count` + 1; log RELABEL / CLOSED.
4. **kindFor** (`:36-44`): asked PALLET / PACK wins; else PACK when the quantity is sheet goods (RIM x 500, LBR, SHEET) of `WH_REMNANT_MAX_SHEETS` or fewer; else PALLET.
5. **followTransfer** (`:215-231`): per transfer line, the ACTIVE pallets of that class/item(/part for FG) in the source bin, oldest first, locked; `palletsToMove`: the named pallet; or ALL if the source balance is now 0; or the oldest pallets whose sum EXACTLY equals the line qty; else none. Moved units are ordered by their old stack number and placed on top of the destination; the source stack is renumbered. Log MOVED with the transfer number.
6. **revertTransfer** (`:263-276`): for each MOVED log of that transfer (with empty note) whose pallet is still ACTIVE in the destination, move it back with note "Transfer cancelled".
7. **restack** (`:65-86`): renumber, swap `stack_no` with the neighbour in the direction; error "This unit is already at the top/bottom."; log RESTACK "from > to".

## Formula (as-is)
- Gap = `round(stock - sum(active pallet qty), 4)`; tolerance `EPS` 0.00005.
- Suggested expiry = arrival date + `mat_raw.shelf_life_days`, null if the item has none (`:127-130`).
- Worked example: bin holds 15,000 LBR as 10,000 + 5,000 labels; 6,000 are issued: stock 9,000 < labels 15,000 -> RELABEL; officer sets the opened pallet to 4,000 (remaining) -> labels 9,000 -> gap 0.

## Edge cases
- Transfer lines with no pallet and a quantity that does not match any exact sum of pallets leave the pallets where they are; the gap appears as label work. FROM CODE (`:241-260`).
- `revertTransfer` identifies moved pallets by `ref_no` and an empty note; a pallet restacked or relabelled in between is still found, but one moved again elsewhere is not. FROM CODE.
- Comment in `InvPallet.php:11` names a method `gaps` that does not exist (the code uses `worklist`). FACT.
- Pallet numbers: per-year prefix only (`PL26`); `substr($last, 4)` assumes that prefix length. FROM CODE.
- Writes occur inside DB transactions on `tm2inv` only; `compact` locks the rows (`lockForUpdate`) but is also called from the Confirm Pick transaction. FROM CODE.

## Tests (read, not run)
`PalletServiceTest` (14 tests, listed in the Pallet card).

## Used by
Print Pallet Label, Item label (receipt lines), Stack Monitor / Map (through `WarehouseStock::stacks`), Scan Track, Confirm Pick (relabel), Stock Transfer (follow / revert), `RELABEL` move in `Pallets::moveTo`.
