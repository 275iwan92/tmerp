# ENGINE card: Material Planning Engine (interface side to Sales and Customer Order)

> DRAFT GROUP C (2026-10-16), awaiting Jo's approval. This card deliberately covers ONLY the Sales/CO side: what the engine reads from Inquiry, Costing, Quotation, CO and CPDEL, and when it is triggered by Sales documents. Material calculations (ink, varnish, PO/PR netting, Buy) are covered briefly; the full card belongs to the Planning/Inventory module. Source: `app/Services/Planning/MaterialPlanningEngine.php` (1055 lines, the demand, pre-booking, persist, confirm parts read in full; the ink and material standard part `inkLines/standardLines` was NOT read), `PlanningServiceProvider.php`, `BookingMaintenance.php`. Tests run: `PreBookingTest` (9 passed, 199 asserts). Worked examples calculated from `ddt*` data via `demands()`/`plan()` (read only).

- **Canonical name**: Material Planning Engine (MPS + MRP + Booking in one engine). **Class**: `App\Services\Planning\MaterialPlanningEngine`. **Registry**: key `material-planning`, mode `suggest`, group Planning. **Module/sub-module**: Production > Planning (menu); upstream interface to Sales > Order > Customer Order.
- **Frozen status**: CO/CPDEL flow = frozen zone. The engine only READS Sales documents and masters; it writes only bookings (`rm_booking`, `rm_booking_d`, `rm_booking_alloc`) in `tm2inv`.
- **Synonyms**: pre-booking, CO booking, MRP, B0/B1/B2/B3, Pick Item, Hold. **Neighbor cards (Sales)**: `10_card_customer-order.md`, `11_card_cpdel.md`, `20_engine_co-order-builder.md`, `21_engine_cpdel-promise.md`, `../quotation/10_card_quotation.md`.
- **NOT the same as**: `GateService` (PPIC WO readiness gate; it only reads the `plan()` result via `$this->mrp->plan()['lines']`, `GateService.php:57`) and `BookingMaintenance` (separate card in the registry, `booking-maintenance`).

## Trigger
FACT, from code:
| Trigger | Method | File:line |
|---|---|---|
| CO changes `doc_status` or `stat_cancel` (Confirm CO, Back to Draft, Cancel) | `persist` when CONFIRMED; `release` when cancelled or not CONFIRMED | `app/Providers/PlanningServiceProvider.php:22-35` (model event `CoOrder::updated`, wrapped in `try` so a failure does not fail Confirm CO) |
| WO born from CO (Post to Production) | `persist` then write the first `wo_id` to the header | `PlanningServiceProvider.php:37-47` (event `PrdWo::created`, only when `source_co_id` exists) |
| Nightly schedule 01:30 | `runAll` (then `runPre`) | `routes/console.php:29` -> command `planning:run` (`PlanningRun.php:22`) |
| Nightly schedule 01:45 | `BookingMaintenance->run` -> `release` stale bookings | `routes/console.php:53` -> `inventory:booking-maintain` (`BookingMaintain.php:27`) |
| Buttons on the v2 screens (Recalculate All, Recalculate one CO, Confirm, Back to Draft, Pick Item, Hold, Release Hold, Recalculate Pre-bookings) | `runAll`, `persist`, `confirm`, `unlock`, `pick`, `hold`, `releaseHold`, `runPre` | `routes/v2_planning.php:19, 31, 37, 41, 62` etc.; screens `material-booking`, `material-planning`, `material-prebooking` |
| Screens read the result | `plan` | `app/Support/V2/Data/MaterialPlanning.php:110`, `PreBookings.php`, and `GateService` |
Not triggered directly by the Costing Engine, Quotation, or Reverse (FACT, grep); a new pre-booking appears at the next `runAll`/`runPre` (nightly schedule or button).

