# financeaccounting_delta_process_engine.md

**Modul 06 Finance & Accounting — realisasi mockup blueprint "LedgerOne".**
Sumber: `docs/mockups/MODULE_accounting-process-engine-blueprint.html` +
`docs/mockups/MODULE_accounting-mockup-blueprint.html` (diberikan user 2026-09-19,
instruksi: "saya minta sesuai mockup yang sudah saya berikan. Buat dulu, lalu buat
dummy data nya akan saya check hasilnya").

Status: **DIBANGUN 2026-09-19/21, menunggu QC user.** Delta ini melengkapi
`financeaccounting_tier1/2/4.md` (tidak membatalkannya) dan **mengeksekusi**
`financeaccounting_delta_period_close.md` yang sebelumnya masih spec.

---

## 1. Layar (16 baru, semua route `module:FINACC`)

| Menu (sys_app_menu) | Route | Isi |
|---|---|---|
| Control Center > Process Engine | `finance.engine` | kartu volume dokumen/approval/close, alur posting kanonik, peta integrasi modul, **Workflow Rules** (fin_approval_rule) |
| Control Center > Dashboard | `finance.dashboard` | kas/AR/AP/laba, tren arus kas 12 bulan, Compliance Center, Action Center |
| Transaction Flows > Order to Cash | `finance.receivables` | faktur penjualan (buat/posting/terima/alokasi), AR aging, alur O2C |
| Transaction Flows > Procure to Pay | `finance.payables` | tagihan vendor (3-way match PV), approval by nominal, payment run, PPN masukan & PPh 23 |
| Transaction Flows > Bank & Reconciliation | `finance.bank` | mutasi buku vs rekening koran, import CSV, auto-match, jurnal penyesuaian, rekonsiliasi |
| Transaction Flows > Cashier / Down Payment | `finance.cash-bank`, `finance.dp-invoice` | layar lama, tetap dipakai (hanya relabel menu) |
| Accounting > Master of Accounts | `finance.coa` | layar lama (tidak diubah, lihat §6) |
| Accounting > Journal Entries | `finance.journals` | Draft → Submitted → Approved → Posted → Reversed, template recurring, auto-reversal akrual |
| Accounting > General Ledger | `finance.ledger` | register baris jurnal POSTED per periode + saldo berjalan per akun |
| Accounting > Fixed Assets | `finance.assets` | register aset, kapitalisasi, jadwal, run penyusutan, pelepasan |
| Accounting > Amortization | `finance.amortization` | prabayar / tak berwujud / leasehold, preview & run amortisasi |
| Controls & Tax > Period Closing | `finance.closing` | checklist 6 kontrol + kontrol otomatis, Period Manager 13 periode, voucher penutup tahun |
| Controls & Tax > Indonesian Tax | `finance.tax` | register PPN/PPh 21/23/Badan, kalender kepatuhan, rekonsiliasi buku-pajak, setor (NTPN) & lapor |
| Financial Reports | `finance.statements`, `.balance-sheet`, `.income-statement`, `.cash-flow`, `.trial-balance` | semua dari GL POSTED |

UI: CSS mockup diport ke `resources/css/ledger.css` (kelas `lg-*`, diimpor dari
`app.css`) + komponen `resources/views/components/ledger/*` (terdaftar di
`_manifest.yaml`). Sidebar/topbar mockup TIDAK ditiru — navigasi tetap shell
TMERP (`sys_app_menu`), sesuai NAV-SHELL-CONTRACT.

## 2. Skema (migration `2026_09_19_000001`)

- `fin_period`, `fin_period_log` — persis `financeaccounting_delta_period_close.md` §2-3.
- `fin_close_task` — checklist tutup buku per periode.
- `fin_voucher`: status ENUM jadi `DRAFT/SUBMITTED/APPROVED/POSTED/CANCELLED`;
  kolom baru `journal_kind`, `submitted_by/at`, `approved_by/at`,
  `reversal_of_id`, `reversed_by_id`, `auto_reverse_date`.
- `fin_account`: `fs_line` (baris Neraca/Laba Rugi) + `cf_line` (baris Arus Kas)
  — laporan disusun dari pemetaan ini, bukan hardcode kode akun di kode.
- `fin_invoice`: `doc_status`, `description`, `partner_ref`, `tax_invoice_no`,
  `tax_validated`, `pph_amount`, `pph_code`, `revenue_account_id`.
- Tabel baru: `fin_payment_alloc`, `fin_approval_rule`, `fin_bank_statement`,
  `fin_bank_recon`, `fin_asset_category`, `fin_asset`, `fin_asset_depr`,
  `fin_tax_register`, dan `tm2mst.fin_tax_scheme` (dependency lama yang belum pernah dibuat).
- Akun baru: 115100 prabayar · 121300/121400/121500 kendaraan/peralatan/bangunan ·
  121910/121920/121930 akumulasi · 130000/131100/131200/131900 aset tak berwujud ·
  214100 PPh 21 · 214200 PPh Badan · 215100 hutang bank · 617000/618000/619000 beban ·
  900000/911000 beban pajak penghasilan.

## 3. Engine (app/Services/Acc)

| Service | Tugas |
|---|---|
| `FinPeriodGuard` | `assertOpen()` dipanggil di SETIAP titik tulis voucher (JournalPostingService, DP Invoice, Kasir); `closeBlockers()` = gate `tm2inv.inv_period` + voucher belum posted + checklist; CLOSED ireversibel |
| `JournalPostingService` | `post()` (otomatis), `saveDraft/submit/approve/reject/postDraft` (manual), `reverse()`, `runAutoReversals()`, aturan approval dari `fin_approval_rule` (termasuk pemisahan tugas) |
| `ArApService` | faktur penjualan, tagihan vendor, `settle()` penerimaan/pembayaran + alokasi (tak boleh melebihi sisa), tarif dari `fin_tax_scheme` |
| `AssetService` | kapitalisasi, `plan()` garis lurus & saldo menurun (PMK 72/2023), run bulanan 1 voucher, pelepasan |
| `BankReconService` | import CSV (dd/mm/yyyy & 1.234.567,50 dikenali), auto-match, jurnal penyesuaian DRAFT, rekonsiliasi |
| `TaxEngine` | register masa dari mutasi GL + payroll `tm2pyr`, setor (NTPN) & lapor, provisi PPh Badan, rekonsiliasi buku-pajak |
| `CloseService` | checklist, kontrol otomatis, lock, voucher penutup tahun (DRAFT, wajib review manual) |
| `LedgerService` | satu-satunya sumber angka laporan: TB, Neraca, Laba Rugi, Arus Kas tidak langsung |
| `FinanceMetrics` | kartu & panel Dashboard/Process Engine |

**Keputusan baru yang perlu dicatat:**
1. Koreksi voucher POSTED = **jurnal pembalik** (`reversal_of_id`), bukan
   copy-record REVISE seperti draf `financeaccounting_tier2.md` §B5. REVISE
   copy-record tidak dibangun.
2. Akun `is_recon` (piutang/hutang/uang muka) **ditolak** di layar Journal
   Entries — harus lewat Order to Cash / Procure to Pay supaya subledger sinkron.
3. PPN efektif 12% × DPP 11/12 (PMK 131/2024); jatuh tempo bayar tgl 15, lapor
   PPh tgl 20, lapor PPN akhir bulan berikutnya (PMK 81/2024). Semuanya kolom
   tabel, bukan konstanta kode.
4. `inv_period` status `CLOSING` dianggap **belum** CLOSED untuk gate GL
   (menutup open item FIN-PERIOD-STATUS-VOCAB).
5. Prasyarat §5 delta period-close (menyambungkan `PeriodService::assertOpen()`
   di Inventory) **BELUM** dikerjakan — `StockPostingService` masih memakai
   pengecekan `period_id` inline miliknya sendiri. Gate GL→Inventory sudah
   jalan dari sisi Finance, tapi penegakan periode di Inventory belum satu jalur.

## 4. Dummy data

`php artisan fin:dummy` (dan `--purge` untuk menghapus + membuka kembali periode
yang ditutup seeder). Semua baris `data_origin=DUMMY`, `created_by=dummy_fin`,
dibuat LEWAT service yang sama dengan layar. Isi: 12 aset + 3 jadwal amortisasi
(riwayat penyusutan sebelum 2026 tanpa voucher), saldo awal 31-12-2025, 36 faktur
penjualan + penerimaan, 27 tagihan vendor + pembayaran, HPP/payroll/overhead/
angsuran bank per bulan, capex, run penyusutan & amortisasi Jan–Agu, jurnal
draft/menunggu-approval/akrual-auto-reverse, 25 mutasi rekening koran (22 cocok
otomatis, 3 sisa), register pajak Jan–Sep (Jan–Jul PAID+FILED), periode Jan–Jul
CLOSED, checklist Agustus 4/6.

Hasil cek integritas (2026-09-21): trial balance nol selisih, Aset = Kewajiban +
Ekuitas, arus kas menjelaskan seluruh perubahan kas, pendapatan YTD Rp 8,91 M,
laba kotor 35,8%, laba bersih Rp 1,03 M, kas Rp 4,49 M, AR Rp 2,07 M
(4 faktur menunggak), AP Rp 1,00 M.

## 5. Test

`tests/Feature/AccountingEngineTest.php` (13 test, 47 assertion): guard periode,
jurnal tidak balance, jurnal pembalik, posted tak bisa diedit, PPN faktur +
jurnalnya, penerimaan parsial→lunas, alokasi melebihi sisa, jadwal SL & DDB,
neraca seimbang + arus kas terekonsiliasi, blocker close, CLOSED ireversibel,
faktur duplikat, render 16 layar.

## 6. Sengaja TIDAK dikerjakan (bukan lupa)

- **Master of Accounts tidak dirombak** ke gaya mockup blueprint (kolom "Current
  Balance" dll). Layar COA sekarang mengikuti mockup Tier 4 lama
  (`master_coa_mockup.html`) yang sudah LOCKED di `financeaccounting_tier4.md`.
  Perlu keputusan user kalau mau diganti.
- Faktur final dari Delivery Order (Event 3 `downpayment_tier1-4.md`) belum ada
  — Customer Invoice masih dibuat manual (boleh merujuk CO).
- Migrasi saldo AR/AP legacy (`co_invoice_legacy` 26 ribu baris,
  `po_invoice_legacy`) tidak dilakukan.
- e-Faktur/e-Bupot hanya **penampungan nomor**, tidak ada validasi/integrasi DJP.
- Multi-currency revaluation (akun USD 111300/112200) tetap fase berikutnya.
