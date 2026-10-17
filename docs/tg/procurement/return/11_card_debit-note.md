# Concept card: Debit Note to supplier (dn_debit_note)

> Written 2026-10-17 (group Procurement). FACT = mockup DB (`--env=mockup`, `tm2pur` = `ddtpur`) and code with line numbers seen in this session. The table has **0 rows** and no screen of its own; nothing was run. Verified: 2026-10-17.

- **Document type**: concept card. **Module/sub-module**: Procurement > Return (no menu item; the document is created inside the Shortage Request flow).
- **Canonical name**: Debit Note = `tm2pur.dn_debit_note`, a simple Draft document asking a supplier (or subcontractor) to credit us for goods that were invoiced or received but never delivered. Number `DN` + 2-digit year + 5 digits.
- **Synonyms**: DN, debit note, nota debet, nota potongan kurang kirim.
- **NOT the same as**: Purchase Return (physical goods back, card `10_card_purchase-return.md`) and its credit note request; the AR/AP debit or credit notes of Finance (not checked here).

## Facts
| Item | Content | Evidence |
|---|---|---|
| Table | `dn_debit_note`: `dn_id`, `doc_no` UNIQUE, `i_doc_date`, `i_supplier_id`, `pv_id`, `po_id`, `shortage_id` (the request it came from), `i_reason`, `i_qty`, `i_uom`, `i_unit_price`, `c_amount`, `status` varchar(10), `notes`, audit columns. 0 rows. The model guards only `dn_id`. | FACT, `show columns`, count; `DnDebitNote.php` |
| How it is born | Only `ShortageFollowUp::debitNote`, called when a shortage request is refused or its sending window lapses (and the source is not CUSTOMER): quantity = shortage in stock unit x (purchase unit / stock unit ratio of the receipt line), price = the receipt line price, `c_amount` = quantity x price rounded to 4 decimals, `status` DRAFT, reason "Shortage <no> not delivered: WO <no>"; the request gets `debit_note_id`. | FACT, `ShortageFollowUp.php:52-68`; call site `app/Services/Pur/ShortageRequestService.php:159` (in `refuse`) |
| What can be done with it | Nothing in the application: no screen, no list, no print, no approval, no journal; the class comment says "belum ada fitur debit note pembelian" and "jurnalnya belum dibuat". `status` is written only as DRAFT. | FACT, grep `DnDebitNote` / `dn_debit_note` in `app`, `resources`, `routes` (only the model, `ShortageFollowUp`, `ShortageRequestService` and the migration `2026_10_09_000800_inventory_shortage_followup.php`) |
| Where it is counted | The Shortage by Supplier report counts requests with `debit_note_required`, not rows of this table. | FACT, [Shortage Request card](../../inventory/batch-quality/15_card_shortage-request.md) (report row) |

## Relations
- **source**: a refused or lapsed Shortage Request (Inventory flow, see "See also"), the receipt line price (`../receive/10_card_purchase-receive.md`).
- **used_by**: nothing reads it yet.
- **flows**: refuse / lapse -> Draft DN -> (end).
- **frozen_zone**: the shortage flow is a v2.01 rule and the receipt / GR-IR flow is frozen; a journal for the DN would touch GR/IR, so ask Jo.

## Findings (recorded, not fixed)
1. **The document is a dead end**: created as Draft, never shown, approved, printed, sent or journalled. A refused shortage therefore leaves the money claim only in `inv_shortage_request.debit_note_required` and in this unseen row. FACT.
2. **`status` has width 10 and only the value DRAFT exists**; no lifecycle (Confirmed / Cancelled / Closed) as CLAUDE.md section 3 asks. FACT.
3. **Duplicates of the idea**: the Purchase Return has its own credit-note fields (`prt_return.cn_supplier_no`, `cn_received_date`), the Supplier Claim of Inventory (`inv_supplier_claim`) is a third place where a supplier is asked for compensation. Whether they should be one document was not decided (no source found). FACT for the three places.

## See also (other authors, not repeated)
[Shortage Request](../../inventory/batch-quality/15_card_shortage-request.md), [Supplier claim and trace](../../inventory/batch-quality/14_card_supplier-claim-and-trace.md).

## Already exists? Searched where, result
`concept:find "debit note"`: TG cards (Inventory: supplier claim, shortage request, auto detector); columns `dn_debit_note.*`, `inv_shortage_request.debit_note_required`, `inv_shortage_request.debit_note_id`; file `DnDebitNote.php`; help topic `guide-shortage-documents`. Result: ALREADY EXISTS as a table and a model with one creator and no screen; a new debit note table would be a duplicate.