## Flow (text twin of diagram `../img/material-planning.svg`)
1. **Demand from Sales**: (a) each active CO (`DRAFT`, `SUBMITTED`, `CONFIRMED`, not cancelled) per PRODUCT line, per CPDEL delivery schedule with status PLANNED (without a schedule: one row with the item `due_date`); (b) MPS forecast remainder; (c) pre-booking from PUBLISHED costings that do not yet have an active CO.
2. **Need date** per row (formula below).
3. **Priority order**: CO Confirmed (1), CO Draft/Submitted (2), Forecast (3), Quotation (4), Costing pre-booking (5); then need date; then CO id.
4. Finished-goods stock of the same product is booked first; the remaining qty is split into materials (paper from the CONFIRMED ProDev layout, then from the inquiry costing, then from the master BOM; ink and other materials from the usage standard).
5. **Netting** per material: available stock, on-time PO, PR, late PO/PR, then SHORT.
6. **Write bookings** only for CONFIRMED COs (`persist`, status DRAFT, soft) and pre-bookings (`persistPre`). Bookings already Confirmed by PPIC are frozen: read from the DB, not recalculated; a CO that changes afterwards only raises a "CO changed" warning (fingerprint).
7. One chain, one header: a pre-booking of the same inquiry is taken over by the CO when the CO is CONFIRMED (`adoptPre`, B0/B1 become B2/B3).
**Officer decision points (A.7)**: PPIC Confirm / Back to Draft, Pick Item (choosing the material item), Hold; the engine only proposes.

## Input / Output
- **Input (Sales side, read)**: `tm2sdi.co_order` (`doc_status`, `stat_cancel`, `co_date`, `co_no`, `customer_id`), `co_order_item` (`line_kind` PRODUCT, `product_id`, `qty`, `due_date`, `inquiry_id`), `co_order_delivery` (`status` PLANNED, `ship_qty`, `ship_date`), `iqp_costing` (`published_flag`, `reversed_at`, `inquiry_id`, size/qty columns only; price columns are not read), `iqp_costing_part`, `iqp_inquiry` (`stat_cancel`, `due_date`, `product_id`), `iqp_tier`, `quo_quotation` + `quo_quotation_item` (`doc_status`, `stat_cancel`, `valid_until`); others: `tm2prd.prd_demand_forecast`, product/material masters, `tm2inv.v_rm_available`, `tm2pur.po_line`/`pr_line`, `sys_holiday`, `sys_office_hour`; constants `BOOKING_NEED_WORKDAYS` (3), `INV_PRE_BOOKING_NEED_WORKDAYS` (14), `MRP_HORIZON_DAYS` (90), `BOOKING_DEFAULT_WASTE_PCT` (5), `INV_BOOKING_HOLD_WORKDAYS` (3), `PURCHASE_LEAD_DAYS_DEFAULT` (7) (values: mockup DB, 2026-10-16).
- **Output (writes)**: `tm2inv.rm_booking` (header: type CO/PRE, stage B0-B4, `chain_key` = inquiry id, `co_fingerprint`, hold), `rm_booking_d`, `rm_booking_alloc`. Does not write Sales tables.
- **Who may overwrite**: the engine overwrites bookings with status DRAFT; CONFIRMED/HELD bookings are changed only by PPIC (Back to Draft, Release Hold) or by `release` on CO cancellation / stale booking.

## FORMULA
1. **CO row need date**: `batas = co_date + BOOKING_NEED_WORKDAYS hari kerja`; `need = due (tanggal kirim CPDEL atau due_date item)` if `due < batas`, otherwise `batas` (lines 145-170). Working day = not an active `sys_holiday` holiday and not a non-working day in `sys_office_hour` (mockup DB: only Sunday = 7; 51 active holidays). Worked example: CO2600022 `co_date` 2026-10-01 (Thursday), due 2026-10-30 -> `need` 2026-10-05 (rule "CO date + 3 working days"); manual check: Friday 10-02 (1), Saturday 10-03 (2), Sunday off, Monday 10-05 (3). The engine result is the same.
2. **Forecast remainder** per customer x product x month: `sisa = qty_forecast - jumlah qty item CO bulan itu` (month = `due_date`, or `co_date` when empty); a row is created when `sisa > 0`; need date = first working day on or after the start of that month (if the period start has passed: the next working day after today); horizon `MRP_HORIZON_DAYS`.
3. **Pre-booking**: costing `published_flag = 1`, not yet `reversed_at`, inquiry not cancelled, inquiry does not yet have an active CO and is not on Hold. Qty = the main tier qty of the inquiry (`is_primary` then `tier_seq`). Inquiry quotation status: `ACTIVE` (a published quotation exists, not expired) -> stage B1, tier 4; `NONE` (no quotation or still draft) -> B0, tier 5; `DEAD` (cancelled or all expired) -> not counted. Need date = inquiry `due_date` if present, otherwise today + `INV_PRE_BOOKING_NEED_WORKDAYS` working days.
4. **Sheets per part**: `lembar = ceil( ceil(qtyBagian / UP) * (1 + waste/100) )`, `qtyBagian = qty_dibuat * qty_per_sku`; UP and waste from the CONFIRMED ProDev layout (`up_count` = UP per plano; a combined layout uses `qty_up_in_group` of that part; `waste_pct` of the layout or `BOOKING_DEFAULT_WASTE_PCT`), otherwise from costing (`UP = round(i_cut_per_plano * max(1, i_isi_per_lb))`), otherwise from the BOM material size vs the spread (`max(floor(W/w)*floor(H/h), floor(W/h)*floor(H/w))`) (lines 546-585).
5. **Netting** (`net`): per row in priority order (tier, need date, CO): take available stock first, then unused PO/PR (in stock unit): on time first (date <= need date), then late ones; the remainder = `SHORT`. Statuses: `SHORT`, `LATE_PO`, `LATE_PR`, `PEGGED_PR`, `PEGGED_PO`, `STOCK`.
6. **CO fingerprint** = `sha256` of the CO items (`id_item`, `product_id`, `qty`, `due_date`) and the PLANNED schedules (`id_delivery`, `ship_qty`, `ship_date`) (lines 993-998).

