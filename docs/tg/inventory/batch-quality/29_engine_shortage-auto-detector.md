# ENGINE card: ShortageAutoDetector (Draft shortage requests from production output)

> Written 2026-10-16. Source: `app/Services/Pur/ShortageAutoDetector.php` (135 lines), `app/Services/Pur/ShortageDetector.php` (61 lines) and `app/Console/Commands/InvDetectShortage.php` (32 lines) read in full. Table, statuses and findings: concept card `15_card_shortage-request.md` (not repeated). The command was NOT run (not even `--dry-run`). Verified: 2026-10-16.

- **Canonical name**: Shortage Auto Detector. **Class**: `App\Services\Pur\ShortageAutoDetector` (static `scan`, `check`, `productionFacts`). **Registry**: key `shortage-auto-detector`, mode `suggest`, group Procurement (the formula part, `ShortageDetector`, is key `shortage-detector`). **Module/sub-module**: Inventory > Batch & Quality > Shortage Request.
- **Frozen status**: none named. It only creates DRAFT requests; Warehouse recount and PPIC confirm remain the decisions (A.7).
- **Synonyms**: detect shortage, `inv:detect-shortage`, auto-detect, kurang kirim otomatis. **NOT the same as**: the manual New Record on the Shortage Request screen (it goes through the same `ShortageRequestService::create`).

## Trigger (scheduled)
SCHEDULED daily 05:15: `Schedule::command('inv:detect-shortage')->dailyAt('05:15')->withoutOverlapping()`, only when table `inv_shortage_request` exists in `tm2inv`. FACT, `routes/console.php:55`. Manual: `php artisan inv:detect-shortage [--wo=ID] [--dry-run] [-v]` (`app/Console/Commands/InvDetectShortage.php:12`).

## Input / Output
- **Input**: pairs (work order, material) with net used > 0 from view `v_inv_wo_reconcile`; `prd_wo` (`qty`, `wo_status`), `prd_wo_material` (`qty_required`, `cutting_yield_waste_qty`, `uom`), `prd_wo_operation` (last operation by `seq_no`), `prd_wo_operation_output` (`qty_good`), issue documents with batch (`rm_stock_d.batch_id`), `pv_line.i_batch_id`, `po_line.c_tol_pct`, constant `RECEIVE_TOLERANCE_PCT` (5).
- **Output (writes)**: via `ShortageRequestService::create` one DRAFT row in `inv_shortage_request` (with note "Detected automatically from production output.", then `detected_source` = AUTO) and the "Recount needed" bell notice. Command prints CREATED / WOULD_CREATE lines and a summary; `-v` shows the skip reasons.

## Flow
1. Scan every pair with net used > 0 (or one WO).
2. Skip with a reason when: no material row on the WO; a request for that WO + material already exists; the WO need is 0; the WO unit differs from the warehouse unit; the WO is not finished (`wo_status` COMPLETED, or last operation DONE / QC_PASSED); the last operation has no output; the issued material has no batch; no POSTED receipt line of type PO / SUBCON / CUSTOMER belongs to the issued batches.
3. Convert: output good (material unit) = sum of `qty_required` x (sum of last operation `qty_good` / WO qty); standard waste = sum of `cutting_yield_waste_qty` x the same ratio.
4. Evaluate with `ShortageDetector::evaluate` and the tolerance of the PO line of the receipt line (the batch issued most to the WO is tried first). Outcomes: COVERED, WITHIN_TOLERANCE, WOULD_CREATE (dry run) or CREATED.

## Formula (as-is) and worked example
Candidate = max(0, issued net - (output good + standard waste)); tolerance qty = issued net x tolerance % / 100; a request only if candidate > 0 AND candidate > tolerance qty.
Example: issued net 1,000 kg; WO qty 10,000 pcs, last operation good 9,000 pcs (ratio 0.9); `qty_required` 950 kg, standard waste 20 kg. Output good = 950 x 0.9 = 855; waste = 20 x 0.9 = 18; candidate = 1,000 - 873 = 127 kg; tolerance 5% of 1,000 = 50 kg; 127 > 50 -> a DRAFT request for 127 kg suspected. Because `qty_required` already holds the waste of every step, the detector leans lenient (stated in the file header).

## Edge cases
- It never judges a pair without a batch on the issue lines, so old unbatched issues stay manual.
- `check` creates the request with the same validation as a manual one; if `create` refuses, the pair is SKIPPED with the message.
- No duplicate guard exists in the manual path (concept card 15, finding 2).

## Tests (read, not run)
`ShortageAutoDetectTest` (output converted and a Draft request made with a notice and no duplicate; pairs that cannot be judged are skipped with the reason and small gaps are no request; confirm makes an additional Draft PR and a refusal makes a Draft debit note), `ShortageRequestTest::test_detector_applies_the_standard_waste_and_the_tolerance`.

## Used by
The nightly schedule; the Shortage Request list (rows with source AUTO).
