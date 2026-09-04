---
name: screen_finance_cash_bank
description: Layar entri & posting Jurnal Kas & Bank (BKM/BKK/BBM/BBK) dengan alur Draft -> Post.
---

# Jurnal Kas & Bank (BKM/BKK/BBM/BBK)

**File**: `resources/views/pages/finance/cash-bank.blade.php`
**Route**: `finance.cash-bank` (`/finance/cash-bank`)
**Koneksi DB utama**: acc (tm2acc)
**Modul**: Finance & Accounting

## Tujuan
Layar entri manual 4 jenis bukti kas/bank: Bukti Kas Masuk (BKM), Bukti Kas Keluar (BKK), Bukti Bank Masuk (BBM), Bukti Bank Keluar (BBK). Berbeda dari DP Invoice (yang langsung POSTED via service), layar ini benar-benar memanfaatkan status `DRAFT` pada `fin_voucher` — user bisa simpan draft dulu, edit berkali-kali, baru posting manual saat sudah yakin (jurnal berimbang debit=kredit). Setelah POSTED, dokumen terkunci (read-only), tidak bisa diedit lagi.

Pola tampilan: 1 route, panel switching antara grid (daftar voucher) dan form (header + 3 tab: Rincian Jurnal, Informasi Perpajakan, Audit Trail) — bukan modal, karena kontennya terlalu banyak (mengikuti pola Stock In/Out, bukan pola modal DP Invoice/COA).

Aktor: staf Finance/Kasir (input & posting jurnal kas/bank).

## Field & Kolom

### Header form
- `doc_no` — No. Dokumen (readonly, otomatis via `nextVoucherNumber()` saat simpan pertama, format `TIPE/YYYY/MM/NNN`)
- `voucher_type` — select: BKM/BKK/BBM/BBK (wire:model.live, memicu `seedFirstLine()`)
- `entry_date` — date picker, tanggal pembukuan
- `main_account_id` — select Akun Kas/Bank Utama, sumber: `FinAccount::where('account_code','like','111%')->where('is_postable',true)` (computed `cashBankAccounts`)
- `partner_name` — input teks bebas (mitra/penerima)
- `reference_no` — input teks bebas (No. Cek/Giro)
- `status` — DRAFT/POSTED/CANCELLED (readonly, badge)

### Tab Rincian Jurnal (`lines[]`, array baris)
- `lines.*.account_id` — select dari `postableAccounts` (semua `FinAccount::is_postable=true`)
- `lines.*.desc` — keterangan baris
- `lines.*.debit`, `lines.*.credit` — angka (number, step 0.01), wire:model.live untuk update total real-time
- Baris pertama auto-seed dari `main_account_id` + arah normal (BKM/BBM = uang masuk → Debit; BKK/BBK = uang keluar → Kredit), tetap bisa diedit bebas

### Tab Informasi Perpajakan
- `ppn_type` — select: NONE/MASUKAN_11/KELUARAN_11
- `efaktur_no` — No. Faktur Pajak (e-Faktur)
- `pph_type` — select: NONE/PPH23_2/PPH4_2_10/PPH21
- `bupot_no` — No. Bukti Potong PPh

### Tab Audit Trail (readonly)
- Dibuat Oleh (`created_by`), Diposting Oleh (`posted_by`), Tgl. Posting (`posted_at`)

### Grid (panel `grid`)
- Search: `search` (live debounce 300ms) — cari `voucher_number`/`partner_name`/`voucher_type`
- Kolom: Mitra/No. Bukti (identity-cell), Tanggal, Tipe (badge warna per tipe), Akun Kas/Bank, Total (IDR, sum debit baris), Status (badge), Aksi
- Stat box: Total Voucher, Draft, Sudah Posting
- Limit query grid: 200 baris terbaru (`orderByDesc('voucher_id')->limit(200)`)

## Aksi / Method
- `openNew()` — reset form, set default (voucher_type=BKM, entry_date=hari ini, status=DRAFT), seed baris pertama, pindah ke panel form
- `updatedVoucherType()` / `updatedMainAccountId()` — re-seed baris pertama otomatis saat tipe/akun utama berubah
- `addLine()` / `removeLine(int $index)` — tambah/hapus baris jurnal manual
- `openEdit(int $id)` — load voucher + lines existing, `readOnly` jadi true bila `status !== 'DRAFT'`
- `backToGrid()` — kembali ke panel grid
- `saveDraft()` — validasi (akun utama wajib, minimal 1 baris valid), simpan/replace `fin_voucher` + `fin_voucher_line` (baris lama dihapus lalu diganti) dalam transaksi `tm2acc`. Menolak simpan kalau voucher sudah bukan DRAFT (`abort_if`)
- `postDoc(?int $docId, JournalPostingService $svc)` — panggil `JournalPostingService::postDraft()` untuk mem-posting voucher; menangkap exception dan tampilkan sebagai `errorMessage` (di form) atau flash error (di grid)
- `cancelDoc()` — set `status=CANCELLED`, `stat_cancel=true`, hanya berlaku untuk voucher masih DRAFT
- `nextVoucherNumber(string $type)` — private, generate nomor dokumen dengan `lockForUpdate()` mencegah race condition
- `typeBadgeClass()`, `statusBadgeClass()` — helper kelas CSS badge

## Tabel & Model yang Disentuh
- `App\Models\Acc\FinVoucher` — tabel `fin_voucher` (koneksi `tm2acc`, PK `voucher_id`)
- `App\Models\Acc\FinVoucherLine` — tabel `fin_voucher_line` (koneksi `tm2acc`)
- `App\Models\Acc\FinAccount` — tabel `fin_account` (koneksi `tm2acc`), untuk pilihan akun kas/bank dan akun postable
- Query mentah `DB::connection('tm2acc')->table('fin_journal')` untuk mapping `voucher_type` → `journal_id` via `journal_code` (BKM/BKK→CSH, BBM/BBK→BNK)
- Tidak ada join lintas-DB — semua tabel di `tm2acc`.

## Alur / Business Logic Penting
- **Draft-first**: berbeda dari DP Invoice (langsung POSTED), di sini `fin_voucher.status` benar-benar dipakai sebagai state DRAFT sebelum posting manual.
- **Posting manual**: `saveDraft()` HANYA menulis/update `fin_voucher`+`fin_voucher_line` (tanpa efek GL lain). Posting sesungguhnya (validasi balance dsb.) dilakukan terpisah lewat `postDoc()` → `JournalPostingService::postDraft()`.
- **Auto-seed baris pertama**: arah debit/kredit ditentukan dari jenis dokumen (uang masuk vs keluar) agar user tidak perlu mikir arah jurnal untuk baris utama.
- **Nomor dokumen anti-race**: `nextVoucherNumber()` pakai `lockForUpdate()` saat mengambil nomor urut terakhir per prefix bulan berjalan.
- Total Debit/Kredit/Selisih dihitung live (computed `totalDebit`/`totalCredit`) dan ditampilkan agar user bisa cek balance sebelum posting.

## Catatan
- "Smart button" e-Faktur/Rekonsiliasi Bank di mockup HTML asal **sengaja tidak dibangun** — komentar kode eksplisit menyebut itu placeholder statis tanpa logika di baliknya; tidak ada validasi e-Faktur atau rekonsiliasi bank yang diimplementasikan di layar ini.
- Field pajak (`ppn_type`, `efaktur_no`, `pph_type`, `bupot_no`) disimpan tapi tidak divalidasi silang ke nilai voucher/jurnal (tidak ada hitungan otomatis PPN/PPh berbasis field ini di komponen).
