# Sub-module: Product Costing (Pro-Cost)

> The sub-module page only summarizes and links; table/column facts are in the cards. Updated 2026-10-16 (Phase 2).

- **Module**: Sales > Inquiry & Quotation > Pro-Cost. Menu (FACT, `sys_app_menu` mockup): Pro-Cost New, Revise, Recalc, Instant (`v2:sales-procost*`).
- **Purpose**: calculate HPP and selling price per qty tier from Inquiry + ProDev; costing is confidential (A.8-A.9), publish = declassification to Sales.
- **Frozen zone**: ProCost flow/data. Read: `docs/claude_vital_flows.md`, `docs/ENGINE-ATTENTION.md`.

## Relations
- **source**: Inquiry Product (BOM, part, `stat_confirm_sales`), ProDev (layout, BOP waste, `stat_confirm_prodev`), rate masters (`prod_part_template`, `mat_grammage`), COSTING constants.
- **used_by**: Quotation (published price, must be integrated A.1), then Customer Order.
- **flags**: `stat_confirm_procost` in `iqp_inquiry` (A.20).

## Cards and engines
| Content | File |
|---|---|
| ProCost interface (Sales side) | `11_card_procost-interface.md` |
| Costing Advisor | `10_engine_costing-advisor.md` |
| Costing Engine (price formula) | `20_engine_costing-engine.md` |
| Reverse Costing | `30_engine_reverse-costing.md` |
| Quotation (price recipient) | `../quotation/10_card_quotation.md` |

## Diagram
`../img/costing-advisor.svg`, `../img/costing-engine.svg` (text twin is in the cards, section "Flow").

## Existing User's Guide (UG)
`docs/guides/sales/03_procost.md` (section guide). The TG does not repeat its content.

> Checked 2026-10-16 after the SpecEngine / MaterialPlanningEngine change (Jo agreed): this card only names or links the changed cards; nothing in it needs to change.

> Checked 2026-10-16 after the ProCost BOM panel change (Jo agreed): this card only names or links the changed cards; nothing in it needs to change.
