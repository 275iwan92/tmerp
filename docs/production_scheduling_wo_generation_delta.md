# DELTA — WO Generation dari Plan Delivery + Layar Work Order (Picklist, Detail BOP/BOM, Monitoring)

> Delta doc (pola sama `matcategory_delta.md`) — bukan revisi `production_scheduling_tier1-4.md`
> (dokumen itu tetap acuan LOCKED apa adanya). Dokumen ini menutup 2 gap yang ketahuan saat
> sesi 2026-08-28: **F1 "Job Order Generation otomatis dari CO confirmed" (Tier1 §5) belum
> pernah diimplementasikan** (semua `prd_wo` yang ada saat itu adalah dummy data manual), dan
> **user minta alur 3-layar WO konkret** (picklist → detail BOP/BOM → monitoring kartu) yang
> tidak ada padanannya di Tier4 (yang cuma py Worklist WO grid generik).

---

## 1. Trigger: WO lahir dari Plan Delivery, bukan langsung dari CO

`production_scheduling_tier1.md` §5 F1 bilang JO/WO "otomatis tergenerate dari CO confirmed".
Setelah `co/delivery-plan.blade.php` (Plan Delivery) dibangun 2026-08-22, titik masuk yang lebih
presisi ternyata bukan CO secara keseluruhan, tapi **per baris `co_order_delivery`** — 1 CO bisa
py beberapa jadwal kirim (split/gabung), dan tiap jadwal kirim itu yang jadi pemicu real "kapan
produksi harus mulai untuk qty sekian".

**Alur:**
```
Plan Delivery (co/delivery-plan.blade.php) — baris co_order_delivery dgn
status=PLANNED DAN order.doc_status=CONFIRMED
        │
        ▼  PPIC pilih 1 baris (Screen #1 — Worklist WO "Generate dari Plan Delivery")
        │
WoGenerationService::generateFromDelivery($delivery)
        │
        ├─ resolve inquiry via item→inquiry_id (BOM/BOP snapshot beku, Prinsip Tier1 #snapshot)
        │
        ▼  loop tiap IqpPart milik inquiry itu (1 PART = 1 WO, lihat §2)
        │
    prd_wo (DRAFT) + prd_wo_operation per iqp_part_process (Bill of Process)
```

Guard: 1 baris `co_order_delivery` cuma boleh generate WO SEKALI (`prd_wo.source_delivery_id`
dicek dulu) — supaya PPIC tidak dobel-klik menghasilkan WO ganda.

---

## 2. 1 WO per part, konvergensi di luar scope

Produk fisik bisa py lebih dari 1 part (mis. kemasan Badan + Tutup), dan tiap part **py routing
sendiri yang genuinely beda jumlah/urutan proses** (Badan bisa 7 langkah, Tutup cuma 5) — bukan
sekadar sub-kategori dari 1 routing yang sama. Karena itu:

- **1 `iqp_part` → 1 `prd_wo`** (bukan 1 WO menampung semua part sebuah produk).
- `prd_wo.part_code` (kolom baru, §4) menyimpan `iqp_part.part_code` (mis. "BODY"/"TUTUP") —
  identitas part yang dipakai manusia (PPIC/Marketing) sehari-hari, BUKAN FK ke `prod_part`
  (lihat §3 kenapa).
- **Titik konvergensi** (mis. Badan + Tutup disatukan di proses Packing akhir) **TIDAK
  dimodelkan di pass ini** — skema saat ini (1 `prd_wo_operation` cuma milik 1 `prd_wo`) tidak
  bisa merepresentasikan "1 operation menerima input dari 2 WO berbeda". PPIC gabungkan manual
  di lantai produksi untuk saat ini. Kalau nanti dikerjakan, butuh desain terpisah (mis. tabel
  `prd_wo_convergence` yang menghubungkan WO anak ke WO/operation induk) — dicatat sebagai
  temuan, bukan diselesaikan di sini.

---

## 3. Kenapa target dikunci ke `prod_product`+`part_code`, bukan `prod_part`

Tabel master `prod_part` (dimaksudkan sbg identitas part yang reusable lintas-order, dirujuk
`prd_wo.parts_id` sbg "LOGICAL FK") **ternyata tidak pernah diisi di kode manapun** — `iqp_part`
(snapshot per-Inquiry, dibuat ulang tiap Inquiry baru sesuai prinsip snapshot/beku Tier0) tidak
py link balik ke `prod_part`. Membangun link itu (konfirmasi Inquiry → Master Part, mirip pola
konfirmasi Master Produk yang sudah ada) adalah pekerjaan tersendiri yang cukup besar, lintas ke
modul Sales — **di luar scope pass ini** (dicatat sebagai temuan cross-modul, keputusan
eksplisit: jalan dulu tanpa menunggu perbaikan itu).

