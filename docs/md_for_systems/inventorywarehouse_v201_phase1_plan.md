# Inventory & Warehouse v2.01 — Rencana Fase 1 (Fondasi)

> **Status: DRAFT rev.2 2026-10-08, menunggu persetujuan user sebelum kode. ATURAN USER: semua perubahan di-PREVIEW (JPEG) dan disetujui dulu; Warehouse Map, Stock Card, dan Stock Summary yang ada sudah benar dan tidak diubah.** Cakupan induk: `inventorywarehouse_v201_scope.md` (disetujui user 2026-10-08).
> Rencana ini memuat urutan kerja, skema rinci, perjalanan pengguna, mockup yang diperlukan, rencana uji dengan angka yang diharapkan, dummy, migrasi saldo, dan risiko.
> **Tidak menyentuh zona beku** (Inquiry Product, ProDev, ProCost) dan **tidak menulis ke `tm2*`**; semua pekerjaan di `ddt*` (mockup.test) sampai rilis.

## 1. Tujuan dan hasil akhir Fase 1

Setelah Fase 1 selesai, user dapat:
1. Melihat dan mengelola **master batch** (nomor batch, lot supplier, tanggal produksi, kedaluwarsa, status QC, sertifikasi).
2. Melihat saldo stok **per pemilik** (Company / Customer / Supplier) dan **per status batch** lewat layar Batch (tab Stock of this batch); stok pemilik Customer tidak dinilai dan tidak ikut Average. **Stock Card, Stock Summary, dan Warehouse Map tidak diubah.**
3. Mendapatkan **satu Average per item** untuk seluruh perusahaan: biaya keluar sama apa pun bin yang diambil, transfer tidak mengubah Average.
4. Membuat **lokasi bertipe** (penerimaan, karantina, WIP, scrap, dan virtual: transit, loss) dan mengatur aturan campur bin.
5. Mengisi semua batas yang terkait lewat layar pengaturan (aturan 7.12: setiap nilai punya inputan).

Fase 1 **tidak** memuat: sampling QC dan URGENT (Fase 2), booking dari costing (Fase 3), subkon dan titipan customer end-to-end (Fase 4), pick list.

## 2. Butir kebutuhan yang dikerjakan

| Kode | Butir | Catatan hasil pemeriksaan kode |
|---|---|---|
| I-03 | Master batch | Sekarang hanya teks `lot_no` di dokumen dan kartu stok |
| I-04 | Pemilik stok | Belum ada kolom pemilik |
| I-05 | Status stok per batch | Belum ada |
| V-01b | **Average per item** | Sekarang per bin (`StockCardChain`) |
| W-01 | Jenis area + area virtual | **8 jenis area sudah ada** (`WhZone::TYPES`: Receiving, QC Hold, Storage, Production Staging, Shipping, Reject, Customer Consignment, Tooling). Fase 1 hanya menambah **WIP, At Subcontractor, In Transit, Loss** (tiga terakhir virtual, tidak tampil di peta) |
| W-02 | **Aturan area**: Mixing, Pick order (FIFO / FEFO), hitung tiap N hari | **Belum ada di database** (hanya `max_stack`, `stack_height_m`); gambar peta lama baru rancangan. Kolom dibuat di `wh_zone`, bukan `wh_loc` |
| V-13 | Cut-off (tanggal posting mengikuti penerimaan fisik) | Hanya diperiksa dan diuji; `post_date`, `period_id` sudah ada |
| O-10 | Migrasi `lot_no` teks ke master batch | Satu batch per item + lot |
| I-42 | Larangan stok minus | **Sudah ada** (`StockPostingService` menolak saldo minus); tidak dikerjakan ulang, hanya diuji pada rantai per item |
| V-05 | Kunci periode inventory | **Sudah ada** (`inv_period`); hanya diuji bersama rantai baru |

## 3. Urutan kerja (setiap langkah = satu commit + push, Agent: Jo/Iwan menurut hari)

