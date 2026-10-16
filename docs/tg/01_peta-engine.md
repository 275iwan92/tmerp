# Peta ENGINE (Fase 3)

> Dibangkitkan 2026-10-16 dari `app/Support/Engines/EngineRegistry.php` (60 engine terdaftar), `routes/console.php` dan grep pemanggil. Status verifikasi: **JADWAL** = terbukti di `routes/console.php` dan command-nya memakai kelas engine (FAKTA, dilihat sesi ini). **Layar/rute** = hanya grep nama kelas di `resources/views`, `app/Support/V2`, `routes`; belum dipetakan ke tombol/baris, jadi tidak boleh dibaca sebagai 'pemicu pasti'. Kartu lengkap baru ada untuk 10 engine Sales; sisanya menunggu modulnya (Fase 5). Status butir terbuka tetap di `docs/ENGINE-ATTENTION.md`.

## Engine tanpa menu (background)
Hanya 10 engine yang jalan terjadwal (jam server, `php artisan schedule:run`):

| Jam | Command | Engine |
|---|---|---|
| tgl 1, 02:00 | `prodev:usage-rates` | Usage Rate Engine |
| 01:30 | `planning:run` | Material Planning Engine |
| 01:45 | `inventory:booking-maintain` | Booking Maintenance |
| 02:00 + awal shift | `ppic:auto-plan` | PPIC Auto-Planner |
| 03:00 | `production:suggest-speed-targets` | Speed Target Engine |
| 03:30 | `ppic:learn` | Learning Engine (changeover) |
| 03:45 | `sequence:train` | Job Order Learning (Sequence) |
| 05:00 | `inv:count-due` | Count Task Service |
| 05:15 | `inv:detect-shortage` | Shortage Auto Detector |
| 06:00 | `maintenance:generate-pm` | Preventive Plan Engine |

Jadwal lain di `routes/console.php` tanpa engine terdaftar: `recruitment:sync-portal` (tiap 2 menit), `assistant:prune` (02:30), `maintenance:accumulate-meters` (05:30), `inv:reconcile` (04:00), `inv:qc-escalate` (tiap jam). Apakah command ini memakai engine lain: BELUM dicek.

## Pemicu per engine

### Inquiry Product

| Key | Engine | Mode | Pemicu | Kartu TG |
|---|---|---|---|---|
| `dieline` | Dieline Engine (TMDieline) | calc | Tiga salinan kode (JS dan port PHP), lihat kartu | [kartu](./sales/inquiry-product/20_engine_dieline.md) |

### Pro-Dev

| Key | Engine | Mode | Pemicu | Kartu TG |
|---|---|---|---|---|
| `imposition` | Imposition Engine | suggest | Layar/rute: 6 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | [kartu](./sales/prodev/10_engine_imposition.md) |
| `spec` | Spec Engine (BOP · BOM · Ink) | suggest | Layar/rute: 3 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | [kartu](./sales/prodev/20_engine_spec.md) |
| `usage-rate` | Usage Rate Engine | suggest | JADWAL: `prodev:usage-rates`, tiap tanggal 1 pukul 02:00 | [kartu](./sales/prodev/30_engine_usage-rate.md) |
| `routing` | Routing Template Engine | suggest | Layar/rute: 1 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |

### Pro-Cost

| Key | Engine | Mode | Pemicu | Kartu TG |
|---|---|---|---|---|
| `costing` | Costing Engine (Pro-Cost) | calc | Layar/rute: 4 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | [kartu](./sales/product-costing/20_engine_costing-engine.md) |
| `costing-advisor` | Costing Advisor (fills the boxes, explains the price, learns) | suggest | Layar/rute: 3 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | [kartu](./sales/product-costing/10_engine_costing-advisor.md) |
| `reverse-costing` | Reverse Costing | auto | Layar/rute: 1 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | [kartu](./sales/product-costing/30_engine_reverse-costing.md) |

### Approval

| Key | Engine | Mode | Pemicu | Kartu TG |
|---|---|---|---|---|
| `flow` | Flow Engine (Approval) | flow | Layar/rute: 12 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |

### Sales Order