Sebagai gantinya, 2 tabel target rate baru (§4) dikunci ke **`prod_product.id_product` (SUDAH
konsisten terisi sejak Master Produk dibangun) + `part_code` (teks)** — cukup stabil karena part
yang sama pada produk yang sama biasanya dipakai nama/kode yang sama tiap kali di-quote ulang
(mis. "BODY" selalu "BODY"), meski belum py FK formal.

---

## 4. Skema baru

### 4.1 `prd_wo` — 3 kolom tambahan (ALTER, bukan tabel baru)
| Kolom | Tipe | Keterangan |
|---|---|---|
| `part_code` | VARCHAR(50) NULL | Salinan `iqp_part.part_code` — identitas part yang dipakai manusia. |
| `source_delivery_id` | BIGINT UNSIGNED NULL | LOGICAL FK `tm2sdi.co_order_delivery.id_delivery` — cegah generate dobel + jejak asal WO. |
| `source_iqp_part_id` | BIGINT UNSIGNED NULL | LOGICAL FK `tm2sdi.iqp_part.id_part` — jembatan balik ke BOM/BOP snapshot asli utk ditampilkan di Screen #2. |

### 4.2 `prod_part_process_machine_rate` (tm2mst, tabel baru)
Target default qty/jam per kombinasi produk+part+proses+**mesin**. `product_id`/`process_id`/
`machine_id` FK fisik (sama DB tm2mst). UNIQUE(product_id, part_code, process_id, machine_id).

### 4.3 `prod_part_process_employee_rate` (tm2mst, tabel baru)
Sama seperti 4.2 tapi utk task **manual** (per-orang, bukan mesin) — `employee_id` ganti
`machine_id` (FK fisik `emp_employee`, sama DB tm2mst — lihat CLAUDE.md §2, employee ada di
tm2mst). UNIQUE(product_id, part_code, process_id, employee_id).

Kedua tabel ini murni DATA referensi (PPIC/Manager bisa isi/edit — layar kelola menyusul kalau
dibutuhkan; utk pass ini diisi manual via `WoGenerationService` sbg default awal `rate_band_value`
di tiap `prd_wo_operation`, **selalu bisa di-override** manusia di Screen #2, tidak pernah
mengunci PPIC).

---

## 5. Layar — REVISI 2026-08-28 (menggantikan spek 3-layar awal di bawah)

> **Riwayat keputusan**: spek awal (masih di §5.1) adalah 3 layar terpisah (picklist/detail/monitoring
> kartu per-proses+mesin). Setelah preview mockup bolak-balik dgn user (referensi: layar V1
> "UPDATE MASTER PRODUCTION SCHEDULE" & dokumen fisik "MASTER CARD/KARTU INDUK" yang ditunjukkan
> user), desain final berubah jadi **1 layar WO Detail saja** — picklist Plan Delivery jadi
> popup/modal (bukan layar/menu sendiri), dan Screen #3 monitoring-kartu **dibatalkan** (diganti
> insight lain: Master Card + dieline jadi bagian WO Detail). Perubahan ini dicatat sbg REVISI,
> bukan ditimpa diam-diam, supaya jejak keputusan (dan kenapa) tetap terbaca.

**1 layar `production.wo.entry/{wo}` (WO Detail), struktur top-to-bottom:**

1. **Strip sumber + tombol "🔀 Pilih Plan Delivery Lain"** — TIDAK ADA grid picklist permanen di
   layar utama. Tombol buka **modal popup**: list `co_order_delivery` eligible (status=PLANNED,
   `order.doc_status=CONFIRMED`), terbelah per `iqp_part` kalau produk multi-part (indented sub-baris
   di bawah tiap CO). User pilih 1 (radio-select) → klik "OK · Proses jadi WO" → modal tertutup →
   `WoGenerationService::generateFromDelivery()` jalan → layar reload dgn WO baru. Dipakai juga dari
   Worklist WO ("+ Generate dari Plan Delivery" existing) sbg entry point kalau belum ada WO dibuka.