| Langkah | Isi | Bergantung pada |
|---|---|---|
| **S0** | Pastikan cara menjalankan uji terhadap `ddt*` (lihat risiko R-1) dan catat baseline: jalankan suite inventory yang ada | – |
| **S1** | Migrasi skema (ddt saja): `inv_batch`, kolom pemilik dan batch, `inv_item_valuation`, jenis lokasi, atribut bin, kunci pengaturan | S0 |
| **S2** | Perintah `inv:backfill-batch`: batch dari `lot_no` kartu stok **dan** dari `inv_pallet` (item + lot, kedaluwarsa dari palet), `--check` dan idempoten | S1 |
| **S3** | Mesin Average per item: `ItemValuationChain` (baru), `StockPostingService` dan `StockTransferService` memakainya | S1 |
| **S4** | Perintah `inv:revalue-per-item` (peralihan saldo tanpa jurnal), `--check` dan idempoten | S3 |
| **S5** | Layar pengaturan (konstanta) dan jenis lokasi di layar Storage Areas / Bins yang ada | S1 |
| **S6** | Setelah mockup disetujui, layar: Batch master (grid + form), field Batch pada form Stock In, isian aturan area dan jenis area baru di Storage Areas. **Stock Card, Stock Summary, Warehouse Map tidak disentuh** | S1, persetujuan mockup |
| **S7** | Help (topik layar baru), kamus terjemahan, menu, `php artisan build:catalog --env=mockup` | S6 |
| **S8** | Dummy baru (bagian 7), uji penuh, perbandingan Σ nilai sebelum dan sesudah | S2 sampai S6 |

S1 sampai S4 adalah pekerjaan mesin tanpa layar baru; boleh dimulai setelah rencana ini disetujui. S6 menunggu mockup.

## 4. Skema rinci (ddt dulu; tm2 saat rilis)

Aturan: PK `id_<entitas>` BIGINT auto number; nama English; **tanpa FK lintas database**; `activate`; audit kolom; nilai default aman untuk data lama.

