# Kartu konsep: Delivery Order (DO) dan Delivery Note (Surat Jalan)

> Ditulis 2026-10-16 (kelompok A). FAKTA = DB mockup (`ddt*` lewat `tm2*`, `--env=mockup`) dan kode bernomor baris, dilihat sesi ini. Tes dijalankan dengan `--env=mockup`: `DispatchLifecycleTest` (3 lulus, 19 assert), `DispatchNoMergeRuleTest` (2 lulus, 10 assert); jumlah baris tabel sebelum dan sesudah sama.

- **Jenis dokumen**: kartu konsep. **Modul/sub-modul**: Sales > Delivery > Delivery Order dan Delivery Notes (`SALES_DO` `v2:delivery-order`, `SALES_DN` `v2:delivery-notes`, `SALES_DN_SUBCON` `v2:subcon-delivery-note`; FAKTA `sys_app_menu`).
- **Nama kanonik**: Delivery Order = `tm2sdi.do_delivery` (header) + `do_line` (baris). **Delivery Note / Surat Jalan** = dokumen YANG SAMA (satu objek data, dua tampilan/cetakan): DO berstatus CONFIRMED atau SHIPPED, tanpa harga.
- **Sinonim**: DO, Surat Jalan, SJ, DN, dispatch, `do_no` (`DO/26/00001`), Surat Jalan Subkon (DO berjenis SUBCON).
- **BUKAN sama dengan**: CPDEL (jadwal, tidak memotong sisa qty), Proof of Delivery (`do_delivery_pod`, kartu `11_kartu_proof-of-delivery.md`), Goods Receipt.

## Fakta
| Butir | Isi | Bukti |
|---|---|---|
| Header | `do_delivery`: 3 baris. PK `id_delivery`, `do_no` UNIK dan NULL selama Draft, `do_type` (CUSTOMER / SUBCON), `is_ppn`, `do_date`, `customer_id` atau `supplier_id` (subkon), `pr_id`, `ref_part_id`, `subcon_process`, kolom alamat `ship_*`, `scheduled_ship_date`, `actual_ship_date`, `status`, `price_change_flag`, `price_change_approval_status`, `notes`. | FAKTA, `show columns` |
| Baris | `do_line`: 0 baris di dummy. Kolom: `co_order_id`, `co_order_item_id`, `co_delivery_id` (FK RESTRICT ke CO/jadwal), `item_description`, `qty_do`, `qty_received`, `receive_diff_reason`, `receive_note`, `qty_unit`, `unit_price`, `subtotal`, `price_overridden`, `original_unit_price`, `qty_invoiced`, `status` (PLANNED/SHIPPED/CANCELLED), `split_from_line_id`. | FAKTA, `show columns`; `referential_constraints` |
| Status | DRAFT -> CONFIRMED -> SHIPPED; CANCELLED dari DRAFT atau CONFIRMED saja, alasan wajib. Nomor `DO/yy/NNNNN` lahir saat Confirm (maks+1 per tahun). Sebaran data: DRAFT 1, CONFIRMED 2. Tidak ada kolom `stat_confirm/_cancel/_close`. | FAKTA, `DispatchService.php:190-255,258-290,292-313,318-330`; `DispatchLifecycleTest` |
| Sumber baris | Baris ditarik dari jadwal kirim CO lewat view `v_co_delivery_outstanding`: sisa = `ship_qty` - jumlah `qty_do` baris PLANNED/SHIPPED, jadwal bukan SHIPPED. Satu jadwal = satu baris DO (1:1, tidak pernah digabung). | FAKTA, definisi view; `DispatchService.php:17-109`; `DispatchNoMergeRuleTest` |
| Syarat tarik baris | Customer sama dengan DO, CO berstatus CONFIRMED, sisa > 0, status PPN sama dengan baris pertama (tidak boleh campur). `qty_do = min(sisa, ship_qty)`. Di layar v2 ditambah: jadwal sudah Post to Production (A.3). | FAKTA, `DispatchService.php:54-84`; `DeliveryOrder.php:66-72,115-132` |
| Qty | Naik/turun qty baris dibatasi `sisa + qty_do baris itu sendiri` (> 0). Confirm memeriksa ulang dengan kunci baris (`lockForUpdate`) dan menolak bila CO asal tidak lagi CONFIRMED. | FAKTA, `DispatchService.php:111-145,190-250` |
| Harga | `unit_price` disalin dari CO saat ditarik (snapshot). Diubah manual: `original_unit_price` diisi sekali, `price_overridden=1`, header `price_change_flag=1`. Subtotal = `qty_do x unit_price` (Qty Minim: nilai baris = harga). Cetak DO memberi 403 bila bukan pemegang hak harga; Surat Jalan tidak pernah menampilkan harga atau total. | FAKTA, `DispatchService.php:86-102,147-174`; `DeliveryOrderPrint.php:49`; `DeliveryNotePrint.php:11-14`; tes `test_delivery_order_print_shows_price_but_surat_jalan_never_does` lulus |
| Shipped | Aksi tunggal atas seluruh baris PLANNED; mengisi `actual_ship_date`; jadwal CPDEL jadi SHIPPED hanya bila jumlah `qty_do` SHIPPED >= `ship_qty` jadwal itu. | FAKTA, `DispatchService.php:258-290`; tes lifecycle |
| Batal | Baris DO jadi CANCELLED; sisa jadwal pulih sendiri lewat view. Alasan ditulis ke `do_delivery.notes` (menimpa catatan lama). | FAKTA, `DispatchService.php:292-313`; tes `test_cancel_from_confirmed_restores_qty_available…` |
| Subkon | DO `SUBCON` (kirim ke vendor, bukan customer): `supplier_id`, `subcon_process`, `pr_id`, tanpa `co_order_id` pada baris; layar sendiri; tidak ikut daftar DO/DN/POD. | FAKTA, `DeliveryOrder.php:36-39`; `DispatchService.php:231-234`; `SubconDelivery.php` |
| Layar | DO = `DeliveryOrder` (aturan komponen v1 `pages::dispatch.do.entry` → `DispatchService`); Delivery Notes = daftar DO CONFIRMED/SHIPPED; aksi save/pull/confirm/ship/cancel di rute `v2.do.save`. | FAKTA, `DeliveryOrder.php:10-31`; `DeliveryNoteListGrid.php:30-33`; `routes/v2.php:855-891` |
| Pemakai hilir | Customer Invoice membaca `qty_received`/`qty_invoiced` (dasar tagih = qty diterima, A.21). | FAKTA, `DeliveryPromiseService.php:124-152` (isi `qty_received`); A.21 DARI DOKUMEN untuk aturan tagih |