2. **Panel Master Card / Kartu Induk** — KONSOLIDASI, bukan diulang-ulang (nama produk/customer
   TIDAK ditampilkan 2x di tempat berbeda seperti draft mockup sebelumnya). Dikelompokkan 3 grup:
   - *Identitas Order*: No CO, Kode Item, No Desain, Due Date, Full/Partial, Nama Produk+Customer
     (1 baris gabungan), **Priority** (Normal/Urgent/Super Urgent — field baru, lihat §5.2).
   - *Spesifikasi Produk*: Part, Qty Order, Dimensi P×L×T, Kode Pisau, Ukuran Pisau, Material,
     Kualitas, Ukuran Bahan, Ukuran Cetak, Jenis Finishing, Bentuk, Bentuk Sambungan, Isi Satu
     Cetakan, Pack Qty — sumber: `vw_master_card` (VIEW YANG SUDAH ADA di tm2mst, lihat §5.3) +
     kolom `prod_part` yang belum terisi ditandai "— belum diisi" (bukan disembunyikan, supaya
     kelihatan gap datanya).
   - *Warna Cetak*: Warna Pertama (CS) & Warna Kedua (KS) per Unit 1-4, dari `prod_part_ink`.
   - Panel kiri (gambar): **artwork + dieline overlay** — foto/gambar produk dgn garis **potong
     (merah, solid, "harus putus")** vs **lipat (biru, dashed, "tidak putus")** dibedakan jelas,
     plus **anotasi dimensi** (panah + ukuran) di tepi gambar. Sumber: `prod_part_artwork` (belum
     ada file tersimpan sama sekali di DB manapun saat ini — kolom `artwork_path`/`artwork_thumb`
     kosong, jadi ini genuinely gap data, bukan cuma UI). **BUKAN** visualisasi plano/up-count ala
     Imposition (`inquiry/imposition.blade.php`) — itu tetap ada di layar Imposition sendiri, beda
     tujuan (Imposition = efisiensi potong plano utk Sales/Costing; panel ini = pengenalan visual
     fisik barang utk PPIC/operator lapangan, "biar tahu mana barangnya").
   - Footer panel: warning text (proof print wajib dilampirkan) + **sign-off 5 role** (Desainer,
     Marketing, PPIC, QA, Plant Manager) — sumber: tabel `prod_mastercard_signoff` (SUDAH ADA,
     `role_name`/`signed_by`/`signed_at`).
3. **Panel Bill of Process** — grid, kolom (kiri→kanan): **Operator** (default terisi, bisa diubah
   manual), **Est. Mulai**, **Est. Selesai** (2 kolom ini dihitung `BackwardSchedulingService`,
   ditandai "⚙ dari engine" — override manual tetap boleh), **Target Kecepatan**, lalu **Proses &
   Mesin** (box gabungan nama proses+mesin, kolom PALING KANAN — permintaan eksplisit user, bukan
   convention biasa taruh identitas di kiri).
4. **Panel Bill of Material** — grid SEMUA bahan yang kepakai sesuai hitungan Product Costing
   (`docs/inquiryproduct_tier2.md`/`CostingEngine.php`) — bukan cuma kertas/board utama: termasuk
   **tinta, lem, coating/varnish**, dst. Kolom: **Qty Dibutuhkan**, **Qty Tersedia (Gudang)**,
   lalu **Material & Spesifikasi** (box gabungan nama+spec, kolom PALING KANAN, sama pola dgn Bill
   of Process). Tiap baris **satuannya boleh beda** (Lembar utk board, Kg utk tinta/lem, dst — TIDAK
   dipaksa 1 UOM seragam). Qty Tersedia ditandai merah kalau di bawah kebutuhan (sinyal perlu PR/PO).
5. **Print WO** (tombol "🖨 Cetak" di header, tetap ada, tidak berubah dari desain sebelumnya) —
   cetak header WO + Bill of Process (kolom kosong utk isi manual) + Bill of Material referensi,
   pakai `resources/views/components/print/*` (`printnotastandard.md`).

### 5.1 Spek AWAL (SUPERSEDED, disimpan sbg riwayat — jangan diimplementasikan)

| Layar | Fungsi |
|---|---|
| Screen #1 — Generate dari Plan Delivery | Picklist `co_order_delivery` eligible, layar/menu sendiri. **DIGANTI**: jadi modal popup dari 1 tombol, lihat §5 poin 1. |
| Screen #2 — WO Detail | Header CO+produk+part, BOM read-only, BOP interaktif. **DIGANTI**: dikonsolidasi jadi panel Master Card (§5 poin 2) + grid BOP/BOM baru (§5 poin 3-4), field2 tak boleh dobel tampil. |
| Screen #3 — Monitoring Kartu | Dashboard kartu per-proses & per-mesin terpisah. **DIBATALKAN** — user tidak lagi minta ini di revisi terbaru; kalau dibutuhkan lagi nanti, re-evaluasi scope-nya dari awal (jangan asumsikan spek lama ini masih berlaku). |

