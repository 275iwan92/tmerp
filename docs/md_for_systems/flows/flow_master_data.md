---
name: flow_master_data
description: Master Data bukan alur transaksional linear — kumpulan layar kelola data referensi (tm2mst) yang dikonsumsi banyak modul lain
---

# Flow: Master Data

Beda dari modul transaksional (Inquiry, CO, PR/PO, dst), Master Data **tidak** punya urutan langkah wajib — tiap layar independen, dikelola sesuai kebutuhan, dan hasilnya dikonsumsi modul lain kapan saja. Semua di koneksi `mst` (tm2mst), "master netral" single source of truth lintas modul (CLAUDE.md §2). Kolom toggle aktif konsisten `activate` (bukan `is_active`, DEC-FIELDNAME-STATUS).

## Kelompok Master & Layar Kelola

| Kelompok | Layar (docs/screens/) | Prefix tabel | Berwenang mengelola |
|---|---|---|---|
| Produk & Material | `screen_master_produk`, `screen_master_mat_category`, `screen_master_raw_material_index`, `screen_master_raw_material_entry` | `prod_*`, `mat_*` | Product Development / Purchasing (raw material) |
| Partner (Customer/Supplier) | `screen_master_customer`, `screen_master_supplier` | `cust_*`, `sup_*` | Sales (customer), Purchasing (supplier) |
| Organisasi & Karyawan | `screen_master_employee`, `screen_master_shift` | `emp_*`, `org_*` | HR |
| Gudang | `screen_master_warehouse_index`, `screen_master_warehouse_entry`, `screen_master_warehouse_print_labels` | `wh_*` | Inventory/PPIC |
| Mesin | `screen_master_machine` | `prod_*` (mesin ikut skema produksi) | Production/PPIC |
| Finance-adjacent | `screen_master_currency`, `screen_master_exchange_rate` | `fin_*` | Finance & Accounting |
| Satuan & Kalender | `screen_master_uom`, `screen_master_holiday` | `uom_*`, `sys_*` | Setup/Admin |

## Konsumsi Lintas Modul (ringkas)

| Master | Dikonsumsi modul |
|---|---|
| Customer (`cust_customer`) | Inquiry, Quotation, CO, Dispatch/DO |
| Supplier (`sup_supplier`) | Procurement (PR/PO/PQ/PRT/PV) |
| Produk & Part (`prod_*`) | Inquiry (Tipe Produk, dieline), ProDev, ProCost |
| Material (`mat_*`, incl. `mat_coating_rate`/`mat_sheet_rate`/`mat_grammage`) | Inquiry, ProCost (CostingEngine rate versioned) |
| Employee (`emp_*`) | HRM, Payroll (belum dibangun), auth security-setup (resolve tgl lahir/HP) |
| Warehouse (`wh_*`) | Inventory (Stock In/Out/Adjustment), Dispatch |
| Machine (`prod_*` mesin) | Production Scheduling (Scheduling Board, WO), ProDev routing |
| Currency/Exchange Rate (`fin_*`) | Finance, Quotation/CO (kalau ada transaksi valas) |
| UOM (`uom_*`) | Hampir semua modul transaksi (qty/satuan) |
| Holiday (`sys_holiday`) | Production Scheduling (`MachineCalendarService`, jadwal sadar hari libur) |

## Catatan
Sifat "netral" berarti Master Data TIDAK PERNAH menyimpan nilai transaksional (harga jual, status dokumen) — hanya data referensi yang dibekukan (snapshot) oleh modul transaksi saat dipakai (prinsip snapshot/beku, CLAUDE.md §4). Perubahan di Master TIDAK mengubah transaksi lama yang sudah membekukan nilainya.
