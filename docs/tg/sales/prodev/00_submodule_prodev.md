# Sub-module: Pro-Dev (Product Development)

> DRAFT GROUP C (2026-10-16). The sub-module page only summarises and links; facts are in the cards. Menu (FACT, `sys_app_menu` mockup, parent `SALES_PRODEVGRP` under Inquiry & Quotation): Pro-Dev New (`v2:prodev-new`), Pro-Dev Revise (`v2:prodev-revise`), Pro-Dev Manual (`v2:prodev`).

- **Module**: Sales > Inquiry & Quotation > Pro-Dev.
- **Purpose**: from the Inquiry, derive the production layout (plano, UP), BOP, BOM, Ink Configuration and Master Card; the officer decides (A.7). The result is the basis for Pro-Cost, then WO.
- **Frozen zone**: ProDev flow/data. Read: `docs/claude_vital_flows.md`, `docs/ENGINE-ATTENTION.md`.

## Relations
- **source**: Inquiry Product (part, finishing, material, qty tier, `stat_confirm_sales`), master `prod_type` (dieline), rules `prod_bop_rule`.
- **used_by**: Pro-Cost (CONFIRMED layout via `ProCostContextService`), Material Planning (UP from layout), WO creation (BOP/BOM).
- **flags**: `stat_confirm_prodev` in `iqp_inquiry` (A.20), recomputed after Save/Confirm layout.

## Engines in this sub-module
| Engine | Card | Short trigger |
|---|---|---|
| Imposition Engine | `10_engine_imposition.md` | v1 Imposition screen per Inquiry, Form WO (preview), Costing Advisor; the active ProDev does NOT use it |
| Spec Engine (BOP, BOM, Ink) | `20_engine_spec.md` | ProDev buttons/actions (generate, Save), WO creation and re-arrangement |
| Usage Rate Engine | `30_engine_usage-rate.md` | monthly schedule on day 1 at 02:00, button on the BOP Rule screen |
| Routing Template Engine | NOT written | not connected to ProDev according to the code; see `../90_findings_engine-group-c.md` |
| Inquiry <-> ProDev interface (concept card, other group) | `10_card_prodev-interface.md` | button Kirim ke ProDev, Save/Confirm layout |
| Dieline Engine | `../inquiry-product/20_engine_dieline.md` | Part & Dieline display (Inquiry and ProDev) |

## Diagram
`../img/imposition.svg`, `../img/spec-engine.svg` (text twin in each card, section "Flow").

## User's Guide (UG)
`docs/guides/sales/02_prodev.md` (FACT: file exists; content not read). TG does not repeat its content.

> Checked 2026-10-16 after the SpecEngine / MaterialPlanningEngine change (Jo agreed): this card only names or links the changed cards; nothing in it needs to change.

> Checked 2026-10-16 after the ProCost BOM panel change (Jo agreed): this card only names or links the changed cards; nothing in it needs to change.
