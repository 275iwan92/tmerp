# Sub-module: PPIC (Manufacture > PPIC)

> Written 2026-10-17 (Manufacture group). This page only summarizes and links; table, column and engine facts are in the cards. FACT = seen first-hand in this session (mockup DB with `--env=mockup`, code, tests I ran). The menu was read from `sys_app_menu` (code `PROD_PPIC`). **Masters of machines, processes, process x machine pairs and employees are REAL data: this sub-module was only READ.**

- **Module**: Manufacture (`PROD`) > PPIC (`PROD_PPIC`). Menu rows (FACT, 13 active screens in 3 groups):
  - PPIC Settings (12): All PPIC Settings (`v2:ppic-settings`), Master Machine (`v2:prod-machine`), Machine Model Catalog (`v2:prod-machine-model`), Master Process (`v2:prod-process`), Master Routing Template (`v2:prod-routing`), BOP Rule Setting (`v2:prod-bop-rule`), Tooling Master (`v2:prod-tooling`), Setup and Changeover (`v2:prod-changeover`), Wait Rules (`v2:prod-wait-rule`), Rule Library (`v2:prod-rule-library`), Master Crew (`v2:prod-crew`), Planning Parameters (`v2:setup-constants`);
  - Support (1): Shift Schedule (`v2:hrm-roster`);
  - Master group: Master Card and Layout are inactive legacy items (Master Card is now born in ProDev).
- **Purpose**: the standing data that the planning engines read (capacity, capability, calendar, tooling, setup rules, parameters) and the engines that belong to it (speed targets, operator timer).
- **Frozen zone**: real masters are never given `data_origin = DUMMY` and machine specifications are never invented; changes to them are made by users only. `prod_bop_rule` belongs to ProDev (frozen).
- **Rules**: every value the application reads must have an input screen (CLAUDE.md section 7 item 5); A.7 engine suggests, officer decides; master changed only by the user.

## Relations
- **source**: HRM (employees, work centers, shift master, roster), Maintenance (machine status, downtime), Master Data (Holiday Calendar), ProDev (BOP rule, product, dieline), Settings > Constants.
- **used_by**: all planning engines (`../planning/`), Assign Crew, Production Output.
- **flags**: `PPIC_SCHEDULING` and `PPIC_INVENTORY` (rows of `sec_module`), see `16_card_planning-parameters.md`.

## Cards and engines in this sub-module
| Type | File | Content |
|---|---|---|
| Concept | `10_card_machine.md` | Master Machine, downtime, Machine Model Catalog |
| Concept | `11_card_process-and-routing.md` | process, capability pairs, routing template, precedence, BOP rule; 14 unread process flags |
| Concept | `12_card_shift-and-calendar.md` | the planner's working calendar versus shift master and roster |
| Concept | `13_card_crew-master.md` | Master Crew and work-center skills |
| Concept | `14_card_tooling.md` | plates and dies |
| Concept | `15_card_changeover-and-wait-rules.md` | changeover master and wait rules (master side) |
| Concept | `16_card_planning-parameters.md` | the 17 `PPIC_*` constants, PPIC Settings hub, Rule Library, access flags |
| ENGINE | `20_engine_speed-target.md` | speed target suggestions |
| ENGINE | `21_engine_operator-timer.md` | Play/Pause/Stop timers and Save result (screen is in Manufacture > Production) |
| ENGINE | not written here | Routing Template Engine (not connected to ProDev: `../../sales/90_findings_engine-group-c.md` finding 2) |

## Diagram
Not made.

## Existing User's Guide (UG)
`docs/guides/production/02_planning_scheduling.md` and `03_learning_timer_crew.md` (Indonesian). The TG does not repeat them.

## Cross-card findings (summary; details in the cards)
Fourteen process flags (bottleneck, parallel, QC, lot sizes, skill, cost basis and others) are stored and read by no code; the changeover and wait masters are empty (0 rows) and the changeover master has no effect on a plan; the shift master and the roster do not drive the planner's calendar; the Master Crew list has no employee link (217 of 217 empty); 26 speed suggestions exist with no screen to accept or reject them; the access flag `PPIC_INVENTORY` is never checked; both offset presses have no `max_colors_per_pass` and one of them has been in BREAKDOWN since 1 October. Summary and tests: `../90_findings_planning-ppic.md`.
