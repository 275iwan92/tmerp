# Concept card: CPDEL (Customer Plan Delivery)

> Written 2026-10-16 (group A). FACT = mockup DB (`ddt*` via `tm2*`, `--env=mockup`) and code with line numbers, seen this session. FROM DOCUMENT = not checked.

- **Document type**: concept card. **Module/sub-module**: Sales > Order > Customer Plan Delivery (`SALES_CPDEL`, `v2:cpdel`, FACT `sys_app_menu`).
- **Canonical name**: CPDEL = delivery schedule row `tm2sdi.co_order_delivery` (one row = one planned delivery of one CO line), together with its negotiation log `co_delivery_negotiation`.
- **Synonyms**: Plan Delivery (old name), delivery schedule, delivery slot, `ship_date`, "janji ke customer".
- **NOT the same as**: Delivery Order (`do_delivery`, the physical delivery document that deducts remaining qty), Work Order. CPDEL is purely a schedule; it does not touch stock (screen REMARK and `Cpdel.php:30-31`).

## Facts
| Item | Content | Evidence |
|---|---|---|
| Schedule table | `co_order_delivery`: 29 rows, PK `id_delivery`. Columns: `order_id`, `item_id`, `shipment_type`, `ship_date`, `ship_time`, `ship_qty`, `original_date`, `ready_at`, `ready_source`, `ready_by`, `escalated_at`, `status` (PLANNED/SHIPPED/CANCELLED), `split_from_id`, `posted_at`, `posted_by`. FK: `order_id` and `item_id` CASCADE, `split_from_id` SET NULL. | FACT, `show columns`; `referential_constraints` |
| Negotiation log | `co_delivery_negotiation` (0 rows in dummy): `delivery_id` (FK CASCADE), `event`, `actor_role`, `old/new_date`, `old/new_time`, `old/new_qty`, `ready_at`, `reason_code`, `channel`, `customer_contact`, `decision`, `note`. | FACT, `show columns` |
| Delay reasons | `co_delay_reason`: 9 active rows (MATERIAL_LATE, MACHINE_DOWN, CAPACITY, REWORK, ARTWORK_LATE, PLANNING, TRANSPORT, CUSTOMER_REQUEST, OTHER), each reason points to a department. | FACT, query |
| Born | When the last CO approval passes (`CoOrderBuilder::confirm`): each PRODUCT line that has no schedule yet gets 1 row `shipment_type=FULL`, `ship_qty` = full qty, `ship_date` = line `due_date`. Existing manual schedules are not overwritten. Details: engine card `20_engine_co-order-builder.md`. | FACT, `CoOrderBuilder.php:231-246` |
| Data distribution | Of 29 rows (all CO CONFIRMED): PLANNED 29, none past date, 25 already `posted_at`, 0 have `ready_at`, 0 have `ship_time`, 0 have `split_from_id`. The sum of `ship_qty` per CO line equals the line qty (0 exceptions). | FACT, query today |
| Screen | Grid with one row per schedule with TWO promises: customer slot (`ship_date`+`ship_time`) and production promise (`ready_at`). Only COs with `doc_status=CONFIRMED` are shown. | FACT, `Cpdel.php:40-43,45-74` |
| Split / move date | `qty` empty: only `ship_date` is changed. `qty` filled: must be `0 < qty < ship_qty` of the source row; a new PLANNED row is created (`shipment_type=PARTIAL`, `split_from_id`) on the new date, the remainder stays on the source row. Total qty stays the same. Rejected if the row is not PLANNED. | FACT, `resources/views/pages/co/delivery-plan.blade.php:111-155` (v1 component called by `Cpdel::split`, `Cpdel.php:113-125`) |
| Merge | Only PLANNED rows of the same CO and product, minimum 2; the winner = earliest date, qty summed, the other rows are DELETED. | FACT, `delivery-plan.blade.php:175-207`; `Cpdel.php:127-137` |
| Post to Production | Per-row action: creates a WO via `WoGenerationService::generateFromDelivery` (unless a non-cancelled WO already exists), then `posted_at/posted_by` are filled and the PPIC plan is rebuilt (Draft). After posting, date and qty are locked in CPDEL; only the slot time may change. | FACT, `Cpdel.php:76-103,113-137`; `CpdelPromiseService.php:167-169` |
| Gate to DO | A new schedule may be pulled into a Delivery Order when `posted_at` is filled (A.3). The guard is in the v2 screen (`DeliveryOrder.php:66-72,115-132`), NOT in `DispatchService::pullLines`. | FACT, code |
| Two promises | Customer slot: date+time, managed by Sales. Production promise `ready_at`: managed by PPIC. A slot is valid if it is a Telasih working day (`sys_office_hour`, `sys_holiday`) and within the customer receiving hours (`cust_receiving_hour`, 7 rows). Status/color rules: engine card `21_engine_cpdel-promise.md`. | FACT, `CpdelPromiseService.php:106-128` |
| Constants | `CPDEL_DUE_SOON_DAYS` = 2 (yellow when the slot is H-2 working days or less); `QUICK_JOB_MINUTES` = 30 (reference only, text on the Post button). | FACT, `sys_constant` |
| Closed | A row becomes SHIPPED only by `DispatchService::markShipped` (the Shipped action on a DO), when the sum of SHIPPED `do_line` >= `ship_qty`. A CONFIRMED DO alone does not change it; it holds the remainder through view `v_co_delivery_outstanding`. | FACT, `DispatchService.php:258-290`; view definition |
| Price | The CPDEL screen and tables contain no price/HPP (tables have no price column; `Cpdel::rows` has no price). | FACT, `show columns`; `Cpdel.php:46-73` |