| Key | Engine | Mode | Pemicu | Kartu TG |
|---|---|---|---|---|
| `cpdel` | CPDEL Engine (Customer Plan Delivery) | auto | Layar/rute: 7 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | [kartu](./sales/order/20_engine_co-order-builder.md) |
| `cpdel-promise` | CPDEL Two Promises | flow | Layar/rute: 4 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | [kartu](./sales/order/21_engine_cpdel-promise.md) |

### Planning

| Key | Engine | Mode | Pemicu | Kartu TG |
|---|---|---|---|---|
| `material-planning` | Material Planning Engine (MPS · MRP · Booking) | suggest | JADWAL: `planning:run`, harian 01:30; juga layar | [kartu](./sales/order/30_engine_material-planning.md) |
| `booking-maintenance` | Booking Maintenance (hold and stale booking) | auto | JADWAL: `inventory:booking-maintain`, harian 01:45 (bila kolom `rm_booking.hold_until` ada) | belum |

### Inventory

| Key | Engine | Mode | Pemicu | Kartu TG |
|---|---|---|---|---|
| `warehouse-stock` | Warehouse Stock (bins, capacity, dead stock) | calc | Layar/rute: 8 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |
| `stock-posting` | Stock Document Posting (stock card order) | flow | Layar/rute: 5 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |
| `stock-transfer` | Stock Transfer Posting | flow | Layar/rute: 6 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |
| `batch` | Batch Service (batch master and quality status) | flow | Layar/rute: 3 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |
| `batch-backfill` | Batch Backfill (batches from old lot data) | auto | Command manual `inv:backfill-batch` (tidak dijadwalkan) | belum |
| `putaway-planner` | Putaway Planner (suggests a bin) | suggest | Layar/rute: 3 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |
| `warehouse-task` | Warehouse Task Service (putaway tasks) | flow | Layar/rute: 6 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |
| `pick-allocator` | Pick Allocator (which pallet to take) | suggest | Layar/rute: 2 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |
| `pick-list` | Pick List Service | flow | Layar/rute: 3 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |
| `qc-sampling` | QC Sampling Service (sampling plan) | suggest | Layar/rute: 5 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |
| `qc-decision` | QC Decision Service | flow | Layar/rute: 8 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |
| `qc-trace` | QC Trace Service (forward trace of a rejected batch) | flow | Layar/rute: 4 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |
| `stock-reversal` | Stock Reversal Service (cancel a posted stock document) | flow | Layar/rute: 1 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |
| `count-task` | Count Task Service (cycle count) | flow | JADWAL: `inv:count-due`, harian 05:00; juga layar | belum |
| `subcon-stock` | Subcontract Stock Service (material at the vendor) | flow | Layar/rute: 3 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |
| `customer-material` | Customer Material Service (consignment) | flow | Dipanggil: SpecialReceiptService | belum |
| `virtual-location` | Virtual Location Service (Loss, In Transit, vendor bins) | auto | Dipanggil engine lain: StockTransferService, SubconStockService, BinGenerator | belum |
| `pallet-labels` | Pallet Labels (arrival label, relabel after use) | suggest | Layar/rute: 7 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |
| `stock-ledger` | Stock Period Ledger | calc | Layar/rute: 2 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |

### Procurement

| Key | Engine | Mode | Pemicu | Kartu TG |
|---|---|---|---|---|
| `urgent-receipt` | Urgent Receipt Service | flow | Layar/rute: 3 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |
| `shortage-detector` | Shortage Detector (short delivery found late) | suggest | Layar/rute: 2 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |
| `shortage-request` | Shortage Request Service (send us the shortfall) | flow | Layar/rute: 5 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |
| `shortage-auto-detector` | Shortage Auto Detector (from production output) | suggest | JADWAL: `inv:detect-shortage`, harian 05:15 (bila tabel `inv_shortage_request` ada) | belum |
| `pq-matcher` | Purchase Quotation Matcher | suggest | Layar/rute: 2 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |

### Production