### 5.2 Field baru: Priority

`prd_wo` BELUM py kolom priority bertingkat (cuma `is_hot_job` boolean). Perlu ALTER tambah
`priority` ENUM('NORMAL','URGENT','SUPER_URGENT') DEFAULT 'NORMAL' (atau tabel referensi kalau mau
konsisten pola `ref_*` — putuskan saat implementasi, cek dulu apakah ada dampak ke PDR
`CompositePriorityScorer` yang mungkin sudah py konsep priority sendiri, jangan bikin 2 sumber
kebenaran).

### 5.3 Temuan: skema Master Card SUDAH ADA, datanya yang kosong

Riset 2026-08-28 nemu `tm2mst.vw_master_card` (VIEW) + `tm2mst.prod_mastercard_signoff` (TABEL)
— keduanya sudah dirancang persis utk kebutuhan dokumen fisik "MASTER CARD/KARTU INDUK" yang
dipakai user selama ini (bukan didesain baru sesi ini). View ini join `prod_product`+`prod_part`+
`prod_part_ink`+`prod_part_material`+`prod_part_process`+`cust_customer`. **Gap nyata**: hampir
semua kolom sumbernya (`prod_part.dieline_ref`/`joint_type`/`finishing`/`printsize_p`/`printsize_l`/
`up_per_print`/`pack_qty`, `prod_part_ink`, `prod_part_material`, `prod_part_artwork`) **kosong di
seluruh DB** (0 baris atau NULL) — jadi field2 ini akan tampil "— belum diisi" di WO Detail sampai
seseorang (Product Designer/PPIC) melengkapi Master Card produknya masing2 lewat modul PROD_CARD
(`sys_app_menu` code `PROD_CARD`, masih `NOT_BUILT` — lihat [[project_master_produk_missing]]).
Ini artinya **membangun WO Detail versi baru TIDAK otomatis bikin data ini terisi** — perlu
diputuskan terpisah apakah PROD_CARD dibangun duluan (isi data via layar sendiri) atau WO Detail
sementara terima banyak "— belum diisi" sampai data itu ada.

---

## 6. Langkah berikutnya (di luar pass ini)

- Konvergensi antar-WO-part (§2).
- Perbaikan link Inquiry(`iqp_part`) → Master Part (`prod_part`) supaya §3 bisa dikunci ke FK
  formal, bukan `part_code` teks.
- Layar kelola `prod_part_process_machine_rate`/`_employee_rate` (saat ini diisi manual/lewat
  tinker sampai ada kebutuhan nyata utk PPIC self-service).

---

## 7. Rute & file (referensi cepat) — REVISI 2026-08-28 (Screen #1 dihapus)

> Screen #1 berdiri sendiri (`wo-generate.blade.php`, route `production.wo.generate`, menu
> `PROD_WO_GENERATE`) **DIHAPUS** atas permintaan user — fungsinya (picklist Plan Delivery)
> digabung jadi **modal** di dalam WO Detail. File screen dihapus, route dihapus, menu dihapus
> (migration `2026_08_28_000023`). Jangan buat ulang layar terpisah utk ini.

| Layar | Route | File |
|---|---|---|
| WO Detail — "Add New" (belum ada WO, modal picklist auto-buka) | `production.wo.new` | `resources/views/pages/production/wo-entry.blade.php` (sama komponen, `$wo=null`) |
| WO Detail — edit (Master Card/BOP/BOM tab) | `production.wo.entry` | `resources/views/pages/production/wo-entry.blade.php` |
| Screen #3 (Monitoring Kartu) | `production.monitoring` | `resources/views/pages/production/monitoring-dashboard.blade.php` |
| Print WO | `production.wo.print` | `resources/views/pages/production/wo-print.blade.php` |
| Kelola Target Rate Proses×Mesin/Pegawai | `production.process-rate` | `resources/views/pages/production/process-rate.blade.php` |

Screen #3 dipasang ke `sys_app_menu` code **`PROD_MON_WO`** ("Monitoring Work Order") — baris
lama peninggalan V1 (`target=grid_monitoring_wo.php`, `status_code=NOT_BUILT` sejak 2026-08-08)
yang ternyata pas jadi slot Screen #3, dipakai ulang bukan bikin kode menu baru. WO Detail (baik
mode baru maupun edit) & Print WO sengaja TIDAK py menu top-level sendiri — masuk lewat Worklist
WO ("+ Generate dari Plan Delivery" → `production.wo.new`, atau "↗ Buka" baris existing →
`production.wo.entry`) / tombol "🖨 Cetak" di dalam WO Detail, pola sama Nota Inquiry
(`printnotastandard.md`). Print WO diselaraskan sama kolom baru WO Detail: Bill of Process py
kolom Operator (rencana) + Rencana Mulai (terisi) + Aktual Mulai/Selesai (kosong utk diisi
tangan); Bill of Material py Qty Kebutuhan/Tersedia (terisi, sumber sama `billOfMaterialRows()`
WO Detail) + Qty Diambil (kosong, paraf gudang).

