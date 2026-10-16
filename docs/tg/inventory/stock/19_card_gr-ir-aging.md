# Concept card: GR/IR Aging (goods received, not invoiced; invoiced, not received)

> Written 2026-10-16 (group Inventory/Stock). FACT = mockup DB (`ddtpur`, `ddtacc`) and code with line numbers, seen this session. Test read by name only.
> Verified: 2026-10-16

- **Document type**: concept card (a report, no document of its own). **Module/sub-module**: Inventory > Stock > Valuation & Period > Report > "GR/IR Aging" (`INV_VAL_GRIR`, `v2:inv-grir-aging`).
- **Canonical name**: GR/IR Aging = `GrIrAgingService::rows` shown by the grid `GrIrAgingReport`; the "GR/IR" clearing account is the account that goods-receipt posting credits (comment in the service; the posting itself is in Procurement).
- **Synonyms**: GRNI, goods received not invoiced, barang diterima belum ditagih, tagihan belum ada barang, umur GR/IR.
- **NOT the same as**: Supplier Invoice Receipt (Finance document), Purchase Return, Daily Reconciliation.

## Facts
| Item | Content | Evidence |
|---|---|---|
| Received side | Lines of goods receipts with status POSTED, type PO, `is_stock`=1 and accepted quantity > 0, dated on or before the "To date"; value = accepted quantity x PO unit price. | FACT, `GrIrAgingService.php:27-34,41` |
| Invoiced side | (a) `pv_line.c_qty_invoiced` x price; (b) Confirmed, not cancelled supplier invoice receipt lines that carry a PO id (`fin_invoice_receipt_line.po_id`), dated on or before the To date, summed per PO. | FACT, `GrIrAgingService.php:85-95` |
| Matching | Per PO, the invoiced amount closes the OLDEST receipts first; what is left of a receipt = "Received not invoiced", aged from the receipt date; an invoiced amount beyond all receipts = "Invoiced not received", aged from the oldest invoice date; POs with invoices and no receipt at all are listed too. | FACT, `GrIrAgingService.php:37-60` |
| Buckets | 0-30, 31-60, 61-90, 90+ days (columns b1 to b4); totals per kind and the 90+ total. | FACT, `GrIrAgingService.php:19-24,63-82` |
| Screen | Report grid with a "To date" selector (default today), grouped by supplier; sources shown: `pv_receipt`, `pv_line`, `fin_invoice_receipt`, `fin_invoice_receipt_line`, `sup_supplier`. | FACT, `GrIrAgingReport.php` |
| Data (mockup) | 5 POSTED PO stock lines; 0 invoice receipt lines with a PO id. | FACT, query |

## Relations
- **source**: Procurement (`pv_receipt`, `pv_line`), Finance (`fin_invoice_receipt*`), supplier master.
- **used_by**: Valuation & Period screen (link), month-end review by Finance.
- **flows**: goods receipt post -> (invoice receipt confirm) -> aging report; no document is written.
- **frozen_zone**: none.

## Findings (recorded, not fixed)
1. **Source (a) is dead**: `pv_line.c_qty_invoiced` is read here but written by no code under `app/` (grep), so only the invoice receipt lines close receipts. FACT (grep).
2. **Approximate matching** (stated in the service comment): invoice value is compared with receipt value as is, without separating VAT and discounts, per PO and not per line; a VAT-inclusive invoice would close more receipt value than it should. FROM CODE.
3. **Invoice receipt without PO id never appears**: only lines with `po_id` are counted. FROM CODE.
4. **Report only**: nothing is posted; the GR/IR account balance itself is checked nowhere in this module (the Daily Reconciliation covers the inventory accounts only). FROM CODE.

## Tests (existing, not run now)
`GrIrAgingTest` (an invoice closes the oldest receipts first and the rest is aged; an uninvoiced old receipt falls in 90+; an invoice bigger than the receipt is reported as invoiced not received; receipts after the To date are left out).

## Already exists? Searched where
Searched: `GrIrAgingService`, `GrIrAgingReport`, Procurement posting comments. The concept ALREADY EXISTS.