### 4.1 `tm2inv.inv_batch` (baru)
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id_batch` | bigint auto PK | |
| `item_class` | varchar(5) | RM / SFG / FG |
| `item_id` | bigint | logis: material_id / parts_id / product_id |
| `batch_no` | varchar(60) | unik per item_class + item_id |
| `supplier_lot` | varchar(100) null | lot dari supplier / mill |
| `supplier_id` | bigint null | logis ke `tm2mst.sup_*` |
| `production_date`, `expiry_date`, `retest_date` | date null | |
| `qc_status` | varchar(15) | QUARANTINE / CONDITIONAL / RELEASED / REJECTED / BLOCKED; data lama = RELEASED |
| `certification` | varchar(20) null | NONE / FSC / PEFC / lainnya |
| `food_grade` | tinyint | |
| `grain_direction` | varchar(10) null | LONG / SHORT (kertas) |
| `coa_path` | varchar(255) null | sertifikat analisis |
| `notes` | varchar(255) null | |
| `activate`, `created_at/by`, `updated_at/by` | | |
| Indeks | | UNIQUE (`item_class`, `item_id`, `batch_no`), indeks `expiry_date`, `qc_status` |

### 4.2 Kolom tambahan
| Tabel | Kolom baru |
|---|---|
| `rm_stockcard`, `sfg_stockcard`, `fg_stockcard` | `batch_id` bigint null (indeks), `owner_type` varchar(10) default `COMPANY`, `owner_id` bigint null |
| `rm_stock_d`, `sfg_stock_d`, `fg_stock_d` | `batch_id`, `owner_type`, `owner_id` |
| `inv_stock_layer` | tidak diubah (belum dipakai, FIFO ditunda) |
| `tm2mst.wh_zone` | `is_virtual` tinyint default 0, `mix_rule` varchar(12) default `ANY` (ANY / ONE_ITEM / ONE_BATCH), `pick_order` varchar(5) default `FIFO` (FIFO / FEFO), `count_every_days` smallint null |
| `tm2inv.inv_pallet` | `batch_id` bigint null (indeks). **Palet tetap satuan fisik berlabel** (item, lot, kode palet supplier, tanggal datang, kedaluwarsa, qty, bin, tumpukan); batch adalah master mutu per lot yang ditautkan palet. Kedaluwarsa: batch memberi nilai awal, palet boleh menimpa (perilaku `PalletService::suggestedExpiry` dipertahankan) |
| `tm2mst.wh_warehouse` | nilai `warehouse_type` baru `VIRTUAL` (gudang maya untuk area virtual) |
| `wh_zone.zone_type` | nilai baru **WIP, SUBCON, TRANSIT, LOSS** (delapan nilai lama tidak berubah; `QC_HOLD` sudah menjadi area karantina) |

`lot_no` **tidak dihapus** di Fase 1 (dipertahankan sebagai kolom lama sampai semua pembaca berpindah ke `batch_id`).

### 4.3 `tm2inv.inv_item_valuation` (baru): rantai nilai per item
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id_item_valuation` | bigint auto PK | |
| `item_class`, `item_id`, `part_id` | varchar(5), bigint, bigint default 0 | kunci item (FG: product + part) |
| `doc_date` | datetime | urutan rantai `(doc_date, id)` |
| `movement` | varchar(8) | OPEN, IN, OUT, ADJ (transfer **tidak** masuk rantai karena tidak mengubah total item) |
| `source_stockcard_id`, `source_class` | bigint, varchar(5) | baris kartu stok penyebab (logis) |
| `qty_bb`, `qty_in`, `qty_out`, `qty_adj`, `qty_eb` | decimal(20,4) | qty **total item** semua bin pemilik Company |
| `value_bb`, `value_in`, `value_out`, `value_adj`, `value_eb` | decimal(20,4) | |
| `avg_unitprice` | decimal(30,5) | |
| `period_id` | bigint null | |
| Indeks | | (`item_class`, `item_id`, `part_id`, `doc_date`, `id_item_valuation`) |

Perilaku:
- Baris kartu stok per bin tetap ada; `value_in` / `value_out` per bin = qty x Avg item **saat itu**; nilai saldo bin dibaca sebagai qty bin x Avg item **terkini** (laporan nilai memakai rantai item).
- Stok `owner_type <> COMPANY` **tidak** masuk rantai.
- Baris terakhir qty 0 memaksa `value_eb` = 0 (sisa pembulatan masuk ke baris keluar terakhir).

### 4.4 Pengaturan (`sys_constant`, bisa diubah di Settings > Constants)
| Kunci | Nilai awal | Arti |
|---|---|---|
| `INV_NO_LOT_BATCH_CODE` | `NO-LOT` | kode batch untuk lot kosong saat migrasi |
| `INV_ALLOW_NEGATIVE_STOCK` | 0 | pengecualian stok minus per lokasi tetap ditolak kecuali diubah (tahap lanjut) |

(Nilai pengaturan Fase 2 dan seterusnya: tenggat QC, booking basi, tabel sampel, dst. sesuai tabel O-5 di cakupan.)

## 5. Mesin Average per item: rancangan kerja

1. **`ItemValuationChain`** (kelas baru, satu pintu): `append(movement, ...)`, `recalculateFrom(row)`, `averageAt(item, date)`, `lock(item)`.
2. **`StockPostingService`**: IN memanggil `append`; OUT memakai `averageAt(item, doc_date)` untuk nilai keluar; ADJ plus masuk pada Avg, minus keluar pada Avg; semuanya di dalam satu transaksi dengan **kunci per item** (baris terakhir rantai `FOR UPDATE`).
3. **`StockTransferService`**: baris keluar dan masuk memakai Avg item; **tidak** menulis rantai item, tidak ada jurnal (tetap seperti sekarang).
4. **Transaksi mundur**: sisip dokumen, `recalculateFrom` menghitung ulang baris item sesudahnya; selisih nilai baris keluar dilaporkan (basis jurnal koreksi Fase 5, saran O-14).
5. **`StockCardChain`** tetap mengurus **qty per bin** (saldo awal, masuk, keluar, adjustment, saldo akhir) dan penolakan saldo minus; **tidak lagi** menghitung Avg.
6. Layanan lain yang membaca `avg_unitprice` (`StockLedger`, `WarehouseStock`, laporan Loss & Gain, data Sales, `MatRaw`, layanan Maintenance) **diperiksa satu per satu** dan dipindah ke Avg item; hasil pemeriksaan dicatat di commit S3.

