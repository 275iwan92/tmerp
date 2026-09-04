---
name: screen_production_wo_list
description: Worklist Work Order produksi — grid ringkasan status WO (DRAFT/SCHEDULED/IN_PROGRESS/dst) dengan entry point generate dari Plan Delivery.
---

# Worklist Work Order

**File**: `resources/views/pages/production/wo-list.blade.php`
**Route**: `production.wo.list` (`/production/wo`)
**Koneksi DB utama**: prd (tm2prd) — model `App\Models\Prd\PrdWo`; relasi lintas-DB ke `coOrder.quotation.customer` (sdi/mst)
**Modul**: Production Scheduling & Product Development

## Tujuan
Grid worklist Work Order (`production_scheduling_tier4.md` §2). WO **tidak** dibuat manual dari grid ini — lahir otomatis dari CO Confirmed (MTO) atau cycle MTS Forecast. Tombol "+ Generate dari Plan Delivery" mengarah ke `production.wo.new` (WO Detail dengan `$wo=null`), yang membuka modal picklist Plan Delivery untuk auto-generate. Catatan sejarah: layar terpisah `wo-generate.blade.php` sempat ada lalu DIHAPUS 2026-08-28 atas permintaan user, digabung jadi 1 alur dengan WO Detail. Aktor: PPIC.

## Field & Kolom
Grid: kolom WO/Customer (identity-cell, nama customer dari `coOrder->quotation->customer`, kode = `wo_no`), Tipe (MTO/MTS badge), Qty, Due Date, Status (badge match: DRAFT/SIMULATED/SCHEDULED/IN_PROGRESS/ON_HOLD/COMPLETED/CANCELLED, plus badge 🔥 HOT jika `is_hot_job`), Aksi (↗ Buka). Search pada `wo_no`. Pagination via `HasGridPagination`+`WithPagination`. Stat-row 4 kotak: Total WO, Sedang Berjalan (IN_PROGRESS), Berisiko Telat (`critical_ratio < 1` di operations), Menunggu Simulasi (DRAFT).

## Aksi / Method
- `workOrders()` [Computed] — PrdWo with `coOrder.quotation.customer`, filter search `wo_no`, order desc, paginate.
- `statTotal()`, `statInProgress()`, `statAtRisk()`, `statAwaitingSimulation()` [Computed].
- `openWo(id)` — redirect ke `production.wo.entry`.

## Tabel & Model yang Disentuh
- `App\Models\Prd\PrdWo` → `tm2prd.prd_wo` (PK `wo_id`), relasi `operations` (untuk cek `critical_ratio`), relasi `coOrder` (lintas-DB logis ke tm2sdi `co_order` → `quotation` → `customer` di tm2mst).

## Alur / Business Logic Penting
Statistik "Berisiko Telat" dihitung dari `whereHas('operations', fn q => $q->where('critical_ratio', '<', 1))` — indikasi WO yang jadwalnya sudah tidak realistis (critical ratio scheduling). "Menunggu Simulasi" = WO status DRAFT (belum lewat Scheduling Board).

## Catatan
- Field naming di komentar kode: WO lahir dari `co_order_delivery` (Plan Delivery/CPDEL), bukan langsung dari CO — selaras `production_scheduling_wo_generation_delta.md`.
- Detail generation logic ada di `wo-entry.blade.php`, bukan di layar ini.
