# Kartu konsep: Proof of Delivery (POD, bukti terima)

> Ditulis 2026-10-16 (kelompok A). FAKTA = DB mockup (`ddt*` lewat `tm2*`) dan kode bernomor baris, dilihat sesi ini. Tabel DO/baris: kartu `10_kartu_delivery-order.md` (tidak diulang).

- **Jenis dokumen**: kartu konsep. **Modul/sub-modul**: Sales > Delivery > Proof of Delivery (`SALES_POD`, `v2:proof-of-delivery`, FAKTA `sys_app_menu`).
- **Nama kanonik**: POD = `tm2sdi.do_delivery_pod`: satu baris = satu kali truk tiba di customer (percobaan ke-n) untuk satu Delivery Order.
- **Sinonim**: bukti terima, tanda terima, kedatangan, arrival, janji tepat waktu, on-time delivery (KPI), `attempt_no`.
- **BUKAN sama dengan**: Delivery Order (dokumen kirim), status SHIPPED pada DO (aksi gudang/pengiriman, bukan kedatangan), Goods Receipt (pembelian).

## Fakta
| Butir | Isi | Bukti |
|---|---|---|
| Tabel | `do_delivery_pod`: 1 baris. PK `id_pod`; `delivery_id` (FK CASCADE ke `do_delivery`), `attempt_no`, `promise_date`, `promise_time`, `arrived_at`, `result`, `received_by`, `miss_cause`, `promise_kept`, `photo_path`, `gps_lat`, `gps_lng`, `notes`. | FAKTA, `show columns`; `referential_constraints` |
| Hasil | `result`: RECEIVED, PARTIAL, CUSTOMER_CLOSED, REJECTED, NOT_SHIPPED, ACCIDENT. `arrived_at` wajib kecuali NOT_SHIPPED/ACCIDENT; `received_by` wajib untuk RECEIVED/PARTIAL. | FAKTA, `DeliveryPromiseService.php:22-23,86-95` |
| Janji | `promise_date/_time` = slot CPDEL paling awal dari baris yang ditarik ke DO ini; tanpa tautan CPDEL = `scheduled_ship_date`, lalu `do_date`, tanpa jam. | FAKTA, `DeliveryPromiseService.php:34-44` |
| Janji tepat | `promise_kept` = result RECEIVED DAN tanggal tiba = tanggal janji DAN (tanpa jam janji ATAU jam tiba <= jam janji). Selain itu meleset, dan penyebab wajib dipilih. | FAKTA, `DeliveryPromiseService.php:46-53,102-104` |
| Penyebab | 8 penyebab, masing-masing menunjuk departemen: Truck late (Delivery), Production not ready (Production), Slot outside customer hours (Sales), Material late (Purchasing), Quality (Production), Customer (Customer), Accident (Delivery, otomatis bila hasil ACCIDENT), Other. | FAKTA, `DeliveryPromiseService.php:26-29,99-101` |
| Qty diterima | Hasil RECEIVED: tiap baris DO `qty_received = qty_do`. PARTIAL: isian per baris (kosong = qty_do), dijepit antara `qty_invoiced` dan `qty_do`; kurang dari `qty_do` wajib `receive_diff_reason` (DAMAGED, RETURNED, SHORT, OTHER). Hasil lain tidak mengubah baris. Dilewati bila kolom `receive_diff_reason` belum ada di DB. | FAKTA, `DeliveryPromiseService.php:31-32,105-110,129-152` |
| Foto | Opsional, jpg/jpeg/png/webp/pdf maks 10 MB, disimpan di disk `public` folder `pod/YYYYMM`. | FAKTA, `routes/v2.php:1597-1601`; `DeliveryPromiseService.php:116` |
| Layar | Daftar = DO berstatus CONFIRMED atau SHIPPED (bukan SUBCON), status tampilan: Waiting (belum ada kedatangan), Kept, Missed, Retry (CUSTOMER_CLOSED/REJECTED/ACCIDENT, kirim ulang). Hak catat: modul SALES. | FAKTA, `ProofOfDelivery.php:18-44`; `routes/v2.php:1597-1599` |
| KPI | Per bulan, tiap DO dihitung SEKALI menurut kedatangan pertamanya (`attempt_no=1`) berdasarkan `promise_date` di bulan itu; `pct` = kept / total; meleset dikelompokkan per departemen penyebab dan per hasil. | FAKTA, `ProofOfDelivery.php:46-57` |
| Data | 1 baris: DO/26/00001, RECEIVED, tiba 01-Okt-2026 10:00 pada janji 01-Okt (tanpa jam janji), `promise_kept=1`, tanpa foto. Konsisten dengan aturan "janji tepat" di atas. | FAKTA, query hari ini |
| Harga | Tidak memuat harga. | FAKTA, `show columns` |

## Relasi
- **sumber**: `do_delivery` + `do_line`, slot CPDEL (`co_order_delivery`), jam terima customer (`cust_receiving_hour`).
- **dipakai_oleh**: Customer Invoice (`CustomerInvoiceService` menyebut POD; dasar tagih = `do_line.qty_received`, A.21 DARI DOKUMEN), KPI semua departemen, Delivery Track, cetak POD (`ProofOfDeliveryPrint`).
- **mengalir**: DO CONFIRMED/SHIPPED -> kedatangan dicatat -> `promise_kept` dan `qty_received` -> tagihan.
- **zona beku**: flow dokumen Delivery (aturan beku semua alur).

## Temuan (dicatat, tidak diperbaiki)
1. **POD tidak mengubah status DO**: `DeliveryPromiseService::record` hanya menulis `do_delivery_pod` dan `do_line` (baris 112-119); status DO tetap. DO/26/00001 berstatus CONFIRMED padahal sudah RECEIVED, dan tidak punya baris (lihat kartu DO temuan 1). FAKTA.
2. **GPS tak terpakai**: `gps_lat`, `gps_lng` tidak ditulis atau dibaca di luar model (grep `app/ resources/ routes/`: nol). FAKTA.
3. **Foto dapat ditaruh di disk publik** (`public`) tanpa pemeriksaan hak baca saat diunduh; apakah ada penyaringan tidak diperiksa. DARI KODE (baris 116), belum diuji.
4. **KPI memakai `attempt_no=1`**, jadi pengiriman ulang yang berhasil tidak memperbaiki angka; sesuai komentar kode ("janji pertama yang menentukan"), bukan dokumen aturan; kesesuaian dengan kehendak Jo BELUM dicek.
5. **Tidak ada tes** untuk `DeliveryPromiseService` atau `ProofOfDelivery` (grep `tests/`: hanya `StageBuilder` dummy memanggil `record`). FAKTA.
6. **Kolom `qty_received` hanya ada bila migrasi invoice dijalankan**: kode memeriksa kolom saat berjalan (`Schema::hasColumn`, baris 131) sehingga perilaku bergantung pada DB. FAKTA (kode); di dummy kolom ada.

## Sudah ada? Dicari di mana
Dicari: `do_delivery_pod`, `DeliveryPromiseService`, `ProofOfDelivery`, rute `pod.save`, menu Sales > Delivery. Hasil: SUDAH ADA; tidak perlu tabel baru. Engine `DeliveryPromiseService` (aturan janji tepat, jam terima, qty diterima) belum punya kartu ENGINE; tertulis di halaman sub-modul Delivery.
