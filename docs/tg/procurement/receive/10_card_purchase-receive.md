# Concept card: Purchase Receive (Goods Receipt, SPB)

> Written 2026-10-17 (group Procurement). FACT = mockup DB (`--env=mockup`, `tm2pur` = `ddtpur`) and code with line numbers seen in this session. Posting and cancelling are in the engine card `20_engine_pv-posting.md` (not repeated). The warehouse side (bins, putaway, labels) and the batch / URGENT / quality rules are in the Inventory cards linked at the end. Verified: 2026-10-17.

- **Document type**: concept card. **Module/sub-module**: Procurement > Receive > Purchase Receive (`PROC_RCV`, target `v2:purchase-receive`); report Outstanding PV (`PROC_MON_PV`).
- **Canonical name**: Purchase Receive = `tm2pur.pv_receipt` (header) + `pv_line` (lines). Short names PV (code) and SPB (printed document, "Goods Receipt Note").
- **Synonyms**: SPB, surat penerimaan barang, goods receipt, GRN, PV, penerimaan barang; type "From PO", "From Customer", "From Subcon".
- **NOT the same as**: Stock In (a document of Inventory, `rm_stock`, which this posting writes), Delivery Order (Sales), Supplier Invoice Receipt (Finance).

## Facts
| Item | Content | Evidence |
|---|---|---|
| Header | `pv_receipt`: 6 rows (POSTED 3, QC_PENDING 2, DRAFT 1; all type PO). `i_receipt_type` PO / CUSTOMER / SUBCON; `i_supplier_id` (or `i_customer_id` + `i_co_id` for customer material); `i_warehouse_code`; `i_supplier_do_no`, `i_vehicle_no`; `c_po_count`, `c_po_doc_list` (one SPB may cover many POs); `status`; `posted_at/by`; `rm_stock_doc_id` (the Stock In it created), `rm_stock_rev_doc_id` (the reversal), `cancel_reason`; `is_handed_over*`. | FACT, `show columns`, group-by query; `PvReceipt.php:16-27` |
| Number | Born at **posting**, not at create (rule of Jo 2026-10-02: Draft deletes must not leave gaps). Prefix by type: SPB (PO), PBC (customer material), PBS (subcon), + 2-digit year + 5 digits. A Draft shows as "Draft #<id>". | FACT, `PvReceipt.php:40-61`; `PvPostingService.php:94` |
| Lines | `pv_line`: 10 rows. From a PO line: `po_line_id`, `c_po_id`, `c_po_doc_no`, `c_po_line_no` (copied), `i_qty_purchase` (starts as the open quantity of the PO line), `i_qty_stock` and `c_conv_used` (conversion to the stock unit), `c_unit_price` (PO price copied), `c_tol_pct`, `c_qty_variance_pct`, `is_tol_exceeded`, `tol_override_by/at/reason`, `i_lot_no`, `i_location_code`, `i_next_process` (subcon), `c_qty_short`, `c_qty_accepted`, `c_qty_rejected`, `c_line_value`, URGENT columns (Inventory). | FACT, `show columns`; `PvBuilderService.php:120-153` |
| How a Draft is made | Step 1 the supplier worklist (suppliers with at least one open receivable line of a SENT or PARTIALLY_RECEIVED PO); step 2 the open lines of that supplier across POs; the user picks lines and a warehouse code (`wh_warehouse`). All lines must be of the same supplier ([PV-02]) and the POs must have the same currency ([PV-04]; today always NULL = IDR). More lines can be added while DRAFT. | FACT, `PvBuilderService.php:20-109`; route `routes/v2.php:851-862` |
| Other types | CUSTOMER (material the customer brings, needs a CO) and SUBCON (goods back from a subcontractor against a Subcon PO, optional Subcon Delivery Note) are made by `SpecialReceiptService`, have their own save / post path and their own print. | FACT, `routes/v2.php:863-883,952-980`; Inventory cards 16 and 17 |
| Quality check on a line | Two separate checks: quantity (`qc_qty_status` PENDING -> DONE via "mark qty done", which also moves a Draft to QC_PENDING) and quality (`qc_quality_status` PENDING / PASS / PARTIAL / FAIL). PASS: accepted = stock qty, rejected 0; FAIL: accepted 0, rejected = stock qty; PARTIAL: the officer types both. Accepted x price = `c_line_value`. | FACT, `resources/views/pages/pur/pv/entry.blade.php:99-149` |
| Tolerance | Variance % of a line = (received qty - open qty of the PO line before this receipt) / that open qty x 100; the line is "exceeded" above the tolerance of the PO line (copied from `mat_raw.tol_purchase`) or the constant `RECEIVE_TOLERANCE_PCT` (5). An exceeded line blocks posting until someone fills an override reason (name, time, reason saved). | FACT, `PvLine.php:52-64`; `resources/views/pages/pur/pv/entry.blade.php:151-168`; `PvPostingService.php:48-76` |
| Status words | DRAFT, QC_PENDING, POSTED, CANCELLED. Only a POSTED receipt can be cancelled (reason of 10+ characters), refused when it is handed over, has a non-cancelled Purchase Return, or has a shortage correction. | FACT, `PurchaseReceive.php:19`; `PvPostingService.php:137-156` |
| Edit and delete | Header and lines can be changed while DRAFT or QC_PENDING (the v2 save refuses otherwise); delete only while DRAFT. | FACT, `PurchaseReceive.php:122,182` |
| Print | SPB (PO and SUBCON types) and "Tanda Terima Bahan Customer" (CUSTOMER); the print columns show accepted and rejected quantity and lot. | FACT, `routes/v2.php:885-940` |
| Reports | Outstanding PV = POSTED receipts with `is_handed_over` = 0. Outstanding PO = receivable lines of SENT or PARTIALLY_RECEIVED POs. | FACT, `OutstandingPvReport.php:44`; `OutstandingPoReport.php:45-48` |
| Data | 3 lines are PASS with no exceed, 1 PASS with an override (SPB2600003: 54 received against 50 open, +8% against 5%), 1 PARTIAL (receipt for the Subcon PO2600008: 4,500 accepted, 500 rejected, still QC_PENDING), 2 lines pending. The 3 POSTED receipts have `rm_stock_doc_id` NULL. | FACT, queries on `pv_line`, `pv_receipt` |

