# Concept card: Who may do what in QC / URGENT / shortage, and the bell notices

> Written 2026-10-16 (group Inventory, Batch & Quality). FACT = mockup DB (`ddtsys`, same result in `tm2sys`) and code with line numbers, seen this session; no test was run. Verified: 2026-10-16.

- **Document type**: concept card (rights matrix). **Module/sub-module**: Inventory > Batch & Quality, Purchasing Goods Receipt (URGENT), Production Planning (PPIC).
- **Canonical name**: `QcAuthority` (one place for the rights) and `QcNotifier` (one place for the notices). Rights are group codes in `sec_group`; "Ops Manager and above" means admin, group `OPS_MGR` or `DIREKSI`, or management level >= 4.
- **Synonyms**: authority matrix, who decides, hak memutuskan, QA head (Kepala QC), warehouse head (Kepala Gudang), bell popup, `sys_notifications`.
- **NOT the same as**: menu/module access (`sec_*` module grants) and the approval Flow Engine (CO, PO, Transfer); QC rights are NOT routed through the Flow Engine.

## Facts
| Action | Allowed for | Group constant | Checked where | Evidence |
|---|---|---|---|---|
| Mark / clear URGENT on a receipt line | PPIC, production supervisor, production manager, Ops Manager+ | `URGENT_MARK` = `PROD_PLANNING`, `PROD_SPV`, `PROD_MGR` | `UrgentReceiptService::mark/unmark`; also re-checked at posting for whoever marked | FACT, `app/Services/Inventory/QcAuthority.php:15`; `UrgentReceiptService.php:40-62,105-123` |
| Approve URGENT FSC / PEFC / food-grade line | Head of QC, Ops Manager+ | `URGENT_APPROVE` = `QA_HEAD` | `UrgentReceiptService::approve` | FACT, `QcAuthority.php:18`; `UrgentReceiptService.php:77-91` |
| Record a quality decision, First Article, and edit a claim status | QA, head of QC, Ops Manager+ | `QC_DECIDE` = `QA`, `QA_HEAD` | The ROUTE (`v2.iq.decide`, `v2.iq.first-article`, `v2.iq.claim`), not the service | FACT, `QcAuthority.php:21`; `routes/v2_quality.php:33,75,85` |
| Recount a suspected shortage | Warehouse head / inventory manager, Ops Manager+ | `RECOUNT` = `MGR_INV`, `WH_HEAD` | `ShortageRequestService::recount` | FACT, `QcAuthority.php:24`; `ShortageRequestService.php:78` |
| Confirm or dismiss a shortage | PPIC, production manager, Ops Manager+ | `SHORTAGE_CONFIRM` = `PROD_PLANNING`, `PROD_MGR` | `ShortageRequestService::confirm/dismiss` | FACT, `QcAuthority.php:27`; `ShortageRequestService.php:98,109` |
| Send, Receive, Refuse, Close a shortage; edit claim status | Purchasing manager, any user with module `PROC`, Ops Manager+ | `SHORTAGE_FOLLOW_UP` = `MGR_PUR` (+ module `PROC`) | `ShortageRequestService::assertFollowUp` | FACT, `QcAuthority.php:30,62-67`; `ShortageRequestService.php:242-247` |
| Create a shortage request, send/return/use/cancel subcon material, maintain Setup grids, edit a Batch | Anyone who can open the screen / module `INV` | none | route group `module:INV` only (the shortage routes have no module gate) | FACT, `routes/v2_quality.php:122`; `routes/v2_stockflow.php:19` |

| Item | Content | Evidence |
|---|---|---|
| Groups that exist | `sec_group` (mockup and tm2, same): `DIREKSI`, `MGR_INV`, `MGR_PUR`, `OPS_MGR`, `PROD_MGR`, `PROD_PLANNING`, `QA`. Missing: `QA_HEAD`, `WH_HEAD`, `PROD_SPV` (the code comment says they are recognised as soon as Jo creates them in Setup > Security). | FACT, queries on `ddtsys.sec_group` and `tm2sys.sec_group`; `QcAuthority.php:8` |
| How a notice is sent | `QcNotifier::toGroups(groups, title, message, type)` writes one row per ACTIVE member of those groups to `sys_notifications` (the bell). The same title + message is never sent twice to the same user. Admins are not notified automatically ("so it is not noisy"). | FACT, `app/Services/Inventory/QcNotifier.php:16-35` |
| Notices in this area | "Recount needed" (to `MGR_INV`/`WH_HEAD`, new request), "Shortage to confirm" (to PPIC after recount), "Shortage to follow up" (to Purchasing + PPIC, type error, after confirm), "Quality decision escalated" (4 h) and "Quality decision overdue" (8 h). | FACT, `ShortageFollowUp.php:71-86`; `QcEscalationService.php:29` |
| Dedup | The check is the same title + message text for the same user, forever (not per day). An escalation text names the batch, item and status, so it is sent once per stage; a request notice carries the request number, so each request gets its own. | FACT, `QcNotifier.php:20-22` |

## Relations
- **source**: `sec_group`, `sec_user`/group membership, `SecUser::isOpsManagerOrAbove`, module access (`canAccessModule('PROC')`).
- **used_by**: `UrgentReceiptService`, `QcDecisionService` callers (routes), `ShortageRequestService`, `QcEscalationService`, screens (to hide buttons).
- **flows**: see the Conditional Stock, QC decision and Shortage cards.
- **frozen_zone**: security groups are Setup data (Jo); do not create groups from code.

## Findings (recorded, not fixed)
1. **Three groups the code depends on do not exist** (`QA_HEAD`, `WH_HEAD`, `PROD_SPV`). Effects today: the head-of-QC approval and first escalation reach only Ops Manager+, the warehouse recount reaches only `MGR_INV`, and nobody can be a production supervisor marker. FACT (query); recorded in the card of URGENT too.
2. **Admin is always allowed but never notified.** An admin can decide or approve, but receives no bell. FACT, `SecUser::hasGroup` returns true for admin; `QcNotifier::recipients` queries group membership only.
3. **Rights live in the routes for QC decisions** (the service says so on purpose). A new caller of `QcDecisionService::record` (job, API) has no check unless it adds one. FACT, `QcDecisionService.php:9` comment.
4. **The rights matrix is not visible to users:** it is not shown anywhere in Setup; the only way to know it is the screen message or this card. FACT (no screen found; grep `QcAuthority` in `resources/` returns only checks).
5. **Escalation recipients include `PROD_PLANNING` and `QA`** at the overdue stage (`QcEscalationService.php:16`), the only stage that works today with the missing groups. FACT.

## Already exists? Searched where
Searched `QcAuthority`, `QcNotifier`, `sec_group`, `sys_notifications`. Result: ALREADY EXISTS. FROM DOCUMENT: D16 / O-15 (scope section 1, `docs/globaluserrules.md` line 269).
