# Concept card: Material availability for production (Demand Forecast, Pre-Booking, Booking, Material Planning)

> Written 2026-10-17 (Manufacture group). "FACT" = seen first-hand in this session: mockup DB (`--env=mockup`, `tm2*` = `ddt*`; counts = `count(*)` today) and code with line numbers. "FROM DOCUMENT" = not checked. This card covers what the four Planning screens mean for the Work Order and how "Available" is defined; the engine itself has its card in Sales (`../../sales/order/30_engine_material-planning.md`) and is NOT repeated.

- **Document type**: concept card. **Module/sub-module**: Manufacture > Planning: Demand Forecast (MPS) `PROD_MTS_FORECAST` (`v2:demand-forecast`), Pre-Booking `PROD_PREBOOK` (`v2:material-prebooking`), Material Booking `PROD_BOOKING` (`v2:material-booking`), Material Planning (MRP) `PROD_MRP` (`v2:material-planning`).
- **Canonical name**: Material availability = (a) forecast demand `tm2prd.prd_demand_forecast`, (b) bookings `tm2inv.rm_booking` / `rm_booking_d` / `rm_booking_alloc`, (c) on-hand stock `tm2inv.v_rm_available`, (d) the PPIC number "Available" computed by `AvailableStock::of`.
- **Synonyms**: MPS, MRP, pre-booking (B0, B1), CO booking (B2, B3), Hold, Pick Item, Available, On hand, reserve.
- **NOT the same as**: the WO material line (`12_card_wo-material-bom.md`, which reads these numbers), the warehouse pick list (`../../inventory/warehouse/13_card_pick-list.md`), stock ledger (`../../inventory/stock/11_card_stock-card-and-balance.md`).

## Facts
| Item | Content | Evidence |
|---|---|---|
| Demand Forecast | `prd_demand_forecast` (3 rows, all active): customer x product x month: `customer_id`, `product_id`, `period_start`, `qty_forecast`, `notes`, `activate`. A CO of the same customer and product in the same month consumes the forecast; the rest is demand for Material Planning, without booking stock. Today: customer 67, products 14399, 14400, 14401, months Oct, Nov, Dec 2026. | FACT, query; `DemandForecastLookup.php` header |
| Booking headers | `rm_booking`: 71 rows, `booking_type` CO 70 and PRE 1; `stage` B2 63, B1 1, empty 7; `doc_status` DRAFT 67, CANCELLED 4; none CONFIRMED. 48 of the CO bookings already carry a `wo_id`. | FACT, query |
| Booking lines | `rm_booking_d`: 368 rows, columns `qty_need`, `need_date`, `qty_book`, `qty_purchase`, `line_status`, `calc_source`. NONE has `qty_book > 0` today. | FACT, query |
| Stock view | `v_rm_available`: 143 rows over 85 materials, 75 rows in usable locations. | FACT, query |
| PPIC "Available" | `AvailableStock::of(materialIds)`: on hand of usable locations (view) minus the open part of ALL booking lines of every stage (`qty_book - qty_issued > 0`, line and header not cancelled, header not closed, a HELD pre-booking past `hold_until` does not count); result floored at 0; computed on each view, never stored. | FACT, `AvailableStock.php:35-56` |
| Where Available is used | BOM sheet columns On hand and Available (`Ppic::stockOf`, `Ppic.php:1394`), the material gate (`GateService`), the APPLY check. | FACT, grep |
| Other definition | The view `v_rm_available.qty_available` = on hand minus CONFIRMED bookings only; still read by `MaterialPlanningEngine` (netting and location pick), `MaterialPlanning.php`, the dashboard widget `MaterialShortage`, the command `planning:run` and `SpecEngine` (ProDev stock status). Details in ATTENTION-ENGINE-02. | FACT, `docs/ENGINE-ATTENTION.md` item 02; line numbers there have moved |
| Triggers | Nightly `planning:run` 01:30 and `inventory:booking-maintain` 01:45 (`routes/console.php:29,53`); a CO Confirm or cancel; a WO created from a CO (model events in `PlanningServiceProvider`). Engine card has the table. | FACT, `routes/console.php`; `../../sales/order/30_engine_material-planning.md` |
| Who may change | Material Booking and Pre-Booking are changed by the Material Planning engine and its screen actions (Recalculate, Pick Item, Confirm, Hold); PPIC does not book (rule Jo 2026-10-13). The flag `PPIC_INVENTORY` was meant for bookings and shortage follow-up (see finding 4). | FACT, `Ppic` and `GateService` have no write to `rm_booking`; `2026_10_09_000060_ppic_access_flags.php` |

