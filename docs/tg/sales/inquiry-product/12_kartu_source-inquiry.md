# Kartu konsep: Source inquiry (asal permintaan)

> Kelompok B, 2026-10-16. FAKTA = dilihat sendiri (DB mockup `ddtsdi`, kode dengan baris). Kartu kecil; detail Inquiry ada di `11_kartu_inquiry-dan-part.md`.

- **Modul/sub-modul**: Sales > Inquiry & Quotation > Inquiry Product.
- **Nama kanonik**: Source = `tm2sdi.iqp_inquiry.source` (label layar "Source").
- **Sinonim**: asal inquiry, kanal masuk, sumber permintaan, lead source.
- **BUKAN sama dengan**: `material_source` (asal bahan, kartu `13_kartu_material-source.md`); `source` pada baris BOM/BOP (`iqp_part_material.source` ENGINE/MANUAL, `iqp_part_process.source`); "Price Source" CO.

## Fakta
| Butir | Isi | Bukti |
|---|---|---|
| Kolom | `iqp_inquiry.source` varchar(50), boleh NULL, tanpa FK dan tanpa tabel master. | FAKTA, `show columns` |
| Pilihan di layar | Select wajib, nilai tetap `EMAIL, WA, PHONE, VISIT, MARKETPLACE`, bawaan `EMAIL`. Daftar ditulis langsung di Blade. | FAKTA, `inquiry-new.blade.php:48` |
| Validasi server | `in:EMAIL,WA,PHONE,VISIT,MARKETPLACE`, wajib. | FAKTA, `InquiryPayload.php:17` |
| Cara simpan | `InquiryPayload::from` -> `InquiryWriter::headerAttributes` -> `iqp_inquiry.source`. | FAKTA, `InquiryPayload.php:38`, `InquiryWriter.php:205` |
| Nilai lain yang ditulis kode | `MASTER CARD` oleh `MasterCardService` saat kartu koleksi dijadikan Inquiry; `REVERSE` oleh `ReverseCostingService` saat costing INSTANT dibalik menjadi Inquiry (0 baris di data). Revisi/Recalc/Duplicate menyalin nilai inquiry asal. | FAKTA, `MasterCardService.php:328`, `ReverseCostingService.php:88`, `InquiryDerivedWriter.php:125`, `InquiryDuplicator.php` (`'source' => $source->source`) |
| Sebaran data (mockup) | EMAIL 15 (LIVE 5, DUMMY 10), MASTER CARD 8 (DUMMY). | FAKTA, query 2026-10-16 |
| Pembaca | Cetak Inquiry (label "Source"), pemuatan dokumen ke form. Tidak ditemukan laporan, filter grid, atau widget yang mengelompokkan menurut `source`. | FAKTA bahwa `grep` `app/Support/V2`, `app/Services`, `packages` hanya menemukan `InquiryPrint.php:24,61` dan `Sales.php:165`; laporan di luar folder itu BELUM dicek |

## Relasi
- **sumber**: pilihan Sales di form; `MasterCardService` untuk kartu lama.
- **dipakai_oleh**: Cetak Inquiry. Belum ada konsumen analitik.
- **mengalir**: disalin ke inquiry turunan (Revise, Recalc, Duplicate). Tidak ikut ke Quotation atau CO.
- **zona beku**: Inquiry Product (field header). Hanya dicatat.

## Temuan (dicatat, tidak diperbaiki)
1. **`MASTER CARD` dan `REVERSE` tidak ada di pilihan maupun aturan validasi.** `MasterCardService` mengarahkan pengguna ke `inquiry-new?id=` untuk melengkapi DRAFT (`MasterCardService.php:290`); saat Save, aturan `in:` menolak nilai itu bila yang terkirim memang `MASTER CARD`. (Untuk `REVERSE` dokumen sudah Confirm, jadi terkunci dan tidak diedit.) Perilaku `select` untuk nilai di luar daftar TIDAK diuji di browser, jadi belum pasti pengguna melihat error atau nilai diam-diam menjadi EMAIL.
2. **Daftar kanal tertanam di kode dua tempat** (Blade dan `InquiryPayload`), tanpa master dan tanpa layar input; menambah kanal berarti mengubah kode (butir 7 CLAUDE.md: setiap nilai punya layar input; di sini nilainya konstanta daftar, bukan master).
3. Nilai data berbahasa campur (EMAIL, WA, MASTER CARD), sedangkan database dan nilai kode seharusnya English (B.3); ini tidak berbahaya, dicatat saja.

## Sudah ada? Dicari di mana, hasil
Dicari: kolom `source`/`channel`/`lead` di tm2sdi dan tm2mst (`information_schema`), `grep` `source` di `app/`, `resources/views/v2`, `packages`. Hasil: hanya satu kolom `iqp_inquiry.source`; tidak ada master kanal. Konsep SUDAH ADA; tidak perlu tabel baru bila Jo tidak menginginkan analitik kanal.

