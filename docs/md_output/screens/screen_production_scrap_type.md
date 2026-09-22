---
name: screen_production_scrap_type
description: Master data Tipe Scrap (kategori REJECT/WASTE) untuk dipakai di layar Catat Output WO Detail.
---

# Kelola Tipe Scrap

**File**: `resources/views/pages/production/scrap-type.blade.php`
**Route**: `production.scrap-type` (`/production/scrap-type`)
**Koneksi DB utama**: prd (tm2prd) — model `App\Models\Prd\PrdScrapType`
**Modul**: Production Scheduling & Product Development

## Tujuan
Master data pendukung F15 Production Output Recording (`prd_scrap_type`). PPIC/QA mengelola sendiri kategori scrap (REJECT = barang jadi cacat, WASTE = bahan/proses terbuang) dan tipe di dalamnya, bukan hardcode di kode. Data awal (migration `2026_08_28_000013`) berasal dari kategori umum print-industry MIS (Systrade/PrintVis/eProductivity) — user akan menyusulkan daftar riil PT Telasih Makmur. Dipakai di layar "Catat Output" (WO Detail). Pola grid mirip Rule Library.

## Field & Kolom
Modal form (bukan panel terpisah):
- `category` (select: REJECT | WASTE, default REJECT).
- `type_code` (required, max:30) — otomatis di-uppercase & spasi→underscore saat save.
- `type_name` (required, max:100).

Grid: Kategori (badge), Kode (mono), Nama Tipe, Status (Aktif/Nonaktif), Aksi. Stat-row 3 kotak: Total Tipe, Tipe Reject, Tipe Waste.

## Aksi / Method
- `types()` [Computed] — order by category, sort_order.
- `statTotal()`, `statReject()` (scope `reject()`), `statWaste()` (scope `waste()`) [Computed].
- `openNew()` / `openEdit(id)` / `closeModal()` — kontrol modal.
- `save()` — validasi + create (default `activate=true`, `sort_order=500`) atau update.
- `toggleActive(id)` — flip `activate`.

## Tabel & Model yang Disentuh
- `App\Models\Prd\PrdScrapType` → `tm2prd.prd_scrap_type` (PK `type_id`), punya scope `reject()`/`waste()`.

## Alur / Business Logic Penting
Murni master CRUD via modal. Dikonsumsi oleh layar Catat Output (WO Detail/Monitoring) — TBD detail integrasinya, perlu cek `wo-entry.blade.php` atau monitoring-dashboard untuk pemakaian aktual `PrdScrapType`.

## Catatan
- Data awal masih placeholder dari referensi industri umum, belum final untuk PT Telasih Makmur — komentar kode eksplisit menyebut ini.
