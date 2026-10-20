# ENGINE card: CpdelPromiseService (the two CPDEL promises)

> Written 2026-10-16 (group A). Source: `app/Services/Sales/CpdelPromiseService.php` (254 lines) read in full, `app/Support/V2/Data/Cpdel.php`, route `routes/v2.php:1608-1650`. Tests run: `CpdelDueColourTest` (2 passed, 15 asserts). Tables/columns: see the concept card `11_card_cpdel.md`. No confidential section (no price/HPP/margin).

- **Canonical name**: CpdelPromiseService (engine "CPDEL Two Promises"). **Class**: `App\Services\Sales\CpdelPromiseService` (final, static methods). **Registry**: key `cpdel-promise`, mode `flow`, group Sales Order. **Module/sub-module**: Sales > Order > Customer Plan Delivery.
- **Frozen status**: the CPDEL flow is frozen. This engine records human agreements; it does not decide the schedule (A.7).

## Trigger (no background / schedule)
Searched in `routes/console.php` (none), observers, jobs, and all callers. FACT: only from the CPDEL screen.
| Method | Trigger | File:line |
|---|---|---|
| `state`, `gapDays`, `due` | Screen renders the grid and form | `Cpdel.php:56,67,72` (grid), `Cpdel.php:156,163` (form) |
| `slotProblems` | Row form, shows slot problems | `Cpdel.php:185` |
| `commitReady` | POST `v2.cpdel.ready` (PPIC) | `routes/v2.php:1614-1615` |
| `reschedule` | POST `v2.cpdel.reschedule` (Sales) | `routes/v2.php:1620-1621` |
| `customerRejected` | POST `v2.cpdel.reject` (Sales) | `routes/v2.php:1626-1627` |
| `opsDecision` | POST `v2.cpdel.decide` (Ops Manager) | `routes/v2.php:1632-1633` |
| `merge` | POST `v2.cpdel.merge` (Sales) | `routes/v2.php:1638-1641` |
| `canPlan/canSell/canDecide` | Route gates and button display | `routes/v2.php:1614,1620,1626,1632,1638`; `Cpdel.php:167,186,201` |
| `STATES` | Grid badge colors | `app/Support/V2/Grid/Sales/CpdelListGrid.php:65` |

## Input / Output
- **Input**: one `CoOrderDelivery`; form fields (date, time, reason, channel, contact, note, split qty, decision); the logged-in actor; `sys_office_hour`, `sys_holiday`, `cust_receiving_hour` (via `DeliveryPromiseService::receivingHours`), `co_delay_reason`, constant `CPDEL_DUE_SOON_DAYS`.
- **Output (writes to DB)**: `co_order_delivery` (`ready_at/_source/_by`, `ship_time`, `original_date`, `escalated_at`, and new/deleted rows via `Cpdel::split/merge`), `co_delivery_negotiation` (one log row per action).
- **Display only**: `state`, due color (`due`), day gap (`gapDays`), list of slot problems.

## Flow (text twin; diagram not made yet)
The row status is calculated (not stored) by `state` (lines 38-50), evaluation order:
1. `status = SHIPPED` -> SHIPPED.
2. `ship_date` present and earlier than today -> LATE.
3. `escalated_at` filled -> ESCALATED.
4. `ready_at` empty -> WAITING_PPIC.
5. `ready_at` greater than the slot -> NEED_NEGOTIATION.
6. Otherwise -> ALIGNED.

Actions (all reject if the row is not PLANNED, `assertOpen` lines 242-247):
1. **PPIC `commitReady`**: `ready_date` + `ready_time` required; if later than the slot, `reason_code` is required. Writes `ready_at`, `ready_source=MANUAL`, `ready_by`; log `READY_COMMITTED`.
2. **Sales `reschedule`**: date, time, channel (WhatsApp/Phone call/Email/Meeting), customer contact required; the slot must pass `slotProblems`; reason required except the first time the time is agreed (date unchanged, no split, `ship_time` still empty). If the row already has `posted_at`, date and qty are locked (time only). `original_date` is filled once and never changes again. If the date changes or on split: `Cpdel::split`. `escalated_at` is cleared. Log `SLOT_TIME_SET`/`RESCHEDULED`/`SPLIT` (split = two logs).
3. **Sales `customerRejected`**: note required; `escalated_at = now()`; log `CUSTOMER_REJECTED`.
4. **Ops Manager `opsDecision`**: decision (Overtime, Re-prioritize production, Subcontract, Make customer a new offer) + instruction required; `escalated_at` is cleared; `ready_at` is cleared except for decision NEW_OFFER (PPIC must refill it); log `OPS_DECISION`.
5. **Sales `merge`**: `Cpdel::merge` then log `MERGED` on the row that remains.

