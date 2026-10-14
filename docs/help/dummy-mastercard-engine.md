# Engine data dummy Master Card (PDF -> set dummy berlevel)

> Versi HTML dengan diagram: `docs/help/dummy-mastercard-engine.html`. Patokan aturan: `docs/PATOKAN-DATA-DUMMY.md`.
> Dibuat 2026-10-14. Hanya situs dummy ddt* (mockup.test). Untuk debug dan ubah: bagian 5 dan 6.

## 1. Fungsi

PDF Master Card baru di `docs/attachments/mastercard/` dibaca, lalu dibuat data dummy **berlevel**: Inquiry, ProDev, ProCost, CO. Level N membawa semua level di belakangnya. Setiap set selalu punya dieline, artwork, ukuran P x L x T, dan ukuran datar (mm); kartu yang kurang tidak dibuat, alasannya dilaporkan.

Menjalankan:
- Layar: **Developer > Database > Master Card Dummy (PDF)** (`/v2/dummy/mastercard`, developer saja, hanya ddt*): info input / output (jumlah set per level) dan tiga tombol tanpa isian: **Make Master Product** (tiap PDF siap jadi Master Product langsung dari kartu), **Make dummy data** (PDF siap dibagi bergiliran ke Inquiry, ProDev, ProCost, CO; qty 5000, due 21), dan **Reset** (hapus semua hasil engine supaya data tidak bertumpuk; perintah: `--reset`). Perintah di bawah = cara dari terminal dengan kuota sendiri.
- Perintah: `php artisan dummy:mastercard --env=mockup --levels=inquiry:2,prodev:2,procost:2,co:2 [--qty=5000] [--due=21] [--path=docs/attachments/mastercard]`. Tanpa `--levels`: semua produk baru ke level Inquiry.

## 2. Alur proses

```
PDF + gambar extracted/<slug nama pdf>.png|jpg
  1. MasterCardPdfReader::read()      pdftotext -layout, nilai = milik label terdekat di atasnya
  2. skipReason()                     sudah ada (no. desain / kode item / produk aktif)  -> dilewati
                                      artwork / P x L x T / ukuran pisau / bahan kurang  -> dilewati + alasan
  3. kuota level                      urut berkas; kuota habis -> dilewati
  Level 1 inquiry()   : kartu Collection -> makeInquiry -> flat = ukuran pisau per potong -> urut bagian -> Sales Confirm
  Level 2 toProdev()  : openInProdev -> Prodev::saveLayout(layout dari kartu, confirm) -> SpecEngine: BOP, BOM, tinta
  Level 3 toProcost() : priceAndPublish (CostingEngine, harga contoh)
  Level 4 toCo()      : Quotation -> WIN (Master Product) -> CO: pilih qty, due date, Confirm, approval -> baris CPDEL
```

Berhenti di CO Confirmed. Post to Production, WO, dan plan tidak dijalankan engine.

## 3. Data flow

| Tahap | File / tabel dibaca | Tabel ditulis (ddt*) |
|---|---|---|
| Awal | PDF, `extracted/*`; `prod_master_card`, `prod_product` (cek ada); `cust_customer`, `mat_category`, `prod_process`, `ref_finishing_type`, `mat_join_type` | - |
| 1 Inquiry | kartu hasil PDF | `prod_master_card`, `prod_master_card_file` (SOURCE, ARTWORK), `cust_customer` (bila baru), `iqp_inquiry`, `iqp_tier`, `iqp_part`, `iqp_part_material`, `iqp_part_ink`, `iqp_part_finishing`, `iqp_part_process`; tanda `stat_confirm_sales`; berkas `master-card/`, `inquiry/{part}/artwork/` |
| 2 ProDev | `prod_machine`, `mat_plano_size`, `prod_bop_rule`, `mat_raw`, bagian Inquiry | `prod_product` (draft), `prod_part`, `prod_layout_group(+_part)`, `iqp_layout`, `prod_part_process`, `prod_part_material`, `prod_part_ink`; `stat_confirm_prodev` |
| 3 ProCost | `iqp_tier`, `iqp_part`, `mat_grammage` | `iqp_costing`, `iqp_costing_part`, `iqp_costing_price_tier`; `stat_confirm_procost`, `sub_state` |
| 4 CO | costing published, jabatan approver | `quo_quotation(+_item)`, `co_order`, `co_order_item`, `co_order_delivery`, `prod_product` (ACTIVE), `prod_part_*`, log approval; `iqp_inquiry.co_id/product_id`, `prod_master_card.status = PRODUCT` |

Cabang **product** (`--levels=...,product:n`, tombol Make Master Product): kartu -> `MasterCardService::makeProduct` -> Inquiry SUBMITTED + `ProductMasterSync` -> Master Product ACTIVE (`prod_product`, `prod_part_*`, `prod_part_artwork`), lalu ukuran datar bagian = ukuran pisau; tanpa ProDev, ProCost, Quotation, CO. Tabel: `prod_master_card(+_file)`, `iqp_inquiry`, `iqp_part_*`, `prod_product`, `prod_product_acc`, `prod_part`, `prod_part_*`.

Pelaku (user dummy): `yuna` Sales, `dedi` ProDev, `wiwik` ProCost, approver = pemegang jabatan langkah berjalan. User yang login dipulihkan setelah `run()`.

## 4. Aturan data dari kartu

