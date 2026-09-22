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

## 4. Kalau mau tambah kategori baru

Lewat layar **Master Kategori Bahan Baku** (`/master/mat-category`), bukan
insert manual ke DB lagi. Pilih Level → (kalau Level > 1) pilih Induk → isi
Nama & Kode Prefix → simpan. Kalau Level = 1, ada seksi tambahan "Default
Akuntansi" (fallback kalau item individualnya sendiri tidak override akun).
