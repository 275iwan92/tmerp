---
name: screen_inquiry_imposition
description: Layar Imposition Engine per-inquiry — hitung layout produksi (plano/UP/orientasi) per part dan konfirmasi hasilnya.
---

# Imposition Engine (Per-Inquiry)

**File**: `resources/views/pages/inquiry/imposition.blade.php`
**Route**: `inquiry.imposition` (`/inquiry/{inquiry}/imposition`)
**Koneksi DB utama**: sdi (tm2sdi), baca `mat_plano_size` dari mst (tm2mst)
**Modul**: Inquiry Product / ProDev Engine

## Tujuan
Layar kerja Product Designer/ProDev untuk 1 inquiry: memilih part, menghitung layout imposisi (berapa banyak UP muat di 1 plano, orientasi tegak/putar 90°/campur) memakai `ImpositionEngine`, memilih ukuran plano dari saran terbaik, lalu mengonfirmasi hasil. Hasil confirmed auto-mengalir ke Costing (`iqp_costing_part`). Ini mengimplementasikan pola "engine suggests, officer confirms" (CLAUDE.md §4) dan merupakan bagian dari ProDev Engine (goal: layout produksi fisik paling hemat bahan — lihat `project_inqprod_engine_goal.md`).

## Field & Kolom
FORM (per-layout, editable selama belum `stat_confirm`):
- `flat_w_mm`, `flat_h_mm` — bentangan flat 1 up (default dari `iqp_part.flat_w/flat_h`, bisa dioverride).
- `gap_mm` — jarak antar-up.
- `plano_w_mm`, `plano_h_mm` — ukuran plano (default dari `mat_plano_size` aktif pertama).
- `gripper_mm`, `margin_mm` — margin cetak.
- `method` (readonly) — metode terpilih hasil engine (TEGAK/PUTAR/CAMPUR).

Read-only/computed: `up_count`, `efficiency_pct`, `waste_pct`, visual layout (grid cell posisi), tabel "Saran Plano Terbaik" (per `mat_plano_size` aktif: up & efisiensi).

## Aksi / Method
- `mount(int $inquiry)` — set part pertama sebagai aktif, panggil `ensureLayout()`.
- `ensureLayout(int $partId)` (protected) — buat baris `IqpLayout` default kalau belum ada, lalu `recompute()`.
- `recompute(int $partId)` (protected) — jalankan `ImpositionEngine::run()` + `cells()`, simpan hasil ke `IqpLayout` (skip kalau sudah `stat_confirm`).
- `switchPart(int $partId)` — pindah part aktif.
- `updateField(string $field, $value)` — update salah satu field layout (whitelist: flat_w_mm/flat_h_mm/gap_mm/plano_w_mm/plano_h_mm/gripper_mm/margin_mm), lalu `recompute()`. Diblok kalau `stat_confirm`.
- `pickPlano(int $planoSizeId)` — terapkan ukuran plano dari saran, lalu `recompute()`.
- `confirm()` — set `stat_confirm=true` + `confirmed_by/at`, lalu auto-update `IqpCostingPart` (kolom `i_panjang_plano_cm`, `i_lebar_plano_cm`, `i_cut_per_plano`) kalau costing_part untuk part ini sudah ada.
- `unlock()` — buka kunci (`stat_confirm=false`, reset confirmed_by/at).
- `#[Computed]` inquiry, parts, layout, planoSizes, suggestions, visual — data turunan untuk render.

## Tabel & Model yang Disentuh
- `App\Models\Sdi\IqpInquiry`, `IqpPart`, `IqpLayout`, `IqpCostingPart` — semua koneksi sdi (tm2sdi).
- `App\Models\Master\MatPlanoSize` — koneksi mst (tm2mst), query terpisah (pola 1 — bukan JOIN lintas-DB).
- `App\Services\Imposition\ImpositionEngine` — service murni (bukan Eloquent), method `run()` dan `cells()`.

## Alur / Business Logic Penting
- 1 part = 1 baris `IqpLayout`, dibuat otomatis saat part pertama kali dibuka di layar ini (`ensureLayout`).
- Semua field input dikunci (`@disabled`) begitu `stat_confirm=true` — mencegah edit setelah konfirmasi; harus `unlock()` dulu untuk mengubah lagi.
- `confirm()` mensyaratkan `up_count >= 1` (tidak bisa confirm kalau engine gagal menghasilkan layout valid).
- Auto-fill ke Costing terjadi HANYA saat confirm dan HANYA kalau `IqpCostingPart` untuk part tsb sudah ada (kalau belum ada baris costing_part, tidak ada yang diupdate — bukan error).
- Visual layout digambar manual dari `layout_detail` (bukan render TMDieline) — kotak-kotak cell dengan warna beda untuk orientasi tegak vs putar 90°, plus area gripper.

## Catatan
Komentar kode menandai referensi `productcosting_tier1-4.md §1.1` untuk pola auto-fill ke Costing. Tidak ada TODO eksplisit lain di file ini.
