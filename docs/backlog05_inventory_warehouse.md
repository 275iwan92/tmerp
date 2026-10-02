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

## TATA LETAK GUDANG — TAHAP 1 + PUTARAN 2 DIBANGUN 2026-10-02 (mockup.test), MENUNGGU PENILAIAN USER

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
| 05.14 | Stock Card simple + extended, Item per Lokasi, Master Stock, Stock On Hand (preview 2026-10-01) | 1 | menunggu OK |
| 05.15 | Adjustment dengan approval v2 (A1–A2) | 1 | menunggu user |
| 05.17 | Saldo berjalan kartu stok tidak konsisten di dummy LAMA (`php artisan data:audit --only=stock`): stok di `WH-RAW-01` / `UNKNOWN-LEGACY`. Stok contoh baru di gedung A10 / A5 / A6 sudah benar | — | hapus / pindahkan dummy lama setelah user setuju |
| 05.19 | Kapasitas: volume hanya diperkirakan untuk lembaran kertas; tinta, lem, barang jadi, plat belum punya data ukuran / berat per item di master | 1 | **menunggu user** |
| 05.20 | Menu + Help + gedung nyata di tmerp.test (tabel dan view sudah ada) | — | menunggu penilaian user |
| 05.21 | Stock In / Out / Adjustment v2 yang memilih bin dari peta (sekarang layar v1) | 2 | belum |
| 05.24 | Approval Stock Transfer: pemeriksa dan batas nilai (fasilitas ada, bawaan mati); Setting Approval belum terpasang di tm2 | — | menunggu user |
| 05.25 | Pilih palet per baris di form Stock Transfer (sekarang: palet ikut otomatis, atau lewat Move this Pallet) | 2 | belum |
| 05.22 | Gudang lama buatan sistem (`WH-RAW-01`, `WH-FG-01`, `WH-MIX-01`, `WH-SCP-01`) dinonaktifkan setelah stoknya dipindah | — | menunggu user |
| 05.23 | **Urutan posting kartu stok**: dokumen bertanggal lebih awal dari baris terakhir tidak sampai ke saldo (rincian di bawah) | 1 | **menunggu aturan dari user** |

---

## 05.23 URUTAN POSTING KARTU STOK — CACAT TERBUKTI 2026-10-02, MENUNGGU ATURAN USER

Bukti: `tests/Feature/StockPostingOrderTest.php` (4 uji berstatus INCOMPLETE "KNOWN DEFECT"; lulus sendiri begitu diperbaiki).
Belum ada kode aplikasi yang diubah — menyentuh nilai stok (harga rata-rata) dan periode tutup buku, jadi aturannya dipilih user dulu.

**Sebabnya.** `StockPostingService::postDocument()` (satu jalur untuk `rm_stockcard`, `sfg_stockcard`, `fg_stockcard`) mengambil saldo
awal dari baris SEBELUM tanggal dokumen dan tidak menyentuh baris yang bertanggal sesudahnya. Saldo dibaca dari baris TERAKHIR
(view `v_rm_onhand` / `v_sfg_onhand` / `v_fg_onhand`, `StockTransferService::balance()`, `WarehouseStock`). Semua dokumen stok manual
dan SPB bertanggal jam 00:00; Stock Transfer mencatat jam saat itu. Jadi dokumen apa pun yang diposting pada hari yang sama SESUDAH
ada transfer (atau dokumen bertanggal mundur) "terselip" di tengah kartu.

| # | Yang terjadi sekarang | Terbukti di uji |
|---|---|---|
| 1 | Barang masuk bertanggal lebih awal dari baris terakhir: tertulis di kartu, saldo bin tetap (5.000, bukan 5.010). `cur_stock` di master bahan naik, jadi master dan saldo bin berbeda. Harga rata-rata baris sesudahnya tidak ikut berubah | `test_a_posting_dated_before_the_latest_row_...` |
| 2 | Dua dokumen pada tanggal yang sama: baris "sebelumnya" dicari dengan membandingkan nomor urut baris KARTU dengan nomor urut KEPALA DOKUMEN (dua deret angka berbeda, `StockPostingService` baris 108). Selama nomor kartu lebih kecil hasilnya kebetulan benar (tm2inv: 48 vs 102, ddtinv: 171 vs 195); transfer menambah 2 baris kartu tanpa kepala dokumen, jadi akan terlewati — sesudah itu dokumen kedua tidak melihat dokumen pertama (saldo 10, bukan 110) | `test_a_second_document_on_the_same_day_...` |
| 3 | Cancel SPB (`PvPostingService::reverseInventory`, Stock Out bertanggal hari ini 00:00) sesudah barangnya dipindah ke bin lain: diterima, padahal bin penerimaan kosong. SPB batal, barang tetap ada di bin tujuan, `cur_stock` berkurang | `test_reversing_a_receipt_whose_stock_was_moved_away_is_refused` |
| 4 | Stock Adjustment / Opname bertanggal lebih awal dari baris terakhir: selisih tertulis di kartu, saldo tidak terkoreksi (80, bukan 75). Kena juga bila ada dokumen lain diposting selama adjustment menunggu approval | `test_an_adjustment_dated_before_the_latest_row_...` |

Ikut terkena lewat jalur yang sama: Stock Out (cek saldo minus memakai saldo lama, jurnal pemakaian memakai harga rata-rata lama) dan
Stock Return. Tidak terkena: Stock Transfer (`move()` selalu menaruh barisnya paling akhir).

Temuan sampingan (belum diubah):
- Posting stok tidak memeriksa periode: `inv_period` hanya dibaca `FinPeriodGuard` saat tutup buku GL. Dokumen stok bertanggal di bulan
  yang sudah ditutup tetap diterima; jurnalnya ditolak `JournalPostingService` dan hanya jadi peringatan.
- `stock-adjustment.blade.php::pullFullScope()` memakai `MAX(doc_id)` sebagai "baris terakhir" (bukan tanggal + nomor seperti tempat lain).
- `data:audit --only=stock`: tm2inv 10 temuan, ddtinv 3 (sebagian data lama tanpa `create_by`; `sfg_stockcard` item 2 lokasi 4 = pola no. 1).

**Pilihan aturan (menunggu user):**
- (a) Hitung ulang semua baris sesudahnya (qty_bb / qty_eb / value_eb / avg_unitprice) item + bin itu setelah posting bertanggal mundur.
- (b) Tolak posting yang bertanggal sebelum baris terakhir item + bin itu.
- (c) Selalu taruh baris baru paling akhir (seperti `StockTransferService::move()`); tanggal dokumen tetap tersimpan di kepala dokumen.