---

## 8. Status implementasi (2026-08-28, revisi §5 SUDAH terpasang)

`WoGenerationService` + Screen #1 (picklist awal, sekarang jadi modal dalam WO Detail) SELESAI
dibangun & diverifikasi sejak pass pertama. Spek §5 (1 layar WO Detail: strip sumber+modal,
Master Card, Bill of Process grid, Bill of Material grid) SUDAH diimplementasikan penuh di
`resources/views/pages/production/wo-entry.blade.php`, termasuk layar baru
`production/process-rate.blade.php` (kelola `prod_part_process_machine_rate`/`_employee_rate`,
route `production.process-rate`, menu `PROD_PROCESS_RATE`). Kolom `prd_wo.priority`
(NORMAL/URGENT/SUPER_URGENT) ditambahkan (migration `2026_08_28_000022`).

Diverifikasi via `Livewire::test()`/tinker + 13 test PHPUnit permanen baru
(`tests/Feature/ProductionWoDetailRebuildTest.php` 7 test + `ProductionWoGenerationTest.php` 6
test existing) — priority update, operator dblclick-assign (crew OPERATOR_UTAMA), Est
Mulai/Selesai inline-edit, Master Card render dari `vw_master_card`, Bill of Material dgn stok
gudang live (`v_rm_available`, tm2inv — SUDAH ADA sejak modul Inventory, bukan skema baru), modal
Plan Delivery generate+redirect, CRUD `process-rate`. Regresi penuh: 63/64 (1 gagal pra-eksisting
tak terkait, sama sejak sesi pertama hari ini).

**Simplifikasi yang sengaja dicatat, bukan disembunyikan** (lihat §5.3 juga): Master Card diambil
per `product_id` saja (ambil vw_master_card baris pertama) — belum presisi utk produk multi-part
krn `prd_wo` tak py FK ke `prod_part` spesifik. Bill of Material "Qty Kebutuhan" = qty WO langsung
(belum ikut faktor lembar/plano dari Imposition — gap §3 yang sama). "Qty Harus Dibeli" = selisih
kebutuhan-tersedia sederhana, BUKAN MRP netting penuh lintas-WO (F10, sengaja dideferred).
Artwork/dieline masih placeholder ilustrasi (`prod_part_artwork` 0 baris di seluruh DB).

---

## 9. Screen #3 HIDUP LAGI 2026-08-28 (revisi §5 sebelumnya, versi Kanban)

§5 di atas bilang Screen #3 "dibatalkan" saat WO Detail dirombak jadi 1 layar. User kemudian minta
menu-nya tetap ada tapi diberi nama ulang jadi 3 baris berurutan di Planning: **"Work Order"**
(`PROD_WO_LIST`), **"MPS - Gantt Chart"** (`PROD_MPS` → `production.scheduling-board`, existing),
**"MPS - Cards"** (`PROD_MON_WO`, dipindah dari grup Output ke Planning — migration `000024`).
"MPS - Cards" inilah Screen #3 yang tadinya dianggap batal, HIDUP LAGI dalam bentuk baru: bukan
kartu statis per-proses/per-mesin lagi, tapi **Kanban ala Trello** (route `production.monitoring`,
file `monitoring-dashboard.blade.php`) — direview via mockup HTML (Claude Artifact, drag-drop
native HTML5 utk simulasi) sebelum dibangun.

**Struktur**: kolom = **Antrean** (task mesin `task_status=PENDING` + `target_start` NULL, backlog
global lepas tanggal) + 1 kolom per `ProdMachine` aktif divisi OFFSET (filter tanggal via date
picker, MERAH kalau tak ada card RUNNING/ASSIGNED) + kolom **Manual** (task `resource_type=MANUAL`
hari itu, tak ikut logika kosong/merah). **Card = 1 `prd_wo_operation`** (task), BUKAN 1 WO utuh —
keputusan eksplisit user krn 1 WO bisa py beberapa task di mesin berbeda.

