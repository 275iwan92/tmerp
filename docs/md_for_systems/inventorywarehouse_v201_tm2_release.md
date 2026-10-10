# Inventory & Warehouse v2.01 — daftar migration untuk tm2* (rilis)

Disusun 2026-10-10 (Agent: Jo). **Belum dijalankan di tm2*.** Semua migration di bawah baru terpasang di ddt* (dummy).
Aturan: jangan menulis ke tm2* sebelum user memberi izin rilis. Dokumen ini hanya daftar dan urutan.

## 0. Peringatan penting
- **JANGAN `php artisan migrate` polos di tm2.** `migrate:status` tm2 menunjukkan 47 migration pending, termasuk milik pekerjaan lain
  (menu v2 2026_10_07_*, Maintenance 2026_10_08_00001x-00004x, PPIC 2026_10_09_00001x-00007x). Jalankan hanya berkas v2.01 di bawah
  dengan `--path=` satu per satu (urutan nama berkas = urutan pakai).
- Prasyarat yang sudah dicek (baca saja, 2026-10-10) ada di tm2: tabel `rm_booking(+_d)`, `rm/sfg/fg_stockcard`, `rm/sfg/fg_stock(+_d)`,
  `inv_pallet`, `inv_transfer(+_line)`, `inv_period`, `wh_zone`, `wh_loc`, `wh_warehouse`, `pv_line`, `pv_receipt`, `po_line`,
  `sys_app_menu`, `sys_constant`, `fin_account`; kolom `rm_booking.co_id`; menu induk `INV`, `INV_WH`, `PROC`.
- Backup dulu: dump `tm2inv`, `tm2mst`, `tm2pur`, `tm2sys`, `tm2acc`. Migration 000100 menambah kolom pada tabel kartu stok yang besar
  (online DDL MySQL 8; jalankan di luar jam kerja, lihat skill tmerp-mysql).
- Semua migration idempoten (cek `hasTable`/`hasColumn`/kode menu) — aman diulang.

## 1. Urutan (15 berkas)
| # | Berkas | DB tersentuh | Isi |
|---|---|---|---|
| 1 | 2026_10_08_000100_inventory_warehouse_v201_foundation | tm2inv, tm2mst, tm2pur, tm2sys | `inv_batch`; kolom batch/owner pada kartu & dokumen stok + palet; kolom pembatalan; In Transit; kolom booking; aturan area; count lock; VIRTUAL; kolom URGENT `pv_line`; 12 konstanta |
| 2 | 2026_10_08_000110_menu_inventory_batch_install | tm2sys | grup Inventory > Batch & Quality + item Batch |
| 3 | 2026_10_08_000120_warehouse_tasks | tm2inv, tm2sys | `wh_task`; menu Receiving & Putaway |
| 4 | 2026_10_08_000130_pick_list_and_prebooking | tm2inv, tm2sys | `wh_pick_list(+_line)`; kolom hold/basi; konstanta; menu Pick List & Pre-Booking |
| 5 | 2026_10_08_000131_prebooking_need_constant | tm2sys | konstanta INV_PRE_BOOKING_NEED_WORKDAYS |
| 6 | 2026_10_08_000140_inventory_qc_shortage | tm2inv, tm2pur, tm2sys | tabel sampling/risk/decision/first article/hold/claim/shortage; kolom pv_line; nilai awal tabel sampel |
| 7 | 2026_10_08_000141_menu_inventory_batch_quality | tm2sys | menu Conditional Stock, First Article, Sampling, Risk, Shortage |
| 8 | 2026_10_09_000210_inventory_transit_count_subcon | tm2inv, tm2sys | kolom transit/count; `inv_subcon_issue(+_line)`, `inv_tool`; menu |
| 9 | 2026_10_09_000300_inventory_item_average_open | tm2inv | `inv_item_avg_open` (titik peralihan Average per item) |
| 10 | 2026_10_09_000600_inventory_valuation_accounting | tm2inv, tm2sys, **tm2acc** | landed cost, repost, reconcile, provisi; 12 konstanta; **4 akun baru di `fin_account` (211950, 518000, 620000, 113900)**; menu Valuation & Period |
| 11 | 2026_10_09_000500_menu_inventory_valuation_check | tm2sys | menu Valuation Check (butuh grup Reports; jalankan setelah #10 bila grup dibuat di sana) |
| 12 | 2026_10_09_000700_menu_warehouse_task_board | tm2sys | menu Task Board (butuh modul WH dari #13: jalankan setelah #13) |
| 13 | 2026_10_09_000400_menu_inventory_warehouse_split | tm2sys | pisah modul Inventory / Warehouse (kode modul akses tetap INV) |
| 14 | 2026_10_09_000800_inventory_shortage_followup | tm2inv, tm2pur | kolom PR/debit note; `dn_debit_note` |
| 15 | 2026_10_10_000100_landed_cost_stock_cards | tm2inv | kolom `card_value`/`card_synced` pada baris landed cost |

Urutan eksekusi yang disarankan (menu terakhir agar semua induk ada): **1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 14, 15, 11, 13, 12**
(menu 000500 dan 000700 mencari grup/modul induk; jalankan `--pretend` dulu untuk melihat SQL-nya).

Contoh satu perintah (tm2, hanya setelah izin):
```
php artisan migrate --path=database/migrations/2026_10_08_000100_inventory_warehouse_v201_foundation.php --force
```
Pratinjau tanpa menulis: tambahkan `--pretend`.

## 2. Setelah migration (data, bukan skema)
1. `php artisan inv:backfill-batch --check` -> tinjau -> `php artisan inv:backfill-batch` (membuat batch dari lot lama, menautkan kartu/palet).
2. `php artisan inv:revalue-per-item --check` -> tinjau -> `--apply` (titik peralihan). **Tidak mengubah nilai selama `INV_AVG_SCOPE=BIN`.**
3. Buka layar Valuation Check; setelah selisih 0 di semua item, ubah `INV_AVG_SCOPE` menjadi `ITEM` di Settings > Constants
   (total nilai persediaan sebelum = sesudah; dummy: 558.311.500).
4. Landed cost lama (bila ada): `LandedCostCards::sync()` sekali.
5. Isi data pemilik (bukan kode): Sampling Plan & Risk Class (QA periksa tabel ANSI Z1.4), grup `PROD_SPV`, `QA_HEAD`, `WH_HEAD`
   di Security bila ingin hak lebih rinci, persentase provisi di Settings > Constants.
6. `php artisan build:catalog` + `php artisan engine:catalog` di lingkungan rilis; `php artisan help:import` bila Help diimpor per lingkungan.

## 3. Penjadwalan
Lima perintah sudah ada di `routes/console.php` dan otomatis dilewati bila tabelnya belum ada:
`inv:reconcile` 04:00, `inv:qc-escalate` tiap jam, `inventory:booking-maintain` 01:45, `inv:count-due` 05:00, `inv:detect-shortage` 05:15.
Nyalakan hanya setelah migration & isian data (butir 2) selesai.

## 4. Risiko / yang perlu dicek user
- Akun baru di `fin_account` (data_origin LIVE) — persetujuan akuntan.
- Migration 000100 pada tabel kartu stok besar: ukur dulu di salinan.
- Belum ada layar yang diuji di browser oleh user; uji UAT di mockup.test dulu.
