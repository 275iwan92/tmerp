# Concept card: Conditional Stock and the URGENT receipt

> Written 2026-10-16 (group Inventory, Batch & Quality). FACT = mockup DB and code with line numbers, seen this session; no test was run. Verified: 2026-10-16.

- **Document type**: concept card. **Module/sub-module**: Inventory > Batch & Quality > Conditional Stock (`INV_BQ_COND`, `v2:conditional-stock`, form) and Report > URGENT Usage (`INV_BQ_URG`, `v2:qc-urgent-usage`); the URGENT mark itself lives in Purchasing > Goods Receipt (`purchase-receive`, part `_receipt-urgent.blade.php`).
- **Canonical name**: Conditional Stock = batches whose `inv_batch.qc_status` is `CONDITIONAL` or `QUARANTINE` (waiting for a quality decision). URGENT receipt = a `pv_line` marked `is_urgent`, posted before the quality QC result and taken to production while sampling runs.
- **Synonyms**: dock-to-line, URGENT, conditional use, usage decision (the late decision), Quarantine, hours waiting, Escalate, Overdue.
- **NOT the same as**: a normal receipt (waits for the dock sampling result and is born `RELEASED`), Quarantine (not usable) versus Conditional (usable but marked), Hold of a work order (`inv_qc_hold`, card `14_card_supplier-claim-and-trace.md`).

## Facts
| Item | Content | Evidence |
|---|---|---|
| Two paths | Normal line: blocked from posting while `qc_quality_status` is PENDING; batch born `RELEASED` and a `SAMPLING_DOCK`/`RELEASED` decision is written (new batch only). URGENT line: not blocked by PENDING quality; batch born `CONDITIONAL`; no decision row at posting. | FACT, `app/Services/Pur/PvPostingService.php:61-67`; `app/Services/Inventory/ReceiptBatchService.php:38-60` |
| Who marks URGENT | PPIC (`PROD_PLANNING`), `PROD_SPV`, `PROD_MGR`, Ops Manager and above (`OPS_MGR`, `DIREKSI`, management level >= 4, admin). Needs a reason of 5+ characters, an existing work order, a stock item line, and a receipt still DRAFT or QC_PENDING. A new mark, or a changed reason/WO, wipes an earlier approval. | FACT, `app/Services/Pur/UrgentReceiptService.php:40-62`; `app/Services/Inventory/QcAuthority.php:15` |
| FSC / food grade guard | If constant `INV_URGENT_FSC_APPROVAL` >= 1 (value 1), a line with certification FSC or PEFC, food grade, or an existing batch (same item + lot) with those attributes needs approval by the head of QC (`QA_HEAD`) or Ops Manager and above before posting. | FACT, `UrgentReceiptService.php:24-37,77-91,105-123`; `sys_constant` row |
| Posting blockers | URGENT line: reason + WO required, marker must still be allowed to mark, approval when needed. Posting an URGENT line with `c_qty_accepted` 0 sets accepted = full stock qty. | FACT, `UrgentReceiptService.php:105-133` |
| What is listed | Conditional Stock grid: batches `CONDITIONAL`/`QUARANTINE` with `activate` = 1, oldest `qc_status_at` first. Columns: batch, item, lot, supplier, status, deadline badge (On time / Escalate / Overdue), hours waiting, URGENT badge + reason, certification. | FACT, `app/Support/V2/Grid/Inv/ConditionalStockListGrid.php:43-57` |
| Age and deadline | Hours = wall-clock minutes since `qc_status_at` / 60. `ESCALATE` at constant `INV_QC_ESCALATE_HOURS` (4), `OVERDUE` at `INV_QC_DEADLINE_HOURS` (8). | FACT, `app/Services/Inventory/QcDecisionService.php:82-92`; `sys_constant` rows |
| Batch page | Opening a row shows: status, since, hours waiting, received qty (sum of `pv_line.c_qty_accepted` of the batch), certification, expiry, URGENT line (who + why), the sampling plan computed for this lot, a Quality Decision card (QA only), decision history, "Where This Batch Was Used", held work orders, supplier claim. | FACT, `resources/views/v2/screens/conditional-stock.blade.php:37-105`; `app/Support/V2/Data/BatchQuality.php:36-56` |
| URGENT Usage report | One row per URGENT `pv_line`: receipt, supplier, item, qty, requested by, WO, reason, approved by, batch, batch status, test result and decided by (the LATEST decision row of the batch). Stat boxes All / Awaiting / Released / Rejected. | FACT, `app/Support/V2/Grid/Inv/UrgentUsageReport.php:41-59,87-91` |
| Pick rule | A Conditional batch may be picked unless the job is class A (batch food grade or FSC/PEFC) or constant `INV_PICK_CONDITIONAL_ALLOWED` is 0. | FACT, `app/Services/Inventory/PickAllocator.php:87-96` |
| Escalation | Hourly command `inv:qc-escalate` notifies groups (card `24_engine_qc-escalation.md`). | FACT, `routes/console.php:52` |
| Dummy data | 0 URGENT lines, 0 Conditional/Quarantine batches, 1 decision row (a `SAMPLING_DOCK`/`RELEASED`). The screens are proven by tests, not by dummy rows. | FACT (queries) |

