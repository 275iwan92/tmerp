# matcategory_delta.md — Ketentuan Master Kategori Bahan Baku (`mat_category`)

> Ditulis 2026-08-26. Dokumen ini mencatat keputusan penamaan & desain yang
> menggantikan `doc_rmprefix1..5` (5 tabel snowflake, prefix `doc_` tak sesuai
> konvensi domain LOCKED) dengan satu tabel self-referencing `mat_category`.
> Terdaftar di `sys_doc_reference` (related_menu_code=`MD_MATCAT`), bukan
> `sys_app_menu.doc_slug` — dokumen ini gabungan keputusan arsitektur + delta
> DDL, bukan family dokumen Tier 1-4 penuh.

---

## 1. Kenapa diganti

`doc_rmprefix1..5` (dump legacy V1, diimpor apa adanya lewat migration
`2026_08_20_000010`) punya 2 masalah:

1. **Nama salah domain.** Prefix `doc_` tidak ada di daftar prefix `tm2mst`
   LOCKED (`globalarchitecture_tier0.md` §3 / `prefixlist.md`) — domain yang
   benar untuk klasifikasi material adalah `mat_`.
2. **Desain berantai rapuh.** 5 tabel snowflake, tiap level nyambung ke level
   di atasnya lewat **TEXT match** (`category1` varchar), bukan FK id — nama
   kategori yang diketik beda spasi/kapital diam-diam memutus rantai. Tidak
   ada `is_active`/audit kolom. Tabel level-4 bahkan tanpa unique constraint.
   Depth di-hardcode ke 5 (butuh level ke-6 = bikin tabel baru lagi).

## 2. Aturan yang berlaku sekarang (LOCKED, sama level dengan §2-3 CLAUDE.md)

- **Satu tabel** `tm2mst.mat_category`, self-referencing (adjacency list):
  `id_category` (PK), `parent_id` (FK ke dirinya sendiri, nullable),
  `level` (1-5), `category_name`, `prefix_char`, `note`, `is_active`,
  `created_at/by`, `updated_at/by`.
- **RULE PENTING (instruksi eksplisit user, jangan dipecah lagi):** satu baris
  `mat_category` adalah sumber **NAMA** (`category_name` — dipakai isi
  `m_raw_material.category1..5`) **DAN** sumber **KODE** (`prefix_char` —
  dipakai bentuk `material_code`) **sekaligus**. Jangan pisah jadi tabel nama
  vs tabel kode.
- Cuma baris **`level = 1`** yang boleh isi 4 kolom fallback akuntansi:
  `stock_class`, `default_coa_account_id`, `default_stock_asset_account_id`,
  `default_gr_ir_account_id` (dulu eksklusif milik `doc_rmprefix1`, sekarang
  cuma dipakai kalau `level=1`, level lain selalu NULL).
- **Constraint unique** cuma `(parent_id, prefix_char)` — **BUKAN**
  `(parent_id, category_name)`. Data legacy asli punya sibling dengan nama
  sama tapi kode beda (mis. dua baris "R3" di bawah "Plastik", prefix `3` vs
  `4`) — jangan tambah unique nama lagi, migrasi akan gagal.
- **Level & Induk (`parent_id`) dikunci setelah baris dibuat** (pola sama
  "kategori dikunci" di Item Bahan Baku) — kalau salah taruh, buat baris baru
  + nonaktifkan yang lama, jangan reparent diam-diam (bisa yatim-piatukan
  anaknya).
- `m_raw_material.category1..5` **tetap kolom TEXT** (di luar scope delta
  ini) — pick di form Item Bahan Baku & PR Entry sekarang menyimpan
  `id_category` (int) buat cascading via `parent_id`, baru diterjemahkan ke
  `category_name` (text) sesaat sebelum disimpan ke `m_raw_material`.
- `m_raw_material.prefix5_id` FK di-repoint dari `doc_rmprefix5` ke
  `mat_category.id_category` — kolom ini **masih belum pernah diisi** (buka
  `PROC-ITEMCODE-GEN` di `procurement_state.yaml`, belum diputuskan
  auto-generate vs manual).

## 3. Implementasi

| Bagian | File |
|---|---|
| Migration data + FK repoint | `database/migrations/2026_08_26_000002_replace_doc_rmprefix_with_mat_category.php` |
| Migration menu (`MD_MATCAT`) | `database/migrations/2026_08_26_000003_add_master_mat_category_menu_item.php` |
| Model | `app/Models/Master/MatCategory.php` (self-ref `parent()`/`children()`, `scopeLevel()`, `fullPrefix()`) |
| Layar input (baru, sebelumnya tak ada) | `resources/views/pages/master/mat-category.blade.php` — route `master.mat-category`, menu MDATA → "Master Kategori Bahan Baku" |
| Dipakai di | `master/raw-material/entry.blade.php`, `pur/pr/entry.blade.php` (dropdown berjenjang), `PvPostingService`/`PoBuilderService`/`StockPostingService` (fallback akun kategori level 1) |

