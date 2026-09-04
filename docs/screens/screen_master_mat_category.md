---
name: screen_master_mat_category
description: Master Kategori Bahan Baku (mat_category) — hirarki 5 level self-referencing, tree collapsible
---

# Master Kategori Bahan Baku

**File**: `resources/views/pages/master/mat-category.blade.php`
**Route**: `master.mat-category` (`/master/mat-category`)
**Koneksi DB utama**: mst (tm2mst)
**Modul**: Master Data

## Tujuan
CRUD untuk `mat_category` — hirarki 5 level (Kategori → Jenis → Gramatur/Spec → Ukuran → Merek) pembentuk nama & kode Item Bahan Baku. Menggantikan 5 tabel terpisah `doc_rmprefix1..5` yang dulu **tidak punya layar input sendiri** (cuma dibaca lewat dropdown berantai di Item Bahan Baku & PR) — sekarang officer bisa tambah/nonaktifkan kategori sendiri tanpa insert manual ke DB (lihat memory `project_mat_category_redesign.md`, migration `2026_08_26_000002`). Kategori Utama (Level 1) juga jadi fallback akun akuntansi (Persediaan/HPP/GR-IR) kalau item-nya sendiri tidak diisi.

## Field & Kolom
Form: `level` (1-5, dropdown, **dikunci setelah baris dibuat** — `disabled` saat mode edit), `parent_id` (FK ke `mat_category` level di atasnya, wajib kalau level>1, juga dikunci saat edit), `category_name` (required, max:100), `prefix_char` (required, max:3, auto-uppercase, unique per induk yang sama — divalidasi custom closure), `note`, `cat_activate` (checkbox → kolom `activate`). Khusus Level 1 saja: `stock_class` (RAW/INDIRECT/SERVICE), `default_coa_account_id`, `default_stock_asset_account_id`, `default_gr_ir_account_id` (3 dropdown akun dari `tm2acc.fin_account` yang `is_postable=1`, lintas-DB via query builder biasa — bukan Eloquent relasi, disebut "Default Akuntansi (fallback kalau item-nya sendiri kosong)").

Grid: tree collapsible (chevron ▸/▾, state `$expanded` array id_category), kolom Kategori (identity-cell dengan indent `padding-left: 12+depth*22px`), Level (badge L1-L5), Induk (nama parent), Kelas Stok (hanya diisi utk Level 1), Status, Aksi (Edit+Toggle). Search (`search`) memakai mode **flat** (mengabaikan `$expanded`, supaya hasil pencarian dalam tetap ketemu tanpa harus expand manual). Stat box: Total Kategori, Aktif, Kategori Utama (Level 1 aktif), Nonaktif.

## Aksi / Method
- `visibleRows()` — computed; mode search = flat list; mode normal = flatten hirarki rekursif server-side sesuai `$expanded`, hasil punya properti runtime `_depth`/`_hasChildren`
- `toggleExpand(id)` — expand/collapse node tree
- `parentOptions()` — computed dropdown induk (level-1 yang aktif), kosong kalau `$level<=1`
- `coaAccounts()` — computed, query `DB::connection('tm2acc')->table('fin_account')` (lintas-DB read-only, bukan Eloquent relasi lintas-koneksi — sesuai pola 1 CLAUDE.md §2)
- `updatedLevel()` — reset `parent_id` & clear cache `parentOptions` saat level form berubah
- `openModal(mode, id)`, `showGrid()` — navigasi form/grid
- `save()` — validasi + custom rule unique `prefix_char` per induk sama, field akuntansi hanya disimpan kalau `level===1` (di-null-kan untuk level lain)
- `toggleActive(id)` — toggle `activate`
- `statTotal/statActive/statInactive/statLevel1()` — computed stat box

## Tabel & Model yang Disentuh
- `App\Models\Master\MatCategory` → `tm2mst.mat_category` (koneksi `mst`, PK `id_category`), self-referencing via `parent_id`, scope `level($n)` dipakai.
- Query langsung (bukan model) ke `tm2acc.fin_account` (koneksi `tm2acc`, cross-database read khusus untuk dropdown akun — sesuai CLAUDE.md §2 pola 1, query terpisah).

## Alur / Business Logic Penting
- **Level & Induk dikunci setelah baris dibuat** — pola sama dengan `master/raw-material/entry.blade.php` (komentar eksplisit di kode) — mencegah kerusakan diam-diam pada hirarki yang sudah dipakai material lain. Kalau salah taruh: buat baris baru + nonaktifkan yang lama.
- Kolom akuntansi default (`default_coa_account_id` dst) cuma bermakna di Level 1 — jadi fallback kalau item bahan baku individual tidak mengisi akunnya sendiri.
- Tree collapsible ditambahkan 2026-08-26 atas instruksi user setelah versi flat 553-baris dianggap "tidak dimengerti" — pola chevron expand-row (lihat `griduistandard.md` §9), diperluas jadi rekursif multi-level.

## Catatan
- Tidak ada TODO eksplisit tersisa di komentar kode — redesign dari `doc_rmprefix1..5` disebut sudah selesai.
- Konsumen: dropdown kategori dipakai di Master Bahan Baku (`master/raw-material/entry.blade.php`) dan PR (Procurement).
