# ENGINE card: CountTaskService (cycle count by area and the bin lock)

> Written 2026-10-16. Verified: 2026-10-16. Source: `app/Services/Inventory/CountTaskService.php` (135 lines) read in full, with `app/Console/Commands/InvCountDue.php`, `routes/v2_stockflow.php:44-80`, `routes/console.php:54`. Tests were NOT run (brief: no DB writes). Concept card: `16_card_count-task.md`.

- **Canonical name**: Count Task Service. **Class**: `App\Services\Inventory\CountTaskService` (instance). **Registry**: key `count-task` (mode flow). **Module/sub-module**: Inventory > Warehouse > Count > Count Tasks. The only scheduled warehouse engine of this group (`inv:count-due`, daily 05:00).
- **Frozen status**: not frozen. Posting of the adjustment is delegated to `StockDocumentService` / `StockPostingService` (Stock group); this service never writes stock card rows itself.

## Trigger
| Method | Trigger | File:line |
|---|---|---|
| `start` | Start Count (one area) | `routes/v2_stockflow.php:44-54` |
| `startDue` | Start Due Areas | `routes/v2_stockflow.php:56-60` |
| `dueZones`, `start` | `php artisan inv:count-due` (scheduler daily 05:00 with `withoutOverlapping`, only if table `wh_task` exists) | `app/Console/Commands/InvCountDue.php:16-30`; `routes/console.php:54` |
| `confirm` | Confirm on a task | `routes/v2_stockflow.php:62-72` |
| `cancel` | Cancel on a task | `routes/v2_stockflow.php:74-80` |
| `dueZones` | screen option list "due" marks, Task Board "Count due" cards | `app/Support/V2/Data/CountTasks.php:40`; `app/Support/V2/Data/WarehouseTaskBoard.php:83` |

## Input / Output
- **Input**: a `WhZone`, a task, the counted qty (>= 0), a reason, the login; `wh_zone.count_every_days`; stock positions (`WarehouseStock::positions`); the balance (`StockTransferService::balance`).
- **Output (writes)**: `tm2inv.wh_task` rows (`task_type` COUNT, `system_qty`, `counted_qty`, `count_zone_id`, `adjustment_doc_id/_no`, status OPEN / DONE / CANCELLED); `tm2mst.wh_loc` (`count_lock`, `count_lock_by`, `count_lock_at`); a Stock Opname document (`doc_cat` OP, `ref_doc_type` COUNT_TASK, `opn_officer`) posted through the standard path.
- **Returns**: counts or the task; refusals throw `WarehouseTaskRefused` (translated text).

## Flow
1. **dueZones** (`:31-39`): zones active, not virtual, `count_every_days` > 0; exclude those with an OPEN COUNT task; due when never counted or `max(created_at)` of non-cancelled COUNT tasks + N days <= now.
2. **start** (`:48-70`): refuse if the area has an OPEN count; bins = active bins of the area; positions = stock > 0 in them; in one transaction create one task per position (`system_qty = qty`); after that transaction set `count_lock` on the bins that have positions.
3. **confirm** (`:73-94`): negative refused; in a transaction lock the task, require COUNT and OPEN; `now` = current balance of the position; `diff = round(counted - now, 4)`; if |diff| > 0.00005 `adjust`; mark DONE with `counted_qty`, adjustment ids, `confirmed_by/_at`; after the transaction `unlockIfFinished` for the bin.
4. **adjust** (`:110-127`): new document number `nextDocNo(class, 'OPN')`, one line (`qty` = diff, `qty_system` = now, `qty_physical` = counted, `action_type` COUNT), saved as draft then posted; if posting is refused the draft is cancelled and the refusal is thrown, so the task stays OPEN.
5. **cancel** (`:96-106`): OPEN COUNT only; status CANCELLED, reason (blank = "Cancelled"); unlock check.
6. **unlockIfFinished** (`:129-134`): unlock the bin when no OPEN COUNT task has it as `from_location_id`.

## Formula (as-is)
- `diff = round(counted - balance_at_confirm, 4)`; positive = surplus booked, negative = loss booked.
- Due date = latest task `created_at` + `count_every_days` days; completion date is not used.
- Worked example: area every 30 days, last task created 01-Oct, today 31-Oct 05:00 -> due (>= ). System 100, balance at confirm 100, counted 90 -> diff -10 -> Stock Opname with -10.

## Edge cases
- The lock is enforced only by transfer checks, putaway, pick allocation / Confirm Pick and the Customer Material default bin (`grep count_lock`); other stock documents ignore it, so the balance can change between Start and Confirm. FACT.
- Empty bins are not locked. FROM CODE (`:64`).
- Lock set outside the task-creation transaction. FROM CODE (`:55-67`).
- No approval before posting the opname (the design notes expect one, FROM DOCUMENT).
- A task whose bin no longer exists would fail in `adjust()` at `WhLoc::findOrFail` (the 12 dummy tasks are in this state). FROM CODE.
- Cancelled tasks still count as "last counted" only if non-cancelled: a cancelled task does not postpone the next count. FROM CODE (`:35`).

## Tests (read, not run)
`CountLockTest` (7 tests, listed in the Count Task card).

## Used by
Count Tasks screen, Task Board, `inv:count-due`, `BinRules` (reads the lock), `PutawayPlanner`, `PickAllocator`, `PickListService`, `CustomerMaterialService`.
