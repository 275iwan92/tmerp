# Concept card: Labels (Location, Item, Pallet), QR/barcode Scan Track and Label History

> Written 2026-10-16 (group Inventory-Warehouse). Verified: 2026-10-16. FACT = mockup DB (`ddt*` through `tm2*`, `--env=mockup`) and code with line numbers, seen this session. FROM DOCUMENT = not checked. Tests were NOT run (brief: no DB writes). No printer, scanner or phone camera was tried.

- **Document type**: concept card (print outputs plus a lookup screen; the only table is the print log). **Module/sub-module**: Inventory > Warehouse > Labels & Scan (`INV_LBL` group, status NOT_BUILT on the group row but READY_FOR_QC on its items): Print Location Label (QR) `v2:label-location`, Print Pallet Label (QR) `v2:label-pallet`, Print Item Label (QR) `v2:label-item`, Track via QR/Barcode `v2:scan-track`, Report > Label Print History `v2:inv-label-history`; item "Scan Transaction (Mobile Camera)" (`INV_SCAN_ITEM1`) is hidden and NOT_BUILT (FACT `sys_app_menu`).
- **Canonical name**: label content = `App\Support\V2\Data\Labels` (builds `x-dl::label` data); print log = `tm2inv.inv_label_print` (model `InvLabelPrint`); lookup = `Labels::track` / `trackDesk` shown by `x-mp::inv.scan-track`; printing sheet = `x-dl::label-sheet`.
- **Synonyms**: label lokasi / bin, label item, label palet, QR, barcode, pindai, lacak, scan, "Scan Center", riwayat cetak.
- **NOT the same as**: product/carton labels for customers (not in this module), Surat Jalan QR (Sales), the pallet record itself (card `14_card_pallet.md`).

## Facts
| Item | Content | Evidence |
|---|---|---|
| Three different labels | LOCATION (bin code, area, floor, building, max weight, volume, floor area, pallets, "In use" %; ribbon colour = area type; tags Blocked, Holds dead stock, expiry); ITEM (one stock position: code, name, qty, bin, arrived, lot, expiry, weight; ribbon = stock class RM blue / SFG amber / FG green; tags Dead stock, expiry); PALLET (pallet or pack number, item, qty now, arrived, supplier pallet code, lot, receipt, bin + stack no., expiry, "At arrival" qty when different, label count; ribbon = arrival month; footer "After every use: print a new label with the remaining quantity."). Each has a QR (address of the Track screen) and a 1D barcode of the plain code. | FACT, `app/Support/V2/Data/Labels.php:23-117` |
| Sizes and paper | Sizes sm 60 x 40 mm, md 100 x 50 mm, lg 100 x 150 mm (21 / 10 / 2 per A4); paper "Label roll (label printer)" or "A4 sheet (office printer)"; copies 1-20 per label; at most 300 labels per print (`Labels::MAX_PER_PRINT`). Smaller sizes drop lines (sm keeps 3). A pack on size lg is printed as md. | FACT, `../devlib/src/Components/Label.php:17-20`; `LabelSheet.php:12`; `app/Support/V2/Data/Labels.php:31,121-138`; `routes/v2_warehouse.php:184,210` |
| Print log | `inv_label_print`: 1 row (PALLET `PL2600032`, FG, qty 12,000 PCS, size lg, 1 copy, by `johiwan`, 03-Oct-2026). Columns `label_type` (LOCATION/ITEM/PALLET), `ref_code`, `ref_name`, `stock_class`, `ref_id`, `lot_no`, `qty`, `uom_code`, `copies`, `label_size`, `printed_at`, `printed_by`. One row per unique label per Print action. | FACT, query; `app/Support/V2/Data/Labels.php:245-274`; `app/Models/Inventory/InvLabelPrint.php:10-22` |
| Print flow | Screen form -> POST `labels.print` (validates type, 1-300 keys, size, paper, copies, back) -> `Labels::log` (history) -> for pallets `PalletService::printed` (label_count + 1) -> redirect to the printable sheet `labels.sheet` -> browser print. The sheet's Back button returns to the origin screen (`warehouse-map`, `stock-transfer`, `scan-track`, `label-*`, `purchase-receive`). | FACT, `routes/v2_warehouse.php:137-164` |
| Location label screen | Choose building, optionally a storage area (ticks all its bins), tick bins, size, paper, copies. A single bin label can be printed straight from the Map/Track (`bin.label`, size md, A4). | FACT, `app/Support/V2/Data/Labels.php:343-364`; `routes/v2_warehouse.php:238-243` |
| Item label screen | Choose building and stock positions; from a goods receipt (`?receipt=`) the receipt's positions are pre-ticked and buttons lead back to the receipt and to its pallet labels. Hint: "One label per pack or stack: type how many packs carry the same label." | FACT, `app/Support/V2/Data/Labels.php:368-399` |
| Scan Track | Type, scan (USB/Bluetooth scanner presses Enter) or use the phone camera button; input is parsed: a QR address (`...?code=LOC:xxx`), prefixed `LOC:` / `ITM:RM:code` / `PLT:no`, or a plain code. Result types: bin (label, contents, facts, Show on Map, Label), pallet (label, facts, history, buttons Move this Pallet, Relabel / Print, Show on Map), Stock Transfer (number), item (positions, pallets, last 15 movements), or "Nothing found". | FACT, `app/Support/V2/Data/Labels.php:278-339,403-467`; `packages/mockup-parts/resources/views/inv/scan-track.blade.php` |
| Lookup order for a plain code | bin code first, then pallet number, then transfer number, then item code (RM, SFG, FG masters). | FACT, `app/Support/V2/Data/Labels.php:298-339` |
| Label History | Grid with filters Label, Size, Printed By and groups; no stat boxes. | FACT, `app/Support/V2/Grid/Inv/LabelHistoryReport.php:62-89` |
| Scan used for work | Putaway and Pick List scans accept the same code formats through their own parsers (`Labels::parse` in putaway; a private `code()` in pick list). | FACT, `WarehouseTaskService.php:137-151`; `PickListService.php:219-227` |