**Drag-drop**: Alpine `x-data="{draggedId}"` di root + `x-on:dragstart`/`x-on:dragover.prevent`/
`x-on:drop="$wire.moveOperation(draggedId, machineId)"` per kartu/kolom — bukan library eksternal
(SortableJS dst, tak ada di project ini), native HTML5 drag events + Livewire method call langsung.
`moveOperation(operationId, ?machineId)`:
- Digated: hanya task `resource_type=MACHINE` + status `PENDING`/`ASSIGNED` yang boleh digeser
  (task yang sudah RUNNING/selesai/breakdown ditolak dgn toast, arahkan ke "Lapor Breakdown" di WO
  Detail — bukan dead-end, tetap ada jalan lain).
- Drop ke kolom mesin dari Antrean (belum py jadwal): set `machine_id`, `target_start=now()`,
  `target_finish` dari estimasi `rate_band_value`/`qty` (fallback 1 jam) — **simplifikasi sadar**,
  BUKAN backward scheduling penuh (kalender/konflik tetap tanggung jawab
  `BackwardSchedulingService` via MPS - Gantt Chart), transisi status PENDING→ASSIGNED.
- Drop ke Antrean (unassign): **SENGAJA TIDAK menghapus `machine_id`** — cuma reset
  `target_start`/`target_finish`/status. Alasan: `WoGenerationService` sudah pre-fill `machine_id`
  sbg SARAN dari snapshot routing Inquiry saat WO digenerate (WO belum tentu py `target_start` tapi
  SUDAH py `machine_id`); menghapusnya bikin `BackwardSchedulingService::resolveRateForTask()` salah
  kira task itu "manual" (guard-nya cek `!machine_id`, bukan cek `resource_type` doang) dan
  `RateBandResolver` melempar exception. **Bug ini benar-benar kejadian** saat development sesi ini
  — skrip tinker ad-hoc (bukan test permanen) sempat menge-null-kan `machine_id` WO2600250 lewat
  draft awal `moveOperation()`, bikin `ProductionSchedulingBoardSmokeTest::test_hot_job_dialog_...`
  gagal (ketahuan dari re-run regresi, bukan dari user) — datanya diperbaiki manual + logic
  diperbaiki supaya kelas bug ini tak terulang.

Test permanen: `tests/Feature/ProductionMpsCardsKanbanTest.php` (6 test — render, assign dari
Antrean, unassign balik ke Antrean tanpa menghapus `machine_id`, guard task RUNNING/MANUAL tak bisa
digeser, deteksi mesin kosong). `ProductionWoGenerationTest::test_screen3_monitoring_dashboard_renders`
diupdate (assertion lama "Pencapaian per Proses"/"Status per Mesin" sudah tak ada lagi, diganti cek
"MPS - Cards"/"Antrean").

---

## 10. Engine MPS disatukan lintas WO Detail/Gantt/Kanban — REVISI 2026-08-28

User minta dipastikan "engine MPS" (PdrEngine + BackwardSchedulingService + RateBandResolver +
CrewAvailabilityChecker) benar2 jalan otomatis di ketiga layar (WO Detail, MPS - Gantt Chart,
MPS - Kanban Board), bukan cuma tampilan berbeda dari data yang sama (itu sudah benar sejak awal —
ketiganya query `PrdWo`/`PrdWoOperation` langsung, tak ada cache/salinan). Ditemukan 1 bug nyata +
2 gap desain saat ditelusuri:

1. **Bug nyata (WoGenerationService)**: `generateFromDelivery()` memanggil
   `$this->backwardScheduler->schedule($wo->fresh(['operations']))` — `schedule()` cuma menghitung
   di memory, TIDAK menyimpan (beda dari `scheduleAndPersist()`). Hasilnya dibuang (dipanggil di
   objek `fresh()` terpisah dari `$wo` yang di-push ke `$created`), padahal komentar lama mengklaim
   "`material_needed_by` langsung terisi". **Fix**: ganti ke `scheduleAndPersist($wo)` — Est.
   Mulai/Selesai & `material_needed_by` sekarang benar2 terisi begitu WO digenerate.
2. **Gap desain (Gantt Chart)**: `moveOperation()`/`resizeOperation()`/`reassignOperation()` di
   `scheduling-board.blade.php` langsung `$op->update()` tanpa lewat `PdrEngine::
   validateManualReschedule()` — padahal method itu didokumentasikan "WAJIB dipanggil server-side
   saat drag-drop manual" (`CrewAvailabilityChecker` docblock) dan sudah ada sejak awal, cuma
   kepakai di alur Hot Job. **Fix**: ketiga mutator sekarang panggil `validateManualReschedule()`
   dulu (cek konflik crew, durasi minimum, lock QC_HOLD) sebelum commit — drag yang bentrok ditolak
   dgn toast pesan alasannya.
