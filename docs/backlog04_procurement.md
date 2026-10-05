# BACKLOG 04 — PROCUREMENT: PR S/D BARANG MASUK

> Pecahan dari `BACKLOG.md` (2026-09-22, permintaan user). Indeks semua
> backlog: **[`BACKLOG.md`](BACKLOG.md)**. Item yang sudah selesai dicatat
> di **[`LOG_JOB_DONE.md`](LOG_JOB_DONE.md)**, bukan di sini.

Purchase Requisition -> Purchase Order -> Purchase Quotation -> penerimaan barang, sampai serah terima ke Inventory.

---


## 4. PROCUREMENT (tm2pur)

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
- **Alur Purchasing (user 2026-10-01): PR -> PQ (boleh dilewati) -> PO -> Purchase Receive (SPB) -> otomatis
  STOCK INPUT.** PR/PQ/PO/SPB v2 sudah tersambung; Post SPB otomatis membuat & mem-posting Stock In RM + jurnal.
  Grid **Stock In v2** SELESAI 2026-10-02 (LOG_JOB_DONE; pembalik Cancel SPB `PV_CANCEL` tampil di Stock Out RM).
- **Purchase Return v2 — dibahas di sesi lain (user 2026-10-01, "agar lebih detail").** Preview desain sudah pernah
  ditunjukkan (Draft -> Confirm -> approval PRT -> Kirim Barang: Stock Out + Dr GR/IR / Cr Persediaan -> Closed saat CN
  diterima / barang pengganti masuk). Retur v1 sekarang belum mengeluarkan stok, belum menjurnal, belum update PO.
- **Tagihan supplier** sudah ada di Finance > Procure to Pay (`fin_invoice` SUPPLIER_BILL, dicek 2026-10-02). Yang belum dicek: apakah posting
  tagihan itu melunasi GR/IR (211900) yang dibuat saat SPB -- perlu uji alur PO -> SPB -> tagihan -> bayar dengan Finance.
- **Konversi roll -> rim saat terima**: rumus berat 1 rim = lebar roll cm x panjang potong cm x GSM / 20.000;
  rim = berat bersih roll / berat 1 rim. Butuh keputusan user: satuan beli kg/roll, kapan panjang potong ditentukan, % waste.

### Sisa sesi 2026-10-01 (Purchase) — menunggu user
1. **Review di mockup.test** (menu v2: **R kuning** = belum direview, **A hijau** = approved/LOCKED oleh user):
   - Alur penuh pakai **PR2600009** (dummy, APPROVED, 3 baris: Ivory 300 / HVS 58 / Duplex 260): PQ (upload file
     penawaran -> Confirm -> Bandingkan -> Buat PO) -> PO -> SPB (Confirm -> Print SPB / Cancel).
   - Penerimaan jenis From Customer (pilih CO; Print Receipt; tab Customer Material di CO) dan From Subcon (dummy PO
     Subkon PO2600008 CENTRAL GRAFIK; Report Shortage; Print Receipt).
   - PO Kontan -> approval login `dewi` (Direktur F&A) -> Kas Kecil: Give Advance / Settlement / Reimburse / Top Up /
     Setting / Cancel Transaction.
2. **tm2***: semua migration Purchase sudah jalan, termasuk `2026_10_01_000035_flow_poc_cash_po` (dicek 2026-10-05:
   `migrate:status` tanpa Pending, `tm2mst.flow_config` berisi `POC`). Yang belum ada di tmerp.test hanya baris menu
   **Approval Levels** (`SET_APPROVAL`), jadi approver PO Kontan di sana belum bisa diatur lewat layar.
3. **Kas Kecil**: plafon (imprest) belum diisi — isi di Kas Kecil > Setting; saldo awal masuk lewat Top Up.
4. **PQ**: `ANTHROPIC_API_KEY` belum diisi (PDF/foto penawaran belum terbaca otomatis; keputusan user: key & izin data
   penawaran dikirim ke Anthropic); .eml/.msg belum didukung.

---