## Relations
- **source**: Customer Order and Quotation (`../../sales/order/10_card_customer-order.md`), ProCost (pre-booking from costing), Inventory stock (`../../inventory/stock/11_card_stock-card-and-balance.md`), Purchase (open PO and PR lines for the Incoming tab).
- **used_by**: BOM sheet and gates (`12_card_wo-material-bom.md`, `14_card_gates-and-readiness.md`, `22_engine_gate-service.md`), warehouse pick list, shortage requests (`../../inventory/batch-quality/15_card_shortage-request.md`), PPIC Workspace tabs Material and Incoming (`13_card_plan-draft-and-live.md`).
- **flows**: costing -> pre-booking (soft) -> CO Confirmed -> CO booking -> WO -> Release (no booking) -> pick list -> stock out. Release does NOT book; two WOs can release the same stock (ATTENTION-ENGINE-03).
- **frozen_zone**: ProCost / Material Planning territory; PPIC reads only.

## Findings (recorded, not fixed)
1. **The Pre-Booking screen says pre-bookings do not reduce Available** (REMARK of `material-prebooking.blade.php`: "bersifat lunak: tidak mengurangi Available"), while `AvailableStock` subtracts every open booking including Draft and PRE (Jo 2026-10-13 06:31Z, text in `AvailableStock.php:20-28`). The two statements cannot both be right for the PPIC number. FACT (code vs screen remark).
2. **No booking holds stock today** (368 lines, 0 with `qty_book > 0`; 0 CONFIRMED headers), so Available equals on hand and the whole rule is untested on real figures. The ATTENTION item quotes 227 lines; now 368, still 0 positive. FACT.
3. **Four screens, one engine, no planning decision by PPIC**: PPIC can only read; the actions on these screens belong to Material Planning. Menu placement under Manufacture > Planning is by user decision; the owner is ProCost. FACT.
4. **The access flag `PPIC_INVENTORY` is never checked**: `PpicAccess::canInventory` has no caller (grep); only `canSchedule` is used (`PpicController.php:60`). The migration describes it as the flag for bookings, shortage follow-up and ready dates. FACT.
5. **Forecast has 3 rows for one customer** and no screen shows its effect on a WO; the forecast creates no WO (`source_type MTS_FORECAST` has no writer, `10_card_work-order.md`). FACT.

## Already exists? Searched where, result
`concept:find "booking"`, `"forecast"`: Inventory cards (pick list, stock reports) and the Sales engine card exist; the Planning screens had no card of their own. Searched code: Available is defined twice (view and `AvailableStock`), see ATTENTION-ENGINE-02; no third definition. User's guide: `docs/guides/production/00_alur_data_dan_sistem.md` section 2.
- Re-checked 2026-10-22 after the entry rules of PR, PO, receipt, Delivery Order and CPDEL moved from the v1 pages to services (rule D.25 and D.26, Master T10 stage 7a-7c): `routes/v2.php` and `app/Support/V2/Screens.php` only had comment and description text changed on their existing lines (no line added or removed), and `app/Support/V2/Data/DeliveryOrder.php` and `MaterialPlanning.php` changed in the lines their cards do not cite or were remapped from the diff; this card's content is still true.
