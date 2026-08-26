# FIN · TIER 4 — Screen Spec + Binding
**Layar: Master Chart of Accounts.** Mockup: `master_coa_mockup.html`. Layout: `layouts.shell`.

## 1. Layout
- **FULL-PAGE** (`w-full` + padding, TANPA `container`). Grid operasional.
- Sidebar navy `#2c3e69`, item aktif border-left accent `#2f6fed`. Menu Finance:
  Master COA · Cost Center · Voucher/Jurnal · AR/AP · Trial Balance.
- Struktur: page-head (judul + tombol) → metrics (4 kartu) → card[toolbar + tabel `.qtbl`].

## 2. Sumber data
Grid: **`v1_fin_account_tree`** (bukan tabel mentah — sudah ada `tree_level` & `default_cc_code`).
Server-side pagination + search (WHERE), index `idx_acc_code`/`idx_acc_type` wajib dipakai.

## 3. Binding field → kolom
| Kolom grid | Sumber | Catatan |
|---|---|---|
| Kode | `account_code` | indent per `tree_level` (1/2/3) |
| Nama Akun | `account_name` | lvl-1 uppercase bold |
| Tipe | `account_type` | filter dropdown |
| Saldo | `normal_balance` | badge DEBIT(biru)/KREDIT(kuning) |
| Recon & Tagging | `is_recon` / `tax_scheme_code` | recon→badge navy; tax→badge abu; header→badge abu |
| Default Cost Center | `default_cc_code` | badge hijau; header `-` |
| Aksi | — | Edit (bukan untuk `is_postable=0`) |

## 4. Form Tambah/Edit Akun (binding)
| Field | Kolom | Aturan |
|---|---|---|
| Parent | `parent_id` | selector pohon; kosong = root |
| Kode | `account_code` | unik, required |
| Nama | `account_name` | required |
| Tipe | `account_type` | enum 8 nilai |
| Saldo normal | `normal_balance` | DEBIT/CREDIT |
| Akun recon | `is_recon` | toggle |
| Bisa dijurnal | `is_postable` | toggle (header=0) |
| Default CC | `default_cc_id` | selector `fin_cost_center` aktif |
| Skema pajak | `tax_scheme_code` | selector `tm2mst.fin_tax_scheme` (opsional) |
| Aktif | `is_active` | default 1 |

## 5. Metrics (dummy → query nyata)
Total akun aktif = `COUNT is_active=1`; Recon = `COUNT is_recon=1`; COGS = `COUNT type=COGS & is_postable=1`;
Persediaan = `COUNT code LIKE '1131%'..'1135%'`.

## 6. Warna & badge (delta dari mockup asal — SUDAH diselaraskan)
teal→navy `--navy #2c3e69`; brand-blue→accent `--accent #2f6fed`. Badge: b-debit(biru muda),
b-credit(kuning muda), b-recon(navy), b-tax(abu), b-cc(hijau), b-header(abu).

## 7. Production wiring checklist (untuk Claude Code)
- [ ] Model `FinAccount` (connection `tm2acc`), `$table='fin_account'`, self `parent()/children()`.
- [ ] Livewire `Finance/CoaIndex` — `WithPagination`, search server-side, sumber `v1_fin_account_tree`.
- [ ] Blade `resources/views/livewire/finance/coa-index.blade.php` — replikasi mockup, token navy.
- [ ] Form `Finance/CoaForm` — validasi unik code, guard header tak-postable.
- [ ] Menu `sys_app_menu`: node `FIN` + leaf `FIN_COA` (route `finance.coa`).
- [ ] Seeder jalankan `TIER_3A_FINANCE.sql` (journal, cost_center, COA).
- [ ] `data-mapping` comment header di tiap Blade (field→kolom sesuai §3).