| Isi kartu | Menjadi |
|---|---|
| Dimensi P / L / T | `dim_l / dim_w / dim_h` (mm) lalu `dieline_spec.dims` (tipe dari `MasterCardShape::suggestType`) |
| Ukuran Pisau, Isi dalam Satu Cetakan | ukuran datar per bagian (`iqp_part.flat_w/h`) = pisau / jumlah potong; UP dan susunan di layout ProDev |
| Ukuran Cetak | kanvas layout ProDev |
| Material, Kualitas, Ukuran Bahan | `iqp_part_material` (kategori master dari `MasterCardService::category`) |
| Urutan Pekerjaan | proses Inquiry (`iqp_part_process`); BOP akhir tetap hasil SpecEngine |
| Cetak di mesin ... | mesin cetak layout: VERSAFIRE / HANWAY / SUNTHINK / SAKURA, selain itu Komori GL440 |
| Warna Pertama / Kedua | `iqp_part_ink` |
| nama berakhiran A / B / SET n / COVER | label bagian; urut: COVER, lalu SET dari angka terbesar ke 1 |

## 5. Peta kode (untuk debug)

| Berkas | Isi |
|---|---|
| `app/Services/MasterCard/MasterCardPdfReader.php` | `read()`, `text()` (pdftotext), `pieces()` (potongan teks per baris), `colors()`, `steps()`, `sizes()`, `artwork()` |
| `app/Services/MasterCard/MasterCardDummyEngine.php` | `plan()`, `run()`, `groups()`, `skipReason()`, `one()`, `inquiry()`, `shapeParts()`, `toProdev()`, `layout()`, `press()`, `toProcost()`, `toCo()`, `approve()` |
| `app/Console/Commands/DummyMasterCard.php` | opsi perintah, tabel laporan |
| `routes/v2_dummy_mastercard.php`, `resources/views/v2/dummy-mastercard.blade.php` | layar info + tombol Run; dimuat dari `routes/web.php` |
| `app/Support/V2/AppMenu.php` | item menu Developer > Database |
| dipakai ulang | `MasterCardService` (fromData, storeFile, makeInquiry, refresh, existingProduct, customerId), `MasterCardShape` (knifePiece, pieces, parts), `Prodev::component()->saveLayout`, `SpecEngine::persist`, `InquiryStage`, `InquiryWriter`, `PricedDummyChain` (priceAndPublish, winViaQuotation), `CustomerOrder` (pickQty, save, confirm, decide) |

Cek cepat satu PDF (tanpa menulis):
```php
// php artisan tinker --env=mockup
(new App\Services\MasterCard\MasterCardPdfReader)->read(base_path('docs/attachments/mastercard/<file>.pdf'));
app(App\Services\MasterCard\MasterCardDummyEngine::class)->plan(base_path('docs/attachments/mastercard'));
```

Gejala umum:
| Gejala | Penyebab | Tindakan |
|---|---|---|
| `pdftotext tidak ditemukan` / `gagal` | engine mencari: env `PDFTOTEXT_BIN`, PATH, lalu pdftotext bawaan Git for Windows | pasang xpdf / poppler atau isi `PDFTOTEXT_BIN` di .env.mockup |
| Field kosong atau salah | label formulir bergeser | lihat `LABELS` dan `pieces()` di reader; uji dengan `read()` |
| "card incomplete: artwork" | gambar tidak ada di `extracted/` dengan nama slug PDF | taruh gambar, nama = `Str::slug(nama PDF tanpa .pdf)` |
| "incomplete: Customer (master)" | customer tidak ada atau placeholder kartu | isi kartu / buat customer |
| plan Draft tidak terbentuk setelah dummy lain | nama pisau > 60 karakter (`prd_wo.dieline_ref`) | pendekkan nama produk (gap engine, lihat analisis) |
| "tidak ada user aktif pemegang jabatan approver" | approver CO tidak punya user aktif | aktifkan user pemegang jabatan |

## 6. Cara mengubah

| Mau | Ubah |
|---|---|
| Level baru | tambah ke `LEVELS`, buat `toNama(IqpInquiry)`, urutkan; parsing kuota di `DummyMasterCard`; hitungan di `counts()` |
| Syarat kartu lengkap | `missing()` di engine |
| Pemetaan mesin cetak | konstanta `PRESS` di engine |
| Isi kartu baru (label baru) | `LABELS` dan pemetaan field di `MasterCardPdfReader::read()` |
| Folder lain | `--path`, atau path di route layar info |
| Qty, due date default | opsi `--qty`, `--due`; nilai awal form di view |

## 7. Menghapus hasil engine (tombol Reset atau `--reset`; urutan yang dipakai, per inquiry)

`co_order_delivery` -> `co_order_item` -> `co_order` -> `quo_quotation_item` -> `quo_quotation` -> `iqp_costing_part_coating`, `iqp_costing_part`, `iqp_costing_price_tier`, `iqp_costing` -> `prod_layout_group_part` -> `prod_layout_group` -> `prod_part_material/ink/process/finishing/artwork/attachment` -> `prod_part` -> `prod_product_acc`, `prod_product` -> `iqp_layout` -> `iqp_part_material/finishing/process/ink` -> `iqp_part` -> `iqp_tier` -> `iqp_inquiry`. WO dari CO itu dihapus dulu (`prd_wo_operation*`, `prd_wo_material`, `prd_wo`). Reset hanya menyentuh kartu Collection bersumber PDF (`source_kind = PDF`) beserta rantainya dan berkasnya; customer yang dibuat engine tetap ada.
