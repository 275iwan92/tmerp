# ENGINE card: CoOrderBuilder (Customer Order builder and CPDEL engine)

> Written 2026-10-16 (group A). Source: `app/Services/Sales/CoOrderBuilder.php` (299 lines) read in full, plus `CoDueDate.php`, `CoOrder.php`, `CoOrderItem.php`. Tests run: `CoDueDateTest` (2 passed, 4 asserts). Tables/columns: see the concept cards `10_card_customer-order.md` and `11_card_cpdel.md` (not repeated here). No confidential section: this engine does not read HPP/margin (A.8-A.9), only the tier `tolerance_pct`.

- **Canonical name**: CoOrderBuilder. **Class**: `App\Services\Sales\CoOrderBuilder` (all methods static). **Registry**: key `cpdel`, name "CPDEL Engine (Customer Plan Delivery)", mode `auto`, group Sales Order, registered methods only `submit` and `confirm`. **Module/sub-module**: Sales > Order.
- **Frozen status**: the CO and CPDEL document flow is frozen. This engine is not an advisor; it enforces rules (A.1-A.2, A.4, A.10, A.11, A.15). The Confirm decision stays with humans/approval.

## Trigger (no background / schedule)
Searched in `routes/console.php` (no CO schedule), `app/Observers` (only 2, unrelated to CO), Jobs, and all callers. FACT: called only by screen actions, seeders, and migrations.
| Method | Trigger | File:line |
|---|---|---|
| `findOrCreateFromQuotation` | Quotation WIN button (v2) | `app/Support/V2/Data/Quotation.php:273`; route `routes/v2.php:442-445` |
| | Pick a Quotation on the Customer Order screen (v2) | `app/Support/V2/Data/CustomerOrder.php:74` |
| | v1 screens | `tmerp_unused/co_list_v1_2026-10-20.blade.php:149`, `tmerp_unused/quotation_entry_v1_2026-10-20.blade.php:502` |
| | Dummy seeder/migration | `database/seeders/UatQuotationInquirySeeder.php:127`, `database/migrations/2026_09_21_000007_seed_inquiry_to_co_win_chain.php:139` |
| `createInstant` | Create an INSTANT CO from a customer choice (v2) and Duplicate of a rejected/cancelled CO | `CustomerOrder.php:79`, `CustomerOrder.php:228` |
| | v1, PPIC dummy, migration | `tmerp_unused/co_list_v1_2026-10-20.blade.php:160`, `app/Console/Commands/PpicSeedDummy.php:77`, migration `…000007…:202` |
| `addProductLine` | Pick a Master Product on an INSTANT CO (v1, called from a component that v2 also uses) | `app/Services/Sales/CoEntryService.php:88`, `PpicSeedDummy.php:78`, migration `…:210` |
| `submit` | CO Confirm button (v2) | `CustomerOrder.php:179` |
| `confirm` | Last approval passes | `CustomerOrder.php:210` (inside `decide`) |
| | v1 path without approval, and seeders | `tmerp_unused/co_entry_v1_2026-10-20.blade.php:414`, `tmerp_unused/co_list_v1_2026-10-20.blade.php:180`, migration `…:158,215` |
| `cancel` / `cancelBlocker` | CO Cancel button (v2) | `CustomerOrder.php:254` |

## Input / Output
- **Input**: `QuoQuotation` PUBLISHED + items + inquiries; `CustCustomer` (tax profile, terms, default address); `ProdProduct` (INSTANT); `IqpCostingPriceTier.tolerance_pct`; `sales_person`; flow config `CO`; constant `CO_LEAD_WORKDAYS`; product ACC Digital (via `CoDueDate`).
- **Output (writes to DB)**: `co_order`, `co_order_item`, `co_order_delivery` (only `confirm`), `iqp_inquiry` (`co_id`, `sub_state=CONVERTED`), `tm2mst.prod_product` and parts (via `ProductMasterSync`, run at WIN), `flow_log` (via `FlowEngineService::submit`), schedule status on `cancel`.
- **Returns only**: `submit` and `cancel` return an error message or null; `confirm` returns a bool without a message.

## Flow (text twin; diagram not made yet)
**A. WIN: `findOrCreateFromQuotation`** (`CoOrderBuilder.php:27-113`)
1. Not `PUBLISHED` -> null. A CO with that `quotation_id` already exists -> return the existing one (1 Quotation = 1 CO).
2. Inside a `tm2sdi` transaction: create the header `INTEGRATED`, `DRAFT`, number `nextCoNo`, `is_ppn` from the inquiry (`tax_scheme = PPN`) or the customer profile (`PPN_11`), discount from the first inquiry of type `INQUIRY_TIER`, sales from the `pic_sales` text (no match = NULL).
3. Each Quotation item becomes a CO line: `FREEFORM` -> `SERVICE`, otherwise `PRODUCT`; qty, unit, price, discount are copied; tolerance is calculated from the tier (formula below); `recalcSubtotal`.
4. All related inquiries: `co_id` is filled and `sub_state=CONVERTED` (bulk update, does not fire model events).
5. Each inquiry becomes a Master Product (`ProductMasterSync::fromInquiry`), the CO line `product_id` is filled.

