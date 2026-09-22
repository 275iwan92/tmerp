---
name: screen_recruitment_offer_list
description: Grid worklist Offering — daftar offer terkirim + shortcut ke kandidat yang belum dibuatkan offer.
---

# Offering — Grid (L7)

**File**: `resources/views/pages/recruitment/offer/list.blade.php`
**Route**: `hrm.recruitment.offer.list` — `GET /hrm/recruitment/offer` (middleware `auth`, `force.password`, `module:HRM`)
**Koneksi DB utama**: `tm2hrr`
**Modul**: Recruitment (Internal) — L7 (grid), lihat komentar kode: `docs/recruitment_tier4.md` SECTION 12

## Tujuan
Grid worklist semua `AppOffer` yang pernah dibuat, plus panel shortcut kandidat berstatus `OFFERING` yang belum punya offer sama sekali (offer pertama dibuat lewat layar Entry, bukan dari grid ini — klik chip kandidat langsung redirect ke `offer/entry` yang auto-create draft offer di `mount()`).

## Field & Kolom
**Filter/search**: `search` (live, debounce 300ms) — cari nama kandidat via relasi `application.candidate`.

**Kotak statistik**: Menunggu Offer (`AppApplication.status=OFFERING` tanpa offer), Menunggu Approval (`AppOffer.status=PENDING_APPROVAL`), Diterima Kandidat (`AppOffer.status=ACCEPTED`).

**Panel "Menunggu Dibuatkan Offer"**: chip nama kandidat, link langsung ke `offer.entry`.

**Kolom grid**: Kandidat, Gaji Diusulkan (rata kanan, format ribuan), Jenis Kontrak, Status (badge warna sesuai status), Aksi (tombol "Buka" ↗).

## Aksi / Method
- `updatedSearch()` — reset pagination
- `awaitingOffer()` [Computed] — `AppApplication::where('status', 'OFFERING')->whereDoesntHave('offers')`
- `stats()` [Computed] — 3 angka kotak statistik
- `rows()` [Computed] — `AppOffer::with('application.candidate')`, paginate 20

Tidak ada aksi create/edit langsung di grid ini — semua mutasi terjadi di layar Entry (klik 2x baris atau tombol "Buka").

## Tabel & Model yang Disentuh
- `AppOffer` (`app_offer`, `tm2hrr`, PK `id_offer`) — utama, read-only di layar ini
- `AppApplication` (`app_application`, `tm2hrr`) — sumber panel "Menunggu Dibuatkan Offer"

## Alur / Business Logic Penting
Klik 2x baris (atau tombol "Buka") membuka `offer/entry.blade.php` dengan `id_application` (bukan `id_offer`) sebagai parameter route — konsisten dengan pola "1 application bisa punya banyak offer historis (revisi), Entry selalu ambil offer terbaru".

## Catatan
- Tidak ada gap/TODO eksplisit di komentar kode untuk layar ini.
