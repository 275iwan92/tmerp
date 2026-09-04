---
name: screen_finance_coa
description: Master Chart of Accounts (COA) berbentuk grid tree berjenjang dengan modal tambah/edit akun.
---

# Master Chart of Accounts (COA)

**File**: `resources/views/pages/finance/coa.blade.php`
**Route**: `finance.coa` (`/finance/coa`)
**Koneksi DB utama**: acc (tm2acc)
**Modul**: Finance & Accounting

## Tujuan
Layar Master untuk mengelola struktur akun GL (Chart of Accounts) berjenjang (header/kelompok vs akun postable). Grid dibangun dari VIEW `v1_fin_account_tree` (lewat model `FinAccountTree`) yang sudah menghitung `tree_level` dan `default_cc_code` di level database. Akun header (`is_postable=false`) tidak bisa diedit lewat form — hanya untuk pengelompokan visual. Aktor: staf Finance/Accounting yang mengelola struktur akun.

## Field & Kolom

### Grid
- Search: `search` (live debounce 300ms) — cari `account_code`/`account_name`
- Toggle `showDetail` — ciutkan/tampilkan baris level-3 (akun postable), header (level 1-2) tetap tampil
- Kolom: Kode (dengan indentasi sesuai `tree_level`), Nama Akun (+ badge NONAKTIF), Tipe, Saldo (DEBIT/KREDIT), Recon & Tagging (badge RECON AR/AP atau kode skema pajak), Default Cost Center (badge kode CC), Aksi
- Stat box: Total Akun Aktif, Akun Rekonsiliasi (AR/AP) — bertanda TERKUNCI, Akun HPP Produksi (COGS, postable), Persediaan & Tooling (akun kode `1131%`-`1135%`)

### Modal Tambah/Edit Akun
- `parent_id` — select parent akun (opsional, root jika kosong); tidak boleh parent dari diri sendiri saat edit
- `account_code` — wajib, unik (`unique:tm2acc.fin_account,account_code`), max 20
- `account_name` — wajib, max 150
- `account_type` — wajib, salah satu dari `accountTypes` (ASSET/LIABILITY/EQUITY/REVENUE/COGS/EXPENSE/OTHER_INCOME/OTHER_EXPENSE)
- `normal_balance` — DEBIT/CREDIT, wajib
- `is_recon` — checkbox: akun rekonsiliasi (wajib partner di tiap baris jurnal — AR/AP)
- `is_postable` — checkbox: bisa dijurnal langsung (nonaktifkan untuk akun header/kelompok)
- `default_cc_id` — select dari `FinCostCenter::where('activate',true)`, opsional
- `tax_scheme_code` — input teks bebas max 20 (kode referensi skema pajak, mis. PPN_OUT — nilai % diambil dari `tm2mst.fin_tax_scheme` saat posting voucher, bukan disimpan di sini)
- `coa_activate` — checkbox Aktif (map ke kolom `activate`)

## Aksi / Method
- `toggleDetail()` — ciutkan/tampilkan baris postable
- `toggleActive(int $accountId)` — toggle kolom `activate` pada `FinAccount`
- `openModal(string $mode, ?int $id)` — mode `new`/`edit`; kalau edit akun header (`is_postable=false`), modal TIDAK dibuka (guard di awal method, `financeaccounting_tier4.md §3`)
- `closeModal()`
- `nextAccountId()` — private, `account_id` MANUAL (bukan AUTO_INCREMENT): `max(account_id)+1`
- `save()` — validasi lengkap (lihat di atas), tolak kalau `parent_id === editingId` saat edit (cegah self-parent), insert/update `FinAccount`
- `accountTypeLabel()`, `reconBadge()` — helper label & badge tampilan

## Tabel & Model yang Disentuh
- `App\Models\Acc\FinAccount` — tabel `fin_account` (koneksi `tm2acc`, PK `account_id` manual)
- `App\Models\Acc\FinAccountTree` — VIEW `v1_fin_account_tree` (koneksi `tm2acc`), sumber grid (tree_level + default_cc_code dihitung DB)
- `App\Models\Acc\FinCostCenter` — tabel `fin_cost_center` (koneksi `tm2acc`), untuk pilihan Default Cost Center
- Referensi tekstual (bukan FK): `tm2mst.fin_tax_scheme` untuk rate PPN/PPh berdasarkan `tax_scheme_code` — dibaca modul lain saat posting voucher, tidak di-query di layar ini.

## Alur / Business Logic Penting
- **VIEW-driven tree**: perhitungan `tree_level` (kedalaman hierarki) dan `default_cc_code` didorong ke level database lewat VIEW, bukan dihitung rekursif di PHP — sesuai pola performa-tinggi CLAUDE.md §2 pola 2 (VIEW read-only), meski di sini VIEW-nya dalam-DB (tm2acc saja), bukan lintas-DB.
- **account_id manual**: PK bukan auto-increment, di-generate app-layer via `nextAccountId()` — berbeda dari kebanyakan tabel lain di proyek.
- **Header akun terkunci dari edit**: akun `is_postable=false` (baris kelompok/header) sengaja tidak bisa dibuka form edit — desain agar struktur pengelompokan tidak berubah sembarangan lewat form akun biasa.
- **is_recon**: menandai akun yang wajib diisi partner (AR/AP) di tiap baris jurnal — dipakai layar lain (mis. Cash & Bank, DP Invoice) saat memilih akun AR/AP customer.

## Catatan
- Tidak ada validasi eksplisit bahwa `default_cc_id` cocok dengan `account_type` — bebas dipilih cost center manapun yang aktif.
- Rate pajak (%) memang sengaja tidak disimpan di tabel ini — komentar Blade eksplisit menjelaskan `tax_scheme_code` cuma kode referensi.
