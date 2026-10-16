# Kartu konsep: Snapshot (pembekuan master) sisi Sales

> Ditulis 2026-10-16 (kelompok A). Aturan A.13: "transaksi membekukan nilai master; master berubah, transaksi lama tetap" (`docs/ringkas/globaluserrules.md`, DARI DOKUMEN; teks asli di `docs/globaluserrules.md` tidak dibuka ulang di sesi ini). FAKTA = kode bernomor baris dan DB mockup (`ddt*` lewat `tm2*`) dilihat sesi ini. Tabel/kolom dirinci di kartu konsep terkait; di sini hanya peta "dari mana ke mana".

- **Jenis dokumen**: kartu konsep (aturan lintas-dokumen). **Modul/sub-modul**: Sales (Order, Delivery, CRM; Inquiry/Quotation dirujuk).
- **Nama kanonik**: Snapshot = salinan nilai master/dokumen hulu ke dokumen hilir pada saat tertentu, tanpa kunci hidup (live link) untuk nilai itu.
- **Sinonim**: pembekuan, freeze, salinan nilai, "bukan live-linked", "di-SNAPSHOT dari master".
- **BUKAN sama dengan**: tautan logis (`product_id`, `customer_id`) yang tetap dibaca hidup; log perubahan.

## Fakta: peta snapshot di Sales
| Dari -> ke | Yang dibekukan | Saat | Bukti |
|---|---|---|---|
| `prod_type` -> `iqp_part.dieline_spec` | `type_code`, spec dieline, dimensi | simpan part Inquiry | DARI KARTU Tipe produk (`inquiry-product/10_kartu_tipe-produk.md`), tidak dicek ulang |
| Quotation item -> `co_order_item` | `description`, `spec_note`, `qty`, `qty_unit`, `unit_price`, `discount_pct`, tipe baris; CO header: `payment_term`, `currency_code`, `is_ppn` (dari inquiry/profil pajak customer), diskon dokumen | CO lahir (WIN) | FAKTA, `CoOrderBuilder.php:45-91`; data: 13 baris INTEGRATED, harga/qty/deskripsi sama dengan item Quotation (0 selisih); 13 CO, mata uang dan termin sama dengan Quotation (0 selisih) |
| Tier ProCost -> `co_order_item.qty_min/qty_max` | toleransi qty cetak (`tolerance_pct`) | CO lahir | FAKTA, `CoOrderBuilder.php:65-85` |
| `prod_product` -> baris CO INSTANT | `description` (`nama (kode)`), `spec_note`, `qty_unit` (`uom_sales`), `qty_min/max` (batas qty master), `unit_price` (`default_selling_price`, 0 bila kosong) | baris ditambahkan | FAKTA, `CoOrderBuilder.php:154-181`; data: 13 baris INSTANT, harga sama dengan harga master (0 selisih) |
| Customer -> CO INSTANT | `payment_term`, `currency_code`, `is_ppn`, alamat bawaan | CO dibuat | FAKTA, `CoOrderBuilder.php:130-149` |
| Jadwal kirim CO -> `do_line` | `item_description`, `qty_unit`, `unit_price` (lewat view `v_co_delivery_outstanding`) | baris ditarik ke DO | FAKTA, `DispatchService.php:86-100`; view |
| Harga `do_line` diubah | `original_unit_price` diisi SEKALI, `price_overridden`, header `price_change_flag` | override manual | FAKTA, `DispatchService.php:160-171` |
| Produk -> `crm_complaint_item` | `product_name` (teks wajib) berdampingan dengan `product_id` | catat komplain | FAKTA, `show columns crm_complaint_item` |
| Inquiry -> Master Product | part, dieline, artwork, finishing, nama, dimensi; produk jadi `ACTIVE` + `stat_confirm` | WIN | FAKTA, `ProductMasterSync.php:25-60`; dipanggil `CoOrderBuilder.php:104-109` |

## Yang TIDAK dibekukan (dibaca hidup)
- Nama customer pada CO/DO: `co_order` dan `do_delivery` hanya menyimpan `customer_id`; nama dibaca dari master tiap tampil (`CustomerOrder::rows` memetakan `customer_name` dari master, `CustomerOrder.php:42-43`; tidak ada kolom nama customer di `co_order`/`do_delivery`). FAKTA.
- `co_order_item.product_id`, `inquiry_id`, `quotation_item_id`: tautan logis hidup; nama produk di baris (`description`) beku, tetapi atribut master lain dibaca hidup.
- Alamat kirim CO INTEGRATED: TIDAK disalin sama sekali (13 dari 13 kosong, kartu CO temuan 6), jadi tidak beku maupun hidup.

## Relasi
- **sumber**: master (`prod_type`, `prod_product`, `cust_customer`), Quotation, tier ProCost.
- **dipakai_oleh**: CO, DO/Surat Jalan, Complaint, Master Product (arah sebaliknya: transaksi mengisi master saat WIN).
- **mengalir**: lihat kolom "Dari -> ke". Perubahan master sesudah momen beku tidak merambat ke dokumen lama.
- **zona beku**: Inquiry/ProDev/ProCost dan flow dokumen.

## Temuan (dicatat, tidak diperbaiki)
1. **11 dari 13 baris CO INSTANT bernilai Rp 0**: `unit_price` = 0 karena `default_selling_price` master 0 (cocok dengan master, 0 selisih; `CoOrderBuilder.php:170` jatuh ke 0 bila kosong). Tidak ada peringatan di engine bahwa harga snapshot 0. FAKTA (query hari ini).
2. **WIN menulis ke master oleh transaksi** (`ProductMasterSync`: `card_status=ACTIVE`, `stat_confirm=true`, part/finishing disalin), sementara aturan B.14 menyatakan master hanya diubah user (default boleh). Konsisten dengan A.4 ("WIN = Master Product lahir") tetapi dua aturan bertemu. Tidak ada tindakan; dicatat. FAKTA (kode).
3. **Transaksi lintas koneksi**: snapshot WIN menulis `tm2sdi` dan `tm2mst` dalam dua transaksi terpisah (kartu engine CoOrderBuilder, kasus tepi). DARI KODE.
4. **Tak ada penanda waktu snapshot**: tidak ada kolom "disalin pada" di `co_order_item`/`do_line`; saat snapshot dapat diperkirakan dari `created_at` baris. FAKTA (`show columns`).
5. **Snapshot Quotation dari Inquiry** tidak diperiksa di sesi ini (milik penulis kartu Quotation).

## Sudah ada? Dicari di mana
Dicari: kata "snapshot" di `app/Services/Sales`, `app/Models/Sdi`, `CoOrderBuilder`, `DispatchService`; kolom `original_*`, `*_snapshot`, `amount_snapshot` (ada di `flow_log`, bukan Sales). Hasil: pola SUDAH ADA sebagai penyalinan nilai di dalam service; tidak ada tabel atau kolom khusus snapshot, dan tidak diperlukan.
