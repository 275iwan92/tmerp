# BACKLOG 05 — INVENTORY & WAREHOUSING (TERMASUK TATA LETAK)

> Pecahan dari `BACKLOG.md` (2026-09-22, permintaan user). Indeks semua
> backlog: **[`BACKLOG.md`](BACKLOG.md)**. Item yang sudah selesai dicatat
> di **[`LOG_JOB_DONE.md`](LOG_JOB_DONE.md)**, bukan di sini.

Stok 3 kelas (RM/SFG/FG), kartu stok, mutasi, dan tata letak gudang (struktur wh_*, bin, penempatan fisik).

---


## 5. INVENTORY (tm2inv)

- Sebagian besar sudah live (Stock In/Out/Return/Adjustment+Approval,
  3 kelas RM/FG/SFG). Yang tersisa mengikuti dari gap Procurement di atas
  (Purchase Receive 3-alur → tujuan akhirnya nulis ke `sfg_stock`).

---

## TATA LETAK GUDANG — TAHAP 1 DIBANGUN 2026-10-02 (mockup.test), MENUNGGU PENILAIAN USER

Rancangan: **[`warehouselayout_tier1.md`](warehouselayout_tier1.md)** (bagian 13 = yang dibangun). Dokumen review:
**[`md_for_systems/warehouse_review.md`](md_for_systems/warehouse_review.md)**. Yang sudah selesai dicatat di `LOG_JOB_DONE.md`.

| # | Pekerjaan | Tahap | Status |
|---|---|---|---|
| 05.4 | Putaway / picking terarah (aturan putaway, saran bin) | 2 | belum; menunggu penilaian Tahap 1 |
| 05.5 | Pallet / License Plate `inv_pallet` + `pallet_id` di stok; Print Pallet Label | 2 | belum |
| 05.6 | `wh_putaway_rule` + `inv_wh_task` + Scan Transaction (kerja gudang di HP) | 2 | belum |
| 05.7 | FEFO: `mat_category.pick_strategy`, `expiry_date` di stok, isi `shelf_life_days` (baru 1 item) | 2 | menunggu K10 |
| 05.8 | Titipan customer: `owner_customer_id` di stok + area CONSIGN (terkait backlog04 Receiving dari Customer) | 2 | menunggu K4 |
| 05.9 | Subkon: gudang virtual, 1 bin per supplier (terkait backlog04 Receiving dari Subkon) | 2 | menunggu K5 |
| 05.10 | Cycle count per area / bin (reuse doc OP + scope) | 2 | belum |
| 05.11 | Bin Utilization + peringatan kapasitas | 3 | — |
| 05.12 | Denah nyata A5 / A6 / A10 + gedung lain: user 2026-10-02 = denah sementara buatan agen dipakai dulu, officer warehouse mengubahnya lewat layar (Buildings, Storage Areas, Bins, Atur Tata Letak). Gedung lain ditambah user di Buildings | — | di tangan officer; bukan pekerjaan agen lagi |
| 05.13 | Lapis Site + transfer dua langkah (dalam perjalanan) | 2 | hanya bila ada gudang beda kota |
| 05.14 | Stock Card simple + extended, Item per Lokasi, Master Stock, Stock On Hand (preview 2026-10-01) | 1 | menunggu OK |
| 05.15 | Adjustment dengan approval v2 (A1–A2) | 1 | menunggu user |
| 05.17 | Saldo berjalan kartu stok tidak konsisten di dummy LAMA (`php artisan data:audit --only=stock`): stok di `WH-RAW-01` / `UNKNOWN-LEGACY`. Stok contoh baru di gedung A10 / A5 / A6 sudah benar | — | hapus / pindahkan dummy lama setelah user setuju |
| 05.19 | Kapasitas bin: berat per item belum ada di master (FG, plat, dll tidak terhitung) — isi berat per item, atau kapasitas dalam pallet | 1 | **menunggu user** |
| 05.20 | Menu + Help + gedung nyata di tmerp.test (tabel dan view sudah ada) | — | menunggu penilaian user |
| 05.21 | Stock In / Out / Adjustment v2 yang memilih bin dari peta (sekarang layar v1) | 2 | belum |
| 05.22 | Gudang lama buatan sistem (`WH-RAW-01`, `WH-FG-01`, `WH-MIX-01`, `WH-SCP-01`) dinonaktifkan setelah stoknya dipindah | — | menunggu user |
