# Concept card: Planning parameters, PPIC Settings hub, Rule Library, access flags

> Written 2026-10-17 (Manufacture group). "FACT" = seen first-hand in this session: mockup DB (`--env=mockup`; `sys_constant` and `sec_module` queried read only) and code with line numbers. "FROM DOCUMENT" = not checked.

- **Document type**: concept card. **Module/sub-module**: Manufacture > PPIC > PPIC Settings: All PPIC Settings (`PROD_PPIC_ALL`, `v2:ppic-settings`), Planning Parameters (`PROD_PPIC_PARAMS`, `v2:setup-constants`), Rule Library (`PROD_RULE_LIB`, `v2:prod-rule-library`).
- **Canonical name**: Planning parameters = the 17 rows of `tm2sys.sys_constant` with `constant_group = PPIC` (read through one door, `PpicSettings`) + the start values of `config/ppic.php`. PPIC Settings = a hub page that lists eight categories of masters with a status per line. Rule Library = master rows of `tm2prd.prd_scheduling_rule` (engine card `../planning/30_engine_scheduling-rules.md`).
- **Synonyms**: PPIC_* constants, Settings > Constants, "all PPIC settings", assumed start values.
- **Rule** (CLAUDE.md section 3): every value the application reads must have an input screen.

## Facts
| Item | Content | Evidence |
|---|---|---|
| The one door | `PpicSettings::number(key)`: the constant wins, `config/ppic.php` is the start value; cached per request. `KEYS` maps 17 config keys to constants. Engines must not read `config('ppic.*')` for these numbers. | FACT, `PpicSettings.php:20-51` |
| The 17 constants (value today) | `PPIC_LOCK_SHIFTS` 1, `PPIC_WEIGHT_URGENCY` 0.50, `PPIC_WEIGHT_SETUP` 0.35, `PPIC_WEIGHT_TIER` 0.15, `PPIC_SIMILAR_WINDOW_HOURS` 8, `PPIC_HORIZON_HOURS` 72, `PPIC_HORIZON_DAYS` 60, `PPIC_GROUP_DUE_HOURS` 48, `PPIC_GROUP_SLACK_HOURS` 8, `PPIC_COLOR_ORDER_FACTOR` 0.7, `PPIC_BOTTLENECK_LOAD_PCT` 90, `PPIC_DEFAULT_SHIFTS` 2, `PPIC_HOURS_PER_SHIFT` 8, `PPIC_BASE_SETUP_MIN` 15, `PPIC_LEARN_MIN_SAMPLES` 5, `PPIC_LEARN_MAX_STEP_PCT` 20, `PPIC_LEARN_BLEND` 0.3. All active, category PRODUCTION. | FACT, `sys_constant` query |
| Who uses each | Lock: `PlanConfirmService`; weights, similar window, horizon hours, group due/slack, wash factor, base setup: `ScheduleSolver` / `SetupTimeEngine` through `AutoPlanRunner::solver`; horizon days, default shifts and hours: `PlanLoader::calendar`; bottleneck percent: `Ppic::exceptions` and the Gantt; learning limits: `LearningEngine`. Each of the 17 has a reader. | FACT, grep `PpicSettings::number`, `weights`, `setupRules` |
| Not in constants | `config('ppic.day_start')` (07:00, env `PPIC_DAY_START`), wait_after (CETAK 120, VARNISH 45, UV 0, LAMINASI 0; overridden by `prod_wait_rule`), the setup start table (`ppic.setup.*`: spot colour 20/30, full wash 38/60, ink type 55/80, board 15/20 with gap 20, size 12/15, die 15, plate 10), Sunday off. | FACT, `config/ppic.php`, `WorkingCalendar.php:31-39` |
| Speed engine constants | 12 more constants in group `SPEED_ENGINE` (percentile 75, minimum sample 20, high sample 200, run window 5 to 960 minutes, efficiency 0.5/0.6, change 5 percent, lookback 365 days, excluded process words). Card `20_engine_speed-target.md`. | FACT, query |
| PPIC Settings hub | A page of eight categories (Machines and capacity, Processes and routing, Tooling and setup, Calendar, Rules and priority, Engine parameters, People, Materials) with a search box; each line is a link plus a status badge ADA / RAPIKAN / BELUM and a note. The list is typed in the view (audit of 10 Oct 2026), not generated. | FACT, `ppic-settings.blade.php` |
| Statuses on the hub today | RAPIKAN: Rule Library ("read by the old scheduling board only") and Master Crew ("not used as capacity"). BELUM: Preventive maintenance windows in the plan, Operator skills per process, Automatic booking confirmation. | FACT, `ppic-settings.blade.php` |
| Rule Library | `prd_scheduling_rule`: 0 rows; screen is a v2 grid over the v1 modal; activation goes through approval of v1. | FACT, `RuleLibraryGrid.php`; query |
| Access flags | Two rows in `tm2sys.sec_module` (not menu modules, parent PROD): `PPIC_SCHEDULING` (sort 41) and `PPIC_INVENTORY` (42). Starting grants by the migration: groups PROD_PLANNING (both), PROD_MGR and OPS_MGR (scheduling), MGR_INV (inventory control). Administrators hold both. | FACT, query; `database/migrations/2026_10_09_000060_ppic_access_flags.php` |

## Relations
- **source**: Settings > Constants (`tm2sys.sys_constant`), Security groups.
- **used_by**: all planning engines in `../planning/` (cards 23 to 28), controller guard `PpicController::schedulerOnly`.
- **flows**: constant -> `PpicSettings` (cache) -> engine; flag -> group -> user -> button allowed.
- **frozen_zone**: none.
- **also_named_by**: `15_card_changeover-and-wait-rules.md`, `17_card_learning-changeover.md`, `25_engine_setup-time.md`.

## Findings (recorded, not fixed)
1. **The shift-start run times ignore two of the constants** (`../planning/23_engine_auto-plan-runner.md` finding 1). FACT.
2. **The day start, the wait defaults and the setup start table have no input screen as constants** (the wait and changeover masters override them, but the day start has no screen at all). FACT.
3. **`PPIC_INVENTORY` is never checked in code** (`PpicAccess::canInventory` has no caller; grep `canInventory`); the flag gives no right today. FACT.
4. **The hub is hand-written**: its status lines can become wrong when a feature is built (for example the tooling note still says repeat minutes are used). It is a screen text, not data. FACT.
5. **The constants cache is per request** (`PpicSettings::$cache`); `LearningEngine::run` calls `forget`, other engines do not need it. A constant edited while a long queue worker runs is read only when the worker restarts. FACT (code; the worker case is NOT observed).
6. **Descriptions of the old plan note differ from values**: the note's weight list adds an age weight and "alpha 0.3"; the constants hold no age weight (`../planning/23_engine_auto-plan-runner.md` finding 5). FACT.

## Already exists? Searched where, result
`concept:find "constant"` was not needed: the constants are named in the cards above; `docs/ENGINE-ATTENTION.md` does not list them. Searched: `PpicSettings` is the only reader of the 17 keys (no direct `SysConstant::number('PPIC_...')` elsewhere: grep).
