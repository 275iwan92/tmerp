# TIER 1 — MASTER PRODUCT (KARTU INDUK / MASTER CARD)

| | |
|---|---|
| **Modul** | Master Data · `tm2mst` |
| **Inti** | Master Product = induk spesifikasi produk cetak. Output kanoniknya = **MASTER CARD / KARTU INDUK**. |
| **Prinsip** | Master Card **bukan tabel** — ia dokumen hasil rakit (`vw_master_card`) dari `prod_product` + rantai part. |

## 1. Tujuan
Menyimpan spesifikasi lengkap produk agar dapat dipakai ulang oleh **RECALC** (harga ulang) & **REVISE** (ubah spec), serta menjadi acuan produksi (kartu dicetak untuk lantai).

## 2. Ruang Lingkup (F-list)
| F | Fitur | Sumber contoh |
|---|---|---|
| F1 | Header kartu: Kode Item, No. Desain, Nama Barang, Tanggal, Pelanggan | GENIALE DS003048 |
| F2 | Dimensi P×L×T + Kode Pisau + Ukuran Pisau/Cetak/Bahan | 31,3×21,3×13 · 69,8×55,1 |
| F3 | Material multi-layer (Duplex/Ivory + SF E/Flute) + kualitas gsm | 310gsm · M125/K125 |
| F4 | **Warna per pass per unit** (CS/KS) | CS: BLACK/CYAN/MAGENTA/YELLOW |
| F5 | Urutan Pekerjaan (proses berurut, tanda MERGE laminasi) | 10 langkah |
| F6 | Bentuk Sambungan · Jenis Finishing · Isi/Cetakan · Pack qty | Double Join · Lam Glossy · 25/pack |
| F7 | **Artwork + ACC Proof** (versioning) | wajib dilampirkan |
| F8 | **Dieline SVG** (per part) | DC 310g/1140×72 |
| F9 | **Sign-off**: Desainer·Marketing·PPIC·QA·Plant Manager | blok Mengetahui |
| F10 | Pemilik: customer asli (MTO) / **GENERAL** (MTS) | — |
| F11 | Mode single-part vs multi-part (driver `has_multi_part`) | GENIALE=single |

## 3. Aturan Bisnis (locked)
1. **1 Master Card = 1 product**; artwork & dieline **milik part** (multi-part → per part).
2. **Warna 2-pass**: CS (pass 1) + KS (pass 2), tiap pass s/d 4 unit. Menentukan jumlah plate & pass → costing.
3. **Artwork = upload-versi** (v1→v2→v3), tak menimpa; 1 versi aktif; **ACC Proof (digital+cetak) wajib**.
4. **Card_status APPROVED** hanya bila 5 sign-off lengkap + artwork approved.
5. **Pemilik**: MTO → customer; MTS → baris `GENERAL` (bukan NULL).
6. **Sumber lahir**: Master Card lahir dari **inquiry** (desain+proof), lalu dipromosikan jadi baku (`last_design_id`). Entry manual diizinkan untuk migrasi legacy.
7. Urutan proses mengikuti standar pabrik (Lampiran A): Coating **sebelum** Laminasi.

## 4. Aktor
Desainer (input/kartu), Sales (pemilik/pemesan), PPIC/QA/Plant Manager (sign-off), Costing (baca warna/material).

## 5. Keluar-masuk data
- **Masuk**: dari inquiry (promosi) atau entry manual.
- **Keluar**: dipakai RECALC/REVISE inquiry; dicetak sebagai kartu produksi.
