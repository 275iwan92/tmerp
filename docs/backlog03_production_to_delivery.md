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
- **F10-F14 & F18-F20** (MRP netting, Material Substitution/Cutting Yield
  engine, Time-Phased Procurement, Lead Time Historical Tracking, Customer
  Interruption Profile reporting) — semua secara eksplisit ditunda user,
  belum digarap sama sekali.
- **Maintenance** (modul v2 `MAINT`) — DIHAPUS TOTAL 2026-10-08 atas perintah user (kode, layar, tabel `mnt_*` dan menu di ddt*, tanpa arsip).
  Dibangun ulang mulai dari Master Data (rencana teknis + mockup `docs/mockups/maintenance_v3_*.jpg`). Sisa di tm2*: 11 tabel `mnt_*`
  kosong (kecuali `mnt_code`), modul `MAINT`, menu, grup `MAINT_TECH` / `MAINT_HEAD` dan konstanta `MNT_LABOR_RATE_HOUR` -- dibereskan
  oleh migration pembangunan ulang saat rilis.
---

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
