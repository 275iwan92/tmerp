# Concept card: Warehouse Task (wh_task) and the Task Board

> Written 2026-10-16 (group Inventory-Warehouse). Verified: 2026-10-16. FACT = mockup DB (`ddt*` through `tm2*`, `--env=mockup`) and code with line numbers, seen this session. FROM DOCUMENT = not checked. Tests were NOT run (brief: no DB writes); test names are read from the files.

- **Document type**: concept card (a work item table plus a read-only board). **Module/sub-module**: Inventory > Warehouse > Task Board (`WH_TASK` group, `WH_TASK_BOARD` `v2:wh-task-board`; FACT `sys_app_menu`).
- **Canonical name**: Warehouse Task = `tm2inv.wh_task` (number `WT` + yy + 5 digits, `WhTask::nextNo`). Task Board = `WarehouseTaskBoard` (`app/Support/V2/Data/WarehouseTaskBoard.php`) shown as a read-only kanban.
- **Synonyms**: tugas gudang, work item, kanban gudang, task board.
- **NOT the same as**: production tasks / scheduling (PPIC), the Pick List (its own tables), a Stock Transfer in Transit (own table; the board only shows it).

## Facts
| Item | Content | Evidence |
|---|---|---|
| Table | `wh_task`: 12 rows (all `task_type` COUNT). Columns: `task_no` UNIQUE, `task_type`, `status` (varchar 10, default OPEN), `stock_class`, `item_id`, `part_id`, `item_code`, `item_name`, `pallet_id`, `batch_id`, `qty`, `uom_code`, `from_location_id`, `to_location_id`, `ref_doc_no`, `is_urgent`, `wo_id`, `assigned_to`, `scan_pallet`, `scan_bin`, `reason`, `transfer_id`, `cancel_reason`, `confirmed_by/_at`, `system_qty`, `counted_qty`, `count_zone_id`, `adjustment_doc_id`, `adjustment_doc_no`. | FACT, `show columns`; count |
| Constraints | Foreign keys inside `tm2inv`: `fk_wh_task_batch` (-> `inv_batch`), `fk_wh_task_pallet` (-> `inv_pallet`), `fk_wh_task_transfer` (-> `inv_transfer`), all ON DELETE SET NULL. `from_location_id`, `to_location_id`, `count_zone_id`, `wo_id` point into other databases and have no FK. Only plain indexes otherwise (`ix_wh_task_type_status`). | FACT, `referential_constraints`, `show index` |
| Types and statuses | `WhTask::TYPES`: RECEIVE, PUTAWAY, PICK, MOVE, COUNT; `STATUSES`: OPEN, DONE, CANCELLED. Only PUTAWAY (card `12_card_receiving-and-putaway.md`) and COUNT (card `16_card_count-task.md`) are ever created. Putaway tasks have a derived run state (WAITING, READY, TO_LINE, BLOCKED) that is not stored. | FACT, `app/Models/Inventory/WhTask.php:15-17`; grep for creators |
| Writers | Rows are written only by `WarehouseTaskService` and `CountTaskService`; screens never write the table. | FACT, `WhTask.php:10-12`; grep `WhTask::create` |
| Board columns | Open / In progress / Done (Done = last 7 days). Cards come from four sources: `wh_task` (OPEN to Open, DONE since 7 days to Done), open pick lists (DRAFT to Open, IN_PROGRESS to In progress, DONE since 7 days to Done), two-step transfers (SHIPPED or DISCREPANCY to In progress, RECEIVED or CLOSED since 7 days to Done), and one "Count due" card per area past its count interval (Open). Each card has a tag (type), a priority High for URGENT tasks and discrepancy transfers, and a button Open that goes to the owning screen. The board writes nothing. | FACT, `WarehouseTaskBoard.php:17-86`; `resources/views/v2/screens/wh-task-board.blade.php` |
| Limits | 300 tasks, 200 pick lists, 200 transfers per load. | FACT, `WarehouseTaskBoard.php:43,58,72` |
| Data in the dummy | The board would show the 2 OPEN count tasks (orphaned, see card 16), no putaway task, no open pick list (all 16 are CANCELLED and hidden), no in-transit transfer, and no "Count due" card (no area has `count_every_days`). | FACT, queries |
| Tests (read, not run) | `WarehouseTaskBoardTest` (3: three columns with tasks by status and links, read-only kanban, in the Warehouse menu). | FACT, test file |

## Relations
- **source**: `WarehouseTaskService` (putaway), `CountTaskService`, `PickListService` tables, `StockTransferService` two-step fields.
- **used_by**: the warehouse supervisor's overview; links to `wh-receiving-putaway`, `wh-count-tasks`, `wh-pick-list`, `wh-in-transit`.
- **flows**: none of its own; it only reads.
- **frozen_zone**: no.

## Findings (recorded, not fixed)
1. **Three declared task types are never produced** (RECEIVE, MOVE, PICK): the board has a route branch for PICK tasks (`wh-pick-list`) that no row can reach; pick lists have their own tables. FACT, grep. A future dead-code cleanup should decide whether to drop these types or implement them.
2. **No assignment**: `assigned_to` exists but nothing writes or filters on it; every officer sees every task. FACT, grep.
3. **Unlimited re-creation**: because `sync()` only creates a task for a pallet without an OPEN one, a pallet that is moved out and back creates a new task each time; the tasks table grows with no archive (CANCELLED/DONE rows are kept forever). FROM CODE (`WarehouseTaskService.php:40-63`).
4. **The board ignores CANCELLED tasks and CANCELLED pick lists** (not in any column), so a supervisor cannot see why a task disappeared. FACT, `WarehouseTaskBoard.php:43,58`.
5. **Task numbers are shared**: putaway and count tasks use one sequence, so the numbers WT2600001-12 are all used by test count tasks. FACT.

## Already exists? Searched where
Searched `wh_task`, `WhTask`, `WarehouseTaskBoard`, `wh-task-board`, `assigned_to`, design note 02 (outdated: "Task Board belum dibangun"). Result: exists; no new concept proposed.
