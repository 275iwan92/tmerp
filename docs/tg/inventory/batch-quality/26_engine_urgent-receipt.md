# ENGINE card: UrgentReceiptService (URGENT mark, FSC approval, posting blockers)

> Written 2026-10-16. Source: `app/Services/Pur/UrgentReceiptService.php` (142 lines), `app/Services/Pur/PvPostingService.php:44-130` and `routes/v2_quality.php:99-119` read. Concept card: `11_card_conditional-stock-and-urgent.md` (not repeated). No test was run. Verified: 2026-10-16.

- **Canonical name**: Urgent Receipt Service. **Class**: `App\Services\Pur\UrgentReceiptService` (static). **Registry**: key `urgent-receipt`, mode `flow`, group Procurement. **Module/sub-module**: Purchasing > Goods Receipt, with Inventory > Batch & Quality.
- **Frozen status**: the Goods Receipt posting flow is frozen; the URGENT branch inside it was added by the v2.01 rule (D16) and is not to be altered without Jo.
- **Synonyms**: URGENT, dock-to-line, mark urgent, approve urgent. **NOT the same as**: an urgent Purchase Requisition or an urgent WO priority.

## Trigger (no background / schedule)
| Method | Trigger | File:line |
|---|---|---|
| `mark`, `unmark`, `approve`, `setCertification` | Buttons URGENT, Clear, Approve, Save on the Goods Receipt URGENT card (route `v2.iq.urgent`, module PROC) | `routes/v2_quality.php:100-119`; `resources/views/v2/screens/_receipt-urgent.blade.php:36-43` |
| `postBlockers` | Posting a PO receipt | `app/Services/Pur/PvPostingService.php:61-62` |
| `prepareForPost` | Start of posting | `app/Services/Pur/PvPostingService.php:90` |
| `needsApproval` | Receipt rows for the card | `app/Support/V2/Data/BatchQuality.php:70` |

## Input / Output
- **Input**: a `pv_line`, the acting user (login or user object), reason, work order id, approval note, certification, food grade.
- **Output (writes, `tm2pur.pv_line`)**: `is_urgent`, `urgent_by`, `urgent_at`, `urgent_reason`, `urgent_wo_id`, `urgent_approved_by/at`, `urgent_approval_note`, `i_certification`, `i_food_grade`, and `c_qty_accepted`/`c_qty_rejected`/`c_line_value` in `prepareForPost`.
- **Returns**: the line; `InvalidArgumentException` with an English message on refusal (shown at the card).

## Flow
1. **mark**: marker must be allowed (`QcAuthority::canMarkUrgent`); receipt status DRAFT or QC_PENDING; line is a stock item; reason >= 5 characters; the work order exists. A new mark or a changed WO clears an earlier approval.
2. **setCertification**: certification must be NONE / FSC / PEFC / OTHER; changes are allowed only while the receipt is open.
3. **approve**: approver `QA_HEAD` or Ops Manager+; the line must be URGENT; stores who/when/note.
4. **postBlockers**: reason and WO present; the marker is still allowed to mark; if `needsApproval` then an approval exists. A non-URGENT line with quality PENDING stays blocked in `PvPostingService::blockers`.
5. **prepareForPost**: URGENT line with PENDING quality and no accepted qty gets accepted qty = stock qty, rejected 0, value = qty x unit price.
6. After posting, `ReceiptBatchService` makes the batch `CONDITIONAL`.

## Formula (as-is)
`needsApproval` = constant `INV_URGENT_FSC_APPROVAL` >= 1 AND (line certification FSC or PEFC, OR food grade, OR an existing batch of the same item + lot with those attributes). Example: FSC board, constant 1 -> needs approval; the same board with the constant 0 -> no approval.

## Edge cases
- `mark` repeated by someone else replaces `urgent_by`; posting then re-checks that person.
- Un-marking after approval wipes the approval.
- `qc_qty_status` (quantity QC) must still be DONE; URGENT skips only the QUALITY QC.
- The card is for receipts of type PO ("penerimaan / SPB jenis PO"); Subcon and Customer receipts have no URGENT.

## Tests (read, not run)
`QcUrgentReceiptTest` (normal line still held by pending quality; marking needs an authorised person, a reason, a WO; URGENT line posts with Conditional batch; FSC needs approval; changing the WO withdraws approval; a normal posted line gets a Released batch and dock decision), `QcScreensTest::test_urgent_card_on_the_goods_receipt_and_the_mark_route`.

## Used by
Goods Receipt screen, posting service, URGENT Usage report, Conditional Stock page.
