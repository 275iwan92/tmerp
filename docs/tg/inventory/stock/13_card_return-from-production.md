# Concept card: Stock Out and Return from Production

> Written 2026-10-16 (group Inventory/Stock). FACT = mockup DB (`ddtinv`) and code with line numbers, seen this session. No test was run (no DB writes allowed in this task).
> Verified: 2026-10-18

- **Document type**: concept card. **Module/sub-module**: Inventory > Stock > Raw Material / Semi Finished Goods > "Stock Out" (`INV_RM_ITEM2`, `INV_SFG_ITEM2`) and "Return from Production" (`INV_RM_ITEM3`, `INV_SFG_ITEM3`); the same menu items exist under Finished Goods (`INV_FG_ITEM2`, `INV_FG_ITEM3`) but are read-only lists (see Facts).
- **Canonical name**: Stock Out = Stock Document (card `10_card_stock-document.md`) with `doc_type` -1 and `doc_cat` STOCK. Return from Production (RTP) = Stock Document with `doc_cat` RETURN, `doc_type` 1, `ref_doc_type` OUT, pointing at one POSTED Stock Out.
- **Synonyms**: issue, pengeluaran bahan, Bukti Barang Keluar, retur dari produksi, RTP, return to warehouse, GOOD / SCRAP (afkir).
- **NOT the same as**: Purchase Return (`prt_*`, Procurement, goods back to the supplier); Stock Reversal (cancel of a wrong Stock Out, card `22_engine_stock-reversal.md`); Pick List Confirm (Warehouse), which CREATES Stock Out documents through the same engine.

## Facts
| Item | Content | Evidence |
|---|---|---|
| Classes allowed | Stock Out and Return exist for RM and SFG only. FG: `mount()` answers 404, and the v2 grid has no New/Open link for FG out/return (read-only list). FG leaves the company only by the Delivery Order, which writes no stock. | FACT, `stock-out.blade.php:79`; `stock-return.blade.php:82`; `StockDocGrid.php:99-126` |
| Stock Out header | Required: date, warehouse, "issued to" department (`issued_to_dept`); optional `process_code`, notes. Lines: item, bin (usable bins of the warehouse), quantity > 0, lot. `action_type` ISSUE. No Work Order is required: the form is manual ("v1 locked manual"); Pick List Confirm fills `wo_id` for its own documents. | FACT, `stock-out.blade.php:221-263`; `PickListService.php:296-300` |
| What Stock Out checks | Only what posting checks: open inventory period and no negative balance (card `20_engine_stock-posting.md`). It does NOT compare with Available (booked stock) or with the Work Order requirement. | FACT, `StockPostingService.php:99-103,175-180`; grep |
| Journal | A posted RM or SFG Stock Out posts the usage journal Dr cost of goods sold / Cr inventory (journal code USG), valued at the average price carried by the card row, grouped per account pair; lines without accounts are skipped with a warning. | FACT, `StockPostingService.php:199-201,273-319` |
| Return source | Only a POSTED, not cancelled Stock Out (`doc_cat` STOCK, `doc_type` -1) that still has a remainder (`qty` - `qty_returned` > 0). Picked in a "source" dialog; lines are copied with the returnable quantity. | FACT, `stock-return.blade.php:130-182` |
| Return rules | Date, source, destination warehouse for good material, at least one line, quantity > 0, bin per line. Quantity may not exceed the remainder ("Qty retur ... melebihi sisa yang boleh diretur"). Condition GOOD goes to a usable bin of the chosen warehouse; SCRAP goes to any bin with `is_usable`=0 and needs a note ("Keterangan wajib diisi utk baris afkir/SCRAP"). | FACT, `stock-return.blade.php:110-127,273-296` |
| Return value | `unit_price` of each return line = `avg_unitprice` of the source card row, so the material comes back at its issue value. Posting treats RETURN as an In (`qty_in` = absolute quantity, card `doc_type` IN, `value_in` = quantity x price). | FACT, `stock-return.blade.php:300-322`; `StockPostingService.php:131,141,164` |
| Remainder update | After a successful post, `qty_returned` of each source line is increased by the returned quantity, in a separate statement outside the posting transaction. | FACT, `stock-return.blade.php:359-370` |
| Journal for Return | None. The posting engine journals only OUT; "Adjustment / Opname / Return / BB are deliberately not journalled" (code comment). | FACT, `StockPostingService.php:32-41,187-190` |
| Cancel after posting | Stock Out and Return are reversible (`doc_cat` STOCK or RETURN, own screen, source MANUAL/OUT/blank). Pick List and Subcon Stock Outs must be cancelled from their own process. | FACT, `StockReversalService.php:29-32,53-63` |
| Data (mockup) | RM Stock Out documents (`doc_cat` STOCK, `doc_type` -1): 12 (8 DRAFT, of which 3 have `stat_confirm`=1; 4 POSTED). No Return document exists in RM, SFG or FG. | FACT, group-by query |

