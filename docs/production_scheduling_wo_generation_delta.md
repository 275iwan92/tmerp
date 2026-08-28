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

## 7. Rute & file (referensi cepat)

| Layar | Route | File |
|---|---|---|
| Screen #1 | `production.wo.generate` | `resources/views/pages/production/wo-generate.blade.php` |
| Screen #2 | `production.wo.entry` | `resources/views/pages/production/wo-entry.blade.php` |
| Screen #3 | `production.monitoring` | `resources/views/pages/production/monitoring-dashboard.blade.php` |
| Print WO | `production.wo.print` | `resources/views/pages/production/wo-print.blade.php` |

Screen #3 dipasang ke `sys_app_menu` code **`PROD_MON_WO`** ("Monitoring Work Order") — baris
lama peninggalan V1 (`target=grid_monitoring_wo.php`, `status_code=NOT_BUILT` sejak 2026-08-08)
yang ternyata pas jadi slot Screen #3, dipakai ulang bukan bikin kode menu baru. Screen #2 & Print
WO sengaja TIDAK py menu sendiri — diakses lewat Worklist WO ("↗ Buka") / tombol "🖨 Cetak" di WO
Detail, pola sama Nota Inquiry (`printnotastandard.md`).

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
