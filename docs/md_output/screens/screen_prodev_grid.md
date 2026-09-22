---
name: screen_prodev_grid
description: Grid worklist Pro-Dev (daftar prod_part) sebelum masuk workbench ProDev, menampilkan asal part (Inquiry vs Manual) dan status layout.
---

# Pro-Dev (Worklist)

**File**: `resources/views/pages/production/prodev-list.blade.php`
**Route**: `production.prodev.list` (`/production/prodev-list`)
**Koneksi DB utama**: mst (tm2mst) untuk ProdPart; sdi (tm2sdi) untuk IqpPart (cek asal Inquiry) — lintas-DB via query terpisah (pola 1, cocokkan di PHP)
**Modul**: Production Scheduling & Product Development

## Tujuan
Grid depan sebelum masuk workbench ProDev (`production.prodev`). Dibuat 2026-09-03 untuk menegakkan pola griduistandard.md (grid dulu → klik 2x buka detail) — sebelumnya menu langsung nyemplung ke form/workbench, dikoreksi user. Grid ini murni "↗ Buka" (tanpa New Record/Confirm) karena part baru hanya lahir dari Inquiry ("Kirim ke ProDev") atau dummy seed, bukan dibuat dari grid ini. Aktor: ProDev officer.

## Field & Kolom
Grid saja (tanpa form):
- Kolom Part — identity-cell (`part_name` + `displayMastercardCode()`).
- Kolom Produk — `product->product_name`.
- Kolom Asal — badge "📥 Inquiry" (kalau ada `iqp_part.prod_part_id` menunjuk ke part ini) atau "✎ Manual".
- Kolom Status Layout — BELUM MULAI / DRAFT / CONFIRMED (dari layout group terbaru).
- Aksi — ↗ Buka Pro-Dev.

Search (`#[Url] $search`) match `part_name`, `part_code`, `mastercard_code`, atau `product.product_name`. Stat-row 3 kotak: Total Part, Sudah Confirm, Belum Confirm.

## Aksi / Method
- `open(id)` — redirect ke `production.prodev` dengan `part` param.
- `parts()` [Computed] — ProdPart with `product`,`layoutGroups`, filter search, order desc id_part, limit 200.
- `originPartIds()` [Computed] — `IqpPart::whereNotNull('prod_part_id')->distinct()->pluck('prod_part_id')->flip()` — dipakai deteksi Mode #1 (dari Inquiry).
- `isFromInquiry(part)` — cek `originPartIds->has($part->id_part)`.
- `layoutStatus(part)` — ambil layout group terbaru, return BELUM MULAI/DRAFT/CONFIRMED.
- `layoutStatusBadgeClass(status)` — mapping warna badge.
- `statTotal()`, `statConfirmed()`, `statPending()` [Computed].

## Tabel & Model yang Disentuh
- `App\Models\Master\ProdPart` → tm2mst (relasi `product`, `layoutGroups`).
- `App\Models\Sdi\IqpPart` → tm2sdi, kolom `prod_part_id` (nullable FK logis ke `prod_part` di DB lain — bukan FK fisik, lintas-DB).

## Alur / Business Logic Penting
- Deteksi "Mode #1 vs Mode #2" (lihat CLAUDE.md §4 ProDev 2-mode): part dianggap Mode #1 (integrasi dari Inquiry) kalau minimal 1 `iqp_part` menunjuk balik via `prod_part_id`; selain itu dianggap Mode #2 (manual/standalone).
- Status Layout diambil dari `prod_layout_group` (relasi `layoutGroups`) terbaru per part — CONFIRMED artinya sudah lolos ProDev Engine.

## Catatan
- Grid ini murni entry point; logic ProDev sesungguhnya (Engine Tingkat 1/2, Tool #2/#4/#10) ada di `prodev.blade.php`.
