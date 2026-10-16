# Sub-module: Inquiry Product

> Group B, 2026-10-16. The sub-module page only summarises and links; table/column facts are in the cards. FACT = seen directly (menu: `sys_app_menu` mockup; code with lines).

- **Module**: Sales > Inquiry & Quotation > Inquiry Product (menu group id 1161). Menu (FACT, `sys_app_menu` mockup): Inquiry New (`v2:inquiry-list-new`, grid), Inquiry Revise (`v2:inquiry-revise`), Inquiry Recalc (`v2:inquiry-recalc`), heading "Reports" then All Inquiries (`v2:inquiry`). Document form: `v2:inquiry-new` (opened from the grid; not a menu item of its own). Item "Inquiry Note" (id 931) is inactive and has no target.
- **Purpose**: Sales records the customer request as a product specification (part, dieline size, material, finishing, qty tier), then Confirms; Inquiry becomes the upstream source of the whole chain (ProDev, ProCost, Quotation, CO).
- **Three types** (A.5): NEW, REVISE (spec changes), RECALC (same spec, material price changes). RECALC goes straight to Pro-Cost (A.20).
- **Frozen zone**: Inquiry Product, ProDev, ProCost. Read `docs/claude_vital_flows.md` before touching.

## Relations
- **source**: Sales (form), Master Card Collection, Duplicate, Master Product (Revise/Recalc), Reverse costing.
- **used_by**: ProDev (card `../prodev/10_card_prodev-interface.md`), ProCost (`../product-costing/11_card_procost-interface.md`), Quotation (`../quotation/10_card_quotation.md`).
- **flags**: three flags `stat_confirm_sales/_prodev/_procost` in `iqp_inquiry` (A.20). Cross-module flag cards are written by other groups; only a link here.

## Concept cards in this sub-module
| Card | Content |
|---|---|
| `10_card_product-type.md` | Product type (`prod_type`), created earlier |
| `11_card_inquiry-and-part.md` | Inquiry, Part, Qty tier, types NEW/REVISE/RECALC, flags |
| `12_card_inquiry-source.md` | Source (origin of the request) |
| `13_card_material-source.md` | Material Source (own material / customer-supplied) |
| `14_card_dieline-and-master-card.md` | Dieline, dieline snapshot, Master Card on the Sales side |
| `15_card_inquiry-supporting-masters.md` | Product Market, market sizes, material category, finishing, join type |
| `../master-data/10_card_customer.md` | Customer on the Sales side |
| `../master-data/11_card_sales-pic.md` | Sales PIC |

## Engines in this sub-module
No engine writes Inquiry itself. Engines that touch Inquiry data: `SpecEngine` (ProDev; writes back `iqp_part_process/_material/_ink`), `InquiryStage` (computes the flags). ENGINE cards: `20_engine_dieline.md` (engine group), `../prodev/20_engine_spec.md`; open items in `docs/ENGINE-ATTENTION.md`.

## Findings across cards (summary; detail in the cards)
- The Source choices do not include `MASTER CARD` and `REVERSE` that the code writes; the channel list has no master.
- `pic_sales` is free text; 0 of 19 match the Sales Person master.
- Customer target price per tier and discounts have no input on the v2 screen; column `iqp_tier.price_excl` has a double meaning.
- The next Inquiry number will jump because of seeder numbers (computed from code, not run).
- Finishing master, join type, product type have no input screen; constant `INQUIRY_ARTWORK_MAX_MB` has no row.

## Diagram
Not made yet (next Phase). The text flow is in the Inquiry card and the interface card.

## User's Guide (UG) already existing
`docs/guides/sales/01_inquiry_product.md`; data flow: `docs/guides/sales/00_alur_data_dan_sistem.md` (FROM DOCUMENT, not opened in this session). TG does not repeat its content.
