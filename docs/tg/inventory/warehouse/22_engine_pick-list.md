# ENGINE card: PickListService, PickAllocator and PickRequirement

> Written 2026-10-16. Verified: 2026-10-16. Source: `app/Services/Inventory/PickListService.php` (356 lines), `PickAllocator.php` (132), `PickRequirement.php` (66), `PickListRefused.php` read in full; routes `routes/v2_picklist.php`. Tests were NOT run (brief: no DB writes). Concept card: `13_card_pick-list.md`.

- **Canonical name**: Pick List Service + Pick Allocator. **Classes**: `App\Services\Inventory\PickListService` (static; flow), `App\Services\Inventory\PickAllocator` (instance per list, `new PickAllocator($customerId)`; suggestions), `App\Services\Inventory\PickRequirement` (value object from the spec text). **Registry**: key `pick-list` (mode flow); the allocator has its own key `pick-allocator` (mode suggest). **Module/sub-module**: Inventory > Warehouse > Pick List.
- **Frozen status**: not frozen, but Booking (`rm_booking`) and the WO belong to Production; this service reads them and writes only `rm_booking.stage` (set to B4) and `rm_booking_d.qty_issued`.
- **Stock is never invented**: what the allocator cannot cover becomes a SHORT line (rule: "bahan tidak pernah dikarang", `PickAllocator.php:25`).

## Trigger (screen actions only, no schedule)
| Method | Trigger | File:line |
|---|---|---|
| `create` | New Record > Create | `routes/v2_picklist.php:16-25` |
| `reallocate` / `confirm` / `cancel` | form buttons (POST `act`, field `action`) | `routes/v2_picklist.php:28-52` |
| `scan` | Scan card | `routes/v2_picklist.php:54-63` |
| `PickAllocator::allocate` | called by `fill` on create and reallocate | `PickListService.php:86-110` |

## Input / Output
- **Input**: WO id (`tm2prd.prd_wo`), optional staging bin, login; the Confirmed booking (`rm_booking`, `rm_booking_d`); pallets, batches, bins/areas, `rm_stockcard` owner, `sys_constant` `INV_PICK_CONDITIONAL_ALLOWED`.
- **Output (writes, `tm2inv`)**: `wh_pick_list`, `wh_pick_list_line`; on Confirm a Stock Out RM document (`rm_stock`/`rm_stock_d` with `batch_id`, `qty_booked`, `owner_type`, `owner_id`) through `StockDocumentService` + `StockPostingService`; `inv_pallet` (relabel) and `inv_pallet_log`; `rm_booking_d.qty_issued`; `rm_booking.stage`.
- **Returns**: `create` the list; `confirm` `['docs' => [numbers], 'warning' => journal warning]`; all rule failures throw `PickListRefused` with a translated message (nothing changes).

## Flow
1. **create** (`PickListService.php:32-65`): WO must exist; refuse if an open list (DRAFT / IN_PROGRESS) exists. Inside one transaction on `tm2inv`: find the Confirmed, not cancelled/closed booking (WO's own first, else the CO's) and lock its header (two officers creating from one booking take turns); number `PK`yy+5; need lines = booking detail rows of the WO's product, not cancelled, with material, not role FG, `qty_need` > 0, matching delivery and part; if none, refuse. Allocate (below); if no line results, refuse; set `need_date` = earliest need date; booking `stage` B4; default `deliver_to_location_id` = first usable STAGING bin of the building of the first allocated bin.
2. **fill** (`:86-110`): remaining = `qty_need` - `qty_issued` - what ALLOCATED/PICKED lines of open lists already hold for that detail (`heldForDetail`); `PickRequirement::fromSpec` reads grain (LONG/SHORT), certification (FSC/PEFC) and food grade from the free-text `spec_text` only when stated; allocate; the uncovered part = one SHORT line.
3. **allocate** (`PickAllocator.php:38-52`): walk candidates in order, take `min(need, available)` from each.
4. **candidates** (`:55-84`): pallets ACTIVE, class RM, qty > 0, item = material; available = qty - held by open lists; drop when: bin missing/blocked/inactive/count-locked or area virtual or not STORAGE/CONSIGN; available <= 0; expiry (batch, else pallet) before today; batch refused; owner not allowed. Order: FIFO key = arrival date, FEFO key = expiry date (per the area's `pick_order`), then arrival, then id.
5. **batch rules** (`:87-97`): batch not allocatable (Quarantine, Rejected, Blocked) refused; Conditional refused when the constant < 1, or the batch is food grade, or FSC/PEFC; then `PickRequirement::refusal` (no batch while a requirement exists; wrong grain; wrong certification; not food grade).
6. **owner rule** (`:107-122`): owner = latest `rm_stockcard` row of that material in that bin (and batch); COMPANY allowed; CUSTOMER allowed only if it is the CO's customer; without a card = COMPANY.
7. **scan** (`:151-209`): pallet scan sets `scan_pallet_at/_by` (code must equal the pallet number); bin scan needs the pallet scan first, re-checks that the pallet is still ACTIVE, in the allocated bin and holds at least the allocated qty, then sets PICKED with `qty_picked = qty_allocated`. Either scan moves DRAFT to IN_PROGRESS. `scan()` picks the matching line itself.
8. **confirm** (`:254-280`): refuse SHORT / ALLOCATED lines, nothing picked, any picked bin count-locked; unless `stock_out_doc_id` is already set, issue stock (one document per warehouse) and save the numbers; then `finish`.
9. **finish** (`:322-338`, one transaction): relabel each picked pallet with `qty - picked` (0 closes), add to `qty_issued`, set DONE with `confirmed_by/_at`.
10. **cancel** (`:342-355`): open list, reason required, no Stock Out yet; lines and header CANCELLED.

## Formula (as-is)
- `remaining = qty_need - qty_issued - held`; tolerance `EPS` 0.00005 everywhere.
- Short = `max(0, round(need left after the last candidate, 4))`.
- Worked example: need 12,000 LBR; pallets A 5,000 and B 5,500 (FIFO) and C 4,000 are allowed; lines A 5,000, B 5,500, C 1,500; short 0. With C missing: lines A, B and a SHORT line "Short 1500".

## Edge cases
- `issueStock` posts one Stock Out per warehouse in a loop outside any transaction; if a later warehouse is refused the earlier document stays posted but `stock_out_doc_id` is not saved, so a retry would issue the first group again (finding in the Pick List card). FROM CODE, not reproduced.
- Pallets without a label are invisible to the allocator (it reads `inv_pallet`, not the stock card), so unlabelled stock shows as SHORT. FACT.
- `reallocate` keeps lines with a scanned pallet and PICKED lines and recomputes the rest; the held quantity of kept lines is subtracted, so no double count. FROM CODE.
- Class A logic uses `food_grade` and certification only; no separate "class" field exists. FROM CODE.
- `deliver_to_location_id` is never used to move stock. FACT.

## Tests (read, not run)
`PickListServiceTest` (13 tests, listed in the Pick List card).

## Used by
Pick List screen and print, Task Board, Warehouse Map pick layer (`PickLists::mapLayer`), Customer Material tests (`CustomerMaterialTest` in the registry).