## 6. Perjalanan pengguna dan mockup (aturan: konfirmasi alur dan tunjukkan JPEG sebelum membangun layar)

| # | Perjalanan | Layar | Mockup JPEG |
|---|---|---|---|
| J1 | Admin stok membuka **Inventory > Batch & Quality > Batch**, mencari batch per item, membuka satu batch, mengubah tanggal kedaluwarsa atau sertifikasi, melihat saldo batch per bin dan palet | grid + form | **M1**: `inv_v201_batch_grid.jpg`, `inv_v201_batch_form.jpg` |
| J2 | Admin stok membuka **Stock Card** dan **Stock Summary**: tampilan dan angkanya **tidak berubah sama sekali** (keputusan user: format yang ada adalah format yang biasa dipakai) | grid yang ada | **M2**: `inv_v201_stock_card.jpg` dan `inv_v201_stock_summary.jpg` (keduanya tidak diubah) |
| J3 | Operator membuat **Stock In RM**: kolom **Batch** (pilih batch yang ada atau buat batch baru dengan lot supplier dan kedaluwarsa) menggantikan teks Lot | form yang ada | **M3**: `inv_v201_stock_in_batch.jpg` |
| J4 | Admin gudang membuka **Storage Areas**, memilih **jenis area** (termasuk WIP dan area virtual) dan mengisi **aturan area** (Mixing, Pick order, hitung tiap N hari). **Warehouse Map tidak diubah** | form yang ada | **M4**: `wh_v201_storage_area.jpg` |
| J5 | Admin stok melihat **Stock Summary**: nilai akhir sama sebelum dan sesudah peralihan Average per item, dan Opening + In − Out + Adjustment = Closing di setiap baris | grid yang ada | `inv_v201_stock_summary.jpg` |

Mockup dibuat dengan skill `tmerp-ui-mockup` dan sudah dirender di `docs/mockups/`. **Tidak ada yang dibangun sebelum user menyetujui tiap gambar.** Gambar untuk layar yang sudah ada memakai kolom asli dari kode (`StockCardReport`, `StockSummaryReport`). Layar mengikuti objek DevLib (`x-dl::*`) dan komponen yang ada.

## 7. Dummy baru (aturan: dummy basi diganti, bukan ditambah)

Himpunan data `inventory_v201` di `ddt*` (tidak menyentuh `tm2*`), memakai alur dokumen sungguhan (tidak SQL langsung), isinya:

| Isi | Tujuan |
|---|---|
| Satu item kertas dengan **dua bin** di gudang berbeda | Menguji Average per item |
| Pergerakan persis tabel 8.3 di cakupan (saldo awal 1.000 kg @15.000, terima 500 @16.500, issue 800, terima 600 @18.000, issue 1.000) dibagi ke dua bin | Hasil akhir yang diharapkan: nilai keluar 12.400.000 dan 16.653.846, saldo 300 kg, 4.996.154 |
| Penerimaan **bertanggal mundur** (skenario 8.4) | Issue 800 kg menjadi 12.971.429 |
| Batch dengan kedaluwarsa dekat dan batch tanpa kedaluwarsa | Uji laporan dan filter |
| Satu lot **milik Customer** | Tidak dinilai, tidak ikut Average |
| Lokasi virtual (Transit, Loss) dan lokasi karantina | Uji jenis lokasi |

