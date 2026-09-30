# BACKLOG 07 — FINANCE & ACCOUNTING (AR / AP)

> Pecahan dari `BACKLOG.md` (2026-09-22, permintaan user). Indeks semua
> backlog: **[`BACKLOG.md`](BACKLOG.md)**. Item yang sudah selesai dicatat
> di **[`LOG_JOB_DONE.md`](LOG_JOB_DONE.md)**, bukan di sini.

General Ledger, Account Receivable, Account Payable, kas & bank, pajak, dan Accounting Process Engine.

---


## 7. FINANCE & ACCOUNTING (tm2acc)

- **Aset Tetap (Fixed Asset)** — modul **belum ada sama sekali** (dicek
  2026-09-02: cuma akun COA "ASET TETAP PABRIK"/"Akumulasi Penyusutan
  Mesin" di `fin_account`, bukan tabel register). Menu placeholder
  `ACC_FIXEDASSET` (NOT_BUILT) sudah ditaruh di grup ACC (migration
  `2026_09_02_000019`), permintaan user. Butuh desain dulu sebelum
  dibangun: skema register (kode aset, tgl/nilai perolehan, metode &
  umur penyusutan, lokasi/departemen pemegang, link ke akun aset vs
  akumulasi penyusutan di COA), siapa yang input, apakah auto-jurnal
  penyusutan bulanan (mirip `JournalPostingService` PV) atau manual.
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

### 12.3 Procurement/Finance — subsistem yang belum ada sama sekali

- **Purchase Invoice (SUPPLIER_DP/SUPPLIER_BILL)** — belum dibangun,
  butuh tabel delta `fin_invoice_line` yang belum ada.
- **Seluruh subsistem AR/AP Finance** (Invoice Entry+print, e-Faktur,
  Billing Status, Payment Record, AR/AP Aging, Statement of Account,
  Kwitansi, GL Trial Balance dashboard) — belum ada 1 layar pun,
  Journal Entry sendiri sudah live (Cash & Bank + auto-posting).
- **Grup A (Kategori Produk → akun COA)** — resolve chain sudah dibangun
  (`docs/LOG_JOB_DONE.md` 2026-09-11). **Sisa yang BELUM**: UI layar Master
  Kategori Produk CRUD (belum pernah ada, cuma dropdown read-only di
  `produk.blade.php`) — kolom akun tetap NULL sampai ada cara isi.
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
