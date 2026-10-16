# Kartu ENGINE: Material Planning Engine (sisi antarmuka ke Sales dan Customer Order)

> DRAFT KELOMPOK C (2026-10-16), menunggu persetujuan Jo. Kartu ini sengaja HANYA mencakup sisi Sales/CO: apa yang engine baca dari Inquiry, Costing, Quotation, CO dan CPDEL, dan kapan ia terpicu oleh dokumen Sales. Perhitungan bahan (tinta, varnish, netting PO/PR, Buy) dibahas ringkas; kartu penuh milik modul Planning/Inventory. Sumber: `app/Services/Planning/MaterialPlanningEngine.php` (1055 baris, bagian demand, pre-booking, persist, confirm dibaca penuh; bagian tinta dan standar bahan `inkLines/standardLines` TIDAK dibaca), `PlanningServiceProvider.php`, `BookingMaintenance.php`. Tes dijalankan: `PreBookingTest` (9 lulus, 199 assert). Contoh angka dihitung dari data `ddt*` lewat `demands()`/`plan()` (hanya membaca).

- **Nama kanonik**: Material Planning Engine (MPS + MRP + Booking dalam satu engine). **Kelas**: `App\Services\Planning\MaterialPlanningEngine`. **Registry**: key `material-planning`, mode `suggest`, grup Planning. **Modul/sub-modul**: Production > Planning (menu); antarmuka hulu ke Sales > Order > Customer Order.
- **Status beku**: flow CO/CPDEL = zona beku. Engine hanya MEMBACA dokumen Sales dan master; yang ditulis hanya booking (`rm_booking`, `rm_booking_d`, `rm_booking_alloc`) di `tm2inv`.
- **Sinonim**: pre-booking, booking CO, MRP, B0/B1/B2/B3, Pick Item, Hold. **Kartu tetangga (Sales)**: `10_kartu_customer-order.md`, `11_kartu_cpdel.md`, `20_engine_co-order-builder.md`, `21_engine_cpdel-promise.md`, `../quotation/10_kartu_quotation.md`.
- **BUKAN sama dengan**: `GateService` (gerbang kesiapan WO PPIC; hanya membaca hasil `plan()` lewat `$this->mrp->plan()['lines']`, `GateService.php:57`) dan `BookingMaintenance` (kartu terpisah di registry, `booking-maintenance`).

## Pemicu
FAKTA, dari kode:
| Pemicu | Method | Berkas:baris |
|---|---|---|
| CO berubah `doc_status` atau `stat_cancel` (Confirm CO, Back to Draft, Cancel) | `persist` bila CONFIRMED; `release` bila batal atau tidak CONFIRMED | `app/Providers/PlanningServiceProvider.php:22-35` (event model `CoOrder::updated`, dibungkus `try` sehingga kegagalan tidak menggagalkan Confirm CO) |
| WO lahir dari CO (Post to Production) | `persist` lalu tulis `wo_id` pertama ke header | `PlanningServiceProvider.php:37-47` (event `PrdWo::created`, hanya bila `source_co_id` ada) |
| Jadwal malam 01:30 | `runAll` (lalu `runPre`) | `routes/console.php:29` -> perintah `planning:run` (`PlanningRun.php:22`) |
| Jadwal malam 01:45 | `BookingMaintenance->run` -> `release` booking basi | `routes/console.php:53` -> `inventory:booking-maintain` (`BookingMaintain.php:27`) |
| Tombol di layar v2 (Recalculate All, Recalculate satu CO, Confirm, Back to Draft, Pick Item, Hold, Release Hold, Recalculate Pre-bookings) | `runAll`, `persist`, `confirm`, `unlock`, `pick`, `hold`, `releaseHold`, `runPre` | `routes/v2_planning.php:19, 31, 37, 41, 62` dst.; layar `material-booking`, `material-planning`, `material-prebooking` |
| Layar membaca hasil | `plan` | `app/Support/V2/Data/MaterialPlanning.php:110`, `PreBookings.php`, dan `GateService` |
Tidak dipicu langsung oleh Costing Engine, Quotation, atau Reverse (FAKTA, grep); pre-booking baru muncul saat `runAll`/`runPre` berikutnya (jadwal malam atau tombol).

