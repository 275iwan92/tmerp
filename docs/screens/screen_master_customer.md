---
name: screen_master_customer
description: Master Customer — grid+form bersatu (bukan tab lama), profil lengkap customer offset printing
---

# Master Customer

**File**: `resources/views/pages/master/customer.blade.php`
**Route**: `master.customer` (`/master/customer`)
**Koneksi DB utama**: mst (tm2mst)
**Modul**: Master Data

## Tujuan
Layar CRUD utama untuk `cust_customer` — data pelanggan CV Telasih Makmur. Redesign v2 (2026-08-19) dari versi tab identitas/alamat/kontak/accounting terpisah menjadi 1 layar bersatu grid <-> form (form merangkap tampil+edit, tanpa panel "detail" terpisah). Form terbagi 6 tab: Overview, Printing Specs (spesifikasi cetak-spesifik yang mempengaruhi quotation/proofing/produksi/QC), Sales & Finance, Sites & Contacts (gabungan alamat + kontak & cabang tambahan), Governance (KYC/pajak), History (timeline ringkas created/updated, bukan audit trail penuh). Aktor: Sales/Marketing (entry/edit data umum), Finance/Admin (blokir-buka blokir, kredit).

## Field & Kolom
**Tab Overview**: `customer_code` (required, unique), `customer_name` (required), `trade_name`, `parent_group`, `industry_category`, `tax_code` (NPWP), `customer_type` (local/import), `category` (FK `cust_category.category_code`), `activate` (checkbox), `is_prospect` (checkbox), `note`.

**Tab Printing Specs** (semua nullable, teks bebas): `primary_product`, `secondary_product`, `default_substrate`, `color_standard`, `proofing_method`, `artwork_approval`, `retained_sample`, `barcode_verification`, `finishing_preference`, `die_owner`, `packing_instruction`, `quality_notes`.

**Tab Sales & Finance**: `sales_division_id` (FK `org_division`), `price_list_code`, `tax_profile` (PPN_11/EXEMPT), `payment_term` (FK `fin_payment_term.payment_term_code`), `currency_code`, `credit_limit` (numeric), `credit_check_enabled` (checkbox), `credit_rating` (A/B/C via `CustCustomer::CREDIT_RATINGS`). Ada placeholder "Akun Rekonsiliasi Piutang" (Chart of Accounts) — **belum fungsional**, menunggu desain skema COA, field `disabled`.

**Tab Sites & Contacts**: `default_address`, `default_area_id`/`default_area_level`/`address_label` (dari komponen `<livewire:address-picker>`), `postal_code`, `mr_ms` (sapaan Bapak/Ibu), `contact_name` (PIC utama), `phone`, `email`, `website`. Plus list kontak tambahan (`cust_customer_contact`) & cabang tambahan (`cust_customer_branch`) — hanya tampil setelah customer tersimpan (`$editingId`).

**Tab Governance**: `tax_validated` (checkbox, NPWP diverifikasi Finance), `kyc_status` (PENDING/APPROVED/REJECTED via `CustCustomer::KYC_STATUSES`).

**Grid**: kolom Customer (avatar inisial + nama + kode + parent_group), Tipe (Lokal/Import + Prospek), Industri, Limit Kredit, Termin (dari relasi `paymentTerm`), Status (badge `activate` Aktif/Nonaktif + badge `approval_status` + ikon 🚫 kalau `is_blacklisted`), Aksi (Edit + Toggle-aktif). Search box (`q`, live debounce 400ms) cari di `customer_name`/`customer_code`/`contact_name`. Stat box: Total Customer, Aktif (approval_status=APPROVED & activate=true), Terblokir (is_blacklisted atau approval_status=BLOCKED), Draft/Onboarding.

