---
name: screen_inquiry_imposition-list
description: "[LEGACY/TIDAK AKTIF] Worklist Imposition Engine lama — digantikan screen_prodev_grid.md. Lihat catatan status di bawah."
---

> **[LEGACY — TIDAK AKTIF, 2026-09-05]** Digantikan oleh grid **ProDev**
> (`screen_prodev_grid.md`, route `production.prodev` list). File ini
> (route `inquiry.imposition.list`) ditinggal, TIDAK dihapus, TIDAK ikut
> pola rename `screen_prodev_*`. Jangan bangun fitur baru di layar ini.

# Imposition Engine (Worklist)

**File**: `resources/views/pages/inquiry/imposition-list.blade.php`
**Route**: `inquiry.imposition.list` (`/inquiry/imposition`, menu `SALES_IMPO`)
**Koneksi DB utama**: sdi (tm2sdi)
**Modul**: Inquiry Product

## Tujuan
Grid worklist yang menampilkan inquiry confirmed (`sub_state` di luar `DRAFT`/`CANCELLED`/`LOST`) dan punya part, sebagai antrian yang perlu diimposisi (dicarikan layout produksi optimal — plano/UP/orientasi). Klik 2x baris (atau ikon Aksi) redirect ke layar `inquiry.imposition` (imposition.blade.php) untuk inquiry tersebut. Ini murni worklist, tidak melakukan perhitungan imposisi sendiri.

## Field & Kolom
GRID:
- Kolom: Inkuiri (identity-cell), Produk, Tipe (badge inquiry_type), Status (badge sub_state), Progres Part (badge "done/total" part yang layout-nya `stat_confirm`), Aksi.
- Filter: `search` (Url-bound) — cocok ke `inquiry_no` atau `product_draft_name`.
- Stat box: Total Perlu Imposisi, Selesai (semua part terkonfirmasi), Belum Selesai.

## Aksi / Method
- `open(int $id)` — redirect ke route `inquiry.imposition`.
- `impositionProgress(IqpInquiry $inquiry)` — hitung "done/total" part berdasarkan `part->layout?->stat_confirm`.
- `isImpositionDone(IqpInquiry $inquiry)` — true kalau semua part sudah confirmed layout-nya.
- `subStateBadgeClass()`, `typeBadgeClass()` — helper warna badge.
- `#[Computed] inquiries()` — query utama.
- `#[Computed] statTotal/statDone/statPending()`.

## Tabel & Model yang Disentuh
- `App\Models\Sdi\IqpInquiry` (`iqp_inquiry`, koneksi sdi), eager-load `customer` (lintas-DB, pola 1) dan `parts.layout` (dalam tm2sdi, ke `prod_layout_group`/tabel layout part — nama relasi `layout` pada part).

## Alur / Business Logic Penting
- Progres per-part dihitung dari flag `stat_confirm` pada layout part — mencerminkan alur ProDev Engine "engine suggests, officer confirms" (CLAUDE.md §4): imposisi jadi "selesai" untuk 1 part hanya setelah officer confirm layout-nya, bukan otomatis setelah engine menyarankan.
- Layar ini adalah pintu masuk ke ProDev Engine dari sisi Inquiry (Mode #1 integrasi — lihat `project_prodev_procost_2mode_architecture.md`).

## Catatan
Tidak ada TODO/gap eksplisit di komentar kode file ini.
