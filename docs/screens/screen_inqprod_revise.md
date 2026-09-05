---
name: screen_inquiry_revise
description: Layar REVISI — ubah spesifikasi via copy-record + versi baru, scope selector (Material/Dimensi/Artwork/Warna/Proses) menentukan blok edit yang aktif.
---

# Ubah Spesifikasi (REVISE)

**File**: `resources/views/pages/inquiry/revise.blade.php`
**Route**: `inquiry.revise` (`/inquiry/revise`, param `#[Url] id` atau `#[Url] source_id`)
**Koneksi DB utama**: sdi (tm2sdi), baca lintas-DB ke mst (tm2mst) untuk material & dimensi tipe
**Modul**: Inquiry Product

## Tujuan
Layar entry doc-type **REVISI** — dipakai Sales saat reorder dengan spec BERUBAH (beda dari REKALKULASI yang spec-nya sama). Pola copy-record + versi baru: setiap REVISE membuat inquiry baru (`revision_no` naik), inquiry lama ditandai `CANCELLED`/`SUPERSEDED` — dokumen hilir (quotation/CO) yang sudah pakai versi lama tetap aman karena snapshot beku, tidak tersentuh. Sales wajib pilih manual **scope revisi** (checkbox multi-select: MATERIAL/DIMENSION/ARTWORK/COLOR/PROCESS) — sistem tidak menebak apa yang berubah, supaya tidak memicu proof/imposisi ulang yang mahal secara tidak perlu. Field di luar scope tetap reuse verbatim (read-only) dari inquiry baku.

## Field & Kolom
- Trace: `source->inquiry_no` (v`revision_no`) -> versi baru (v`revision_no+1`).
- Identitas readonly: Customer, Produk, Versi baru.
- **Scope Revisi** (`$scope` array, checkbox toggle via `toggleScope()`): MATERIAL (Bahan/gramasi -> costing ulang), DIMENSION (Ukuran P×L×T -> imposisi ulang + costing), ARTWORK (Desain -> proof warna ulang), COLOR (Tinta/plate -> costing ulang), PROCESS (Urutan/finishing -> costing ulang). Wajib minimal 1. `revise_reason` (required, textarea/input, max 255).
- **Matriks Konsekuensi** (computed `triggers()`, otomatis dari scope, display-only): Imposisi ulang (trigger DIMENSION), Proof warna ulang (trigger ARTWORK), Costing/HPP ulang (trigger MATERIAL/DIMENSION/COLOR/PROCESS), Due-date reset (trigger ARTWORK).
- Blok edit conditional (tampil hanya kalau scope-nya dicentang), semua per part dari array `$parts`:
  - **ARTWORK**: `design_number` (No. Design/Proof), upload artwork baru per part (temp Livewire upload, preview old vs new side-by-side, saran warna dari `analyzeImageColors()`), tombol hapus artwork baru.
  - **COLOR**: `cfg_color` per part (notasi mis. 4/4), `cfg_ink` (catatan tinta/plate).
  - **DIMENSION**: field dinamis dari `ProdType::params` (per `type_code` part) atau fallback `flat_w`/`flat_h` kalau tipe tak punya param terdaftar.
  - **MATERIAL**: sub-repeater per part — autocomplete `MatRaw` (`materialResults`/`selectMaterial`), `gram_weight`, tombol tambah/hapus baris.
  - **PROCESS**: sub-repeater per part, `process_name` free-text, tambah/hapus baris.

