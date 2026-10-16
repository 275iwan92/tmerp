# Kartu konsep: Quotation (penawaran)

> Kelompok B, 2026-10-16, menunggu persetujuan Jo. FAKTA = dilihat sendiri (DB mockup `ddtsdi`, kode dengan baris). Angka harga hanya di bagian setelah penanda johiwan-only (A.8-A.9).

- **Jenis dokumen**: kartu konsep. **Modul/sub-modul**: Sales > Inquiry & Quotation > Quotation (item menu `v2:quotation`; laporan `Open Quotations`, `Follow-up Quotation`, `Win-Rate Sales` di grup Report).
- **Nama kanonik**: Quotation = `tm2sdi.quo_quotation` + `quo_quotation_item` (+ `quo_quotation_cc`).
- **Sinonim**: penawaran, QUO-nomor, Sales Quotation, PUBLISHED/WON/LOST.
- **BUKAN sama dengan**: Purchase Quotation (modul Procurement, layar `purchase-quotation`); Nota Inquiry (cetak harga per tier dari satu Inquiry); Customer Order (`co_order`).

## Fakta
| Butir | Isi | Bukti |
|---|---|---|
| Isi tabel (mockup) | `quo_quotation` 20 baris (DRAFT 2, PUBLISHED 18); `quo_quotation_item` 24 (semua `source_type='INQUIRY_TIER'`, semua punya `inquiry_id` dan `tier_id`); `quo_quotation_cc` 0. | FAKTA, query 2026-10-16 |
| Kolom header | `quotation_no` (unik), `customer_id`, `attn_contact`, `sales_pic` (teks), `revision_no`, `issued_date`, `valid_until`, `deadline_date`, `payment_term`, `tax_scheme` (EXCL), `currency_code` (IDR), `notes`, `doc_status`, `stat_cancel`, `cancel_reason`, `issued_by`. TIDAK ada `stat_confirm`/`stat_close`. | FAKTA, `show columns` |
| Kolom baris | `line_seq`, `source_type`, `inquiry_id`, `tier_id`, `description`, `spec_note`, `qty`, `qty_unit`, `unit_price`, `discount_pct`, `subtotal`. | FAKTA, `show columns` |
| Status | `doc_status` DRAFT -> PUBLISHED (tombol Confirm) -> CANCELLED. WON/LOST TIDAK disimpan di quotation; diturunkan dari `iqp_inquiry.sub_state` baris-barisnya (CONVERTED = WON; semua LOST = LOST). | FAKTA, `Data/Quotation.php:34-42`, `QuoQuotation.php:71-82` (`outcome()`) |
| Penomoran | `QUO` + 2 digit tahun + 5 digit urut, counter per tahun. | FAKTA, `QuoQuotation.php:103-114` (`nextQuotationNo`) |
| Inquiry yang boleh ditawarkan | `QuotationBuilder::availableInquiries`: costing `published_flag=true`, `customer_id` tidak NULL, `sub_state` bukan CANCELLED/LOST/CONVERTED, belum ada di quotation lain yang tidak CANCELLED. Satu quotation = satu customer. | FAKTA, `QuotationBuilder.php:46-57,125-134` |
| Wajib integrated (A.1) | Jalur normal: baris `INQUIRY_TIER` per tier dari costing PUBLISHED (+ baris tooling `INQUIRY_TOOLING` per jenis). Data: 24/24 baris tertaut ke costing PUBLISHED; 0 pelanggaran. | FAKTA, `QuotationBuilder.php:138-190`; query |
| 1 Quotation = 1 CO (A.10) | Indeks unik `co_order_quotation_id_unique` pada `co_order.quotation_id` (Non_unique=0); WIN memakai `findOrCreateFromQuotation` (idempoten: bila CO sudah ada, dikembalikan). Data: 13 CO INTEGRATED, tidak ada quotation dengan >1 CO; 12 CO INSTANT tanpa quotation. | FAKTA, `show index`; `CoOrderBuilder.php:27-40`; query |
| WIN | CO lahir dari seluruh baris quotation (snapshot harga, qty, toleransi dari tier costing), `sub_state` inquiry menjadi CONVERTED dan `co_id` diisi, lalu `ProductMasterSync::fromInquiry` membuat Master Product. | FAKTA, `CoOrderBuilder.php:42-112 (ProductMasterSync pada baris 105-109)` |
| Rute | `v2.quotation.create/save/add-inquiry/delete/cancel/lost/win/activity`; semua di grup `middleware('module:SALES')`. | FAKTA, `routes/v2.php:376-459` |
| Follow-up | Aktivitas follow-up disimpan di `crm_activity` (`QuoActivity` mewarisi `CrmActivity`); 15 baris di seluruh dummy (bukan hanya quotation). | FAKTA, `QuoActivity.php:5-7`; query |
| Visibilitas harga (A.8-A.9) | Penyaring harga di server (`SecUser::canViewSalesPrice`: grup SALES_STAFF, SALES_SPV, MGR_SALES, MARKETING, COSTING_STAFF, OPS_MGR, DIREKSI, atau admin, atau level manajemen >= 4) dipakai oleh Customer Order, Delivery Order, CRM, dan grid CO Report. TIDAK ditemukan pemakaian di Quotation maupun Inquiry. | FAKTA, `SecUser.php:285-290`; `grep canViewSalesPrice` (CoReportGrid, CustomerOrderPrint, DeliveryOrderPrint, Crm, Board, print-doc) |
| Isi grid Quotation | Kolom `total` (grand total) ikut dikirim ke setiap pengguna yang boleh membuka layar; baris anak (Expand) sengaja "tanpa harga". | FAKTA, `Data/Quotation.php:75-78` |