**B. Direct CO: `createInstant` + `addProductLine`** (`:130-181`): header `INSTANT`, `quotation_id` NULL, terms/currency/PPN/address from the customer. Each line from a Master Product: description `nama (kode)`, price `default_selling_price` (0 if empty), unit `uom_sales` (default Pcs), `qty_min/max` from the master.

**C. Confirm: `submit` then `confirm`** (`:190-250`)
1. `submit`: if DRAFT, recalculate the automatic line due dates (`CoDueDate::refresh`).
2. Reject if not DRAFT or `isConfirmable()` is false (every PRODUCT line must have a due date, INSTANT must also have a product).
3. Reject if a product has more than one qty line from the quotation (A.15).
4. Reject if flow `CO` is not configured. Otherwise `doc_status=SUBMITTED` and `FlowEngineService::submit('CO', id, 'SUBMITTED', net_receivable)`.
5. Last approval -> `confirm`: only from DRAFT or SUBMITTED and `isConfirmable()`; set `CONFIRMED`; for each PRODUCT line that has no schedule yet, create 1 `FULL` row, `ship_qty` = qty, `ship_date` = `due_date`, `PLANNED`. Lines that already have a schedule are not touched.

**D. Cancel: `cancelBlocker`/`cancel`** (`:253-287`): only CONFIRMED; reject if a non-cancelled WO or non-cancelled DO line points to its schedule (the message includes the number); if it passes: `CANCELLED`, `stat_cancel=1`, reason, all schedules `CANCELLED`. The Quotation stays WIN and the inquiry stays CONVERTED; reordering = INSTANT CO (code comment line 273).

## Formula (one place; only as-is from the code)
- Tolerance (lines 68-85): `tol = iqp_costing_price_tier.tolerance_pct` of the item's `tier_id` row (0 if no tier); if `tol > 0`: `qty_min = round(qty x (1 - tol/100), 2)`, `qty_max = round(qty x (1 + tol/100), 2)`; otherwise NULL. Worked example from mockup data (CO2600022): qty 20.000, tol 5 -> 19.000 / 21.000; 13 of 13 INTEGRATED lines match (query today).
- Line subtotal (`CoOrderItem.php:106-111`): `qty x unit_price x (1 - diskon/100)`, rounded to 2 decimals; if `qty_minim`: `unit_price x (1 - diskon/100)`.
- Due date (`CoDueDate.php:29-40`): `addWorkdays(dasar, CO_LEAD_WORKDAYS)`; INSTANT base = `co_date`, otherwise the latest DIGITAL ACC `acc_at` (NULL if none). Data example: CO2600059 `co_date` 16-Okt-2026 + 14 working days (Sunday off) = 02-Nov-2026. Test `CoDueDateTest`: Friday 02-Okt-2026 + 14 working days = Monday 19-Okt-2026.
- Document total: `CoOrder::totals` (`CoOrder.php:183-221`), explained in the CO card.

## Edge cases
- A Quotation whose CO is already CANCELLED or REJECTED still returns that old CO (lines 33-37), it does not create a new one.
- The transaction is only on connection `tm2sdi`; `ProductMasterSync` opens its own transaction on `tm2mst` (`ProductMasterSync.php`, `DB::connection('tm2mst')->transaction`). A failure midway does not roll back both together (FROM CODE, not tested).
- Approval with `total net_receivable` as `amount_snapshot`; the figure is read at submit and is not updated if the CO is changed afterwards (a DRAFT can no longer be changed after SUBMITTED, `CustomerOrder::save`, `abort_unless` DRAFT, line 94).
- `confirm` silently returns false (no message) when the conditions are not met; the v1 caller (archived) ignored it (`tmerp_unused/co_entry_v1_2026-10-20.blade.php:414`).
- `confirm` does not recheck the one-qty rule (A.15) and does not check approval; only `submit` checks A.15.

## Used by
Customer Order screens v2 and v1, Quotation (WIN), dummy seeders, Planning (reads CONFIRMED COs), Delivery Order (reads the schedules created by this engine), Customer 360.

## Open items
- No `ATTENTION-ENGINE` number in `docs/ENGINE-ATTENTION.md` mentions CO/CPDEL (searched: cpdel, customer order, co_order, delivery; zero results).
- The registry (`EngineRegistry.php:386-414`) registers only `submit` and `confirm`; `findOrCreateFromQuotation`, `createInstant`, `addProductLine`, `cancel` are not registered, and the `writes` list does not include `iqp_inquiry`, `prod_product`, `flow_log`. FACT.
- Conflict A.10 (PHYSICAL proof) versus code (ACC Digital): CO card, finding 10.
- Trigger A.11/A.15 is guarded only in `submit`/`decide` (v2): CO card, finding 3.

