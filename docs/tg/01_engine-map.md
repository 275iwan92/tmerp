# ENGINE map (Phase 3)

> Generated 2026-10-16 from `app/Support/Engines/EngineRegistry.php` (61 registered engines), `routes/console.php` and a grep for callers. Verification status: **SCHEDULED** = proven in `routes/console.php` and its command uses the engine class (FACT, seen this session). **Screen/route** = only a class-name grep in `resources/views`, `app/Support/V2`, `routes`; not yet mapped to a button/row, so it must not be read as 'certain trigger'. Full cards exist only for 10 Sales engines; the rest wait for their module (Phase 5). The status of open items stays in `docs/ENGINE-ATTENTION.md`.

## Engines without a menu (background)
Only 10 engines run on a schedule (server time, `php artisan schedule:run`):

| Time | Command | Engine |
|---|---|---|
| day 1, 02:00 | `prodev:usage-rates` | Usage Rate Engine |
| 01:30 | `planning:run` | Material Planning Engine |
| 01:45 | `inventory:booking-maintain` | Booking Maintenance |
| 02:00 + shift start | `ppic:auto-plan` | PPIC Auto-Planner |
| 03:00 | `production:suggest-speed-targets` | Speed Target Engine |
| 03:30 | `ppic:learn` | Learning Engine (changeover) |
| 03:45 | `sequence:train` | Job Order Learning (Sequence) |
| 05:00 | `inv:count-due` | Count Task Service |
| 05:15 | `inv:detect-shortage` | Shortage Auto Detector |
| 06:00 | `maintenance:generate-pm` | Preventive Plan Engine |

Other schedules in `routes/console.php` without a registered engine: `recruitment:sync-portal` (every 2 minutes), `assistant:prune` (02:30), `maintenance:accumulate-meters` (05:30), `inv:reconcile` (04:00), `inv:qc-escalate` (every hour). Whether these commands use another engine: NOT checked.

## Trigger per engine

### Inquiry Product

| Key | Engine | Mode | Trigger | TG card |
|---|---|---|---|---|
| `dieline` | Dieline Engine (TMDieline) | calc | Three code copies (JS and PHP port), see card | [card](./sales/inquiry-product/20_engine_dieline.md) |

### Pro-Dev

| Key | Engine | Mode | Trigger | TG card |
|---|---|---|---|---|
| `imposition` | Imposition Engine | suggest | Screen/route: 6 files refer to this class (FROM class-name grep; button/row not yet mapped) | [card](./sales/prodev/10_engine_imposition.md) |
| `spec` | Spec Engine (BOP · BOM · Ink) | suggest | Screen/route: 3 files refer to this class (FROM class-name grep; button/row not yet mapped) | [card](./sales/prodev/20_engine_spec.md) |
| `usage-rate` | Usage Rate Engine | suggest | SCHEDULED: `prodev:usage-rates`, on day 1 of each month at 02:00 | [card](./sales/prodev/30_engine_usage-rate.md) |
| `routing` | Routing Template Engine | suggest | Screen/route: 1 file refers to this class (FROM class-name grep; button/row not yet mapped) | not yet |

### Pro-Cost

| Key | Engine | Mode | Trigger | TG card |
|---|---|---|---|---|
| `costing` | Costing Engine (Pro-Cost) | calc | Screen/route: 4 files refer to this class (FROM class-name grep; button/row not yet mapped) | [card](./sales/product-costing/20_engine_costing-engine.md) |
| `costing-advisor` | Costing Advisor (fills the boxes, explains the price, learns) | suggest | Screen/route: 3 files refer to this class (FROM class-name grep; button/row not yet mapped) | [card](./sales/product-costing/10_engine_costing-advisor.md) |
| `reverse-costing` | Reverse Costing | auto | Screen/route: 1 file refers to this class (FROM class-name grep; button/row not yet mapped) | [card](./sales/product-costing/30_engine_reverse-costing.md) |

### Approval

| Key | Engine | Mode | Trigger | TG card |
|---|---|---|---|---|
| `flow` | Flow Engine (Approval) | flow | Screen/route: 12 files refer to this class (FROM class-name grep; button/row not yet mapped) | not yet |

### Sales Order

| Key | Engine | Mode | Trigger | TG card |
|---|---|---|---|---|
| `cpdel` | CPDEL Engine (Customer Plan Delivery) | auto | Screen/route: 7 files refer to this class (FROM class-name grep; button/row not yet mapped) | [card](./sales/order/20_engine_co-order-builder.md) |
| `cpdel-promise` | CPDEL Two Promises | flow | Screen/route: 4 files refer to this class (FROM class-name grep; button/row not yet mapped) | [card](./sales/order/21_engine_cpdel-promise.md) |

### Planning

