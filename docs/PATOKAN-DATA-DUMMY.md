# PATOKAN DATA DUMMY (Jo, 2026-10-14)

> Berkas ini mencatat pemahaman yang dikonfirmasi Jo ("Paham") dan menjadi patokan semua pekerjaan data dummy.
> Versi HTML (cerita yang sama): `docs/PATOKAN-DATA-DUMMY.html`.
> Isi hanya apa yang Jo katakan. Yang belum diputuskan ada di bagian terakhir; jangan dianggap aturan.

## Cerita singkat

1. **Tujuan Jo.** Setiap data dummy harus punya **dieline**, **artwork**, dan **ukuran produk yang jelas** (P x L x T, ukuran datar, mm).
2. **Sumber.** Jo punya data Master Card dalam Excel; Jo mengubahnya menjadi **PDF** supaya bisa dibaca. PDF itu (folder `docs/attachments/mastercard/`) adalah sumber kebenaran isi data dummy.
3. **Data dummy dibuat BERLEVEL, dalam beberapa set.** Jumlah set tidak dikunci ("misal 12 set atau entah berapa"). Contoh Jo:

| Level | Posisi set di alur | Contoh jumlah |
|---|---|---|
| 1 | Baru Inquiry saja | 2 set |
| 2 | Sudah di ProDev | 2 set |
| 3 | Sudah di ProCost | 2 set |
| 4 | Sudah masuk CO | 2 set |

4. **Yang di level lebih maju harus punya jejak lengkap di belakangnya.** Set yang sudah masuk CO berarti di belakangnya ada Inquiry, ProDev, dan ProCost untuk set itu juga, tidak boleh loncat.

## Aturan turunan (langsung dari butir di atas)

- Setiap set, di level mana pun, membawa dieline + artwork + ukuran produk (P x L x T) + ukuran datar (mm) yang jelas, sejak Inquiry.
- Set dibuat per level, bukan satu campuran: tiap set berhenti tepat di levelnya (set level 1 tidak punya ProDev; set level 4 punya semuanya sampai CO).
- Set level N harus bisa ditelusuri mundur ke level N-1 ... 1 dengan data yang konsisten (nomor inquiry, produk, part, qty sama).

## Engine (dibuat 2026-10-14)

PDF baru di `docs/attachments/mastercard/` diproses satu perintah (hanya ddt*):

```bash
php artisan dummy:mastercard --env=mockup --levels=inquiry:2,prodev:2,procost:2,co:2
```

- Gambar kartu (bentuk / dieline) = berkas di `docs/attachments/mastercard/extracted/` dengan nama slug PDF-nya (huruf kecil, tanda hubung).
- Produk baru diisi ke level sesuai kuota, urut berkas. Tanpa `--levels`: semua ke level Inquiry. Opsi lain: `--qty` (default 5000), `--due` (hari, default 21).
- Level N membawa semua level di belakangnya; berhenti di CO Confirmed (baris CPDEL lahir, belum di-Post).
- Kartu tanpa artwork, ukuran P x L x T, ukuran pisau, atau bahan **tidak dibuat**, dilaporkan alasannya. Produk yang sudah ada dilewati.
- Kode: `MasterCardPdfReader` (PDF -> data kartu), `MasterCardDummyEngine` (level), `DummyMasterCard` (perintah). Butuh program `pdftotext` (PATH atau env `PDFTOTEXT_BIN`).

## Belum diputuskan Jo (jangan diisi sendiri)

- Jumlah set total dan jumlah per level.
- Apakah ada level setelah CO (Post to Production / WO / plan) dan berapa set per level itu.
- Set mana dari PDF yang masuk level mana.