## Relations
- **source**: item master and bins, POSTED Stock Out (for a Return), accounts of item/category/part for the journal.
- **used_by**: posting engine, Stock Card and Recap reports (Out/In), Pick List and Subcon (which write Stock Out documents), WO material reconciliation view `v_inv_wo_reconcile`.
- **flows**: Stock Out Draft -> Confirm & Post (card out, usage journal) -> optional Return Draft -> Confirm & Post (card in, `qty_returned` on the source line).
- **frozen_zone**: none.

## Findings (recorded, not fixed)
1. **Return does not reverse the usage journal**: material issued is expensed (Dr COGS / Cr inventory) at Stock Out; the Return adds the quantity and value back into the card but posts no counter-journal, so the general ledger inventory balance falls below the cards by the returned value until a manual journal. The daily reconciliation (card `26_engine_repost-reconcile.md`) would flag the difference. Stated as intentional in a code comment, not as a decision by Jo in the documents read. FROM CODE; not run.
2. **SCRAP stays in inventory value**: SCRAP goes to a non-usable bin but is valued (owner COMPANY) and not written off, so it still counts in stock value until Obsolete Stock or an Adjustment. FROM CODE.
3. **`qty_returned` can be wrong after cancel**: nothing decreases `qty_returned` when a Return is cancelled by reversal (only `stock-return.blade.php` writes it), and `refusal()` does not block cancelling a Stock Out that already has returns, which would put the quantity back a second time. FACT (grep `qty_returned`; `refusal()` read in full).
4. **Remainder update is not atomic**: posting commits first, `qty_returned` is updated afterwards; a failure between the two leaves a posted Return with an unchanged remainder (the same quantity could be returned again). FROM CODE.
5. **No Available or WO check on Stock Out**: a manual Stock Out may take stock that a booking has reserved; only the no-negative rule protects. The Pick List path is the guarded one. FROM CODE.
6. **FG Stock Out and Return are menu items without function**: the menu offers them, the grid is read-only and the form answers 404. Intentional per a v1 comment ("FG leaves only through the Delivery Order"), but the Delivery Order does not deduct stock (card `10_card_stock-document.md` Findings 3). FACT.
7. **`issued_to_dept` type**: the form validates an integer department id, the column is `varchar(100)`; the grid shows the stored text. FACT (`show columns`, `stock-out.blade.php:222`).

## Already exists? Searched where
Searched: `stock-out.blade.php`, `stock-return.blade.php`, `RmStockD.qty_returned`, `StockPostingService` journal block, `PickListService`, `SubconStockService`, design notes 05 and 06 (FROM DOCUMENT: out needs a Work Order, return needs the Stock Out as reference; v1 deliberately simplified). The concept ALREADY EXISTS in simplified form.
> Label pass 2026-10-18 (Jo): the visible texts of the screens this card cites (labels, columns, tabs, buttons, messages) are English now. Tables, fields, flow and stored values are unchanged, so nothing else in this card changed. Verified: 2026-10-18.
- Re-checked 2026-10-19 after Stock In moved to the v2 form `inv-stock-in-form` (rule D.25, Master T5): `routes/web.php` replaced the v1 `/inventory/stock-in/{category}` route by a redirect on the same line (no line added or removed) and now also requires `routes/v2_stockdocs.php` on the line that requires `v2_finance.php`, `app/Support/V2/Screens.php` gained one group entry far below line 65, `app/Support/Menu/MenuRoutes.php` lost the three Stock In entries, `app/Support/V2/Lookups.php` gained the stock lookups and a REMARK block (cited lines shifted by 10 and corrected in the cards that cite them) and `app/Support/V2/Grid/Inv/StockDocGrid.php` gained the v2 form hook (cited lines corrected); this card does not depend on the changed lines and its content is still true.
