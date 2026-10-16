# ENGINE card: QcEscalationService (hourly notices for undecided Conditional / Quarantine batches)

> Written 2026-10-16. Source: `app/Services/Inventory/QcEscalationService.php` (36 lines), `app/Services/Inventory/QcNotifier.php` (35 lines), `app/Console/Commands/InvQcEscalate.php` (26 lines) and `routes/console.php:48-55` read in full. Rights and groups: concept card `18_card_qc-authority-and-notices.md`. The command was NOT run (it writes bell rows; `--dry-run` was not used). Verified: 2026-10-16.

- **Canonical name**: QC Escalation Service. **Class**: `App\Services\Inventory\QcEscalationService` (`run`). **Registry**: none (searched `EngineRegistry`: no key for it; the engine map lists `inv:qc-escalate` as a schedule without an engine, `docs/tg/01_engine-map.md`). **Module/sub-module**: Inventory > Batch & Quality.
- **Frozen status**: none named. It only sends notices; it changes no document.
- **Synonyms**: escalate, overdue notice, eskalasi keputusan mutu, `inv:qc-escalate`. **NOT the same as**: a decision (card `23_engine_qc-decision.md`).

## Trigger (scheduled)
SCHEDULED hourly: `Schedule::command('inv:qc-escalate')->hourly()->withoutOverlapping()`, only when table `inv_qc_decision` exists in `tm2inv` (a guard so databases not yet released skip it). FACT, `routes/console.php:52`. Manual: `php artisan inv:qc-escalate [--dry-run]` (`app/Console/Commands/InvQcEscalate.php:12`). Server time zone; needs `schedule:run` running.

## Input / Output
- **Input**: all batches with `qc_status` CONDITIONAL or QUARANTINE (not filtered by `activate`), oldest `qc_status_at` first; constants `INV_QC_ESCALATE_HOURS` (4) and `INV_QC_DEADLINE_HOURS` (8).
- **Output (writes, `tm2sys.sys_notifications`)**: bell rows. Stage ESCALATE -> to groups `QA_HEAD`, `OPS_MGR`, title "Quality decision escalated" (type warning), text "<batch> · <item code> (<status>): no quality decision yet. Decide before the deadline." Stage OVERDUE -> to `QA_HEAD`, `QA`, `OPS_MGR`, `PROD_PLANNING`, title "Quality decision overdue" (type error), text ends "A usage decision is required now."
- **Returns** (command): one line per batch past the limit (`batch_no`, state, hours, notices sent) and a total. `--dry-run` sends nothing.

## Flow
1. For each undecided batch compute the age (`QcDecisionService::age`); skip `OK`.
2. Choose the recipients of the stage and call `QcNotifier::toGroups`; each active member of those groups gets one row unless the same title + text already exists for them.
3. Report the rows.

## Edge cases
- Because the text carries no date, a batch that is escalated gets ONE notice per person at stage ESCALATE and ONE at OVERDUE, never repeated every hour. If the same batch later returns to Conditional, the same text is not sent again.
- Groups `QA_HEAD` (missing in `sec_group`) therefore receive nothing; only `OPS_MGR` members at ESCALATE and `QA`, `PROD_PLANNING`, `OPS_MGR` at OVERDUE (concept card 18, finding 1). Admins are never notified.
- The Conditional Stock screen shows the same state as a badge (On time / Escalate / Overdue) without any notice.
- The deadline is wall-clock hours, the constant description says "working hours".
- Stale comment: the header of `InvQcEscalate.php` (line 8) says "NOT scheduled yet ... add it to routes/console.php when the user agrees", but the schedule exists (`routes/console.php:52`, comment at `:48` says Jo approved the schedule on 2026-10-10). FACT.

## Tests (read, not run)
`QcEscalateTest::test_batches_past_the_limits_notify_the_right_groups_once_per_stage`.

## Used by
Bell popups of QA, Ops Manager, PPIC. Nothing reads the notices back.
