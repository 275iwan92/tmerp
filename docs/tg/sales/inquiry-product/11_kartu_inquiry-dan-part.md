# Kartu konsep: Inquiry, Part, Tier qty

> Kelompok B, 2026-10-16, menunggu persetujuan Jo. FAKTA = dilihat sendiri di sesi ini: DB mockup (`--env=mockup`, koneksi `tm2sdi` -> `ddtsdi`), `show columns`, dan kode dengan nomor baris. Tidak ada yang diubah.

- **Jenis dokumen**: kartu konsep. **Modul/sub-modul**: Sales > Inquiry & Quotation > Inquiry Product.
- **Nama kanonik**: Inquiry = `tm2sdi.iqp_inquiry`; Part = `tm2sdi.iqp_part`; Tier qty = `tm2sdi.iqp_tier`.
- **Sinonim**: inquiry product, InqProd, permintaan penawaran, IQ-nomor, part komponen, rakitan (assembly), jenjang qty.
- **BUKAN sama dengan**: Master Product (`tm2mst.prod_product`) dan part master (`tm2mst.prod_part`), keduanya lahir dari Inquiry, tabel lain; *jenis part* `iqp_part.part_type` dan *tipe produk* (kartu `10_kartu_tipe-produk.md`).

## Fakta
| Butir | Isi | Bukti |
|---|---|---|
| Isi tabel (mockup) | `iqp_inquiry` 23 baris, `iqp_part` 42 (COMPONENT 36, ASSEMBLY 6), `iqp_tier` 33. Tabel anak part: `iqp_part_material` 129, `iqp_part_finishing`, `iqp_part_ink`, `iqp_part_process`; dokumen: `iqp_tooling` 0 baris, `iqp_external_cost` 0, `iqp_revise_change` 0, `iqp_prove` 0. | FAKTA, `count(*)` 2026-10-16 (anak lain hanya perkiraan information_schema) |
| Kolom `iqp_inquiry` | PK `id_inquiry`; `inquiry_no` (unik); `inquiry_date`; `source`; `pic_sales` (teks); `customer_id` / `is_prospect` / `prospect_name`; `deadline_quotation`; `product_draft_name`; `sales_note`; `inquiry_type`; `parent_inquiry_id`; `product_id`; `revision_no`; `revise_scope`; `revise_reason`; `material_source`; `job_nature`; `cust_material_spec`; `cust_material_eta`; `tax_scheme`; `pph_applicable`; `ppn_percent`; `pph_percent`; `discount_doc_*`; `discount_qty_*`; `prove_*`; `due_date`; `design_number`; `co_id`; `sub_state`; tiga tanda `stat_confirm_sales/_prodev/_procost` masing-masing dengan `_by` dan `_at`; `stat_cancel`; `cancel_reason`; `is_approved` (+by/at); `data_origin`. | FAKTA, `show columns` |
| Kolom `iqp_part` | `inquiry_id`, `prod_part_id` (jembatan ke part master), `parent_part_id`, `part_kind` (COMPONENT/ASSEMBLY), `join_type_id`, `qty_in_parent`, `part_seq`, `part_code`, `part_name`, `part_type`, `qty_per_sku`, `pack_qty`, `cfg_color`, `cfg_ink`, `dieline_spec` (JSON), `flat_w`, `flat_h`, `flat_area`, `artwork_path`, `artwork_inner_path` (+ nama berkas asli), `artwork_colors` (JSON). | FAKTA, `show columns` |
| Kolom `iqp_tier` | `inquiry_id`, `tier_seq`, `qty`, `is_primary`, `discount_item_type/_value`, `price_excl`, `price_incl`, `primary_key_guard` (UNIK). | FAKTA, `show columns` |
| Tiga jenis | Master `iqp_type` 3 baris: NEW "Produk Baru", RECALC "Hitung Ulang Harga", REVISE "Ubah Spesifikasi". FK dalam-DB `iqp_inquiry.inquiry_type` -> `iqp_type.type_code`. Data: NEW 21, REVISE 2, **RECALC 0**. | FAKTA, `IqpInquiry.php:107-110`; query `group by` |
| Asal REVISE/RECALC | `InquiryDerivedWriter::create` menyalin header + struktur part dari inquiry asal (`parent_inquiry_id`); RECALC hanya mengisi Qty + Deadline; REVISE hanya blok scope (ARTWORK, COLOR, DIMENSION, MATERIAL, PROCESS, JOIN) dan mencatat beda ke `iqp_revise_change`. | FAKTA, `app/Services/Sales/InquiryDerivedWriter.php:21,38-51 (recalc),53-85 (revise),121-148 (create),192-223 (recordDiff)` |
| Penomoran | `IQ` + 2 digit tahun + 5 digit urut, counter per tahun, diambil dari `max(inquiry_no)` dengan awalan sama (`substr($last,4)`). | FAKTA, `IqpInquiry.php:163-176` |
| Layar & simpan | Layar `inquiry-new` mengirim ke `v2.inquiry.save` -> `InquiryPayload::from` -> `InquiryWriter::save` (satu transaksi: header, tier, part+bahan+finishing, tooling, biaya eksternal). Setelah `stat_confirm_sales`, form terkunci. | FAKTA, `inquiry-new.blade.php:35-39`, `routes/v2.php:272-287`, `InquiryWriter.php:27-55` |
| Siklus | Save = DRAFT; Confirm = `stat_confirm_sales=1` dan `sub_state=SUBMITTED`; Cancel hanya bila sudah Confirm dan belum batal; Mark Lost dari `sub_state` apa pun yang belum CANCELLED/CONVERTED/LOST. | FAKTA, `InquiryWriter.php:57-79` |
| Tier | Wajib >= 1 baris dan TEPAT 1 `is_primary`; qty > 0. Kunci unik DB `primary_key_guard`. | FAKTA, `InquiryWriter.php:181-197`; `show columns` |
| Rakitan | Lewat `InquiryAssembly::create` (>= 2 anak, jenis sambungan `mat_join_type`), bukan lewat form part. `persistParts` tidak menghapus rakitan. | FAKTA, `routes/v2.php:354-374`, `InquiryWriter.php:287-288` |
| Duplicate | `InquiryDuplicator`: lahir sebagai NEW DRAFT, struktur disalin, tier qty dan harga TIDAK ikut. | FAKTA, `InquiryDuplicator.php:8-10 (komentar), 13-45` |
| Sebaran tanda (mockup) | NEW CONVERTED (sales=1, prodev=1, procost=1): 13; NEW SUBMITTED prodev=1: 2; NEW SUBMITTED prodev=0: 3; REVISE SUBMITTED prodev=0: 2; NEW DRAFT: 2; NEW LOST: 1. Tidak ada inquiry `stat_confirm_procost=1` tanpa costing PUBLISHED dan sebaliknya (0 baris). | FAKTA, query 2026-10-16 |
| Status yang ditulis kode | Penulis `sub_state`: DRAFT (bawaan DB), SUBMITTED (Confirm), CANCELLED, LOST (`InquiryWriter.php`, `pages/quotation/entry.blade.php:426`), CONVERTED (`CoOrderBuilder.php:101`), PRICE_CALCULATED (`ReverseCostingService.php:93` dan Publish costing `pages/inquiry/costing.blade.php:582`). | FAKTA, grep `app/`, `resources/`, `routes/`, `packages/` |

