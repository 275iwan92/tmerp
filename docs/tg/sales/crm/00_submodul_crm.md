# Sub-modul: CRM (Customer 360, Activities, Complaint)

> Ditulis 2026-10-16 (kelompok A). Halaman ini hanya merangkum dan menautkan; fakta tabel/kolom ada di kartu.

- **Modul**: Sales > CRM (`SALES_CRMGRP`). Menu (FAKTA, `sys_app_menu` mockup, aktif): Customer 360 (`v2:crm-customer`), Activities & Follow-up (`v2:crm-activity`), Complaint & Return (`v2:crm-complaint`), grup Master Data (Master Customer `v2:md-customer`, Sales Person `v2:md-sales-person`), grup Report (Win-Rate Sales `v2:sales-win-rate`). Jawaban mutu komplain ada di menu Production: Complaint Review (QA) (`v2:qc-complaint-review`).
- **Tujuan**: membangun hubungan dengan customer: merangkum riwayat (Customer 360, hanya membaca), mencatat kontak dan tindak lanjut, dan menerima komplain/retur yang mutunya dijawab QA.
- **Zona beku**: tidak ada zona beku khusus CRM selain flow dokumen (aturan beku semua alur).
- **Aturan**: A.21b (Sales satu grup besar; `crm_activity` per customer; komplain: informasi ke Sales, mutu ke QA; Customer 360 hanya membaca, DARI DOKUMEN indeks ringkas); A.9 (nilai rupiah hanya pemegang hak harga); A.12 (siklus Confirmed/Cancelled/Closed, dipenuhi Complaint).

## Relasi
- **sumber**: Master Customer dan kontak, Quotation, CO (baru dan arsip), DO/Surat Jalan (baru dan arsip), Production (jawaban QA).
- **dipakai_oleh**: Sales (kerja harian), QA (meja komplain), Win-Rate Sales (BELUM dicek apakah memakai aktivitas).
- **tanda**: Complaint memakai `stat_confirm/_close/_cancel`; Activity tanpa status dokumen (`next_done`); Customer 360 tanpa tabel.

## Kartu dan engine di sub-modul ini
| Jenis | Berkas | Isi |
|---|---|---|
| Konsep | `10_kartu_crm-activity.md` | `crm_activity`, follow-up, warna jatuh tempo, hak ubah |
| Konsep | `11_kartu_crm-complaint.md` | `crm_complaint`, `crm_complaint_item`, QA, Confirm/Close/Cancel |
| Konsep | `12_kartu_customer-360.md` | ringkasan hanya-baca, segmen Active/Sleeping/Prospect/No order |
| ENGINE | tidak ada | CRM tidak punya engine, jadwal, atau background (dicari: `routes/console.php`, `app/Services`, registry; nol) |

## Diagram
Tidak diperlukan (tidak ada engine).

## Panduan pemakai (UG) yang sudah ada
Belum ada berkas CRM di `docs/guides/sales/` (isi folder: 00 sampai 04, tidak ada CRM). Dicatat; tidak dibuat di tugas ini.

## Temuan lintas kartu (ringkas; rincian di kartu)
Tabel `quo_activity` sudah tidak ada tetapi kelas `QuoActivity` dan komentar masih menyebutnya; Cancel Complaint tanpa `cancelled_at/by`; segmen Customer 360 menghitung arsip DRAFT/UNKNOWN sebagai order dan CO REJECTED tidak dikecualikan; tab Customer 360 terpotong diam-diam di 100/200 baris.
