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
- **Purchase Invoice / hutang supplier** belum ada (backlog07 §12.3) -> GR/IR (211900) belum pernah dilunasi.
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
2. **tm2***: 5 migration Purchase sudah dijalankan 2026-10-01 (000002, 000020, 000030, 000040, 000050, satu per satu
   dgn `--path`). **`2026_10_01_000035_flow_poc_cash_po` (flow approval PO Kontan) masih Pending di tm2*** — butuh
   kolom dari `2026_09_30_000009_flow_config_approval_setting` (milik sesi Approval, juga belum jalan di tm2*). Jalankan
   keduanya bersama saat migration sesi lain dijalankan ke tm2*; sebelum itu PO Kontan di tmerp.test belum punya approver.
3. **Kas Kecil**: plafon (imprest) belum diisi — isi di Kas Kecil > Setting; saldo awal masuk lewat Top Up.
4. **PQ**: `ANTHROPIC_API_KEY` belum diisi (PDF/foto penawaran belum terbaca otomatis; keputusan user: key & izin data
   penawaran dikirim ke Anthropic); .eml/.msg belum didukung; CSV angka Indonesia ("12.500") bisa terbaca 12,5.

---
