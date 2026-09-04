---
name: screen_master_warehouse_entry
description: Form Master Gudang (wh_warehouse) + kelola Lokasi/Bin (wh_loc) satu layar
---

# Master Gudang — Form (Entry)

**File**: `resources/views/pages/master/warehouse/entry.blade.php`
**Route**: `master.warehouse.create` (`/master/warehouse/create`) dan `master.warehouse.edit` (`/master/warehouse/{warehouse}/edit`)
**Koneksi DB utama**: mst (tm2mst)
**Modul**: Master Data

## Tujuan
Layar 2-bagian: (1) Identitas & Klasifikasi gudang header (`wh_warehouse`), (2) kelola Lokasi/Storage Bin (`wh_loc`) — hanya aktif setelah header tersimpan (`isEdit`). Form gudang baru langsung redirect ke mode edit setelah simpan supaya bagian Lokasi terbuka.

## Field & Kolom
Header: `warehouse_code` (required, max:20, unique per record), `warehouse_name` (required, max:100), `warehouse_type` (radio button: RM/SFG/FG/MIX/WASTE/TRANSIT, required), `activate` (checkbox).

Lokasi/Bin (mini-form baris baru — pola sama dengan `addCoating()` di `inquiry/costing.blade.php`, bukan `wire:model` per kolom grid): `newRow`, `newCol`, `newStack` (semua wajib), `newCapacity` (opsional, kg). `location_code` (bin code) **auto-generate** format `{warehouse_code}-{row}-{col}-{stack}`, dicek duplikat sebelum insert.

## Aksi / Method
- `mount(?warehouse)` — kalau ada param, load data existing (`isEdit=true`)
- `locations()` — computed, list `wh_loc` untuk warehouse ini, order `row_code`/`col_code`/`stack_code`
- `save()` — validasi (`unique:tm2mst.wh_warehouse,warehouse_code,{id},id_warehouse`), insert/update; kalau insert baru → redirect ke mode edit
- `addLocation()` — guard "simpan gudang dulu" kalau belum `isEdit`; validasi Row/Col/Stack wajib; cek duplikat bin code; auto-set `location_type='WASTE'`+`is_usable=false` kalau `warehouse_type==='WASTE'`, selain itu `STORAGE`+`is_usable=true`
- `removeLocation(locationId)` — hapus 1 baris lokasi (dibatasi `warehouse_id` yang sama)

## Tabel & Model yang Disentuh
- `App\Models\Master\WhWarehouse` → `tm2mst.wh_warehouse` (koneksi `mst`, PK `id_warehouse`)
- `App\Models\Master\WhLoc` → `tm2mst.wh_loc` (FK dalam-DB `warehouse_id`)

## Alur / Business Logic Penting
- Bagian Lokasi/Bin **disabled visual** (`opacity-50 pointer-events-none`) sampai gudang header tersimpan — mencegah insert `wh_loc` tanpa `warehouse_id` valid.
- Bin code auto-generate dari kombinasi kode gudang+row+col+stack, bukan input manual, mencegah duplikat/typo.
- Tipe gudang `WASTE` otomatis membuat semua lokasi barunya `location_type=WASTE` & `is_usable=false` — mencegah bin afkir dianggap storage biasa.
- Ada link "🏷 Cetak Label Rak" ke `master.warehouse.print-labels` (hanya muncul kalau `isEdit`).

## Catatan
- Tidak ada TODO eksplisit tersisa di komentar kode.
