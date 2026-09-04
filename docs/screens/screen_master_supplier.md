---
name: screen_master_supplier
description: Master Supplier — grid/form/detail 3-panel, pola sama dengan Master Customer
---

# Master Supplier

**File**: `resources/views/pages/master/supplier.blade.php`
**Route**: `master.supplier` (`/master/supplier`)
**Koneksi DB utama**: mst (tm2mst)
**Modul**: Master Data

## Tujuan
CRUD untuk `sup_supplier` — data pemasok/vendor & subkontraktor. **Pola sama persis dengan `pages::master.customer`** (komentar eksplisit di kode: "lihat file itu untuk komentar arsitektur lebih lengkap") tapi dengan 1 perbedaan struktural: supplier punya **3 panel** (grid/form/**detail**), bukan cuma grid/form — panel detail read-only menampilkan ringkasan supplier + kontak/cabang tambahan + placeholder riwayat PO (belum ada, modul Purchasing tm2pur belum dibangun untuk relasi ini secara aktual — walau modul Procurement lain sudah live).

## Field & Kolom
Form terbagi 4 tab: **Identitas** (`supplier_code` required unique max:50, `supplier_name` required max:200, `category` FK `sup_category.category_code`, `tax_code` NPWP, `activate` checkbox, `is_blacklisted` checkbox "🚫 Blokir supplier ini (bermasalah)" — beda dari Customer, di sini blokir cuma checkbox biasa di form bukan alur modal terpisah, `note`), **Alamat** (`default_address`, `default_area_id`/`default_area_level`/`address_label` via `<livewire:address-picker>`, `postal_code`), **Kontak PIC** (`contact_name`, `phone`, `phone_2`, `fax`, `email`, `website`), **Termin & Pajak** (`payment_term` FK `fin_payment_term`, `tax_purchase_value`/`tax_service_value` numeric 0-100 persen). `is_subcon` (boolean) ada di property tapi tidak terlihat ada input form eksplisit di tab manapun — kemungkinan diset di tempat lain / TBD - perlu verifikasi lanjut kalau field ini dipakai di form.

Grid: kolom Supplier (identity-cell), Kategori, Kontak PIC, Termin, Status (Aktif/Nonaktif + badge 🚫 Blokir), Aksi (Edit+Toggle). Search (`q`) di `supplier_name`/`supplier_code`/`contact_name`. Stat box: Total Supplier, Aktif, Subkontraktor (`is_subcon=true`), Terblokir.

Panel Detail: header nama+badge blokir, info kode/kategori/termin, placeholder "Riwayat Purchase Order belum tersedia — modul Purchasing (tm2pur) belum dibangun", catatan (kalau ada), 2 kartu (Kontak dengan badge UTAMA untuk kontak default form + list kontak tambahan; Alamat dengan badge UTAMA untuk alamat header + list cabang tambahan).

## Aksi / Method
- `suppliers()` — computed list + search (limit 200), eager-load `paymentTerm`
- `selectedSupplier()` — computed dengan relasi `branches`+`contacts`
- `paymentTerms()`, `categories()` — computed dropdown
- `onAddressPicked()` — listener `address-picked`, beda handling `supplier-address` vs `supplier-branch-address`
- `openBranchModal/closeBranchModal/saveBranch/deleteBranch/setDefaultBranch` — CRUD `sup_supplier_branch` (pola identik Customer)
- `openContactModal/closeContactModal/saveContact/deleteContact/setDefaultContact` — CRUD `sup_supplier_contact`
- `updatedQ()/selectRow()/editRow()/showGrid()/showDetail()/newSupplier()/editSupplier()` — navigasi 3 panel
- `setFormTab()/tabHasError()` — switch tab identitas/alamat/kontak/termin
- `resetForm()` — reset field form
- `save()` — validasi + `unique:tm2mst.sup_supplier,supplier_code`, auto-pindah tab pertama yang error
- `toggleActive(id)` — toggle `activate`
- `metricTotal/metricActive/metricBlocked/metricSubcon()` — computed stat box
- `deleteSupplier()` — **soft-guard**: cek `SupplierGuardService::blockingReasons()` dulu; kalau ada alasan (masih dipakai transaksi) → **nonaktifkan** saja (bukan hapus) + pesan; kalau tidak ada alasan → hapus permanen
- `toggleBlacklist()` — toggle `is_blacklisted` dari panel Detail (terpisah dari checkbox di form)

## Tabel & Model yang Disentuh
- `App\Models\Master\SupSupplier` → `tm2mst.sup_supplier` (koneksi `mst`, PK `id_supplier`), migration `2026_08_18_000005_create_sup_supplier_table.php`
- `App\Models\Master\SupSupplierBranch` → `tm2mst.sup_supplier_branch`
- `App\Models\Master\SupSupplierContact` → `tm2mst.sup_supplier_contact`
- `App\Models\Master\SupCategory` → `tm2mst.sup_category` (dropdown kategori)
- `App\Models\Master\FinPaymentTerm` → `tm2mst.fin_payment_term` (dropdown termin)
- `App\Services\Master\SupplierGuardService` — cek dependensi transaksi sebelum hapus permanen

## Alur / Business Logic Penting
- **Hapus vs nonaktifkan**: `deleteSupplier()` **tidak langsung hapus** — cek dulu `SupplierGuardService::blockingReasons()`. Komentar eksplisit di kode: "Hapus permanen HANYA kalau tidak ada transaksi — saat ini selalu boleh, modul Purchasing belum ada; lihat komentar di service-nya untuk kapan ini akan mulai memblokir." Artinya guard ini **belum benar-benar memblokir apa pun** sampai ada modul yang menghasilkan alasan.
- Blokir supplier: **beda pola dari Customer** — di form ada checkbox `is_blacklisted` langsung (bukan modal + alasan wajib seperti Customer), dan di panel Detail ada tombol toggle cepat `toggleBlacklist()` tanpa modal otorisasi khusus (lebih longgar dari Customer, kemungkinan karena risiko lebih rendah — cek ke user kalau perlu diseragamkan).
- 1 default per supplier untuk kontak & cabang tambahan (sama pola Customer).

## Catatan
- `is_subcon` (boolean, subkontraktor) ada sebagai property & dipakai di stat box `metricSubcon`, tapi tidak ditemukan input form eksplisit di 4 tab — TBD, perlu verifikasi apakah field ini memang belum ada UI-nya atau di-set dari tempat lain (mis. saat pemilihan kategori tertentu).
- Riwayat Purchase Order di panel Detail masih placeholder — menunggu integrasi modul Purchasing (tm2pur) menampilkan PO per supplier di layar ini.