Dummy lama yang saldonya tidak konsisten dengan rantai baru **dihapus dan diganti** pada S8 (aturan user).

## 8. Rencana uji (angka diharapkan)

Setiap uji **membersihkan datanya sendiri** di `tearDown()` dan hasilnya **diverifikasi ke database** setelah dijalankan (aturan proyek).

| # | Uji | Skenario | Hasil yang diharapkan |
|---|---|---|---|
| T1 | `ItemAverageAcrossBinsTest` | Terima 1.000 kg @15.000 di bin A, 500 kg @16.500 di bin B. Issue 800 dari A, lalu 100 dari B | Average item 15.500; kedua issue bernilai 15.500/kg (12.400.000 dan 1.550.000). Kode lama memberi 16.500 untuk bin B |
| T2 | sama | Transfer 300 kg A ke B | Average dan nilai total item tidak berubah; tidak ada jurnal |
| T3 | `ItemAverageRisingPriceTest` | Tabel 8.3 lengkap | Avg 15.500 lalu 16.653,85; issue 12.400.000 dan 16.653.846; saldo 300 kg = 4.996.154; jumlah = 34.050.000 |
| T4 | `ItemAverageBackdatedTest` | Sisip penerimaan 600 kg @18.000 sebelum issue pertama | Issue 800 kg menjadi 12.971.429; selisih +571.429 dilaporkan; baris sesudahnya dihitung ulang |
| T5 | sama | Issue seluruh sisa | `value_eb` tepat 0 |
| T6 | `OwnerStockExcludedTest` | Terima 200 kg pemilik Customer | Tidak masuk rantai; Average item tidak berubah; tidak ada jurnal; tidak tampil di Available untuk perusahaan |
| T7 | `RevalueCommandTest` | `inv:revalue-per-item --check` lalu tanpa `--check`, lalu ulang | Tiap item: Σ nilai sebelum = Σ nilai sesudah; Avg awal = total nilai / total qty; `--check` tidak menulis; ulang tidak mengubah apa pun |
| T8 | `BatchBackfillTest` | Kartu stok dengan lot "A1", "A1", "B2", kosong | 3 batch (A1, B2, NO-LOT) untuk item itu; baris menunjuk `batch_id`; ulang tidak menggandakan |
| T9 | `NegativeStockOnChainTest` | Issue lebih dari saldo item | Ditolak utuh; tidak ada baris tertulis |
| T10 | `ClosedPeriodOnChainTest` | Dokumen bertanggal di periode CLOSED | Ditolak; koreksi lewat Adjustment di periode terbuka berhasil |
| T11 | `LocationTypesTest` | Buat zona Quarantine dan lokasi virtual Transit | Tersimpan; `is_virtual`, `mix_rule` terbaca; lokasi virtual tidak muncul di peta fisik |
| T12 | `PrimaryKeyRuleTest` (yang ada) | otomatis | Tabel baru lolos aturan PK BIGINT auto number |
| T14 | `ReportsUnchangedByCutoverTest` | Rekam Stock Card (kolom qty) dan Stock Summary (Opening, In, Out, Adjustment, Closing per kelas dan kategori) sebelum `inv:revalue-per-item`, jalankan peralihan, rekam lagi | Stock Card identik; Closing per kelas dan kategori identik; Opening + In − Out + Adjustment = Closing di setiap baris |
| T15 | Peta gudang tidak berubah | Data dan tampilan Warehouse Map sebelum dan sesudah migrasi skema | Sama; area virtual tidak muncul di peta |
| T13 | **Regresi** | `StockPostingOrderTest`, `StockTransferServiceTest`, `PalletServiceTest`, `StockReportsTest`, `PvStockIntegrationTest`, `BoardStockDocumentsTest`, `HelpContentTest`, `DevlibOnlyRatchetTest`, `MultiLanguageRatchetTest`, `InventoryReportInteractivityTest` | Semua tetap lulus; uji lama yang mengandung asumsi Average per bin diperbarui dan dicatat di commit |

