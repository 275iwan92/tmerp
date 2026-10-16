# ENGINE card: StockTransferService (posting, approval, two-step In Transit)

> Written 2026-10-16. Verified: 2026-10-16. Source: `app/Services/Inventory/StockTransferService.php` (409 lines) read in full, with `app/Support/V2/Data/StockTransfer.php`, `app/Services/Inventory/VirtualLocationService.php`, routes `routes/v2_warehouse.php:84-133` and `routes/v2_stockflow.php:25-41`. Tests were NOT run (brief: no DB writes). Concept card: `15_card_stock-transfer-and-in-transit.md`.

- **Canonical name**: Stock Transfer Posting. **Class**: `App\Services\Inventory\StockTransferService` (instance). **Registry**: key `stock-transfer` (mode flow). **Module/sub-module**: Inventory > Stock > Stock Transfer; Warehouse > Transfer > In Transit.
- **Frozen status**: this class is the ONLY writer of stock card rows for transfers (header comment, `StockTransferService.php:19-24`); do not add a second writer. The stock card chain itself belongs to the Stock group.
- **No journal**: owner and value are unchanged by a transfer; nothing is posted to accounting.

## Trigger
| Method | Trigger | File:line |
|---|---|---|
| `confirm` (-> `submit` or `post`) | Confirm on a DIRECT draft (`StockTransfer::act`) | `app/Support/V2/Data/StockTransfer.php:276-283` |
| `post($t, $login, true)` | last approval level approves | `StockTransfer.php:337` |
| `post` | putaway confirm (bypasses approval) | `WarehouseTaskService.php:188` |
| `ship` | Confirm on a TWO_STEP draft | `StockTransfer.php:282` |
| `receive`, `resolveDiscrepancy` | In Transit screen | `routes/v2_stockflow.php:25-41` |
| `cancel` | Cancel with reason | `routes/v2_warehouse.php:103-108` |
| `confirmErrors` | the form (messages) and every posting method | `StockTransferService.php:50-93` |
No schedule or job.

## Input / Output
- **Input**: an `InvTransfer` with lines, login, reason text, received quantities per line, settle choice LOSS / RETURN; stock card rows (`rm_stockcard`, `sfg_stockcard`, `fg_stockcard`), bins, `flow_config` module TF, `ItemAverageCost`.
- **Output (writes, `tm2inv`)**: two stock card rows per line and step (`doc_type` TF, `reference_name` `tm2inv.inv_transfer_line:` + tag OUT / IN / CANCEL_OUT / CANCEL_IN / SHIP_OUT / SHIP_IN / RECV_OUT / RECV_IN / DIFF_OUT / DIFF_IN), `inv_transfer` (number, `stat_confirm`, `stat_cancel`, `flow_status`, `transit_status`, ship/receive stamps), `inv_transfer_line` (`unit_cost`, `was_dead_stock`, `qty_received`, `qty_diff`, `diff_resolution`), the flow log (through `FlowEngineService`), pallets (through `PalletService`).
- **Returns**: `?string` error (null = success); thrown `RuntimeException`s inside transactions become that string.

## Flow
1. **Checks** (`confirmErrors`, `:50-93`): lines exist; qty > 0; destination bin set, in the destination building, usable and active, not equal to the source; per position the sum of lines <= balance of the source bin; `BinRules::transferViolations` (Mixing on the destination, also among lines of the same document); `BinRules::countLockViolations`.
2. **Confirm** (`:96-99`): if `activeConfig('TF')` exists -> `submit`, else `post`.
3. **submit** (`:103-125`): lock the row, require DRAFT, run the checks, set `transfer_no` (`TF`yy+5, max+1 per year) and `flow_status` SUBMITTED, `FlowEngineService::submit` with the value; if no level is pending, `apply` at once.
4. **post / apply** (`:128-169`): lock; status must be DRAFT (SUBMITTED when `$approved`); checks again; per line `move` OUT/IN; `unit_cost` = average moved; `was_dead_stock` from the source position; set number, `stat_confirm`, `confirmed_by/_at`, `flow_status` APPROVED when it came through approval; then `PalletService::followTransfer`.
5. **move** (`:363-408`): lock the last card row of source and target positions; refuse when the source balance < qty ("... not enough stock in :bin (:qty on hand)."); new row date = latest of now and the two last row dates (so the new rows are always last in both chains); OUT row at the source average; IN row with weighted average `(balTo x avgTo + qty x avg) / max(eb, 0.0001)`, `value_in` = moved value; when `INV_AVG_SCOPE` = ITEM, `ItemAverageCost::recalculate` rewrites both rows to the item average.
6. **cancel** (`:173-207`): reason required; CONFIRMED or SUBMITTED only. Confirmed: move back with CANCEL tags (needs the stock still in the destination), `PalletService::revertTransfer`; a two-step transfer can be cancelled only while SHIPPED (stock comes back from In Transit); then `stat_cancel`, `cancel_reason`. Submitted: only the flags.
7. **ship** (`:216-247`): TWO_STEP and DRAFT; checks; stock to the `VirtualLocationService::transit()` bin (`VIRT-TRN`, created on demand); number born; `transit_status` SHIPPED; pallets follow to In Transit.
8. **receive** (`:250-289`): requires SHIPPED; each received qty 0 <= q <= sent (default = sent); count-lock and Mixing re-checked; moves `q` from In Transit to the destination; `qty_received`, `qty_diff`; `transit_status` DISCREPANCY if any shortfall else RECEIVED.
9. **resolveDiscrepancy** (`:292-319`): LOSS = move the difference In Transit -> Loss bin (`VIRT-LOSS`); RETURN = back to the source bin; line `diff_resolution` set; when none left `transit_status` CLOSED.

## Formula (as-is)
- Value of a document for approval: `round(sum(qty x readerAvg(class, item, part, last average of the source position)), 2)` (`:149-154`).
- OUT: `qty_bb` = balance, `qty_out` = qty, `qty_eb` = balance - qty, `value_eb` = qty_eb x avg.
- Worked example: source 100 @ 10, target 50 @ 20, move 40: OUT eb 60; IN eb 90, new average (50x20 + 40x10)/90 = 15.56.

## Edge cases
- Cancel of a Confirmed DIRECT transfer does NOT call `confirmErrors` (no lock, block or Mixing re-check). FROM CODE.
- Receive passes the original SOURCE bin of each line to `countLockViolations`, so a lock on the source bin blocks receiving. FROM CODE (`:265`).
- A submitted-then-rejected transfer keeps its number (born at submit). FROM CODE (`:114`).
- The reason of approval Reject / Hold is recorded by `FlowEngineService`, not in `inv_transfer`. FROM CODE.
- Dates: new rows may be dated later than the transfer date (see 5). FACT.

## Tests (read, not run)
`StockTransferServiceTest` (6), `TransitTransferTest` (8), `PalletServiceTest::test_with_approval_switched_on_the_stock_moves_only_after_the_last_approval`, `CountLockTest`, `WarehouseTaskServiceTest::test_confirming_with_the_scans_moves_the_stock_through_a_real_transfer`.

## Used by
Stock Transfer and In Transit screens, Putaway confirm, Pallet relabel move, Warehouse Map / Scan Track (create draft), Subcontract Send (EngineRegistry text, not read), Task Board.
