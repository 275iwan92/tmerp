---
name: screen_master_uom
description: Master Satuan (uom_unit) — pilot #2 spreadsheet-editable + panel Konversi antar-satuan (uom_conversion)
---

# Master Satuan (UoM)

**File**: `resources/views/pages/master/uom.blade.php`
**Route**: `master.uom` (`/master/uom`)
**Koneksi DB utama**: mst (tm2mst)
**Modul**: Master Data

## Tujuan
2 bagian dalam 1 layar: (1) **List Satuan** (`uom_unit`) — pilot #2 `docs/spreadsheetgridstandard.md` (2026-08-30, klik sel langsung edit); (2) **Panel Konversi** (`uom_conversion`) — relasional, **sengaja tetap modal biasa** (bukan spreadsheet-edit) karena melibatkan pasangan satuan + validasi dimensi. Method modal lama (`openUnitModal`/`saveUnit`) untuk satuan **sengaja dipertahankan** walau toolbar grid sudah pindah ke pola spreadsheet "+ Baris" — masih dites langsung via `Livewire::test()` di `MasterUomSmokeTest`.

## Field & Kolom
**List Satuan** (kolom spreadsheet, `spreadsheetColumns()`): `uom_code` (text, required, tidak bisa diedit lagi setelah tersimpan), `uom_name` (text, required), `dimension` (select: PANJANG/LUAS/MASSA/VOLUME/JUMLAH/WAKTU/SPEC, required), `is_base` (boolean, satuan dasar per dimensi), `activate` (boolean). Kolom aksi `_conversion` (ikon 🔁, method `openConversionPanel`) — **disembunyikan** untuk baris `dimension=SPEC` (satuan SPEC seperti GSM/WARNA tidak bisa dikonversi).

Validasi custom: dimensi `SPEC` **tidak boleh** `is_base=true` (`Validator::after` closure).

**Panel/Modal Konversi** (`uom_src`, `uom_dst`, `conversion_value` numeric >0, `conversion_activate`): validasi `uom_dst != uom_src`, kedua satuan harus dimensi sama, pasangan src→dst tidak boleh duplikat.

Stat box: Total Satuan, Aktif, Satuan Dasar (`is_base=true`), Nonaktif. Banner info: "Konversi hanya sah dalam satu dimensi. Satuan SPEC (GSM, WARNA, dsb.) tak dikonversi."

## Aksi / Method
- `units()` — computed list dasar (dipakai grid non-spreadsheet lama, masih ada)
- `spreadsheetKey/spreadsheetColumns/spreadsheetQuery/spreadsheetRowId/spreadsheetNewRowDefaults/spreadsheetValidateRow/spreadsheetPersistRow` — kontrak trait `HasSpreadsheetGrid`; `spreadsheetPersistRow` otomatis unset `is_base` unit lain di dimensi sama kalau baris ini di-set jadi base (1 base per dimensi)
- `selectedUnit()`, `convertibleUnits()` — computed
- `directConversions()` — computed, semua baris `uom_conversion` yang melibatkan `selectedCode` sebagai src atau dst
- `factorMap()` — computed: **BFS/graph traversal** dari tiap satuan dasar (`is_base`) menyebarkan faktor konversi ke semua satuan terhubung dalam dimensi yang sama (via adjacency list dari `uom_conversion` aktif) — algoritma non-trivial untuk menghitung faktor **tidak langsung** (transitif), bukan cuma pasangan langsung.
- `derivedConversions()` — computed, daftar konversi turunan (transitif) ke semua satuan lain di dimensi yang sama, dihitung dari `factorMap`
- `formatNumber()`, `dimensionBadgeClass()` — helper tampilan
- `selectUnit()/openConversionPanel()/closeConversionPanel()/editSelectedUnit()` — navigasi panel konversi
- `openUnitModal()/showGrid()/saveUnit()` — modal satuan lama (masih ada, tidak dipanggil dari toolbar grid lagi)
- `openConvModal()/closeConvModal()/saveConv()` — CRUD `uom_conversion`, validasi dimensi sama + no-duplicate pair
- `toggleConversionActive(id)`, `toggleUnitActive(code)` — toggle `activate`
- `statTotal/statActive/statBase/statInactive()` — computed stat box

## Tabel & Model yang Disentuh
- `App\Models\Master\UomUnit` → `tm2mst.uom_unit` (koneksi `mst`, PK `uom_code` — kode sebagai PK, bukan surrogate integer)
- `App\Models\Master\UomConversion` → `tm2mst.uom_conversion` (PK `id_conversion`, FK dalam-DB `uom_src`/`uom_dst` ke `uom_unit.uom_code`)
- Trait `App\Livewire\Concerns\HasSpreadsheetGrid` untuk bagian List Satuan

## Alur / Business Logic Penting
- **1 `is_base=true` per dimensi** — pola versioned/rate CLAUDE.md §4 mirip tapi untuk struktur satuan: begitu 1 unit di-set base, semua unit lain di dimensi sama otomatis di-unset.
- **Konversi transitif dihitung on-the-fly** via graph traversal (`factorMap()`) — bukan disimpan sebagai baris terpisah per pasangan; hanya konversi langsung (`directConversions`) yang tersimpan di `uom_conversion`, hasil turunan (mis. A→C lewat B) dihitung real-time.
- Dimensi `SPEC` sengaja tidak boleh dikonversi & tidak boleh punya `is_base` — dipakai untuk satuan non-fisik seperti GSM/WARNA.

## Catatan
- Tidak ada TODO eksplisit tersisa — pilot spreadsheet-grid #2 dianggap berhasil (setelah pilot #1 di Master Currency).
- Konsumen: dropdown UoM dipakai luas — Master Produk (`uom_stock`/`uom_sales`), Master Bahan Baku (`uom_stock`/`uom_purchase`/`uom_usage`), dan kemungkinan modul Inventory/Procurement lain.
