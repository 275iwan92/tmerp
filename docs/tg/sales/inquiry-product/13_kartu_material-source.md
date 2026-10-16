# Kartu konsep: Material Source (bahan dari perusahaan atau dari customer)

> Kelompok B, 2026-10-16. FAKTA = dilihat sendiri (DB mockup `ddtsdi`, kode dengan baris).

- **Modul/sub-modul**: Sales > Inquiry & Quotation > Inquiry Product (hilir: Stock > Customer Material, Costing).
- **Nama kanonik**: Material Source = `tm2sdi.iqp_inquiry.material_source` (`OWN` | `CUSTOMER`) bersama `job_nature`, `cust_material_spec`, `cust_material_eta`, `pph_applicable`.
- **Sinonim**: bahan titipan, customer supply, consignment, jasa (JASA), bahan sendiri (OWN), `cust_material_*`.
- **BUKAN sama dengan**: `source` inquiry (kartu `12_kartu_source-inquiry.md`); modul inventori "Customer Material" (layar `customer-material`, laporan).

## Fakta
| Butir | Isi | Bukti |
|---|---|---|
| Kolom header | `material_source` varchar(10) bawaan `OWN`; `job_nature` varchar(10) bawaan `PRODUCT`; `cust_material_spec` text NULL; `cust_material_eta` date NULL; `pph_applicable` tinyint bawaan 0. | FAKTA, `show columns` |
| Kolom per bahan | `iqp_part_material.material_source` varchar(10) bawaan `OWN`. | FAKTA, `show columns` |
| Layar | Satu kotak centang "Material Source: Customer supplies its own raw material". Bila dicentang tampil "Customer material description" (wajib) dan "Material ETA". | FAKTA, `inquiry-new.blade.php:85-95`; JS `inquiry-form.js:305,330` |
| Simpan header | `CUSTOMER` -> `job_nature='JASA'`, `cust_material_spec/_eta` disimpan; bila tidak, `job_nature='PRODUCT'` dan keduanya NULL. | FAKTA, `InquiryWriter.php:201-218` |
| PPh | Dokumen baru: `pph_applicable = (bahan dari customer)`; dokumen lama dipertahankan. | FAKTA, `InquiryPayload.php:48` |
| Simpan per bahan | Nilai header disalin ke SETIAP baris bahan (`'material_source' => $materialSource`) dengan komentar "granularitas per-layer belum diputuskan". | FAKTA, `InquiryWriter.php:313-314` |
| Validasi | `cust_material_spec` wajib bila `material_source` dicentang. | FAKTA, `InquiryPayload.php:22` |
| Pembaca | `SpecialReceiptService` (kartu spesifikasi bahan titipan masuk Receiving tanpa biaya), `ProCostContextService` (kolom `owner` di BOM costing), `InquiryPrint` ("own material" / "customer material"), `SpecEngine.php:1225` (menyalin ke baris BOM). | FAKTA bahwa berkas merujuk; perilaku tiap pembaca BELUM dicek |
| Aturan layar | "Customer material -> material COGS = 0; masuk lewat Receiving non-costed (consignment). Handling & spoilage dihitung Costing." | DARI DOKUMEN (teks alert layar, `inquiry-new.blade.php:94`); perhitungan COGS = 0 BELUM dicek di ProCost |
| Sebaran data (mockup) | `iqp_inquiry`: 23 baris semuanya `OWN`/`PRODUCT`, 0 punya `cust_material_spec`. `iqp_part_material`: 129 baris semuanya `OWN` (ENGINE 85, MANUAL 44). | FAKTA, query 2026-10-16 |

## Relasi
- **sumber**: kotak centang di Inquiry New (Sales). Revisi/Recalc/Duplicate menyalin dari inquiry asal.
- **dipakai_oleh**: ProCost (pemilik bahan per baris), Receiving khusus (bahan titipan), cetak Inquiry, SpecEngine (menyalin ke BOM).
- **mengalir**: Inquiry -> BOM part (`material_source`) -> costing. Perubahan di master tidak relevan; ini data transaksi.
- **zona beku**: Inquiry dan ProCost.

## Temuan (dicatat, tidak diperbaiki)
1. **Tidak ada satu pun data uji jalur CUSTOMER** (0 dari 23 inquiry, 0 dari 129 bahan). Jalur JASA, PPh, kartu Customer Material, dan COGS = 0 belum bisa dinilai dari data.
2. **Dua tingkat penyimpanan, satu tingkat input.** Layar hanya punya satu saklar untuk seluruh inquiry, tetapi bahan menyimpan sendiri `material_source`; bila kelak campuran (sebagian bahan dari customer) ada, tidak ada tempat input (diakui di komentar `InquiryWriter.php:313`).
3. **`job_nature` dan `pph_applicable` diturunkan, bukan diisi.** `pph_applicable` hanya diisi otomatis saat dokumen baru dibuat; pada dokumen lama nilainya dipertahankan walau saklar bahan berubah (`InquiryPayload.php:48`), sehingga PPh bisa tidak sinkron dengan `material_source` bila saklar diubah saat edit. Belum dibuktikan di layar.
4. Nama `cust_material_*` di tugas ini hanyalah dua kolom header; TIDAK ada tabel `cust_material*` di database (dicari di semua `ddt*`).

## Sudah ada? Dicari di mana, hasil
Dicari tabel `cust_material%` / `%material_source%` di semua `ddt*` (`information_schema`): tidak ada. `grep` `material_source|cust_material|job_nature` di `app/`, `resources/views/v2`, `packages`. Hasil: konsep SUDAH ADA di kolom header + baris bahan; hilirnya (Receiving, Customer Material) milik modul Stock, bukan kartu ini.