Rights (lines 84-98): `canPlan` = admin or module PROD `can_input`; `canSell` = admin or module SALES `can_input`; `canDecide` = admin or `maxMgmtLevel() >= 4`.

## Formula (one place; as-is from the code)
- `slot` (lines 33-36): `ship_date` + `ship_time`, without a time = `23:59:00`.
- `gapDays` (lines 79-82): `ship_date.startOfDay` to `ready_at.startOfDay` in days, signed; positive = production later than the promise.
- `due` (lines 55-76), in WORKING DAYS (`WorkCalendar`): SHIPPED -> muted; no `ship_date` -> LATER; slot < today -> OVERDUE ("Late n days", n = `workdaysBetween`, minimum 1); slot = today -> TODAY; otherwise `n = max(1, workdaysBetween(hari ini, slot))`, `n <= soon` -> SOON (yellow), further -> LATER. `week` = 1 if the slot is at most today + 6 calendar days.
- `slotProblems` (lines 106-128): empty = valid. Holiday date (`SysHoliday`), office closed or time outside office hours, customer does not receive or time outside receiving hours (with break gap).
- Worked example from the test run (`CpdelDueColourTest`, today = Friday 02-Okt-2026, Saturday working, Sunday off, soon = 2): slot 29-Sep -> OVERDUE (red, text contains 3 days); slot 02-Okt -> TODAY (orange); 03-Okt and 05-Okt (Monday) -> SOON; 06-Okt -> LATER, but SOON if soon = 3; slot 15-Okt -> `week` 0; SHIPPED -> muted. `workdaysBetween(02-Okt, 05-Okt) = 2` and the public holiday 06-Okt is not counted.

## Edge cases
- The server does not check the source status for `customerRejected`, `opsDecision`, and `commitReady`: all PLANNED rows are accepted even though the screen only offers their buttons at certain statuses (`Cpdel.php:199-202`). FROM CODE (lines 203-224), not tested.
- `reschedule` does not check that the new date >= today; only `slotProblems` (holiday/time).
- `ESCALATED` loses to `LATE` (line 44 before 45): an escalated row whose slot has passed shows LATE and the Ops decision button does not appear (`Cpdel.php:201`). See CPDEL card finding 1.
- Default PPIC form time 07:30, default slot time = customer opening time or 08:00 (`Cpdel.php:189,195`); only initial form values.

## Used by
CPDEL screen (grid + form `x-mp::sales.promise-book`), `CpdelListGrid`. The delivery promise-keeping KPI is separate in `DeliveryPromiseService` (card not written yet; see the Delivery sub-module).

## Open items
No `ATTENTION-ENGINE` number for CPDEL (see `docs/ENGINE-ATTENTION.md`, searched 'cpdel'). CPDEL card findings 1, 2, 5 are relevant.

## Tests
`tests/Feature/CpdelDueColourTest.php` (2 tests: working day count, row color; passed). Registered in the registry. No tests for `state`, `slotProblems`, `commitReady`, `reschedule`, `customerRejected`, `opsDecision`, `merge` (grep `tests/`: zero).
- Re-checked 2026-10-20 after the Quotation and Customer Order entry rules moved from the v1 components to services (rule D.25, T10): `routes/web.php` replaced the v1 routes `/quotation/new`, `/quotation/{quotation}/edit`, `/quotation/{quotation}/print`, `/co/{order}/edit` and `/co/{order}/print` by redirects on the same lines (no line added or removed), `routes/v2.php` changed only two comment lines, `app/Support/V2/Screens.php` changed the description text of the `quotation` and `customer-order` rows on the same lines, and `app/Support/V2/Data/Quotation.php` and `CustomerOrder.php` now call `QuotationEntryService` and `CoEntryService` instead of the v1 Livewire components (the `component()` helper is gone; cards that cite lines of those two files were remapped from the diff). The v1 pages `pages::quotation.entry`, `pages::co.entry` and the two v1 prints are archived in `tmerp_unused/`. Every other cited line number is unchanged and this card's content is still true.