3. **Keputusan user (Kanban)**: sebelumnya `moveOperation()` di `monitoring-dashboard.blade.php`
   sengaja pakai estimasi qty/rate sendiri ("quick-assign visual", §9 di atas). User memutuskan
   dibalik: Kanban sekarang panggil `PdrEngine::backwardScheduleAndPersist($op->wo)` juga — assign
   ke kolom mesin memicu hitung ULANG seluruh urutan task WO itu dari `due_date` (bukan cuma task
   yang digeser), pakai `RateBandResolver` yang sama dgn Gantt. Efek: task lain di WO yang sama BISA
   ikut pindah tanggal/kolom setelah 1 drag — ini disengaja (1 data, 1 sumber kebenaran jadwal),
   bukan bug. Fallback ke estimasi sederhana lama tetap ada (try/catch `RuntimeException`) khusus
   utk WO yang py task manual/rate band belum lengkap (`RateBandResolver`/`resolveRateForTask` blm
   dukung resource manual), supaya drag tak dead-end — toast menandai kalau lagi fallback.

**Efek samping yang perlu diketahui**: `rate_band_value` pada operation kini di-overwrite oleh
`RateBandResolver` (Rate Band Tier1 §5A.6: histori DONE/QC_PASSED, fallback `max_speed_per_hour *
0.8`) setelah backward-schedule persist — BUKAN lagi nilai dari `prod_part_process_machine_rate`
(target rate PPIC, dipakai `WoGenerationService::lookupDefaultMachineRate()` cuma sbg bootstrap
awal sebelum backward-schedule pertama jalan). 2 sumber rate ini sudah beda konsep sejak desain
awal (target rate vs rate band aktual) — bug lama menyembunyikan bedanya krn backward-schedule tak
pernah persisten. `ProductionWoGenerationTest::test_generate_creates_wo_with_operations_and_correct_qty`
diupdate mengikuti (assert 6400 dari `max_speed_per_hour` CETAK-01, bukan 6000 dari rate table).

Regresi penuh 77/77 setelah perubahan ini (`php artisan test --testsuite=Feature`).

---

## 11. MPS - Spreadsheet — layar ke-4, kompatibilitas Google Sheets (2026-08-28)

User minta 1 layar lagi: **"MPS - Spreadsheet"** (`PROD_MPS_SHEET`, route `production.mps-spreadsheet`,
file `mps-spreadsheet.blade.php`) — UI/UX kompatibel dgn kebiasaan lama PPIC pakai Google Sheets utk
kelola MPS. **BUKAN sumber data baru** — baca/tulis `prd_wo_operation`/`prd_wo` yang SAMA dgn WO
Detail/Gantt/Kanban (kini **1 data, 4 tampilan**), lewat `PdrEngine` yang sama juga (permintaan
eksplisit user: "PASTIKAN engine MPS nya tetap jalan dengan baik").

**Scope**: 1 baris = 1 `prd_wo_operation` (task), semua task yang BELUM `DONE`/`QC_PASSED` (cap 1000
baris, bukan per-hari kayak Gantt/Kanban — ini justru poin utamanya, PPIC biasa lihat SEMUA task
sekaligus di 1 sheet raksasa, DataTables yang urus sort/search/paging/filter di sisi klien). Kolom:
WO No (link ke WO Detail) · Customer · Produk/Part · Proses · **Mesin** (editable) · **Operator**
(editable) · Qty · Target/Jam · **Est. Mulai** (editable) · **Est. Selesai** (editable) · Status ·
Due Date. Hanya task `PENDING`/`ASSIGNED` yang editable (task `RUNNING`/`BREAKDOWN`/dst kelola lewat
WO Detail "Lapor Breakdown" — guard sama persis Kanban).

**Filter per kolom ala Excel AutoFilter** (permintaan lanjutan user, "sangat dibutuhkan utk lihat
per Proses/Mesin"): baris ke-2 `<thead>` berisi dropdown (Proses/Mesin/Operator/Status, exact-match
via regex anchor `^value$`) + text input bebas (WO No/Customer/Produk) per kolom, disamping search
global bawaan DataTables. `orderCellsTop: true` supaya klik sort tetap nempel di baris header
PERTAMA (bukan baris filter).

**Library**: DataTables 3 + KeyTable (dari `docs/data tables/`, disediakan user — bundle Editor 3
ikut ter-download tapi **SENGAJA TIDAK dipakai**, lihat alasan di bawah). File `.min.js`/`.min.css`
disalin ke `public/vendor/datatables/`, dimuat via `@assets`/`@endassets` blade Livewire (bukan lewat
Vite/app.js global — cuma dipakai 1 layar, tak perlu bloat bundle semua halaman lain).

