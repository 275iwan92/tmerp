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

## 5. Layar (menambah, bukan mengganti, Tier4 §1)

| Layar | Fungsi |
|---|---|
| **Screen #1 — Generate dari Plan Delivery** | Picklist `co_order_delivery` eligible (belum py `prd_wo`). Pilih 1 baris → `WoGenerationService` jalan → redirect Worklist WO / WO Detail. |
| **Screen #2 — WO Detail (diperluas)** | Sudah ada (`wo-entry.blade.php`, dibangun sesi sebelumnya hari ini utk QC Gate/Breakdown/Catat Output) — ditambah: header CO no+produk+part, panel Bill of Material (read-only dari `iqp_part_material` via `source_iqp_part_id`), Bill of Process jadi interaktif (tambah/edit proses, isi Target per mesin/pegawai dgn default dari §4.2/4.3), guard edit cuma selagi status DRAFT/SIMULATED. |
| **Screen #3 — Monitoring Kartu** | Dashboard kartu (bukan Gantt) per-proses & per-mesin, achievement % dari data F15 (`totalQtyGood()`), kartu merah kalau mesin kosong/tidak ada job — PELENGKAP Scheduling Board yang sudah py highlight serupa dalam bentuk Gantt. |
| **Print WO** | Cetak header WO + Bill of Process (kolom kosong utk isi manual: actual start/finish, qty baik/reject/waste, paraf) + Bill of Material sbg referensi — pakai komponen `resources/views/components/print/*` (`printnotastandard.md`), bukan pola baru. |

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

## 8. Status implementasi (2026-08-28)

Semua 3 layar + print form + `WoGenerationService` SELESAI dibangun & diverifikasi
(`php -l` + `Livewire::test()`/tinker terhadap WO nyata hasil generate, WO2600261) sesi ini.
`PROD_WO_GENERATE` & `PROD_MON_WO` dipromosikan `READY_FOR_QC`
(`2026_08_28_000020_promote_wo_generation_screens_ready.php`). Test PHPUnit permanen (bukan cuma
tinker) untuk `WoGenerationService` BELUM ditulis — masih di-cover manual, lihat §6 dan project
memory `project_production_scheduling_spec.md` untuk status terkini.
