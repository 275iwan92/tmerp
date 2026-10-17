# Sub-module: Warehouse (Map, Receiving & Putaway, Pick List, Transfer, Count, Labels, Setup)

> Written 2026-10-16. Verified: 2026-10-16. This page only summarizes and links; table/column facts are in the cards. Screens were read from code, not opened in a browser; tests were read, not run.

- **Module**: Inventory > Warehouse (`WH`). Menu groups (FACT, `sys_app_menu` mockup, active): Map & Monitor (`WH_MAP`: Warehouse Map `v2:warehouse-map`, Stack Monitor `v2:stack-monitor`, Stock by Location `v2:inv-rpt-location`), Receiving & Putaway (`v2:wh-receiving-putaway`), Pick List (`v2:wh-pick-list`), Transfer (In Transit `v2:wh-in-transit`; Stock Transfer and its Approval Center sit under Inventory > Stock > Stock Transfer: `v2:stock-transfer`, `v2:approval-tf`), Count (Count Tasks `v2:wh-count-tasks`), Labels & Scan (`INV_LBL`: location/pallet/item QR labels, Scan Track `v2:scan-track`, Label Print History), Task Board (`WH_TASK`), Setup (Buildings `v2:wh-buildings`, Storage Areas `v2:wh-zones`, Bins `v2:wh-bins`, Warehouse & Location `v2:md-warehouse`).
- **Purpose**: Inventory answers what, how much, value and owner; Warehouse answers where the goods are and how they are physically handled (map, bins, putaway, picking, counting, labels).
- **Frozen zone**: none of this sub-module; it consumes Work Order / BOM requirements read-only.
- **Rules**: stock posting rules in [../stock/](../stock/); batch status flags in [../batch-quality/](../batch-quality/). Design intent: `docs/design-notes/inventory/`, `docs/warehouselayout_tier1.md` (FROM DOCUMENT, not checked).

## Relations
- **source**: Purchase Receive (stock arrives in a Receiving area), Work Order requirement (pick list), Stock Posting engine, location master.
- **used_by**: Stock Card (location per card row), Production (issue by pick list), Production tooling (kept in Manufacture > PPIC Settings > Tooling Master, not here), QC (QC Hold areas).
- **flags**: transfer/pick/count have their own status columns (see cards), not `stat_confirm`.

## Cards and engines in this sub-module
| Type | File | Content |
|---|---|---|
| Concept | `10_card_location-hierarchy.md` | Building, Storage Area, Bin |
| Concept | `11_card_warehouse-map-and-monitors.md` | Warehouse Map, Stack Monitor, Stock by Location |
| Concept | `12_card_receiving-and-putaway.md` | Receiving & Putaway |
| Concept | `13_card_pick-list.md` | Pick List per Work Order |
| Concept | `14_card_pallet.md` | Pallet and stack |
| Concept | `15_card_stock-transfer-and-in-transit.md` | Stock Transfer, Approval Center, In Transit |
| Concept | `16_card_count-task.md` | Count Task, bin lock |
| Concept | `17_card_warehouse-task-and-board.md` | `wh_task`, Task Board |
| Concept | `18_card_labels-and-scan.md` | Labels, Scan Track, Label History |
| ENGINE | `20_engine_bin-generator.md` | `BinGenerator` |
| ENGINE | `21_engine_putaway-planner.md` | `PutawayPlanner`, `BinRules` |
| ENGINE | `22_engine_pick-list.md` | `PickListService`, `PickAllocator`, `PickRequirement` |
| ENGINE | `23_engine_stock-transfer-service.md` | `StockTransferService` |
| ENGINE | `24_engine_count-task-service.md` | `CountTaskService` |
| ENGINE | `25_engine_pallet-service.md` | `PalletService` |
| ENGINE | `26_engine_warehouse-task-service.md` | `WarehouseTaskService` |
| ENGINE | not yet written | `VirtualLocationService`, `WarehouseStock` (mentioned in the cards by registry key) |

## Diagram
Not yet made.

## Existing user guides (UG)
`docs/guides/inventory/20_penerimaan_dan_putaway.md`, `21_pick_list_dan_transfer.md`, `22_hitung_stok_dan_tugas_gudang.md`, `23_peta_gudang_label_dan_scan.md`, `24_master_lokasi_gudang.md`.

## Cross-card findings (short; details in the cards)
Mockup data has test residue (11 of 15 transfers point to deleted bins, all 16 pick lists cancelled on a fake WO, 12 count tasks reference deleted areas), so no real putaway/pick/in-transit flow has run on dummy data; the count lock covers only transfer, putaway, pick and the Customer Material default bin (not goods receipts or other stock documents); Confirm Pick is not atomic across warehouses; 78 of 105 raw-material positions have no pallet label so the pick allocator shows them Short; `WhTask` types RECEIVE/PICK/MOVE are declared but never created; cancel of a confirmed transfer skips the Confirm checks.
