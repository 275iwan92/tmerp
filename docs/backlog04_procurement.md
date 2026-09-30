# BACKLOG 04 — PROCUREMENT: PR S/D BARANG MASUK

> Pecahan dari `BACKLOG.md` (2026-09-22, permintaan user). Indeks semua
> backlog: **[`BACKLOG.md`](BACKLOG.md)**. Item yang sudah selesai dicatat
> di **[`LOG_JOB_DONE.md`](LOG_JOB_DONE.md)**, bukan di sini.

Purchase Requisition -> Purchase Order -> Purchase Quotation -> penerimaan barang, sampai serah terima ke Inventory.

---


## 4. PROCUREMENT (tm2pur)

- **Purchase Receive — 3 alur, baru 1 yang ada**: PV/SPB saat ini cuma
  cover "Receiving dari PO" (supplier vs PO). **Belum dibangun**:
  "Receiving dari Customer" (consignment, customer kirim bahan sendiri,
  butuh stok terpisah/`ownership_type` — skema belum diputuskan) dan
  "Receiving dari Subkontraktor" (SFG kembali dari pihak ketiga, masuk
  `sfg_stock`). Detail lengkap: `docs/SIDEBAR_MENU_STANDARD.md` §6/§6a.
- **12 dari 18 kategori Level-1 `mat_category`** masih kosong default COA
  account (`default_coa_account_id`/`default_stock_asset_account_id`) —
  layar isinya **SUDAH ADA** (`master/mat-category.blade.php`, bagian
  "Default Akuntansi" muncul otomatis utk kategori Level 1, dicek ulang
  2026-09-02 — sempat salah dicatat seolah belum dibangun). Kategori kosong:
  Gasoline, Solasi, Plastik, CTP Plate, Pack Material, Stationery/ATK, 2×
  Spare Part (Machine/Vehicle), Tools/Equipment, Laptop/Desktop/Networking,
  Jasa/Service, Peralatan Kebersihan — murni tinggal user isi manual per
  kategori (keputusan akun, bukan kode), akun GR/IR universal (813) sudah
  terisi semua.
- **PO Kontan + Kas Kecil — BELUM ADA, dibahas di sesi lain** (dicatat user
  2026-10-01). Pembelian kontan berdampak ke petty cash DAN ke PO (ada input
  "PO Kontan"). Kondisi kode: `po_order.i_po_type` baru `LOCAL`/`IMPORT`/
  `SUBCONTRACT` (belum `CASH`); modul Kas Kecil (saldo, pemegang, plafon,
  kasbon, replenish) belum ada; `fin_voucher.source_type/source_id` sudah bisa
  dipakai menautkan voucher ke PO; SPB sudah jurnal Dr Persediaan / Cr GR/IR.
  Usulan alur (imprest): PO Kontan -> kasbon (Dr Uang Muka Pembelian / Cr Kas
  Kecil) -> SPB -> pertanggungjawaban nota (Dr GR/IR / Cr Uang Muka, selisih
  kembali/kurang) -> replenish kas kecil dari bank. **Keputusan user yang
  ditunggu:** (1) kasbon dulu vs PO dicatat setelah belanja dari nota;
  (2) plafon nilai PO Kontan; (3) wajib PR/approval atau approval ringan;
  (4) satu kas kecil atau per gudang/departemen; (5) akun COA Kas Kecil &
  Uang Muka Pembelian sudah ada atau perlu dibuat. Kas Kecil = layar Finance
  yang dipakai bersama, bukan cuma Purchasing.

---

---
