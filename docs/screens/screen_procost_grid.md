---
name: screen_procost_grid
description: Worklist Product Costing — 2 grid berdiri sendiri (Integrated dari Inquiry, Instant standalone), gerbang ke layar costing yang sama.
---

# Product Costing (Worklist) — 2 Mode

**Keputusan user 2026-09-18**: ProCost punya **2 grid terpisah**, masing-masing
route sendiri, dihubungkan lewat switch kecil (`🔗 Integrated` / `🧮 Instant`)
di toolbar (`x-slot:tools`) kedua grid. Landing default menu ProCost selalu
grid **Integrated**.

## Grid 1 — Integrated (default landing)

**File**: `resources/views/pages/inquiry/costing-list.blade.php`
**Route**: `inquiry.costing.list` (`/inquiry/costing`, menu `SALES_COST`)
**Koneksi DB utama**: sdi (tm2sdi)
**Modul**: Inquiry Product

### Tujuan
Grid worklist untuk role Costing/Direksi — menampilkan semua inquiry yang sudah confirmed (`sub_state` di luar `DRAFT`/`CANCELLED`/`LOST`) dan sudah punya minimal 1 part, sebagai antrian yang perlu dihitung HPP-nya. Klik 2x baris (atau ikon Aksi) redirect ke layar `inquiry.costing` (costing.blade.php, route `inquiry.costing` dgn param `{inquiry}`) untuk inquiry tersebut. Halaman ini sendiri tidak menghitung apa pun — murni worklist/gerbang. Ada banner peringatan bahwa layar ini "rahasia" (Sisi A, CLAUDE.md §4) — HPP & margin tidak boleh bocor ke Sales sebelum `doc_status = PUBLISHED`.

## Field & Kolom
GRID:
- Kolom: Inkuiri (identity-cell: avatar+nama produk/prospek+no. inquiry), Produk (`product_draft_name`), Tipe (badge `inquiry_type`: NEW/RECALC/REVISE), Status Inquiry (badge `sub_state`), Status Costing (badge `costing.doc_status`: null="Belum dimulai"/CALCULATED/PENDING_APPROVAL/PUBLISHED/FAILED), Aksi (ikon buka).
- Filter: `search` (Url-bound) — cocok ke `inquiry_no` atau `product_draft_name`.
- Stat box: Total Perlu Costing, Belum Dimulai (costing null), Menunggu Approval Direksi (`doc_status=PENDING_APPROVAL`), Published (`doc_status=PUBLISHED`, subtitle "DEKLASIFIKASI KE SALES").

## Aksi / Method
- `open(int $id)` — redirect ke route `inquiry.costing` dengan param inquiry.
- `subStateBadgeClass()`, `typeBadgeClass()`, `docStatusBadgeClass()` — helper warna badge.
- `#[Computed] inquiries()` — query utama (lihat di bawah).
- `#[Computed] statTotal/statNotStarted/statPendingApproval/statPublished()` — turunan dari `inquiries()`.

## Tabel & Model yang Disentuh
- `App\Models\Sdi\IqpInquiry` (tabel `iqp_inquiry`, koneksi sdi) — query utama dengan eager-load `customer` (relasi ke tm2mst, lintas-DB pola 1: Eloquent relationship query terpisah) dan `costing` (relasi dalam tm2sdi ke `iqp_costing`).
- Filter query: `whereNotIn('sub_state', [...])`, `whereHas('parts')`, `limit(200)`.

## Alur / Business Logic Penting
- Hanya inquiry yang sudah keluar dari DRAFT dan punya part yang muncul di worklist ini (belum ada part = belum siap di-costing).
- Status costing `null` berarti proses costing belum pernah dimulai untuk inquiry tsb.
- Prinsip Sisi A/Sisi B (CLAUDE.md): HPP/margin cuma boleh dilihat role Costing/Direksi di sini; publish (`doc_status=PUBLISHED`) baru mendeklasifikasi harga ke Sales.

### Catatan
Tidak ada TODO/gap eksplisit di komentar kode file ini. Komentar kode menyebut "Componentization grid (2026-08-27)" — helper avatar dipindah ke `App\Support\Grid\Avatar`.

## Grid 2 — Instant (standalone)

**File**: `resources/views/pages/inquiry/costing-instant-list.blade.php`
**Route**: `inquiry.costing.instant.list` (`/costing/instant`, menu `SALES_COST` sama — tidak ada sidebar entry terpisah, dijangkau via switch dari Grid 1)
**Koneksi DB utama**: sdi (tm2sdi)

### Tujuan
Grid **berdiri sendiri** (bukan turunan/filter dari Grid Integrated) untuk dokumen costing `mode=INSTANT` — kalkulator HPP standalone tanpa Inquiry Product (`inquiry_id NULL`). Beda dari Grid 1: punya tombol **"+ New Record"** sendiri (`newInstant()`) karena tidak ada Inquiry yang jadi gerbang masuk — klik langsung bikin baris `iqp_costing` baru (`mode=INSTANT`, `doc_status=DRAFT`) lalu redirect ke form. Klik 2x baris (atau ikon Aksi) buka layar `inquiry.costing` yang SAMA dengan Grid 1, tapi lewat route `inquiry.costing.instant` (param `{costing}`, bukan `{inquiry}`).

### Field & Kolom
- Kolom: Dokumen (identity-cell: `doc_no` atau `Draft #{id}`), Produk (`product_name_draft`, bebas ketik), Status (badge `doc_status`), Reverse? (badge tanggal `reversed_at` kalau sudah pernah di-reverse, `—` kalau belum), Aksi.
- Filter: `search` (Url-bound) — cocok ke `doc_no` atau `product_name_draft`.
- Stat box: Total Instant, Draft, Sudah Dihitung (`doc_status=CALCULATED`), Sudah Direverse (subtitle "JADI MODE INTEGRATED").

### Aksi / Method
- `newInstant()` — `IqpCosting::create(['mode'=>'INSTANT', 'calc_type'=>'NEW', 'doc_status'=>'DRAFT', ...])`, redirect ke `inquiry.costing.instant`.
- `open(int $id)` — redirect ke `inquiry.costing.instant` dengan param `costing`.
- `docStatusBadgeClass()`, `#[Computed] rows()`, `statTotal/statDraft/statCalculated/statReversed()`.

### Catatan
- `reversed_at`/`reversed_by` (kolom baru `iqp_costing`) baru terisi kalau tombol Reverse di form sudah benar-benar jalan — **saat ini masih stub** (`reverseToIntegrated()` cuma flash pesan, lihat `screen_procost_form.md` & `procost_formula_library.md` §V-VI), jadi kolom "Reverse?" akan selalu `—` sampai fitur itu diselesaikan.
- Tidak ada physical FK `customer_id` (lintas-DB ke `mst.cust_customer`) — pola logical FK yang sama dipakai di seluruh proyek (CLAUDE.md §2).