| Key | Engine | Mode | Trigger | TG card |
|---|---|---|---|---|
| `material-planning` | Material Planning Engine (MPS · MRP · Booking) | suggest | SCHEDULED: `planning:run`, daily 01:30; also a screen | [card](./sales/order/30_engine_material-planning.md) |
| `booking-maintenance` | Booking Maintenance (hold and stale booking) | auto | SCHEDULED: `inventory:booking-maintain`, daily 01:45 (if column `rm_booking.hold_until` exists) | not yet |

### Inventory

| Key | Engine | Mode | Trigger | TG card |
|---|---|---|---|---|
| `warehouse-stock` | Warehouse Stock (bins, capacity, dead stock) | calc | Screen/route: 8 files refer to this class (FROM class-name grep; button/row not yet mapped) | not yet |
| `stock-posting` | Stock Document Posting (stock card order) | flow | Screen/route: 5 files refer to this class (FROM class-name grep; button/row not yet mapped) | not yet |
| `stock-transfer` | Stock Transfer Posting | flow | Screen/route: 6 files refer to this class (FROM class-name grep; button/row not yet mapped) | not yet |
| `batch` | Batch Service (batch master and quality status) | flow | Screen/route: 3 files refer to this class (FROM class-name grep; button/row not yet mapped) | not yet |
| `batch-backfill` | Batch Backfill (batches from old lot data) | auto | Manual command `inv:backfill-batch` (not scheduled) | not yet |
| `putaway-planner` | Putaway Planner (suggests a bin) | suggest | Screen/route: 3 files refer to this class (FROM class-name grep; button/row not yet mapped) | not yet |
| `warehouse-task` | Warehouse Task Service (putaway tasks) | flow | Screen/route: 6 files refer to this class (FROM class-name grep; button/row not yet mapped) | not yet |
| `pick-allocator` | Pick Allocator (which pallet to take) | suggest | Screen/route: 2 files refer to this class (FROM class-name grep; button/row not yet mapped) | not yet |
| `pick-list` | Pick List Service | flow | Screen/route: 3 files refer to this class (FROM class-name grep; button/row not yet mapped) | not yet |
| `qc-sampling` | QC Sampling Service (sampling plan) | suggest | Screen/route: 5 files refer to this class (FROM class-name grep; button/row not yet mapped) | not yet |
| `qc-decision` | QC Decision Service | flow | Screen/route: 8 files refer to this class (FROM class-name grep; button/row not yet mapped) | not yet |
| `qc-trace` | QC Trace Service (forward trace of a rejected batch) | flow | Screen/route: 4 files refer to this class (FROM class-name grep; button/row not yet mapped) | not yet |
| `stock-reversal` | Stock Reversal Service (cancel a posted stock document) | flow | Screen/route: 1 file refers to this class (FROM class-name grep; button/row not yet mapped) | not yet |
| `count-task` | Count Task Service (cycle count) | flow | SCHEDULED: `inv:count-due`, daily 05:00; also a screen | not yet |
| `subcon-stock` | Subcontract Stock Service (material at the vendor) | flow | Screen/route: 3 files refer to this class (FROM class-name grep; button/row not yet mapped) | not yet |
| `customer-material` | Customer Material Service (consignment) | flow | Called: SpecialReceiptService | not yet |
| `virtual-location` | Virtual Location Service (Loss, In Transit, vendor bins) | auto | Called by another engine: StockTransferService, SubconStockService, BinGenerator | not yet |
| `pallet-labels` | Pallet Labels (arrival label, relabel after use) | suggest | Screen/route: 7 files refer to this class (FROM class-name grep; button/row not yet mapped) | not yet |
| `stock-ledger` | Stock Period Ledger | calc | Screen/route: 2 files refer to this class (FROM class-name grep; button/row not yet mapped) | not yet |

### Procurement

| Key | Engine | Mode | Trigger | TG card |
|---|---|---|---|---|
| `urgent-receipt` | Urgent Receipt Service | flow | Screen/route: 3 files refer to this class (FROM class-name grep; button/row not yet mapped) | not yet |
| `shortage-detector` | Shortage Detector (short delivery found late) | suggest | Screen/route: 2 files refer to this class (FROM class-name grep; button/row not yet mapped) | not yet |
| `shortage-request` | Shortage Request Service (send us the shortfall) | flow | Screen/route: 5 files refer to this class (FROM class-name grep; button/row not yet mapped) | not yet |
| `shortage-auto-detector` | Shortage Auto Detector (from production output) | suggest | SCHEDULED: `inv:detect-shortage`, daily 05:15 (if table `inv_shortage_request` exists) | not yet |
| `pq-matcher` | Purchase Quotation Matcher | suggest | Screen/route: 2 files refer to this class (FROM class-name grep; button/row not yet mapped) | not yet |

### Production

