# Concept card: Purchase Return (PRT, retur ke supplier)

> Written 2026-10-17 (group Procurement). FACT = mockup DB (`--env=mockup`, `tm2pur` = `ddtpur`) and code with line numbers seen in this session. The table has **0 rows** in the mockup data, so everything below is from code and schema; nothing was run. Verified: 2026-10-18.

- **Document type**: concept card. **Module/sub-module**: Procurement > Return > Purchase Return (`PROC_RET`, target `v2:purchase-return`, a grid whose form is the v1 component) and Purchase Return Approval Center (`PROC_RET_APPR`).
- **Canonical name**: Purchase Return = `tm2pur.prt_return` (header) + `prt_line` (lines). Document number prefix NRB.
- **Synonyms**: PRT, retur pembelian, nota retur barang, return to supplier, credit note request.
- **NOT the same as**: Stock Return from Production (Inventory, `13_card_return-from-production.md`), Customer return / Complaint (Sales CRM), Debit Note for a shortage (card `11_card_debit-note.md`), the shortage refusal.

## Facts
| Item | Content | Evidence |
|---|---|---|
| Header | `prt_return`: 0 rows. `doc_no` UNIQUE (`NRB` + 2-digit year + 5 digits, born at Submit), `i_supplier_id`, `pv_id`, `po_id`, `i_source` (QC_REJECT or FROM_STOCK), `i_disposition` (CREDIT_NOTE or REPLACEMENT), `i_reason` NOT NULL, `c_credit_value`, `status`, communication and logistics fields (`qc_inspector`, `comm_method`, `supplier_pic`, `physical_return_date`, `expedition`), supplier credit note fields (`cn_supplier_no`, `cn_received_date`), `replacement_pv_id`, `replacement_due_date`, `rm_stock_doc_id`. | FACT, `show columns`; `PrtReturn.php:17-25,48-55` |
| Lines | `prt_line`: 0 rows. `pv_line_id`, `item_id`, `i_qty`, `i_uom`, `c_unit_price`, `c_line_value` (= qty x price), `is_stock_moved`, `i_lot_no`, `replacement_pv_id`, `replacement_qty`, `replacement_status`. FK `prt_id` ON DELETE CASCADE. | FACT, `show columns`; FK query |
| Two sources | **QC_REJECT**: start from a receipt (`startFromPv`: supplier, `pv_id`, first `c_po_id` of the receipt), add its lines that have a rejected quantity (`pv_line.c_qty_rejected` above 0) minus what other non-cancelled returns already took; the line quantity starts as that remainder, price from the receipt line. **FROM_STOCK**: choose the supplier, search the material master, quantity starts at 1, price `mat_raw.last_unitprice`, `is_stock_moved` = true on the line. | FACT, `resources/views/pages/pur/prt/index.blade.php:127-166`; `resources/views/pages/pur/prt/entry.blade.php:53-82,118-178` |
| Statuses | DRAFT -> SUBMITTED (Submit: needs a line, a reason and a disposition; number born; amount = sum of line values) -> flow: CONFIRMED (final status of flow PRT) or REJECTED; CONFIRMED -> COMPLETED by the button "mark complete"; CANCELLED only from DRAFT (reason of 10+ characters). | FACT, `resources/views/pages/pur/prt/entry.blade.php:225-262,313-336`; `flow_config` 8 |
| Approval | One level, Operational Manager, any amount (card `../requisition/11_card_procurement-approval.md`). Reject needs 10+ characters; Hold ("Minta Info") a note. | FACT, same |
| Credit value | `prt_return.c_credit_value` = sum of `c_line_value` when the disposition is CREDIT_NOTE; stays NULL for REPLACEMENT. | FACT, `PrtReturn.php:57-62` |
| What approval does | Only the status changes. No Stock Out, no journal, no update of the PO or of `pv_line.c_qty_returned`; `rm_stock_doc_id` is never written. The header comment of the component and the backlog (`docs/backlog04_procurement.md`) say the same ("Retur v1 sekarang belum mengeluarkan stok, belum menjurnal, belum update PO"). | FACT, grep of writers of `rm_stock_doc_id`, `c_qty_returned`, `is_stock_moved`, `replacement_*` (none besides the entry component) |
| Guard in Receive | A POSTED receipt that has a non-cancelled return cannot be cancelled. | FACT, `PvPostingService.php:148-151` |
| Read elsewhere | `pv_line.c_qty_returned` is read by the Stock Inspection report (rejected minus returned = still to return). | FACT, `StockInspectionReport.php:84-93` |

## Relations
- **source**: a POSTED receipt and its rejected quantities (`../receive/10_card_purchase-receive.md`), the material master, the supplier (`../order/12_card_supplier-and-category.md`).
- **used_by**: the receipt cancel guard, the Stock Inspection report, the Approval Center, the Purchase Return grid.
- **flows**: DRAFT -> SUBMITTED -> CONFIRMED -> COMPLETED (manual); nothing flows to stock or finance.
- **frozen_zone**: the Goods Receipt / GR-IR flow is frozen; an automatic Stock Out and journal on approval would change it, so it needs Jo's decision (backlog item "Purchase Return v2, dibahas di sesi lain").

## Findings (recorded, not fixed)
1. **Approval of a return moves nothing.** No stock out, no GR/IR reversal, no PO or receipt update. The "rejected, still to return" quantity of the Stock Inspection report never falls, because `pv_line.c_qty_returned` is never written; and the PO line stays RECEIVED although part of the delivery was rejected (receipt card finding 1). The replacement columns (`replacement_*`) are never written either. FACT.
2. **`updateHeader` lists `i_notes`, which is not a column of `prt_return`**; the value is silently dropped (not in `$fillable`). The field `qc_inspector` is shown only for the QC_REJECT source. FACT (`resources/views/pages/pur/prt/entry.blade.php:109`; `show columns`).
3. **No quantity check against the receipt for the typed quantity**: the remainder is the default, but `updateLine` accepts any quantity (even above what was rejected or received). FROM CODE (`updateLine`, line 181).
4. **The server does not re-check the receipt.** The picker lists only POSTED receipts that have a rejected quantity (`resources/views/pages/pur/prt/index.blade.php:74-78`), but `startFromPv` itself accepts any receipt id. FROM CODE.
5. **Cancel only in DRAFT**; a SUBMITTED or CONFIRMED return cannot be withdrawn, and a REJECTED one is final. FACT.
6. **Three vocabularies** (PR/PO APPROVED, return CONFIRMED / COMPLETED, receipt POSTED). FACT.
7. **No test and no data**: no test mentions `PrtReturn` (grep `tests/`), and `prt_return` / `prt_line` have no rows. FACT.

## Already exists? Searched where, result
`concept:find "purchase return"` (no card; menu `PROC_RET`, `PROC_RET_APPR`); files `PurchaseReturnGrid.php`, `resources/views/pages/pur/prt/index.blade.php`, `resources/views/pages/pur/prt/entry.blade.php`; tables `prt_*`; help topics `approval-prt`. Result: ALREADY EXISTS in v1 (components) with a v2 grid; the full v2 design of Jo (Draft, Confirm, approval, ship goods with Stock Out and journal, Closed on credit note) is only a preview mentioned in the backlog (FROM DOCUMENT, not built).
> Label pass 2026-10-18 (Jo): the visible texts of the screens this card cites (labels, columns, tabs, buttons, messages) are English now. Tables, fields, flow and stored values are unchanged, so nothing else in this card changed. Verified: 2026-10-18.