## Alur (kembaran teks dari diagram `../img/material-planning.svg`)
1. **Demand dari Sales**: (a) tiap CO aktif (`DRAFT`, `SUBMITTED`, `CONFIRMED`, tidak batal) per baris PRODUCT, per jadwal kirim CPDEL berstatus PLANNED (tanpa jadwal: satu baris dengan `due_date` item); (b) sisa forecast MPS; (c) pre-booking dari costing PUBLISHED yang belum punya CO aktif.
2. **Tanggal butuh** per baris (rumus di bawah).
3. **Urutan prioritas**: CO Confirmed (1), CO Draft/Submitted (2), Forecast (3), Quotation (4), Pre-booking costing (5); lalu tanggal butuh; lalu id CO.
4. Stok barang jadi produk yang sama dibooking dulu; sisa qty dipecah ke bahan (kertas dari layout ProDev CONFIRMED, lalu dari costing inquiry, lalu dari BOM master; tinta dan bahan lain dari standar pemakaian).
5. **Netting** per bahan: stok tersedia, PO tepat waktu, PR, PO/PR telat, lalu SHORT.
6. **Tulis booking** hanya untuk CO CONFIRMED (`persist`, status DRAFT, lunak) dan pre-booking (`persistPre`). Booking yang sudah di-Confirm PPIC beku: dibaca dari DB, tidak dihitung ulang; CO yang berubah sesudahnya hanya memunculkan peringatan "CO changed" (sidik jari).
7. Satu rantai satu header: pre-booking inquiry yang sama diambil alih CO saat CO CONFIRMED (`adoptPre`, B0/B1 menjadi B2/B3).
**Titik keputusan officer (A.7)**: PPIC Confirm / Back to Draft, Pick Item (memilih item bahan), Hold; engine hanya mengusulkan.

## Input / Output
- **Input (sisi Sales, dibaca)**: `tm2sdi.co_order` (`doc_status`, `stat_cancel`, `co_date`, `co_no`, `customer_id`), `co_order_item` (`line_kind` PRODUCT, `product_id`, `qty`, `due_date`, `inquiry_id`), `co_order_delivery` (`status` PLANNED, `ship_qty`, `ship_date`), `iqp_costing` (`published_flag`, `reversed_at`, `inquiry_id`, kolom ukuran/qty saja; kolom harga tidak dibaca), `iqp_costing_part`, `iqp_inquiry` (`stat_cancel`, `due_date`, `product_id`), `iqp_tier`, `quo_quotation` + `quo_quotation_item` (`doc_status`, `stat_cancel`, `valid_until`); lainnya: `tm2prd.prd_demand_forecast`, master produk/bahan, `tm2inv.v_rm_available`, `tm2pur.po_line`/`pr_line`, `sys_holiday`, `sys_office_hour`; konstanta `BOOKING_NEED_WORKDAYS` (3), `INV_PRE_BOOKING_NEED_WORKDAYS` (14), `MRP_HORIZON_DAYS` (90), `BOOKING_DEFAULT_WASTE_PCT` (5), `INV_BOOKING_HOLD_WORKDAYS` (3), `PURCHASE_LEAD_DAYS_DEFAULT` (7) (nilai: DB mockup, 2026-10-16).
- **Output (menulis)**: `tm2inv.rm_booking` (header: tipe CO/PRE, tahap B0-B4, `chain_key` = id inquiry, `co_fingerprint`, hold), `rm_booking_d`, `rm_booking_alloc`. Tidak menulis tabel Sales.
- **Siapa boleh menimpa**: engine menimpa booking berstatus DRAFT; booking CONFIRMED/HELD hanya diubah PPIC (Back to Draft, Release Hold) atau `release` oleh pembatalan CO / booking basi.

## FORMULA
1. **Tanggal butuh baris CO**: `batas = co_date + BOOKING_NEED_WORKDAYS hari kerja`; `need = due (tanggal kirim CPDEL atau due_date item)` bila `due < batas`, kalau tidak `batas` (baris 145-170). Hari kerja = bukan hari libur `sys_holiday` aktif dan bukan hari non-kerja `sys_office_hour` (DB mockup: hanya Minggu = 7; 51 hari libur aktif). Contoh dihitung: CO2600022 `co_date` 2026-10-01 (Kamis), due 2026-10-30 -> `need` 2026-10-05 (aturan "CO date + 3 working days"); cek tangan: Jumat 10-02 (1), Sabtu 10-03 (2), Minggu libur, Senin 10-05 (3). Hasil engine sama.
2. **Sisa forecast** per customer x produk x bulan: `sisa = qty_forecast - jumlah qty item CO bulan itu` (bulan = `due_date`, atau `co_date` bila kosong); baris dibuat bila `sisa > 0`; tanggal butuh = hari kerja pertama pada atau sesudah awal bulan itu (bila awal periode sudah lewat: hari kerja berikutnya setelah hari ini); horizon `MRP_HORIZON_DAYS`.
3. **Pre-booking**: costing `published_flag = 1`, belum `reversed_at`, inquiry tidak batal, inquiry belum punya CO aktif dan belum Hold. Qty = qty tier utama inquiry (`is_primary` lalu `tier_seq`). Status quotation inquiry: `ACTIVE` (ada quotation terbit, belum kedaluwarsa) -> tahap B1, tier 4; `NONE` (tanpa quotation atau masih draft) -> B0, tier 5; `DEAD` (batal atau semua kedaluwarsa) -> tidak dihitung. Tanggal butuh = `due_date` inquiry bila ada, kalau tidak hari ini + `INV_PRE_BOOKING_NEED_WORKDAYS` hari kerja.
4. **Lembar kertas per bagian**: `lembar = ceil( ceil(qtyBagian / UP) * (1 + waste/100) )`, `qtyBagian = qty_dibuat * qty_per_sku`; UP dan waste dari layout ProDev CONFIRMED (`up_count` = UP per plano; layout gabungan memakai `qty_up_in_group` bagian itu; `waste_pct` layout atau `BOOKING_DEFAULT_WASTE_PCT`), kalau tidak dari costing (`UP = round(i_cut_per_plano * max(1, i_isi_per_lb))`), kalau tidak dari ukuran bahan BOM vs bentangan (`max(floor(W/w)*floor(H/h), floor(W/h)*floor(H/w))`) (baris 546-585).
5. **Netting** (`net`): per baris urut prioritas (tier, tanggal butuh, CO): ambil stok tersedia dahulu, lalu PO/PR yang belum terpakai (dalam satuan stok): tepat waktu dahulu (tanggal <= tanggal butuh), baru yang telat; sisa = `SHORT`. Status: `SHORT`, `LATE_PO`, `LATE_PR`, `PEGGED_PR`, `PEGGED_PO`, `STOCK`.
6. **Sidik jari CO** = `sha256` dari item CO (`id_item`, `product_id`, `qty`, `due_date`) dan jadwal PLANNED (`id_delivery`, `ship_qty`, `ship_date`) (baris 993-998).