## Tests
No test for `CoOrderBuilder` (grep `tests/`: zero). Tests touching related parts: `tests/Feature/CoDueDateTest.php` (passed 2/2), `CoListExpandRowTest` (archived to `tmerp_unused/test_CoListExpandRowTest_v1_2026-10-20.php`), `QtyMinimLumpSumTest.php`. Proposal (needs Jo's approval, not done): one WIN -> submit -> confirm test that cleans up its own data.
Update 2026-10-18: `CoListExpandRowTest` no longer relies on the old dummy CO; it builds its own CO and external cost through `tests/Support/WoFixture.php` and removes them in tearDown (1 passed on `--env=mockup`).

> Re-checked 2026-10-17 against `EngineRegistry.php` and `engines.json`: key `paper-price` (Procurement) was added after the lines cited here; nothing in this card changed.

> Re-checked 2026-10-17 against `EngineRegistry.php` and `engines.json`: the `paper-price` entry text now names the Tax Rate screen (`fin_tax_rate`) for PPN; nothing in this card changed.

> Re-checked 2026-10-17: `CoOrder::totals()` now reads the PPN rate with `TaxRate::ppn(co_date)` (table `fin_tax_rate`, screen Master Data > Tax Rate) instead of today's constant; the rate for current documents is unchanged (11), an older CO uses the rate of its own date.

> Re-checked 2026-10-17 against `EngineRegistry.php` and `engines.json`: key `single-face-price` (Procurement) was added after the lines cited here; nothing in this card changed.

> Re-checked 2026-10-17 against `EngineRegistry.php` and `engines.json`: keys `duplex-price` and `corrugated-sheet-price` (Procurement) were added after the lines cited here; nothing in this card changed.

- Re-checked 2026-10-18 after the duplicate-module cleanup (v1 Scheduling Board, Rule Library and Master Crew removed): the registry entry that uses `CoOrderBuilder` (key `cpdel`) is now `EngineRegistry.php:386-414`; `engines.json` lost the 3 removed engines only; nothing else in this card changed.
- Re-checked 2026-10-20 after the Quotation and Customer Order entry rules moved from the v1 components to services (rule D.25, T10): `routes/web.php` replaced the v1 routes `/quotation/new`, `/quotation/{quotation}/edit`, `/quotation/{quotation}/print`, `/co/{order}/edit` and `/co/{order}/print` by redirects on the same lines (no line added or removed), `routes/v2.php` changed only two comment lines, `app/Support/V2/Screens.php` changed the description text of the `quotation` and `customer-order` rows on the same lines, and `app/Support/V2/Data/Quotation.php` and `CustomerOrder.php` now call `QuotationEntryService` and `CoEntryService` instead of the v1 Livewire components (the `component()` helper is gone; cards that cite lines of those two files were remapped from the diff). The v1 pages `pages::quotation.entry`, `pages::co.entry` and the two v1 prints are archived in `tmerp_unused/`. Every other cited line number is unchanged and this card's content is still true.
- Re-checked 2026-10-20 after Imposition and Inquiry Nota moved off v1 (rule D.25, T10 stage 2): `routes/web.php` replaced the v1 routes `/inquiry/imposition`, `/inquiry/{inquiry}/imposition` and `/inquiry/{inquiry}/nota` by redirects (to ProDev v2 `/v2/s/prodev-new`, `/v2/prodev/open/{id}` and the v2 print `/v2/print/inquiry-note/{id}`) on the same lines (no line added or removed), `app/Support/V2/Print/PrintDocs.php` registered the `inquiry-note` print type on the existing `inquiry` line, and `app/Support/V2/Screens.php` changed the description text of the `nota-inquiry` row on the same line. The v1 pages `pages::inquiry.imposition`, `imposition-list` and `nota` are archived in `tmerp_unused/`; `ImpositionEngine` is unchanged. Every other cited line number is unchanged and this card's content is still true.
- Re-checked 2026-10-20 after the Developer menu got the admin-only group "Comparison · Special Forms" (rule D.25): `routes/v2.php` loads the new `routes/v2_compare.php` on the existing `v2_quality.php` line (no line added or removed), and `app/Support/V2/AppMenu.php` gained the group on one new line after the Engines group (the Developer menu lines 75-79 cited by cards are unchanged). The new route `GET /v2/compare/{slug}` only renders static sample data for admins and does not touch what this card describes. Every cited line number is unchanged and this card's content is still true.
- Re-checked 2026-10-21 after ProCost moved off v1 (rule D.25, T10 stage 3): `routes/web.php` replaced the v1 routes `/inquiry/costing`, `/inquiry/{inquiry}/costing`, `/costing/instant` and `/costing/instant/{costing}` by redirects (to `/v2/s/sales-procost`, `/v2/s/procost?inquiry={id}`, `/v2/s/sales-procost-instant`, `/v2/s/procost-instant?id={id}`) on the same lines and extended the `require` line of the v2 route files with `routes/v2_procost.php` (no line added or removed); `routes/v2.php` changed the comment and redirect target of `procost-instant.new` on the same lines; `app/Support/V2/Screens.php` and `app/Support/Menu/MenuRoutes.php` changed row text and two menu targets on the same lines; `CostingAdvisor.php` changed one REMARK line. The v1 pages `pages::inquiry.costing`, `costing-list`, `costing-instant` and `costing-instant-list` are archived in `tmerp_unused/`; the engines are unchanged. Every other cited line number is unchanged and this card's content is still true.