## Aksi / Method
- `customers()` — computed, list dengan search + limit 200, eager-load `paymentTerm`
- `metricTotal/metricActive/metricBlocked/metricDraft()` — computed stat box
- `selectedCustomer()` — computed, dengan relasi `branches`+`contacts`
- `paymentTerms()`, `categories()`, `divisions()` — computed dropdown sources (filter `activate=true`)
- `avatarInitials()` — helper strip prefix PT/CV/UD/TBK sebelum ambil inisial
- `onAddressPicked()` — listener event `address-picked` dari `<livewire:address-picker>`, beda handling untuk field `customer-address` vs `customer-branch-address`
- `openBranchModal()/closeBranchModal()/saveBranch()/deleteBranch()/setDefaultBranch()` — CRUD `cust_customer_branch`, 1 default per customer (unset default lain saat set baru)
- `openContactModal()/closeContactModal()/saveContact()/deleteContact()/setDefaultContact()` — CRUD `cust_customer_contact`, pola sama (1 default)
- `selectRow()/editRow()/showGrid()/newCustomer()/editCustomer()` — navigasi grid<->form
- `setFormTab()/tabHasError()` — switch tab + indikator dot merah kalau tab punya error validasi
- `resetForm()` — reset semua field form ke default
- `setApprovalStatus()` — ubah `approval_status` langsung dari tombol pill di header form (tanpa modal)
- `save()` — validasi lengkap semua tab, `unique:tm2mst.cust_customer,customer_code`, kalau error auto-pindah ke tab pertama yang error
- `toggleActive()` — toggle `activate` dari grid
- `blockCustomer()` — set `is_blacklisted=true` + `approval_status=BLOCKED` langsung (siapa saja yang bisa edit customer)
- `openUnblockModal()/confirmUnblock()` — buka blokir **wajib** lewat modal + alasan wajib diisi + cek permission (`Auth::user()->is_admin || Auth::user()->canAccessModule('FINACC')`), alasan dicatat sebagai jejak di `note` (bukan modal PIN statis seperti mockup lama)

## Tabel & Model yang Disentuh
- `App\Models\Master\CustCustomer` → `tm2mst.cust_customer` (koneksi `mst`, PK `id_customer`). Const: `APPROVAL_STATUSES`, `CREDIT_RATINGS`, `KYC_STATUSES`.
- `App\Models\Master\CustCustomerBranch` → `tm2mst.cust_customer_branch`
- `App\Models\Master\CustCustomerContact` → `tm2mst.cust_customer_contact`
- `App\Models\Master\CustCategory` → `tm2mst.cust_category` (dropdown `category`)
- `App\Models\Master\FinPaymentTerm` → `tm2mst.fin_payment_term` (dropdown `payment_term`)
- `App\Models\Master\OrgDivision` → `tm2mst.org_division` (dropdown `sales_division_id`)
- Komponen `<livewire:address-picker>` — pemilih wilayah (kelurahan/kecamatan/kota/provinsi/kode pos), dipakai 2x (alamat utama & alamat cabang)

## Alur / Business Logic Penting
- Approval status (`DRAFT` → dst, lihat `CustCustomer::APPROVAL_STATUSES`) diubah langsung via tombol pill di header form, terpisah dari alur blokir.
- Blokir/unblock: blokir = aksi 1-klik (siapa saja boleh edit customer boleh blokir); unblock **wajib** modal + alasan + permission gate (Admin atau modul FINACC) — desain ulang dari PIN statis "8888" di mockup lama (dianggap ilusi keamanan).
- 1 default per customer untuk kontak tambahan maupun cabang tambahan (`is_default`), diberlakukan di level Service (unset semua lalu set 1).
- Kontak & cabang tambahan hanya bisa ditambah setelah customer tersimpan (`$editingId` harus ada) — customer baru harus disimpan dulu.
- Sesuai CLAUDE.md §4 "Costing rahasia (Sisi A)": data finansial (COA/GL) bersifat internal, publish diperlukan untuk dibagikan ke Sales.

## Catatan
- Placeholder "Akun Rekonsiliasi Piutang" (Chart of Accounts) di tab Sales & Finance sengaja **belum fungsional** — input `disabled`, menunggu pembahasan desain skema COA terpisah, komentar eksplisit di kode: "PLACEHOLDER BELUM FUNGSIONAL — menunggu pembahasan skema COA dgn Jo".
- Tab History cuma menampilkan jejak dibuat/diubah terakhir (`created_at/by`, `updated_at/by`) — bukan audit trail per-field lengkap, dicatat eksplisit di UI: "Riwayat detail per-field (audit trail lengkap) belum tersedia".
- "Sales Organization"/"Distribution Channel" dari mockup lama sengaja **tidak dibawa** (dianggap SAP-isme tak relevan untuk percetakan, satu-satunya bagian `customer_spec.md` lama yang masih relevan).
- ~22 kolom baru ditambahkan ke `cust_customer` di migration `2026_08_19_000019`.