## 9. Migrasi saldo ke Average per item (`inv:revalue-per-item`)

1. `--check`: untuk tiap item tampilkan Σ `value_eb` dan Σ `qty_eb` seluruh bin pemilik Company, Avg hasil, dan selisih yang akan terjadi (harus nol pada total).
2. Tanpa `--check`: tulis satu baris `OPEN` pada rantai item bertanggal peralihan dengan `qty_eb` dan `value_eb` total dan Avg = total nilai / total qty.
3. **Tidak ada jurnal**: nilai total item tidak berubah.
4. Baris riwayat lama di kartu stok **tidak dihitung ulang**.
5. Idempoten: item yang sudah punya baris `OPEN` dilewati.
6. Item dengan qty total 0 atau nilai tidak konsisten dicantumkan di laporan, tidak ditulis.
7. Untuk rilis ke `tm2*`: jalankan `--check` dulu, tinjau, lalu jalankan pada jendela tanpa transaksi stok, dan simpan keluaran laporan.

## 10. Risiko dan hal yang harus dipastikan

| # | Risiko | Penanganan |
|---|---|---|
| R-1 | **Uji proyek menulis ke `tm2*`**, sedangkan migrasi Fase 1 hanya boleh ke `ddt*` sampai rilis | Pada S0 verifikasi menjalankan suite dengan `--env=mockup` agar koneksi menunjuk `ddt*`; bila tidak bisa, putuskan bersama user (migrasi skema aditif ke `tm2*` lebih awal tanpa data) |
| R-2 | Posting item yang sama menjadi **serial** karena kunci per item | Uji beban kecil; volume pabrik ini rendah |
| R-3 | Nilai saldo bin pada baris lama bukan lagi penjumlahan yang konsisten | Laporan nilai membaca rantai item; baris bin hanya menyimpan nilai pergerakan |
| R-4 | Pembaca lain `avg_unitprice` terlewat | Pemeriksaan satu per satu (bagian 5.6) dan uji regresi |
| R-5 | Perubahan memengaruhi dokumen Procurement (penerimaan PV memposting stok) | `PvStockIntegrationTest` wajib lulus; urutan posting dipertahankan |
| R-6 | Terjemahan Indonesia label baru | Isi lewat Setup > Translation Dictionary; teks English, bantuan di Help |
| R-7 | View `v_rm_onhand` / `v_rm_available` belum mengenal pemilik dan status | Disesuaikan pada S1 agar stok pemilik Customer tidak dihitung Available untuk perusahaan |
| R-8 | Data lama dengan `lot_no` tidak rapi (spasi, huruf besar-kecil) | `inv:backfill-batch` menormalkan (potong spasi, seragamkan huruf besar) dan melaporkan kelompok yang digabung |

## 11. Yang membutuhkan keputusan user sebelum kode

1. **Setujui rencana ini** (urutan, skema, uji).
2. **Mockup M1 sampai M4**: **sudah dibuat** (enam JPEG di `docs/mockups/`); tunggu persetujuan per gambar.
3. **R-1**: izin memverifikasi cara menjalankan uji ke `ddt*` (`--env=mockup`); bila tidak bisa, pilihan penanganannya.

## 12. Riwayat

| Tanggal | Perubahan |
|---|---|
| 2026-10-08 | Rev.3: user memutuskan **Stock Card dibiarkan persis seperti sekarang** (tanpa kolom baru; gambar diganti replika tanpa perubahan) dan **aturan area** cukup tiga isian di gambar 6 (belum ada aturan lain) |
| 2026-10-08 | Rev.2: laporan Stock Card, Stock Summary dan Warehouse Map dinyatakan benar dan tidak diubah; delapan jenis area dan `inv_pallet` ternyata sudah ada (W-01 menyusut menjadi empat jenis baru, batch ditautkan ke palet, aturan area di `wh_zone`); mockup M1 sampai M4 dibuat; uji T14, T15 ditambahkan |
| 2026-10-08 | Draft pertama rencana Fase 1 |
