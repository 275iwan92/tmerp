---
name: screen_production_mts_forecast
description: Entry forecast Make-To-Stock per produk per periode, banding vs actual untuk hitung accuracy_pct; belum ada generator replenishment otomatis.
---

# Forecast MTS

**File**: `resources/views/pages/production/mts-forecast.blade.php`
**Route**: `production.mts-forecast` (`/production/mts-forecast`)
**Koneksi DB utama**: prd (tm2prd) — model `App\Models\Prd\PrdMtsForecast`; mst (tm2mst) untuk `App\Models\Master\ProdProduct` (search produk)
**Modul**: Production Scheduling & Product Development

## Tujuan
Input manual forecast MTS (Make-To-Stock) per produk per periode oleh Direksi/Top Management, lalu dibandingkan dengan actual untuk hitung `accuracy_pct` (`production_scheduling_tier1.md` §5A.8, tier2.md §6). **SENGAJA TIDAK ADA**: generator WO replenishment otomatis saat stok di bawah safety level (F20, "Tier 2 lanjutan" — di luar scope sesi yang membangun layar ini).

## Field & Kolom
Modal "Forecast MTS Baru":
- `productId`/`productName`/`productQuery` — autocomplete cari produk (min 2 karakter, limit 8 hasil) via `productResults()`, lalu `selectProduct()`.
- `periodStart` (required date), `periodEnd` (required date, after_or_equal periodStart).
- `forecastQty` (required numeric min:0.01).

Modal "Isi Qty Actual" (per baris forecast):
- `actualQty` (required numeric min:0).

Grid: Produk, Periode (format `d/m/y`–`d/m/y`), Forecast (angka), Actual (angka atau —), Akurasi (badge % atau "belum diisi"), Aksi ("Isi Actual"). Stat-row 3 kotak: Total Forecast, Akurasi ≥85%, Di Bawah Target.

## Aksi / Method
- `forecasts()` [Computed] — with `product`, order desc.
- `productResults()` [Computed] — search ProdProduct by nama/kode.
- `openNew()`/`closeModal()`/`selectProduct(id)`/`clearProduct()`.
- `save()` — create PrdMtsForecast, `input_by` = login.
- `openActualEntry(id)`/`closeActualEntry()`.
- `saveActual()` — hitung `accuracy = max(0, 1 - ABS(forecast-actual)/forecast)` (Tier2 §6 langkah 2), update `actual_qty`+`accuracy_pct`.
- `statTotal()`, `statAccurate()` (accuracy_pct ≥ 0.85), `statBelowTarget()` (accuracy_pct < 0.85 dan tidak null) [Computed].

## Tabel & Model yang Disentuh
- `App\Models\Prd\PrdMtsForecast` → `tm2prd.prd_mts_forecast` (PK `forecast_id`, kolom `product_id`,`period_start`,`period_end`,`forecast_qty`,`actual_qty`,`accuracy_pct`,`input_by`).
- `App\Models\Master\ProdProduct` → `tm2mst.prod_product` (relasi lintas-DB logis, hanya untuk lookup nama produk di form).

## Alur / Business Logic Penting
Rumus akurasi: `accuracy = 1 - ABS(forecast - actual) / forecast`, floor di 0. Layar ini murni input data historis untuk pengukuran akurasi forecast — tidak memicu WO atau proses lain secara otomatis.

## Catatan
- Komentar kode eksplisit: "SENGAJA TIDAK ADA" generator replenishment otomatis (F20) — jangan asumsikan fitur ini ada di layar/engine manapun tanpa verifikasi.
