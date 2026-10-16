# Sub-modul: Inquiry Product

> Kelompok B, 2026-10-16. Halaman sub-modul hanya merangkum dan menautkan; fakta tabel/kolom ada di kartu. FAKTA = dilihat sendiri (menu: `sys_app_menu` mockup; kode dengan baris).

- **Modul**: Sales > Inquiry & Quotation > Inquiry Product (grup menu id 1161). Menu (FAKTA, `sys_app_menu` mockup): Inquiry New (`v2:inquiry-list-new`, grid), Inquiry Revise (`v2:inquiry-revise`), Inquiry Recalc (`v2:inquiry-recalc`), judul "Reports" lalu All Inquiries (`v2:inquiry`). Form dokumen: `v2:inquiry-new` (dibuka dari grid; bukan item menu sendiri). Item "Inquiry Note" (id 931) tidak aktif dan tanpa target.
- **Tujuan**: Sales mencatat permintaan customer sebagai spesifikasi produk (part, ukuran dieline, bahan, finishing, tier qty), lalu Confirm; Inquiry menjadi sumber hulu seluruh rantai (ProDev, ProCost, Quotation, CO).
- **Tiga jenis** (A.5): NEW, REVISE (spec berubah), RECALC (spec sama, harga bahan berubah). RECALC langsung ke Pro-Cost (A.20).
- **Zona beku**: Inquiry Product, ProDev, ProCost. Baca `docs/claude_vital_flows.md` sebelum menyentuh.

## Relasi
- **sumber**: Sales (form), Master Card Collection, Duplicate, Master Product (Revise/Recalc), Reverse costing.
- **dipakai_oleh**: ProDev (kartu `../prodev/10_kartu_antarmuka-prodev.md`), ProCost (`../product-costing/11_kartu_antarmuka-procost.md`), Quotation (`../quotation/10_kartu_quotation.md`).
- **tanda**: tiga tanda `stat_confirm_sales/_prodev/_procost` di `iqp_inquiry` (A.20). Kartu tanda lintas-modul ditulis kelompok lain; di sini hanya tautan.

## Kartu konsep di sub-modul ini
| Kartu | Isi |
|---|---|
| `10_kartu_tipe-produk.md` | Tipe produk (`prod_type`), dibuat sebelumnya |
| `11_kartu_inquiry-dan-part.md` | Inquiry, Part, Tier qty, jenis NEW/REVISE/RECALC, tanda |
| `12_kartu_source-inquiry.md` | Source (asal permintaan) |
| `13_kartu_material-source.md` | Material Source (bahan sendiri / titipan customer) |
| `14_kartu_dieline-dan-master-card.md` | Dieline, snapshot dieline, Master Card sisi Sales |
| `15_kartu_master-pendukung-inquiry.md` | Product Market, ukuran pasaran, kategori bahan, finishing, jenis sambungan |
| `../master-data/10_kartu_customer.md` | Customer sisi Sales |
| `../master-data/11_kartu_sales-pic.md` | Sales PIC |

## Engine di sub-modul ini
Tidak ada engine yang menulis Inquiry sendiri. Yang menyentuh data Inquiry: `SpecEngine` (ProDev; menulis balik `iqp_part_process/_material/_ink`), `InquiryStage` (menghitung tanda). Kartu ENGINE: `20_engine_dieline.md` (kelompok engine), `../prodev/20_engine_spec.md`; butir terbuka di `docs/ENGINE-ATTENTION.md`.

## Temuan lintas kartu (ringkasan; rinci di kartu)
- Pilihan Source tidak memuat `MASTER CARD` dan `REVERSE` yang ditulis kode; daftar kanal tanpa master.
- `pic_sales` teks bebas; 0 dari 19 cocok dengan master Sales Person.
- Harga target customer per tier dan diskon tidak punya isian di layar v2; kolom `iqp_tier.price_excl` bermakna ganda.
- Nomor Inquiry berikutnya akan melompat akibat nomor seeder (dihitung dari kode, belum dijalankan).
- Master finishing, jenis sambungan, tipe produk tanpa layar input; konstanta `INQUIRY_ARTWORK_MAX_MB` tanpa baris.

## Diagram
Belum dibuat (Fase berikutnya). Alur teks ada di kartu Inquiry dan kartu antarmuka.

## Panduan pemakai (UG) yang sudah ada
`docs/guides/sales/01_inquiry_product.md`; alur data: `docs/guides/sales/00_alur_data_dan_sistem.md` (DARI DOKUMEN, tidak dibuka di sesi ini). TG tidak mengulang isinya.

