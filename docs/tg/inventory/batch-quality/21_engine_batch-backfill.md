# ENGINE card: BatchBackfillService (batches from old lot data)

> Written 2026-10-16. Source: `app/Services/Inventory/BatchBackfillService.php` (106 lines) and `app/Console/Commands/InvBackfillBatch.php` (39 lines) read in full. Concept card: `10_card_batch-master.md`. The command was NOT run (it writes; `--check` was not used either). Verified: 2026-10-16.

- **Canonical name**: Batch Backfill. **Class**: `App\Services\Inventory\BatchBackfillService` (`run`). **Registry**: key `batch-backfill`, mode `auto`, group Inventory. **Module/sub-module**: Inventory > Batch & Quality (migration support).
- **Frozen status**: none named. One-off migration tool; safe to repeat.
- **Synonyms**: backfill batch, lama -> batch, "Created from existing lot data". **NOT the same as**: batches made at receipt posting (`ReceiptBatchService`, card `20_engine_batch-service.md`).

## Trigger (no background / schedule)
Manual command only: `php artisan inv:backfill-batch [--check] [--class=RM|SFG|FG] [--item=ID ...]` (add `--env=mockup` for the dummy site). Not in `routes/console.php`. FACT, `app/Console/Commands/InvBackfillBatch.php:15`; the grep of `routes/console.php` shows only `inv:qc-escalate`, `inv:detect-shortage`, `inv:reconcile`, `inv:count-due`.

## Input / Output
- **Input**: `lot_no` values of `rm_stockcard`, `sfg_stockcard`, `fg_stockcard` (item columns `material_id`, `parts_id`, `product_id`) and of `inv_pallet` (with `expiry_date`); item names from `StockItemMeta`.
- **Output (writes, `tm2inv`)**: `inv_batch` (status `RELEASED`, note "Created from existing lot data", expiry = the earliest pallet expiry of the lot, no supplier), then `batch_id` on card rows, document lines (`rm_stock_d`, `sfg_stock_d`, `fg_stock_d`) and `inv_pallet` rows that were still NULL.
- **Returns**: counts per class (groups, created, existing, merged, cards, lines, pallets); `--check` counts only and writes nothing.

## Flow
1. Group every distinct (class, item, normalised lot) from cards and pallets; the lot variants that differ only by case/space merge into one group ("merged" count).
2. For each group: reuse the existing batch (`BatchService::findByLot`) or create one with `BatchService::create`.
3. Update only rows with `batch_id` NULL whose normalised lot matches (the "no lot" group takes empty lots and lots literally named like the constant `INV_NO_LOT_BATCH_CODE`).
4. Idempotent: a second run finds the batches and has nothing left to link.

## Formula (as-is)
- Expiry of a new batch = minimum of the group's pallet `expiry_date` (or NULL). FACT, `BatchBackfillService.php:62-90`.

## Edge cases
- Cards written after the run with a lot but no `batch_id` are caught by the next run; cards of a stock-in made through a screen that sets the batch are skipped.
- The batch has no supplier, so sampling history cannot use it (concept card finding 4).
- A group is per item + lot; the same lot number from two suppliers becomes one batch.
- Dummy state seen: 82 batches (81 with the backfill note), 101 of 136 `rm_stock_d` rows linked; `tm2inv.inv_batch` is empty, meaning the live databases have not been backfilled yet (the release plan is in `docs/md_for_systems/inventorywarehouse_v201_tm2_release.md`, FROM DOCUMENT, not read).

## Tests (read, not run)
`BatchBackfillTest`: check mode counts but writes nothing; backfill creates Released batches, links rows and is idempotent; the artisan command reports and `--check` does not write.

## Used by
Release/migration to tm2 (not yet done per the empty table); nothing at runtime.
