# Sub-module: Dummy Data and Migration

> Written 2026-10-17. This page only summarizes and links; facts are in the cards. Only the Master Card Dummy engine is documented so far; the other items of this sub-module have no card yet.

- **Module**: Setup (`SETUP`). **Sub-module**: Dummy Data & Migration (menu group `SET_GRP_DUMMY` in `sys_app_menu`, active). Items in that group today (FACT, menu query on the mockup DB): Dummy & Legacy Data (`v2:setup-data-origin`) and Migration (`v2:setup-migration`).
- **Purpose**: build and clean the test data of the dummy sites (`ddt*` databases, `data_origin=DUMMY`). The live site (`tm2*`) is not touched by these engines.
- **Rules**: dummy data starts at Inquiry and always carries a Master Card, artwork and dieline with clear size (P x L x T and flat size, mm) (`CLAUDE.md` section 1); real masters (machine, process, employee) are never given `data_origin='DUMMY'`; a wrong dummy row is deleted per row, never a total purge; frozen-zone flows are driven through the real services, not edited.
- **Frozen zone**: none owned here; the engine calls frozen flows (Inquiry, ProDev, ProCost, Quotation, CO) as a normal user would.

## Cards and engines in this sub-module
| Type | File | Content |
|---|---|---|
| ENGINE | `20_engine_master-card-dummy.md` | Master Card PDFs to leveled dummy sets (inquiry, prodev, procost, co, product), command and Developer screen, reset, findings |
| Concept | not written yet | `data_origin` and `DataOriginScope` (screen "Dummy & Legacy Data") |
| Concept / ENGINE | not written yet | Migration screen (`v2:setup-migration`), the `ddt:*` commands (`ddt:copy`, `ddt:copy-sys`, `ddt:stage`, `ddt:baseline`) |

## Relations
- **source**: Jo's Master Card PDFs in `docs/attachments/mastercard`; masters (customer, machine, plano, process); Sales services (Inquiry, ProDev, ProCost, Quotation, Customer Order).
- **used_by**: UAT on `mockup.test` (data for every module after Inquiry), Planning / PPIC (inherit the Master Card and the CO), the engine catalog.
- **tables written** (summary; the exact list per level is in the engine card): `prod_master_card`, `prod_master_card_file`, `iqp_*`, `quo_*`, `co_*`, `prod_product`, `prod_part`, `prod_layout_group`, only in `ddt*`.
- **neighbours**: Master Card and dieline concept card `docs/tg/sales/inquiry-product/14_card_dieline-and-master-card.md` (decision A.28: Master Card is one form; the engine fills the collection shape directly and the view shape only through the normal product birth).

## Diagram
Not made yet (a text twin is in the "Flow" section of the engine card).

## Existing User's Guide (UG)
None for this screen (searched `docs/guides` for "master card dummy" and "dummy:mastercard": only unrelated mentions in the Inquiry and ProDev guides). Jo's reference note: `docs/PATOKAN-DATA-DUMMY.md`.

## Cross-card findings (summary; details in the engine card)
The Master Card Dummy screen sits in the Developer menu, not in this group; reset leaves approval and trace rows behind and has no transaction; part of the chain gets `data_origin=LIVE` (quotation, delivery lines, layouts); products built through the chain levels show an empty knife code and print size in `vw_master_card`; a PDF with neither design number nor item code is always skipped.