**Catatan migrasi data:** 1 baris legacy level-4 ("ZETLA BROWN") tidak
punya induk level-3 yang cocok di dump asli — bukan bug migrasi, data
legacy memang begitu. Dibiarkan sebagai baris yatim yang inert (tak
direferensikan `m_raw_material` manapun).

## 5. PEDOMAN: Material Category = pembentuk KODE ITEM (user 2026-10-03, LOCKED)

> User: *"Material category ini pembentuk KODE ITEM. IMPORTANT !!!!"* — disampaikan untuk ke-7 kalinya, dengan
> acuan `docs/ddl/doc_rmprefix1.sql`. **Baca bagian ini sebelum menyentuh Material Category, Raw Material, kode item,
> atau dropdown kategori di PR / PO.** Aturan singkatnya: `docs/globaluserrules.md` B.16.

### 5.1 Sumber: `docs/ddl/doc_rmprefix1.sql`

Dump `tmmst` (server TM 188). Lima tabel, **tiap baris membawa seluruh rantai di atasnya** (nama + prefix):

| Tabel | Kolom nama | Kolom prefix | Unik | Baris |
|---|---|---|---|---|
| `doc_rmprefix1` | `category1` | `prefix1` (1 karakter) | `prefix1` | 18 |
| `doc_rmprefix2` | `category1..2` | `prefix1..2` (1 karakter) | `prefix1+2` | 75 |
| `doc_rmprefix3` | `category1..3` | `prefix1..3` (1 karakter) | `prefix1+2+3` | 103 |
| `doc_rmprefix4` | `category1..4` | `prefix1..3` + `prefix4` (sampai 3) | tidak ada | 200 |
| `doc_rmprefix5` | `category1..5` | `prefix1..4` + `prefix5` (sampai 3) | `prefix1..5` | 157 |

Sekarang semuanya ada di satu tabel `tm2mst.mat_category` (bagian 2): `category_name` = nama, `prefix_char` = prefix,
`parent_id` = baris tingkat di atasnya.

### 5.2 Kode item = gabungan prefix

`material_code` = `prefix1` + `prefix2` + `prefix3` + `prefix4` + `prefix5`, tanpa pemisah.

| Rantai | Prefix | Kode item |
|---|---|---|
| Paper › Art carton › 210gr › 65 X 100 CM › GOLDEN | P · C · C · 02 · 002 | `PCC02002` |
| Paper › Duplex › 250gr › 105 X 102 CM › DAYASA | P · D · A · 01 · 001 | `PDA01001` |
| Paper › HVS › 58 GR › 65 x 100 CM › RAINBOW | P · 4 · 4 · 5 · 4 | `P4454` |
| Ink › OFFSET › HN › AMBRI › AOK RED SAR | I · O · H · 3 · 1 | `IOH31` |

Panjang kode tidak tetap (5 sampai 8 karakter) karena prefix 4 dan 5 boleh 1 sampai 3 karakter.

### 5.3 Arti tiap tingkat berbeda per kategori

| Category 1 | Category 2 | Category 3 | Category 4 | Category 5 |
|---|---|---|---|---|
| Paper (P) | jenis: Art carton, Duplex, Ivory, Kraft, HVS | gramatur: 210gr | ukuran lembar: 65 X 100 CM | merek: GOLDEN, DAYASA, IKA DP |
| Sheet (S) | flute: SHBF, SHEF, SFEF, BCF, DF | substance: K150/M150/K200 | ukuran mm: 520 X 780 | (belum ada) |
| Ink (I) | kelompok: OFFSET | seri: HN, DGM, RAPIDA, HAEMA | nama / nomor: AMBRI, 11077, IMPRESS | warna: CYAN, BLACK |
| Glue (G) | OFFSET | PF | 7300 | (belum ada) |
| CTP Plate (T) | BLANKET | CITO | ukuran: 0.5 X 1.2 | MATRIX |

Karena itu label layar **Category 1 … Category 5**, bukan "gramatur / ukuran / merek".

### 5.4 Layar: dropdown yang saling terkait

Pilih Category 1 → Category 2 hanya berisi anak Category 1 itu → … → Category 5, dan kode item tersusun selagi memilih.
Menambah entri di tingkat N = pilih dulu tingkat 1 sampai N-1 lewat dropdown, lalu isi nama + prefix tingkat N.
Daftar rata dengan kolom Level + Parent (grid v2 `MatCategoryGrid`, 2026-10-02) **salah** dan harus diganti.

### 5.5 Yang bukan acuan

`tm2old` (ScriptCase V1) hanya punya 2 tingkat klasifikasi di `mst_item`: `mst_item_material_cat` (10 baris) →
`mst_item_material_type` (25 baris). Itu **bukan** pembentuk kode (kode item V1 = nama item) dan tidak menggantikan
rantai 5 tingkat.

### 5.6 Keadaan kode item sekarang di `mat_raw` (dicek 2026-10-03 di `ddtmst`, 11.401 item)

