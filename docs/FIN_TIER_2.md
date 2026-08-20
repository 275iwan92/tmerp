# FIN · TIER 2 — Business Logic
**Modul 06 Finance & Accounting.** Format: A happy-path / B special-case / C state-form-button.

---
## PART A — HAPPY PATH

### A1. Kelola COA (master)
1. Buka Master COA → grid pohon 3 level dari `v1_fin_account_tree`.
2. Tambah akun: pilih parent, isi code/name/type/normal_balance, set `is_recon`/`is_postable`,
   default cost center, tax_scheme_code (opsional). Simpan → `fin_account`.
3. Header (`is_postable=0`) tak muncul di selector jurnal.

### A2. Input voucher jurnal
1. Pilih journal (CSH/BNK/SAL/PUR/GEN), tanggal entry & posting, deskripsi, reference_no.
2. Tambah baris: pilih akun (hanya `is_postable=1`), isi debit **atau** kredit, cost center,
   narration. Jika akun `is_recon=1` → field partner **wajib**; untuk pendapatan → `co_id`.
3. Sistem cek: Σdebit = Σkredit. Status DRAFT tersimpan.
4. **Posting**: status → POSTED, `posted_by/at` terisi. Masuk trial balance.

### A3. Faktur penjualan (AR) — fase-1 manual
1. Buat `fin_invoice` type CUSTOMER_INVOICE, partner=customer, `co_id`, subtotal, tax_amount
   (dari rate `PPN_OUT`), total=subtotal+tax (snapshot), due_date.
2. Posting jurnal pengakuan: **D** Piutang (112x00, recon+partner) / **K** Pendapatan (41x, +co_id)
   & **K** PPN Keluaran (212100). `fin_invoice.voucher_id` terisi.

### A4. Tagihan supplier (AP) — fase-1 manual
Simetris A3: type SUPPLIER_BILL. **D** Persediaan/Beban + **D** PPN Masukan / **K** Hutang (211x00, recon+partner).

### A5. COGS saat kirim (perpetual)
Saat DO dikirim → jurnal: **D** HPP (51x, cost center) / **K** Persediaan Barang Jadi (113500).
Nilai = snapshot HPP Costing V2 per CO.

### A6. Laporan
Trial Balance (`v1_fin_trial_balance`), AR Aging (`v1_fin_ar_aging`), AP Aging (`v1_fin_ap_aging`).

---
## PART B — SPECIAL CASES

- **B1. Voucher tak balance** → tombol Posting disabled; banner selisih Σdebit−Σkredit.
- **B2. Akun recon tanpa partner** → validasi gagal, baris ditandai merah.
- **B3. Debit & kredit sama-sama terisi** → ditolak (CHECK DB + validasi UI).
- **B4. Cross-CO shipment**: satu DO → beberapa baris invoice/jurnal `co_id` berbeda, harga per CO
  berbeda. Penyelesaian & revenue dihitung **per CO** (bukan header). Contoh:
  CO-A1 sisa 2k (harga lama, menutup A1) + CO-A2 5k (harga baru) dalam 1 pengiriman 7k.
- **B5. REVISE voucher POSTED**: tak edit langsung → copy-record `revision_no+1`; lama
  `is_cancelled=1 cancel_reason='SUPERSEDED'`. (Alternatif akuntansi: jurnal pembalik — fase-2.)
- **B6. Pembayaran parsial**: `paid_amount` naik, `payment_status` PARTIAL→PAID; aging pakai outstanding.
- **B7. Rate pajak berubah**: hanya transaksi baru; yang lama tetap snapshot (tak retroaktif).
- **B8. Ekspor USD**: akun 111300/122200 mata uang USD; selisih kurs → 811000 (fase-2 revaluation).

---
## PART C — STATE · FORM · BUTTON

### C1. Voucher state
`DRAFT → POSTED → (CANCELLED)`
| State | Bisa edit? | Aksi tersedia |
|---|---|---|
| DRAFT | ya | Edit, Tambah/Hapus baris, **Posting**, Hapus (hard) |
| POSTED | tidak | Detail, **REVISE** (copy), Cancel (→CANCELLED) |
| CANCELLED | tidak | Detail |

### C2. Tombol per konteks
- Grid COA: **+Tambah Akun**, Edit (baris terpilih), Import, Export. Header row: tanpa Edit.
- Voucher form: **Simpan Draft**, **Posting** (aktif hanya bila balance), **Batal**.
- Invoice: **Buat**, **Posting Jurnal**, **Catat Pembayaran**.

### C3. Sisi A / Sisi B
Finance & Direksi = Sisi A (HPP/margin visible). Sales = Sisi B (HPP redacted). COA & GL
umumnya internal → default Sisi A; laporan yang di-share ke Sales harus melewati publish.

### C4. Guard
Hard delete → DRAFT only. Akun header tak dipilih di jurnal. Akun `is_active=0` disembunyikan
dari selector tapi tetap muncul di laporan historis.