## Relations
- **source**: bins/areas, stock positions (`WarehouseStock`), pallets, purchase receipt (receipt number and date), constants `WH_EXPIRY_SOON_DAYS`, `ASSISTANT_DEADSTOCK_MONTHS`.
- **used_by**: Purchase Receipt form (buttons to item / pallet labels), Warehouse Map, Stock Transfer (bin labels), Stack Monitor (Open), putaway and pick scans.
- **flows**: print only; the QR opens the Track screen (needs login).
- **frozen_zone**: no. Rule A.22 (FROM DOCUMENT): item and pallet labels must differ; every label has QR, barcode and arrival date; expiry tag for paper and ink.

## Findings (recorded, not fixed)
1. **Two parsers for one label format**: putaway uses `Labels::parse` and accepts a pallet scan only when the prefix is `PLT` or none; the pick list uses a private `code()` that strips any of `LOC|PLT|ITM` first, so `LOC:PL2600001` would count as a pallet scan there but not in putaway. FROM CODE (`PickListService.php:219-227` versus `app/Support/V2/Data/Labels.php:281-295` and `WarehouseTaskService.php:137-143`); not tried on screen.
2. **A GET route writes**: `bin.label` (`GET /v2/warehouse/bin/{id}/label`) writes a history row every time it is opened, and it can be re-opened by refresh/back. FACT, `routes/v2_warehouse.php:238-243`.
3. **History records requests, not prints**: the log is written before the sheet opens; cancelling the browser print dialog still leaves a row, and reprinting from the sheet itself writes nothing. FROM CODE.
4. **Group status vs item status**: `INV_LBL` is NOT_BUILT while its items are READY_FOR_QC; the hidden "Scan Transaction (Mobile Camera)" item is the unbuilt part (no mobile transaction screen). FACT.
5. **Ambiguous plain codes**: a bare number that is both a bin code and an item code resolves to the bin silently. FROM CODE (lookup order). Unlikely with the current code formats.
6. **Item label qty is a snapshot**: an item label prints the on-hand at print time and nothing updates or voids it after stock moves (only pallet labels have a relabel rule). FROM CODE.
7. **Hardware and layout untested here**: printable sheet sizes, QR readability, camera scan and the label roll were not tried.

## Already exists? Searched where
Searched `inv_label_print`, `InvLabelPrint`, `Labels`, `LabelHistoryReport`, devlib `Label`, `LabelSheet`, mockup-parts `inv/label-print`, `inv/pallet-label`, `inv/scan-track`, routes `v2.wh.labels.*`, menu `INV_LBL*`. Result: exists; no new concept proposed.