## Relasi
- **sumber**: layar Sales (`inquiry-new`), Reverse costing INSTANT (`ReverseCostingService`, lihat kartu antarmuka ProCost), Master Card Collection (kartu lama jadi Inquiry sumber `MASTER CARD`, `MasterCardService.php:328`), Duplicate, Revise/Recalc dari Master Product.
- **dipakai_oleh**: ProDev (kartu `../prodev/10_kartu_antarmuka-prodev.md`), ProCost (kartu `../product-costing/11_kartu_antarmuka-procost.md`), Quotation (kartu `../quotation/10_kartu_quotation.md`), Master Product (`ProductMasterSync::fromInquiry` saat WIN), Nota Inquiry / Print.
- **mengalir**: Inquiry (stat_confirm_sales) -> ProDev (stat_confirm_prodev, dihitung dari layout) -> ProCost (stat_confirm_procost, dihitung dari costing published) -> Quotation -> WIN = CO + `sub_state=CONVERTED`. Tanda diatur A.20. RECALC melewati ProDev (`InquiryStage::PRODEV_TYPES = ['NEW','REVISE']`, baris 20).
- **zona beku**: Inquiry Product, ProDev, ProCost (CLAUDE.md butir 1). Kartu ini hanya mencatat.

## Temuan (dicatat, tidak diperbaiki)
1. **Nomor Inquiry bisa melompat.** Data memuat `IQ26090067`, `IQ2690068`, `IQ2690069` (hasil seeder). `nextInquiryNo` mengambil nilai terbesar berawalan `IQ26` lalu menambah 1 pada `substr(,4)`; dari kode + data, inquiry nyata berikutnya menjadi `IQ2690070`, bukan `IQ2600007`. Dihitung dari kode, TIDAK dijalankan.
2. **Dua inquiry bertanda ProDev tanpa part master**: `IQ2600001` dan `IQ2600002` punya `stat_confirm_prodev=1` tetapi part komponennya tidak punya `prod_part_id`. Tanda seharusnya dihitung `InquiryStage::syncProdev` dari layout part master (`InquiryStage.php:37-50`); kedua baris itu tampaknya ditulis di luar jalur itu (belum ditelusuri siapa yang menulis).
3. **Dummy REVISE tidak sama dengan aturan kode.** `IQ99PD0003/4` (REVISE) memakai `product_id` yang SAMA dengan inquiry induknya (14404 dan 14403), padahal `ensureDraftProduct` membuat product turunan (`IqpInquiry.php:132-154`; aturan A.4: Revisi = product turunan). `iqp_revise_change` kosong sehingga kartu "Revision: what changed" di ProDev tidak punya isi untuk dua dokumen ini.
4. **Tidak ada dokumen RECALC** di data; jalur RECALC (`inquiry-recalc`) tidak teruji dengan data. REMARK `inquiry-recalc.blade.php:3` dan pesan `routes/v2.php` (Confirm) menyebut "lanjut ProDev dan ProCost", sedangkan A.20 dan `InquiryStage` menetapkan Recalc langsung ke Pro-Cost: teks usang.
5. **Harga target customer per tier tanpa layar input v2.** `iqp_tier.price_excl/price_incl` 0 baris terisi; `InquiryWriter::persistTiers` tidak menulisnya (baris 227-245) dan layar `inquiry-new` hanya punya `qty` dan `is_primary` (baris 101-105). Pembacanya `nota.blade.php` (v1) sebagai "Target customer" (baris 180-206); satu-satunya PENULIS yang ditemukan justru `ReverseCostingService.php:170-171` yang mengisinya dengan HARGA HASIL costing (`price_per_pcs`, `c_after_ppn_pc`). Satu kolom, dua arti (target customer vs harga terhitung); jalur Reverse 0 baris di data sehingga belum terlihat. Kolom diskon (`discount_doc_*`, `discount_qty_*`, `discount_item_*`) juga tanpa isian di layar v2 (`InquiryPayload.php:51-54,58`, nilai lama dipertahankan); semuanya kosong di mockup. Butir 5 CLAUDE.md (setiap nilai punya layar input): belum terpenuhi.
6. **Inquiry NEW DRAFT tidak punya Delete.** `InquiryForm.php` hanya punya Cancel dan Mark Lost (baris 78-80), dan `routes/v2.php` grup `inquiry.` tidak punya rute hapus; sebaliknya RECALC/REVISE punya `derived.delete`. Belum diputuskan apakah itu sengaja.
7. **Prospect tidak bisa dibuat lewat layar v2.** `InquiryPayload.php:41` selalu `is_prospect=false`; 4 inquiry prospect di data berasal dari luar layar ini. Prospect (tanpa `customer_id`) juga tidak bisa masuk Quotation (`QuotationBuilder.php:53`, `whereNotNull('customer_id')`).
8. **`pic_sales` teks bebas** (bukan FK ke `sales_person`): lihat kartu `../master-data/11_kartu_sales-pic.md`.
9. **Status tak bertuan.** `Sales::STATES` memuat WAITING_COST, PRICE_APPROVED, QUOTED, PROVING, PROVE_APPROVED, tetapi tidak ada kode yang menulisnya; hanya pemetaan tampilan di halaman v1. Semuanya 0 baris di data.
10. **`is_approved`**: 11 inquiry bernilai 1 di mockup; penulisnya belum dicari. `doc_state_log` (aturan penamaan CLAUDE.md butir 5) TIDAK ADA di database mana pun di mockup; transisi Inquiry hanya terlihat lewat `sub_state` dan `confirm_*_by/_at`.

## Sudah ada? Dicari di mana, hasil
Dicari: `show columns` + `information_schema` tm2sdi/tm2mst/tm2sys; `grep` `app/`, `routes/`, `resources/views/v2`, `packages/`; sinonim: inquiry, IQ, revisi, recalc, tier, rakitan, prospect. Hasil: seluruh konsep SUDAH ADA; temuan di atas bukan permintaan skema baru.

## Panduan pemakai (UG) yang sudah ada
`docs/guides/sales/01_inquiry_product.md` (DARI DOKUMEN, tidak dibuka di sesi ini).


