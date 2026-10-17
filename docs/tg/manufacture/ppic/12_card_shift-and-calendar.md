# Concept card: Shift, roster and working calendar for planning

> Written 2026-10-17 (Manufacture group). "FACT" = seen first-hand in this session: mockup DB (`--env=mockup`; counts = `count(*)` today) and code with line numbers. "FROM DOCUMENT" = not checked. Employees and the shift roster are REAL master data: only read here. The roster is owned by HRM; this card only says what the production planner takes from it (very little).

- **Document type**: concept card. **Module/sub-module**: Manufacture > PPIC > Support > Shift Schedule (`PROD_SHIFT_ROSTER`, `v2:hrm-roster`); Master Shift (`md-shift`) and Holiday Calendar (`holiday-calendar`) are Master Data screens listed in PPIC Settings > Calendar.
- **Canonical name**: Working calendar for planning = (a) per machine `shifts_per_day` x `hours_per_shift` (`tm2mst.prod_machine`), (b) the day start `config('ppic.day_start')` (07:00), (c) holidays `tm2mst.sys_holiday`, (d) Sunday off. The shift master `emp_shift` / `emp_shift_detail` and the roster `emp_shift_roster` are a different thing used by HR, Assign Crew and the old backward scheduler.
- **Synonyms**: kalender kerja, shift, roster, Shift Schedule, libur, working day, work minute.
- **NOT the same as**: office hours (`sys_office_hour`, used by CPDEL slots, `../../sales/order/11_card_cpdel.md`).

## Facts
| Item | Content | Evidence |
|---|---|---|
| Planner calendar | `WorkingCalendar`: a machine works `shifts x hours` minutes a day starting at the day start, Monday to Saturday, minus holidays; maximum 1440 minutes a day; the planning horizon of the loader's holidays is today to today + `PPIC_HORIZON_DAYS` (60). Defaults for a machine without values: `PPIC_DEFAULT_SHIFTS` (2) and `PPIC_HOURS_PER_SHIFT` (8). | FACT, `WorkingCalendar.php`, `PlanLoader.php:91-100` |
| Machine values | 34 active machines have 1 shift x 8.0 hours, 11 have 2 shifts x 7.8 hours (07:00 to 22:36 in the calendar). | FACT, query |
| Holidays | `sys_holiday`: 51 active rows from 2026-01-01 to 2027-12-26 (28 of them from 2026-12-24 onward); types NATIONAL, COLLECTIVE_LEAVE, COMPANY (`WorkCalendar::TYPES`). Screen: Master Data > Holiday Calendar. | FACT, query; `app/Support/V2/Data/WorkCalendar.php` |
| Shift master | `emp_shift`: 8 rows (`SH1`, `SH2`, `SEC3`, `TDP1`, `TDP2`, `IND1`, `IND2`, `REG`) with `department_id`; `emp_shift_detail`: 25 rows (`day_of_week`, `start_time`, `break_out`, `break_in`, `end_time`) only for SH1, SH2, SEC3 and REG. | FACT, query |
| Roster | `emp_shift_roster`: 628 rows from 2026-09-07 to 2026-09-19 (`employee_id`, `shift_id`, `roster_date`, `machine_id`, `work_center_id`, `schedule_id`, `batch_id`, `is_overtime`); only 24 rows name a machine, all on one machine; `emp_shift_roster_batch`: 0 rows. One placement = one employee, one shift, one full week. | FACT, query; screen REMARK |
| Who reads shift and roster | `MachineCalendarService` (backward scheduling at WO creation and the v1 board; shift window from roster + detail when the machine has roster history, else 08:00 to 20:00), `MachineCrewService` (production shifts for crews), HR screens. NOT the v2 planner. | FACT, `MachineCalendarService.php:60-150`, `MachineCrewService.php:35-60`, grep |
| Tests | `tests/Feature/ShiftRosterTest.php` (4 passed on `tm2*`, 14 assertions); `ShiftRosterRotateTest.php` and `ShiftRosterRoutesTest.php` (6 each) were not run. | FACT, test run |

## Relations
- **source**: HRM (Master Shift, roster), Master Data (Holiday Calendar), machine master (`10_card_machine.md`).
- **used_by**: `../planning/24_engine_schedule-solver.md` (work minutes), `../planning/23_engine_auto-plan-runner.md` (calendar load), `../planning/29_engine_pdr.md` (old calendar), `../planning/19_card_assign-crew.md`.
- **flows**: machine shift values + holidays -> work minutes -> slot times. Roster -> crew names (display) and HR attendance, not capacity.
- **frozen_zone**: none; HRM owns the roster.

## Findings (recorded, not fixed)
1. **The planner's calendar ignores the shift master and the roster.** A machine with a real roster of 08:00 to 20:00 (the old calendar) and one with 07:00 start (planner) coexist for the same machine. The PPIC Settings screen lists Master Shift, Holiday Calendar, Office Hours and Shift Schedule as "Exists" under Calendar; only the holiday table is read by the planner. FACT (code vs screen).
2. **The day start is a config value (`PPIC_DAY_START` env, default 07:00), not a constant with a screen**; `PPIC_DAY_START` is not among the `PPIC_*` constants of Settings > Constants (`PpicSettings::KEYS` has 17 keys, none for the day start). Against rule "every value the application reads must have an input screen". FACT (code, constants query).
3. **Machine shifts: 7.8 hours per shift on 11 machines** make the working day 15.6 hours; the shift master's `REG` is "Reguler 07:30-16:30". The two numbers are not tied. FACT (query).
4. **The roster has dates only in September 2026** (13 days) and almost no machine link, so the old calendar falls back to 08:00 to 20:00 for most machines. FACT (query).
5. **Holidays outside the horizon are not loaded**: a plan longer than 60 days from today would treat later holidays as working days. FACT (code `PlanLoader.php:96`).
6. **Saturday is a full working day; Sunday never** (`$sundayOff = true` default, no constant). FACT.

## Already exists? Searched where, result
`concept:find "shift"`: the engine map and the Sales findings only name shifts in passing; no card existed. Searched: three calendars exist (planner, old backward service, office hours of Sales); no shared class.
