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
  **Yang belum:** layar **Stock In v2** (menu INV_IN / INV_RM_ITEM1 masih NOT_BUILT) untuk melihat dokumen Stock In
  hasil SPB / pembalik Cancel SPB (`OUT/..` ref PV_CANCEL) maupun input manual.
- **Purchase Return v2 — dibahas di sesi lain (user 2026-10-01, "agar lebih detail").** Preview desain sudah pernah
  ditunjukkan (Draft -> Confirm -> approval PRT -> Kirim Barang: Stock Out + Dr GR/IR / Cr Persediaan -> Closed saat CN
  diterima / barang pengganti masuk). Retur v1 sekarang belum mengeluarkan stok, belum menjurnal, belum update PO.
- **Purchase Invoice / hutang supplier** belum ada (backlog07 §12.3) -> GR/IR (211900) belum pernah dilunasi.
- **Konversi roll -> rim saat terima**: rumus berat 1 rim = lebar roll cm x panjang potong cm x GSM / 20.000;
  rim = berat bersih roll / berat 1 rim. Butuh keputusan user: satuan beli kg/roll, kapan panjang potong ditentukan, % waste.

### Sisa sesi 2026-10-01 (Purchase) — menunggu user
1. **Review di mockup.test** (menu v2: **R kuning** = belum direview, **A hijau** = approved/LOCKED oleh user):
   - Purchase Quotation (upload file penawaran -> cek -> Confirm -> Bandingkan -> Buat PO).
   - Penerimaan Barang: Cancel SPB; + New Record jenis **From Customer** (pilih CO) dan **From Subcon** (dummy
     PO Subkon PO2600008 CENTRAL GRAFIK, 2 baris laminasi) + Report Shortage setelah Confirm.
   - PO Kontan (+ New Record > Jenis PO = Cash PO) -> approval login `dewi` (Direktur F&A) -> Finance > Kas & Bank >
     Kas Kecil: Give Advance / Settlement / Reimburse / Top Up / Setting.
2. **OK menjalankan migration ke tm2*** (sudah jalan di ddt*): `2026_10_01_000002_pq_upload_extract_and_po_line_link`,
   `2026_10_01_000020_pv_receipt_cancel_columns`, `2026_10_01_000030_po_cash_petty_cash` (COA 111400, jurnal PTY,
   flow POC, konstanta PO_CASH_MAX, kas kecil KK-01 Mia), `2026_10_01_000040_pv_receipt_customer_subcon`.
3. **Kas Kecil**: plafon (imprest) belum diisi — isi di Kas Kecil > Setting; saldo awal masuk lewat Top Up.
4. **PQ**: `ANTHROPIC_API_KEY` belum diisi (PDF/foto penawaran belum terbaca otomatis; keputusan user: key & izin data
   penawaran dikirim ke Anthropic); .eml/.msg belum didukung; CSV angka Indonesia ("12.500") bisa terbaca 12,5.
5. **Belum dibangun (kecil, menyusul bila perlu)**: pembatalan transaksi kas kecil (kasbon/settlement salah input);
   cetak Tanda Terima Bahan Customer & cetak SPB; daftar bahan customer yang sudah datang di layar CO; data uji PR baru
   (semua baris PR APPROVED di ddt* sudah FULLY_ORDERED -> buat PR baru + approve untuk mencoba PR -> PQ -> PO).

---
