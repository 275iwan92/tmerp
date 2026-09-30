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

## TATA LETAK GUDANG — DESAIN SIAP, MENUNGGU PERSETUJUAN USER (2026-10-01)

Desain lengkap (riset Odoo/SAP/Oracle/D365/ERPNext/Accurate/Zahir, skema,
journey, keputusan): **[`warehouselayout_tier1.md`](warehouselayout_tier1.md)**.
Mockup: `mockups/warehouse_layout_v2.jpg` (peta + setup zona/generator bin),
`mockups/warehouse_layout_mobile_v2.jpg` (HP: tugas, putaway, pick list).

Temuan cek DB 2026-10-01: `wh_loc` **sudah** berbentuk bin (row/col/stack,
capacity_kg) dan stok sudah dicatat per `location_id` + `lot_no`
(`StockPostingService` menolak saldo negatif per lokasi). Maka `wh_loc` tetap
jadi bin; yang ditambah = lapis **`wh_zone`** di atasnya (bukan `wh_bin`).

| # | Pekerjaan | Tahap | Status |
|---|---|---|---|
| 05.1 | `wh_zone` + kolom baru `wh_loc` (zone_id, map_x/y, capacity_pallet, supplier_id, machine_id, count_freq_days, ...) + generator bin + label bin QR | 1 | menunggu OK desain |
| 05.2 | Warehouse Map (DevLib `x-dl::wh-map` dulu di devlib.test) | 1 | menunggu OK desain |
| 05.3 | Bin Transfer (doc_cat `TF`, `*_stock_d.from_location_id`) + report Stock by Location | 1 | menunggu OK desain |
| 05.4 | Putaway/picking terarah — **diusulkan: Tahap 1 pencatatan, Tahap 2 terarah** (keputusan K2) | 2 | menunggu user |
| 05.5 | Pallet/License Plate `inv_pallet` + `pallet_id` di stok; Pallet Label menu Label & QR | 2 | menunggu K3 |
| 05.6 | `wh_putaway_rule` + `inv_wh_task` + layar HP (DevLib `x-dl::scan`) | 2 | menunggu OK desain |
| 05.7 | FEFO: `mat_category.pick_strategy`, `expiry_date` di stok, isi `shelf_life_days` (baru 1 item) | 2 | menunggu K10 |
| 05.8 | Titipan customer: `owner_customer_id` di stok + zona CONSIGN (terkait backlog04 Receiving dari Customer) | 2 | menunggu K4 |
| 05.9 | Subkon: gudang virtual WH-SUBCON, 1 bin per supplier (terkait backlog04 Receiving dari Subkon) | 2 | menunggu K5 |
| 05.10 | Cycle count per zona/bin (reuse doc OP + scope) | 2 | menunggu OK desain |
| 05.11 | Bin Utilization + peringatan kapasitas | 3 | — |
| 05.12 | Denah nyata gudang dari user (sketsa/foto + daftar rak) sebelum dijalankan ke tm2 | — | **menunggu user (K9)** |
| 05.13 | Multi lokasi: `wh_site` + gudang virtual IN-TRANSIT, transfer antar site 2 langkah (M1–M3) | 1 | menunggu user |
| 05.14 | Cari Barang multi lokasi, Stock Card simple + extended, Item per Lokasi + riwayat, Master Stock, Stock On Hand | 1 | preview dibuat 2026-10-01, menunggu OK |
| 05.15 | Adjustment dengan approval v2 (A1–A2) | 1 | menunggu user |
| 05.16 | Material Booking dari CO (`materialbooking_tier1.md`, B1–B9) | 1–2 | preview dibuat 2026-10-01, menunggu OK |
| 05.17 | Periksa saldo berjalan `rm_stockcard.qty_eb` yang tidak konsisten (ANIMAL GLUE, UNKNOWN-LEGACY negatif) | — | temuan 2026-10-01 |
