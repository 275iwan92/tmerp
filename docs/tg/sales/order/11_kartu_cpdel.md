# Kartu konsep: CPDEL (Customer Plan Delivery)

> Ditulis 2026-10-16 (kelompok A). FAKTA = DB mockup (`ddt*` lewat `tm2*`, `--env=mockup`) dan kode bernomor baris, dilihat sesi ini. DARI DOKUMEN = belum dicek.

- **Jenis dokumen**: kartu konsep. **Modul/sub-modul**: Sales > Order > Customer Plan Delivery (`SALES_CPDEL`, `v2:cpdel`, FAKTA `sys_app_menu`).
- **Nama kanonik**: CPDEL = baris jadwal kirim `tm2sdi.co_order_delivery` (satu baris = satu pengiriman terencana dari satu baris CO), bersama log negosiasinya `co_delivery_negotiation`.
- **Sinonim**: Plan Delivery (nama lama), jadwal kirim, delivery schedule, slot kirim, `ship_date`, "janji ke customer".
- **BUKAN sama dengan**: Delivery Order (`do_delivery`, dokumen kirim fisik yang memotong sisa qty), Work Order. CPDEL murni jadwal; tidak menyentuh stok (REMARK layar dan `Cpdel.php:30-31`).

## Fakta
| Butir | Isi | Bukti |
|---|---|---|
| Tabel jadwal | `co_order_delivery`: 29 baris, PK `id_delivery`. Kolom: `order_id`, `item_id`, `shipment_type`, `ship_date`, `ship_time`, `ship_qty`, `original_date`, `ready_at`, `ready_source`, `ready_by`, `escalated_at`, `status` (PLANNED/SHIPPED/CANCELLED), `split_from_id`, `posted_at`, `posted_by`. FK: `order_id` dan `item_id` CASCADE, `split_from_id` SET NULL. | FAKTA, `show columns`; `referential_constraints` |
| Log negosiasi | `co_delivery_negotiation` (0 baris di dummy): `delivery_id` (FK CASCADE), `event`, `actor_role`, `old/new_date`, `old/new_time`, `old/new_qty`, `ready_at`, `reason_code`, `channel`, `customer_contact`, `decision`, `note`. | FAKTA, `show columns` |
| Alasan terlambat | `co_delay_reason`: 9 baris aktif (MATERIAL_LATE, MACHINE_DOWN, CAPACITY, REWORK, ARTWORK_LATE, PLANNING, TRANSPORT, CUSTOMER_REQUEST, OTHER), tiap alasan menunjuk departemen. | FAKTA, query |
| Lahir | Saat approval terakhir CO lolos (`CoOrderBuilder::confirm`): tiap baris PRODUCT yang belum punya jadwal mendapat 1 baris `shipment_type=FULL`, `ship_qty` = qty penuh, `ship_date` = `due_date` baris. Jadwal manual yang sudah ada tidak ditimpa. Detail: kartu engine `20_engine_co-order-builder.md`. | FAKTA, `CoOrderBuilder.php:231-246` |
| Sebaran data | Dari 29 baris (semua CO CONFIRMED): PLANNED 29, tidak ada yang lewat tanggal, 25 sudah `posted_at`, 0 punya `ready_at`, 0 punya `ship_time`, 0 punya `split_from_id`. Jumlah `ship_qty` per baris CO sama dengan qty baris (0 pengecualian). | FAKTA, query hari ini |
| Layar | Grid satu baris per jadwal dengan DUA janji: slot customer (`ship_date`+`ship_time`) dan janji produksi (`ready_at`). Hanya CO dengan `doc_status=CONFIRMED` tampil. | FAKTA, `Cpdel.php:40-43,45-74` |
| Split / pindah tanggal | `qty` kosong: hanya `ship_date` diganti. `qty` diisi: harus `0 < qty < ship_qty` baris asal; dibuat baris baru PLANNED (`shipment_type=PARTIAL`, `split_from_id`) di tanggal baru, sisa tetap di baris asal. Total qty tetap. Ditolak bila baris bukan PLANNED. | FAKTA, `resources/views/pages/co/delivery-plan.blade.php:111-155` (komponen v1 yang dipanggil `Cpdel::split`, `Cpdel.php:113-125`) |
| Gabung | Hanya baris PLANNED dari CO dan produk yang sama, minimal 2; pemenang = tanggal paling awal, qty dijumlah, baris lain DIHAPUS. | FAKTA, `delivery-plan.blade.php:175-207`; `Cpdel.php:127-137` |
| Post to Production | Aksi per baris: membuat WO lewat `WoGenerationService::generateFromDelivery` (kecuali WO tidak-batal sudah ada), lalu `posted_at/posted_by` diisi dan rencana PPIC dibuat ulang (Draft). Setelah posted, tanggal dan qty terkunci di CPDEL; hanya jam slot yang boleh berubah. | FAKTA, `Cpdel.php:76-103,113-137`; `CpdelPromiseService.php:167-169` |
| Gerbang ke DO | Jadwal baru boleh ditarik ke Delivery Order bila `posted_at` terisi (A.3). Penjaganya ada di layar v2 (`DeliveryOrder.php:66-72,115-132`), BUKAN di `DispatchService::pullLines`. | FAKTA, kode |
| Dua janji | Slot customer: tanggal+jam, dikelola Sales. Janji produksi `ready_at`: dikelola PPIC. Slot sah bila hari kerja Telasih (`sys_office_hour`, `sys_holiday`) dan di dalam jam terima customer (`cust_receiving_hour`, 7 baris). Aturan status/warna: kartu engine `21_engine_cpdel-promise.md`. | FAKTA, `CpdelPromiseService.php:106-128` |
| Konstanta | `CPDEL_DUE_SOON_DAYS` = 2 (kuning bila slot H-2 hari kerja atau kurang); `QUICK_JOB_MINUTES` = 30 (acuan saja, teks pada tombol Post). | FAKTA, `sys_constant` |
| Tertutup | Baris jadi SHIPPED hanya oleh `DispatchService::markShipped` (aksi Shipped pada DO), bila jumlah `do_line` SHIPPED >= `ship_qty`. DO CONFIRMED saja tidak mengubahnya; ia menahan sisa lewat view `v_co_delivery_outstanding`. | FAKTA, `DispatchService.php:258-290`; definisi view |
| Harga | Layar dan tabel CPDEL tidak memuat harga/HPP (tabel tanpa kolom harga; `Cpdel::rows` tanpa harga). | FAKTA, `show columns`; `Cpdel.php:46-73` |