## Relasi
- **sumber**: CPDEL (`co_order_delivery`), CO (`co_order`, `co_order_item`: harga, deskripsi), master customer/supplier.
- **dipakai_oleh**: Surat Jalan cetak (QR "online document & proof of receipt"), POD (`do_delivery_pod.delivery_id`), Customer Invoice (`qty_received`), KPI ketepatan janji, Customer 360 (daftar kiriman), `QcTraceService`.
- **mengalir**: CPDEL (Posted) -> tarik ke DO DRAFT -> Confirm (nomor lahir, Surat Jalan siap cetak) -> Shipped (jadwal CPDEL SHIPPED) -> POD mencatat kedatangan dan qty diterima.
- **zona beku**: flow dokumen DO/DN (aturan beku semua alur). Dibaca: `docs/claude_vital_flows.md` (menu Delivery).

## Temuan (dicatat, tidak diperbaiki)
1. **DO tanpa baris**: 2 dari 2 DO yang CONFIRMED di dummy tidak punya `do_line` (DO/26/00001 customer, DO/26/00002 subkon). `DispatchService::confirm` menolak DO tanpa baris, jadi DO ini dibuat di luar service (dummy). FAKTA (query).
2. **Penanda asal data**: `DO/26/00001` bernilai `data_origin=LIVE` padahal catatannya "[UAT-CHAIN] dummy rantai Inquiry-Quotation-CO-Produksi" dan ada di database dummy. FAKTA.
3. **Kolom tak terpakai**: `price_change_approval_status` hanya tercantum di `$fillable` dan dibaca satu layar (`delivery-order.blade.php:70`), tidak pernah ditulis. `split_from_line_id` nol pemakaian di `app/`, `resources/`, `routes/`. `ref_part_id` ditulis satu tempat (`ProcurementBridge.php:224`). FAKTA (grep).
4. **Gerbang `posted_at` hanya v2**: `DispatchService::pullLines` tidak memeriksa Post to Production; hanya `DeliveryOrder::pull` (v2). Jalur v1 `pages::dispatch.do.entry` melewatinya. DARI KODE (kartu CPDEL temuan 5).
5. **Batal menimpa catatan**: `cancel` menulis alasan ke `do_delivery.notes` (baris 307), catatan sebelumnya hilang. Tidak ada kolom `cancel_reason` seperti dokumen lain. FAKTA.
6. **Penamaan status**: DO memakai `status` penuh kata, bukan `stat_confirm/_cancel/_close` (aturan penamaan CLAUDE.md butir 5); lihat kartu Status. FAKTA.
7. **Price override tanpa approval**: header mencatat `price_change_flag` tetapi tidak ada alur persetujuan (kolom approval kosong, temuan 3). Aturan A.9 hanya membatasi siapa yang melihat harga; apakah override harus disetujui BELUM dicek di dokumen.
8. **Kendaraan/pengemudi/berat/ikat** belum punya kolom; tercetak kosong untuk diisi tangan (komentar `DeliveryNotePrint.php:11-14`). FAKTA.

## Sudah ada? Dicari di mana
Dicari: `do_*`, `v_co_delivery_outstanding`, `DispatchService`, `DeliveryOrder`, `DoDelivery/DoLine`, menu Sales > Delivery, registry engine (tidak ada key khusus DO; `DispatchService` tidak terdaftar). Hasil: konsep SUDAH ADA; tidak ada tabel baru diperlukan. Dokumen lama `dispatch_tier*.md` disebut di komentar kode (DARI DOKUMEN, tidak dibaca; tier4 tertulis sudah diarsipkan).