**Kenapa TIDAK pakai extension Editor** (walau paling "siap pakai" utk spreadsheet-style editing):
Editor bawa kontrak AJAX sendiri (action=edit, server balikin `{data:[...]}`/`{error:...}`) yang
kalau diimplementasi jujur perlu endpoint terpisah dari Livewire — artinya validasi
`PdrEngine`/`CrewAvailabilityChecker` harus dipanggil dari 2 tempat berbeda dan gampang menyimpang
seiring waktu. Sebagai gantinya: sel editable pakai pola **klik 2x reveal input/select** (sama
semangat dgn dblclick-edit yang sudah ada di WO Detail), commit lewat method Livewire yang sama
persis gayanya dgn Gantt/Kanban/WO Detail.

**Kenapa tabelnya `wire:ignore`, bukan re-render Livewire biasa** (beda dari 3 layar lain): DataTables
menyisipkan wrapper DOM sendiri (search box, kontrol paging) di sekitar `<table>` aslinya — kalau
Livewire coba morph ulang subtree itu tiap request (pola normal Gantt/Kanban), wrapper itu akan
bentrok/rusak. Konsekuensinya: 4 method Livewire (`updateMachine`/`updateOperator`/`updateStart`/
`updateFinish`) di-panggil via `$wire.call()` dari 1 komponen Alpine `mpsRow` per `<tr>` (bukan
`wire:change` biasa), dan masing2 me-**return array kecil** (`{ok, machineLabel, startLabel, ...}`)
yg langsung dipakai Alpine utk nge-patch span sel yg relevan + memberi tahu DataTables re-index sel
itu (`table.row(tr).invalidate('dom').draw(false)`) — tanpa reload apa pun. Toast tetap jalan normal
(dispatch event browser, tak terpengaruh `wire:ignore`).

**Kenapa Mesin/Operator/Est Mulai/Selesai pakai `<template x-if>`, BUKAN `x-show`, utk elemen
edit-nya**: awalnya dicoba `x-show` (CSS `display:none` doang) — ternyata `<select>`/`<input>` yang
cuma disembunyikan CSS TETAP ada di DOM, dan DataTables baca `textContent` sel utk sort/search
termasuk isi elemen tersembunyi itu (gabungan semua `<option>`, bukan cuma span teksnya) — sort/
search kolom itu rusak diam-diam. `<template x-if="editingX">` beneran MELEPAS elemen dari DOM saat
tidak diedit, jadi cuma `<span x-text="...">` yang terbaca DataTables.

**Keterbatasan yg disengaja** (bukan bug tersembunyi): assign mesin ke task belum terjadwal bisa
nge-reschedule SELURUH WO (task lain, baris LAIN) lewat `backwardScheduleAndPersist` — baris lain itu
TIDAK auto-update tampilannya (di luar jangkauan `wire:ignore`/patch per-baris), user diberi tahu
lewat toast utk klik "🔄 Refresh" (reload polos, `stateSave` DataTables tetap mempertahankan
halaman/sort/filter yg lagi aktif).

**Engine, kontekstual persis spt layar lain** (`mps-spreadsheet.blade.php`):
- Assign Mesin ke task **belum terjadwal** (`target_start` NULL) → sama persis Kanban:
  `PdrEngine::backwardScheduleAndPersist($op->wo)` (hitung ulang seluruh WO dari `due_date`),
  fallback estimasi sederhana kalau rate band blm lengkap (`RuntimeException`, task manual dst).
- Ganti Mesin/Operator/waktu manual pada task **sudah terjadwal** → sama persis Gantt:
  `PdrEngine::validateManualReschedule()` dulu (konflik crew, durasi minimum, lock QC_HOLD) sebelum
  commit — ditolak dgn toast pesan alasan kalau invalid.

Test permanen: `tests/Feature/ProductionMpsSpreadsheetTest.php` (5 test — render, assign mesin ke
task Antrean via engine, edit Est Mulai tervalidasi, **konflik operator ditolak** [skenario nyata:
2 task overlap waktu, assign operator yg sama ke keduanya → assignment KEDUA ditolak], task RUNNING
tak bisa diedit). Test ini cek method Livewire (PHP/DB/engine) — interaksi DataTables/Alpine di
browser (sort/filter/klik 2x edit) belum ada automated test-nya (`Livewire::test()` tak menjalankan
JS), diverifikasi manual dulu. Regresi penuh 82/82 setelah ini.
