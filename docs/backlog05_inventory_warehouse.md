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

## TATA LETAK GUDANG — TAHAP 1 + PUTARAN 2 TERPASANG DI mockup.test DAN tmerp.test (2026-10-03), MENUNGGU PENILAIAN USER

Rancangan: **[`warehouselayout_tier1.md`](warehouselayout_tier1.md)** (bagian 13 = yang dibangun). Dokumen review:
**[`md_for_systems/warehouse_review.md`](md_for_systems/warehouse_review.md)** (bagian 9 = putaran 2). Yang sudah selesai dicatat di `LOG_JOB_DONE.md`.

| # | Pekerjaan | Tahap | Status |
|---|---|---|---|
| 05.4 | Putaway / picking terarah (aturan putaway, saran bin) | 2 | belum; menunggu penilaian Tahap 1 |
| 05.5 | Label palet untuk barang jadi saat hasil produksi masuk gudang (sekarang lewat daftar pekerjaan label) | 2 | menunggu user |
| 05.6 | `wh_putaway_rule` + `inv_wh_task` + Scan Transaction (kerja gudang di HP) | 2 | belum |
| 05.7 | FEFO: saran ambil palet yang kedaluwarsa duluan; larangan memakai palet kedaluwarsa; isi `shelf_life_days` master (baru sedikit item). Tanggal kedaluwarsa per palet + tag sudah ada | 2 | menunggu user |
| 05.8 | Titipan customer: `owner_customer_id` di stok + area CONSIGN (terkait backlog04 Receiving dari Customer) | 2 | menunggu K4 |
| 05.9 | Subkon: gudang virtual, 1 bin per supplier (terkait backlog04 Receiving dari Subkon) | 2 | menunggu K5 |
| 05.10 | Cycle count per area / bin (reuse doc OP + scope) | 2 | belum |
| 05.11 | Bin Utilization + peringatan kapasitas | 3 | — |
| 05.12 | Denah nyata A5 / A6 / A10 + gedung lain: user 2026-10-02 = denah sementara buatan agen dipakai dulu, officer warehouse mengubahnya lewat layar (Buildings, Storage Areas, Bins, Atur Tata Letak). Gedung lain ditambah user di Buildings | — | di tangan officer; bukan pekerjaan agen lagi |
| 05.13 | Lapis Site + transfer dua langkah (dalam perjalanan) | 2 | hanya bila ada gudang beda kota |
| 05.14 | Stock On Hand gabungan (On Hand, Booked, Available, Incoming PO, Pending adj, status Short / Late PO) dari preview 2026-10-01. Stock Card, Master Stock (= Stock Recapitulation) dan Item per Lokasi sudah dibangun 2026-10-02 | 1 | menunggu OK |
| 05.15 | Adjustment dengan approval v2 (A1–A2) | 1 | menunggu user |
| 05.17 | Saldo berjalan kartu stok tidak konsisten di dummy LAMA (`php artisan data:audit --only=stock`): stok di `WH-RAW-01` / `UNKNOWN-LEGACY`. Stok contoh baru di gedung A10 / A5 / A6 sudah benar | — | hapus / pindahkan dummy lama setelah user setuju |
| 05.19 | Kapasitas: volume hanya diperkirakan untuk lembaran kertas; tinta, lem, barang jadi, plat belum punya data ukuran / berat per item di master | 1 | **menunggu user** |
| 05.21 | Stock In / Out / Adjustment v2 yang memilih bin dari peta (sekarang layar v1) | 2 | belum |
| 05.24 | Approval Stock Transfer: pemeriksa dan batas nilai (fasilitas ada, bawaan mati). **Di tmerp.test belum bisa diaktifkan**: Setting Approval v2 belum terpasang di tm2 (migration `2026_09_30_000006` .. `000010` belum dijalankan di sana, milik pekerjaan approval). Sesudah terpasang: `php artisan db:seed --class=StockTransferApprovalSeeder --force` | — | menunggu user |
| 05.25 | Pilih palet per baris di form Stock Transfer (sekarang: palet ikut otomatis, atau lewat Move this Pallet) | 2 | belum |
| 05.22 | Gudang lama buatan sistem (`WH-RAW-01`, `WH-FG-01`, `WH-MIX-01`, `WH-SCP-01`) dinonaktifkan setelah stoknya dipindah | — | menunggu user |
| 05.23 | **Urutan posting kartu stok**: aturan hitung ulang sudah dibangun 2026-10-03 (`LOG_JOB_DONE.md`). Sisa: keputusan accounting (jurnal koreksi), hapus + bangun ulang dummy kartu stok yang salah (rincian di bawah) | 1 | **menunggu user** |
| 05.37 | Layar tutup / buka periode inventory (`inv_period`: sekarang hanya 1 baris, tanpa layar; `StockPostingService` sudah menolak tanggal di periode CLOSED, `FinPeriodGuard` mensyaratkannya sebelum tutup buku GL) | 1 | belum |
| 05.30 | Laporan umur stok (Stock Ageing: nilai dan qty per rentang 0-30 / 31-60 / 61-90 / lebih dari 90 hari sejak diterima). Ada di Odoo, Business Central, D365, Accurate, ERPNext (`md_for_systems/inventoryreport_references.md`) | 2 | belum; perlu keputusan rentang hari |
| 05.31 | Analisis ABC item stok (nilai pemakaian, kelas A / B / C). Ada di SAP dan Business Central | 3 | belum |
| 05.32 | Pembanding nilai stok vs saldo akun persediaan (rekonsiliasi gudang dengan buku besar) | 2 | belum; perlu keputusan accounting (akun persediaan per kelas) |
| 05.33 | Barang diterima belum ditagih (goods received not invoiced): periksa apakah sudah tertutup laporan Purchase / Finance | — | belum diperiksa |
| 05.34 | Laporan stok semua kelas: hak lihat NILAI (sekarang semua yang boleh membuka Inventory melihat nilai, sama dengan Stock Balance). Usulan lama S1: hanya Finance, Costing, Ops Manager ke atas | — | **menunggu user** |
| 05.35 | Stock Card: pilih item lewat lookup master (sekarang kotak teks kode / nama), dan Stock Card "simple" yang menyembunyikan transfer dalam satu gudang | 2 | belum |
| 05.36 | Stock Opname: lembar hitung kosong untuk dicetak sebelum menghitung (count sheet per gudang / area), dan cetakan berita acara per dokumen | 2 | belum |

