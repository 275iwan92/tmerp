# Concept card: Customer Material (titipan / consignment stock)

> Written 2026-10-16 (group Inventory, Batch & Quality). FACT = mockup DB and code with line numbers, seen this session; no test was run (`CustomerMaterialTest` read only). Verified: 2026-10-16.

- **Document type**: concept card. **Module/sub-module**: Inventory > Customer Material > Customer Material (`INV_WH_RPT_CUSTMAT`, `v2:customer-material`, report); the stock is created by a Purchasing goods receipt of type CUSTOMER (Penerimaan Bahan Baku Customer).
- **Canonical name**: Customer Material = raw-material stock whose owner is a customer: rows of `tm2inv.rm_stockcard` with `owner_type` = `CUSTOMER` and `owner_id` = the customer, in a bin of a CONSIGN zone, with no value and no journal (rulebook D3, FROM DOCUMENT).
- **Synonyms**: bahan titipan customer, consignment, customer-supplied material, owner CUSTOMER.
- **NOT the same as**: company stock sent to a subcontractor (card `16_card_subcontract-stock.md`), the Sales Customer Order lines for customer material (not read), finished goods of a customer's order.

## Facts
| Item | Content | Evidence |
|---|---|---|
| How it is born | Posting a CUSTOMER receipt (`pv_receipt.i_receipt_type` = CUSTOMER) with lines that point to a raw material (`item_id`, qty > 0): one Stock In RM document is created and posted in the same call, `unit_price` 0, `owner_type` CUSTOMER, `owner_id` = `pv_receipt.i_customer_id`, bin = first usable bin of a CONSIGN zone (optionally of the receipt's warehouse code). Lines without a material stay notes (no stock). | FACT, `app/Services/Inventory/CustomerMaterialService.php:20-54`; `app/Services/Pur/SpecialReceiptService.php:146-165` |
| Refusal | No usable consign bin: "There is no usable bin in a Consignment area. Add one in Storage Areas and Bins first." and nothing is posted. If the stock posting is refused the draft is cancelled and the error shown. | FACT, `CustomerMaterialService.php:40,47-51`; `SpecialReceiptService.php:148-151` |
| Not valued | The stock posting treats any owner other than COMPANY as unvalued: no value on the card and no journal. | FACT, `app/Services/Inventory/StockPostingService.php:137-141` (owner), header comment of `CustomerMaterialService.php:13-16` |
| Picking rule | Stock owned by a customer is allocated only to the pick list of a job of THAT customer; company stock is allocated to anyone. Owner = the newest card of the material in that bin (and batch if any); no card = COMPANY. | FACT, `app/Services/Inventory/PickAllocator.php:106-123` |
| Report | Positions (material + bin) whose newest card is CUSTOMER-owned and quantity-after (`qty_eb`) > 0: customer, item, bin, qty, unit, last movement, age in days since that card. Filter by customer. | FACT, `app/Support/V2/Grid/Inv/CustomerMaterialReport.php:46-58` |
| Zone and data | A CONSIGN zone exists (1 zone, real, not virtual). Dummy: 0 customer-owned cards (`rm_stockcard.owner_type` = CUSTOMER), so the report is empty. | FACT, queries on `wh_zone`, `rm_stockcard` |
| Batch tab | The Batch form's stock tab shows the owner ("Company" or "Customer") per bin; the batch On Hand adds all owners together. | FACT, `app/Support/V2/Data/Batches.php:86-103`; `BatchLookup.php:124` |
| Shortage | A customer can be the source of a Shortage Request (customer sent less than the receipt says). | FACT, `ShortageRequestService.php:31,37` (card `15_card_shortage-request.md`) |
| Tests (read, not run) | `CustomerMaterialTest`: a customer receipt becomes customer-owned stock in the consign bin without value or journal; no consign bin -> refused and nothing posted; a line without a material stays a note. | FACT, `tests/Feature/CustomerMaterialTest.php:64,90,103` |

## Relations
- **source**: Purchasing receipt type CUSTOMER (`pv_receipt`, `pv_line`), customer master (`cust_customer`), storage areas and bins (`wh_zone`, `wh_loc`).
- **used_by**: Customer Material report, pick allocation (`PickAllocator`), Stock Card (owner column), Shortage Request (source CUSTOMER), Batch form stock tab.
- **flows**: customer delivers material -> CUSTOMER receipt posted -> Stock In (owner CUSTOMER, consign bin) -> picked only for that customer's job -> consumed by a work order (Stock Out; not read here).
- **frozen_zone**: Goods Receipt flow (do not change without Jo).

## Findings (recorded, not fixed)
1. **A Stock Transfer or putaway silently turns customer stock into company stock.** `StockTransferService::move` writes the new cards without `owner_type`/`owner_id` (column default `COMPANY`, `rm_stockcard.owner_type`), and no owner handling exists in `StockTransferService`, `BinRules`, `PutawayPlanner`, `PalletService` or `WarehouseStock` (grep). After one move the newest card in the destination bin says COMPANY, so `ownerOf` allows any job to pick it and the report no longer lists it. FACT from code; NOT run on data. This is the most serious risk of the topic.
2. **No batch and no lot rule for customer material.** `SpecialReceiptService` never calls `ReceiptBatchService`; `receive` passes only `lot_no`. Customer material therefore has no batch status, cannot be sampled or rejected through the QC screens, and is outside forward trace. FACT.
3. **The report decides ownership per bin by the newest card only.** If a bin holds both company and customer material of the same item, the latest card wins for the whole position (owner is not per batch/quantity). FACT, `CustomerMaterialReport.php:49-50`.
4. **Age counts from the last movement, not from arrival**, so a small pick today resets the age of the remaining stock. FACT, `CustomerMaterialReport.php:56`.
5. **N+1 queries**: one query per position in the report. Harmless at current size. FACT, `CustomerMaterialReport.php:49`.
6. **Retention and return are not built.** The design notes propose a retention period then a customer decision with default bill-or-destroy (FROM DOCUMENT, `docs/design-notes/inventory/15_inv-open-decisions.md` item 8); there is no screen to return, bill or scrap customer material. FACT (grep `CUSTOMER` owner paths).
7. **No link from the receipt to the Customer Order.** The shortage request carries `co_id` from `pv_receipt.i_co_id`, but the stock card has no CO reference; the pick rule uses the customer only, not the CO. FACT.
8. **Dummy has no customer material at all**, so none of the above has been seen on screen in UAT. FACT.

## Already exists? Searched where
Searched `owner_type`, `CustomerMaterialService`, `CONSIGN`, menu `INV_CUSTMAT`/`v2:customer-material`, EngineRegistry key `customer-material`. Result: ALREADY EXISTS (receipt, stock, pick rule, report). Rulebook D3 and line 266 (FROM DOCUMENT) require the same.
- Re-checked 2026-10-19 after Return from Production moved to the v2 form `inv-stock-doc-form` (rule D.25, Master T5c): `routes/web.php` replaced the v1 `/inventory/stock-return/{category}` route by a redirect on the same line (no line added or removed), `app/Support/Menu/MenuRoutes.php` lost the two Return entries, `app/Support/V2/Lookups.php` gained the `stock_return_bin` lookup (lines after 68 moved by two), `app/Services/Inventory/StockPostingService.php` gained the `addReturned` call on an existing line and one method at the end of the file, and `routes/v2_stockdocs.php`, `StockDocGrid.php` and the screen got the Return kind; this card does not depend on the changed lines and its content is still true.
- Re-checked 2026-10-19 after Stock Adjustment and Opname moved to the v2 form `inv-stock-adj-form` (rule D.25, Master T5d): `routes/web.php` replaced the v1 `/inventory/stock-adjustment/{category}/{doc?}` route by a redirect on the same line (no line added or removed), `app/Support/Menu/MenuRoutes.php` lost the three Adjustment entries, `app/Support/V2/Screens.php` got one item appended on an existing line, `app/Support/V2/Grid/Inv/StockDocGrid.php` and `app/Support/V2/Data/ApprovalCenter.php` changed in the inventory and approval parts, and `routes/v2_stockdocs.php` got a second route group at the end; this card does not depend on the changed lines and its content is still true.
