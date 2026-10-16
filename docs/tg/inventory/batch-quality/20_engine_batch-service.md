# ENGINE card: BatchService (the one door for batches)

> Written 2026-10-16. Source: `app/Services/Inventory/BatchService.php` (110 lines) and `app/Services/Inventory/ReceiptBatchService.php` (61 lines) read in full. Tables/columns and findings: concept card `10_card_batch-master.md` (not repeated). No test was run. Verified: 2026-10-16.

- **Canonical name**: Batch Service. **Class**: `App\Services\Inventory\BatchService` (static methods). **Registry**: key `batch`, mode `flow`, group Inventory. **Module/sub-module**: Inventory > Batch & Quality.
- **Frozen status**: none named. This engine enforces rules and decides nothing about quality (A.7); the decision is `QcDecisionService`.
- **Synonyms**: batch master, ensure batch, lot number rule. **NOT the same as**: `ReceiptBatchService` (the caller that makes batches at receipt posting, described below), `BatchBackfillService` (card `21_engine_batch-backfill.md`).

## Trigger (no background / schedule)
None in `routes/console.php`. FACT, callers (grep `BatchService::`):
| Method | Trigger | File:line |
|---|---|---|
| `create` | New batch saved on the Batch form | `app/Support/V2/Grid/Inv/BatchLookup.php:223`; route `routes/v2_quality.php:51-70` |
| `create` / `findByLot` | PO receipt posted: `PvPostingService::post` -> `ReceiptBatchService::forPostedReceipt` | `app/Services/Pur/PvPostingService.php:125`; `app/Services/Inventory/ReceiptBatchService.php:19-45` |
| `create` / `findByLot` | `inv:backfill-batch` | `app/Services/Inventory/BatchBackfillService.php:38-42` |
| `setStatus` | Every QC decision | `app/Services/Inventory/QcDecisionService.php:45` |
| `findByLot`, `needsExpiry`, `itemByCode` | Batch form validation | `BatchLookup.php:193-217` |
| `findByLot` | Urgent receipt FSC check | `app/Services/Pur/UrgentReceiptService.php:35` (inside `needsApproval`) |
| `allocatable` | Pick allocator, putaway planner | `app/Services/Inventory/PickAllocator.php:89`; `app/Services/Inventory/PutawayPlanner.php:33` |

## Input / Output
- **Input**: stock class `RM`/`SFG`/`FG`, item id, supplier lot (may be empty), attribute array (`part_id`, `item_code`, `item_name`, `supplier_id`, `production_date`, `expiry_date`, `retest_date`, `qc_status`, `certification`, `food_grade`, `grain_direction`, `coa_path`, `notes`), login.
- **Output (writes, all in `tm2inv`)**: `inv_batch` only (insert in `create`, update of `qc_status`, `qc_status_by`, `qc_status_at`, `updated_by` in `setStatus`). `ReceiptBatchService` also writes `pv_line.i_batch_id` (`tm2pur`), `rm_stock_d.batch_id` and `rm_stockcard.batch_id` (only rows still NULL) and, for a new non-URGENT batch, one `SAMPLING_DOCK`/`RELEASED` decision.
- **Does NOT write**: stock quantities, values or journals.

## Flow
1. **Lot key**: `normalizeLot` = upper-case + trim; empty = constant `INV_NO_LOT_BATCH_CODE` (`NO-LOT`). `findByLot` compares `UPPER(TRIM(IFNULL(supplier_lot, 'NO-LOT')))` on class + item.
2. **`ensure`** = `findByLot` or `create`; attributes are used only when creating.
3. **`create`**: class must be RM/SFG/FG; status default `RELEASED`, must be a known status; number from `InvBatch::nextNo`; stores `qc_status_by/at`; copies the allowed attributes.
4. **`setStatus`**: unknown status throws; REJECTED or BLOCKED needs a non-empty reason (message "Alasan wajib diisi untuk status Rejected atau Blocked.", Indonesian text in code); saves who/when.
5. **Receipt posting** (`ReceiptBatchService::forLine`): per stock line with accepted qty > 0: URGENT with quality PENDING = `CONDITIONAL`, otherwise `RELEASED`; reuses an existing batch of the same item + lot; links the PO line, the stock document lines and the cards; writes the dock decision only when the batch is new and not Conditional. A failure becomes a warning, the posting stays done.

## Formula (as-is from the code)
- Batch number: `BT` + `yy` + 5-digit (maximum of that prefix + 1). Example: last `BT2600031` gives `BT2600032`; first of 2027 gives `BT2700001`.
- Flags per status: see the table in the concept card.
- Expiry state (`InvBatch::expiryState`): days to expiry < 0 -> EXPIRED; <= `WH_EXPIRY_SOON_DAYS` (30) -> SOON.

## Edge cases
- The message text of `setStatus` is Indonesian and not translated (it is thrown to the screen only if a caller bypasses `QcDecisionService`, which validates earlier in English).
- A receipt line without accepted qty makes no batch; a line with `is_stock` false makes none.
- `ReceiptBatchService` silently takes the first supplier for an existing batch (see concept card finding 1).
- Race: two `ensure` calls for one new lot can create two batches (no unique index).

## Tests (read, not run)
`BatchServiceTest` (number pattern, lot unique ignoring case/space, empty lot = one NO-LOT batch, flags, refusal of unknown status/class, expiry state), `QcUrgentReceiptTest` (receipt creates Conditional or Released batch), `BatchStockInFormTest`, `BatchOwnerPostingTest` (not read). Not tested: the race above.

## Used by
Batch screens, Purchasing Goods Receipt, `QcDecisionService`, `BatchBackfillService`, pick allocator, putaway planner, UrgentReceiptService, forward trace.
