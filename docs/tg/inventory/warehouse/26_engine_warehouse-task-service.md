# ENGINE card: WarehouseTaskService (putaway tasks)

> Written 2026-10-16. Verified: 2026-10-16. Source: `app/Services/Inventory/WarehouseTaskService.php` (217 lines) and `WarehouseTaskRefused.php` read in full, with `app/Models/Inventory/WhTask.php`, `app/Support/V2/Data/ReceivingPutaway.php`, `routes/v2_warehouse.php:246-258`. Tests were NOT run (brief: no DB writes). Concept cards: `12_card_receiving-and-putaway.md`, `17_card_warehouse-task-and-board.md`.

- **Canonical name**: Warehouse Task Service. **Class**: `App\Services\Inventory\WarehouseTaskService` (instance; constants WAITING, READY, TO_LINE, BLOCKED). **Registry**: key `warehouse-task` (mode flow). **Module/sub-module**: Inventory > Warehouse > Receiving & Putaway.
- **Frozen status**: not frozen. It never edits a balance: stock moves only through a real Stock Transfer posted by `StockTransferService` (header comment, `WarehouseTaskService.php:21-25`). Only the PUTAWAY type is built here.

## Trigger
| Method | Trigger | File:line |
|---|---|---|
| `sync` | every GET of the Receiving & Putaway screen | `resources/views/v2/screens/wh-receiving-putaway.blade.php:17` |
| `state`, `suggestions` | screen rows, panel, Map putaway layer | `app/Support/V2/Data/ReceivingPutaway.php:60-85`; `Warehouse.php:268-276` |
| `confirm` | Confirm in the task panel (POST `task.confirm`) | `routes/v2_warehouse.php:246-258` |
No scheduler runs `sync`; tasks exist only after someone opens the screen.

## Input / Output
- **Input**: ACTIVE pallets with qty > 0 in bins of RECEIVING / QC_HOLD areas; receipt lines for urgency (`pv_receipt`, `pv_line.is_urgent`, `urgent_wo_id`); batch quality; typed scans for pallet and bin; an optional reason; login.
- **Output (writes, `tm2inv`)**: `wh_task` rows (PUTAWAY; DONE with `to_location_id`, `scan_pallet`, `scan_bin`, `reason`, `transfer_id`, `confirmed_by/_at`); `inv_transfer` + `inv_transfer_line` (one line, reason OTHER, note "Putaway <task> / <pallet>"), stock card rows and pallet move through `StockTransferService::post`.
- **Returns**: `sync` `['created' => n, 'cancelled' => n]`; `confirm` the DONE task; refusals throw `WarehouseTaskRefused`, nothing changes.

## Flow
1. **sync** (`:40-63`): pallets in receiving bins ordered by arrival; urgency looked up for the ones without an OPEN task (RM only); in a transaction create a task per missing pallet (`WT`yy+5; class, item, pallet, batch, qty, from bin, receipt number, `is_urgent`, `wo_id`); OPEN tasks whose pallet is no longer in a receiving bin become CANCELLED with reason "Pallet is no longer in a receiving area.".
2. **state** (`:66-81`): DONE / CANCELLED as stored; open: QUARANTINE = WAITING; CONDITIONAL = TO_LINE; REJECTED / BLOCKED = BLOCKED; otherwise READY.
3. **suggestions** (`:85-99`): READY -> `PutawayPlanner::suggest`; TO_LINE -> `toLine` (WIP then Staging); others none.
4. **confirm** (`:102-134`), inside one transaction with row locks on task and pallet: task must be OPEN; pallet ACTIVE and still in `from_location_id`; state must be READY or TO_LINE; scanned pallet code = pallet number (`Labels::parse`, kinds PLT or none, case-insensitive); scanned bin = a `wh_loc.location_code`; destination checks (`checkDestination`, `:154-177`): bin active and usable, not count-locked, not the current bin, area type STORAGE (WIP / STAGING for TO_LINE) and not virtual, area class ANY or the pallet's, `BinRules::mixViolation`; if the bin is not the top suggestion a reason is required; `move` builds the transfer and posts it; the task is closed.

## Formula (as-is)
- No arithmetic of its own; capacity logic is in the planner (card `21_engine_putaway-planner.md`).
- Worked example: pallet PL2600101 (Released batch) in `A10-RCV`; top suggestion `A10-PPR-L01-04`; officer scans `PL2600101` then `LOC:A10-PPR-L01-04` -> transfer TF2600015 posted, task DONE, pallet in the new bin at the top of its stack.

## Edge cases
- Two simultaneous `sync` calls can make two OPEN tasks for one pallet (no unique key). FROM CODE + `show index`.
- Confirm re-reads the suggestion at confirm time; if the situation changed between the screen and the click, a different top bin may demand a reason. FROM CODE (`:123-127`).
- A transfer refusal (for example the source has no stock card balance) is rethrown as `WarehouseTaskRefused` and the task transaction rolls back, including the posted rows (same connection). FROM CODE (`:188-190`).
- `sync` creates tasks for FG and SFG pallets too (any class), but urgency exists only for RM. FROM CODE.
- Putaway skips module TF approval by calling `post()`. FACT (`:25,188`).

## Tests (read, not run)
`WarehouseTaskServiceTest` (15 tests, listed in the Receiving & Putaway card).

## Used by
Receiving & Putaway screen, Task Board, Warehouse Map putaway layer, Print Pallet Label (the pallets that create tasks).