| Key | Engine | Mode | Trigger | TG card |
|---|---|---|---|---|
| `wo-generation` | Work Order Generation | auto | Screen/route: 6 files refer to this class (FROM class-name grep; button/row not yet mapped) | not yet |
| `pdr` | PDR Engine (Scheduling) | suggest | Screen/route: 1 file refers to this class (FROM class-name grep; button/row not yet mapped) | not yet |
| `scheduling-rules` | Scheduling Rule Engine | calc | Called by another engine: PdrEngine | not yet |
| `sequence-learning` | Job Order Learning (Sequence) | suggest | SCHEDULED: `sequence:train`, daily 03:45 (if table `prd_sequence_model` exists); also a screen | not yet |
| `interruption` | Hot Job Option Generator | suggest | Screen/route: 1 file refers to this class (FROM class-name grep; button/row not yet mapped) | not yet |
| `speed-target` | Speed Target Engine | suggest | SCHEDULED: `production:suggest-speed-targets`, daily 03:00 | not yet |
| `ppic-auto-plan` | PPIC Auto-Planner | suggest | SCHEDULED: `ppic:auto-plan --trigger=DAILY` 02:00 and `--trigger=SHIFT` (shift start), if `$ppicReady`; also a screen | not yet |
| `ppic-schedule-solver` | Schedule Solver (finite capacity) | calc | Called by another engine: AutoPlanRunner, PlanChangeService | not yet |
| `ppic-setup-time` | Setup Time Engine | calc | Screen/route: 2 files refer to this class (FROM class-name grep; button/row not yet mapped) | not yet |
| `ppic-gates` | Readiness Gates | auto | Screen/route: 2 files refer to this class (FROM class-name grep; button/row not yet mapped) | not yet |
| `ppic-wo-enrich` | Work Order Enrichment | suggest | Screen/route: 1 file refers to this class (FROM class-name grep; button/row not yet mapped) | not yet |
| `ppic-plan-control` | Plan Confirm and Change | flow | Called: PpicController, AutoPlanRunner, GateService | not yet |
| `ppic-operator-timer` | Operator Timer | flow | Screen/route: 3 files refer to this class (FROM class-name grep; button/row not yet mapped) | not yet |
| `ppic-learning` | Learning Engine (changeover) | auto | SCHEDULED: `ppic:learn`, daily 03:30 (if `$ppicReady`); also a screen | not yet |

### Maintenance

| Key | Engine | Mode | Trigger | TG card |
|---|---|---|---|---|
| `maintenance-plan` | Preventive Plan Engine | auto | SCHEDULED: `maintenance:generate-pm`, daily 06:00; also a screen | not yet |
| `maintenance-order` | Maintenance Order Lifecycle | flow | Screen/route: 4 files refer to this class (FROM class-name grep; button/row not yet mapped) | not yet |
| `maintenance-request` | Maintenance Request Engine | suggest | Screen/route: 3 files refer to this class (FROM class-name grep; button/row not yet mapped) | not yet |
| `maintenance-parts` | Maintenance Spare Part Supply | auto | Screen/route: 2 files refer to this class (FROM class-name grep; button/row not yet mapped) | not yet |
| `maintenance-kpi` | Maintenance KPI | calc | Screen/route: 2 files refer to this class (FROM class-name grep; button/row not yet mapped) | not yet |
| `maintenance-block` | Maintenance Machine Hold (Scheduling) | calc | Screen/route: 2 files refer to this class (FROM class-name grep; button/row not yet mapped) | not yet |

### Finance

| Key | Engine | Mode | Trigger | TG card |
|---|---|---|---|---|
| `tax` | Tax Engine | calc | Screen/route: 3 files refer to this class (FROM class-name grep; button/row not yet mapped) | not yet |

### HR & Payroll

| Key | Engine | Mode | Trigger | TG card |
|---|---|---|---|---|
| `payroll` | Payroll Engine | calc | Screen/route: 2 files refer to this class (FROM class-name grep; button/row not yet mapped) | not yet |

### Help

| Key | Engine | Mode | Trigger | TG card |
|---|---|---|---|---|
| `assistant` | Assistant Engine (offline) | auto | Trigger NOT found in `app/` (class of package `Assistant`); NOT checked further | not yet |

### Dummy Data

| Key | Engine | Mode | Trigger | TG card |
|---|---|---|---|---|
| `master-card-dummy` | Master Card Dummy Engine (PDF to leveled dummy data) | auto | Manual command `dummy:mastercard --env=mockup` and the Developer screen `v2/dummy/mastercard` (buttons Make Master Product, Make dummy data, Reset); ddt* databases only (FROM CODE: `assertDummy`) | not yet |

## Findings (recorded, not fixed)
1. RESOLVED 2026-10-16: Jo decided to include `MasterCardDummyEngine`; registered as key `master-card-dummy`, `engines.json` regenerated, `EngineCatalogTest` 6/6 pass.
2. Registry: 61 keys but 60 classes; key `dieline` has no class (three code copies). `assistant` has no caller in `app/`.
3. `ppic:auto-plan`, `ppic:learn` only run if `$ppicReady` is true; that condition was not read this session.
4. Engines without a TG card: 51 of 61. Registry vs code findings for the 10 Sales engines: `sales/90_findings_engine-group-c.md`.

## Diagram
[img/engine-map.svg](./img/engine-map.svg), text twin = the schedule table above.