## Relasi
- **sumber**: CO (`co_order`, `co_order_item.due_date`) lewat engine CO; konstanta; kalender kerja; jam terima customer.
- **dipakai_oleh**: Delivery Order (`do_line.co_delivery_id`, FK RESTRICT), WO (`prd_wo.source_delivery_id`), Planning PPIC (`PlanEvents::replan`), POD (janji = slot CPDEL paling awal, `DeliveryPromiseService::promise`), KPI pengiriman tepat janji.
- **mengalir**: CO CONFIRMED -> baris FULL -> (Split/Gabung/Pindah, nego) -> Post to Production -> ditarik ke DO -> DO Shipped -> baris SHIPPED -> POD.
- **zona beku**: flow dokumen CPDEL (aturan beku semua alur).

## Temuan (dicatat, tidak diperbaiki)
1. **Escalated tak bisa diputuskan setelah slot lewat**: `CpdelPromiseService::state` memeriksa `LATE` (baris 44) sebelum `ESCALATED` (baris 45), sedangkan tombol keputusan Operational Manager hanya muncul bila `state === 'ESCALATED'` (`Cpdel.php:201`). Baris yang ditolak customer lalu melewati tanggal slot menjadi LATE, tanpa keputusan Ops (Sales masih bisa menjadwal ulang). DARI KODE, belum diuji di layar.
2. **Gabung menghapus riwayat**: `confirmMerge` menghapus baris yang kalah (`delivery-plan.blade.php:201`); FK `co_delivery_negotiation` CASCADE sehingga log janji baris itu ikut hilang, padahal aturan layar: "janji awal tidak pernah berubah". `CpdelPromiseService::merge` hanya mencatat baris pemenang. FAKTA (kode+FK).
3. **Logika inti di komponen v1**: Split/Gabung ada di `pages::co.delivery-plan` yang dipanggil dari v2 (`Cpdel::component`). Pesan galatnya teks Indonesia literal tanpa `t()` (mis. baris 116, 178). FAKTA.
4. **Teks "SHIPPED otomatis begitu DO terbit"** di komentar dan banner v1 (`delivery-plan.blade.php:22,234`) tidak cocok dengan kode: SHIPPED hanya saat `markShipped`. FAKTA.
5. **Gerbang `posted_at` hanya di layar v2**: `DispatchService::pullLines` (jalur v1 `pages::dispatch.do.entry`) tidak memeriksanya (`DispatchService.php:22-109`). DARI KODE.
6. **Jumlah slot tidak dijaga**: tidak ada pemeriksaan jumlah `ship_qty` per baris CO = qty setelah qty baris diubah (hanya data hari ini yang cocok).
7. **Tidak ada data dummy untuk jalur nego**: `co_delivery_negotiation` 0 baris, `ready_at` 0 baris, `ship_time` 0 baris; jalur PPIC/Sales/Ops tidak punya contoh di dummy. FAKTA.
8. Dummy `shipment_type=DELIVER` (16 baris) di luar pilihan layar FULL/PARTIAL (lihat kartu CO, temuan 12). FAKTA.
9. Baris CO2600021 yang CANCELLED-tapi-CONFIRMED masih tampil di CPDEL (kartu CO, temuan 1). FAKTA.

## Sudah ada? Dicari di mana
Dicari: `co_order_delivery`, `co_delivery_negotiation`, `CpdelPromiseService`, `Cpdel`, `v_co_delivery_outstanding`, rute `v2.cpdel.*`, menu Sales > Order, registry engine (`cpdel`, `cpdel-promise`). Hasil: SUDAH ADA; tidak ada kolom baru diperlukan. Dokumen lama `docs/md_for_systems/SALES_QUO_ORDER, CPDEL.md` ada (DARI DOKUMEN, tidak dibaca di sesi ini).
