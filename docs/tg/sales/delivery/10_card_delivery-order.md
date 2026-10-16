# Concept card: Delivery Order (DO) and Delivery Note (Surat Jalan)

> Written 2026-10-16 (group A). FACT = mockup DB (`ddt*` through `tm2*`, `--env=mockup`) and code with line numbers, seen this session. Tests run with `--env=mockup`: `DispatchLifecycleTest` (3 passed, 19 assertions), `DispatchNoMergeRuleTest` (2 passed, 10 assertions); the table row counts are the same before and after.

- **Document type**: concept card. **Module/sub-module**: Sales > Delivery > Delivery Order and Delivery Notes (`SALES_DO` `v2:delivery-order`, `SALES_DN` `v2:delivery-notes`, `SALES_DN_SUBCON` `v2:subcon-delivery-note`; FACT `sys_app_menu`).
- **Canonical name**: Delivery Order = `tm2sdi.do_delivery` (header) + `do_line` (lines). **Delivery Note / Surat Jalan** = the SAME document (one data object, two views/prints): a DO with status CONFIRMED or SHIPPED, without price.
- **Synonyms**: DO, Surat Jalan, SJ, DN, dispatch, `do_no` (`DO/26/00001`), Subcon Surat Jalan (DO of type SUBCON).
- **NOT the same as**: CPDEL (schedule, does not deduct remaining qty), Proof of Delivery (`do_delivery_pod`, card `11_card_proof-of-delivery.md`), Goods Receipt.

