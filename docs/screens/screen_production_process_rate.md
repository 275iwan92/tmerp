---
name: screen_production_process_rate
description: Kelola target rate default (qty/jam) per Produk+Part+Proses+Mesin ATAU Pegawai, dipakai WO Generation dan tombol refresh di Bill of Process WO Detail.
---

# Target Rate Proses x Mesin/Pegawai

**File**: `resources/views/pages/production/process-rate.blade.php`
**Route**: `production.process-rate` (`/production/process-rate`)
**Koneksi DB utama**: mst (tm2mst) — model `App\Models\Master\ProdPartProcessMachineRate` & `ProdPartProcessEmployeeRate` (plus `ProdProduct`, `ProdProcess`, `ProdMachine`, `EmpEmployee`)
**Modul**: Production Scheduling & Product Development

## Tujuan
Kelola target rate produksi default per kombinasi Produk+Part+Proses+Mesin (tab "Mesin") atau Produk+Part+Proses+Pegawai (tab "Pegawai (Manual)"). Skema (`prod_part_process_machine_rate`/`_employee_rate`) sudah ada sejak WO Generation dibangun (`docs/production_scheduling_wo_generation_delta.md` §4.2/4.3), tapi sebelumnya hanya bisa diisi lewat tinker. Layar ini dibuat 2026-08-28 atas permintaan user (dipicu tombol "Link & Act" di Bill of Process WO Detail) supaya PPIC bisa kelola sendiri, bukan hanya lewat `WoGenerationService::refillTargetDefault()`. Dua tabel terpisah untuk Mesin vs Pegawai (keputusan awal sesi WO Generation: target beda sumber daya beda tabel), 1 layar karena bentuk identik.

## Field & Kolom
Toggle tab `resource` (MACHINE | MANUAL). Modal form:
- `product_id` (required, dropdown ProdProduct aktif).
- `part_code` (required, max:50, free text mis. "BODY").
- `process_id` (required, dropdown ProdProcess aktif).
- `machine_id` (required jika tab MACHINE, dropdown ProdMachine aktif) ATAU `employee_id` (required jika tab MANUAL, dropdown EmpEmployee aktif, limit 200).
- `target_qty_per_hour` (required numeric min:0.01).

Grid (per tab): Produk/Part, Proses, Mesin atau Pegawai (kolom header berubah sesuai tab), Target/Jam, Status, Aksi (Edit + toggle-aktif). Stat-row 2 kotak: Total Rate, Aktif (dihitung per tab aktif, pakai scope `active()`).

## Aksi / Method
- `rates()` [Computed] — pilih model sesuai `$resource`, with relasi terkait, order desc.
- `productOptions()`/`processOptions()`/`machineOptions()`/`employeeOptions()` [Computed] — dropdown sumber, semua filter `activate=true`.
- `switchResource(resource)` — ganti tab.
- `openNew()`/`openEdit(id)`/`closeModal()`.
- `save()` — validasi field umum + validasi tambahan `machine_id` atau `employee_id` sesuai tab, create/update ke model yang sesuai.
- `toggleActive(id)` — flip `activate` pada model sesuai tab aktif.

## Tabel & Model yang Disentuh
- `App\Models\Master\ProdPartProcessMachineRate` → tm2mst (PK `rate_id`, scope `active()`).
- `App\Models\Master\ProdPartProcessEmployeeRate` → tm2mst (PK `rate_id`, scope `active()`).
- `App\Models\Master\ProdProduct`, `ProdProcess`, `ProdMachine`, `EmpEmployee` → tm2mst, semua untuk dropdown referensi.

## Alur / Business Logic Penting
Rate default ini dikonsumsi oleh WO Generation (`WoGenerationService::refillTargetDefault()`) dan tombol "↻" (refresh/link) di Bill of Process WO Detail — nilai default ini **selalu bisa di-override manual per WO** (tidak mengunci).

## Catatan
- Sebelumnya data ini hanya bisa diisi via tinker (gap operasional nyata) sebelum layar ini dibangun.
