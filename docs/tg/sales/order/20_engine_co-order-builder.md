# Kartu ENGINE: CoOrderBuilder (pembuat Customer Order dan engine CPDEL)

> Ditulis 2026-10-16 (kelompok A). Sumber: `app/Services/Sales/CoOrderBuilder.php` (299 baris) dibaca penuh, plus `CoDueDate.php`, `CoOrder.php`, `CoOrderItem.php`. Tes dijalankan: `CoDueDateTest` (2 lulus, 4 assert). Tabel/kolom: lihat kartu konsep `10_kartu_customer-order.md` dan `11_kartu_cpdel.md` (tidak diulang di sini). Tidak ada bagian rahasia: engine ini tidak membaca HPP/margin (A.8-A.9), hanya `tolerance_pct` tier.

- **Nama kanonik**: CoOrderBuilder. **Kelas**: `App\Services\Sales\CoOrderBuilder` (semua method statis). **Registry**: key `cpdel`, nama "CPDEL Engine (Customer Plan Delivery)", mode `auto`, grup Sales Order, method terdaftar hanya `submit` dan `confirm`. **Modul/sub-modul**: Sales > Order.
- **Status beku**: flow dokumen CO dan CPDEL beku. Engine ini bukan penyaran; ia menjalankan aturan (A.1-A.2, A.4, A.10, A.11, A.15). Keputusan Confirm tetap di manusia/approval.

## Pemicu (tidak ada background / jadwal)
Dicari di `routes/console.php` (tidak ada jadwal CO), `app/Observers` (hanya 2, tidak terkait CO), Jobs, dan semua pemanggil. FAKTA: hanya dipanggil oleh aksi layar, seeder, dan migration.
| Method | Pemicu | Berkas:baris |
|---|---|---|
| `findOrCreateFromQuotation` | Tombol WIN Quotation (v2) | `app/Support/V2/Data/Quotation.php:284`; rute `routes/v2.php:442-445` |
| | Pilih Quotation di layar Customer Order (v2) | `app/Support/V2/Data/CustomerOrder.php:83` |
| | Layar v1 | `resources/views/pages/co/list.blade.php:149`, `pages/quotation/entry.blade.php:502` |
| | Seeder/migration dummy | `database/seeders/UatQuotationInquirySeeder.php:127`, `database/migrations/2026_09_21_000007_seed_inquiry_to_co_win_chain.php:139` |
| `createInstant` | Buat CO INSTANT dari pilihan customer (v2) dan Duplicate CO ditolak/batal | `CustomerOrder.php:88`, `CustomerOrder.php:241` |
| | v1, dummy PPIC, migration | `pages/co/list.blade.php:160`, `app/Console/Commands/PpicSeedDummy.php:77`, migration `…000007…:202` |
| `addProductLine` | Pilih Master Product pada CO INSTANT (v1 dipanggil dari komponen yang juga dipakai v2) | `pages/co/entry.blade.php:219`, `PpicSeedDummy.php:78`, migration `…:210` |
| `submit` | Tombol Confirm CO (v2) | `CustomerOrder.php:192` |
| `confirm` | Approval terakhir lolos | `CustomerOrder.php:223` (dalam `decide`) |
| | Jalur v1 tanpa approval, dan seeder | `pages/co/entry.blade.php:414`, `pages/co/list.blade.php:180`, migration `…:158,215` |
| `cancel` / `cancelBlocker` | Tombol Cancel CO (v2) | `CustomerOrder.php:267` |

## Input / Output
- **Input**: `QuoQuotation` PUBLISHED + item + inquiry; `CustCustomer` (profil pajak, termin, alamat bawaan); `ProdProduct` (INSTANT); `IqpCostingPriceTier.tolerance_pct`; `sales_person`; flow config `CO`; konstanta `CO_LEAD_WORKDAYS`; ACC Digital produk (lewat `CoDueDate`).
- **Output (menulis ke DB)**: `co_order`, `co_order_item`, `co_order_delivery` (hanya `confirm`), `iqp_inquiry` (`co_id`, `sub_state=CONVERTED`), `tm2mst.prod_product` dan part (lewat `ProductMasterSync`, dijalankan saat WIN), `flow_log` (lewat `FlowEngineService::submit`), status jadwal pada `cancel`.
- **Hanya mengembalikan**: `submit` dan `cancel` mengembalikan pesan galat atau null; `confirm` mengembalikan bool tanpa pesan.

## Alur (kembaran teks; diagram belum dibuat)
**A. WIN: `findOrCreateFromQuotation`** (`CoOrderBuilder.php:27-113`)
1. Bukan `PUBLISHED` -> null. Sudah ada CO dengan `quotation_id` itu -> kembalikan yang ada (1 Quotation = 1 CO).
2. Dalam transaksi `tm2sdi`: buat header `INTEGRATED`, `DRAFT`, nomor `nextCoNo`, `is_ppn` dari inquiry (`tax_scheme = PPN`) atau profil customer (`PPN_11`), diskon dari inquiry pertama berjenis `INQUIRY_TIER`, sales dari teks `pic_sales` (tidak cocok = NULL).
3. Tiap item Quotation menjadi baris CO: `FREEFORM` -> `SERVICE`, selain itu `PRODUCT`; qty, satuan, harga, diskon disalin; toleransi dihitung dari tier (rumus di bawah); `recalcSubtotal`.
4. Semua inquiry terkait: `co_id` diisi dan `sub_state=CONVERTED` (update massal, tidak memicu event model).
5. Tiap inquiry menjadi Master Product (`ProductMasterSync::fromInquiry`), `product_id` baris CO diisi.