## Relations
- **source**: CO (`co_order`, `co_order_item.due_date`) via the CO engine; constants; working calendar; customer receiving hours.
- **used_by**: Delivery Order (`do_line.co_delivery_id`, FK RESTRICT), WO (`prd_wo.source_delivery_id`), PPIC Planning (`PlanEvents::replan`), POD (promise = earliest CPDEL slot, `DeliveryPromiseService::promise`), on-time delivery KPI.
- **flows**: CO CONFIRMED -> FULL row -> (Split/Merge/Move date, negotiation) -> Post to Production -> pulled into DO -> DO Shipped -> row SHIPPED -> POD.
- **frozen_zone**: the CPDEL document flow (the rule that freezes all flows).

## Findings (recorded, not fixed)
1. **Escalated cannot be decided after the slot has passed**: `CpdelPromiseService::state` checks `LATE` (line 44) before `ESCALATED` (line 45), while the Operational Manager decision button appears only when `state === 'ESCALATED'` (`Cpdel.php:201`). A row rejected by the customer and then past the slot date becomes LATE, without an Ops decision (Sales can still reschedule). FROM CODE, not tested on screen.
2. **Merge deletes history**: `confirmMerge` deletes the losing row (`delivery-plan.blade.php:201`); the `co_delivery_negotiation` FK is CASCADE so the promise log of that row is lost too, although the screen rule says: "janji awal tidak pernah berubah". `CpdelPromiseService::merge` only records the winning row. FACT (code+FK).
3. **Core logic in a v1 component**: Split/Merge are in `pages::co.delivery-plan`, called from v2 (`Cpdel::component`). Its error messages are literal Indonesian text without `t()` (e.g. lines 116, 178). FACT.
4. **The text "SHIPPED otomatis begitu DO terbit"** in the v1 comment and banner (`delivery-plan.blade.php:22,234`) does not match the code: SHIPPED only happens at `markShipped`. FACT.
5. **The `posted_at` gate exists only in the v2 screen**: `DispatchService::pullLines` (v1 path `pages::dispatch.do.entry`) does not check it (`DispatchService.php:22-109`). FROM CODE.
6. **Slot quantity is not guarded**: there is no check that the sum of `ship_qty` per CO line = qty after the line qty is changed (only today's data matches).
7. **No dummy data for the negotiation path**: `co_delivery_negotiation` 0 rows, `ready_at` 0 rows, `ship_time` 0 rows; the PPIC/Sales/Ops path has no example in the dummy. FACT.
8. Dummy `shipment_type=DELIVER` (16 rows) is outside the screen choices FULL/PARTIAL (see CO card, finding 12). FACT.
9. The CO2600021 row that is CANCELLED-but-CONFIRMED is still shown in CPDEL (CO card, finding 1). FACT.

## Already exists? Searched where, result
Searched: `co_order_delivery`, `co_delivery_negotiation`, `CpdelPromiseService`, `Cpdel`, `v_co_delivery_outstanding`, routes `v2.cpdel.*`, the Sales > Order menu, the engine registry (`cpdel`, `cpdel-promise`). Result: ALREADY EXISTS; no new column needed. The old document `docs/md_for_systems/SALES_QUO_ORDER, CPDEL.md` exists (FROM DOCUMENT, not read in this session).
