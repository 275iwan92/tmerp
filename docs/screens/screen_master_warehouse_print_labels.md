---
name: screen_master_warehouse_print_labels
description: Cetak Label Rak per lokasi/bin gudang — lembar label (bukan Nota dokumen formal)
---

# Master Gudang — Cetak Label Rak

**File**: `resources/views/pages/master/warehouse/print-labels.blade.php`
**Route**: `master.warehouse.print-labels` (`/master/warehouse/{warehouse}/print-labels`)
**Koneksi DB utama**: mst (tm2mst)
**Modul**: Master Data

## Tujuan
Cetak lembar label rak (grid kartu kecil siap gunting/tempel) untuk semua lokasi/bin aktif dalam 1 gudang. Realisasi item "[P]" (print) dari `checklistmockuperp.md` — "Nota Alokasi / Label Rak" yang sengaja ditunda setelah Form/Grid selesai lebih dulu (sesuai prioritas [F]/[G] sebelum [P]/[D], lihat memory `feedback_form_grid_priority.md`).

## Field & Kolom
Tidak ada form input — murni tampilan cetak read-only. Per kartu label: nama gudang (`warehouse_code · warehouse_name`), `location_code` (bin code, besar/mono), `R{row}-C{col}-S{stack}`, `location_name` (kalau ada).

## Aksi / Method
- `mount(warehouse)` — set `$warehouseId`
- `warehouse()` — computed, `WhWarehouse::with(['locations' => activate=true])->findOrFail()`
- Tombol "🖨 Cetak" murni `onclick="window.print()"` (JS browser native, bukan method Livewire)

## Tabel & Model yang Disentuh
- `App\Models\Master\WhWarehouse` → `tm2mst.wh_warehouse` (read-only)
- Relasi `locations` → `tm2mst.wh_loc` (filter `activate=true` saja yang dicetak)

## Alur / Business Logic Penting
- **Beda bentuk dari Nota transaksi lain** (komentar eksplisit di kode): bukan 1 dokumen formal ber-signature, tapi lembar label grid 4-kolom siap gunting — karena itu **tidak** memakai komponen reusable `resources/views/components/print/*` (dirancang untuk Nota header+parties+items+signatures, standar `printnotastandard.md`). Layar ini pakai layout & print CSS sendiri (`@media print`, `@page { size: A4; margin: 10mm }`).
- Hanya lokasi dengan `activate=true` yang tampil/tercetak.

## Catatan
- Tidak ada TODO eksplisit tersisa di komentar kode — item [P] checklist ini dianggap selesai untuk Warehouse.
