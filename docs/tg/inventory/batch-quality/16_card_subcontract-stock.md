# Concept card: Subcontract stock (Send to Subcontractor, Stock at Vendor)

> Written 2026-10-16 (group Inventory, Batch & Quality). FACT = mockup DB and code with line numbers, seen this session; no test was run (`SubconStockTest` read only). Verified: 2026-10-16.

- **Document type**: concept card. **Module/sub-module**: Inventory > Subcontract > Send to Subcontractor (`INV_WH_SUBCON`, `v2:subcon-send`, grid + form) and Stock at Vendor (`INV_WH_RPT_VENDOR`, `v2:stock-at-vendor`, report). The menu group is `INV_SUBCON`; the item codes keep the older prefix `INV_WH_`.
- **Canonical name**: Subcon send = one `tm2inv.inv_subcon_issue` (header, model `InvSubconIssue`, number `SC` + year + 5 digits) with lines in `inv_subcon_issue_line` (model `InvSubconIssueLine`). It records raw material that the company owns and has moved to a subcontractor's virtual bin.
- **Synonyms**: kirim bahan ke subkon, bahan di vendor, material at vendor, subcon issue, Surat Jalan Subkon (that is the Sales-side DO of type SUBCON, a different document).
- **NOT the same as**: the Subcontract Delivery Note (`do_delivery` type SUBCON, Sales > Delivery, card `../../sales/delivery/10_card_delivery-order.md`), the subcon goods receipt (`pv_receipt` type SUBCON, Purchasing; it receives the finished SERVICE, not stock), customer material (card `17_card_customer-material.md`).