## Facts
| Item | Content | Evidence |
|---|---|---|
| Header | `do_delivery`: 3 rows. PK `id_delivery`, `do_no` UNIQUE and NULL while Draft, `do_type` (CUSTOMER / SUBCON), `is_ppn`, `do_date`, `customer_id` or `supplier_id` (subcon), `pr_id`, `ref_part_id`, `subcon_process`, address columns `ship_*`, `scheduled_ship_date`, `actual_ship_date`, `status`, `price_change_flag`, `price_change_approval_status`, `notes`. | FACT, `show columns` |
| Lines | `do_line`: 0 rows in the dummy. Columns: `co_order_id`, `co_order_item_id`, `co_delivery_id` (FK RESTRICT to CO/schedule), `item_description`, `qty_do`, `qty_received`, `receive_diff_reason`, `receive_note`, `qty_unit`, `unit_price`, `subtotal`, `price_overridden`, `original_unit_price`, `qty_invoiced`, `status` (PLANNED/SHIPPED/CANCELLED), `split_from_line_id`. | FACT, `show columns`; `referential_constraints` |
| Status | DRAFT -> CONFIRMED -> SHIPPED; CANCELLED only from DRAFT or CONFIRMED, reason required. Number `DO/yy/NNNNN` is born at Confirm (max+1 per year). Data distribution: DRAFT 1, CONFIRMED 2. There are no columns `stat_confirm/_cancel/_close`. | FACT, `DispatchService.php:190-255,258-290,292-313,318-330`; `DispatchLifecycleTest` |
| Line source | Lines are pulled from the CO delivery schedule through view `v_co_delivery_outstanding`: remaining = `ship_qty` - sum of `qty_do` of PLANNED/SHIPPED lines, schedule not SHIPPED. One schedule = one DO line (1:1, never merged). | FACT, view definition; `DispatchService.php:17-109`; `DispatchNoMergeRuleTest` |
| Pull conditions | Same customer as the DO, CO with status CONFIRMED, remaining > 0, PPN status equal to the first line (no mixing). `qty_do = min(sisa, ship_qty)` (sisa = remaining). On the v2 screen one more condition: the schedule is already Posted to Production (A.3). | FACT, `DispatchService.php:54-84`; `DeliveryOrder.php:66-72,115-132` |
| Qty | Raising/lowering a line qty is limited to `sisa + qty_do baris itu sendiri` (remaining + the line's own qty_do) (> 0). Confirm re-checks with a row lock (`lockForUpdate`) and rejects if the source CO is no longer CONFIRMED. | FACT, `DispatchService.php:111-145,190-250` |
| Price | `unit_price` is copied from the CO when pulled (snapshot). Changed manually: `original_unit_price` is filled once, `price_overridden=1`, header `price_change_flag=1`. Subtotal = `qty_do x unit_price` (Minimum Qty: line value = price). DO print gives 403 if the user does not hold the price right; Surat Jalan never shows price or totals. | FACT, `DispatchService.php:86-102,147-174`; `DeliveryOrderPrint.php:49`; `DeliveryNotePrint.php:11-14`; test `test_delivery_order_print_shows_price_but_surat_jalan_never_does` passed |
| Shipped | A single action over all PLANNED lines; fills `actual_ship_date`; the CPDEL schedule becomes SHIPPED only if the sum of SHIPPED `qty_do` >= that schedule's `ship_qty`. | FACT, `DispatchService.php:258-290`; lifecycle test |
| Cancel | The DO lines become CANCELLED; the schedule's remaining recovers by itself through the view. The reason is written to `do_delivery.notes` (overwriting the old note). | FACT, `DispatchService.php:292-313`; test `test_cancel_from_confirmed_restores_qty_available…` |
| Subcon | DO `SUBCON` (shipped to a vendor, not a customer): `supplier_id`, `subcon_process`, `pr_id`, no `co_order_id` on lines; its own screen; not part of the DO/DN/POD lists. | FACT, `DeliveryOrder.php:36-39`; `DispatchService.php:231-234`; `SubconDelivery.php` |
| Screen | DO = `DeliveryOrder` (v1 component rule `pages::dispatch.do.entry` → `DispatchService`); Delivery Notes = list of CONFIRMED/SHIPPED DOs; save/pull/confirm/ship/cancel actions at route `v2.do.save`. | FACT, `DeliveryOrder.php:10-31`; `DeliveryNoteListGrid.php:30-33`; `routes/v2.php:855-891` |
| Downstream users | Customer Invoice reads `qty_received`/`qty_invoiced` (invoice basis = received qty, A.21). | FACT, `DeliveryPromiseService.php:124-152` (fills `qty_received`); A.21 FROM DOCUMENT for the invoicing rule |

## Relations
- **source**: CPDEL (`co_order_delivery`), CO (`co_order`, `co_order_item`: price, description), customer/supplier master.
- **used_by**: printed Surat Jalan (QR "online document & proof of receipt"), POD (`do_delivery_pod.delivery_id`), Customer Invoice (`qty_received`), promise-accuracy KPI, Customer 360 (shipment list), `QcTraceService`.
- **flows**: CPDEL (Posted) -> pull into DRAFT DO -> Confirm (number is born, Surat Jalan ready to print) -> Shipped (CPDEL schedule SHIPPED) -> POD records the arrival and received qty.
- **frozen_zone**: DO/DN document flow (the rule freezes all flows). Read: `docs/claude_vital_flows.md` (Delivery menu).

## Findings (recorded, not fixed)
1. **DO without lines**: 2 of 2 CONFIRMED DOs in the dummy have no `do_line` (DO/26/00001 customer, DO/26/00002 subcon). `DispatchService::confirm` rejects a DO without lines, so these DOs were created outside the service (dummy). FACT (query).
2. **Data origin marker**: `DO/26/00001` has `data_origin=LIVE` although its note says "[UAT-CHAIN] dummy rantai Inquiry-Quotation-CO-Produksi" and it lives in the dummy database. FACT.
3. **Unused columns**: `price_change_approval_status` appears only in `$fillable` and is read by one screen (`delivery-order.blade.php:70`), never written. `split_from_line_id` has zero uses in `app/`, `resources/`, `routes/`. `ref_part_id` is written in one place (`ProcurementBridge.php:224`). FACT (grep).
4. **`posted_at` gate only in v2**: `DispatchService::pullLines` does not check Post to Production; only `DeliveryOrder::pull` (v2) does. The v1 path `pages::dispatch.do.entry` bypasses it. FROM CODE (CPDEL card finding 5).
5. **Cancel overwrites the note**: `cancel` writes the reason to `do_delivery.notes` (line 307), the previous note is lost. There is no `cancel_reason` column as in other documents. FACT.
6. **Status naming**: DO uses a full-word `status`, not `stat_confirm/_cancel/_close` (naming rule CLAUDE.md item 5); see the Status card. FACT.
7. **Price override without approval**: the header records `price_change_flag` but there is no approval flow (approval column empty, finding 3). Rule A.9 only limits who sees the price; whether an override must be approved is NOT checked in the documents.
8. **Vehicle/driver/weight/bundle** have no columns yet; printed empty to be filled by hand (comment `DeliveryNotePrint.php:11-14`). FACT.

## Already exists? Searched where
Searched: `do_*`, `v_co_delivery_outstanding`, `DispatchService`, `DeliveryOrder`, `DoDelivery/DoLine`, menu Sales > Delivery, engine registry (no DO-specific key; `DispatchService` is not registered). Result: the concept ALREADY EXISTS; no new table needed. The old document `dispatch_tier*.md` is mentioned in code comments (FROM DOCUMENT, not read; tier4 is written as already archived).
