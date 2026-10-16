# Kartu konsep: Tipe produk (Product Type)

> CONTOH FASE 1 (2026-10-16), menunggu persetujuan Jo. Semua butir "FAKTA" dilihat sendiri di sesi ini (DB mockup `ddt*` via koneksi `tm2*` dengan `--env=mockup`, dan kode).

- **Jenis dokumen**: kartu konsep. **Modul/sub-modul**: Sales > Inquiry & Quotation > Inquiry Product (cocok pohon menu `sys_app_menu`).
- **Nama kanonik**: Tipe produk = `prod_type.type_code` (label layar "Product Type (international dieline)").
- **Sinonim** (untuk pencarian): type_code, tipe dieline, bentuk kemasan, RSC_2PC, CARD_BOX.
- **BUKAN sama dengan**: *jenis part* (`iqp_part.part_type`: DC, DC+SHEET, DC+SF, SHEET). Dua konsep berbeda, jangan digabung.

## Fakta
| Butir | Isi | Bukti |
|---|---|---|
| Master | `tm2mst.prod_type`: 63 baris, 28 aktif. Kolom: `type_code`, `type_name`, `category`, `sub_category`, `box_style`, `dieline_spec` (JSON), `dieline_template_id`, `ecma_code`, `part_type_default`, `material_filter`, `has_multi_part`. Kunci unik `type_code`, PK DB `id_type`. | FAKTA, `show columns` + `information_schema` |
| Yang mengisi | Layar Inquiry New, pilihan "Product Type (international dieline)". | FAKTA, `resources/views/v2/screens/inquiry-new.blade.php:68` |
| Cara simpan | `InquiryWriter::persistParts` membekukan (snapshot, A.13) `prod_type.dieline_spec` + dimensi + `type_code` ke **`iqp_part.dieline_spec` (JSON, kunci `type_code`)**. **Bukan** ke kolom `part_type`. | FAKTA, `app/Services/Sales/InquiryWriter.php:254-258`; data: `json_extract(dieline_spec,'$.type_code')` terisi di ddtsdi |
| Sebaran data dummy | 42 part: RSC_2PC 12, BUKU 10, RTE_BOX 8, TRAY_LID_DW 4, LOCK_BOTTOM_2PC 3, CARD_BOX 2, KALENDER_DINDING 1, kosong 2. `part_type`: DC 25, DC+SHEET 13, DC+SF 3, SHEET 1. | FAKTA, query 2026-10-16 |
| Tingkat Inquiry | `iqp_inquiry` TIDAK punya kolom tipe produk (hanya `inquiry_type` NEW/REVISE/RECALC, `product_draft_name`, `product_id`). Tipe produk hidup di tingkat **part**. | FAKTA, `show columns` |
| Pemakai | Kolom grid "Product Type" = part pertama (`inquiry.blade.php:21`); `RoutingTemplateService`, `RoutingTemplateGrid` (kolom `prod_routing_template.prod_type_code`), `PartDieline`, `InquiryPrint`, `ProdMasterCard`. | FAKTA bahwa berkas merujuk `ProdType`/`prod_type_code`; perilaku tiap pemakai BELUM dicek |
| Layar input master | Layar pengisi master `prod_type` | BELUM dicek (aturan CLAUDE.md butir 7.5 menuntut layarnya) |

## Relasi
- **sumber**: master `prod_type` (netral, `tm2mst`).
- **dipakai_oleh**: Inquiry Product (simpan), ProDev (`PartDieline`), Production (`prod_routing_template`), Master Card (`ProdMasterCard`).
- **mengalir**: `type_code` + `dieline_spec` dibekukan di `iqp_part.dieline_spec`; perubahan master sesudahnya TIDAK merambat ke Inquiry lama (snapshot).
- **zona beku**: Inquiry/ProDev. Menyentuh = peringatan ke Jo lebih dulu.

## Temuan (dicatat, tidak diperbaiki)
1. Layar mendeklarasikan sumber `tm2sdi.iqp_part.part_type` untuk field Product Type (`inquiry-new.blade.php:70`), padahal nilai tersimpan di `iqp_part.dieline_spec.type_code`. Teks sumber di layar menyesatkan.
2. `persistParts` hanya menyimpan `type_code` bila `prod_type.dieline_spec` terisi (baris 254). Tipe tanpa spec tidak tersimpan diam-diam. Di master: 1 dari 28 tipe aktif tidak punya spec. Ada 2 part dummy dengan `type_code` kosong; sebabnya belum diselidiki.
3. Model `ProdType` memakai `type_code` sebagai primary key (`app/Models/Master/ProdType.php:14`), sedangkan DB punya PK `id_type` (aturan penamaan: PK `id_<entitas>`).
4. Catatan sesi ProDev ("type_code dideklarasikan ke `part_type`") sudah dicek: yang benar seperti di tabel Fakta.

## Sudah ada? Dicari di mana
Dicari: kolom/tabel `type_code` + `prod_type` di DB mockup, `inquiry*.blade.php`, `app/Services/Sales`, pohon menu Sales. Hasil: konsep SUDAH ADA; tidak ada kolom baru yang diperlukan di `iqp_part` atau `iqp_inquiry`.