| Key | Engine | Mode | Pemicu | Kartu TG |
|---|---|---|---|---|
| `wo-generation` | Work Order Generation | auto | Layar/rute: 6 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |
| `pdr` | PDR Engine (Scheduling) | suggest | Layar/rute: 1 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |
| `scheduling-rules` | Scheduling Rule Engine | calc | Dipanggil engine lain: PdrEngine | belum |
| `sequence-learning` | Job Order Learning (Sequence) | suggest | JADWAL: `sequence:train`, harian 03:45 (bila tabel `prd_sequence_model` ada); juga layar | belum |
| `interruption` | Hot Job Option Generator | suggest | Layar/rute: 1 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |
| `speed-target` | Speed Target Engine | suggest | JADWAL: `production:suggest-speed-targets`, harian 03:00 | belum |
| `ppic-auto-plan` | PPIC Auto-Planner | suggest | JADWAL: `ppic:auto-plan --trigger=DAILY` 02:00 dan `--trigger=SHIFT` (awal shift), bila `$ppicReady`; juga layar | belum |
| `ppic-schedule-solver` | Schedule Solver (finite capacity) | calc | Dipanggil engine lain: AutoPlanRunner, PlanChangeService | belum |
| `ppic-setup-time` | Setup Time Engine | calc | Layar/rute: 2 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |
| `ppic-gates` | Readiness Gates | auto | Layar/rute: 2 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |
| `ppic-wo-enrich` | Work Order Enrichment | suggest | Layar/rute: 1 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |
| `ppic-plan-control` | Plan Confirm and Change | flow | Dipanggil: PpicController, AutoPlanRunner, GateService | belum |
| `ppic-operator-timer` | Operator Timer | flow | Layar/rute: 3 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |
| `ppic-learning` | Learning Engine (changeover) | auto | JADWAL: `ppic:learn`, harian 03:30 (bila `$ppicReady`); juga layar | belum |

### Maintenance

| Key | Engine | Mode | Pemicu | Kartu TG |
|---|---|---|---|---|
| `maintenance-plan` | Preventive Plan Engine | auto | JADWAL: `maintenance:generate-pm`, harian 06:00; juga layar | belum |
| `maintenance-order` | Maintenance Order Lifecycle | flow | Layar/rute: 4 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |
| `maintenance-request` | Maintenance Request Engine | suggest | Layar/rute: 3 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |
| `maintenance-parts` | Maintenance Spare Part Supply | auto | Layar/rute: 2 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |
| `maintenance-kpi` | Maintenance KPI | calc | Layar/rute: 2 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |
| `maintenance-block` | Maintenance Machine Hold (Scheduling) | calc | Layar/rute: 2 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |

### Finance

| Key | Engine | Mode | Pemicu | Kartu TG |
|---|---|---|---|---|
| `tax` | Tax Engine | calc | Layar/rute: 3 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |

### HR & Payroll

| Key | Engine | Mode | Pemicu | Kartu TG |
|---|---|---|---|---|
| `payroll` | Payroll Engine | calc | Layar/rute: 2 berkas merujuk kelas ini (DARI grep nama kelas; tombol/baris belum dipetakan) | belum |

### Help

| Key | Engine | Mode | Pemicu | Kartu TG |
|---|---|---|---|---|
| `assistant` | Assistant Engine (offline) | auto | Pemicu TIDAK ditemukan di `app/` (kelas paket `Assistant`); BELUM dicek lebih jauh | belum |

## Temuan (dicatat, tidak diperbaiki)
1. `MasterCardDummyEngine` (`App\Services\MasterCard`) tidak terdaftar; `EngineCatalogTest::test_every_engine_class_is_registered` GAGAL (dijalankan 2026-10-16). Butuh keputusan Jo: daftarkan atau kecualikan.
2. Registry: 60 key tetapi 59 kelas; key `dieline` tidak punya kelas (tiga salinan kode). `assistant` tidak punya pemanggil di `app/`.
3. `ppic:auto-plan`, `ppic:learn` hanya jalan bila `\` benar; kondisi itu belum dibaca di sesi ini.
4. Engine tanpa kartu TG: 50 dari 60. Temuan registry vs kode untuk 10 engine Sales: `sales/90_temuan_engine-kelompok-c.md`.

## Diagram
[img/peta-engine.svg](./img/peta-engine.svg), kembaran teks = tabel jadwal di atas.