## Relations
- **source**: posted PO receipt (`pv_receipt`, `pv_line`), batch (`inv_batch`), sampling plan (`inv_qc_sampling_plan`, `inv_qc_intensity_rule`, `inv_qc_risk_class`).
- **used_by**: QA/QA head deciding stock, PPIC and production (they use the stock before the decision), Ops Manager (escalation), pick list allocation (Warehouse), URGENT Usage report.
- **flows**: mark URGENT (Goods Receipt) -> approve if FSC/food grade -> post -> batch `CONDITIONAL` -> sampling/decision (`QcDecisionService`) -> `RELEASED` or `REJECTED`/`BLOCKED` (hold + claim). Parallel: escalation notices at 4 h and 8 h.
- **frozen_zone**: Goods Receipt posting flow (do not change without Jo).

## Findings (recorded, not fixed)
1. **Groups `QA_HEAD` and `PROD_SPV` do not exist in `sec_group`** (mockup and tm2: only `QA`, `PROD_PLANNING`, `PROD_MGR`, `OPS_MGR`, `DIREKSI`, `MGR_INV`, `MGR_PUR` found). Effect today: URGENT FSC/food-grade approval and the first escalation notice reach only Ops Manager and above; `PROD_SPV` cannot mark URGENT. The code comment says the groups are recognised once Jo creates them. FACT (query).
2. **Deadline hours are wall-clock, the constant text says "working hours".** `sys_constant.description` of `INV_QC_DEADLINE_HOURS` reads "Working hours allowed..."; `QcDecisionService::age` counts calendar hours (no office-hour calendar). A Conditional batch received Friday afternoon is Overdue on Saturday morning. FACT.
3. **The scope document says a Usage Decision is also required when the job ends before the decision (FROM DOCUMENT, `inventorywarehouse_v201_scope.md` 7.4 item 4).** No code checks the work order's end; only the timer exists. FACT (grep).
4. **The URGENT Usage column "Sampling Result" shows the LATEST decision of the batch, whatever its type.** `UrgentUsageReport.php:47` loads decisions in ascending id and `keyBy('batch_id')` keeps the last one, so a later Usage Decision, First Article or Deviation replaces the dock sampling result under the same column title. FACT.
5. **A dummy with 0 URGENT rows means the URGENT screens have never shown real data in UAT.** FACT.
6. **Conditional batches are not visible as a physical state**: no zone move, no label (Putaway/Warehouse may place them anywhere, see `10_card_batch-master.md` finding 7). FACT.

## Already exists? Searched where
Searched `is_urgent`, `urgent_*`, `UrgentReceiptService`, `ConditionalStockListGrid`, menu `INV_BQ_COND`/`INV_BQ_URG`, EngineRegistry key `urgent-receipt`. Result: ALREADY EXISTS. Rulebook A.26 / D16 (FROM DOCUMENT, `docs/globaluserrules.md` lines 261, 269) agree with the code on who may mark URGENT and the FSC approval.