- Kode mengikuti rantai prefix lengkap: ±100 item (`PCC…`, `PDA…`, `PKA…`, `PRA…`, `PY…`, `IOH…`).
- Kode sementara hasil impor, hanya punya Category 1: ±5.800 item berpola huruf + `Z` + 5 angka (`PZ00001`, `XZ00002`,
  `IZ…`, `GZ…`, `TZ…`, `LZ…`). Hurufnya tidak selalu sama dengan prefix Category 1 (Others = `X` padahal prefix `O`,
  Lamination = `L` padahal prefix `F`).
- Kode = nama item lama, tanpa kategori: ±5.400 item.
- `mat_raw.prefix5_id` belum pernah diisi.

Merapikan kode item lama ke rantai 5 tingkat = keputusan user (data lama tidak 100% benar; tanya dulu).

### 5.7 Kejanggalan di data asal (jangan diperbaiki diam-diam)

- Nama dengan spasi di ujung: `BLANKET `, `CITO `, `60 GR `, `BUSA `.
- Tingkat kembar beda tulisan di Ivory: `210gr` (G) dan `210 GR` (2); `230gr` (H) dan `230 GR` (3).
- `doc_rmprefix2` id 26, 27: `category1` = `Chemical` (tidak ada di tingkat 1; yang ada `Chemical Liquid`, prefix `Q`).
- `doc_rmprefix2` id 66 `BOTTCHER PRO AR`: `prefix2` kosong.
- `doc_rmprefix4` id 107: nama `Duplex › 250gr › 104 X 66 CM` tetapi prefix `P·R·A·13` (= Art Papper › 85gr).
- `doc_rmprefix4` id 158 `Ink › DGM › 11581 › ZETLA BROWN`: tidak punya induk tingkat 3.
- `doc_rmprefix5` Kraft: `category5` kosong tetapi `prefix5` terisi (merek belum diketahui, nomor sudah dipakai).
- Dua baris `R3` di bawah Plastik (prefix `3` dan `4`).

## 4. Kalau mau tambah kategori baru (layar v2, 2026-10-03)

Layar **Master Data > Master Raw Material Category** (`/v2/s/md-matcat`, kedua situs). Pilih Category 1, lalu Category 2
hanya berisi isi di bawahnya, dan seterusnya; kode item tersusun selagi memilih. **Add** di bawah satu tingkat = entri baru
di bawah rantai yang sedang dipilih; isi Name + Prefix lalu Save. Category 1 juga punya Stock Class + tiga akun bawaan.
Layar v1 (`/master/mat-category`, pohon Level + Induk) sudah diarsipkan.

## 6. Yang dibangun 2026-10-03 (status + serah terima)

| Bagian | File |
|---|---|
| Objek DevLib dropdown berantai penyusun kode (umum, dipakai lagi untuk Product) | `packages/devlib/src/Components/CodeChain.php` (`x-dl::code-chain`), `resources/assets/js/components/code-chain.js`, demo `devlib/resources/views/demos/code-chain.blade.php` |
| Aturan rantai + kode (posisi terkunci, prefix per tingkat, kode tidak bentrok, ganti nama ikut ke `mat_raw`) | `app/Services/Master/MaterialCategoryService.php` |
| Layar + daftar + cetakan | `resources/views/v2/screens/md-matcat.blade.php`, `app/Support/V2/Grid/Master/MatCategoryGrid.php` (print = seluruh rantai, route `v2.report.print`), route simpan `v2.matcat.save` |
| Pagar | `tests/Feature/MaterialCategoryChainTest.php`: setiap baris `doc_rmprefix1.sql` dicocokkan ke `mat_category` (549 cocok; 7 penyimpangan 5.7 dikunci sebagai daftar KNOWN_*) |
| Help + kamus | `resources/data/help/master_setup.json` (`md-matcat`), migration `2026_10_03_000030_dictionary_material_category_chain` |

**Belum / menunggu user:**
- 7 penyimpangan data 5.7 (ZETLA BROWN tanpa induk, R10 dan BOTTCHER PRO AR hilang, "Chemical" vs "Chemical Liquid",
  104 X 66 CM berprefix Art Papper, tambahan Lamination / Others / Duplex 350gr) -- keputusan user, tidak diubah agen.
- Form Raw Material v1 dan PR masih memakai dropdown berantai lamanya sendiri (rantai benar, tetapi belum `x-dl::code-chain`
  dan belum memakai aturan "kode tidak bentrok" dari service).
- Kode item lama di `mat_raw` (5.6) belum dirapikan.
- **Pola yang sama untuk PRODUCT** (perintah user 2026-10-03: "POLA material category ini jalankan juga untuk PRODUCT ...
  make this can print out") -- belum dimulai; perlu dilihat dulu bagaimana `prod_product.product_code` dibentuk sekarang
  dan tingkat apa saja yang membentuknya, lalu preview ke user sebelum dibangun (`x-dl::code-chain` sudah siap dipakai).