**Contoh angka (DB mockup, `demands()` dan `plan()`, hanya membaca)**: 33 baris demand: CO Confirmed (tier 1) 28, CO Draft/Submitted (tier 2) 2, Forecast (tier 3) 3; 1 pre-booking (B1). `plan()` menghasilkan 321 baris bahan dan 18 bahan; status baris: SHORT 162, NEED_ITEM 103, STOCK 51, NEED_LAYOUT 3, FG_STOCK 2. Angka ini mencerminkan data dummy (stok dan PO/PR sedikit), bukan kebenaran bisnis.

## Kasus tepi
- CO tanpa jadwal CPDEL: satu baris dengan `due_date` item (`demands`).
- Booking CONFIRMED: CO-nya dikeluarkan dari perhitungan ulang (`$frozenCos`), dibaca dari booking.
- Part tanpa layout/costing/BOM: baris `NEED_LAYOUT`; bahan belum dipilih: `NEED_ITEM` sampai PPIC Pick Item (pilihan disimpan di booking, bukan di Master Product).
- Hold hanya untuk booking tanpa CO; booking CO memakai Confirm.
- Kegagalan engine saat pemicu CO diam-diam dicatat (`report($e)`) dan tidak menggagalkan Confirm CO; lingkungan tanpa kolom `rm_booking.co_id` dilewati.

## Pemakai (relasi)
- **sumber**: Customer Order, CPDEL, Costing (published), Quotation, Inquiry, ProDev (layout). **dipakai_oleh**: layar Material Planning/Booking/Pre-booking (Production), `GateService` (gerbang WO PPIC), peringatan layar pembuka. **hilir**: PR/PO (tombol Buy, Purchasing), Pick List, Stock (booking mengurangi Available hanya bila Confirm atau Held).

## Butir terbuka
ATTENTION-ENGINE-02 (angka Available beda antar layar; di berkas ini `v_rm_available` dibaca di baris 684 dan 922, sama dengan butir 02), ATTENTION-ENGINE-03 (Release tidak membuat booking), ATTENTION-ENGINE-05 (Purchase belum membuat PR). Status tetap di `docs/ENGINE-ATTENTION.md`, tidak disalin.

## Temuan (dicatat, tidak diperbaiki)
1. Registry langkah pre-booking menulis "from the moment costing has calculated". FAKTA kode: syaratnya `published_flag = 1` (`preDemands`, baris 222). Costing yang hanya CALCULATED tidak menjadi pre-booking.
2. Registry langkah netting menulis "Confirmed first, then Draft, then Forecast"; kode menambah Quotation (tier 4) dan Pre-booking (tier 5) setelah Forecast.
3. Method publik yang ada tetapi tidak tercantum di registry `methods`: `persistPre`, `preDemands`, `explodePre`, `hold`, `releaseHold`, `linesForCo`, `fingerprint`, `leadDays`, `standards`, `material`, `isWorkday`, `addWorkdays`, `subWorkdays`, `pickKey`, `forget` (statis). Sepuluh method yang terdaftar semuanya ada.
4. Registry `reads` tidak menyebut `quo_quotation`, `iqp_costing`, `iqp_inquiry`, `iqp_tier`, `prd_demand_forecast`, `sys_holiday`, `sys_office_hour`.

## Tes
`tests/Feature/PreBookingTest.php` (9 tes: pre-booking dari costing, satu header, Pick Item, B1 dari quotation dan akhir rantai, Hold, penolakan Hold untuk booking CO, CO mengambil alih header, booking basi, layar dan rute), `PreBookingFgAvailabilityTest.php`, `MrpBuyTest.php` (tombol Buy; tidak dijalankan sesi ini), `WoAvailableTest.php`. Tes menulis ke DB sungguhan dan membersihkan sendiri.
