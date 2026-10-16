# Sub-module: Batch and Quality (Batch, QC sampling, Conditional Stock, First Article, Shortage, Subcontract, Customer Material)

> Written 2026-10-16. Verified: 2026-10-16. This page only summarizes and links; table/column facts are in the cards. All 82 dummy batches are Released; most QC paths have no dummy rows (see "Cross-card findings").

- **Module**: Inventory > Stock > Batch & Quality (`INV_BQ`), Subcontract (`INV_SUBCON`), Customer Material (`INV_CUSTMAT`). Menu (FACT, `sys_app_menu` mockup, active): Batch (`v2:inv-batch`), Conditional Stock (`v2:conditional-stock`), First Article (`v2:qc-first-article`), Shortage Request (`v2:supplier-shortage`), Setup: Sampling Plan (`v2:qc-sampling-plan`), Sampling Rules (`v2:qc-sampling-rules`), Risk Class (`v2:qc-risk-class`); Report: URGENT Usage (`v2:qc-urgent-usage`), Shortage by Supplier (`v2:shortage-by-supplier`), Supplier Claims (`v2:supplier-claims`); Send to Subcontractor (`v2:subcon-send`), Stock at Vendor (`v2:stock-at-vendor`), Customer Material (`v2:customer-material`).
- **Purpose**: every received lot becomes a batch with a quality status; incoming lots are sampled by risk; a decision releases, holds or rejects them; stock that is not ours (subcontract, customer material) keeps its owner.
- **Frozen zone**: none of this sub-module (the frozen zone is Sales/ProDev/ProCost flows); it only reads them.
- **Rules**: the stock-posting rules in [../stock/](../stock/) apply (no negative stock). Design intent: `docs/design-notes/inventory/` (FROM DOCUMENT, not checked).

## Relations
- **source**: Purchase Receive (receipt creates the batch), Stock Posting (`../stock/20_engine_stock-posting.md`), master raw material and risk class.
- **used_by**: Stock Card (`batch_id` on card rows), Warehouse pick/putaway (batch status flags), Work Order holds, supplier claims, Procurement (shortage and claim by supplier).
- **flags**: batch status (Released / Conditional / Quarantine / Rejected, see the batch card), not `stat_confirm`.

## Cards and engines in this sub-module
| Type | File | Content |
|---|---|---|
| Concept | `10_card_batch-master.md` | batch master and status model |
| Concept | `11_card_conditional-stock-and-urgent.md` | Conditional Stock, URGENT receipt |
| Concept | `12_card_qc-sampling-setup.md` | Risk Class, Sampling Rules, Sampling Plan |
| Concept | `13_card_qc-decision-and-first-article.md` | QC decision, First Article |
| Concept | `14_card_supplier-claim-and-trace.md` | supplier claim, WO hold, forward trace |
| Concept | `15_card_shortage-request.md` | Shortage Request |
| Concept | `16_card_subcontract-stock.md` | Send to Subcontractor, Stock at Vendor |
| Concept | `17_card_customer-material.md` | Customer Material (titipan) |
| Concept | `18_card_qc-authority-and-notices.md` | who may do what, bell notices |
| ENGINE | `20_engine_batch-service.md` | `BatchService` |
| ENGINE | `21_engine_batch-backfill.md` | `BatchBackfillService` |
| ENGINE | `22_engine_qc-sampling.md` | `QcSamplingService` |
| ENGINE | `23_engine_qc-decision.md` | `QcDecisionService` |
| ENGINE | `24_engine_qc-escalation.md` | `QcEscalationService` |
| ENGINE | `25_engine_qc-trace.md` | `QcTraceService` |
| ENGINE | `26_engine_urgent-receipt.md` | `UrgentReceiptService` |
| ENGINE | `29_engine_shortage-auto-detector.md` | `ShortageAutoDetector` |
| ENGINE | not yet written | shortage request, subcontract stock and customer material services (covered inside concept cards 15-17) |

## Diagram
Not yet made.

## Existing user guides (UG)
`docs/guides/inventory/30_batch_dan_status_mutu.md`, `31_sampling_qc_dan_keputusan.md`, `32_kekurangan_kirim_dan_klaim_supplier.md`, `33_subkon_dan_material_titipan.md`.

## Cross-card findings (short; details in the cards)
A Work Order "hold" is written but no production/picking/delivery code reads it; groups `QA_HEAD`, `WH_HEAD`, `PROD_SPV` are used by code but missing from `sec_group`; 8 orphan OPEN supplier claims in the dummy; Stock Transfer writes no `batch_id` or owner on the new card (customer-owned stock can turn into company stock); QC decisions are accepted on any status; no code creates status Quarantine; the shortage Confirm step spans several databases without one transaction.