---

## 05.23 URUTAN POSTING KARTU STOK — DIBANGUN 2026-10-03, DUA HAL MENUNGGU USER

Aturan user (rulebook A.24) sudah terpasang di kode: dokumen bertanggal mundur disisipkan dan baris sesudahnya dihitung
ulang, periode inventory tertutup ditolak, penolakan membatalkan seluruh dokumen. Rincian aturan:
`md_for_systems/inventory_tier2.md` bagian "Urutan kartu stok". Yang dikerjakan dicatat di `LOG_JOB_DONE.md`.

**Masih terbuka:**

1. **Accounting — jurnal koreksi.** Hitung ulang mengubah harga rata-rata baris Stock Out sesudahnya; jurnal pemakaian
   (HPP) baris itu sudah diposting dengan harga lama. Sekarang selisihnya hanya dilaporkan (peringatan di layar,
   `revaluation` di hasil `postDocument()`). Pertanyaan ke user: dijurnal koreksi otomatis, atau cukup peringatan?
2. **Dummy kartu stok yang salah: hapus + bangun ulang** (keputusan user butir 3) — BELUM dikerjakan; diperiksa
   2026-10-03 (hanya membaca), ini yang ditemukan:
   - Yang salah = dokumen stok LAMA di gudang lama (`UNKNOWN-LEGACY` id 0, `WH-RAW-01`, `WH-FG-01`, `WH-MIX-01`,
     `WH-SCP-01`, `WH-TEST-01`): nomor `26BB…`, `26OU…`, `26SR…`, `26OP…`, `26AD…`, `26IN…`, `26SPB…` (banyak berakhiran
     `-DUP`), `data_origin = LIVE`, `create_by` kosong, sebagian belum diposting. Ada di tm2inv DAN ddtinv (salinan).
     `data:audit --only=stock`: tm2inv 10 temuan, ddtinv 3. Dua dokumen `LEGACY` hasil migrasi (`PRC16…`, tahun 2016)
     tidak termasuk temuan audit — dibiarkan.
   - **ddt:** set stage 1-12 sudah bersih (Stage 1 menghapus semua dokumen stok); stok contoh yang benar =
     `UatWarehouseSeeder` (gedung A10 / A5 / A6). Dokumen lama hanya tersisa di set "mixed" dan di baseline. Menghapusnya
     = hapus di ddt lalu simpan ulang baseline + set "mixed" (`storage/app/ddt-baseline`, `ddt-stages` ada di folder utama
     repo (`Herd/tmerp`), bukan di worktree) — kerjakan dari folder utama, cadangkan baseline lama dulu.
   - **tm2:** dummy tm2 = fixture uji (memori `feedback_tm2_dummy_is_test_fixture`: hapus dummy tm2 hanya setelah bertanya
     dan setelah test yang memakainya ditulis ulang). Test yang membaca stok: `BoardStockDocumentsTest`,
     `StockReportsTest`, `PvStockIntegrationTest` (memakai gudang `WH-RAW-01`). Ambil dump tabel `rm_stock*`, `sfg_stock*`,
     `fg_stock*` dulu sebelum menghapus apa pun.
   - Sesudah menghapus: `cur_stock` master (`mat_raw`, `prod_part`, `prod_product`) disamakan dengan jumlah saldo bin, dan
     gudang lama dinonaktifkan (05.22).
3. Layar tutup periode inventory: 05.37.
