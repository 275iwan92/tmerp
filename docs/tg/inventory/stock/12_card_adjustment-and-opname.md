# Concept card: Stock Adjustment, Physical Count (Opname) and Adjustment Approval

> Written 2026-10-16 (group Inventory/Stock). FACT = mockup DB (`ddtinv`, `ddtmst`) and code with line numbers, seen this session. No test was run (no DB writes allowed in this task).
> Verified: 2026-10-16

- **Document type**: concept card. **Module/sub-module**: Inventory > Stock > (Raw Material | Finished Goods | Semi Finished Goods) > "Stock Adjustment & Physical Count" and "Adjustment Approval" (menu `INV_RM_ITEM4`/`INV_RM_ITEM5`, `INV_FG_ITEM4`/`INV_FG_ITEM5`, `INV_SFG_ITEM4`/`INV_SFG_ITEM5`; FACT `sys_app_menu`; all three Approval items open the same screen `v2:approval-stock`).
- **Canonical name**: a Stock Document (card `10_card_stock-document.md`) with `doc_cat` AD (single correction) or OP (scheduled physical count / opname). Flow engine module codes: AD and OP.
- **Synonyms**: Penyesuaian Stok, Stock Opname, hitung fisik, Physical Count, adjustment, loss and gain, Approval Center (stock).
- **NOT the same as**: Reversal (cancel of a wrong Stock In/Out, card `22_engine_stock-reversal.md`; "Adjustment is only for physical differences"); the warehouse Count Task (`CountTaskService`, Warehouse side, which creates OP documents with `ref_doc_type` COUNT_TASK, not written here); Stock Transfer approval (module TF).

## Facts
| Item | Content | Evidence |
|---|---|---|
| Header | `doc_cat` AD or OP chosen at creation and then locked; `warehouse_id`, `reason_code` required; `doc_type` written as -1. Opname adds officer (`opn_officer`), start and stop time (`opn_start`, `opn_stop`), witness (`opn_witness`) and `full_scope`; all four are required for OP. | FACT, `stock-adjustment.blade.php:330-371` |
| Reason list | Fixed in the Blade: COUNT_ERROR, DAMAGED, LOST, FOUND, EXPIRED, OTHER. The constant `INV_REASON_ADJ` (different list) is not read by any code. | FACT, `stock-adjustment.blade.php:620-621`; grep |
| Line | Per item + bin: `qty_system` = `qty_eb` of the last card of that item in that bin at the moment the bin is chosen; `unit_price` = current average price (item average when `INV_AVG_SCOPE`=ITEM); the user types `qty_physical`; saved `qty` = physical - system (signed). | FACT, `stock-adjustment.blade.php:165-196,310-327` |
| Full scope | Button pulls every item with a non-zero balance in the chosen warehouse and replaces the lines already typed. | FACT, `stock-adjustment.blade.php:207-230` |
| Posting | Zero-difference lines are skipped; the signed difference goes to `qty_adj` of the card (doc_type AD); no value is written on the card row, the chain values it at the current average. Negative balance is NOT refused for AD/OP (the one documented exception, rule A.24). | FACT, `StockPostingService.php:115-117,143,175` |
| Status path | DRAFT / REVISION_NEEDED (editable) -> SUBMITTED -> (approve) POSTED, or (reject) REJECTED. Submit: saves, sets SUBMITTED, then `FlowEngineService::submit`; with no active flow config the document stays SUBMITTED and the screen says to contact Admin. | FACT, `stock-adjustment.blade.php:48,386-420` |
| Approval route | Flow configs 5 (AD) and 6 (OP): ONE active step each, any amount: AD approver position 64 Operational Manager, OP approver position 95 Warehouse Admin; final status POSTED, reject status REJECTED. The older two-level steps (Manager Operasional, Direksi) are `activate`=0 since the 2026-09-30 "Setting Approval" migration. | FACT, `flow_config`, `flow_config_step`, `org_position` |
| Approve | Approver (own position or acting PLT) sees the document in the stock Approval Center or in the form; Approve at the last step calls `postDocument`; Reject needs a reason of at least 10 characters. The Center also offers Hold (status unchanged). The amount stored with the decision is the SUM of the ABSOLUTE differences x price. | FACT, `stock-adjustment.blade.php:435-483`; `ApprovalCenter.php:213-236`; `FlowEngineService.php:174-280` |
| Approval Center | Lists AD and OP together (`ApprovalCenter::SCREENS['stock']`); the document is found by `doc_id` alone by trying RM, then FG, then SFG headers. | FACT, `ApprovalCenter.php:42-45,71-82` |
| Data (mockup) | RM: AD 6 (1 SUBMITTED, 1 REJECTED, 3 DRAFT, 1 POSTED), OP 27 (9 DRAFT, 2 SUBMITTED, 16 POSTED of which 10 from count tasks). No FG or SFG adjustment documents. | FACT, group-by query |