**B. CO langsung: `createInstant` + `addProductLine`** (`:130-181`): header `INSTANT`, `quotation_id` NULL, termin/mata uang/PPN/alamat dari customer. Tiap baris dari Master Product: deskripsi `nama (kode)`, harga `default_selling_price` (0 bila kosong), satuan `uom_sales` (bawaan Pcs), `qty_min/max` dari master.

**C. Confirm: `submit` lalu `confirm`** (`:190-250`)
1. `submit`: bila DRAFT, hitung ulang due date baris otomatis (`CoDueDate::refresh`).
2. Tolak bila bukan DRAFT atau `isConfirmable()` salah (setiap baris PRODUCT harus punya due date, INSTANT juga harus punya produk).
3. Tolak bila ada produk dengan lebih dari satu baris qty dari quotation (A.15).
4. Tolak bila flow `CO` belum dikonfigurasi. Selain itu `doc_status=SUBMITTED` dan `FlowEngineService::submit('CO', id, 'SUBMITTED', net_receivable)`.
5. Approval terakhir -> `confirm`: hanya dari DRAFT atau SUBMITTED dan `isConfirmable()`; set `CONFIRMED`; untuk tiap baris PRODUCT yang belum punya jadwal buat 1 baris `FULL`, `ship_qty` = qty, `ship_date` = `due_date`, `PLANNED`. Baris yang sudah punya jadwal tidak disentuh.

**D. Batal: `cancelBlocker`/`cancel`** (`:253-287`): hanya CONFIRMED; tolak bila ada WO tidak-batal atau baris DO tidak-batal yang menunjuk jadwalnya (pesan memuat nomornya); jika lolos `CANCELLED`, `stat_cancel=1`, alasan, semua jadwal `CANCELLED`. Quotation tetap WIN dan inquiry tetap CONVERTED; order ulang = CO INSTANT (komentar kode baris 273).

## Formula (satu tempat; hanya apa adanya dari kode)
- Toleransi (baris 68-85): `tol = iqp_costing_price_tier.tolerance_pct` baris `tier_id` item (0 bila tidak ada tier); bila `tol > 0`: `qty_min = round(qty x (1 - tol/100), 2)`, `qty_max = round(qty x (1 + tol/100), 2)`; selain itu NULL. Contoh dari data mockup (CO2600022): qty 20.000, tol 5 -> 19.000 / 21.000; 13 dari 13 baris INTEGRATED cocok (query hari ini).
- Subtotal baris (`CoOrderItem.php:106-111`): `qty x unit_price x (1 - diskon/100)`, dibulatkan 2 desimal; bila `qty_minim`: `unit_price x (1 - diskon/100)`.
- Due date (`CoDueDate.php:29-40`): `addWorkdays(dasar, CO_LEAD_WORKDAYS)`; dasar INSTANT = `co_date`, selain itu `acc_at` ACC DIGITAL terbaru (NULL bila tidak ada). Contoh data: CO2600059 `co_date` 16-Okt-2026 + 14 hari kerja (Minggu libur) = 02-Nov-2026. Tes `CoDueDateTest`: Jumat 02-Okt-2026 + 14 hari kerja = Senin 19-Okt-2026.
- Total dokumen: `CoOrder::totals` (`CoOrder.php:183-221`), dijelaskan di kartu CO.

## Kasus tepi
- Quotation yang CO-nya sudah CANCELLED atau REJECTED tetap mengembalikan CO lama itu (baris 33-37), bukan membuat baru.
- Transaksi hanya pada koneksi `tm2sdi`; `ProductMasterSync` membuka transaksi sendiri pada `tm2mst` (`ProductMasterSync.php`, `DB::connection('tm2mst')->transaction`). Gagal di tengah tidak membatalkan keduanya bersama (DARI KODE, tidak diuji).
- Approval dengan `total net_receivable` sebagai `amount_snapshot`; angkanya dibaca saat submit, tidak diperbarui bila CO diubah sesudahnya (DRAFT sudah tidak bisa diubah setelah SUBMITTED, `CustomerOrder::save`, `abort_unless` DRAFT, baris 94).
- `confirm` diam-diam mengembalikan false (tanpa pesan) bila syarat tidak terpenuhi; pemanggil v1 mengabaikannya (`entry.blade.php:414`).
- `confirm` tidak memeriksa ulang aturan satu-qty (A.15) dan tidak memeriksa persetujuan; hanya `submit` yang memeriksa A.15.

## Pemakai
Layar Customer Order v2 dan v1, Quotation (WIN), seeder dummy, Planning (membaca CO CONFIRMED), Delivery Order (membaca jadwal yang dibuat engine ini), Customer 360.

## Butir terbuka
- Tidak ada nomor `ATTENTION-ENGINE` di `docs/ENGINE-ATTENTION.md` yang menyebut CO/CPDEL (dicari: cpdel, customer order, co_order, delivery; nol hasil).
- Registry (`EngineRegistry.php:375-403`) hanya mendaftarkan `submit` dan `confirm`; `findOrCreateFromQuotation`, `createInstant`, `addProductLine`, `cancel` tidak terdaftar, dan daftar `writes` tidak memuat `iqp_inquiry`, `prod_product`, `flow_log`. FAKTA.
- Konflik A.10 (proof FISIK) versus kode (ACC Digital): kartu CO, temuan 10.
- Pemicu A.11/A.15 hanya dijaga di `submit`/`decide` (v2): kartu CO, temuan 3.

## Tes
Tidak ada tes untuk `CoOrderBuilder` (grep `tests/`: nol). Yang menyentuh bagian terkait: `tests/Feature/CoDueDateTest.php` (lulus 2/2), `CoListExpandRowTest.php`, `QtyMinimLumpSumTest.php`. Usul (butuh izin Jo, tidak dikerjakan): satu tes WIN -> submit -> confirm yang membersihkan datanya sendiri.
