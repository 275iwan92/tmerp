# BACKLOG 03 — SIAP PRODUKSI S/D PENGIRIMAN

> Pecahan dari `BACKLOG.md` (2026-09-22, permintaan user). Indeks semua
> backlog: **[`BACKLOG.md`](BACKLOG.md)**. Item yang sudah selesai dicatat
> di **[`LOG_JOB_DONE.md`](LOG_JOB_DONE.md)**, bukan di sini.

Rantai belakang: penjadwalan produksi (PPIC), Work Order, eksekusi lantai, sampai Delivery Order / pengiriman ke customer.

---


## 6. PRODUCTION SCHEDULING / PPIC (tm2prd)

Modul paling matang cakupannya tapi masih banyak sisa — daftar dari
`project_production_scheduling_spec` (memori sesi, verifikasi ulang ke kode
sebelum eksekusi):

- Decision Feedback calibration loop masih sebatas log, belum ada loop
  belajar sungguhan.
- MTS Forecast → WO auto-replenishment generator belum ditulis (entry +
  accuracy tracking sudah ada, trigger generate WO-nya belum).
- **Simulation Sandbox penuh** ("Submit ke Master Schedule", Tier1 §5A.1 —
  tarik CO ke sandbox → PDR suggest → review → Submit) belum ada sama
  sekali — Worklist WO + WO Detail sudah jadi fondasi natural utk ini.
- WO-part convergence (1 operasi hilir dilayani >1 WO, mis. Badan+Tutup
  ketemu di Packing) — skema saat ini 1 `iqp_part` = 1 `prd_wo`, belum
  bisa model ini, butuh desain tabel baru (`prd_wo_convergence` atau
  sejenis).
- `iqp_part` → `prod_part` (master part reusable lintas-order) belum
  ter-link — `prod_part` sendiri ternyata tidak pernah terisi.
- **F10-F14 & F18-F20** (MRP netting, Material Substitution/Cutting Yield
  engine, Time-Phased Procurement, Lead Time Historical Tracking, Customer
  Interruption Profile reporting) — semua secara eksplisit ditunda user,
  belum digarap sama sekali.
- **Maintenance submodule** (`PROD_MTN_SCH`/`PROD_MTN_PREV`) — di luar
  cakupan modul Production Scheduling menurut Tier1 §6 sendiri, belum ada
  spec sama sekali.
- `PROD_MON_WO` sekarang sudah kepakai (Screen #3 Monitoring Dashboard) —
  **BUKAN lagi item terbuka**, catatan lama yang bilang ini ambigu sudah
  usang.

---

---

## 24. TEST YANG MENINGGALKAN SAMPAH DATA (temuan 2026-09-21)

`ProductionWoGenerationTest::test_generate_creates_wo_with_operations_and_correct_qty`
menyisipkan 1 baris `tm2sdi.co_order_delivery` (`created_by='test'`, ship_qty 200)
tiap kali dijalankan dan TIDAK menghapusnya (tidak ada `tearDown`). Melanggar aturan
CLAUDE.md "test yang menyisipkan data wajib cleanup sendiri". Baris id 134 adalah
sisa lama yang belum dihapus.

| # | Pekerjaan | Status |
|---|---|---|
| 24.4 | **`ProductionWoGenerationTest` masih bocor** — pola rusak yang sama (hardcode `order_id=2`/`inquiry_id=5` di baris 38-41, cleanup baris 69-71 hanya jalan di happy path). Kedua test-nya sekarang gagal di assertion angka (`6000.0` vs `6400`, `0.0` vs `6000`), jadi tiap `php artisan test` meninggalkan 5 delivery + 5 WO + 20 operasi. Terapkan pola fixture 24.1. Kegagalan angkanya dipisah — itu soal perilaku engine, bukan fixture | **BELUM** |
| 24.5 | **Run 2026-09-29 malam**: `ProductionWorklistWoTest` breakdown (subcontract test) tiap run menambah baris `prd_breakdown_event` op WO2600231 dan membiarkan op `BREAKDOWN` -> `test_breakdown_reroute` gagal (harap RUNNING). Sisa run malam ini (event 229/230, co_order_delivery 983) sudah dihapus manual. Juga 4 test lama gagal karena teks UI berubah (bukan regresi): InquirySendToProdev "Referensi Inquiry Asal", InquiryHubStatBox "NEW belum confirm", SchedulingBoard "Plong 1", WorklistWo "Lapor Breakdown" | **BELUM** |

---

## 26. MRP & MPS — DITELANTARKAN SENGAJA, DIRANCANG ULANG NANTI (keputusan user 2026-09-22)

> User: *"MRP dan MPS ini kenapa saya telantarkan, karena saya sama sekali
> tidak suka dengan apa yang sudah di buat. Saya ingin nanti nya mengambil
> data dan meniru model UI UX nya dari prodev dan inquiry system."*

**Status: BUKAN pekerjaan tertunda — ini keputusan membuang hasil lama.**
Jangan digabung, jangan dihidupkan, jangan "diperbaiki sedikit".

| Hal | Keadaan |
|---|---|
| Kode | Diarsipkan di tag **`arsip/mrp-mps-2026-08-30`** (commit `9e8bfc3`, 2026-08-30). Tag, bukan branch — tidak bercabang, tidak bisa di-commit |
| Isi arsip | `MrpEngine`, `MpsEngine`, `WoCreationService`, `PrdMrpRun`, `PrdMrpRequirement`, `kanban.blade.php`, `WoCreationEngineTest`, `ProductionKanbanSmokeTest`, migration `2026_08_28_000013/000014/000015` |
| Database | Tabel `tm2prd.prd_mrp_run` & `prd_mrp_requirement` **terlanjur ada** (ketiga migration tercatat "Ran"), tapi tidak ada kode yang memakainya. **Biarkan — jangan dipakai, jangan di-drop** tanpa perintah user |
| Rencana | Rancang ulang dari nol, **meniru model UI/UX ProDev & Inquiry**, dan mengambil datanya dari sana. Belum dijadwalkan |

Catatan 2026-09-22: sempat digabung ke jalur utama, lalu **dibatalkan** begitu
user menjelaskan bahwa penelantarannya disengaja. Penggabungan itu juga
sempat menambahkan tautan "Lihat di Kanban" di Scheduling Board dan
menghidupkan kembali route `production.kanban` yang sudah dibersihkan
migration `2026_08_28_000032_cleanup_mps_kanban_menu` — keduanya ikut batal.

---

---

## DARI AUDIT MENYELURUH `docs/*.md` (agent riset 2026-09-02)

Konteks lengkap audit ini ada di `BACKLOG.md`.

### 12.4 Delivery/Dispatch — 8 item eksplisit di luar scope v1

`dispatch_tier1.md` §8: picking list granular (per-lokasi/barcode),
Packing List (koli/tonase/m³), Load Planner, Route Planner, Shipment
Manifest, approval 3-pihak perubahan harga (kolom sudah disiapkan, TANPA
flow aktif), FG Stock Out otomatis saat SHIPPED (butuh keputusan valuasi
AVG/FIFO dulu), Proof of Delivery digital (foto/e-signature — v1 masih
tanda tangan fisik), Delivery Return (retur pasca-SHIPPED), Invoicing/
Billing (AR-lite, Modul 6 terpisah).

---
