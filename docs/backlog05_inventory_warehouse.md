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

## TATA LETAK GUDANG — BELUM DIRANCANG (permintaan user 2026-09-22)

User minta backlog Inventory **termasuk tata letak**. Keadaan sekarang
(dicek ke DB 2026-09-22):

| Tabel | Isi | Catatan |
|---|---|---|
| `tm2mst.wh_warehouse` | 5 baris | header gudang |
| `tm2mst.wh_loc` | 7 baris | lokasi di dalam gudang |
| `tm2mst.wh_bin` | **tidak ada** | bin/rak per lokasi belum pernah dibuat |

Artinya struktur gudang baru 2 lapis (gudang -> lokasi). Yang belum ada:

| # | Pekerjaan | Status |
|---|---|---|
| 05.1 | Lapis ketiga `wh_bin` (rak/bin per lokasi) + penomoran baris-kolom-tingkat | **BELUM** |
| 05.2 | Penempatan fisik: item mana di bin mana, kapasitas per bin, aturan penempatan | **BELUM** |
| 05.3 | Layar denah/peta gudang (visual tata letak, bukan cuma daftar) | **BELUM** — belum ada mockup |
| 05.4 | Putusan: apakah tata letak dipakai untuk picking/putaway (butuh rute), atau sekadar pencatatan posisi | menunggu user |

Belum ada mockup untuk tata letak. Perlu arahan user dulu sebelum dirancang.

---