**Worked example (mockup DB, `demands()` and `plan()`, read only)**: 33 demand rows: CO Confirmed (tier 1) 28, CO Draft/Submitted (tier 2) 2, Forecast (tier 3) 3; 1 pre-booking (B1). `plan()` produces 321 material rows and 18 materials; row statuses: SHORT 162, NEED_ITEM 103, STOCK 51, NEED_LAYOUT 3, FG_STOCK 2. These figures reflect the dummy data (little stock and PO/PR), not business truth.

## Edge cases
- CO without a CPDEL schedule: one row with the item `due_date` (`demands`).
- CONFIRMED booking: its CO is excluded from recalculation (`$frozenCos`), read from the booking.
- Part without layout/costing/BOM: row `NEED_LAYOUT`; material not yet chosen: `NEED_ITEM` until PPIC Pick Item (the choice is stored in the booking, not in Master Product).
- Hold is only for bookings without a CO; CO bookings use Confirm.
- An engine failure on the CO trigger is silently logged (`report($e)`) and does not fail Confirm CO; environments without column `rm_booking.co_id` are skipped.

## Used by (relations)
- **source**: Customer Order, CPDEL, Costing (published), Quotation, Inquiry, ProDev (layout). **used_by**: Material Planning/Booking/Pre-booking screens (Production), `GateService` (PPIC WO gate), opening-screen warnings. **downstream**: PR/PO (Buy button, Purchasing), Pick List, Stock (booking reduces Available only when Confirmed or Held).

## Open items
ATTENTION-ENGINE-02 (Available figures differ between screens; in this file `v_rm_available` is read at lines 684 and 922, same as item 02), ATTENTION-ENGINE-03 (Release does not create a booking), ATTENTION-ENGINE-05 (Purchase does not create a PR yet). Status stays in `docs/ENGINE-ATTENTION.md`, not copied.

## Findings (recorded, not fixed)
1. The registry pre-booking step says "from the moment costing has calculated". FACT from code: the condition is `published_flag = 1` (`preDemands`, line 222). A costing that is only CALCULATED does not become a pre-booking.
2. The registry netting step says "Confirmed first, then Draft, then Forecast"; the code adds Quotation (tier 4) and Pre-booking (tier 5) after Forecast.
3. Public methods that exist but are not listed in the registry `methods`: `persistPre`, `preDemands`, `explodePre`, `hold`, `releaseHold`, `linesForCo`, `fingerprint`, `leadDays`, `standards`, `material`, `isWorkday`, `addWorkdays`, `subWorkdays`, `pickKey`, `forget` (static). All ten registered methods exist.
4. The registry `reads` does not mention `quo_quotation`, `iqp_costing`, `iqp_inquiry`, `iqp_tier`, `prd_demand_forecast`, `sys_holiday`, `sys_office_hour`.

## Tests
`tests/Feature/PreBookingTest.php` (9 tests: pre-booking from costing, one header, Pick Item, B1 from quotation and chain end, Hold, Hold rejection for CO bookings, CO takes over the header, stale booking, screen and route), `PreBookingFgAvailabilityTest.php`, `MrpBuyTest.php` (Buy button; not run this session), `WoAvailableTest.php`. Tests write to the real DB and clean up after themselves.