## Relations
- **source**: PO lines (`../order/11_card_purchase-order.md`), supplier, warehouse master, material conversion and tolerance, the CO (customer material).
- **used_by**: posting engine `20_engine_pv-posting.md` (Stock In RM document, GR/IR journal, batch), Purchase Return (`../return/10_card_purchase-return.md`, from the rejected quantity), Debit Note (`../return/11_card_debit-note.md`, born by a refused shortage), Shortage Request, Finance GR/IR aging and Supplier Invoice Receipt, Inventory putaway and labels.
- **flows**: worklist -> Draft -> quantity and quality check -> (override) -> Post -> PO recalculated; Cancel reverses.
- **frozen_zone**: the Goods Receipt posting flow is frozen (CLAUDE.md section 1; also named in the URGENT card); ask Jo first.

## Findings (recorded, not fixed)
1. **PO progress counts the delivered quantity, not the accepted quantity.** `recalcPo` sums `pv_line.i_qty_purchase` of POSTED receipts, so a rejected 500 of a 5,000 delivery still counts as received and the PO line closes; the way back is a Purchase Return, whose replacement columns (`prt_line.replacement_*`) nobody writes (return card, finding 1). FACT (code).
2. **`is_handed_over`, `handed_over_at/by` and `pv_line.c_qty_invoiced` are never written** by any code (grep), so every POSTED receipt stays in the Outstanding PV report and the form status "closed" can never appear. GR/IR aging reads `c_qty_invoiced` (always 0). FACT.
3. **The three POSTED dummy receipts have no Stock In document** (`rm_stock_doc_id` NULL): they were created by the seeder, not by the posting rule, so Cancel on them gives "no stock to reverse" and the stock card shows no entry from them. FACT (query); behaviour of Cancel on them not run.
4. **SPB2600001 is QC_PENDING but already numbered**, against the rule that the number is born at posting (older dummy). FACT.
5. **A Subcon PO line received as a "From PO" receipt**: receipt 14 (PO2600008, Subcon) has type PO, not SUBCON, while the PO worklist has no `i_po_type` filter. FACT (data; `PvBuilderService.php:20-56`).
6. **Some v1 component methods have no status guard** (`markQtyDone`, `setQuality`, `updateQcQty`, `overrideTolerance` in `resources/views/pages/pur/pv/entry.blade.php`); only the v2 save refuses a POSTED receipt. FROM CODE; route `/purchasing/pv` of v1 not tried.
7. **Receiving location is free text** (`i_location_code`) matched to a bin at posting, with fallbacks (see the engine card). FACT.

## See also (other authors' cards, not repeated)
Warehouse side: [receiving and putaway](../../inventory/warehouse/12_card_receiving-and-putaway.md). Batch, URGENT and quality: [batch master](../../inventory/batch-quality/10_card_batch-master.md), [conditional stock and URGENT](../../inventory/batch-quality/11_card_conditional-stock-and-urgent.md), [subcontract stock](../../inventory/batch-quality/16_card_subcontract-stock.md), [customer material](../../inventory/batch-quality/17_card_customer-material.md), [shortage request](../../inventory/batch-quality/15_card_shortage-request.md). Finance side: [GR/IR aging](../../inventory/stock/19_card_gr-ir-aging.md).

## Already exists? Searched where, result
Searched `pv_receipt`, `pv_line`, `PvPostingService`, `PvBuilderService`, `SpecialReceiptService`, menu `PROC_RCV`; `concept:find "goods receipt"`. Result: ALREADY EXISTS in v1 (`pages::pur.pv.entry`) and v2 (`purchase-receive`). The Inventory cards describe what posting creates in Inventory; this card and the engine card describe the Procurement side.
