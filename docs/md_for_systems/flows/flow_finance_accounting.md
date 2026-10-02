---
name: flow_finance_accounting
description: Alur kerja Finance & Accounting -- setup COA, jurnal Kas & Bank, dan tagihan uang muka customer.
---

# Alur Finance & Accounting

## Cakupan
Dokumen ini merangkum alur kerja 3 layar Finance & Accounting yang sudah dibangun (koneksi `tm2acc`): Master Chart of Accounts (COA), Jurnal Kas & Bank (BKM/BKK/BBM/BBK), dan Tagihan Uang Muka (DP Invoice). Ketiganya berdiri sendiri secara fungsi (tidak ada 1 wizard berurutan memaksa user lewat semua layar), tapi punya dependensi data: COA harus terisi lebih dulu sebelum Kas & Bank dan DP Invoice bisa memilih akun GL yang valid.

## Tahap -> Layar -> Aktor

> **Perubahan 2026-10-02:** baris 2b–2d di bawah menggambarkan layar Down Payment lama (`finance.dp-invoice`) yang
> **sudah diarsipkan**. Uang muka sekarang dikerjakan di layar **Customer Invoice** (`v2:customer-invoice`): tagihan
> uang muka = permintaan bayar tanpa jurnal, jurnal lahir saat uang diterima (Record Receipt), dan potongan ke invoice
> terjadi saat invoice di-Confirm. Ketentuan yang berlaku: `customerinvoice_accounting.md`.

| Tahap | Layar | Route | Aktor | Keterangan |
|---|---|---|---|---|
| 1. Setup struktur akun | Master Chart of Accounts | `finance.coa` | Staf Finance/Accounting | Definisikan akun header (kelompok) & akun postable (bisa dijurnal), tipe (ASSET/LIABILITY/dst), saldo normal, flag rekonsiliasi (AR/AP), default cost center, skema pajak. Prasyarat bagi 2 layar berikutnya — Kas & Bank dan DP Invoice sama-sama melakukan lookup `FinAccount::where('account_code', ...)`. |
| 2a. Jurnal Kas & Bank manual | Jurnal Kas & Bank (BKM/BKK/BBM/BBK) | `finance.cash-bank` | Staf Finance/Kasir | Entri transaksi kas/bank manual (bukan dari sub-modul lain). Draft dulu (`fin_voucher.status=DRAFT`, bisa diedit berkali-kali) → Post manual (`JournalPostingService::postDraft()`) → terkunci. Independen dari alur DP Invoice. |
| 2b. Tagihan Uang Muka (DP) — Terbitkan | Tagihan Uang Muka (DP) | `finance.dp-invoice` | Staf Finance/Sales | Event 1: pilih customer, nilai kontrak, metode DP (persen/nominal), terbitkan `fin_invoice` (`CUSTOMER_DP`) + jurnal langsung POSTED (AR debit, Uang Muka kredit, PPN Keluaran kredit jika kena pajak). |
| 2c. DP — Catat Pembayaran | Tagihan Uang Muka (DP), tombol "Catat Pembayaran" | `finance.dp-invoice` | Staf Finance | Event 2: setelah customer transfer, tandai `payment_status=PAID`, jurnal Bank debit vs AR kredit. Ini gate produksi — pesan UI eksplisit "Work Order tidak bisa dibuka sampai DP ini lunas" (gate sesungguhnya ada di modul Production, bukan di layar ini). |
| 2d. DP — Alokasikan ke Invoice Final | Tagihan Uang Muka (DP), tombol "Alokasikan" | `finance.dp-invoice` | Staf Finance | Event 4: setelah DP lunas dan ada invoice final (dari Delivery, event 3 — BELUM ADA layarnya, saat ini invoice final harus di-insert manual di luar alur), potong DP ke invoice final. Jurnal DP debit + PPN debit (jika ada) vs AR kredit; `paid_amount` invoice final bertambah, status jadi PARTIAL/PAID. |

## Auto-jurnal
- **Kas & Bank**: posting manual via `JournalPostingService::postDraft()` — satu-satunya layar dalam batch ini yang benar-benar memakai siklus DRAFT → POST eksplisit oleh user.
- **DP Invoice**: SEMUA 3 event (Terbitkan/Bayar/Alokasi) langsung menulis `fin_voucher` dengan `status=POSTED` di dalam transaksi `DB::connection('tm2acc')->transaction()` — tidak lewat draft, dan tidak memanggil `JournalPostingService` (jurnal ditulis manual inline per event; komentar kode menyebut service tsb baru digeneralisasi belakangan dari pola yang awalnya hidup di file DP Invoice ini).
- Kode akun dipakai hardcode via lookup `account_code` (mis. `112100`/`112200` AR domestik/import, `213200` Uang Muka, `212100` PPN Keluaran, `111200` Bank) — bergantung penuh pada COA sudah terisi kode-kode tersebut.

## Gap / Belum Terhubung
- **DP → Customer Order**: `co_reference` di DP Invoice masih teks bebas, belum di-rewiring ke `co_order.id_co` asli meski tabel `co_order` sudah ada sejak 2026-08-20.
- **Event 3 (invoice final per shipment)**: bagian dari modul Delivery Order yang belum ada — Alokasi DP saat ini diuji dengan invoice final yang di-insert manual.
- **Purchase DP** (arah sebaliknya, simetris dengan Sales DP): belum dibangun.
- Tidak ditemukan modul Cashier/GL Trial Balance dalam batch 3 layar ini — cakupan lebih luas ada di memory proyek (`project_finance_scope_decision.md`) tapi di luar scope dokumentasi 3 layar ini.
