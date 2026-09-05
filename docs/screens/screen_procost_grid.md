---
name: screen_inquiry_costing-list
description: Worklist Product Costing — daftar inquiry yang perlu/sudah dihitung HPP-nya, gerbang ke layar costing per-inquiry.
---

# Product Costing (Worklist)

**File**: `resources/views/pages/inquiry/costing-list.blade.php`
**Route**: `inquiry.costing.list` (`/inquiry/costing`, menu `SALES_COST`)
**Koneksi DB utama**: sdi (tm2sdi)
**Modul**: Inquiry Product

## Tujuan
Grid worklist untuk role Costing/Direksi — menampilkan semua inquiry yang sudah confirmed (`sub_state` di luar `DRAFT`/`CANCELLED`/`LOST`) dan sudah punya minimal 1 part, sebagai antrian yang perlu dihitung HPP-nya. Klik 2x baris (atau ikon Aksi) redirect ke layar `inquiry.costing` (costing.blade.php) untuk inquiry tersebut. Halaman ini sendiri tidak menghitung apa pun — murni worklist/gerbang. Ada banner peringatan bahwa layar ini "rahasia" (Sisi A, CLAUDE.md §4) — HPP & margin tidak boleh bocor ke Sales sebelum `doc_status = PUBLISHED`.

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

## Catatan
Tidak ada TODO/gap eksplisit di komentar kode file ini. Komentar kode menyebut "Componentization grid (2026-08-27)" — helper avatar dipindah ke `App\Support\Grid\Avatar`.