## Aksi / Method
- `mount()` — 2 jalur seperti recalc.blade.php: `$id` (buka existing REVISE) atau `$source_id` (draft baru; artwork lama dipindah ke `source_artwork_path` supaya versi lama/baru tidak berbagi 1 file fisik, id part/material di-null-kan supaya `persistParts()` create baris baru).
- `toggleScope(string $key)` — tambah/hapus 1 key dari `$scope`.
- `#[Computed] triggers()` — matriks konsekuensi dari scope aktif.
- `dimParams(string $typeCode)` — cache param dimensi per `ProdType` (mirip form.blade.php).
- Material: `addMaterialRow`, `removeMaterialRow`, `materialResults`, `selectMaterial`.
- Artwork: `updatedArtworkUploads` (analisis warna via `analyzeImageColors()` — kode identik form.blade.php, sengaja tidak diekstrak ke trait/service, 2 file kecil), `removeArtwork`, `persistPendingArtwork` (protected — prioritas upload baru > salin fisik artwork lama ke path part baru).
- Process: `addProcessRow`, `removeProcessRow`.
- `persistPartMaterials`/`persistPartProcesses`/`persistParts` (protected) — replace-all pattern untuk processes (tidak ada tracking id/branch di UI ini).
- `persist(string $subState)` (protected) — transaksi `tm2sdi`: update existing atau create `IqpInquiry` baru (`inquiry_type=REVISE`, `revision_no+1`, clone tier dari source) + tandai `$source` `CANCELLED`/`stat_cancel=true` dengan `cancel_reason` "Digantikan oleh revisi v{n}".
- `saveDraft()` — persist('DRAFT').
- `submitToCosting()` — persist('SUBMITTED') + `stat_confirm=true`, kunci form.

## Tabel & Model yang Disentuh
- `App\Models\Sdi\IqpInquiry` (`iqp_inquiry`, sdi) — versi baru & `parentInquiry()` ke baku, `reviseScopeArray()` (parse kolom `revise_scope` CSV), kolom `revise_reason`, `design_number`.
- `App\Models\Sdi\IqpPart` (`iqp_part`, sdi) — snapshot `dieline_spec` dibekukan ulang per scope DIMENSION.
- `App\Models\Sdi\IqpPartMaterial` (`iqp_part_material`, sdi).
- `App\Models\Master\MatRaw` (`mat_raw`, mst, lintas-DB pola 1) — autocomplete bahan scope MATERIAL.
- `App\Models\Master\ProdType` (`prod_type`, mst) — param dimensi scope DIMENSION, dieline_spec re-freeze.
- `App\Models\Sys\SysConstant` — `INQUIRY_ARTWORK_MAX_MB`.

## Alur / Business Logic Penting
- **Copy-record + versi baru** (lineage R-CO-1/R-REV, `docs/inquiryproduct_delta_revise.md`): setiap submit REVISE menghasilkan `IqpInquiry` baru dengan `revision_no` naik; versi lama dikunci (`stat_confirm=true`) & dibatalkan (`stat_cancel=true`, `sub_state=CANCELLED`) — bukan diedit in-place. CO/Quotation lama tetap aman (snapshot beku, prinsip §4 CLAUDE.md).
- **Scope manual, bukan auto-detect** — sistem sengaja tidak membandingkan nilai lama-vs-baru untuk menyimpulkan scope; Sales harus pilih eksplisit supaya tidak salah memicu proof/imposisi mahal.
- **Field di luar scope = reuse verbatim** — hanya blok scope yang dicentang yang render sebagai input edit; sisanya tidak ditampilkan sebagai form sama sekali di layar ini (beda dari RECALC yang menampilkan SEMUA field sebagai read-only preview).
- **Artwork lama vs baru**: 2 versi Inquiry sengaja tidak berbagi 1 file fisik — artwork lama disalin (bukan direferensikan) ke path milik part versi baru, supaya hapus/ganti di 1 versi tidak pernah merusak versi lain.

## Catatan
- Upload artwork REVISE ditambahkan 2026-09-04 ("REVISE juga harus ada upload image jika terjadi perubahan image") — pola sama persis form.blade.php, TIDAK porting suggestion engine bahan/finishing dari histori (di luar scope revisi, REVISE sudah punya alur substitusi bahan sendiri via scope MATERIAL).
- Proses (scope PROCESS) tidak melacak urutan/branch/flow di UI ini (domain itu ada di ProDev routing) — replace-all per save cukup untuk catatan proses REVISE.