## Relasi
- **sumber**: Inquiry (costing PUBLISHED) lewat `QuotationBuilder`; harga dari `iqp_costing_price_tier` (deklasifikasi ke Sales saat publish); header dari customer (`payment_term`, `contact_name`) dan `pic_sales` inquiry.
- **dipakai_oleh**: Customer Order (WIN), Win-Rate Sales, Open Quotations, Follow-up, cetak Quotation (`QuotationPrint`), Master Product (lahir saat WIN).
- **mengalir**: baris quotation DISNAPSHOT ke `co_order_item` (harga tidak live); perubahan costing sesudah publish tidak merambat. `unit_price` boleh diedit sebelum PUBLISHED (v1 `updateItem`), sesudah itu terkunci (`editable`).
- **zona beku**: Quotation (alur vital); mengubahnya perlu izin Jo.

## Temuan (dicatat, tidak diperbaiki)
1. **Aturan "Quotation WAJIB integrated" tidak ditegakkan server.** `publish()` hanya memeriksa dokumen editable dan punya baris (`entry.blade.php:371-379`); `Quotation::confirm` hanya memeriksa ada baris (`Data/Quotation.php:246-251`). Tombol "Baris Bebas" (`addFreeform`, `source_type='FREEFORM'`, harga ketikan) membuat baris tanpa inquiry; quotation hanya berisi baris bebas bisa di-Confirm dan di-WIN (CO memetakan FREEFORM ke `line_kind='SERVICE'`, `CoOrderBuilder.php:76`). Di data belum ada baris FREEFORM, jadi baru dibuktikan dari kode. Perlu keputusan Jo: baris bebas hanya untuk jasa pendamping, atau dibatasi?
2. **7 quotation tanpa satu pun baris**, termasuk 5 yang PUBLISHED (QUO2600005, 06, 07, 27, 28) padahal `publish()` menolak dokumen tanpa baris: berarti ditulis di luar jalur layar (seeder). Quotation PUBLISHED kosong masih bisa di-WIN menurut `Quotation::win` (hanya cek status, `Data/Quotation.php:280-285`); tidak ada CO dari 7 quotation itu.
3. **Harga tidak disaring di Quotation.** Lihat Fakta "Visibilitas harga": gerbang hanya modul SALES (`routes/v2.php:377`); apakah itu sudah cukup menurut A.9 BELUM diputuskan (A.9 menyebut Sales, Marketing, Product Costing, Operational Manager ke atas). Pengguna modul SALES di luar daftar grup harga akan melihat `total` dan harga baris.
4. **`sales_pic` quotation berisi nama yang tidak ada di `sales_person`** untuk 13 dari 20 baris ("Firman"); lihat `../master-data/11_kartu_sales-pic.md`.
5. **Label sumber usang**: daftar layar menyebut `quo_activity` sebagai sumber follow-up (`app/Support/V2/Screens.php:65`), padahal tabelnya `crm_activity`; tidak ada tabel `quo_activity` di database.
6. **WON/LOST diturunkan dari status inquiry**, bukan dari quotation: quotation berisi 2 inquiry dengan satu CONVERTED dan satu LOST menjadi WON; `Quotation::lost` hanya menandai inquiry yang belum terminal. Hanya dibaca dari kode; kasus multi-inquiry tidak dihitung di data.

## Sudah ada? Dicari di mana, hasil
Dicari: tabel `quo_*` dan `%quotation%` di semua `ddt*`; `grep` `Quotation` di `app/`, `routes/`, `resources/views/v2`, `resources/views/pages/quotation`; kolom penanda "direct"/"manual" pada `quo_quotation`. Hasil: konsep SUDAH ADA; tidak ada kolom yang membedakan quotation integrated dan bebas selain `quo_quotation_item.source_type`.

<!-- johiwan-only -->

## Alur harga ke Quotation (rahasia; angka dari DB mockup 2026-10-16)
- `quo_quotation_item.unit_price` = `iqp_costing_price_tier.price_per_pcs` (tier yang sama, kolom `tier_id`) untuk **24 dari 24** baris: tidak ada yang diedit di data. Rumus: `QuotationBuilder.php:175` `'unit_price' => $row->price_excl`, dan `TierPricingResolver::rows` mengisi `price_excl = price_per_pcs`, `price_incl = c_after_ppn_pc` hanya bila costing `published_flag` (`TierPricingResolver.php:13-27`; saat belum publish semua kolom harga NULL).
- Contoh (3 baris pertama): `QUO2600021`/`IQ99KSP001`, qty 30.000: 2.491,7647; `QUO2600022`/`IQ99MC0001`, qty 20.000: 1.395,2941; `QUO2600023`/`IQ99MC0002`, qty 15.000: 2.525,8824. `c_after_ppn_pc` masing-masing 2.765,8588; 1.548,7765; 2.803,7294 (harga + PPN, tidak disimpan di baris quotation; PPN dihitung ulang dari `TaxRate::ppn()` lewat `QuoQuotation::totals()` untuk skema EXCL).
- Total: skema `EXCL`: `ppn = round(dpp x ppn%/100, 2)`, `grand = dpp + ppn`; skema `INCL`: `dpp = round(subtotal/(1+ppn%/100), 2)`. `QuoQuotation.php:84-101` (`totals()`).
- HPP dan margin tidak dibaca di kartu ini (tidak ada kolom HPP/margin di `quo_*`).

