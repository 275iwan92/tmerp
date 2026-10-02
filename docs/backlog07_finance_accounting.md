# BACKLOG 07 — FINANCE & ACCOUNTING (AR / AP)

> Pecahan dari `BACKLOG.md` (2026-09-22, permintaan user). Indeks semua
> backlog: **[`BACKLOG.md`](BACKLOG.md)**. Item yang sudah selesai dicatat
> di **[`LOG_JOB_DONE.md`](LOG_JOB_DONE.md)**, bukan di sini.

General Ledger, Account Receivable, Account Payable, kas & bank, pajak, dan Accounting Process Engine.

---


## 7. FINANCE & ACCOUNTING (tm2acc)

- **Customer Invoice + Accounts Receivable (satu modul)** — arah dan keputusan A–E disetujui user 2026-10-02.
  Rencana: `md_for_systems/customerinvoice_plan.md`; ketentuan keuangan dan akuntansi:
  `md_for_systems/customerinvoice_accounting.md`. **Menagih, uang muka, dan Record Payment terpasang 2026-10-02 di
  ddt dan tm2; layar lama Order to Cash + Down Payment diarsipkan atas perintah user**
  (`md_for_systems/customerinvoice_review.md`, `LOG_JOB_DONE.md`). Yang terbuka:
  - **Review user** (butir di `customerinvoice_review.md` bagian 10) + koreksi format cetak.
  - **Sudah ada sejak 2026-10-02 (ddt + tm2):** bukti terima per baris Surat Jalan dengan alasan selisih, tab Delivery
    di invoice, buku uang muka per order, tab Billing di Customer Order, To Invoice, AR Aging, Down Payment Ledger,
    satu akun bank per rekening (111210–111250).
  - **Sisa Tahap 2** piutang: PPh 23 / biaya bank / selisih / kelebihan bayar di penerimaan, Statement of Account,
    Invoice Register per seri, Faktur Pajak belum bernomor.
  - **Menunggu wawancara pemakai** (user 2026-10-02 belum bisa memastikan): retur sesudah invoice (Credit Note /
    kirim ganti / kembalikan uang), nasib barang yang tidak diterima customer, bentuk validasi customer, uang muka
    tanpa order (deposit), PPh 23.
  - **Tahap 3**: Credit Note, pengembalian uang muka, batas kredit, e-Faktur, saldo awal piutang.
  - Sisa kecil Tahap 1: hak per aksi, akun pendapatan per kategori product, biaya lain dari biaya eksternal CO,
    pesan salah Confirm berbahasa Indonesia, e-mail invoice, akun PPh 23 Dibayar Dimuka.
  - Sebelum dipakai di tmerp.test: konfirmasi konsultan pajak (PPN uang muka).

- **Auto-jurnal Delivery/Invoice** — PV & Stock Out(RM) sudah auto-posting
  (`JournalPostingService`), tapi Delivery(DO)/Invoice sengaja ditunda
  karena butuh mekanisme snapshot Costing V2 di titik shipment (Keputusan B
  `FIN_TIER_1.md`: COGS diakui saat DO, bukan saat Stock-Out RM). **DO
  sekarang sudah ada layarnya** (`dispatch/do/entry.blade.php`, live sejak
  2026-08-28) — prasyarat "DO belum ada" di catatan lama SUDAH TERPENUHI,
  tapi status mekanisme snapshot Costing V2 itu sendiri **belum
  dikonfirmasi ada atau tidak** — cek dulu sebelum mulai.
- **Payroll → jurnal otomatis** — 11 layar Payroll sudah live (backlog06
  §15), tapi belum ada hook `JournalPostingService` saat run CLOSED (0 kode
  jurnal payroll ditemukan, dicek 2026-09-30). Sisa lain: UI pengajuan/
  approval `emp_overtime_request` (model ada, layar belum), dan Open Item
  Tier 2 yang belum dikonfirmasi bisnis (KJK dijumlah vs window terpanjang,
  PAT-5/PAT+5 prorata vs zero-total, aturan tidak-finger ke-3+).

---

---

## DARI AUDIT MENYELURUH `docs/*.md` (agent riset 2026-09-02)

Konteks lengkap audit ini ada di `BACKLOG.md`.

### 12.3 Procurement/Finance

- Dicek 2026-10-02: Fixed Assets, Amortization, Order to Cash, Procure to Pay, Down Payment, Journal, General Ledger, Trial Balance,
  dan laporan keuangan SUDAH ada (v1 + grid v2). Cetakan Sales Invoice dan Payment Voucher ada (belum A). Master Kategori Produk + akun
  bawaannya sudah punya layar (Master Data > Product Category). Belum ada: e-Faktur, Statement of Account, Kwitansi, tagihan DP supplier.
- **Grup B (Partner Account local/import, §3.1-3.2) — TERNYATA BUKAN "boleh
  jalan sekarang" lagi, dicabut dari kategori itu.** Verifikasi §4 (2026-09-11,
  sesuai instruksi dokumen sendiri "jangan ALTER buta") menemukan akun `211`/
  `212` di `fin_account` **BUKAN** "Hutang Dagang Lokal/Impor" seperti
  diasumsikan dokumen — kenyataannya segmentasi AP di COA aktual per
  **kategori supplier/komoditas** (Kertas & Board/Tinta & Chemical/dst),
  bentrok konsep dgn model "4 kombinasi CUSTOMER/SUPPLIER × local/import" di
  §3. Kemungkinan sudah digantikan mekanisme `mat_category.default_coa_account_id`
  yang sudah ada (§4 Procurement di atas). **Butuh rekonsiliasi desain dgn
  user sebelum lanjut** — apakah AP tetap mau disegmentasi local/import
  (perlu akun COA baru yang belum ada) atau `fin_partner_account` cukup
  dipetakan dari `mat_category` yang sudah jalan. §3.3 (Observer) tetap
  diblokir seperti semula, TIDAK disentuh.
- Blanket PO/kontrak jangka panjang, Supplier performance rating — "belum
  dibutuhkan"/"menyusul", tidak ada urgensi tercatat.

---