## Relations
- **source**: item master, bins, the last stock card row (system quantity), flow engine config (`flow_config`, `flow_config_step`, `flow_position_acting`), org positions.
- **used_by**: posting engine (card `20_engine_stock-posting.md`), reports Stock Opname and Stock Loss & Gain (card `18_card_stock-reports.md`), Approval Center, dashboard widgets.
- **flows**: Draft -> Submit -> Approval Center / form -> Approve -> `postDocument` -> `qty_adj` card row. A wrong posted adjustment is corrected by a NEW adjustment (reversal refuses AD/OP/BB).
- **frozen_zone**: none declared; approval routes are Setting Approval data (Setup), not code.

## Findings (recorded, not fixed)
1. **Rejected adjustment is a dead end**: reject sets `flow_status` REJECTED (flow config `reject_status`), but the form treats only DRAFT and REVISION_NEEDED as editable and shows no button otherwise, so a REJECTED document cannot be corrected, re-submitted or cancelled from the screen. The text "returned to the creator for revision" fits only REVISION_NEEDED (used by the inactive steps). Data: 1 RM adjustment is REJECTED. FACT (code + config + data).
2. **Approve logs APPROVED before posting**: if posting is then refused (closed inventory period; any posting error), the log already says APPROVED, the document stays SUBMITTED and no longer appears in the approver's queue (the queue needs a last log of SUBMITTED/HOLD). FROM CODE (`approveDoc` writes the log, then calls `postDocument`), not reproduced.
3. **Stale system quantity**: `qty_system` is copied when the bin is chosen and never refreshed. Movements between that moment and approval make the posted difference wrong against the real count; the screen has no warning. FROM CODE.
4. **Line reason not enforced**: the placeholder says "wajib" (required) for lines with a difference, but the validation loop has an empty body and the note is not stored. FACT (`stock-adjustment.blade.php:347-354`).
5. **No separation of duties**: nothing stops the creator from approving. OP is approved by the Warehouse Admin position, the same kind of person who prepares counts. FROM CODE (`FlowEngineService::approve` checks position only); not tested.
6. **`doc_id` collision risk in the Approval Center**: `flow_log` keys a document by `module_code` + `doc_id`; AD/OP documents of RM, FG and SFG live in three tables with separate counters, and the Center resolves the id class by class (RM first). Two classes with the same id in SUBMITTED would show the wrong document. No collision in the current data (only RM adjustments exist). FROM CODE.
7. **Allowed to go negative**: AD/OP can drive a position below zero without a warning (documented exception, rule A.24). One historic negative card exists (card `11_card_stock-card-and-balance.md`).
8. **Unused limits**: constants `INV_ADJ_LIMIT_L1`, `INV_ADJ_LIMIT_L2` are not read; routing by amount now lives only in Setting Approval, where the higher steps are inactive. FACT (grep, config).

## Already exists? Searched where
Searched: menu `INV_*_ITEM4/5`, `stock-adjustment.blade.php`, `ApprovalCenter`, `FlowEngineService`, flow config/steps, `CountTaskService` (callers of `postDocument`), reports `StockOpnameReport`, `StockLossGainReport`. The concept ALREADY EXISTS. Design intent (FROM DOCUMENT, `docs/globaluserrules.md` A.24 and A.26): a posted document is never edited; wrong input is cancelled by a reversal with the reason "Kesalahan transaksi no. X"; Adjustment only for physical differences.

<!-- johiwan-only -->
## Amounts (Costing/Direksi only)
Inactive higher steps: AD/OP step 111/114 (second level) applies 5,000,000 to 25,000,000, step 112/115 from 25,000,000; constants `INV_ADJ_LIMIT_L1` = 5,000,000 and `INV_ADJ_LIMIT_L2` = 25,000,000 (mockup `sys_constant`). Before 2026-09-30 an adjustment above 5,000,000 needed the Manajer Operasional and above 25,000,000 the Direksi; today every amount needs the single active step.