## Facts
| Item | Content | Evidence |
|---|---|---|
| Header | `inv_subcon_issue`: `issue_no` UNIQUE, `issue_date`, `po_id`, `po_no`, `supplier_id`, `due_return_date`, `status` (SENT / CLOSED / CANCELLED), `transfer_id` (the Stock Transfer that moved the stock), `notes`, `cancel_reason`, audit. Dummy and tm2: 0 rows. | FACT, `information_schema`; `app/Models/Inventory/InvSubconIssue.php:12` |
| Line | `inv_subcon_issue_line`: `issue_id`, `line_no`, `po_line_id`, `stock_class` (RM only today), `item_id/code/name`, `from_location_id`, `vendor_location_id`, `uom_code`, `qty_sent`, `qty_consumed`, `qty_returned`, `unit_cost`. At vendor = sent - consumed - returned. | FACT, `information_schema`; `app/Models/Inventory/InvSubconIssueLine.php:25-29` |
| Parent document | A Subcon PO: `po_order.i_po_type` = SUBCONTRACT, not cancelled, status SENT or PARTIALLY_RECEIVED. Dummy: exactly 1 such PO (SENT). Message when not: "Choose a Subcon PO that is still open." | FACT, `app/Services/Inventory/SubconStockService.php:35-38`; query |
| Send | One material per send, one source bin. Quantity must be > 0 ("Fill in the quantity to send."). Only stock owned by the COMPANY may be sent ("Only stock owned by the company can be sent to a subcontractor."). A one-line Stock Transfer is created and posted at once (no approval); the stock moves to the vendor's bin `VIRT-SUB-<supplier code>`, stays the company's and keeps its value (the source bin's average travels). No journal. | FACT, `SubconStockService.php:33-66,165-177`; `app/Services/Inventory/VirtualLocationService.php:63-70` |
| Virtual place | Warehouse `VIRT`, zone type `SUBCON` ("At Subcontractor (virtual)"), one bin per supplier, all created on first use. The mockup has no SUBCON zone yet (created by the first send). | FACT, `VirtualLocationService.php:20-49`; query on `wh_zone` |
| Return | Unused material goes back to a chosen bin of the same warehouse as the original bin (Stock Transfer vendor -> bin). Message when too much: "The quantity must be more than 0 and not more than what is left at the vendor (:qty)." | FACT, `SubconStockService.php:69-77,144-162`; `resources/views/v2/screens/subcon-send.blade.php:66-79` |
| Used | "Used" posts a normal RM Stock Out from the vendor bin (`ref_doc_type` SUBCON_ISSUE) through `StockPostingService`, so it becomes ordinary usage with the standard usage journal; if posting is refused the draft is cancelled and the message shown. | FACT, `SubconStockService.php:80-97` |
| Auto-settle | Posting a SUBCON goods receipt calls `settleReceipt`: for each received PO line, the material counts as used in proportion (received qty / PO line qty x qty sent, capped by what is at the vendor). Failure only logs (`report`) and does not undo the receipt. | FACT, `SubconStockService.php:101-122`; `app/Services/Pur/SpecialReceiptService.php:172-178` |
| Cancel | Only while status SENT and nothing used or returned; reason required; reverses the Stock Transfer; status CANCELLED, `cancel_reason`. | FACT, `SubconStockService.php:125-141` |
| Close | The send becomes CLOSED automatically when every line has nothing left at the vendor. | FACT, `SubconStockService.php:158-160` |
| Grid and report | Send list (limit 300): Send No. / Subcon PO, subcontractor, date, due back, material, sent, at vendor, Overdue badge (status SENT and due date before today), status. Stock at Vendor: SENT issues with more than 0.00005 left, per subcontractor, with age in days since `issue_date`, due back and Overdue. | FACT, `app/Support/V2/Data/SubconSends.php:26-37`; `app/Support/V2/Grid/Inv/StockAtVendorReport.php:43-52` |
| Routes and rights | `v2.wh.subcon.create` and `v2.wh.subcon.act` (action return / consume / cancel) inside the `module:INV` group; no `QcAuthority` or group check inside. | FACT, `routes/v2_stockflow.php:19,87-128` |
| Tests (read, not run) | `SubconStockTest`: send moves stock and keeps value; refusal cases; vendor report; proportional settle on receipt; return not twice; cancel only before use. | FACT, `tests/Feature/SubconStockTest.php:82-165` |

## Relations
- **source**: Subcon PO (`po_order`, `po_line`), RM stock in a real bin (`rm_stockcard`, `WarehouseStock::positions`), supplier master, `StockTransferService`, `StockPostingService`, `VirtualLocationService`.
- **used_by**: Stock at Vendor report, subcon goods receipt (auto-settle), valuation (the value stays in the company stock), Sales-side Subcontract Delivery Note (separate document, no link in code).
- **flows**: Subcon PO sent -> Send to Subcontractor (stock to vendor bin) -> either Return (back to bin), Used (Stock Out) or the subcon receipt (auto Used in proportion) -> CLOSED.
- **frozen_zone**: Procurement PO and receipt flow (do not change without Jo). Stock core and transfer rules: `../stock/`, `../warehouse/`.

## Findings (recorded, not fixed)
1. **The send always attaches to the FIRST PO line.** The route passes `null` as the PO line (`routes/v2_stockflow.php:94`) and `send` then takes the lowest `line_no` (`SubconStockService.php:48`). On a Subcon PO with several lines the auto-settle on receipt (matched by `po_line_id`) uses the wrong line. FACT.
2. **Batch and lot are lost on the way out.** The transfer lines carry no `lot_no`/`batch_id` (`SubconStockService.php:169-171`) and the transfer posting writes cards without `batch_id` (`StockTransferService.php:380`); the "Used" Stock Out line has no batch either (`:86-89`). A batch's On Hand therefore does not fall when material goes to a vendor, and the forward trace of a rejected batch cannot see subcon usage. FACT; not run.
3. **Sending is not checked for the owner by batch.** `PickAllocator::ownerOf` takes the latest card of the material in the bin without the batch (`SubconStockService.php:44`), so in a bin that holds both company and customer stock the "owner" answer depends on which card is newest. FACT.
4. **No authority check** beyond module INV: any Inventory user can send, return, use or cancel. By contrast URGENT, QC and shortage steps have named groups (`QcAuthority`). FACT; may be intended for warehouse staff.
5. **Return-to bin is limited only on the screen.** The dropdown lists bins of the warehouse of the original bin (`subcon-send.blade.php:71`); the route and the service accept any bin id (`routes/v2_stockflow.php:112`, `SubconStockService.php:72`); whether the transfer rules then refuse a virtual or blocked bin was NOT checked. FACT for the missing check.
6. **Due-back is an information date only**: Overdue appears on the grid and the report, but no notification, no escalation and no link to the PO's delivery date. FACT (grep `due_return_date`).
7. **Only RM is supported** (`stock_class` default RM; SFG/FG to a subcon is not built, as the service header says). FACT; Sales has a separate Surat Jalan Subkon for finished or semi-finished items.
8. **PO status is not updated by a send** and the send does not check that the PO line's material is the material sent. FACT (service code).
9. **Dummy has 0 sends**; Stock at Vendor and the grid have never shown real rows in UAT. FACT.

## Already exists? Searched where
Searched `inv_subcon_issue`, `SubconStockService`, `VirtualLocationService`, EngineRegistry key `subcon-stock`, menu `INV_SUBCON`, `v2:subcon-send`. Result: ALREADY EXISTS. FROM DOCUMENT: D2 (vendor = virtual location owned by the company) and D22 (Subcon PO as parent), `inventorywarehouse_v201_scope.md` section 1.
