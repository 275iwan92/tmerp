# Sub-module: Requisition (Purchase Requisition and its approval)

> Written 2026-10-17 (group Procurement). This page only summarizes and links; table / column facts are in the cards.

- **Module**: Procurement > Requisition (`PROC_GRP_REQUISITION`). Menu (FACT, `sys_app_menu` mockup, active): Purchase Requisition (PR) (`v2:purchase-requisition`, form), PR Approval Center (`v2:approval-pr`, grid), group Report with Outstanding PR (`v2:pur-rpt-outstanding-pr`).
- **Purpose**: turn a need for material, service or spare parts into an approved Purchase Requisition whose lines are allocated to suppliers and then ordered (Order sub-module).
- **Frozen zone**: none of the Sales documents. The Goods Receipt / PO / GR-IR flow downstream is frozen (Receive). Statuses of documents change only with Jo's approval.
- **Rules**: A.11 (PR = Operational Manager; see the approval card for the PO exception), A.8-A.9 (supplier prices are confidential, shown only after the johiwan-only marker in the cards that hold them), D.24.

## Relations
- **source**: Material Planning (Create PR, Buy), ProDev (material shortage, subcontract step), Maintenance (spare parts), Shortage Request (additional PR), the PR screen itself.
- **used_by**: Order sub-module (Purchase Quotation compares per PR line, Purchase Order takes the allocations of APPROVED PRs), Material Planning and PPIC (open PR = incoming supply), Outstanding PR report.
- **flags**: `pr_requisition.status` (+ `stat_cancel`, never set); line progress in `pr_line.c_line_status`.

## Cards and engines in this sub-module
| Type | File | Content |
|---|---|---|
| Concept | `10_card_purchase-requisition.md` | `pr_requisition`, `pr_line`, `pr_line_alloc`, statuses, edit rules |
| Concept | `11_card_procurement-approval.md` | flow rules of PR, PO, Cash PO and Return; Approval Centers |
| Concept | `12_card_pr-sources-and-buy.md` | the eight creators of a PR including Material Planning Buy |
| ENGINE | not written | `PrAllocationService` (covered in the PR card; not in the engine registry) |

## Diagram
Not made yet (the flow is a text line in the PR card, "flows").

## Existing User's Guide (UG)
None for Procurement (`docs/guides` has inventory, production and sales). Old design documents `docs/md_for_systems/procurement_tier1.md`, `procurement_tier2.md`, `procurement_tier3.md` and `docs/backlog04_procurement.md` are FROM DOCUMENT; only the claims quoted in the cards were checked.

## Cross-card findings (summary; details in the cards)
The PR header never moves past APPROVED although its lines become FULLY_ORDERED; PR and PO have no Cancel and a rejected document is a dead end; PO rule A.11 lists two levels while the data has three above 1,000,001; `mat_raw.suggest_supplier_id` is empty for all materials, so Buy cannot make a PO; PR2600009 is APPROVED without any approval trace.
